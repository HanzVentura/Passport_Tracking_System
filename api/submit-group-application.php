<?php
session_start();
require_once __DIR__ . '/../db.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $isJson = !empty($input);

    $email = $isJson ? ($input['email'] ?? '') : (!empty($_POST['email']) ? trim($_POST['email']) : '');
    if (!$email) {
        if ($isJson) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email is required.']);
            exit;
        }
        die("Error: Email is required.");
    }

    $application_id = $isJson ? ($input['applicationId'] ?? 'PH-GRP-' . rand(100000, 999999)) : ('PH-GRP-' . rand(100000, 999999));
    $application_type = 'Group Passport Application';
    $status = 'Form Submitted';
    $processing_type = 'Regular Processing';
    $amount = 950.00;
    $payment_status = 'Pending';
    $receiving_option = 'In-Person';

    $appointment_location = $isJson ? ($input['appointmentLocation'] ?? 'ANGELES') : (!empty($_POST['site']) ? $_POST['site'] : 'ANGELES');
    $appointment_date = $isJson ? ($input['appointmentDate'] ?? date('Y-m-d')) : (!empty($_POST['app_date']) ? $_POST['app_date'] : date('Y-m-d'));
    $appointment_time = $isJson ? ($input['appointmentTime'] ?? '15:00') : (!empty($_POST['app_time']) ? $_POST['app_time'] : '15:00');

    // Ensure application_id is unique
    $check = $pdo->prepare("SELECT id FROM applications WHERE application_id = ?");
    $check->execute([$application_id]);
    if ($check->fetch()) {
        $application_id = 'PH-GRP-' . rand(100000, 999999);
    }

    $sql = "INSERT INTO applications (application_id, email, application_type, status, processing_type, amount, payment_status, receiving_option, appointment_date, appointment_location, appointment_time, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $application_id,
        $email,
        $application_type,
        $status,
        $processing_type,
        $amount,
        $payment_status,
        $receiving_option,
        $appointment_date,
        $appointment_location,
        $appointment_time
    ]);

    $_SESSION['currentApplicationId'] = $application_id;
    $_SESSION['userEmail'] = $email;

    if ($isJson) {
        echo json_encode([
            'success' => true,
            'application_id' => $application_id,
            'applicationId' => $application_id,
            'applicationType' => $application_type,
            'message' => 'Group application saved successfully.'
        ]);
        exit;
    } else {
        header("Location: ../public/payments.html?id=" . $application_id);
        exit();
    }
} catch (Exception $e) {
    if (!empty($isJson)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
    die("Database error: " . $e->getMessage());
}
?>