<?php
require 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['logged_in' => false, 'in_wishlist' => false]);
    exit();
}

if (!isset($_GET['nft_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_nft_id']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$nftId = (int)$_GET['nft_id'];

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS wishlist (
        user_id INT NOT NULL,
        nft_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, nft_id)
    )");

    $stmt = $pdo->prepare('SELECT 1 FROM wishlist WHERE user_id = ? AND nft_id = ?');
    $stmt->execute([$userId, $nftId]);
    $exists = (bool)$stmt->fetchColumn();
    echo json_encode(['logged_in' => true, 'in_wishlist' => $exists]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
