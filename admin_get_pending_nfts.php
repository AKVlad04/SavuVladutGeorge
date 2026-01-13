<?php
require 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['owner','admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit();
}

try {
    $hasNfts = $pdo->query("SHOW TABLES LIKE 'nfts'")->rowCount() > 0;
    if (!$hasNfts) { echo json_encode([]); exit(); }
    // Only NFTs that are not yet approved (pending)
    $hasApproved = $pdo->query("SHOW COLUMNS FROM nfts LIKE 'is_approved'")->rowCount() > 0;
    if (!$hasApproved) {
        $pdo->exec("ALTER TABLE nfts ADD COLUMN is_approved TINYINT(1) DEFAULT 0");
    }
    $sql = "SELECT n.id, n.name, COALESCE(n.thumbnail, n.image_url, '') AS thumbnail, n.price, n.created_at, COALESCE(u.username, n.creator_id) AS creator FROM nfts n LEFT JOIN users u ON u.id = n.creator_id WHERE n.is_approved = 0 AND n.is_deleted = 0 ORDER BY n.created_at DESC";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();
    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
