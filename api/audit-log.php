<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth-helper.php';
requireAdmin();

try {
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 200;
    $limit = max(1, min($limit, 1000));
    $action = trim($_GET['action'] ?? '');
    $entityType = trim($_GET['entity_type'] ?? '');
    $search = trim($_GET['search'] ?? '');

    $where = [];
    $params = [];

    if ($action !== '') {
        $where[] = 'action_name = ?';
        $params[] = $action;
    }
    if ($entityType !== '') {
        $where[] = 'entity_type = ?';
        $params[] = $entityType;
    }
    if ($search !== '') {
        $where[] = '(actor_email LIKE ? OR entity_id LIKE ? OR action_name LIKE ? OR details LIKE ?)';
        $needle = '%' . $search . '%';
        array_push($params, $needle, $needle, $needle, $needle);
    }

    $sql = 'SELECT id, action_name, entity_type, entity_id, actor_email, actor_role,
                   old_value, new_value, details, ip_address, user_agent, created_at
            FROM audit_logs';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY id DESC LIMIT ' . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'logs' => $logs]);
} catch (PDOException $e) {
    error_log('audit-log.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load audit trail.']);
}
?>
