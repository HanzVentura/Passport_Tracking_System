<?php
// Handles new user registration with universal input parsing (FormData & JSON)
session_start();
header('Content-Type: application/json');

// Connect to MySQL database via centralized connection
require_once __DIR__ . '/../db.php';

// Read raw JSON input if sent as JSON
$jsonInput = json_decode(file_get_contents('php://input'), true) ?? [];

// Capture fields from $_POST (FormData) or JSON payload fallback
$fullName = $_POST['fullname'] ?? $_POST['fullName'] ?? $_POST['name'] ?? $jsonInput['fullname'] ?? $jsonInput['fullName'] ?? $jsonInput['name'] ?? '';
$email = $_POST['email'] ?? $jsonInput['email'] ?? '';
$password = $_POST['password'] ?? $jsonInput['password'] ?? '';

// Validate that all fields are present
if (empty($fullName) || empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please fill in all required fields.',
        'debug' => [
            'received_post' => $_POST,
            'received_json' => $jsonInput
        ]
    ]);
    exit;
}

try {
    // Checks if email is already registered
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->execute([$email]);
    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This email address is already registered. Please log in instead.']);
        exit;
    }

    // Hashes password and inserts new user matching the 'fullname' schema
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
   
    $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password) VALUES (?, ?, ?)");
    $stmt->execute([$fullName, $email, $hashedPassword]);
    $userId = $pdo->lastInsertId();

    // Sets session data
    $_SESSION['user'] = [
        'id' => $userId,
        'name' => $fullName,
        'email' => $email
    ];

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully!',
        'user' => $_SESSION['user']
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
}
?>