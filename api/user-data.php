<?php
// Checks the session to see if a user is logged in and returns their info
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['user'])) {
    echo json_encode([
        'loggedIn' => true,
        'fullName' => $_SESSION['user']['fullName']
    ]);
} else {
    echo json_encode([
        'loggedIn' => false
    ]);
}
?>