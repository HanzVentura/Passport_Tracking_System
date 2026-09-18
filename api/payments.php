<?php
header('Content-Type: application/json');

$dbPath = __DIR__ . '/../database.db';
try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ensure payments table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT,
        application_id TEXT,
        amount REAL,
        processing_type TEXT,
        payment_method TEXT,
        status TEXT,
        appointment_date TEXT,
        appointment_location TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $applicationId = $input['applicationId'] ?? '';
    $amount = $input['amount'] ?? 950;
    $processingType = $input['processingType'] ?? 'regular';
    $paymentMethod = $input['paymentMethod'] ?? 'GCash';

    // Generate appointment date (e.g., 7 or 12 days from now)
    $daysToAdd = ($processingType === 'expedited') ? 7 : 12;
    $appointmentDate = date('Y-m-d', strtotime("+$daysToAdd days"));
    $appointmentLocation = 'DFA NCR Central (Robinsons Galleria)';

    // Update application status to Paid
    $update = $pdo->prepare("UPDATE applications SET status = 'Paid', appointment_date = ?, appointment_location = ? WHERE application_id = ? OR email = ?");
    $update->execute([$appointmentDate, $appointmentLocation, $applicationId, $email]);

    // Insert payment record
    $insert = $pdo->prepare("INSERT INTO payments (email, application_id, amount, processing_type, payment_method, status, appointment_date, appointment_location) VALUES (?, ?, ?, ?, ?, 'Paid', ?, ?)");
    $insert->execute([$email, $applicationId, $amount, $processingType, $paymentMethod, $appointmentDate, $appointmentLocation]);

    echo json_encode([
        'success' => true,
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