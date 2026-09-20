<?php
try {
    $dbPath = __DIR__ . '/database.db';
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Safely add the application_id column to the applications table
    $pdo->exec("ALTER TABLE applications ADD COLUMN application_id TEXT;");

    echo "<h2 style='color: green;'>Success! application_id column added to applications table.</h2>";
} catch (Exception $e) {
    echo "<h2 style='color: orange;'>Notice: " . $e->getMessage() . " </h2>";
}
?>