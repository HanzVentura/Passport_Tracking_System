<?php
header('Content-Type: application/json');
require_once __DIR__ . '/auth-helper.php';

echo json_encode([
    'success' => true,
    'csrfToken' => getCsrfToken(),
]);
