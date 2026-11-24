<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth-helper.php';
startSecureSession();
requireMember();
requireCsrfToken();

if (!isset($_SESSION['user_email']) || trim($_SESSION['user_email']) === '') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to update your profile.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

$sessionEmail = trim($_SESSION['user_email']);

try {
    $columnsStmt = $pdo->query("SHOW COLUMNS FROM users");
    $columns = $columnsStmt ? $columnsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    if (!in_array('nickname', $columns, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN nickname VARCHAR(60) NULL");
    }
    if (!in_array('profile_photo', $columns, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL");
    }

    $stmt = $pdo->prepare("SELECT id, fullname, email, nickname, profile_photo FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$sessionEmail]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'User account not found.'
        ]);
        exit;
    }

    $isMultipart = !empty($_FILES) || isset($_POST['nickname']) || isset($_POST['remove_photo']);
    $input = $isMultipart ? $_POST : (json_decode(file_get_contents('php://input'), true) ?? []);

    $removePhoto = filter_var($input['remove_photo'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $currentPhoto = !empty($user['profile_photo']) ? (string)$user['profile_photo'] : null;
    $newPhotoPath = $currentPhoto;

    $rawNickname = array_key_exists('nickname', $input) ? trim((string)$input['nickname']) : null;
    if ($rawNickname !== null) {
        if (mb_strlen($rawNickname) > 60) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Nickname must be 60 characters or fewer.'
            ]);
            exit;
        }
        $newNickname = ($rawNickname === '') ? null : $rawNickname;
    } else {
        $newNickname = isset($user['nickname']) && trim((string)$user['nickname']) !== ''
            ? trim((string)$user['nickname'])
            : null;
    }

    $uploadDir = __DIR__ . '/../public/uploads/avatars';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new Exception('Unable to create avatar upload directory.');
        }
    }

    $deletePhotoFile = function (?string $relPath) {
        if (!$relPath) return;
        $cleanRel = ltrim(str_replace('\\', '/', $relPath), '/');
        if (strpos($cleanRel, 'uploads/avatars/') === 0) {
            $fullPath = __DIR__ . '/../public/' . $cleanRel;
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
    };

    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['avatar'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Image upload failed (error code ' . (int)$file['error'] . ').'
            ]);
            exit;
        }

        $maxBytes = 2 * 1024 * 1024;
        if ((int)$file['size'] > $maxBytes) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Profile picture must be 2 MB or smaller.'
            ]);
            exit;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($allowedMimes[$mimeType]) || @getimagesize($file['tmp_name']) === false) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Only JPG, PNG, and WEBP image files are allowed.'
            ]);
            exit;
        }

        $ext = $allowedMimes[$mimeType];
        $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo((string)$user['email'], PATHINFO_FILENAME));
        $fileName = 'avatar_' . $safeBase . '_' . (int)$user['id'] . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $targetFullPath = $uploadDir . '/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $targetFullPath)) {
            throw new Exception('Failed to move uploaded profile picture.');
        }

        if ($currentPhoto && $currentPhoto !== ('uploads/avatars/' . $fileName)) {
            $deletePhotoFile($currentPhoto);
        }
        $newPhotoPath = 'uploads/avatars/' . $fileName;
    } elseif ($removePhoto) {
        $deletePhotoFile($currentPhoto);
        $newPhotoPath = null;
    }

    $updateStmt = $pdo->prepare("UPDATE users SET nickname = ?, profile_photo = ? WHERE email = ?");
    $updateStmt->execute([$newNickname, $newPhotoPath, $sessionEmail]);

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully.',
        'user' => [
            'id' => $user['id'],
            'fullname' => $user['fullname'],
            'email' => $user['email'],
            'nickname' => $newNickname,
            'profile_photo' => $newPhotoPath
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Profile update failed: ' . $e->getMessage()
    ]);
}