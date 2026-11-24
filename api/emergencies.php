<?php
header('Content-Type: application/json');
require_once __DIR__ . '/auth-helper.php';
startSecureSession();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/audit-helper.php';

// Ensure emergencies table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS emergencies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        appId VARCHAR(100) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        reply TEXT DEFAULT 'Awaiting consular officer response...',
        status VARCHAR(50) DEFAULT 'Open',
        date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    requireLogin();
    try {
        if (($_SESSION['user_role'] ?? '') === 'admin') {
            $stmt = $pdo->query("SELECT * FROM emergencies ORDER BY id DESC");
            $emergencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM emergencies WHERE email = ? ORDER BY id DESC");
            $stmt->execute([getSessionEmail()]);
            $emergencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(['success' => true, 'emergencies' => $emergencies]);
    } catch (Exception $e) {
        error_log('emergencies.php read error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Unable to load emergency reports.']);
    }
    exit;
}

if ($method === 'POST') {
    requireLogin();
    requireCsrfToken();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['emergencyId'])) {
        $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
        if (!$isAdmin) {
            $owner = $pdo->prepare("SELECT email FROM emergencies WHERE id = ?");
            $owner->execute([$data['emergencyId']]);
            $row = $owner->fetch(PDO::FETCH_ASSOC);
            if (!$row || strtolower((string)$row['email']) !== getSessionEmail()) {
                jsonError('You do not own this emergency report.', 403);
            }
            $newStatus = null;
        }
        $emergencyId = $data['emergencyId'];
        $newReply = $data['reply'] ?? null;
        $newStatus = $data['status'] ?? null;

        try {
            if ($newReply) {
                // Fetch current reply thread
                $stmt = $pdo->prepare("SELECT reply FROM emergencies WHERE id = ?");
                $stmt->execute([$emergencyId]);
                $curr = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $existingReply = $curr['reply'] ?? '';
                if ($existingReply === 'Awaiting consular officer response...' || empty($existingReply)) {
                    $updatedReply = $newReply;
                } else {
                    // Properly append with line breaks so full chat history is preserved
                    $updatedReply = $existingReply . "\n\n" . $newReply;
                }

                $updateStmt = $pdo->prepare("UPDATE emergencies SET reply = ? WHERE id = ?");
                $updateStmt->execute([$updatedReply, $emergencyId]);
            }

            if ($newStatus) {
                $statusStmt = $pdo->prepare("UPDATE emergencies SET status = ? WHERE id = ?");
                $statusStmt->execute([$newStatus, $emergencyId]);
            }
            logAudit($pdo, 'EMERGENCY_UPDATED', 'emergency', (string)$emergencyId, null, $newStatus ?: null, $newReply ? 'Administrator emergency reply added.' : 'Emergency status changed.', $data['actorEmail'] ?? null, $data['actorRole'] ?? 'admin');

            echo json_encode(['success' => true, 'message' => 'Emergency updated successfully.']);
        } catch (Exception $e) {
            error_log('emergencies.php update error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Unable to update emergency report.']);
        }
        exit;
    }

    $email = getSessionEmail() ?? strtolower(trim((string)($data['email'] ?? '')));
    $appId = $data['applicationId'] ?? ('EMG-' . random_int(100000, 999900));
    $subject = $data['subject'] ?? 'Urgent Consular Assistance';
    $message = $data['message'] ?? '';

    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Emergency details are required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO emergencies (email, appId, subject, message, reply, status) VALUES (?, ?, ?, ?, 'Awaiting consular officer response...', 'Open')");
        $stmt->execute([$email, $appId, $subject, $message]);
        logAudit($pdo, 'EMERGENCY_SUBMITTED', 'emergency', (string)$pdo->lastInsertId(), null, 'Open', 'Emergency request submitted.', $email, 'member');

        echo json_encode(['success' => true, 'message' => 'Emergency request filed successfully.']);
    } catch (Exception $e) {
        error_log('emergencies.php submit error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Unable to submit emergency report.']);
    }
    exit;
}
?>