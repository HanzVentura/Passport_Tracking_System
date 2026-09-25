<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

$applicationId = $_GET['applicationId'] ?? $_GET['id'] ?? '';
$email = $_GET['email'] ?? '';

if (!$applicationId && !$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Application ID or email is required.']);
    exit;
}

try {
    $stmt = null;
    if ($applicationId) {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE application_id = ? OR id = ? LIMIT 1");
        $stmt->execute([$applicationId, $applicationId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE email = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email]);
    }

    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Application record not found.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'application' => $application
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
?>