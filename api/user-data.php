<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_email'])) {
    echo json_encode(['loggedIn' => false]);
    exit;
}

$email = $_SESSION['user_email'];

try {
    $stmt = $pdo->prepare("SELECT fullname, email FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode([
            'loggedIn' => true,
            'fullName' => $user['fullname'],
            'email' => $user['email']
        ]);
    } else {
        echo json_encode(['loggedIn' => false]);
    }
} catch (Exception $e) {
    echo json_encode(['loggedIn' => false, 'error' => $e->getMessage()]);
}
?>