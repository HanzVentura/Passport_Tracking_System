<?php
session_start();
// Connect to MySQL database via centralized connection
require_once __DIR__ . '/../db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = !empty($_POST['email']) ? $_POST['email'] : '';
        if (!$email) {
            die("Error: Email is required.");
        }
       
        $application_id = 'PH-' . rand(100000, 999999);
       
        // Capture user-selected application type from the form wizard
        $application_type = !empty($_POST['application_type']) ? $_POST['application_type'] : 'Individual Appointment';
        $status = 'Form Submitted';
       
        $appointment_location = !empty($_POST['site']) ? $_POST['site'] : (!empty($_POST['site_selection']) ? $_POST['site_selection'] : '');
        $appointment_date = !empty($_POST['app_date']) ? $_POST['app_date'] : '';
        $appointment_time = !empty($_POST['app_time']) ? $_POST['app_time'] : '';

        // Handle File Uploads Pre-Verification
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $validIdPath = '';
        if (!empty($_FILES['valid_id_file']['name'])) {
            $fileName = time() . '_id_' . basename($_FILES['valid_id_file']['name']);
            $targetFilePath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['valid_id_file']['tmp_name'], $targetFilePath)) {
                $validIdPath = 'uploads/' . $fileName;
            }
        }

        $passportPhotoPath = '';
        if (!empty($_FILES['passport_photo_file']['name'])) {
            $fileName = time() . '_photo_' . basename($_FILES['passport_photo_file']['name']);
            $targetFilePath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['passport_photo_file']['tmp_name'], $targetFilePath)) {
                $passportPhotoPath = 'uploads/' . $fileName;
            }
        }

        $specificDocPath = '';
        if (!empty($_FILES['specific_doc_file']['name'])) {
            $fileName = time() . '_doc_' . basename($_FILES['specific_doc_file']['name']);
            $targetFilePath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['specific_doc_file']['tmp_name'], $targetFilePath)) {
                $specificDocPath = 'uploads/' . $fileName;
            }
        }
       
        $sql = "INSERT INTO applications (application_id, email, application_type, status, appointment_date, appointment_location, appointment_time, created_at)
                VALUES (:app_id, :email, :app_type, :status, :app_date, :app_location, :app_time, CURRENT_TIMESTAMP)";
       
        $stmt = $pdo->prepare($sql);
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