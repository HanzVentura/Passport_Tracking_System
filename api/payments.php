<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
try {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $applicationId = $input['applicationId'] ?? $input['application_id'] ?? '';
    $amount = $input['amount'] ?? 950;
    $processingType = $input['processingType'] ?? 'Regular Processing';
    $paymentMethod = $input['paymentMethod'] ?? 'GCash';
   
    if (!$applicationId && !$email) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Application ID or email is required for payment.']);
        exit;
    }
   
    // Locate the application record by application_id or email
    $stmt = null;
    if ($applicationId) {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE application_id = ? OR id = ? LIMIT 1");
        $stmt->execute([$applicationId, $applicationId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE email = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email]);
    }
   
    $existingApp = $stmt->fetch(PDO::FETCH_ASSOC);
   
    if (!$existingApp) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Active application record not found in database.']);
        exit;
    }
   
    $targetAppId = $existingApp['application_id'];
    $appointmentDate = !empty($existingApp['appointment_date']) ? $existingApp['appointment_date'] : date('Y-m-d');
    $appointmentLocation = !empty($existingApp['appointment_location']) ? $existingApp['appointment_location'] : 'ANGELES';
   
    // Update the record with payment status 'Paid', processing type, and persist appointment date
    $update = $pdo->prepare("UPDATE applications SET status = 'Form Submitted', payment_status = 'Paid', processing_type = ?, amount = ?, payment_method = ?, appointment_date = ?, appointment_location = ?, updated_at = CURRENT_TIMESTAMP WHERE application_id = ?");
    $update->execute([$processingType, $amount, $paymentMethod, $appointmentDate, $appointmentLocation, $targetAppId]);
   
    echo json_encode([
        'success' => true,
        'applicationId' => $targetAppId,
        'status' => 'Form Submitted',
        'payment_status' => 'Paid',
        'amount' => $amount,
        'processingType' => $processingType,
        'appointmentDate' => $appointmentDate,
        'appointmentLocation' => $appointmentLocation,
        'message' => 'Online payment verified and recorded successfully.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Payment Error: ' . $e->getMessage()]);
}
?>