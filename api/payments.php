<?php
// Handles processing application payments and scheduling appointments
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

// Reads data sent from the frontend
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? $_POST['email'] ?? '';
$applicationId = $input['applicationId'] ?? $_POST['applicationId'] ?? '';
$processingType = $input['processingType'] ?? $_POST['processingType'] ?? '';
$amount = $input['amount'] ?? $_POST['amount'] ?? '';
$paymentMethod = $input['paymentMethod'] ?? $_POST['paymentMethod'] ?? '';

// Defines valid pricing plans
$validPlans = [
    'regular' => ['label' => 'Regular Processing', 'amount' => 950],
    'expedited' => ['label' => 'Expedited / Rush Processing', 'amount' => 1200]
];
$selectedPlan = $validPlans[$processingType] ?? null;
$validMethods = ['GCash', 'Maya', 'Credit Card'];

if (!$email || !$applicationId || !($selectedPlan) || Number($amount) !== $selectedPlan['amount'] || !in_array($paymentMethod, $validMethods)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide a valid application, processing plan, amount, and payment method.']);
    exit;
}

// Calculates appointment date based on processing speed
$daysToAdd = ($processingType === 'expedited') ? 7 : 12;
$appointmentDate = date('Y-m-d', strtotime("+$daysToAdd days"));
$appointmentLocation = 'DFA NCR (SM Megamall)';

try {
    $stmt = $pdo->prepare("UPDATE applications SET status = 'Paid', processing_type = ?, amount = ?, payment_amount = ?, payment_method = ?, appointment_date = ?, appointment_location = ?, updated_at = CURRENT_TIMESTAMP WHERE email = ? AND application_id = ?");
    $stmt->execute([
        $selectedPlan['label'],
        $selectedPlan['amount'],
        $selectedPlan['amount'],
        $paymentMethod,
        $appointmentDate,
        $appointmentLocation,
        $email,
        $applicationId
    ]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Application not found.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Payment recorded successfully. Your appointment has been generated.',
        'applicationId' => $applicationId,
        'status' => 'Paid',
        'appointmentDate' => $appointmentDate,
        'appointmentLocation' => $appointmentLocation,
        'processingType' => $selectedPlan['label'],
        'amount' => $selectedPlan['amount'],
        'paymentMethod' => $paymentMethod
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to process payment.']);
}
?>