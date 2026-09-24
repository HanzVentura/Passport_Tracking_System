<?php
// Handles creating or updating passport applications
header('Content-Type: application/json');
// Connect to MySQL database via centralized connection
require_once __DIR__ . '/../db.php';
// Reads data sent from the frontend
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? $_POST['email'] ?? '';
$applicationId = $input['applicationId'] ?? $input['appId'] ?? $_POST['applicationId'] ?? $_POST['appId'] ?? '';
$applicationType = $input['applicationType'] ?? $_POST['applicationType'] ?? 'New First-Time Application';
if (!$email || !$applicationId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and application ID are required.']);
    exit;
}
try {
    // Tries to update an existing application for this email
    $updateStmt = $pdo->prepare("UPDATE applications SET application_id = ?, application_type = ?, status = 'Form Submitted', updated_at = CURRENT_TIMESTAMP WHERE email = ?");
    $updateStmt->execute([$applicationId, $applicationType, $email]);
   
    if ($updateStmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'applicationId' => $applicationId,
            'applicationType' => $applicationType,
            'status' => 'Form Submitted'
        ]);
        exit;
    }
    // Inserts a new application record if none existed yet with rubric-compliant status
    $insertStmt = $pdo->prepare("INSERT INTO applications (email, application_id, application_type, status) VALUES (?, ?, ?, 'Form Submitted')");
    $insertStmt->execute([$email, $applicationId, $applicationType]);
   
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'id' => $pdo->lastInsertId(),
        'applicationId' => $applicationId,
        'applicationType' => $applicationType,
        'status' => 'Form Submitted'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
?>