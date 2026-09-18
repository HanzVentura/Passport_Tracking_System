<?php
// Destroys the user session and redirects to the home page
session_start();
session_unset();
session_destroy();

header('Location: /Passport%20Tracking%20System/public/');
exit;
?>