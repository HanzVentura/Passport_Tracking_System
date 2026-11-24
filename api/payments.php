<?php
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/audit-helper.php';

function sendJsonResponse(int $statusCode, array $payload): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'POST';

    if ($method === 'GET') {
        $applicationId = trim($_GET['applicationId'] ?? $_GET['id'] ?? $_GET['application_id'] ?? '');
        $email = trim($_GET['email'] ?? ($_SESSION['user_email'] ?? ($_SESSION['userEmail'] ?? '')));

        if ($applicationId === '' && $email === '') {
            sendJsonResponse(400, [
                'success' => false,
                'message' => 'Application ID or email is required.'
            ]);
        }

        if ($applicationId !== '' && preg_match('/^PH-GRP-(\d+)(?:-\d+)?$/', $applicationId, $matches)) {
            $groupPrefix = 'PH-GRP-' . $matches[1];
            $stmt = $pdo->prepare("SELECT * FROM applications WHERE application_id = ? OR application_id LIKE ? ORDER BY id ASC");
            $stmt->execute([$groupPrefix, $groupPrefix . '-%']);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                sendJsonResponse(404, [
                    'success' => false,
                    'message' => 'Group application records not found in database.'
                ]);
            }

            $firstRow = $rows[0];
            $members = [];
            if (!empty($firstRow['group_members'])) {
                $decoded = json_decode((string)$firstRow['group_members'], true);
                if (is_array($decoded)) {
                    $members = $decoded;
                }
            }

            $applicantCount = count($members) > 0 ? count($members) : count($rows);

            sendJsonResponse(200, [
                'success' => true,
                'isGroup' => true,
                'groupPrefix' => $groupPrefix,
                'applicantCount' => $applicantCount,
                'members' => $members,
                'applications' => $rows,
                'application' => $firstRow
            ]);
        }

        if ($applicationId !== '') {
            $stmt = $pdo->prepare("SELECT * FROM applications WHERE application_id = ? OR id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$applicationId, $applicationId]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM applications WHERE email = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$email]);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            sendJsonResponse(404, [
                'success' => false,
                'message' => 'Active application record not found in database.'
            ]);
        }

        $foundId = (string)($row['application_id'] ?? '');
        $isGroupRow = strpos($foundId, 'PH-GRP-') === 0;
        $members = [];
        if (!empty($row['group_members'])) {
            $decoded = json_decode((string)$row['group_members'], true);
            if (is_array($decoded)) {
                $members = $decoded;
            }
        }
        $applicantCount = count($members) > 0 ? count($members) : 1;

        sendJsonResponse(200, [
            'success' => true,
            'isGroup' => $isGroupRow,
            'applicantCount' => $applicantCount,
            'members' => $members,
            'application' => $row
        ]);
    }

    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    $sessionEmail = trim($_SESSION['user_email'] ?? ($_SESSION['userEmail'] ?? ''));
    $email = trim($input['email'] ?? $sessionEmail);
    $applicationId = trim($input['applicationId'] ?? $input['application_id'] ?? $_GET['id'] ?? $_GET['applicationId'] ?? '');
    $amount = isset($input['amount']) ? (float)$input['amount'] : 950.00;
    $deliveryFee = isset($input['deliveryFee']) ? (float)$input['deliveryFee'] : 0.0;
    $processingType = trim($input['processingType'] ?? 'Regular Processing');
    $paymentMethod = trim($input['paymentMethod'] ?? 'GCash');
    $receivingOption = trim($input['receivingOption'] ?? 'In-Person');
    $deliveryAddress = isset($input['deliveryAddress']) && trim((string)$input['deliveryAddress']) !== ''
        ? trim((string)$input['deliveryAddress'])
        : null;

    if ($applicationId === '' && $email === '') {
        sendJsonResponse(400, [
            'success' => false,
            'message' => 'Application ID or email is required for payment.'
        ]);
    }

    // Consolidated Group Checkout Path (Single-row PH-GRP-XXXXXX or legacy PH-GRP-XXXXXX-N)
    if (preg_match('/^PH-GRP-(\d+)(?:-\d+)?$/', $applicationId, $groupMatches)) {
        $groupPrefix = 'PH-GRP-' . $groupMatches[1];

        $stmt = $pdo->prepare("SELECT * FROM applications WHERE application_id = ? OR application_id LIKE ? ORDER BY id ASC");
        $stmt->execute([$groupPrefix, $groupPrefix . '-%']);
        $siblingRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($siblingRows)) {
            sendJsonResponse(404, [
                'success' => false,
                'message' => 'Group application records not found in database.'
            ]);
        }

        $firstRow = $siblingRows[0];
        $appointmentDate = !empty($firstRow['appointment_date']) ? $firstRow['appointment_date'] : date('Y-m-d');
        $appointmentLocation = !empty($firstRow['appointment_location']) ? $firstRow['appointment_location'] : 'ANGELES';

        $allSiblingIds = array_map(static function ($row) {
            return $row['application_id'];
        }, $siblingRows);

        $unpaidRows = array_values(array_filter($siblingRows, static function ($row) {
            $pStatus = $row['payment_status'] ?? '';
            $sStatus = $row['status'] ?? '';
            return $pStatus !== 'Paid' && $sStatus !== 'Paid' && $sStatus !== 'Biometrics Received';
        }));

        // Determine total applicants in group from group_members JSON or sibling row count
        $memberCount = count($siblingRows);
        if (!empty($firstRow['group_members'])) {
            $decodedMembers = json_decode((string)$firstRow['group_members'], true);
            if (is_array($decodedMembers) && count($decodedMembers) > 0) {
                $memberCount = count($decodedMembers);
            }
        } elseif (!empty($input['applicantCount']) && (int)$input['applicantCount'] > 0) {
            $memberCount = (int)$input['applicantCount'];
        }

        // Idempotent check when group row(s) are already settled
        if (empty($unpaidRows)) {
            $existingTotal = 0.0;
            foreach ($siblingRows as $row) {
                $existingTotal += isset($row['amount']) ? (float)$row['amount'] : 0.0;
            }

            sendJsonResponse(200, [
                'success' => true,
                'isGroup' => true,
                'groupPrefix' => $groupPrefix,
                'applicationId' => $firstRow['application_id'],
                'settledIds' => $allSiblingIds,
                'settledCount' => $memberCount,
                'status' => $firstRow['status'] ?? 'Form Submitted',
                'payment_status' => 'Paid',
                'amount' => $existingTotal > 0 ? $existingTotal : $amount,
                'processingType' => $firstRow['processing_type'] ?? $processingType,
                'receivingOption' => $firstRow['receiving_option'] ?? $receivingOption,
                'appointmentDate' => $appointmentDate,
                'appointmentLocation' => $appointmentLocation,
                'message' => 'Payment has already been recorded for this group application.'
            ]);
        }

        // Check if optional columns (payment_method, updated_at) exist before building UPDATE
        $existingCols = [];
        $colsStmt = $pdo->query("SHOW COLUMNS FROM applications");
        if ($colsStmt) {
            foreach ($colsStmt->fetchAll(PDO::FETCH_ASSOC) as $colRow) {
                if (!empty($colRow['Field'])) {
                    $existingCols[strtolower((string)$colRow['Field'])] = (string)$colRow['Field'];
                }
            }
        }

        $unpaidRowCount = count($unpaidRows);
        $perRowAmount = $unpaidRowCount > 0
            ? round(($amount - $deliveryFee) / $unpaidRowCount, 2)
            : $amount;

        $pdo->beginTransaction();

        $settledIds = [];
        $queuedAudits = [];
        $totalSettledAmount = 0.0;

        foreach ($unpaidRows as $index => $row) {
            $rowAmount = ($index === 0) ? ($perRowAmount + $deliveryFee) : $perRowAmount;
            $rowAppId = $row['application_id'];

            $setClauses = [
                "status = 'Form Submitted'",
                "payment_status = 'Paid'",
                "processing_type = ?",
                "amount = ?",
                "receiving_option = ?"
            ];
            $updateParams = [
                $processingType,
                $rowAmount,
                $receivingOption
            ];

            if (isset($existingCols['payment_method'])) {
                $setClauses[] = "payment_method = ?";
                $updateParams[] = $paymentMethod;
            }
            if (isset($existingCols['delivery_address'])) {
                $setClauses[] = "delivery_address = ?";
                $updateParams[] = $deliveryAddress;
            }
            if (isset($existingCols['updated_at'])) {
                $setClauses[] = "updated_at = CURRENT_TIMESTAMP";
            }

            $updateParams[] = $rowAppId;
            $updateSql = "UPDATE applications SET " . implode(', ', $setClauses) . " WHERE application_id = ?";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute($updateParams);

            $settledIds[] = $rowAppId;
            $totalSettledAmount += $rowAmount;

            $queuedAudits[] = [
                'entity_id' => $rowAppId,
                'old_value' => $row['payment_status'] ?? 'Pending',
                'new_value' => 'Paid',
                'details' => 'Consolidated group payment (' . $groupPrefix . ', ' . $memberCount . ' applicants) recorded via ' . $paymentMethod . '; processing: ' . $processingType . '; receiving: ' . $receivingOption . '.',
                'actor_email' => !empty($row['email']) ? $row['email'] : ($sessionEmail !== '' ? $sessionEmail : $email)
            ];
        }

        if ($pdo->inTransaction()) {
            $pdo->commit();
        }

        // Non-blocking audit logging AFTER transaction commit
        foreach ($queuedAudits as $auditEntry) {
            try {
                if (function_exists('logAudit')) {
                    logAudit(
                        $pdo,
                        'PAYMENT_RECORDED',
                        'application',
                        $auditEntry['entity_id'],
                        $auditEntry['old_value'],
                        $auditEntry['new_value'],
                        $auditEntry['details'],
                        $auditEntry['actor_email'],
                        'member'
                    );
                }
            } catch (Throwable $auditErr) {
                error_log('Payment audit log failed: ' . $auditErr->getMessage());
            }
        }

        sendJsonResponse(200, [
            'success' => true,
            'isGroup' => true,
            'groupPrefix' => $groupPrefix,
            'applicationId' => $settledIds[0] ?? $firstRow['application_id'],
            'settledIds' => $settledIds,
            'settledCount' => $memberCount,
            'status' => 'Form Submitted',
            'payment_status' => 'Paid',
            'amount' => $totalSettledAmount,
            'processingType' => $processingType,
            'receivingOption' => $receivingOption,
            'appointmentDate' => $appointmentDate,
            'appointmentLocation' => $appointmentLocation,
            'message' => "Group payment verified and recorded for {$memberCount} " . ($memberCount === 1 ? 'applicant' : 'applicants') . '.'
        ]);
    }

    // Solo Application Checkout Path (PH-XXXXXX)
    if ($applicationId !== '') {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE application_id = ? OR id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$applicationId, $applicationId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE email = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email]);
    }

    $existingApp = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existingApp) {
        sendJsonResponse(404, [
            'success' => false,
            'message' => 'Active application record not found in database.'
        ]);
    }

    if (($existingApp['payment_status'] ?? '') === 'Paid') {
        sendJsonResponse(200, [
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
            updated_at = CURRENT_TIMESTAMP
        WHERE application_id = ?
    ");
    $update->execute([
        $processingType,
        $amount,
        $paymentMethod,
        $receivingOption,
        $deliveryAddress,
        $targetAppId
    ]);

    $actorEmail = $existingApp['email'] ?? ($sessionEmail !== '' ? $sessionEmail : $email);
    try {
        if (function_exists('logAudit')) {
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
        }
    } catch (Throwable $auditErr) {
        error_log('Payment audit log failed: ' . $auditErr->getMessage());
    }

    sendJsonResponse(200, [
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
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendJsonResponse(500, [
        'success' => false,
        'message' => 'Payment Error: ' . $e->getMessage()
    ]);
}