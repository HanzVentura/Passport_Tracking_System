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

    $email = $input['email'] ?? '';
    if (!$email) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email is required.']);
        exit;
    }

    // Parse group data payload (JSON string in FormData)
    $groupDataPayload = $input['group_data_payload'] ?? '[]';
    $groupApplicants = json_decode($groupDataPayload, true);

    if (!is_array($groupApplicants) || empty($groupApplicants)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Group applicant data is required.']);
        exit;
    }

    // Shared appointment details from FormData
    $appointmentLocation = $input['appointmentLocation'] ?? ($input['site'] ?? 'ANGELES');
    $appointmentDate = $input['appointmentDate'] ?? ($input['app_date'] ?? date('Y-m-d'));
    $appointmentTime = $input['appointmentTime'] ?? ($input['app_time'] ?? '15:00');
    $processingType = $input['processingType'] ?? 'Regular Processing';
    $paymentMethod = $input['paymentMethod'] ?? 'GCash';
    $receivingOption = $input['receivingOption'] ?? 'In-Person';
    $deliveryAddress = isset($input['deliveryAddress']) && trim($input['deliveryAddress']) !== ''
        ? trim($input['deliveryAddress'])
        : null;

    // Application types matching DFA_RULES_ENGINE
    $applicationTypes = [
        'First-Time Passport' => 'First-Time Passport',
        'Renewal' => 'Renewal',
        'Replacement - Lost' => 'Replacement - Lost',
        'Replacement - Damaged' => 'Replacement - Damaged',
        'Credential Updating' => 'Credential Updating',
    ];

    $credentialUpdateReasons = [
        'marriage' => 'Marriage',
        'annulment_divorce' => 'Annulment / Foreign Divorce',
        'widowhood' => 'Widowhood',
        'voluntary_reversion' => 'Voluntary Reversion to Maiden Name',
        'name_correction' => 'Typo / Legal Name Change',
        'gender_update' => 'Gender Update',
    ];

    // Helper: resolve application type label (port of JS resolveApplicationType)
    function resolveApplicationTypeLabel($appType, $updateReason) {
        global $credentialUpdateReasons;
        if ($appType !== 'Credential Updating') {
            return $appType;
        }
        if (!$updateReason || !isset($credentialUpdateReasons[$updateReason])) {
            return 'Credential Updating';
        }
        return 'Credential Updating - ' . $credentialUpdateReasons[$updateReason];
    }

    // Helper: calculate age
    function calculateAge($dob, $referenceDate = null) {
        if (!$dob) return 0;
        try {
            $ref = $referenceDate ? new DateTime($referenceDate) : new DateTime();
            $birth = new DateTime($dob);
            $diff = $ref->diff($birth);
            return $diff->y;
        } catch (Exception $e) {
            return 0;
        }
    }

    // Helper: get required documents per applicant (port of JS getRequiredDocuments)
    function getRequiredDocuments($appType, $updateReason, $age) {
        global $applicationTypes, $credentialUpdateReasons;

        $documents = [];

        if ($appType === 'First-Time Passport') {
            $documents = [
                ['key' => 'psa_birth_cert', 'label' => 'PSA Birth Certificate', 'required' => true],
                ['key' => 'valid_id', 'label' => 'Valid ID', 'required' => true],
                ['key' => 'supporting_docs', 'label' => 'Supporting Documents', 'required' => false],
            ];
            if ($age < 18) {
                $documents[] = ['key' => 'parental_consent', 'label' => 'Parental Consent / Affidavit of Support', 'required' => true];
            }
        } elseif ($appType === 'Renewal') {
            $documents = [
                ['key' => 'old_passport', 'label' => 'Old Passport', 'required' => true],
                ['key' => 'valid_id', 'label' => 'Valid ID', 'required' => true],
                ['key' => 'psa_birth_cert', 'label' => 'PSA Birth Certificate', 'required' => false],
                ['key' => 'supporting_docs', 'label' => 'Supporting Documents', 'required' => false],
            ];
            if ($age < 18) {
                $documents[] = ['key' => 'parental_consent', 'label' => 'Parental Consent / Affidavit of Support', 'required' => true];
            }
        } elseif ($appType === 'Replacement - Lost') {
            $documents = [
                ['key' => 'affidavit_of_loss', 'label' => 'Affidavit of Loss', 'required' => true],
                ['key' => 'police_report', 'label' => 'Police Report / NBI Clearance', 'required' => true],
                ['key' => 'psa_birth_cert', 'label' => 'PSA Birth Certificate', 'required' => true],
                ['key' => 'valid_id', 'label' => 'Valid ID', 'required' => true],
                ['key' => 'supporting_docs', 'label' => 'Supporting Documents', 'required' => false],
            ];
        } elseif ($appType === 'Replacement - Damaged') {
            $documents = [
                ['key' => 'damaged_passport', 'label' => 'Damaged Passport', 'required' => true],
                ['key' => 'affidavit_of_damage', 'label' => 'Affidavit of Damage', 'required' => true],
                ['key' => 'psa_birth_cert', 'label' => 'PSA Birth Certificate', 'required' => true],
                ['key' => 'valid_id', 'label' => 'Valid ID', 'required' => true],
                ['key' => 'supporting_docs', 'label' => 'Supporting Documents', 'required' => false],
            ];
        } elseif ($appType === 'Credential Updating') {
            $documents = [
                ['key' => 'psa_birth_cert', 'label' => 'PSA Birth Certificate', 'required' => true],
                ['key' => 'valid_id', 'label' => 'Valid ID', 'required' => true],
                ['key' => 'supporting_docs', 'label' => 'Supporting Documents', 'required' => false],
            ];

            // Add reason-specific documents
            switch ($updateReason) {
                case 'marriage':
                    $documents[] = ['key' => 'marriage_contract', 'label' => 'PSA Marriage Contract', 'required' => true];
                    break;
                case 'annulment_divorce':
                    $documents[] = ['key' => 'annulment_decree', 'label' => 'Court Decree of Annulment', 'required' => true];
                    break;
                case 'widowhood':
                    $documents[] = ['key' => 'death_cert', 'label' => 'PSA Death Certificate of Spouse', 'required' => true];
                    break;
                case 'voluntary_reversion':
                    $documents[] = ['key' => 'affidavit_reversion', 'label' => 'Affidavit of Reversion', 'required' => true];
                    break;
                case 'name_correction':
                case 'gender_update':
                    $documents[] = ['key' => 'court_order', 'label' => 'Court Order / Legal Document', 'required' => true];
                    break;
            }

            if ($age < 18) {
                $documents[] = ['key' => 'parental_consent', 'label' => 'Parental Consent / Affidavit of Support', 'required' => true];
            }
        }

        return $documents;
    }

    // Validate file uploads for all applicants
    $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    $uploadErrors = [];

    if (!$isJson && !empty($_FILES)) {
        foreach ($_FILES as $fieldName => $file) {
            // Skip empty uploads (optional files)
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

    // Create individual application records for each applicant
    $createdApplications = [];
    $pdo->beginTransaction();

    try {
        foreach ($groupApplicants as $index => $applicant) {
            $i = $index + 1;
            $prefix = "applicant_{$i}_";

            // Resolve application type with human-readable label
            $appType = $applicant['application_type'] ?? 'First-Time Passport';
            $credReason = $applicant['credential_reason'] ?? '';
            $resolvedType = resolveApplicationTypeLabel($appType, $credReason);

            // Generate unique application ID
            $applicationId = 'PH-' . rand(100000, 999999);
            $check = $pdo->prepare("SELECT id FROM applications WHERE application_id = ?");
            $check->execute([$applicationId]);
            while ($check->fetch()) {
                $applicationId = 'PH-' . rand(100000, 999999);
                $check->execute([$applicationId]);
            }

            // Extract applicant data
            $givenName = $applicant['givenname'] ?? '';
            $middleName = $applicant['middlename'] ?? '';
            $lastName = $applicant['lastname'] ?? '';
            $extName = $applicant['extname'] ?? '';
            $dob = $applicant['dob'] ?? '';
            $sex = $applicant['sex'] ?? '';
            $civilStatus = $applicant['civil_status'] ?? '';
            $birthPlace = $applicant['birth_place'] ?? '';
            $citizenship = $applicant['citizenship'] ?? 'Filipino';
            $passportType = $applicant['passport_type'] ?? 'Regular';
            $mobile = $applicant['mobile'] ?? '';
            $applicantEmail = $applicant['email'] ?? $email;
            $address = $applicant['address'] ?? '';
            $emergencyContact = $applicant['emergency_contact'] ?? '';
            $emergencyPhone = $applicant['emergency_phone'] ?? '';

            // Calculate age for document requirements
            $age = calculateAge($dob, $appointmentDate);

            // Insert individual application record
            $sql = "INSERT INTO applications (
                        application_id, email, application_type, status,
                        processing_type, amount, payment_status, receiving_option,
                        delivery_address, appointment_date, appointment_location, appointment_time,
                        givenname, middlename, lastname, extname, dob, sex, civil_status,
                        birth_place, citizenship, passport_type, mobile, address,
                        emergency_contact, emergency_phone,
                        created_at
                    ) VALUES (
                        ?, ?, ?, 'Form Submitted',
                        ?, 950.00, 'Pending', ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?,
                        CURRENT_TIMESTAMP
                    )";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $applicationId,
                $applicantEmail,
                $resolvedType,
                $processingType,
                $receivingOption,
                $deliveryAddress,
                $appointmentDate,
                $appointmentLocation,
                $appointmentTime,
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

            $createdApplications[] = [
                'application_id' => $applicationId,
                'applicationId' => $applicationId,
                'applicationType' => $resolvedType,
                'application_type' => $resolvedType,
                'name' => trim("$givenName $middleName $lastName $extName"),
                'email' => $applicantEmail,
                'appointmentDate' => $appointmentDate,
                'appointmentLocation' => $appointmentLocation,
                'appointmentTime' => $appointmentTime,
            ];

            // Audit log
            logAudit(
                $pdo,
                'GROUP_APPLICATION_SUBMITTED',
                'application',
                $applicationId,
                null,
                'Form Submitted',
                "Group application submitted for {$givenName} {$lastName} (type: {$resolvedType})",
                $applicantEmail,
                'member'
            );
        }

        $pdo->commit();

        // Set session to first created application
        if (!empty($createdApplications)) {
            $_SESSION['currentApplicationId'] = $createdApplications[0]['application_id'];
            $_SESSION['userEmail'] = $email;
        }

        echo json_encode([
            'success' => true,
            'applications' => $createdApplications,
            'message' => 'Group application submitted successfully. ' . count($createdApplications) . ' individual applications created.'
        ]);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log('submit-group-application.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    exit;
}