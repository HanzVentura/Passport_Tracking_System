<?php
header('Content-Type: application/json');
require_once __DIR__ . '/auth-helper.php';
startSecureSession();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_email'])) {
    echo json_encode(['loggedIn' => false]);
    exit;
}

$email = $_SESSION['user_email'];

if (($_SESSION['user_role'] ?? '') === 'admin') {
    echo json_encode([
        'loggedIn' => true,
        'fullName' => $_SESSION['admin_name'] ?? 'Administrator',
        'email' => $email,
        'two_factor_enabled' => 0,
        'csrfToken' => getCsrfToken()
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT fullname, email, two_factor_enabled FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode([
            'loggedIn' => true,
            'fullName' => $user['fullname'],
            'email' => $user['email'],
            'two_factor_enabled' => (int)$user['two_factor_enabled'],
            'csrfToken' => getCsrfToken()
        ]);
    } else {
        echo json_encode(['loggedIn' => false]);
    }
} catch (Exception $e) {
    error_log('user-data.php error: ' . $e->getMessage());
    echo json_encode(['loggedIn' => false]);
}
?>