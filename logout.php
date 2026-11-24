<?php
// Destroys the user session and redirects to the home page
require_once __DIR__ . '/api/auth-helper.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api/audit-helper.php';
startSecureSession();

$actorEmail = $_SESSION['user_email'] ?? $_SESSION['userEmail'] ?? null;
$actorRole = $_SESSION['user_role'] ?? null;
if (is_string($actorEmail) && $actorEmail !== '') {
    logAudit(
        $pdo,
        'LOGOUT',
        'session',
        null,
        null,
        null,
        'User logged out.',
        $actorEmail,
        $actorRole ?: 'member'
    );
}
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