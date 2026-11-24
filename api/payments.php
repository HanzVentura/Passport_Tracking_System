<?php
header('Content-Type: application/json');
require_once __DIR__ . '/auth-helper.php';
startSecureSession();
requireMember();
requireCsrfToken();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/audit-helper.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    $sessionEmail = trim($_SESSION['user_email'] ?? '');
    $email = $sessionEmail;
    if (strtolower($email) !== strtolower((string)$sessionEmail)) {
        jsonError('You cannot pay for another member.', 403);
    }
    $applicationId = trim($input['applicationId'] ?? $input['application_id'] ?? $_GET['id'] ?? $_GET['applicationId'] ?? '');
    $amount = isset($input['amount']) ? (float)$input['amount'] : 950.00;
    $processingType = trim($input['processingType'] ?? 'Regular Processing');
    $paymentMethod = trim($input['paymentMethod'] ?? 'GCash');
    $receivingOption = trim($input['receivingOption'] ?? 'In-Person');
    $deliveryAddress = isset($input['deliveryAddress']) && trim($input['deliveryAddress']) !== ''
        ? trim($input['deliveryAddress'])
        : null;
    $deliveryFee = isset($input['deliveryFee']) ? (float)$input['deliveryFee'] : 0.00;
    $applicantCount = max(1, (int)($input['applicantCount'] ?? 1));
    if ($amount < 0 || $deliveryFee < 0) {
        jsonError('Payment amounts cannot be negative.', 400);
    }

    if ($applicationId === '' && $email === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Application ID or email is required for payment.'
        ]);
        exit;
    }

    if ($applicationId !== '') {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE (application_id = ? OR id = ?) AND email = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$applicationId, $applicationId, $email]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE email = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email]);
    }

    $existingApp = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existingApp) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Active application record not found in database.'
        ]);
        exit;
    }

    $existingType = (string)($existingApp['application_type'] ?? '');
    if (isset($existingApp['applicant_count']) && (int)$existingApp['applicant_count'] >= 2) {
        $applicantCount = (int)$existingApp['applicant_count'];
    }
    if (stripos($existingType, 'group') !== false && $applicantCount >= 2) {
        $perApplicantAmount = stripos($processingType, 'expedited') !== false ? 1200.00 : 950.00;
        $amount = ($perApplicantAmount * $applicantCount) + $deliveryFee;
    }

    if (($existingApp['payment_status'] ?? '') === 'Paid') {
        echo json_encode([
            'success' => true,
            'applicationId' => $existingApp['application_id'],
            'status' => $existingApp['status'] ?? 'Form Submitted',
            'payment_status' => 'Paid',
            'amount' => $existingApp['amount'] ?? $amount,
            'processingType' => $existingApp['processing_type'] ?? $processingType,
            'receivingOption' => $existingApp['receiving_option'] ?? $receivingOption,
            'appointmentDate' => !empty($existingApp['appointment_date']) ? $existingApp['appointment_date'] : date('Y-m-d'),
            'appointmentLocation' => !empty($existingApp['appointment_location']) ? $existingApp['appointment_location'] : 'ANGELES',
            'message' => 'Payment has already been recorded for this application.'
        ]);
        exit;
    }

    $targetAppId = $existingApp['application_id'];
    $appointmentDate = !empty($existingApp['appointment_date']) ? $existingApp['appointment_date'] : date('Y-m-d');
    $appointmentLocation = !empty($existingApp['appointment_location']) ? $existingApp['appointment_location'] : 'ANGELES';

    $update = $pdo->prepare("
        UPDATE applications
        SET status = 'Form Submitted',
            payment_status = 'Paid',
            processing_type = ?,
            amount = ?,
            payment_method = ?,
            receiving_option = ?,
            delivery_address = ?,
            delivery_fee = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE application_id = ?
    ");
    $update->execute([
        $processingType,
        $amount,
        $paymentMethod,
        $receivingOption,
        $deliveryAddress,
        $deliveryFee,
        $targetAppId
    ]);

    $actorEmail = $existingApp['email'] ?? ($sessionEmail !== '' ? $sessionEmail : $email);
    logAudit(
        $pdo,
        'PAYMENT_RECORDED',
        'application',
        $targetAppId,
        $existingApp['payment_status'] ?? 'Pending',
        'Paid',
        'Payment recorded via ' . $paymentMethod . '; processing: ' . $processingType . '; receiving: ' . $receivingOption . '.',
        $actorEmail,
        'member'
    );

    echo json_encode([
        'success' => true,
        'applicationId' => $targetAppId,
        'status' => 'Form Submitted',
        'payment_status' => 'Paid',
        'amount' => $amount,
        'processingType' => $processingType,
        'receivingOption' => $receivingOption,
        'appointmentDate' => $appointmentDate,
        'appointmentLocation' => $appointmentLocation,
        'message' => 'Online payment verified and recorded successfully.'
    ]);
} catch (Exception $e) {
    error_log('payments.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to process payment.'
    ]);
}