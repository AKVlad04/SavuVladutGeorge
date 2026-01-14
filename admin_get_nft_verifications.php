<?php
require 'db.php';
session_start();
header('Content-Type: application/json');

// Only admin/owner can access
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['owner','admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit();
}

try {
    $hasNfts = $pdo->query("SHOW TABLES LIKE 'nfts'")->rowCount() > 0;
    if (!$hasNfts) { echo json_encode([]); exit(); }

    // Ensure is_approved column exists
    $col = $pdo->query("SHOW COLUMNS FROM nfts LIKE 'is_approved'")->rowCount();
    if ($col === 0) {
        $pdo->exec("ALTER TABLE nfts ADD COLUMN is_approved TINYINT(1) DEFAULT 0 AFTER is_deleted");
    }

    $hasThumbnail = $pdo->query("SHOW COLUMNS FROM nfts LIKE 'thumbnail'")->rowCount() > 0;
    $hasImageUrl = $pdo->query("SHOW COLUMNS FROM nfts LIKE 'image_url'")->rowCount() > 0;
    if ($hasThumbnail && $hasImageUrl) {
        $thumbExpr = "COALESCE(n.thumbnail, n.image_url, '') AS thumbnail";
    } elseif ($hasThumbnail) {
        $thumbExpr = "COALESCE(n.thumbnail, '') AS thumbnail";
    } elseif ($hasImageUrl) {
        $thumbExpr = "COALESCE(n.image_url, '') AS thumbnail";
    } else {
        $thumbExpr = "'' AS thumbnail";
    }

    $hasCreatedAt = $pdo->query("SHOW COLUMNS FROM nfts LIKE 'created_at'")->rowCount() > 0;
    if ($hasCreatedAt) {
        $createdExpr = "n.created_at";
        $orderBy = "ORDER BY n.created_at DESC";
    } else {
        $createdExpr = "'' AS created_at";
        $orderBy = "ORDER BY n.id DESC";
    }

    $sql = "SELECT n.id, n.name, $thumbExpr, n.price, $createdExpr, COALESCE(u.username, n.creator_id) AS creator " .
           "FROM nfts n LEFT JOIN users u ON u.id = n.creator_id " .
           "WHERE (n.is_deleted IS NULL OR n.is_deleted = 0) AND n.is_approved = 0 $orderBy";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();
    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
