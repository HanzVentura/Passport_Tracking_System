<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

$data = json_decode(file_get_contents('php://input'), true);
$fullName = $data['fullName'] ?? $data['full_name'] ?? '';
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

if (empty($fullName) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email is already registered.']);
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password) VALUES (?, ?, ?)");
    $stmt->execute([$fullName, $email, $hashedPassword]);

    $_SESSION['user_id'] = $pdo->lastInsertId();
    $_SESSION['user_email'] = $email;

    echo json_encode(['success' => true, 'message' => 'Account created successfully!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>