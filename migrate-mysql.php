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

    echo "<h2 style='color: green;'>Success! MySQL tables created successfully with full 6-step workflow support.</h2>";
} catch (Throwable $e) {
    echo "<h2 style='color: red;'>Error: " . $e->getMessage() . "</h2>";
}
?>