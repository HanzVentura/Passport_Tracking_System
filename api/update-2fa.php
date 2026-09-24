<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in (matching the session naming convention used by login.php)
$userId = $_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? null;
if ($userId === null) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to update two-factor authentication.']);
    exit;
}

// Get the JSON input
$data = json_decode(file_get_contents('php://input'), true) ?? [];
$enabled = isset($data['enabled']) ? (bool)$data['enabled'] : false;
$pin = $data['pin'] ?? '';

// Validate PIN if 2FA is being enabled
if ($enabled) {
    if (!is_string($pin) || !preg_match('/^[0-9]{6}$/', $pin)) {
        echo json_encode(['success' => false, 'message' => 'PIN must be exactly 6 digits.']);
        exit;
    }
    $plainPasscode = $pin;
} else {
    $plainPasscode = null; // Clear passcode if 2FA is disabled
}

try {
    // Connect to MySQL database via centralized connection
    require_once __DIR__ . '/../db.php';

    $stmt = $pdo->prepare(
        'UPDATE users SET two_factor_enabled = :enabled, passcode = :pin WHERE id = :user_id'
    );
    $stmt->execute([
        ':enabled' => $enabled ? 1 : 0,
        ':pin' => $plainPasscode,
        ':user_id' => $userId
    ]);
    
    echo json_encode(['success' => true, 'message' => '2FA settings updated successfully!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>