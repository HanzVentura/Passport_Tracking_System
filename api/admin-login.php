<?php
header('Content-Type: application/json');
require_once __DIR__ . '/auth-helper.php';
startSecureSession();
require_once __DIR__ . '/../db.php';

$data = json_decode(file_get_contents('php://input'), true);
$email = strtolower(trim($data['email'] ?? ''));
$password = $data['password'] ?? '';

if ($email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
    exit;
}

$stmt = $pdo->prepare('SELECT id, name, password_hash FROM admin_users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin || !password_verify($password, $admin['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    exit;
}

// Admin session setup (no 2FA required)
$_SESSION['user_id'] = 'admin:' . $email;
$_SESSION['user_email'] = $email;
$_SESSION['user_role'] = 'admin';
$_SESSION['is_admin'] = true;
$_SESSION['admin_name'] = $admin['name'];

echo json_encode([
    'success' => true,
    'message' => 'Admin login successful! Redirecting...',
    'role' => 'admin',
    'name' => $_SESSION['admin_name'],
    'redirect' => 'admin.html'
]);
?>