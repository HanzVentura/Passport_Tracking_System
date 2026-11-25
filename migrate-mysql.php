<?php
require_once __DIR__ . '/db.php';

try {
    // 1. Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fullname VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        two_factor_enabled TINYINT DEFAULT 0,
        passcode VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Admin accounts are stored separately from member accounts.
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        name VARCHAR(255) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Import the existing admin accounts once; existing database records remain authoritative.
    $adminAccounts = require __DIR__ . '/api/login_config.php';
    $adminLookup = $pdo->prepare('SELECT id FROM admin_users WHERE email = ?');
    $adminInsert = $pdo->prepare('INSERT INTO admin_users (email, name, password_hash) VALUES (?, ?, ?)');
    foreach ($adminAccounts as $email => $admin) {
        if (!is_array($admin) || empty($admin['name']) || empty($admin['password_hash'])) {
            throw new RuntimeException('Invalid admin account seed data.');
        }

        $email = strtolower(trim((string)$email));
        $adminLookup->execute([$email]);
        if ($adminLookup->fetchColumn() !== false) {
            continue;
        }

        $adminInsert->execute([$email, $admin['name'], $admin['password_hash']]);
    }

    // 2. Applications table with 6-step lifecycle support
    $pdo->exec("CREATE TABLE IF NOT EXISTS applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id VARCHAR(100) UNIQUE NOT NULL,
        email VARCHAR(255) NOT NULL,
        application_type VARCHAR(100) NOT NULL,
        status VARCHAR(100) DEFAULT 'Form Submitted',
        processing_type VARCHAR(100) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_status VARCHAR(50) DEFAULT 'Pending',
        payment_method VARCHAR(50) DEFAULT NULL,
        receiving_option VARCHAR(50) DEFAULT 'In-Person',
        delivery_address TEXT DEFAULT NULL,
        delivery_fee DECIMAL(10,2) DEFAULT 0.00,
        tracking_number VARCHAR(100) DEFAULT NULL,
        applicant_count INT NOT NULL DEFAULT 1,
        appointment_date VARCHAR(50) DEFAULT NULL,
        appointment_time VARCHAR(50) DEFAULT NULL,
        appointment_location VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Add columns to databases created by earlier migrations.
    $applicationColumns = [
        'receiving_option' => "ALTER TABLE applications ADD COLUMN receiving_option VARCHAR(50) DEFAULT 'In-Person'",
        'delivery_address' => "ALTER TABLE applications ADD COLUMN delivery_address TEXT DEFAULT NULL",
        'delivery_fee' => "ALTER TABLE applications ADD COLUMN delivery_fee DECIMAL(10,2) DEFAULT 0.00",
        'tracking_number' => "ALTER TABLE applications ADD COLUMN tracking_number VARCHAR(100) DEFAULT NULL",
        'applicant_count' => "ALTER TABLE applications ADD COLUMN applicant_count INT NOT NULL DEFAULT 1",
    ];
    foreach ($applicationColumns as $column => $alterSql) {
        $columnStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applications' AND COLUMN_NAME = ?"
        );
        $columnStmt->execute([$column]);
        if ((int)$columnStmt->fetchColumn() === 0) {
            $pdo->exec($alterSql);
        }
    }

    // 3. Enquiries table
    $pdo->exec("CREATE TABLE IF NOT EXISTS enquiries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        application_id VARCHAR(100) DEFAULT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(50) DEFAULT 'Open',
        reply TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 4. Audit trail
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

    // 5. Saved application drafts
    $pdo->exec("CREATE TABLE IF NOT EXISTS application_drafts (
        user_id INT NOT NULL,
        draft_type VARCHAR(20) NOT NULL,
        draft_data LONGTEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, draft_type),
        CONSTRAINT fk_application_drafts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "<h2 style='color: green;'>Success! MySQL tables are ready, including admin accounts and saved application drafts.</h2>";
} catch (Throwable $e) {
    echo "<h2 style='color: red;'>Error: " . $e->getMessage() . "</h2>";
}
?>