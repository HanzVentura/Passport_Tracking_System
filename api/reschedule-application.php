<?php
// Handles rescheduling or canceling existing passport applications
header('Content-Type: application/json');
require_once __DIR__ . '/auth-helper.php';
startSecureSession();
requireMember();
requireCsrfToken();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/audit-helper.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$email = strtolower(trim($input['email'] ?? getSessionEmail() ?? ''));
$applicationId = $input['applicationId'] ?? $input['appId'] ?? '';
if ($email !== getSessionEmail()) {
    jsonError('You cannot modify another member\'s application.', 403);
}
if ($applicationId !== '') {
    $ownerStmt = $pdo->prepare("SELECT email, status FROM applications WHERE application_id = ?");
    $ownerStmt->execute([$applicationId]);
    $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);
    if (!$owner || strtolower((string)$owner['email']) !== getSessionEmail()) {
        jsonError('You do not own this application.', 403);
    }
    $lockedStatuses = [
        'Dispatched',
        'Ready for Release',
        'Ready for Claim',
        'Ready for Pickup',
        'Claimed',
        'Released',
        'Completed'
    ];
    if (in_array((string)($owner['status'] ?? ''), $lockedStatuses, true)) {
        jsonError('This appointment can no longer be rescheduled or cancelled because the passport is ready for claim or completed.', 409);
    }
}

if ($applicationId === '' && $email !== '') {
    $statusStmt = $pdo->prepare(
        "SELECT status FROM applications
         WHERE email = ? AND status NOT IN ('Cancelled')
         ORDER BY id DESC LIMIT 1"
    );
    $statusStmt->execute([$email]);
    $latestStatus = (string)($statusStmt->fetchColumn() ?: '');
    $lockedStatuses = [
        'Dispatched',
        'Ready for Release',
        'Ready for Claim',
        'Ready for Pickup',
        'Claimed',
        'Released',
        'Completed'
    ];
    if (in_array($latestStatus, $lockedStatuses, true)) {
        jsonError('This appointment can no longer be rescheduled or cancelled because the passport is ready for claim or completed.', 409);
    }
}

if (!$email && !$applicationId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email or application ID is required.']);
    exit;
}

try {
    if ($action === 'cancel') {
        // Cancel without deleting historical application data.
        if ($applicationId) {
            $stmt = $pdo->prepare("UPDATE applications SET status = 'Cancelled', updated_at = CURRENT_TIMESTAMP WHERE application_id = ?");
            $stmt->execute([$applicationId]);
        } else {
            $stmt = $pdo->prepare("UPDATE applications SET status = 'Cancelled', updated_at = CURRENT_TIMESTAMP WHERE email = ? AND status != 'Cancelled' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$email]);
        }
        logAudit($pdo, 'APPLICATION_CANCELLED', 'application', $applicationId ?: null, null, 'Cancelled', 'Application canceled and removed.', $email, 'member');

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
            $stmt = $pdo->prepare("UPDATE applications SET appointment_date = ?, appointment_time = ?, appointment_location = COALESCE(?, appointment_location), updated_at = CURRENT_TIMESTAMP WHERE application_id = ?");
            $stmt->execute([$newDate, $newTime, $newLocation, $applicationId]);
        } else {
            $stmt = $pdo->prepare("UPDATE applications SET appointment_date = ?, appointment_time = ?, appointment_location = COALESCE(?, appointment_location), updated_at = CURRENT_TIMESTAMP WHERE email = ?");
            $stmt->execute([$newDate, $newTime, $newLocation, $email]);
        }
        logAudit(
            $pdo,
            'APPLICATION_RESCHEDULED',
            'application',
            $applicationId ?: null,
            null,
            $newDate . ' ' . $newTime,
            'Appointment rescheduled.',
            $email ?: null,
            'member'
        );

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
    error_log('reschedule-application.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update the appointment.']);
}
?>