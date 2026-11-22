<?php
session_start();
header('Content-Type: application/json');

$admins = require __DIR__ . '/login_config.php';

$data = json_decode(file_get_contents('php://input'), true);
$email = strtolower(trim($data['email'] ?? ''));
$password = $data['password'] ?? '';

if ($email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
    exit;
}

$admin = $admins[$email] ?? null;

if (!$admin || empty($admin['password_hash']) || !password_verify($password, $admin['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    exit;
}

// Admin session setup (no 2FA required)
$_SESSION['user_id'] = 'admin:' . $email;
$_SESSION['user_email'] = $email;
$_SESSION['user_role'] = 'admin';
$_SESSION['is_admin'] = true;
$_SESSION['admin_name'] = $admin['name'] ?? $email;

echo json_encode([
    'success' => true,
    'message' => 'Admin login successful! Redirecting...',
    'role' => 'admin',
    'name' => $_SESSION['admin_name'],
    'redirect' => 'admin.html'
]);
?>