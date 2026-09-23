<?php
session_start();
try {
    $dbPath = __DIR__ . '/../database.db';
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $groupData = [];
        if (isset($_POST['group_data_payload']) && !empty($_POST['group_data_payload'])) {
            $groupData = json_decode($_POST['group_data_payload'], true);
        }
        $app1 = $groupData['applicant_1'] ?? [];
       
        $email = !empty($_POST['email']) ? $_POST['email'] : ($app1['email'] ?? '');
        if (!$email) {
            die("Error: Email is required.");
        }

        // Generate a fresh unique application ID for group bookings
        $application_id = 'PH-' . rand(100000, 999999);
        $application_type = 'Group Appointment';
        $status = 'Pending Payment';
       
        // Extract site, date, and time strictly from user choices (checking non-empty values)
        $appointment_location = !empty($app1['site_selection']) ? $app1['site_selection'] : (!empty($_POST['site']) ? $_POST['site'] : ($_POST['site_selection'] ?? ''));
        $appointment_date     = !empty($app1['app_date_selection']) ? $app1['app_date_selection'] : (!empty($_POST['app_date']) ? $_POST['app_date'] : '');
        $appointment_time     = !empty($app1['app_time_selection']) ? $app1['app_time_selection'] : (!empty($_POST['app_time']) ? $_POST['app_time'] : '');
       
        // Insert new group application record with exact user selections
        $sql = "INSERT INTO applications (application_id, email, application_type, status, appointment_date, appointment_location, appointment_time, created_at)
                VALUES (:app_id, :email, :app_type, :status, :app_date, :app_location, :app_time, CURRENT_TIMESTAMP)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':app_id'       => $application_id,
            ':email'        => $email,
            ':app_type'     => $application_type,
            ':status'       => $status,
            ':app_date'     => $appointment_date,
            ':app_location' => $appointment_location,
            ':app_time'     => $appointment_time
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