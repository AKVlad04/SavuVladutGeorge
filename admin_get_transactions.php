<?php
require 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['owner','admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit();
}

try {
    // Use balance_transactions as the unified ledger for all users
    $hasTx = $pdo->query("SHOW TABLES LIKE 'balance_transactions'")->rowCount() > 0;
    if (!$hasTx) { echo json_encode([]); exit(); }

    $sql = "SELECT bt.created_at AS time,
                   u.username      AS actor,
                   bt.type,
                   bt.nft_id,
                   bt.nft_name,
                   bt.amount
            FROM balance_transactions bt
            JOIN users u ON u.id = bt.user_id
            ORDER BY bt.created_at DESC, bt.id DESC
            LIMIT 200";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Map to the structure expected by dashboard.js renderTransactions
    $out = [];
    foreach ($rows as $r) {
        $type = isset($r['type']) ? strtolower($r['type']) : '';
        switch ($type) {
            case 'deposit':
                $action = 'deposited';
                $target = 'balance';
                break;
            case 'withdraw':
                $action = 'withdrew';
                $target = 'balance';
                break;
            case 'buy':
                $action = 'bought';
                $target = $r['nft_name'] ? ('"' . $r['nft_name'] . '"') : 'an NFT';
                break;
            case 'sell':
                $action = 'sold';
                $target = $r['nft_name'] ? ('"' . $r['nft_name'] . '"') : 'an NFT';
                break;
            default:
                $action = $type ?: 'action';
                $target = $r['nft_name'] ?: 'balance';
        }

        $out[] = [
            'time'   => $r['time'],
            'actor'  => $r['actor'],
            'type'   => $type,
            'nft_id' => $r['nft_id'],
            'nft_name' => $r['nft_name'],
            'action' => $action,
            'target' => $target,
            'amount' => $r['amount'],
        ];
    }

    echo json_encode($out);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
