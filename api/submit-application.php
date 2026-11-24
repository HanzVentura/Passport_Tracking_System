<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/audit-helper.php';

try {
    // Support both JSON and multipart/form-data
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $isJson = stripos($contentType, 'application/json') !== false;

    if ($isJson) {
        $input = json_decode(file_get_contents('php://input'), true);
    } else {
        $input = $_POST;
    }

    // Email is optional - fallback to session or default
    $email = $input['email'] ?? $_SESSION['user_email'] ?? 'applicant@example.com';

    // Application ID - generate if not provided
    $application_id = $input['applicationId'] ?? ($input['application_id'] ?? ('PH-' . rand(100000, 999999)));
    $application_type = $input['applicationType'] ?? ($input['application_type'] ?? 'First-Time Passport');
    $status = 'Form Submitted';
    $processing_type = $input['processing_type'] ?? 'Regular Processing';
    $amount = 950.00;
    $payment_status = 'Pending';
    $receiving_option = $input['receiving_option'] ?? 'In-Person';

    $appointment_location = $input['appointmentLocation'] ?? ($input['site'] ?? ($input['site_selection'] ?? 'DFA NCR NORTHEAST'));
    $appointment_date = $input['appointmentDate'] ?? ($input['app_date'] ?? ($input['app_date_selection'] ?? date('Y-m-d')));
    $appointment_time = $input['appointmentTime'] ?? ($input['app_time'] ?? ($input['app_time_selection'] ?? '09:00 AM - 10:00 AM'));

    // Ensure application_id is unique
    $check = $pdo->prepare("SELECT id FROM applications WHERE application_id = ?");
    $check->execute([$application_id]);
    if ($check->fetch()) {
        $application_id = 'PH-' . rand(100000, 999999);
    }

    // Validate file uploads if present (multipart/form-data) - TRANSIENT ONLY
    $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    $uploadWarnings = [];

    if (!$isJson && !empty($_FILES)) {
        foreach ($_FILES as $fieldName => $file) {
            // Skip empty uploads
            if ($file['error'] === UPLOAD_ERR_NO_FILE) continue;

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $uploadWarnings[] = "Upload warning for {$fieldName}: error code " . $file['error'];
                continue;
            }

            // Validate file size (warning only)
            if ($file['size'] > $maxFileSize) {
                $uploadWarnings[] = "File {$fieldName} exceeds 5MB limit (will be ignored)";
                continue;
            }

            // Validate MIME type (warning only)
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedMimeTypes)) {
                $uploadWarnings[] = "File {$fieldName} has invalid type: {$mimeType} (will be ignored). Allowed: PDF, JPG, PNG";
                continue;
            }
        }
    }

    // Log warnings but never block submission
    if (!empty($uploadWarnings)) {
        error_log('submit-application.php upload warnings: ' . implode('; ', $uploadWarnings));
    }

    // Helper to normalize values for MySQL strict mode
    function normalizeForMySQL($val, $maxLen = null, $default = null) {
        if ($val === '' || $val === null) {
            return $default;
        }
        if (is_string($val) && $maxLen !== null && strlen($val) > $maxLen) {
            return substr($val, 0, $maxLen);
        }
        return $val;
    }

    // Insert application with full schema (files are transient - not saved to disk)
    // All NOT NULL columns get safe defaults per migrate-mysql.php DDL
    $sql = "INSERT INTO applications (
                application_id, email, application_type, status,
                processing_type, amount, payment_status, receiving_option,
                appointment_date, appointment_location, appointment_time,
                givenname, middlename, lastname, extname, dob, sex, civil_status,
                birth_place, citizenship, passport_type, mobile, address,
                emergency_contact, emergency_phone,
                created_at
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?,
                CURRENT_TIMESTAMP
            )";

    // Normalize all fields with safe defaults
    $givenName = normalizeForMySQL($input['givenname'] ?? '', 100, 'Not Provided');
    $middleName = normalizeForMySQL($input['middlename'] ?? '', 100, '');
    $lastName = normalizeForMySQL($input['lastname'] ?? '', 100, 'Not Provided');
    $extName = normalizeForMySQL($input['extname'] ?? '', 50, '');
    $dob = normalizeForMySQL($input['dob'] ?? ''); // DATE column - empty becomes null
    $sex = normalizeForMySQL($input['sex'] ?? '', 20, 'Not Provided');
    $civilStatus = normalizeForMySQL($input['civil_status'] ?? '', 50, 'Not Provided');
    $birthPlace = normalizeForMySQL($input['birth_place'] ?? '', 255, 'Not Provided');
    $citizenship = normalizeForMySQL($input['citizenship'] ?? 'Filipino', 100, 'Filipino');
    $passportType = normalizeForMySQL($input['passport_type'] ?? 'Regular', 50, 'Regular');
    $mobile = normalizeForMySQL($input['mobile'] ?? '', 50, '09000000000');
    $address = normalizeForMySQL($input['address'] ?? '', null, 'Not Provided');
    $emergencyContact = normalizeForMySQL($input['emergency_contact'] ?? '', 255, 'Not Provided');
    $emergencyPhone = normalizeForMySQL($input['emergency_phone'] ?? '', 50, '09000000000');
    $applicationTypeNorm = normalizeForMySQL($application_type, 150, 'Passport Application');
    $processingTypeNorm = normalizeForMySQL($processing_type, 100, 'Regular Processing');
    $receivingOptionNorm = normalizeForMySQL($receiving_option, 50, 'In-Person');
    $appointmentDateNorm = normalizeForMySQL($appointment_date, 50, date('Y-m-d'));
    $appointmentLocationNorm = normalizeForMySQL($appointment_location, 255, 'DFA NCR NORTHEAST');
    $appointmentTimeNorm = normalizeForMySQL($appointment_time, 50, '09:00 AM - 10:00 AM');

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $application_id,
        $email,
        $applicationTypeNorm,
        $status,
        $processingTypeNorm,
        $amount,
        $payment_status,
        $receivingOptionNorm,
        $appointmentDateNorm,
        $appointmentLocationNorm,
        $appointmentTimeNorm,
        $givenName,
        $middleName,
        $lastName,
        $extName,
        $dob,
        $sex,
        $civilStatus,
        $birthPlace,
        $citizenship,
        $passportType,
        $mobile,
        $address,
        $emergencyContact,
        $emergencyPhone
    ]);

    $_SESSION['currentApplicationId'] = $application_id;
    $_SESSION['userEmail'] = $email;
    logAudit($pdo, 'APPLICATION_SUBMITTED', 'application', $application_id, null, 'Form Submitted', 'Passport application submitted.', $email, 'member');

    echo json_encode([
        'success' => true,
        'application_id' => $application_id,
        'applicationId' => $application_id,
        'applicationType' => $applicationTypeNorm,
        'message' => 'Application saved successfully.'
    ]);
    exit;
} catch (Exception $e) {
    error_log('submit-application.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    exit;
}