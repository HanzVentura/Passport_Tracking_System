<?php
session_start();
try {
    $dbPath = __DIR__ . '/../database.db';
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = $_POST['email'] ?? '';
        $incoming_id = $_POST['application_id'] ?? '';
        
        if (!$email) {
            die("Email is required.");
        }

        // Check if this user already has a pending application
        $checkStmt = $db->prepare("SELECT application_id FROM applications WHERE email = ? AND status = 'Pending Payment' ORDER BY id DESC LIMIT 1");
        $checkStmt->execute([$email]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing && !empty($existing['application_id'])) {
            $application_id = $existing['application_id'];
        } elseif (!empty($incoming_id)) {
            $application_id = $incoming_id; // Use the pre-allocated ID from Step 6 review screen
        } else {
            $application_id = 'PH-' . rand(100000, 999999);
        }

        // Dynamically map application type from Step 5 selection
        $raw_app_type = $_POST['application_type'] ?? 'FIRST TIME PASSPORT';
        if ($raw_app_type === 'LOST PASSPORT') {
            $application_type = 'Replacement of Lost Passport';
        } elseif ($raw_app_type === 'DAMAGED PASSPORT') {
            $application_type = 'Replacement of Damaged Passport';
        } else {
            $application_type = 'New First-Time Application';
        }

        $status = 'Pending Payment';
        $appointment_date = $_POST['app_date'] ?? '';
        $appointment_location = $_POST['site'] ?? '';

        // If an application row already exists, update it; otherwise insert a new one
        if ($existing) {
            $sql = "UPDATE applications SET appointment_date = :app_date, appointment_location = :app_location, application_type = :app_type, updated_at = CURRENT_TIMESTAMP WHERE application_id = :app_id";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':app_date' => $appointment_date,
                ':app_location' => $appointment_location,
                ':app_type' => $application_type,
                ':app_id' => $application_id
            ]);
        } else {
            $sql = "INSERT INTO applications (application_id, email, application_type, status, appointment_date, appointment_location, created_at)
                    VALUES (:app_id, :email, :app_type, :status, :app_date, :app_location, CURRENT_TIMESTAMP)";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':app_id' => $application_id,
                ':email' => $email,
                ':app_type' => $application_type,
                ':status' => $status,
                ':app_date' => $appointment_date,
                ':app_location' => $appointment_location
            ]);
        }

        setcookie('userEmail', $email, time() + (86400 * 30), "/");
        header("Location: ../public/payments.html?id=" . $application_id);
        exit();
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>