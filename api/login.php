<?php
// Starts a session to remember the logged-in user
session_start();
header('Content-Type: application/json');

// Connects to the SQLite database
$dbPath = __DIR__ . '/../database.db';
try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// Reads the login data sent from the frontend
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? $_POST['email'] ?? '';
$password = $input['password'] ?? $_POST['password'] ?? '';

if (!$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

try {
    // Finds the user record by email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Checks if the user exists and verifies the hashed password
    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials!']);
        exit;
    }

    // Saves the authenticated user into the session
    $_SESSION['user'] = [
        'id' => $user['id'],
        'fullName' => $user['fullname'],
        'email' => $user['email']
    ];

    echo json_encode(['success' => true, 'message' => 'Login successful!']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Server error during login.']);
}
?>