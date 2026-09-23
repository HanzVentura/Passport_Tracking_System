<?php
header('Content-Type: application/json');
$dbPath = __DIR__ . '/../database.db';
try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $applicationId = $input['applicationId'] ?? '';
    $amount = $input['amount'] ?? 950;
    $processingType = $input['processingType'] ?? 'regular';
    $paymentMethod = $input['paymentMethod'] ?? 'GCash';
    
    if (!$email || !$applicationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email and Application ID are required for payment.']);
        exit;
    }
    
    // Find the specific application record matching this exact application_id
    $stmt = $pdo->prepare("SELECT appointment_date, appointment_location FROM applications WHERE application_id = ? LIMIT 1");
    $stmt->execute([$applicationId]);
    $existingApp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingApp) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Active application ID not found.']);
        exit;
    }
    
    // Ensure date and location are never empty strings
    $appointmentDate = (!empty($existingApp['appointment_date'])) ? $existingApp['appointment_date'] : date('Y-m-d');
    $appointmentLocation = (!empty($existingApp['appointment_location'])) ? $existingApp['appointment_location'] : 'ANGELES';
    
    // Update that EXACT application record to Paid
    $update = $pdo->prepare("UPDATE applications SET status = 'Paid', processing_type = ?, amount = ?, payment_status = 'Paid', payment_method = ?, appointment_date = ?, appointment_location = ?, updated_at = CURRENT_TIMESTAMP WHERE application_id = ?");
    $update->execute([$processingType, $amount, $paymentMethod, $appointmentDate, $appointmentLocation, $applicationId]);
    
    echo json_encode([
        'success' => true,
        'applicationId' => $applicationId,
        'status' => 'Paid',
        'amount' => $amount,
        'processingType' => $processingType,
        'appointmentDate' => $appointmentDate,
        'appointmentLocation' => $appointmentLocation,
        'message' => 'Payment recorded successfully.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Payment Error: ' . $e->getMessage()]);
}
?>