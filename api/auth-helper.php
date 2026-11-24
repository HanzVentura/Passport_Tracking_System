<?php

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function jsonError(string $message, int $status = 403): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function requireLogin(): void
{
    startSecureSession();
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        jsonError('Authentication required.', 401);
    }
}

function requireAdmin(): void
{
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin' || ($_SESSION['is_admin'] ?? false) !== true) {
        jsonError('Administrator access required.', 403);
    }
}

function requireMember(): void
{
    requireLogin();
    if ($_SESSION['user_role'] !== 'member') {
        jsonError('Member access required.', 403);
    }
}

function getSessionEmail(): ?string
{
    startSecureSession();
    $email = $_SESSION['user_email'] ?? $_SESSION['userEmail'] ?? null;
    return is_string($email) && $email !== '' ? strtolower(trim($email)) : null;
}

function getCsrfToken(): string
{
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function requireCsrfToken(): void
{
    startSecureSession();
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return;
    }

    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        jsonError('Invalid or missing CSRF token.', 403);
    }
}
