<?php
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/audit-helper.php';

/**
 * Resolve human-readable application type from raw application_type and credential_reason.
 */
function resolveGroupApplicationType(?string $appType, ?string $credentialReason): string
{
    $cleanType = trim((string)($appType ?? ''));
    $cleanReason = trim((string)($credentialReason ?? ''));

    if ($cleanType === '') {
        return 'First-Time Passport';
    }

    if ($cleanType === 'Credential Updating (Change of Personal Details)') {
        $reasonMap = [
            'marriage' => 'Credential Updating - Marriage',
            'annulment_divorce' => 'Credential Updating - Annulment / Foreign Divorce',
            'widowhood' => 'Credential Updating - Widowhood',
            'voluntary_reversion' => 'Credential Updating - Voluntary Reversion',
            'name_correction' => 'Credential Updating - Typo / Legal Name Change',
            'gender_update' => 'Credential Updating - Gender Update',
        ];
        return $reasonMap[$cleanReason] ?? 'Credential Updating (Change of Personal Details)';
    }

    return $cleanType;
}

/**
 * Calculate applicant age at appointment date (or current date if unavailable).
 */
function calculateApplicantAge(?string $dobStr, ?string $appointmentDateStr): ?int
{
    $cleanDob = trim((string)($dobStr ?? ''));
    if ($cleanDob === '') {
        return null;
    }

    try {
        $dob = new DateTimeImmutable($cleanDob);
    } catch (Throwable $e) {
        return null;
    }

    $cleanAppt = trim((string)($appointmentDateStr ?? ''));
    try {
        $refDate = $cleanAppt !== '' ? new DateTimeImmutable($cleanAppt) : new DateTimeImmutable('today');
    } catch (Throwable $e) {
        $refDate = new DateTimeImmutable('today');
    }

    if ($dob > $refDate) {
        return 0;
    }

    return (int)$dob->diff($refDate)->y;
}

/**
 * Normalize optional scalar value to trimmed string or null.
 */
function normalizeNullableString($value, ?int $maxLen = null): ?string
{
    if (!is_scalar($value)) {
        return null;
    }
    $trimmed = trim((string)$value);
    if ($trimmed === '') {
        return null;
    }
    if ($maxLen !== null && function_exists('mb_substr')) {
        return mb_substr($trimmed, 0, $maxLen, 'UTF-8');
    }
    return $maxLen !== null ? substr($trimmed, 0, $maxLen) : $trimmed;
}

/**
 * Normalize string value with a MySQL strict-mode safe non-null fallback.
 */
function normalizeRequiredString($value, string $fallback, ?int $maxLen = null): string
{
    $normalized = normalizeNullableString($value, $maxLen);
    if ($normalized === null) {
        return $fallback;
    }
    return $normalized;
}

/**
 * Validate YYYY-MM-DD date string or return null.
 */
function normalizeDateValue($value): ?string
{
    $str = normalizeNullableString($value, 20);
    if ($str === null) {
        return null;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $str);
    if ($dt === false or $dt->format('Y-m-d') !== $str) {
        return null;
    }
    return $str;
}

try {
    // Support both JSON and multipart/form-data
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $isJson = stripos($contentType, 'application/json') !== false;

    if ($isJson) {
        $rawBody = file_get_contents('php://input');
        $input = json_decode((string)$rawBody, true);
        if (!is_array($input)) {
            $input = [];
        }
    } else {
        $input = $_POST;
    }

    // Validate uploaded files if present (multipart/form-data)
    $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    $uploadErrors = [];

    if (!$isJson && !empty($_FILES)) {
        foreach ($_FILES as $fieldName => $file) {
            if (!is_array($file) or !isset($file['error'])) {
                continue;
            }
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $uploadErrors[] = "Upload error for {$fieldName}: " . $file['error'];
                continue;
            }
            if (($file['size'] ?? 0) > $maxFileSize) {
                $uploadErrors[] = "File {$fieldName} exceeds 5MB limit.";
                continue;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = $finfo !== false ? finfo_file($finfo, $file['tmp_name']) : '';

            if (!in_array($mimeType, $allowedMimeTypes, true)) {
                $uploadErrors[] = "File {$fieldName} has invalid type ({$mimeType}). Allowed: PDF, JPG, PNG.";
                continue;
            }
        }
    }

    if (!empty($uploadErrors)) {
        if (ob_get_length()) {
            ob_clean();
        }
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => implode('; ', $uploadErrors)
        ]);
        exit;
    }

    // Schema check outside transaction: ensure group_members column exists
    $colCheck = $pdo->query("SHOW COLUMNS FROM applications LIKE 'group_members'");
    if ($colCheck === false or !$colCheck->fetch()) {
        try {
            $pdo->exec("ALTER TABLE applications ADD COLUMN group_members LONGTEXT NULL");
        } catch (Throwable $alterEx) {
            error_log('submit-group-application.php schema check warning: ' . $alterEx->getMessage());
        }
    }

    // Collect actual columns present in the applications table
    $existingColumns = [];
    $colsStmt = $pdo->query("SHOW COLUMNS FROM applications");
    if ($colsStmt) {
        foreach ($colsStmt->fetchAll(PDO::FETCH_ASSOC) as $colRow) {
            if (!empty($colRow['Field'])) {
                $existingColumns[strtolower((string)$colRow['Field'])] = (string)$colRow['Field'];
            }
        }
    }

    // Parse group_data_payload (or JSON body) into $groupApplicants
    $rawPayload = $input['group_data_payload'] ?? ($input['groupApplicantsData'] ?? ($input['members'] ?? null));
    $groupApplicants = null;

    if (is_string($rawPayload) && trim($rawPayload) !== '') {
        $decoded = json_decode($rawPayload, true);
        if (is_array($decoded)) {
            $groupApplicants = $decoded;
        }
    } elseif (is_array($rawPayload)) {
        $groupApplicants = $rawPayload;
    }

    if (!is_array($groupApplicants) or count($groupApplicants) === 0) {
        $groupApplicants = [['completed' => true]];
    } else {
        $groupApplicants = array_values($groupApplicants);
    }

    // Shared appointment schedule resolution
    $firstRaw = is_array($groupApplicants[0] ?? null) ? $groupApplicants[0] : [];
    $appointment_location = normalizeRequiredString(
        $input['site'] ?? ($input['appointmentLocation'] ?? ($firstRaw['site_selection'] ?? null)),
        'ANGELES',
        150
    );

    $appointment_date = normalizeDateValue(
        $input['app_date'] ?? ($input['appointmentDate'] ?? ($firstRaw['app_date_selection'] ?? null))
    ) ?? date('Y-m-d');

    $appointment_time = normalizeRequiredString(
        $input['app_time'] ?? ($input['appointmentTime'] ?? ($firstRaw['app_time_selection'] ?? null)),
        '08:00 AM - 09:00 AM',
        100
    );

    $topLevelEmail = normalizeNullableString($input['email'] ?? ($_SESSION['userEmail'] ?? null), 255);

    // Normalize all N members into $normalizedMembers
    $normalizedMembers = [];
    $resolvedTypes = [];

    foreach ($groupApplicants as $index => $rawMember) {
        $member = is_array($rawMember) ? $rawMember : [];

        // If single-item fallback has no personal data, merge top-level form fields
        if ($index === 0 && empty($member['givenname']) && empty($member['lastname'])) {
            $member = array_merge($input, $member);
        }

        $givenname = normalizeNullableString($member['givenname'] ?? ($member['givenName'] ?? null), 100);
        $middlename = normalizeNullableString($member['middlename'] ?? ($member['middleName'] ?? null), 100);
        $lastname = normalizeNullableString($member['lastname'] ?? ($member['lastName'] ?? null), 100);
        $extname = normalizeNullableString($member['extname'] ?? ($member['extName'] ?? null), 50);

        $nameParts = array_filter([$givenname, $middlename, $lastname, $extname], static fn($v) => $v !== null && $v !== '');
        $fullName = count($nameParts) > 0 ? implode(' ', $nameParts) : ('Applicant ' . ($index + 1));

        $memberEmail = normalizeNullableString($member['email'] ?? ($index === 0 ? $topLevelEmail : null), 255);
        $mobile = normalizeNullableString($member['mobile'] ?? null, 50);
        $phone = normalizeNullableString($member['phone'] ?? null, 50);
        $dob = normalizeDateValue($member['dob'] ?? null);
        $sex = normalizeNullableString($member['sex'] ?? ($member['gender'] ?? null), 20);
        $civilStatus = normalizeNullableString($member['civil_status'] ?? ($member['civilStatus'] ?? null), 50);
        $legitimacy = normalizeNullableString($member['legitimacy'] ?? null, 50);

        $birthCountry = normalizeNullableString($member['birth_country'] ?? null, 100);
        $birthProvince = normalizeNullableString($member['birth_province'] ?? null, 100);
        $birthCity = normalizeNullableString($member['birth_city'] ?? null, 100);
        $birthPlace = normalizeNullableString($member['birth_place'] ?? null, 255);
        if ($birthPlace === null) {
            $birthParts = array_filter([$birthCity, $birthProvince, $birthCountry], static fn($v) => $v !== null && $v !== '');
            $birthPlace = count($birthParts) > 0 ? implode(', ', $birthParts) : null;
        }

        $citizenship = normalizeRequiredString($member['citizenship'] ?? null, 'Filipino', 100);
        $citizenshipBasis = normalizeNullableString($member['citizenship_basis'] ?? null, 100);
        $citizenshipBasisOthers = normalizeNullableString($member['citizenship_basis_others'] ?? null, 150);
        $foreignPassport = normalizeNullableString($member['foreign_passport'] ?? null, 20);
        $passportType = normalizeRequiredString($member['passport_type'] ?? null, 'Regular', 50);

        $addressCity = normalizeNullableString($member['address_city'] ?? null, 100);
        $addressProvince = normalizeNullableString($member['address_province'] ?? null, 100);
        $address = normalizeNullableString($member['address'] ?? null, 500);
        if ($address === null) {
            $addrParts = array_filter([$addressCity, $addressProvince], static fn($v) => $v !== null && $v !== '');
            $address = count($addrParts) > 0 ? implode(', ', $addrParts) : null;
        }

        $emergencyContact = normalizeNullableString($member['emergency_contact'] ?? null, 255);
        $emergencyPhone = normalizeNullableString($member['emergency_phone'] ?? ($member['emergency_number'] ?? null), 50);
        $occupation = normalizeNullableString($member['occupation'] ?? null, 100);
        $officeNumber = normalizeNullableString($member['office_number'] ?? null, 50);

        $fatherLastname = normalizeNullableString($member['father_lastname'] ?? null, 100);
        $fatherGivenname = normalizeNullableString($member['father_givenname'] ?? null, 100);
        $fatherMiddlename = normalizeNullableString($member['father_middlename'] ?? null, 100);
        $fatherCitizenship = normalizeNullableString($member['father_citizenship'] ?? null, 100);

        $motherLastname = normalizeNullableString($member['mother_lastname'] ?? null, 100);
        $motherGivenname = normalizeNullableString($member['mother_givenname'] ?? null, 100);
        $motherMiddlename = normalizeNullableString($member['mother_middlename'] ?? null, 100);
        $motherCitizenship = normalizeNullableString($member['mother_citizenship'] ?? null, 100);

        $companionName = normalizeNullableString($member['companion_name'] ?? null, 150);
        $companionRelationship = normalizeNullableString($member['companion_relationship'] ?? null, 100);

        $rawAppType = normalizeNullableString($member['application_type'] ?? ($member['applicationType'] ?? null), 150) ?? 'First-Time Passport';
        $credentialReason = normalizeNullableString($member['credential_reason'] ?? ($member['credentialReason'] ?? null), 100);
        $resolvedAppType = resolveGroupApplicationType($rawAppType, $credentialReason);
        $resolvedTypes[] = $resolvedAppType;

        $age = calculateApplicantAge($dob, $appointment_date);

        $normalizedMembers[] = [
            'member_index' => $index + 1,
            'givenname' => $givenname,
            'middlename' => $middlename,
            'lastname' => $lastname,
            'extname' => $extname,
            'full_name' => $fullName,
            'email' => $memberEmail,
            'mobile' => $mobile,
            'phone' => $phone,
            'dob' => $dob,
            'sex' => $sex,
            'civil_status' => $civilStatus,
            'legitimacy' => $legitimacy,
            'birth_country' => $birthCountry,
            'birth_province' => $birthProvince,
            'birth_city' => $birthCity,
            'birth_place' => $birthPlace,
            'citizenship' => $citizenship,
            'citizenship_basis' => $citizenshipBasis,
            'citizenship_basis_others' => $citizenshipBasisOthers,
            'foreign_passport' => $foreignPassport,
            'passport_type' => $passportType,
            'address' => $address,
            'address_city' => $addressCity,
            'address_province' => $addressProvince,
            'emergency_contact' => $emergencyContact,
            'emergency_phone' => $emergencyPhone,
            'occupation' => $occupation,
            'office_number' => $officeNumber,
            'father_lastname' => $fatherLastname,
            'father_givenname' => $fatherGivenname,
            'father_middlename' => $fatherMiddlename,
            'father_citizenship' => $fatherCitizenship,
            'mother_lastname' => $motherLastname,
            'mother_givenname' => $motherGivenname,
            'mother_middlename' => $motherMiddlename,
            'mother_citizenship' => $motherCitizenship,
            'companion_name' => $companionName,
            'companion_relationship' => $companionRelationship,
            'application_type' => $rawAppType,
            'credential_reason' => $credentialReason,
            'resolved_application_type' => $resolvedAppType,
            'age' => $age,
        ];
    }

    $applicantCount = count($normalizedMembers);
    $leadMember = $normalizedMembers[0];

    $leadEmail = normalizeRequiredString(
        $topLevelEmail ?? $leadMember['email'],
        'group@applicant.com',
        255
    );
    if ($normalizedMembers[0]['email'] === null) {
        $normalizedMembers[0]['email'] = $leadEmail;
    }

    $groupMembersJson = json_encode($normalizedMembers, JSON_UNESCAPED_UNICODE);
    if ($groupMembersJson === false) {
        $groupMembersJson = '[]';
    }

    // Generate single unique group ID (PH-GRP-XXXXXX, no -1/-2 suffix)
    $groupId = null;
    $checkStmt = $pdo->prepare("SELECT id FROM applications WHERE application_id = ? LIMIT 1");
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $candidate = 'PH-GRP-' . random_int(100000, 999999);
        $checkStmt->execute([$candidate]);
        if (!$checkStmt->fetch()) {
            $groupId = $candidate;
            break;
        }
    }

    if ($groupId === null) {
        throw new RuntimeException('Unable to generate a unique group application ID. Please try again.');
    }

    $uniqueResolvedTypes = array_values(array_unique($resolvedTypes));
    $rawGroupAppType = "Group ({$applicantCount}): " . implode(', ', $uniqueResolvedTypes);
    $groupApplicationType = function_exists('mb_substr')
        ? mb_substr($rawGroupAppType, 0, 150, 'UTF-8')
        : substr($rawGroupAppType, 0, 150);

    $status = 'Form Submitted';
    $processing_type = 'Regular Processing';
    $totalAmount = round(950.00 * $applicantCount, 2);
    $payment_status = 'Pending';
    $receiving_option = normalizeRequiredString($input['receiving_option'] ?? null, 'In-Person', 50);
    $delivery_address = normalizeNullableString($input['delivery_address'] ?? null, 500);

    // Lead applicant personal fields with MySQL strict-mode safe normalization
    $leadGivenname = normalizeRequiredString($leadMember['givenname'], 'Not Provided', 100);
    $leadMiddlename = normalizeRequiredString($leadMember['middlename'], '', 100);
    $leadLastname = normalizeRequiredString($leadMember['lastname'], 'Not Provided', 100);
    $leadExtname = normalizeRequiredString($leadMember['extname'], '', 50);
    $leadDob = $leadMember['dob']; // null if empty
    $leadSex = normalizeRequiredString($leadMember['sex'], 'Unspecified', 20);
    $leadCivilStatus = normalizeRequiredString($leadMember['civil_status'], 'SINGLE', 50);
    $leadBirthPlace = normalizeRequiredString($leadMember['birth_place'], 'Philippines', 255);
    $leadCitizenship = normalizeRequiredString($leadMember['citizenship'], 'Filipino', 100);
    $leadPassportType = normalizeRequiredString($leadMember['passport_type'], 'Regular', 50);
    $leadMobile = normalizeRequiredString($leadMember['mobile'], '09000000000', 50);
    $leadAddress = normalizeRequiredString($leadMember['address'], 'Not Provided', 500);
    $leadEmergencyContact = normalizeRequiredString($leadMember['emergency_contact'], 'Not Provided', 255);
    $leadEmergencyPhone = normalizeRequiredString($leadMember['emergency_phone'], '09000000000', 50);

    // Candidate column map filtered dynamically against actual database columns
    $candidateColumns = [
        'application_id'       => $groupId,
        'email'                => $leadEmail,
        'application_type'     => $groupApplicationType,
        'status'               => $status,
        'processing_type'      => $processing_type,
        'amount'               => $totalAmount,
        'payment_status'       => $payment_status,
        'receiving_option'     => $receiving_option,
        'delivery_address'     => $delivery_address,
        'appointment_date'     => $appointment_date,
        'appointment_location' => $appointment_location,
        'appointment_time'     => $appointment_time,
        'givenname'            => $leadGivenname,
        'middlename'           => $leadMiddlename,
        'lastname'             => $leadLastname,
        'extname'              => $leadExtname,
        'dob'                  => $leadDob,
        'sex'                  => $leadSex,
        'civil_status'         => $leadCivilStatus,
        'birth_place'          => $leadBirthPlace,
        'citizenship'          => $leadCitizenship,
        'passport_type'        => $leadPassportType,
        'mobile'               => $leadMobile,
        'address'              => $leadAddress,
        'emergency_contact'    => $leadEmergencyContact,
        'emergency_phone'      => $leadEmergencyPhone,
        'group_members'        => $groupMembersJson,
    ];

    $insertColumns = [];
    $insertPlaceholders = [];
    $insertValues = [];

    foreach ($candidateColumns as $colKey => $colValue) {
        $lowerKey = strtolower($colKey);
        if (isset($existingColumns[$lowerKey])) {
            $insertColumns[] = '`' . $existingColumns[$lowerKey] . '`';
            $insertPlaceholders[] = '?';
            $insertValues[] = $colValue;
        }
    }

    if (isset($existingColumns['created_at'])) {
        $insertColumns[] = '`' . $existingColumns['created_at'] . '`';
        $insertPlaceholders[] = 'CURRENT_TIMESTAMP';
    }

    if (empty($insertColumns)) {
        throw new RuntimeException('No valid columns found in applications table.');
    }

    $sql = sprintf(
        'INSERT INTO applications (%s) VALUES (%s)',
        implode(', ', $insertColumns),
        implode(', ', $insertPlaceholders)
    );

    // Execute single INSERT inside transaction
    $pdo->beginTransaction();

    $stmt = $pdo->prepare($sql);
    $stmt->execute($insertValues);

    $pdo->commit();

    // Post-commit non-blocking audit log
    try {
        if (function_exists('logAudit')) {
            logAudit(
                $pdo,
                'GROUP_APPLICATION_SUBMITTED',
                'application',
                $groupId,
                null,
                'Form Submitted',
                "Group passport application submitted for {$applicantCount} applicants.",
                $leadEmail,
                'member'
            );
        }
    } catch (Throwable $auditEx) {
        error_log('submit-group-application.php audit error: ' . $auditEx->getMessage());
    }

    $_SESSION['currentApplicationId'] = $groupId;
    $_SESSION['userEmail'] = $leadEmail;

    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'groupCode' => $groupId,
        'application_id' => $groupId,
        'applicationId' => $groupId,
        'applicant_count' => $applicantCount,
        'amount' => $totalAmount,
        'applicationType' => $groupApplicationType,
        'members' => $normalizedMembers,
        'message' => "Group application {$groupId} submitted successfully for {$applicantCount} applicants."
    ]);
    exit;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('submit-group-application.php error: ' . $e->getMessage());

    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Unable to save group application: ' . $e->getMessage()
    ]);
    exit;
}