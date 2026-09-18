<?php
try {
    $dbPath = __DIR__ . '/database.db';$pdo = new PDO('sqlite:' . $dbPath);$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_enabled INTEGER DEFAULT 0;");
    $pdo->exec("ALTER TABLE users ADD COLUMN passcode TEXT DEFAULT NULL;");

    echo "<h2 style='color: green;'>Success! Columns added.</h2>";
} catch (Exception $e) {
    echo "<h2 style='color: orange;'>Notice: " . $e->getMessage() . "</h2>";
}
?>
