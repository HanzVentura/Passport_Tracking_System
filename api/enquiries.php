<?php
// Handles submitting and fetching customer support enquiries
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

$method = $_SERVER['REQUEST_METHOD'];

// Handles POST requests to submit a new enquiry
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? $_POST['email'] ?? null;
    $applicationId = $input['applicationId'] ?? $input['appId'] ?? $_POST['applicationId'] ?? $_POST['appId'] ?? '';
    $subject = $input['subject'] ?? $_POST['subject'] ?? '';
    $message = $input['message'] ?? $_POST['message'] ?? '';

    if (!$applicationId || !$subject || !$message) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Application ID, subject, and message are required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO enquiries (email, application_id, subject, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$email, $applicationId, $subject, $message]);

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'enquiry' => [
                'id' => $pdo->lastInsertId(),
                'email' => $email,
                'appId' => $applicationId,
                'subject' => $subject,
                'message' => $message,
                'status' => 'Pending Review',
                'reply' => 'Awaiting administrator response...'
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to submit enquiry.']);
    }
} 
// Handles GET requests to list all enquiries
else if ($method === 'GET') {
    try {
        $stmt = $pdo->query("SELECT id, email, application_id AS appId, subject, message, status, reply, created_at AS date FROM enquiries ORDER BY id DESC");
        $enquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'enquiries' => $enquiries]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to fetch enquiries.']);
    }
}
?>