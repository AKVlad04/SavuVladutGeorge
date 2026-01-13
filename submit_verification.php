<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not logged in']);
    exit();
}
$user_id = $_SESSION['user_id'];

// Check if already verified
$col = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_verified'")->rowCount();
if ($col) {
    $stmt = $pdo->prepare("SELECT is_verified FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    if ($row && $row['is_verified']) {
        echo json_encode(['error' => 'Already verified']);
        exit();
    }
}

// Check for existing pending request
$tbl = $pdo->query("SHOW TABLES LIKE 'verification_requests'")->rowCount();
if ($tbl) {
    $pending = $pdo->prepare("SELECT id FROM verification_requests WHERE user_id = ? AND status = 'pending'");
    $pending->execute([$user_id]);
    if ($pending->fetch()) {
        echo json_encode(['error' => 'Request already pending']);
        exit();
    }
}

if (!isset($_POST['description']) || !isset($_FILES['verify_image'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing fields']);
    exit();
}
$desc = trim($_POST['description']);
$file = $_FILES['verify_image'];

// Validate file
$allowed = ['image/jpeg','image/png','image/jpg'];
if ($file['error'] !== 0 || !in_array($file['type'], $allowed) || $file['size'] > 5*1024*1024) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file']);
    exit();
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$targetDir = __DIR__ . '/uploads/verifications/';
if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
$fname = 'verify_' . $user_id . '_' . time() . '.' . $ext;
$targetFile = $targetDir . $fname;
$publicPath = 'uploads/verifications/' . $fname;
if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file']);
    exit();
}

// Insert request
$stmt = $pdo->prepare("INSERT INTO verification_requests (user_id, description, image_url) VALUES (?, ?, ?)");
$stmt->execute([$user_id, $desc, $publicPath]);
echo json_encode(['ok' => true]);
