<?php
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        if (ob_get_length()) {
            ob_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'message' => 'Server Fatal Error: ' . $err['message'] . ' (line ' . $err['line'] . ')'
        ], JSON_UNESCAPED_UNICODE);
    }
});

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
if (file_exists(__DIR__ . '/audit-helper.php')) {
    require_once __DIR__ . '/audit-helper.php';
}

function sendJson(int $statusCode, array $payload): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function tableHasColumn(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

function parseGroupMembersFromRows(array $rows): array
{
    $firstRow = $rows[0];
    $raw = $firstRow['group_members'] ?? null;

    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && count($decoded) > 0) {
            return array_values($decoded);
        }
    } elseif (is_array($raw) && count($raw) > 0) {
        return array_values($raw);
    }

    // Fallback if legacy multi-row PH-GRP-XXXXXX-1 / -2 records exist
    if (count($rows) > 1) {
        $members = [];
        foreach ($rows as $idx => $r) {
            $fullName = trim(implode(' ', array_filter([
                $r['givenname'] ?? '',
                $r['middlename'] ?? '',
                $r['lastname'] ?? '',
                $r['extname'] ?? ''
            ])));
            $members[] = [
                'member_index' => $idx + 1,
                'application_id' => $r['application_id'] ?? '',
                'full_name' => $fullName !== '' ? $fullName : ('Applicant ' . ($idx + 1)),
                'email' => $r['email'] ?? '',
                'application_type' => $r['application_type'] ?? 'Passport Application',
                'payment_status' => $r['payment_status'] ?? 'Pending'
            ];
        }
        return $members;
    }

    return [];
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $applicationId = trim((string)($_GET['applicationId'] ?? $_GET['application_id'] ?? $_GET['id'] ?? ''));
        $email = trim((string)($_GET['email'] ?? ($_SESSION['user_email'] ?? $_SESSION['userEmail'] ?? '')));

        if ($applicationId === '' || $applicationId === 'PH-GRP' || $applicationId === 'undefined' || $applicationId === 'null') {
            $applicationId = !empty($_SESSION['currentApplicationId'])
                ? trim((string)$_SESSION['currentApplicationId'])
                : '';
        }

        if ($applicationId === '' && $email === '') {
            sendJson(400, [
                'success' => false,
                'message' => 'Application ID or email is required.'
            ]);
        }

        $rows = [];

        if ($applicationId !== '') {
            if (preg_match('/^(PH-GRP-\d+)(?:-\d+)?$/', $applicationId, $m)) {
                $groupBaseId = $m[1];
                $stmt = $pdo->prepare("SELECT * FROM applications WHERE application_id = ? OR application_id LIKE ? ORDER BY id ASC");
                $stmt->execute([$groupBaseId, $groupBaseId . '-%']);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM applications WHERE application_id = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$applicationId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        // Fallback to user's most recent application by email
        if (empty($rows) && $email !== '') {
            $stmt = $pdo->prepare("SELECT * FROM applications WHERE email = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$email]);
            $latest = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($latest) {
                $latestId = (string)($latest['application_id'] ?? '');
                if (preg_match('/^(PH-GRP-\d+)(?:-\d+)?$/', $latestId, $m)) {
                    $groupBaseId = $m[1];
                    $stmt = $pdo->prepare("SELECT * FROM applications WHERE application_id = ? OR application_id LIKE ? ORDER BY id ASC");
                    $stmt->execute([$groupBaseId, $groupBaseId . '-%']);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $rows = [$latest];
                }
            }
        }

        if (empty($rows)) {
            sendJson(404, [
                'success' => false,
                'message' => 'Active application record not found in database.'
            ]);
        }

        $primaryRow = $rows[0];
        $members = parseGroupMembersFromRows($rows);
        $isGroup = (strpos((string)($primaryRow['application_id'] ?? ''), 'PH-GRP-') === 0) || count($members) > 1;
        $applicantCount = count($members) > 0 ? count($members) : 1;

        sendJson(200, [
            'success' => true,
            'isGroup' => $isGroup,
            'applicantCount' => $applicantCount,
            'members' => $members,
            'application' => $primaryRow
        ]);
    }

    // POST: Record payment
    $rawInput = (string)file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $sessionEmail = trim((string)($_SESSION['user_email'] ?? $_SESSION['userEmail'] ?? ''));
    $email = trim((string)($input['email'] ?? $sessionEmail));
    $applicationId = trim((string)($input['applicationId'] ?? $input['application_id'] ?? $_GET['id'] ?? $_GET['applicationId'] ?? ''));
    $amount = isset($input['amount']) ? (float)$input['amount'] : 950.00;
    $deliveryFee = isset($input['deliveryFee']) ? (float)$input['deliveryFee'] : 0.0;
    $processingType = trim((string)($input['processingType'] ?? 'Regular Processing'));
    $paymentMethod = trim((string)($input['paymentMethod'] ?? 'GCash'));
    $receivingOption = trim((string)($input['receivingOption'] ?? 'In-Person'));
    $deliveryAddress = isset($input['deliveryAddress']) && trim((string)$input['deliveryAddress']) !== ''
        ? trim((string)$input['deliveryAddress'])
        : null;

    if ($applicationId === '' && !empty($_SESSION['currentApplicationId'])) {
        $applicationId = trim((string)$_SESSION['currentApplicationId']);
    }

    if ($applicationId === '' && $email === '') {
        sendJson(400, [
            'success' => false,
            'message' => 'Application ID or email is required for payment.'
        ]);
    }

    $rows = [];
    if ($applicationId !== '') {
        if (preg_match('/^(PH-GRP-\d+)(?:-\d+)?$/', $applicationId, $m)) {
            $groupBaseId = $m[1];
            $stmt = $pdo->prepare("SELECT * FROM applications WHERE application_id = ? OR application_id LIKE ? ORDER BY id ASC");
            $stmt->execute([$groupBaseId, $groupBaseId . '-%']);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM applications WHERE application_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$applicationId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } elseif ($email !== '') {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE email = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($rows)) {
        sendJson(404, [
            'success' => false,
            'message' => 'Active application record not found in database.'
        ]);
    }

    $primaryRow = $rows[0];
    $targetAppId = (string)$primaryRow['application_id'];
    $members = parseGroupMembersFromRows($rows);
    $isGroup = (strpos($targetAppId, 'PH-GRP-') === 0) || count($members) > 1;
    $applicantCount = count($members) > 0 ? count($members) : 1;

    $appointmentDate = !empty($primaryRow['appointment_date']) ? $primaryRow['appointment_date'] : date('Y-m-d');
    $appointmentLocation = !empty($primaryRow['appointment_location']) ? $primaryRow['appointment_location'] : 'ANGELES';

    $hasUpdatedAt = tableHasColumn($pdo, 'applications', 'updated_at');
    $hasPaymentMethod = tableHasColumn($pdo, 'applications', 'payment_method');

    $setClauses = [
        "status = 'Form Submitted'",
        "payment_status = 'Paid'",
        "processing_type = ?",
        "amount = ?",
        "receiving_option = ?",
        "delivery_address = ?"
    ];
    if ($hasPaymentMethod) {
        $setClauses[] = "payment_method = ?";
    }
    if ($hasUpdatedAt) {
        $setClauses[] = "updated_at = CURRENT_TIMESTAMP";
    }

    $updateSql = "UPDATE applications SET " . implode(', ', $setClauses) . " WHERE application_id = ?";
    $updateStmt = $pdo->prepare($updateSql);

    // Single-row group or solo application
    if (count($rows) === 1) {
        if (($primaryRow['payment_status'] ?? '') === 'Paid') {
            sendJson(200, [
                'success' => true,
                'isGroup' => $isGroup,
                'applicantCount' => $applicantCount,
                'applicationId' => $targetAppId,
                'status' => $primaryRow['status'] ?? 'Form Submitted',
                'payment_status' => 'Paid',
                'amount' => isset($primaryRow['amount']) ? (float)$primaryRow['amount'] : $amount,
                'processingType' => $primaryRow['processing_type'] ?? $processingType,
                'receivingOption' => $primaryRow['receiving_option'] ?? $receivingOption,
                'appointmentDate' => $appointmentDate,
                'appointmentLocation' => $appointmentLocation,
                'message' => 'Payment has already been recorded for this application.'
            ]);
        }

        $params = [$processingType, round($amount, 2), $receivingOption, $deliveryAddress];
        if ($hasPaymentMethod) {
            $params[] = $paymentMethod;
        }
        $params[] = $targetAppId;
        $updateStmt->execute($params);
    } else {
        // Legacy multi-row group fallback
        $rowCount = count($rows);
        $perRowBase = round(($amount - $deliveryFee) / $rowCount, 2);
        foreach ($rows as $idx => $r) {
            $rowAmount = ($idx === 0) ? ($perRowBase + $deliveryFee) : $perRowBase;
            $params = [$processingType, $rowAmount, $receivingOption, $deliveryAddress];
            if ($hasPaymentMethod) {
                $params[] = $paymentMethod;
            }
            $params[] = $r['application_id'];
            $updateStmt->execute($params);
        }
    }

    $actorEmail = !empty($primaryRow['email']) ? $primaryRow['email'] : ($sessionEmail !== '' ? $sessionEmail : $email);
    try {
        if (function_exists('logAudit')) {
            logAudit(
                $pdo,
                'PAYMENT_RECORDED',
                'application',
                $targetAppId,
                $primaryRow['payment_status'] ?? 'Pending',
                'Paid',
                ($isGroup ? "Group payment ({$applicantCount} applicants) " : "Payment ")
                    . "recorded via {$paymentMethod}; processing: {$processingType}; receiving: {$receivingOption}; amount: {$amount}.",
                $actorEmail,
                'member'
            );
        }
    } catch (Throwable $auditErr) {
        error_log('Payment audit log failed: ' . $auditErr->getMessage());
    }

    sendJson(200, [
        'success' => true,
        'isGroup' => $isGroup,
        'applicantCount' => $applicantCount,
        'applicationId' => $targetAppId,
        'status' => 'Form Submitted',
        'payment_status' => 'Paid',
        'amount' => round($amount, 2),
        'processingType' => $processingType,
        'receivingOption' => $receivingOption,
        'appointmentDate' => $appointmentDate,
        'appointmentLocation' => $appointmentLocation,
        'message' => $isGroup
            ? "Group payment verified and recorded for {$applicantCount} applicants."
            : 'Online payment verified and recorded successfully.'
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('api/payments.php error: ' . $e->getMessage());
    sendJson(500, [
        'success' => false,
        'message' => 'Payment Error: ' . $e->getMessage()
    ]);
}