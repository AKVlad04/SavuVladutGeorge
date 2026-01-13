<?php
require 'db.php';
header('Content-Type: application/json');

// Usage: profile_public.php?user=username OR ?id=123
$user = isset($_GET['user']) ? trim($_GET['user']) : null;
$id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$user && !$id) {
    echo json_encode(['error' => 'Missing user parameter']);
    exit();
}

try {
    // Find user by username or id
    if ($user) {
        $stmt = $pdo->prepare("SELECT id, username, email, status, role, is_verified, wallet FROM users WHERE username = ?");
        $stmt->execute([$user]);
    } else {
        $stmt = $pdo->prepare("SELECT id, username, email, status, role, is_verified, wallet FROM users WHERE id = ?");
        $stmt->execute([$id]);
    }
    $u = $stmt->fetch();
    if (!$u) {
        echo json_encode(['error' => 'User not found']);
        exit();
    }
    // Get user's NFTs
    $nfts = [];
    $tbl = $pdo->query("SHOW TABLES LIKE 'nfts'")->rowCount();
    if ($tbl > 0) {
        $nftStmt = $pdo->prepare("SELECT id, name, price, image_url, created_at FROM nfts WHERE owner_id = ? AND is_deleted = 0 ORDER BY created_at DESC LIMIT 100");
        $nftStmt->execute([$u['id']]);
        $nfts = $nftStmt->fetchAll();
    }
    echo json_encode([
        'id' => (int)$u['id'],
        'username' => $u['username'],
        'email' => $u['email'],
        'status' => $u['status'],
        'role' => $u['role'],
        'is_verified' => isset($u['is_verified']) ? (int)$u['is_verified'] : 0,
        'wallet' => $u['wallet'],
        'nfts' => $nfts
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
