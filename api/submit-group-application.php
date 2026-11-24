<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/audit-helper.php';
require_once __DIR__ . '/auth-helper.php';
startSecureSession();
requireMember();
requireCsrfToken();

try {
    // Support both JSON and multipart/form-data
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $isJson = stripos($contentType, 'application/json') !== false;

    if ($isJson) {
        $input = json_decode(file_get_contents('php://input'), true);
    } else {
        $input = $_POST;
    }

    $email = $input['email'] ?? '';
    $email = strtolower(trim($email));
    if ($email !== getSessionEmail()) {
        jsonError('You cannot submit an application for another member.', 403);
    }
    if (!$email) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email is required.']);
        exit;
    }

    $application_id = $input['applicationId'] ?? ('PH-GRP-' . random_int(100000, 999999));
    $application_type = $input['applicationType'] ?? ($input['application_type'] ?? 'Group Passport Application');
    $status = 'Form Submitted';
    $processing_type = 'Regular Processing';
    $groupPayload = is_array($input['groupPayload'] ?? null) ? $input['groupPayload'] : [];
    $applicantCount = (int)($input['applicantCount'] ?? $input['groupCount'] ?? count($groupPayload));
    if ($applicantCount < 2) {
        $applicantCount = max(2, count($groupPayload));
    }
    $amount = 950.00 * $applicantCount;
    $payment_status = 'Pending';
    $receiving_option = 'In-Person';

    $appointment_location = $input['appointmentLocation'] ?? ($input['site'] ?? 'ANGELES');
    $appointment_date = $input['appointmentDate'] ?? ($input['app_date'] ?? date('Y-m-d'));
    $appointment_time = $input['appointmentTime'] ?? ($input['app_time'] ?? '15:00');

    // Ensure application_id is unique
    $check = $pdo->prepare("SELECT id FROM applications WHERE application_id = ?");
    $check->execute([$application_id]);
    if ($check->fetch()) {
        $application_id = 'PH-GRP-' . random_int(100000, 999999);
    }

    // Validate file uploads if present (multipart/form-data)
    $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    $uploadErrors = [];

    if (!$isJson && !empty($_FILES)) {
        foreach ($_FILES as $fieldName => $file) {
            // Skip empty uploads
            if ($file['error'] === UPLOAD_ERR_NO_FILE) continue;

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $uploadErrors[] = "Upload error for {$fieldName}: " . $file['error'];
                continue;
            }

            // Validate file size
            if ($file['size'] > $maxFileSize) {
                $uploadErrors[] = "File {$fieldName} exceeds 5MB limit";
                continue;
            }

            // Validate MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedMimeTypes)) {
                $uploadErrors[] = "File {$fieldName} has invalid type: {$mimeType}. Allowed: PDF, JPG, PNG";
                continue;
            }
        }
    }

    if (!empty($uploadErrors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => implode('; ', $uploadErrors)]);
        exit;
    }

    // Insert group application (files are transient - not saved to disk)
    $sql = "INSERT INTO applications (application_id, email, application_type, status, processing_type, amount, payment_status, receiving_option, applicant_count, appointment_date, appointment_location, appointment_time, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";

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
        $applicantCount,
        $appointment_date,
        $appointment_location,
        $appointment_time
    ]);

    $_SESSION['currentApplicationId'] = $application_id;
    $_SESSION['userEmail'] = $email;
    logAudit($pdo, 'GROUP_APPLICATION_SUBMITTED', 'application', $application_id, null, 'Form Submitted', 'Group passport application submitted.', $email, 'member');

    echo json_encode([
        'success' => true,
        'application_id' => $application_id,
        'applicationId' => $application_id,
        'applicationType' => $application_type,
        'message' => 'Group application saved successfully.'
    ]);
    exit;
} catch (Exception $e) {
    error_log('submit-group-application.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    exit;
}
?>