<?php
session_start();
header('Content-Type: application/json');

// Connect to MySQL database via centralized connection
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/audit-helper.php';

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$passcode = trim($data['passcode'] ?? '');

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        logAudit($pdo, 'LOGIN_FAILED', 'user', $email, null, null, 'Invalid email or password.', $email, 'unknown');
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    // Check if 2FA is toggled ON for this user in the database
    $isTwoFactorEnabled = isset($user['two_factor_enabled']) && (int)$user['two_factor_enabled'] === 1;
    if ($isTwoFactorEnabled) {
        // If 2FA is enabled but user hasn't provided the passcode yet, request it
        if (empty($passcode)) {
            echo json_encode([
                'success' => false,
                'require_2fa' => true,
                'message' => '2FA required. Please enter your 6-digit passcode.'
            ]);
            exit;
        }
        // Validate the entered passcode against the database record
        if ($passcode !== $user['passcode']) {
            logAudit($pdo, 'LOGIN_FAILED', 'user', $email, null, null, 'Incorrect 2FA passcode.', $email, 'unknown');
            echo json_encode(['success' => false, 'message' => 'Incorrect 6-digit passcode.']);
            exit;
        }
    }

    // Regular user session setup
    unset($_SESSION['is_admin'], $_SESSION['admin_name']);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = 'member';
    
    logAudit($pdo, 'LOGIN_SUCCESS', 'user', $user['email'], null, null, 'User logged in successfully.', $user['email'], 'member');
   
    echo json_encode([
        'success' => true,
        'message' => 'Login successful! Redirecting...',
        'role' => 'member',
        'redirect' => 'dashboard.html'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>