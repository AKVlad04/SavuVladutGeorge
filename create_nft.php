<?php
require 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

try {
    // Validate fields
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    if (!$title || !$price || !$category || !isset($_FILES['file'])) {
        echo json_encode(['error' => 'Missing required fields']);
        exit();
    }
    // Handle file upload
    $file = $_FILES['file'];
    $allowed = ['image/png','image/jpeg','image/gif','image/webp','video/mp4'];
    if (!in_array($file['type'], $allowed)) {
        echo json_encode(['error' => 'Invalid file type']);
        exit();
    }
    if ($file['size'] > 100*1024*1024) {
        echo json_encode(['error' => 'File too large']);
        exit();
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fname = 'uploads/nft_' . uniqid() . '.' . $ext;
    if (!is_dir('uploads')) mkdir('uploads', 0777, true);
    if (!move_uploaded_file($file['tmp_name'], $fname)) {
        echo json_encode(['error' => 'Upload failed']);
        exit();
    }
    // Insert NFT (pending approval)
    $user_id = $_SESSION['user_id'];
    // Ensure is_approved column exists
    $hasApproved = $pdo->query("SHOW COLUMNS FROM nfts LIKE 'is_approved'")->rowCount() > 0;
    if (!$hasApproved) {
        $pdo->exec("ALTER TABLE nfts ADD COLUMN is_approved TINYINT(1) DEFAULT 0");
    }
    $stmt = $pdo->prepare("INSERT INTO nfts (creator_id, owner_id, name, description, price, category, image_url, created_at, is_deleted, is_featured, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 0, 0, 0)");
    $stmt->execute([$user_id, $user_id, $title, $desc, $price, $category, $fname]);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
