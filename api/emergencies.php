<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

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
    try {
        $stmt = $pdo->query("SELECT * FROM emergencies ORDER BY id DESC");
        $emergencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'emergencies' => $emergencies]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['emergencyId'])) {
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

            echo json_encode(['success' => true, 'message' => 'Emergency updated successfully.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    $email = $data['email'] ?? ($_SESSION['user_email'] ?? '');
    $appId = $data['applicationId'] ?? ('EMG-' . rand(100000, 999900));
    $subject = $data['subject'] ?? 'Urgent Consular Assistance';
    $message = $data['message'] ?? '';

    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Emergency details are required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO emergencies (email, appId, subject, message, reply, status) VALUES (?, ?, ?, ?, 'Awaiting consular officer response...', 'Open')");
        $stmt->execute([$email, $appId, $subject, $message]);

        echo json_encode(['success' => true, 'message' => 'Emergency request filed successfully.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>