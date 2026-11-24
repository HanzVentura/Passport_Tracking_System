<?php
// Handles submitting and fetching customer support enquiries
header('Content-Type: application/json');
// Connect to MySQL database via centralized connection
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/audit-helper.php';
require_once __DIR__ . '/auth-helper.php';
startSecureSession();
$method = $_SERVER['REQUEST_METHOD'];

// Handles POST requests to submit a new enquiry or update/reply
if ($method === 'POST') {
    requireLogin();
    requireCsrfToken();
    $input = json_decode(file_get_contents('php://input'), true);
    $sessionEmail = getSessionEmail();
    $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
    
    $enquiryId = $input['enquiryId'] ?? null;
    if ($enquiryId) {
        if (!$isAdmin) {
            $owner = $pdo->prepare("SELECT email FROM enquiries WHERE id = ?");
            $owner->execute([$enquiryId]);
            $row = $owner->fetch(PDO::FETCH_ASSOC);
            if (!$row || strtolower((string)$row['email']) !== $sessionEmail) {
                jsonError('You do not own this enquiry.', 403);
            }
        }

        $newReply = $input['reply'] ?? null;
        $status = $isAdmin ? ($input['status'] ?? 'Open') : 'Open';
        
        try {
            // Fetch existing enquiry to append to conversation history
            $stmt = $pdo->prepare("SELECT reply FROM enquiries WHERE id = ?");
            $stmt->execute([$enquiryId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $existingReply = $existing['reply'] ?? '';
            $timestamp = date('Y-m-d H:i:s');
            
            // Format the new message neatly into the conversation log with an HTML divider
            if ($newReply) {
                $formattedReply = (strpos($newReply, 'Admin (') === 0 || strpos($newReply, 'Member (') === 0) 
                    ? $newReply 
                    : "Admin ($timestamp):\n$newReply";

                if ($existingReply && $existingReply !== 'Awaiting administrator response...') {
                    $combinedReply = $existingReply . "\n\n<hr style=\"border: none; border-top: 1px solid #cbd5e1; margin: 10px 0;\">\n\n" . $formattedReply;
                } else {
                    $combinedReply = $formattedReply;
                }
                $updateStmt = $pdo->prepare("UPDATE enquiries SET reply = ?, status = ? WHERE id = ?");
                $updateStmt->execute([$combinedReply, $status, $enquiryId]);
            } else {
                // Just update status if no text reply was sent
                $updateStmt = $pdo->prepare("UPDATE enquiries SET status = ? WHERE id = ?");
                $updateStmt->execute([$status, $enquiryId]);
            }
            logAudit($pdo, 'ENQUIRY_UPDATED', 'enquiry', (string)$enquiryId, null, $status, $newReply ? 'Enquiry reply added.' : 'Enquiry status changed.', getSessionEmail(), $_SESSION['user_role'] ?? null);
            
            echo json_encode(['success' => true, 'message' => 'Enquiry updated successfully.']);
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Unable to update enquiry history.']);
            exit;
        }
    }

    $email = $input['email'] ?? $_POST['email'] ?? null;
    $email = $email ? strtolower(trim($email)) : $sessionEmail;
    if (!$isAdmin && $email !== $sessionEmail) {
        jsonError('You cannot submit an enquiry for another member.', 403);
    }
    $applicationId = $input['applicationId'] ?? $input['appId'] ?? $_POST['applicationId'] ?? $_POST['appId'] ?? '';
    $subject = $input['subject'] ?? $_POST['subject'] ?? '';
    $message = $input['message'] ?? $_POST['message'] ?? '';
    
    if (!$applicationId || !$subject || !$message) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Application ID, subject, and message are required.']);
        exit;
    }
    try {
        $appOwner = $pdo->prepare("SELECT id FROM applications WHERE application_id = ? AND email = ?");
        $appOwner->execute([$applicationId, $sessionEmail]);
        if (!$isAdmin && !$appOwner->fetch()) {
            jsonError('The application is not associated with your account.', 403);
        }
        $stmt = $pdo->prepare("INSERT INTO enquiries (email, application_id, subject, message, status, reply) VALUES (?, ?, ?, ?, 'Open', 'Awaiting administrator response...')");
        $stmt->execute([$email, $applicationId, $subject, $message]);
       
        http_response_code(201);
        logAudit($pdo, 'ENQUIRY_SUBMITTED', 'enquiry', (string)$pdo->lastInsertId(), null, 'Open', 'Support enquiry submitted.', $email, 'member');
        echo json_encode([
            'success' => true,
            'enquiry' => [
                'id' => $pdo->lastInsertId(),
                'email' => $email,
                'appId' => $applicationId,
                'subject' => $subject,
                'message' => $message,
                'status' => 'Open',
                'reply' => 'Awaiting administrator response...'
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to submit enquiry.']);
    }
}
// Handles GET requests to list all enquiries
else if ($method === 'GET') {
    requireLogin();
    try {
        $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
        if ($isAdmin) {
            $stmt = $pdo->query("SELECT id, email, application_id AS appId, subject, message, status, reply, created_at AS date FROM enquiries ORDER BY id DESC");
            $enquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->prepare("SELECT id, email, application_id AS appId, subject, message, status, reply, created_at AS date FROM enquiries WHERE email = ? ORDER BY id DESC");
            $stmt->execute([getSessionEmail()]);
            $enquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(['success' => true, 'enquiries' => $enquiries]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to fetch enquiries.']);
    }
}
?>