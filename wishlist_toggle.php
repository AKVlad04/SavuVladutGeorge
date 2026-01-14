<?php
require 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'not_logged_in']);
    exit();
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || !isset($body['nft_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_payload']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$nftId = (int)$body['nft_id'];

try {
    // Ensure wishlist table exists (for safety outside migrations)
    $pdo->exec("CREATE TABLE IF NOT EXISTS wishlist (
        user_id INT NOT NULL,
        nft_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, nft_id)
    )");

    $stmt = $pdo->prepare('SELECT 1 FROM wishlist WHERE user_id = ? AND nft_id = ?');
    $stmt->execute([$userId, $nftId]);
    $exists = $stmt->fetchColumn();

    if ($exists) {
        $del = $pdo->prepare('DELETE FROM wishlist WHERE user_id = ? AND nft_id = ?');
        $del->execute([$userId, $nftId]);
        echo json_encode(['ok' => true, 'in_wishlist' => false]);
    } else {
        $ins = $pdo->prepare('INSERT INTO wishlist (user_id, nft_id) VALUES (?, ?)');
        $ins->execute([$userId, $nftId]);
        echo json_encode(['ok' => true, 'in_wishlist' => true]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
