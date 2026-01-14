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
    // If table doesn't exist yet, just return empty activity
    $tblTx = $pdo->query("SHOW TABLES LIKE 'balance_transactions'")->rowCount();
    if ($tblTx === 0) {
        echo json_encode(['ok' => true, 'items' => []]);
        exit();
    }

    $stmt = $pdo->prepare("SELECT type, amount, balance_after, created_at, nft_id, nft_name FROM balance_transactions WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 50");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'items' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error', 'details' => $e->getMessage()]);
}
