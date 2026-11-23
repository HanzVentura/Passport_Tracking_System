<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

$sessionEmail = isset($_SESSION['user_email']) ? trim((string)$_SESSION['user_email']) : '';

if ($sessionEmail === '') {
    echo json_encode([
        'loggedIn' => false,
        'fullName' => '',
        'fullname' => '',
        'nickname' => '',
        'profile_photo' => '',
        'email' => '',
        'user' => null
    ]);
    exit;
}

try {
    $columnsStmt = $pdo->query("SHOW COLUMNS FROM users");
    $columns = $columnsStmt ? $columnsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    if (!in_array('nickname', $columns, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN nickname VARCHAR(60) NULL");
    }
    if (!in_array('profile_photo', $columns, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL");
    }

    $stmt = $pdo->prepare("SELECT id, fullname, email, nickname, profile_photo FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$sessionEmail]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode([
            'loggedIn' => false,
            'fullName' => '',
            'fullname' => '',
            'nickname' => '',
            'profile_photo' => '',
            'email' => $sessionEmail,
            'user' => null
        ]);
        exit;
    }

    $fullName = trim((string)($user['fullname'] ?? ''));
    $nickname = trim((string)($user['nickname'] ?? ''));
    $profilePhoto = trim((string)($user['profile_photo'] ?? ''));
    $email = trim((string)($user['email'] ?? $sessionEmail));

    echo json_encode([
        'loggedIn' => true,
        'fullName' => $fullName,
        'fullname' => $fullName,
        'nickname' => $nickname,
        'profile_photo' => $profilePhoto,
        'email' => $email,
        'user' => [
            'id' => $user['id'],
            'fullname' => $fullName,
            'email' => $email,
            'nickname' => $nickname,
            'profile_photo' => $profilePhoto
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'loggedIn' => false,
        'message' => 'Failed to load user data: ' . $e->getMessage()
    ]);
}