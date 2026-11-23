<?php
header('Content-Type: application/json');
require_once __DIR__ . '/auth-helper.php';
startSecureSession();
requireMember();
requireCsrfToken();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/audit-helper.php';

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
    $storedPasscode = password_hash($pin, PASSWORD_BCRYPT);
} else {
    $storedPasscode = null;
}

try {
    // Connect to MySQL database via centralized connection

    $stmt = $pdo->prepare(
        'UPDATE users SET two_factor_enabled = :enabled, passcode = :pin WHERE id = :user_id'
    );
    $stmt->execute([
        ':enabled' => $enabled ? 1 : 0,
        ':pin' => $storedPasscode,
        ':user_id' => $userId
    ]);
    $actorEmail = $_SESSION['user_email'] ?? null;
    logAudit(
        $pdo,
        $enabled ? '2FA_ENABLED' : '2FA_DISABLED',
        'user',
        $actorEmail,
        $enabled ? 'disabled' : 'enabled',
        $enabled ? 'enabled' : 'disabled',
        'Two-factor authentication settings changed.',
        $actorEmail,
        'member'
    );
    
    echo json_encode(['success' => true, 'message' => '2FA settings updated successfully!']);
} catch (Exception $e) {
    error_log('update-2fa.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update 2FA settings.']);
}
?>