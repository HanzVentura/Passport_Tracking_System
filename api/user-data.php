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
    $columnsStmt = $pdo->query("SHOW COLUMNS FROM users");
    $columns = $columnsStmt ? $columnsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    $nicknameColumn = in_array('nickname', $columns, true) ? 'nickname' : "NULL AS nickname";
    $photoColumn = in_array('profile_photo', $columns, true) ? 'profile_photo' : "NULL AS profile_photo";

    $stmt = $pdo->prepare("SELECT fullname, email, two_factor_enabled, {$nicknameColumn}, {$photoColumn} FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $displayName = trim((string)($user['nickname'] ?? ''));
        if ($displayName === '') {
            $displayName = trim((string)$user['fullname']);
        }
        echo json_encode([
            'loggedIn' => true,
            'fullName' => $displayName,
            'fullname' => $user['fullname'],
            'email' => $user['email'],
            'nickname' => $user['nickname'],
            'profile_photo' => $user['profile_photo'],
            'user' => [
                'fullname' => $user['fullname'],
                'email' => $user['email'],
                'nickname' => $user['nickname'],
                'profile_photo' => $user['profile_photo']
            ],
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