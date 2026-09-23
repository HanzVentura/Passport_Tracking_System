<?php
session_start();
try {
    $dbPath = __DIR__ . '/../database.db';
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = !empty($_POST['email']) ? $_POST['email'] : '';
        if (!$email) {
            die("Error: Email is required.");
        }
        
        $application_id = 'PH-' . rand(100000, 999999);
        
        // Explicitly set type to Individual Appointment
        $application_type = 'Individual Appointment';
        $status = 'Pending Payment';
        
        $appointment_location = !empty($_POST['site']) ? $_POST['site'] : (!empty($_POST['site_selection']) ? $_POST['site_selection'] : '');
        $appointment_date = !empty($_POST['app_date']) ? $_POST['app_date'] : '';
        $appointment_time = !empty($_POST['app_time']) ? $_POST['app_time'] : '';
        
        $sql = "INSERT INTO applications (application_id, email, application_type, status, appointment_date, appointment_location, appointment_time, created_at)
                VALUES (:app_id, :email, :app_type, :status, :app_date, :app_location, :app_time, CURRENT_TIMESTAMP)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':app_id' => $application_id,
            ':email' => $email,
            ':app_type' => $application_type,
            ':status' => $status,
            ':app_date' => $appointment_date,
            ':app_location' => $appointment_location,
            ':app_time' => $appointment_time
        ]);
        
        setcookie('userEmail', $email, time() + (86400 * 30), "/");
        setcookie('currentApplicationId', $application_id, time() + (86400 * 30), "/");
        
        header("Location: ../public/payments.html?id=" . $application_id);
        exit();
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>