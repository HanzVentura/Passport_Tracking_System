<?php
header('Content-Type: application/json');
$dbPath = __DIR__ . '/../database.db';
try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $email = $_GET['email'] ?? '';
    $applicationId = $_GET['applicationId'] ?? '';
    $stmt = null;
    
    // Require an explicit applicationId or email; remove the wildcard global fallback
    if ($applicationId) {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE application_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$applicationId]);
    } else if ($email) {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE email = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No application ID or email provided.']);
        exit;
    }
    
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($application) {
        $appId = $application['application_id'] ?: ('PH-' . rand(100000, 999900));
        echo json_encode([
            'success' => true,
            'application' => [
                'application_id' => $appId,
                'applicationId' => $appId,
                'application_type' => $application['application_type'] ?? 'New First-Time Application',
                'applicationType' => $application['application_type'] ?? 'New First-Time Application',
                'status' => $application['status'] ?? 'Pending Payment',
                'email' => $application['email'] ?? $email,
                'appointmentDate' => $application['appointment_date'] ?? null,
                'appointmentLocation' => $application['appointment_location'] ?? null,
                'createdAt' => $application['created_at'] ?? date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No active application found.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
?>