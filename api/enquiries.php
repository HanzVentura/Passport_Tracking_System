<?php
// Handles submitting and fetching customer support enquiries
header('Content-Type: application/json');
// Connect to MySQL database via centralized connection
require_once __DIR__ . '/../db.php';
$method = $_SERVER['REQUEST_METHOD'];

// Handles POST requests to submit a new enquiry or update/reply
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $enquiryId = $input['enquiryId'] ?? null;
    if ($enquiryId) {
        $newReply = $input['reply'] ?? null;
        $status = $input['status'] ?? 'Open';
        
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
            
            echo json_encode(['success' => true, 'message' => 'Enquiry updated successfully.']);
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Unable to update enquiry history.']);
            exit;
        }
    }

    $email = $input['email'] ?? $_POST['email'] ?? null;
    $applicationId = $input['applicationId'] ?? $input['appId'] ?? $_POST['applicationId'] ?? $_POST['appId'] ?? '';
    $subject = $input['subject'] ?? $_POST['subject'] ?? '';
    $message = $input['message'] ?? $_POST['message'] ?? '';
    
    if (!$applicationId || !$subject || !$message) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Application ID, subject, and message are required.']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO enquiries (email, application_id, subject, message, status, reply) VALUES (?, ?, ?, ?, 'Open', 'Awaiting administrator response...')");
        $stmt->execute([$email, $applicationId, $subject, $message]);
       
        http_response_code(201);
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
    try {
        $stmt = $pdo->query("SELECT id, email, application_id AS appId, subject, message, status, reply, created_at AS date FROM enquiries ORDER BY id DESC");
        $enquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'enquiries' => $enquiries]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to fetch enquiries.']);
    }
}
?>