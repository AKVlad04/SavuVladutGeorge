<?php
require 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'not_logged_in']);
    exit();
}

$userId = (int)$_SESSION['user_id'];

try {
    // Ensure required tables exist
    $tblNfts = $pdo->query("SHOW TABLES LIKE 'nfts'")->rowCount();
    if ($tblNfts === 0) {
        echo json_encode([]);
        exit();
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS wishlist (
        user_id INT NOT NULL,
        nft_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, nft_id)
    )");

    $where = "n.is_deleted = 0";
    $hasApproved = $pdo->query("SHOW COLUMNS FROM nfts LIKE 'is_approved'")->rowCount() > 0;
    if ($hasApproved) {
        $where .= " AND n.is_approved = 1";
    }

    $sql = "SELECT n.id, n.creator_id, n.owner_id, n.name, n.description, n.price, n.category, n.image_url, n.created_at, n.is_featured, ";
    $sql .= "COALESCE(uc.username, n.creator_id) AS creator_name, COALESCE(uo.username, n.owner_id) AS owner_name ";
    $sql .= "FROM wishlist w ";
    $sql .= "JOIN nfts n ON n.id = w.nft_id ";
    $sql .= "LEFT JOIN users uc ON uc.id = n.creator_id ";
    $sql .= "LEFT JOIN users uo ON uo.id = n.owner_id ";
    $sql .= "WHERE w.user_id = :uid AND $where ORDER BY w.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $userId]);
    $rows = $stmt->fetchAll();

    $out = array_map(function($r){
        return [
            'id' => (int)$r['id'],
            'creator_id' => (int)$r['creator_id'],
            'owner_id' => $r['owner_id'] ? (int)$r['owner_id'] : null,
            'name' => $r['name'],
            'description' => $r['description'],
            'price' => (float)$r['price'],
            'category' => $r['category'],
            'image_url' => $r['image_url'] ?: 'img/placeholder.jpg',
            'created_at' => $r['created_at'],
            'is_featured' => isset($r['is_featured']) ? (int)$r['is_featured'] : 0,
            'creator_name' => isset($r['creator_name']) ? $r['creator_name'] : null,
            'owner_name' => isset($r['owner_name']) ? $r['owner_name'] : null
        ];
    }, $rows);

    echo json_encode($out);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
