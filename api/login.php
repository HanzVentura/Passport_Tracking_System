<?php
session_start();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';
$passcode = $data['passcode'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
    exit;
}

try {
    $dbPath = __DIR__ . '/../database.db';
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    // Check if 2FA is enabled for this user
    if (isset($user['two_factor_enabled']) && $user['two_factor_enabled'] == 1) {
        if (empty($passcode)) {
            echo json_encode([
                'success' => false,
                'require_2fa' => true,
                'message' => '2FA required. Please enter your 6-digit passcode.'
            ]);
            exit;
        }

        if ($passcode !== $user['passcode']) {
            echo json_encode(['success' => false, 'message' => 'Incorrect 6-digit passcode.']);
            exit;
        }
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];

    echo json_encode(['success' => true, 'message' => 'Login successful! Redirecting...']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>