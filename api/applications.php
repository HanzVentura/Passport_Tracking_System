<?php
// Handles creating or updating passport applications
header('Content-Type: application/json');

// Connects to the SQLite database
$dbPath = __DIR__ . '/../database.db';
try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Auto-create table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS applications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT,
        application_id TEXT,
        application_type TEXT,
        status TEXT,
        appointment_date TEXT,
        appointment_location TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Ensure application_id column exists if table was already created previously
    $columns = $pdo->query("PRAGMA table_info(applications)")->fetchAll(PDO::FETCH_ASSOC);
    $hasAppId = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'application_id') {
            $hasAppId = true;
            break;
        }
    }
    if (!$hasAppId) {
        $pdo->exec("ALTER TABLE applications ADD COLUMN application_id TEXT");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Reads data sent from the frontend
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? $_POST['email'] ?? '';
$applicationId = $input['applicationId'] ?? $input['appId'] ?? $_POST['applicationId'] ?? $_POST['appId'] ?? '';
$applicationType = $input['applicationType'] ?? $_POST['applicationType'] ?? 'New First-Time Application';

if (!$email || !$applicationId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and application ID are required.']);
    exit;
}

try {
    // Tries to update an existing application for this email
    $updateStmt = $pdo->prepare("UPDATE applications SET application_id = ?, application_type = ?, status = 'Pending Payment', updated_at = CURRENT_TIMESTAMP WHERE email = ?");
    $updateStmt->execute([$applicationId, $applicationType, $email]);

    if ($updateStmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'applicationId' => $applicationId,
            'applicationType' => $applicationType,
            'status' => 'Pending Payment'
        ]);
        exit;
    }

    // Inserts a new application record if none existed yet
    $insertStmt = $pdo->prepare("INSERT INTO applications (email, application_id, application_type, status) VALUES (?, ?, ?, 'Pending Payment')");
    $insertStmt->execute([$email, $applicationId, $applicationType]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'id' => $pdo->lastInsertId(),
        'applicationId' => $applicationId,
        'applicationType' => $applicationType,
        'status' => 'Pending Payment'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
?>