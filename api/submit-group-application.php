<?php
session_start();
// Connect to MySQL database via centralized connection
require_once __DIR__ . '/../db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $groupData = [];
        if (isset($_POST['group_data_payload']) && !empty($_POST['group_data_payload'])) {
            $groupData = json_decode($_POST['group_data_payload'], true);
        }

        if (empty($groupData)) {
            die("Error: Group applicant data payload is missing.");
        }

        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $group_reference_id = 'PH-GRP-' . rand(100000, 999999);
        $primaryEmail = '';

        // Handle file uploads if present in the final submit request
        $uploadedValidId = '';
        if (!empty($_FILES['valid_id_file']['name'])) {
            $fileName = time() . '_group_id_' . basename($_FILES['valid_id_file']['name']);
            if (move_uploaded_file($_FILES['valid_id_file']['tmp_name'], $uploadDir . $fileName)) {
                $uploadedValidId = 'uploads/' . $fileName;
            }
        }

        $uploadedPhoto = '';
        if (!empty($_FILES['passport_photo_file']['name'])) {
            $fileName = time() . '_group_photo_' . basename($_FILES['passport_photo_file']['name']);
            if (move_uploaded_file($_FILES['passport_photo_file']['tmp_name'], $uploadDir . $fileName)) {
                $uploadedPhoto = 'uploads/' . $fileName;
            }
        }

        $uploadedDoc = '';
        if (!empty($_FILES['specific_doc_file']['name'])) {
            $fileName = time() . '_group_doc_' . basename($_FILES['specific_doc_file']['name']);
            if (move_uploaded_file($_FILES['specific_doc_file']['tmp_name'], $uploadDir . $fileName)) {
                $uploadedDoc = 'uploads/' . $fileName;
            }
        }

        // Loop through each applicant in the group payload and insert into database
        foreach ($groupData as $key => $applicant) {
            $applicantEmail = !empty($applicant['email']) ? $applicant['email'] : '';
            if (!$applicantEmail && $key === 'applicant_1') {
                $applicantEmail = !empty($_POST['email']) ? $_POST['email'] : '';
            }
            if (!$applicantEmail) continue;
            
            if (!$primaryEmail) {
                $primaryEmail = $applicantEmail;
            }

            $application_id = 'PH-' . rand(100000, 999999);
            $application_type = !empty($applicant['application_type']) ? $applicant['application_type'] : 'Group Appointment';
            $status = 'Form Submitted';
           
            $appointment_location = $applicant['site_selection'] ?? (!empty($_POST['site']) ? $_POST['site'] : '');
            $appointment_date     = $applicant['app_date_selection'] ?? (!empty($_POST['app_date']) ? $_POST['app_date'] : '');
            $appointment_time     = $applicant['app_time_selection'] ?? (!empty($_POST['app_time']) ? $_POST['app_time'] : '');

            // Insert individual member application row linked to the group transaction
            $sql = "INSERT INTO applications (application_id, email, application_type, status, appointment_date, appointment_location, appointment_time, created_at)
                    VALUES (:app_id, :email, :app_type, :status, :app_date, :app_location, :app_time, CURRENT_TIMESTAMP)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':app_id'       => $application_id,
                ':email'        => $applicantEmail,
                ':app_type'     => $application_type,
                ':status'       => $status,
                ':app_date'     => $appointment_date,
                ':app_location' => $appointment_location,
                ':app_time'     => $appointment_time
            ]);
        }

        setcookie('userEmail', $primaryEmail, time() + (86400 * 30), "/");
        setcookie('currentApplicationId', $group_reference_id, time() + (86400 * 30), "/");
       
        header("Location: ../public/payments.html?id=" . $group_reference_id);
        exit();
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>