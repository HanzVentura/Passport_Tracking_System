<?php
header('Content-Type: application/json');
require_once __DIR__ . '/auth-helper.php';
startSecureSession();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/audit-helper.php';

try {
    $applicationId = trim($_GET['applicationId'] ?? $_GET['application_id'] ?? $_GET['id'] ?? '');
    $requestedEmail = trim($_GET['email'] ?? '');
    $sessionEmail = getSessionEmail();

    if ($requestedEmail !== '' && $sessionEmail !== null && strtolower($requestedEmail) !== $sessionEmail) {
        jsonError('You cannot view another member\'s application.', 403);
    }
    $email = $applicationId === '' ? ($sessionEmail ?: $requestedEmail) : ($sessionEmail ?? '');

    if ($applicationId === '' && $email === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Application ID or email is required.'
        ]);
        exit;
    }

    if ($applicationId !== '') {
        if ($email !== '') {
            $stmt = $pdo->prepare("SELECT * FROM applications WHERE (application_id = ? OR id = ?) AND email = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$applicationId, $applicationId, $email]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM applications WHERE application_id = ? OR id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$applicationId, $applicationId]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } else {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE email = ? AND status != 'Cancelled' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$application) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No active application record was found.'
        ]);
        exit;
    }

    if (($application['status'] ?? '') === 'Appointment Rescheduled') {
        ensureAuditTable($pdo);
        $statusAuditStmt = $pdo->prepare(
            "SELECT new_value FROM audit_logs
             WHERE entity_type = 'application'
               AND entity_id = ?
               AND action_name = 'APPLICATION_STATUS_UPDATED'
               AND new_value IS NOT NULL
               AND new_value <> ''
             ORDER BY id DESC LIMIT 1"
        );
        $statusAuditStmt->execute([$application['application_id']]);
        $restoredStatus = $statusAuditStmt->fetchColumn();
        if (is_string($restoredStatus) && $restoredStatus !== '') {
            $application['status'] = $restoredStatus;
            $restoreStmt = $pdo->prepare(
                "UPDATE applications SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
            );
            $restoreStmt->execute([$restoredStatus, $application['id']]);
        }
    }

    echo json_encode([
        'success' => true,
        'application' => $application
    ]);
} catch (Exception $e) {
    error_log('get-application.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load application.'
    ]);
}