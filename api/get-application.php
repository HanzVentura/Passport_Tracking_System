<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
try {
    $email = $_GET['email'] ?? '';
    $applicationId = $_GET['applicationId'] ?? '';
    $stmt = null;
   
    if ($applicationId) {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE application_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$applicationId]);
    } else if ($email) {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE email = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No application ID or email provided.']);
        exit;
    }
   
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($application) {
        $appId = $application['application_id'] ?: ('PH-' . rand(100000, 999900));
       
        // Dynamic Estimated Completion Date calculation based on Appointment Date and Processing Type[cite: 15]
        $appointmentDate = $application['appointment_date'] ?? date('Y-m-d');
        $processingType = strtolower($application['processing_type'] ?? 'regular');
       
        // Regular processing takes ~12 working days (~16 calendar days); Expedited takes ~7 working days (~10 calendar days)[cite: 15]
        $daysToAdd = ($processingType === 'expedited') ? 10 : 16;
        $estimatedCompletion = date('Y-m-d', strtotime($appointmentDate . " + {$daysToAdd} days"));
        echo json_encode([
            'success' => true,
            'application' => [
                'application_id' => $appId,
                'applicationId' => $appId,
                'application_type' => $application['application_type'] ?? 'New First-Time Application',
                'applicationType' => $application['application_type'] ?? 'New First-Time Application',
                'status' => $application['status'] ?? 'Form Submitted',
                'email' => $application['email'] ?? $email,
                'appointmentDate' => $application['appointment_date'] ?? null,
                'appointment_date' => $application['appointment_date'] ?? null,
                'appointmentLocation' => $application['appointment_location'] ?? null,
                'appointment_location' => $application['appointment_location'] ?? null,
                'appointmentTime' => $application['appointment_time'] ?? '09:00 AM',
                'appointment_time' => $application['appointment_time'] ?? '09:00 AM',
                'processingType' => $processingType,
                'createdAt' => $application['created_at'] ?? date('Y-m-d H:i:s'),
                'estimatedCompletionDate' => $estimatedCompletion,
                'missingDocumentsFlag' => (bool)($application['missing_documents_flag'] ?? false),
                'missingDocumentsReason' => $application['missing_documents_reason'] ?? null
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No active application found.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
?>