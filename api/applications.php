<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';
// If no action is specified in GET request, default to listing all applications for admin convenience
if (empty($action) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = 'list_all';
}
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

// Status hierarchy for irreversible pipeline progression
// Higher rank = further in the pipeline
$STATUS_RANKS = [
    'Form Submitted' => 1,
    'Payment Pending' => 2,
    'Paid' => 3,
    'Appointment Scheduled' => 4,
    'Processing' => 5,
    'Biometrics Received' => 6,
    'Under Consular Review' => 7,
    'Under Review' => 7,      // Alias for same rank
    'Review Completed' => 7,  // Alias for same rank
    'In Printing' => 8,
    'Dispatched' => 9,
    'Ready for Release' => 9, // Alias for same rank
    'Cancelled' => 0          // Terminal state, not part of forward pipeline
];

/**
 * Validates if a status transition is allowed (forward-only, no skipping backwards)
 * @param string $currentStatus Current status in database
 * @param string $newStatus Target status to transition to
 * @return array ['valid' => bool, 'message' => string, 'currentRank' => int, 'newRank' => int]
 */
function validateStatusTransition($currentStatus, $newStatus, $statusRanks) {
    $currentRank = $statusRanks[$currentStatus] ?? 0;
    $newRank = $statusRanks[$newStatus] ?? 0;

    // Unknown status - reject
    if ($newRank === 0 && $newStatus !== 'Cancelled') {
        return [
            'valid' => false,
            'message' => "Invalid target status: $newStatus",
            'currentRank' => $currentRank,
            'newRank' => $newRank
        ];
    }

    // Allow transition to Cancelled from any state (terminal)
    if ($newStatus === 'Cancelled') {
        return [
            'valid' => true,
            'message' => 'Status updated to Cancelled.',
            'currentRank' => $currentRank,
            'newRank' => $newRank
        ];
    }

    // Must move forward in rank (strictly greater)
    if ($newRank <= $currentRank) {
        return [
            'valid' => false,
            'message' => 'Invalid status transition. Status updates are irreversible and must progress forward.',
            'currentRank' => $currentRank,
            'newRank' => $newRank
        ];
    }

    // Allow skipping intermediate steps (e.g., Form Submitted -> Under Consular Review)
    // as long as it's forward progression
    return [
        'valid' => true,
        'message' => 'Valid status transition.',
        'currentRank' => $currentRank,
        'newRank' => $newRank
    ];
}

// Handle Admin Status Updates
if ($action === 'update_status' || (isset($input['status']) && isset($input['applicationId']))) {
    $applicationId = $input['applicationId'] ?? '';
    $newStatus = $input['status'] ?? '';

    if (!$applicationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Application ID is required.']);
        exit;
    }

    if (!$newStatus) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Target status is required.']);
        exit;
    }

    try {
        // First, fetch current status from database
        $stmt = $pdo->prepare("SELECT status FROM applications WHERE application_id = ?");
        $stmt->execute([$applicationId]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Application not found.']);
            exit;
        }

        $currentStatus = $app['status'] ?? 'Form Submitted';

        // Validate transition
        $validation = validateStatusTransition($currentStatus, $newStatus, $STATUS_RANKS);

        if (!$validation['valid']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $validation['message'],
                'currentStatus' => $currentStatus,
                'attemptedStatus' => $newStatus
            ]);
            exit;
        }

        // Valid transition - proceed with update
        $stmt = $pdo->prepare("UPDATE applications SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE application_id = ?");
        $stmt->execute([$newStatus, $applicationId]);

        echo json_encode([
            'success' => true,
            'applicationId' => $applicationId,
            'status' => $newStatus,
            'previousStatus' => $currentStatus,
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
   
    // Added mandatory NOT NULL fields to the INSERT query
    $insertStmt = $pdo->prepare("INSERT INTO applications (email, application_id, application_type, status, processing_type, amount, payment_status, appointment_date, appointment_time, appointment_location) VALUES (?, ?, ?, 'Form Submitted', 'Regular Processing', 950.00, 'Pending', ?, ?, ?)");
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