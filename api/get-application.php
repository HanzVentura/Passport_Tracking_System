<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

try {
    $applicationId = $_GET['applicationId'] ?? $_GET['application_id'] ?? '';
    $email = $_GET['email'] ?? '';

    if (!$applicationId && !$email) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Application ID or email is required.']);
        exit;
    }

    $stmt = null;
    if ($applicationId) {
        $stmt = $pdo->prepare("SELECT application_id, application_type, status, payment_status, processing_type, amount, payment_method, receiving_option, delivery_address, delivery_fee, tracking_number, appointment_date, appointment_time, appointment_location, created_at FROM applications WHERE application_id = ? OR id = ? LIMIT 1");
        $stmt->execute([$applicationId, $applicationId]);
    } else {
        $stmt = $pdo->prepare("SELECT application_id, application_type, status, payment_status, processing_type, amount, payment_method, receiving_option, delivery_address, delivery_fee, tracking_number, appointment_date, appointment_time, appointment_location, created_at FROM applications WHERE email = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email]);
    }

    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Application not found.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'application' => [
            'application_id' => $application['application_id'],
            'applicationId' => $application['application_id'],
            'application_type' => $application['application_type'],
            'applicationType' => $application['application_type'],
            'status' => $application['status'],
            'payment_status' => $application['payment_status'],
            'paymentStatus' => $application['payment_status'],
            'processing_type' => $application['processing_type'],
            'processingType' => $application['processing_type'],
            'amount' => $application['amount'],
            'payment_method' => $application['payment_method'],
            'receiving_option' => $application['receiving_option'],
            'receivingOption' => $application['receiving_option'],
            'delivery_address' => $application['delivery_address'],
            'deliveryAddress' => $application['delivery_address'],
            'delivery_fee' => $application['delivery_fee'],
            'deliveryFee' => $application['delivery_fee'],
            'tracking_number' => $application['tracking_number'],
            'trackingNumber' => $application['tracking_number'],
            'appointmentDate' => $application['appointment_date'],
            'appointmentTime' => $application['appointment_time'],
            'appointmentLocation' => $application['appointment_location'],
            'createdAt' => $application['created_at']
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>