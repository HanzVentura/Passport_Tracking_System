<?php
$dbPath = __DIR__ . '/database.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->beginTransaction();

    // 1. Check and migrate users table columns
    $userColumns = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!empty($userColumns)) {
        if (!in_array('two_factor_enabled', $userColumns, true)) {
            $pdo->exec('ALTER TABLE users ADD COLUMN two_factor_enabled INTEGER DEFAULT 0;');
        }
        if (in_array('otp_code', $userColumns, true) && !in_array('passcode', $userColumns, true)) {
            $pdo->exec('ALTER TABLE users RENAME COLUMN otp_code TO passcode;');
        } elseif (!in_array('passcode', $userColumns, true)) {
            $pdo->exec('ALTER TABLE users ADD COLUMN passcode TEXT DEFAULT NULL;');
        }
    }

    // 2. Ensure applications table exists with application_id
    $pdo->exec("CREATE TABLE IF NOT EXISTS applications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        application_id TEXT,
        email TEXT,
        application_type TEXT,
        status TEXT,
        processing_type TEXT,
        amount NUMERIC,
        payment_status TEXT,
        payment_method TEXT,
        appointment_date TEXT,
        appointment_location TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // If table already existed without application_id, add it safely
    $appColumns = $pdo->query('PRAGMA table_info(applications)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!empty($appColumns) && !in_array('application_id', $appColumns, true)) {
        $pdo->exec('ALTER TABLE applications ADD COLUMN application_id TEXT;');
    }

    $pdo->commit();

    echo "<h2 style='color: green;'>Success! Database schema and application_id are fully up to date.</h2>";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<h2 style='color: orange;'>Notice: " . $e->getMessage() . " </h2>";
}
?>