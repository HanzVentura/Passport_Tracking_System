<?php
header('Content-Type: application/json');
require_once __DIR__ . '/auth-helper.php';
startSecureSession();
require_once __DIR__ . '/../db.php';

try {
    $applicationId = trim($_GET['applicationId'] ?? $_GET['application_id'] ?? $_GET['id'] ?? '');
    $requestedEmail = trim($_GET['email'] ?? '');
    $sessionEmail = getSessionEmail();

    if ($requestedEmail !== '' && $sessionEmail !== null && strtolower($requestedEmail) !== $sessionEmail) {
        jsonError('You cannot view another member\'s application.', 403);
    }
    $email = $applicationId === '' ? ($sessionEmail ?: $requestedEmail) : $sessionEmail;

    if ($applicationId === '' && $email === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Application ID or email is required.'
        ]);
        exit;
    }

    if ($applicationId !== '') {
        if ($email !== '') {
            $stmt = $pdo->prepare("SELECT * FROM applications WHERE (application_id = ? OR id = ?) AND email = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$applicationId, $applicationId, $email]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM applications WHERE application_id = ? OR id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$applicationId, $applicationId]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } else {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE email = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$application) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No active application record was found.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'application' => $application
    ]);
} catch (Exception $e) {
    error_log('get-application.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load application.'
    ]);
}