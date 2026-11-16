<?php
// Handles rescheduling or canceling existing passport applications
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$email = $input['email'] ?? '';
$applicationId = $input['applicationId'] ?? $input['appId'] ?? '';

if (!$email && !$applicationId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email or application ID is required.']);
    exit;
}

try {
    if ($action === 'cancel') {
        // Completely remove or cancel the application record
        if ($applicationId) {
            $stmt = $pdo->prepare("DELETE FROM applications WHERE application_id = ?");
            $stmt->execute([$applicationId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM applications WHERE email = ?");
            $stmt->execute([$email]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Application has been successfully canceled and removed.'
        ]);
        exit;
    }
    
    if ($action === 'reschedule') {
        $newDate = $input['appointmentDate'] ?? '';
        $newTime = $input['appointmentTime'] ?? '';
        $newLocation = $input['appointmentLocation'] ?? '';

        if (!$newDate || !$newTime) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'New appointment date and time are required.']);
            exit;
        }

        // Update appointment details in the database
        if ($applicationId) {
            $stmt = $pdo->prepare("UPDATE applications SET appointment_date = ?, appointment_time = ?, appointment_location = COALESCE(?, appointment_location), status = 'Appointment Rescheduled', updated_at = CURRENT_TIMESTAMP WHERE application_id = ?");
            $stmt->execute([$newDate, $newTime, $newLocation, $applicationId]);
        } else {
            $stmt = $pdo->prepare("UPDATE applications SET appointment_date = ?, appointment_time = ?, appointment_location = COALESCE(?, appointment_location), status = 'Appointment Rescheduled', updated_at = CURRENT_TIMESTAMP WHERE email = ?");
            $stmt->execute([$newDate, $newTime, $newLocation, $email]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Appointment successfully rescheduled.',
            'appointmentDate' => $newDate,
            'appointmentTime' => $newTime
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
?>