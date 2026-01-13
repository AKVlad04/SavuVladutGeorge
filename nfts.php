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

    // Only show approved NFTs
    $hasApproved = $pdo->query("SHOW COLUMNS FROM nfts LIKE 'is_approved'")->rowCount() > 0;
    $where = "is_deleted = 0";
    if ($hasApproved) {
        // Show all NFTs if is_approved is NULL (old rows), or is_approved=1 (approved)
        $where .= " AND (is_approved IS NULL OR is_approved = 1)";
    }
    $stmt = $pdo->prepare("SELECT id, creator_id, owner_id, name, description, price, category, image_url, created_at, is_featured FROM nfts WHERE $where ORDER BY created_at DESC LIMIT 100");
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
            'is_featured' => (int)$r['is_featured']
        ];
    }, $rows);

    echo json_encode($out);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
