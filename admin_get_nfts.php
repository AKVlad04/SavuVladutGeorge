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

    // build a resilient SELECT — detect optional columns (thumbnail/image_url/created_at)
    $hasThumbnail = $pdo->query("SHOW COLUMNS FROM nfts LIKE 'thumbnail'")->rowCount() > 0;
    $hasImageUrl = $pdo->query("SHOW COLUMNS FROM nfts LIKE 'image_url'")->rowCount() > 0;
    $hasCreatedAt = $pdo->query("SHOW COLUMNS FROM nfts LIKE 'created_at'")->rowCount() > 0;

    if ($hasThumbnail && $hasImageUrl) {
        $thumbExpr = "COALESCE(n.thumbnail, n.image_url, '') AS thumbnail";
    } elseif ($hasThumbnail) {
        $thumbExpr = "COALESCE(n.thumbnail, '') AS thumbnail";
    } elseif ($hasImageUrl) {
        $thumbExpr = "COALESCE(n.image_url, '') AS thumbnail";
    } else {
        $thumbExpr = "'' AS thumbnail";
    }

    if ($hasCreatedAt) {
        $createdExpr = "n.created_at";
        $orderBy = "ORDER BY n.created_at DESC";
    } else {
        $createdExpr = "'' AS created_at";
        $orderBy = "ORDER BY n.id DESC";
    }

    $hasIsDeleted = $pdo->query("SHOW COLUMNS FROM nfts LIKE 'is_deleted'")->rowCount() > 0;
    $hasIsFeatured = $pdo->query("SHOW COLUMNS FROM nfts LIKE 'is_featured'")->rowCount() > 0;
    $extraCols = '';
    if ($hasIsDeleted) $extraCols .= ', n.is_deleted';
    if ($hasIsFeatured) $extraCols .= ', n.is_featured';
    $sql = "SELECT n.id, n.name, $thumbExpr, n.price, $createdExpr, ";
    $sql .= "COALESCE(u.username, n.creator_id) AS creator, COALESCE(o.username, n.owner_id) AS owner" . $extraCols . " ";
    $sql .= "FROM nfts n LEFT JOIN users u ON u.id = n.creator_id LEFT JOIN users o ON o.id = n.owner_id $orderBy";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();
    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
