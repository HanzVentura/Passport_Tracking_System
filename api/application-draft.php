<?php
require_once __DIR__ . '/auth-helper.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');
startSecureSession();
requireMember();

$userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
if (!$userId) {
    jsonError('Authenticated member ID is unavailable.', 401);
}

$allowedTypes = ['individual', 'group'];
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $draftType = $_GET['type'] ?? '';
    if (!in_array($draftType, $allowedTypes, true)) {
        jsonError('Invalid draft type.', 400);
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT draft_data, updated_at FROM application_drafts WHERE user_id = ? AND draft_type = ?'
        );
        $stmt->execute([$userId, $draftType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $draft = $row ? json_decode($row['draft_data'], true) : null;
        if ($row && !is_array($draft)) {
            throw new RuntimeException('Saved draft data is invalid.');
        }

        echo json_encode([
            'success' => true,
            'draft' => $draft,
            'updatedAt' => $row['updated_at'] ?? null
        ]);
    } catch (Throwable $e) {
        error_log('application-draft.php load error: ' . $e->getMessage());
        jsonError('Unable to load your saved application draft.', 500);
    }
    exit;
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    jsonError('Method not allowed.', 405);
}

requireCsrfToken();
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    jsonError('Invalid draft request.', 400);
}

$draftType = $input['type'] ?? '';
if (!in_array($draftType, $allowedTypes, true)) {
    jsonError('Invalid draft type.', 400);
}

$action = $input['action'] ?? 'save';
try {
    if ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM application_drafts WHERE user_id = ? AND draft_type = ?');
        $stmt->execute([$userId, $draftType]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action !== 'save' || !is_array($input['data'] ?? null)) {
        jsonError('Invalid draft data.', 400);
    }

    $draftData = json_encode($input['data'], JSON_THROW_ON_ERROR);
    if (strlen($draftData) > 2 * 1024 * 1024) {
        jsonError('Draft is too large to save.', 413);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO application_drafts (user_id, draft_type, draft_data)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE draft_data = VALUES(draft_data), updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([$userId, $draftType, $draftData]);
    echo json_encode(['success' => true]);
} catch (JsonException $e) {
    jsonError('Draft contains data that cannot be saved.', 400);
} catch (Throwable $e) {
    error_log('application-draft.php save error: ' . $e->getMessage());
    jsonError('Unable to save your application draft.', 500);
}
?>
