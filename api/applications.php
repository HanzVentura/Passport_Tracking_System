<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

// Handle Admin Request to List All Applications
if ($action === 'list_all') {
    try {
        $stmt = $pdo->query("SELECT * FROM applications WHERE status IS NOT NULL AND status != 'Cancelled' ORDER BY id DESC");
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'applications' => $applications]);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle Admin Status Updates
if ($action === 'update_status') {
    $applicationId = $input['applicationId'] ?? '';
    $newStatus = $input['status'] ?? '';

    $allowedStatuses = ['Form Submitted', 'Biometrics Received', 'Under Consular Review', 'In Printing', 'Dispatched'];
    if (!in_array($newStatus, $allowedStatuses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status provided.']);
        exit;
    }

    if (!$applicationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Application ID is required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE applications SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE application_id = ?");
        $stmt->execute([$newStatus, $applicationId]);

        echo json_encode([
            'success' => true,
            'applicationId' => $applicationId,
            'status' => $newStatus,
            'message' => 'Application status updated successfully.'
        ]);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
        exit;
    }
}

// Default Handling for Creating / Updating Applications
$email = $input['email'] ?? $_POST['email'] ?? '';
$applicationId = $input['applicationId'] ?? $input['appId'] ?? $_POST['applicationId'] ?? $_POST['appId'] ?? '';
$applicationType = $input['applicationType'] ?? $_POST['applicationType'] ?? 'New First-Time Application';
$appointmentDate = $input['appointmentDate'] ?? $_POST['appointmentDate'] ?? null;
$appointmentTime = $input['appointmentTime'] ?? $_POST['appointmentTime'] ?? null;
$appointmentLocation = $input['appointmentLocation'] ?? $_POST['appointmentLocation'] ?? null;

if (!$email || !$applicationId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and application ID are required.']);
    exit;
}

try {
    $updateStmt = $pdo->prepare("UPDATE applications SET application_id = ?, application_type = ?, status = 'Form Submitted', appointment_date = ?, appointment_time = ?, appointment_location = ?, updated_at = CURRENT_TIMESTAMP WHERE email = ?");
    $updateStmt->execute([$applicationId, $applicationType, $appointmentDate, $appointmentTime, $appointmentLocation, $email]);
   
    if ($updateStmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'applicationId' => $applicationId,
            'applicationType' => $applicationType,
            'status' => 'Form Submitted'
        ]);
        exit;
    }
   
    $insertStmt = $pdo->prepare("INSERT INTO applications (email, application_id, application_type, status, appointment_date, appointment_time, appointment_location) VALUES (?, ?, ?, 'Form Submitted', ?, ?, ?)");
    $insertStmt->execute([$email, $applicationId, $applicationType, $appointmentDate, $appointmentTime, $appointmentLocation]);
   
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