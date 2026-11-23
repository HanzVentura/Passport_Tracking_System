<?php
// Destroys the user session and redirects to the home page
require_once __DIR__ . '/api/auth-helper.php';
startSecureSession();
session_unset();
session_destroy();

header('Cache-Control: no-store');
?>
<!doctype html>
<html>
<body>
<script>
[
    'userEmail',
    'userRole',
    'activeAppId',
    'currentApplicationId',
    'paymentAmount',
    'paymentProcessingType',
    'applicationType',
    'selectedAppointmentDate',
    'selectedAppointmentTime',
    'selectedSite',
    'groupCount',
    'groupApplicantsData'
].forEach((key) => localStorage.removeItem(key));
window.location.replace('public/index.html');
</script>
</body>
</html>
?>