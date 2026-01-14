<?php
require 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'not_authenticated']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$data = $_POST;

$wallet        = isset($data['wallet']) ? trim($data['wallet']) : null;
$newUsername   = isset($data['new_username']) ? trim($data['new_username']) : null;
$currentPass   = isset($data['current_password']) ? $data['current_password'] : null;
$newPass       = isset($data['new_password']) ? $data['new_password'] : null;
$confirmPass   = isset($data['confirm_password']) ? $data['confirm_password'] : null;
$hasAvatarFile = isset($_FILES['avatar']) && is_array($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE;

if ($wallet === null && $newUsername === null && !$hasAvatarFile && ($currentPass === null && $newPass === null && $confirmPass === null)) {
    http_response_code(400);
    echo json_encode(['error' => 'no_fields']);
    exit();
}

$result = ['ok' => true];

try {
    // Ensure wallet column exists if we want to update wallet
    if ($wallet !== null) {
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'wallet'")->rowCount();
        if ($col === 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN wallet VARCHAR(255) DEFAULT NULL");
        }
        $stmt = $pdo->prepare("UPDATE users SET wallet = ? WHERE id = ?");
        $stmt->execute([$wallet, $userId]);
        $_SESSION['wallet'] = $wallet;
        $result['wallet'] = $wallet;
    }

    // Username change
    if ($newUsername !== null && $newUsername !== '') {
        // Basic validation
        if (strlen($newUsername) < 3 || strlen($newUsername) > 50) {
            throw new Exception('Username must be between 3 and 50 characters.');
        }
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
        $check->execute([$newUsername, $userId]);
        if ($check->fetch()) {
            throw new Exception('Username is already taken.');
        }
        $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
        $stmt->execute([$newUsername, $userId]);
        $_SESSION['username'] = $newUsername;
        $result['username'] = $newUsername;
    }

    // Password change
    if ($currentPass !== null || $newPass !== null || $confirmPass !== null) {
        if ($currentPass === null || $newPass === null || $confirmPass === null) {
            throw new Exception('All password fields are required.');
        }
        if ($newPass !== $confirmPass) {
            throw new Exception('New password and confirmation do not match.');
        }
        if (strlen($newPass) < 6) {
            throw new Exception('New password must be at least 6 characters.');
        }
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($currentPass, $row['password'])) {
            throw new Exception('Current password is incorrect.');
        }
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->execute([$newHash, $userId]);
        $result['password_changed'] = true;
    }

    // Avatar upload
    if ($hasAvatarFile) {
        // Ensure avatar_url column exists
        $colAvatar = $pdo->query("SHOW COLUMNS FROM users LIKE 'avatar_url'")->rowCount();
        if ($colAvatar === 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) DEFAULT NULL");
        }

        $file = $_FILES['avatar'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error uploading avatar.');
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('Avatar must be less than 5MB.');
        }
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        $mime = mime_content_type($file['tmp_name']);
        if (!isset($allowed[$mime])) {
            throw new Exception('Invalid avatar type. Allowed: JPG, PNG, GIF, WEBP.');
        }
        $ext = $allowed[$mime];
        $dir = __DIR__ . '/uploads/avatars';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
        $targetPath = $dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Failed to save avatar file.');
        }
        $relativePath = 'uploads/avatars/' . $filename;
        $stmt = $pdo->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
        $stmt->execute([$relativePath, $userId]);
        $result['avatar_url'] = $relativePath;
    }

    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
