<?php
require 'db.php';
header('Content-Type: application/json');

try {
    // Check table exists
    $tbl = $pdo->query("SHOW TABLES LIKE 'nfts'")->rowCount();
    if ($tbl === 0) {
        // no table -> return empty array to allow frontend fallback
        echo json_encode([]);
        exit();
    }

    // Only show non-deleted NFTs, join users to get creator/owner usernames
    $where = "n.is_deleted = 0";
    $sql = "SELECT n.id, n.creator_id, n.owner_id, n.name, n.description, n.price, n.category, n.image_url, n.created_at, n.is_featured, ";
    $sql .= "COALESCE(uc.username, n.creator_id) AS creator_name, COALESCE(uo.username, n.owner_id) AS owner_name ";
    $sql .= "FROM nfts n LEFT JOIN users uc ON uc.id = n.creator_id LEFT JOIN users uo ON uo.id = n.owner_id WHERE $where ORDER BY n.created_at DESC LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    // Normalize keys for client
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
            'is_featured' => (int)$r['is_featured'],
            'creator_name' => isset($r['creator_name']) ? $r['creator_name'] : null,
            'owner_name' => isset($r['owner_name']) ? $r['owner_name'] : null
        ];
    }, $rows);

    echo json_encode($out);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
