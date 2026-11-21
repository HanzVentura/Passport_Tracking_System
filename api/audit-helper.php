<?php

function ensureAuditTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        action_name VARCHAR(100) NOT NULL,
        entity_type VARCHAR(100) NOT NULL,
        entity_id VARCHAR(100) DEFAULT NULL,
        actor_email VARCHAR(255) DEFAULT NULL,
        actor_role VARCHAR(50) DEFAULT NULL,
        old_value VARCHAR(255) DEFAULT NULL,
        new_value VARCHAR(255) DEFAULT NULL,
        details TEXT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_audit_action (action_name),
        INDEX idx_audit_entity (entity_type, entity_id),
        INDEX idx_audit_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

function logAudit(
    PDO $pdo,
    string $action,
    string $entityType,
    ?string $entityId = null,
    ?string $oldValue = null,
    ?string $newValue = null,
    ?string $details = null,
    ?string $actorEmail = null,
    ?string $actorRole = null
): void {
    ensureAuditTable($pdo);

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if ($actorEmail === null) {
        $actorEmail = $_SESSION['user_email']
            ?? $_SESSION['userEmail']
            ?? null;
    }
    if ($actorRole === null) {
        $actorRole = $_SESSION['user_role']
            ?? ($_SESSION['user_id'] ? 'member' : null);
    }

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    if ($userAgent !== null && strlen($userAgent) > 255) {
        $userAgent = substr($userAgent, 0, 255);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO audit_logs
            (action_name, entity_type, entity_id, actor_email, actor_role,
             old_value, new_value, details, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $action,
        $entityType,
        $entityId,
        $actorEmail,
        $actorRole,
        $oldValue,
        $newValue,
        $details,
        $ipAddress,
        $userAgent
    ]);
}
?>
