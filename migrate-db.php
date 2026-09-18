<?php
$dbPath = __DIR__ . '/database.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $columns = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!$columns) {
        throw new RuntimeException('The users table does not exist.');
    }

    $pdo->beginTransaction();

    if (!in_array('two_factor_enabled', $columns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN two_factor_enabled INTEGER DEFAULT 0;');
    }

    if (in_array('otp_code', $columns, true) && !in_array('passcode', $columns, true)) {
        $pdo->exec('ALTER TABLE users RENAME COLUMN otp_code TO passcode;');
    } elseif (!in_array('passcode', $columns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN passcode TEXT DEFAULT NULL;');
    }

    $pdo->commit();

    echo "<h2 style='color: green;'>Success! Database columns are ready at: " . $dbPath . "</h2>";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "<h2 style='color: orange;'>Notice: " . $e->getMessage() . "</h2>";
}
?>