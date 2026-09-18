<?php
// Fetches a single passport application by email
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

$email = $_GET['email'] ?? '';

if (!$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email parameter is required.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, email, application_id AS applicationId, application_type AS applicationType, status, processing_type AS processingType, COALESCE(amount, payment_amount) AS amount, payment_amount AS paymentAmount, payment_method AS paymentMethod, appointment_date AS appointmentDate, appointment_location AS appointmentLocation, created_at AS createdAt, updated_at AS updatedAt FROM applications WHERE email = ?");
    $stmt->execute([$email]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'application' => $application ?: null
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to fetch application.']);
}
?>