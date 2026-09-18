<?php
// Handles creating or updating passport applications
header('Content-Type: application/json');

// Connects to the SQLite database
$dbPath = __DIR__ . '/../database.db';
try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

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
    $updateStmt = $pdo->prepare("UPDATE applications SET application_id = ?, application_type = ?, status = 'Pending Payment', appointment_date = NULL, appointment_location = NULL, updated_at = CURRENT_TIMESTAMP WHERE email = ?");
    $updateStmt->execute([$applicationId, $applicationType, $email]);

    if ($updateStmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'applicationId' => $applicationId,
            'applicationType' => $applicationType,
            'status' => 'Pending Payment',
            'appointmentDate' => null,
            'appointmentLocation' => null
        ]);
        exit;
    }

    // Inserts a new application record if none existed yet
    $insertStmt = $pdo->prepare("INSERT INTO applications (email, application_id, application_type, status, appointment_date, appointment_location) VALUES (?, ?, ?, 'Pending Payment', NULL, NULL)");
    $insertStmt->execute([$email, $applicationId, $applicationType]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'id' => $pdo->lastInsertId(),
        'applicationId' => $applicationId,
        'applicationType' => $applicationType,
        'status' => 'Pending Payment',
        'appointmentDate' => null,
        'appointmentLocation' => null
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save application.']);
}
?>