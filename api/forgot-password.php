<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

require_once __DIR__ . '/../db.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
$email = strtolower(trim($data['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please enter a valid email address.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE LOWER(email) = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        error_log('[Simulated Forgot Password] Reset requested for: ' . $user['email']);
    } else {
        error_log('[Simulated Forgot Password] Reset requested for non-existent email: ' . $email);
    }

    echo json_encode([
        'success' => true,
        'email' => $email,
        'message' => 'If an account exists for ' . $email . ', we\'ve sent password reset instructions. Please check your email.'
    ]);
} catch (PDOException $e) {
    error_log('[Forgot Password DB Error] ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Unable to process request right now. Please try again.'
    ]);
}