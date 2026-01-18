<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'not_authenticated']);
    exit();
}

$userId = (int)$_SESSION['user_id'];

try {
    $tblOffers = $pdo->query("SHOW TABLES LIKE 'offers'")->rowCount();
    if ($tblOffers === 0) {
        echo json_encode(['ok' => true, 'items' => []]);
        exit();
    }

        // "Sent" offers are those initiated by the current user:
        // - Buyer-initiated offers (funds_reserved = 1, parent_offer_id IS NULL) where user is buyer.
        // - Seller counter-offers (funds_reserved = 0, parent_offer_id IS NOT NULL) where user is seller.
          $sql = "SELECT o.id, o.nft_id, o.price, o.status, o.created_at,
               n.name AS nft_name, n.image_url,
               us.username AS seller_name,
               ub.username AS buyer_name,
               o.seller_id, o.buyer_id, o.funds_reserved, o.parent_offer_id
            FROM offers o
            JOIN nfts n ON n.id = o.nft_id
            JOIN users us ON us.id = o.seller_id
            JOIN users ub ON ub.id = o.buyer_id
                WHERE o.status = 'open'
                  AND (
                 (o.funds_reserved = 1 AND o.parent_offer_id IS NULL AND o.buyer_id = ?)
              OR (o.funds_reserved = 0 AND o.parent_offer_id IS NOT NULL AND o.seller_id = ?)
              )
            ORDER BY o.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'items' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error', 'details' => $e->getMessage()]);
}
