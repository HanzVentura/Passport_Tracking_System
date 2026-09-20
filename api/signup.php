<?php
// Handles new user registration with universal input parsing (FormData & JSON)
session_start();
header('Content-Type: application/json');
// Connects to the SQLite database
$dbPath = __DIR__ . '/../database.db';
$isNewDb = !file_exists($dbPath);
try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($isNewDb) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            fullname TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            two_factor_enabled INTEGER DEFAULT 0,
            passcode TEXT DEFAULT ''
        );
        CREATE TABLE IF NOT EXISTS applications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            application_id TEXT,
            email TEXT NOT NULL,
            application_type TEXT,
            status TEXT,
            processing_type TEXT,
            amount REAL,
            payment_status TEXT,
            payment_method TEXT,
            appointment_date TEXT,
            appointment_location TEXT,
            created_at TEXT,
            updated_at TEXT
        );
        CREATE TABLE IF NOT EXISTS enquiries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            application_id INTEGER,
            subject TEXT,
            message TEXT,
            status TEXT,
            reply TEXT,
            created_at TEXT
        );");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}
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