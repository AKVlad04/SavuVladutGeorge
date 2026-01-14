<?php
require 'db.php';
header('Content-Type: application/json');

try {
    $tbl = $pdo->query("SHOW TABLES LIKE 'trades'")->rowCount();
    if ($tbl === 0) {
        echo json_encode([]);
        exit();
    }

    $sql = "SELECT t.id AS trade_id, t.nft_id, t.seller_id, t.price, t.created_at,
                   n.name, n.image_url, n.category, n.price AS base_price,
                   uc.username AS creator_name
            FROM trades t
            JOIN nfts n ON n.id = t.nft_id
            LEFT JOIN users uc ON uc.id = n.creator_id
            WHERE t.status = 'open' AND n.is_deleted = 0
            ORDER BY t.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $rate = 1900; // approx USD per ETH

    $out = array_map(function($r) use ($rate) {
        $priceEth = (float)$r['price'];
        return [
            'trade_id' => (int)$r['trade_id'],
            'nft_id' => (int)$r['nft_id'],
            'seller_id' => (int)$r['seller_id'],
            'name' => $r['name'],
            'creator_name' => $r['creator_name'],
            'image_url' => $r['image_url'] ?: 'img/placeholder.jpg',
            'price' => $priceEth,
            'value_usd' => round($priceEth * $rate, 2),
            'category' => $r['category'],
            'created_at' => $r['created_at']
        ];
    }, $rows);

    echo json_encode($out);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'server_error', 'message' => $e->getMessage()]);
}
