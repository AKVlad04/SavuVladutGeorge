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

        // We return offers where the current user is the next actor:
        // - Buyer-initiated offers (funds_reserved = 1, parent_offer_id IS NULL): seller must respond.
        // - Seller counter-offers (funds_reserved = 0, parent_offer_id IS NOT NULL): buyer must respond.
        $sql = "SELECT o.id, o.nft_id, o.price, o.status, o.created_at,
                                     n.name AS nft_name, n.image_url,
                                     ub.username AS buyer_name,
                                     us.username AS seller_name,
                                     o.seller_id, o.buyer_id, o.funds_reserved, o.parent_offer_id
                        FROM offers o
                        JOIN nfts n ON n.id = o.nft_id
                        JOIN users ub ON ub.id = o.buyer_id
                        JOIN users us ON us.id = o.seller_id
                        WHERE o.status = 'open'
                            AND (
                                        (o.funds_reserved = 1 AND o.parent_offer_id IS NULL AND o.seller_id = ?) -- buyer initiated, seller responds
                                 OR (o.funds_reserved = 0 AND o.parent_offer_id IS NOT NULL AND o.buyer_id = ?) -- seller countered, buyer responds
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
