<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'not_logged_in']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_payload']);
    exit();
}

$nftId = isset($input['nft_id']) ? (int)$input['nft_id'] : 0;
$price = isset($input['price']) ? (float)$input['price'] : 0;

if ($nftId <= 0 || $price <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_data']);
    exit();
}

$userId = (int)$_SESSION['user_id'];

try {
    // Ensure trades table exists
    $tbl = $pdo->query("SHOW TABLES LIKE 'trades'")->rowCount();
    if ($tbl === 0) {
        http_response_code(500);
        echo json_encode(['error' => 'trades_table_missing']);
        exit();
    }

    // Check NFT ownership and that it is not deleted
    $sql = "SELECT id, owner_id, is_deleted FROM nfts WHERE id = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nftId]);
    $nft = $stmt->fetch();

    if (!$nft) {
        http_response_code(404);
        echo json_encode(['error' => 'nft_not_found']);
        exit();
    }

    if (!empty($nft['is_deleted'])) {
        http_response_code(400);
        echo json_encode(['error' => 'nft_deleted']);
        exit();
    }

    if ((int)$nft['owner_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['error' => 'not_owner']);
        exit();
    }

    // One open trade per NFT+seller. Update if exists, else insert.
    $stmt = $pdo->prepare("SELECT id FROM trades WHERE nft_id = ? AND seller_id = ? AND status = 'open' LIMIT 1");
    $stmt->execute([$nftId, $userId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $update = $pdo->prepare("UPDATE trades SET price = ?, created_at = NOW(), status = 'open', buyer_id = NULL, accepted_at = NULL WHERE id = ?");
        $update->execute([$price, $existing['id']]);
        echo json_encode(['ok' => true, 'trade_id' => (int)$existing['id'], 'updated' => true]);
    } else {
        $insert = $pdo->prepare("INSERT INTO trades (nft_id, seller_id, price, status) VALUES (?, ?, ?, 'open')");
        $insert->execute([$nftId, $userId, $price]);
        $tradeId = (int)$pdo->lastInsertId();
        echo json_encode(['ok' => true, 'trade_id' => $tradeId, 'updated' => false]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'server_error', 'message' => $e->getMessage()]);
}
