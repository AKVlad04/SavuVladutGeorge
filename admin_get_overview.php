<?php
require 'db.php';
session_start();
header('Content-Type: application/json');

// allow owner and admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['owner','admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit();
}

try {
    // total users
    $totalUsers = 0;
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $totalUsers = (int)$stmt->fetchColumn();

    // total nfts
    $totalNfts = 0;
    $hasNfts = $pdo->query("SHOW TABLES LIKE 'nfts'")->rowCount() > 0;
    if ($hasNfts) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM nfts");
        $totalNfts = (int)$stmt->fetchColumn();
    }

    // helper flags for legacy transactions vs new balance_transactions
    $hasTx = $pdo->query("SHOW TABLES LIKE 'transactions'")->rowCount() > 0;
    $hasBalanceTx = $pdo->query("SHOW TABLES LIKE 'balance_transactions'")->rowCount() > 0;

    // total volume (prefer legacy transactions.amount, fallback to balance_transactions SELL events)
    $totalVolume = 0;
    if ($hasTx) {
        $stmt = $pdo->query("SELECT IFNULL(SUM(amount),0) FROM transactions");
        $totalVolume = $stmt->fetchColumn();
    } elseif ($hasBalanceTx) {
        // each trade is represented twice (buy & sell); sum only sells to avoid double counting
        $stmt = $pdo->query("SELECT IFNULL(SUM(amount),0) FROM balance_transactions WHERE type = 'sell'");
        $totalVolume = $stmt->fetchColumn();
    }

    // Sales volume last 30 days (prefer transactions, fallback to balance_transactions SELL events)
    $sales_last_30 = [];
    if ($hasTx) {
        $stmt = $pdo->query("SELECT DATE(created_at) as date, SUM(amount) as volume FROM transactions WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY DATE(created_at)");
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) $sales_last_30[] = ['date' => $r['date'], 'volume' => (float)$r['volume']];
    } elseif ($hasBalanceTx) {
        $stmt = $pdo->query("SELECT DATE(created_at) as date, SUM(amount) as volume FROM balance_transactions WHERE type = 'sell' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY DATE(created_at)");
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) $sales_last_30[] = ['date' => $r['date'], 'volume' => (float)$r['volume']];
    }

    // Categories distribution (nfts)
    $categories_distribution = [];
    if ($hasNfts) {
        $stmt = $pdo->query("SELECT category, COUNT(*) as count FROM nfts WHERE category IS NOT NULL AND category != '' GROUP BY category");
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) $categories_distribution[] = ['category' => $r['category'], 'count' => (int)$r['count']];
    }

    // Minting activity (NFTs created per day, last 30 days)
    $mint_activity = [];
    if ($hasNfts && $pdo->query("SHOW COLUMNS FROM nfts LIKE 'created_at'")->rowCount() > 0) {
        $stmt = $pdo->query("SELECT DATE(created_at) as date, COUNT(*) as count FROM nfts WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY DATE(created_at)");
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) $mint_activity[] = ['date' => $r['date'], 'count' => (int)$r['count']];
    }

    // Monthly traded volume (ETH) for last 12 months
    $monthly_volume = [];
    if ($hasTx) {
        $stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(amount) as volume FROM transactions WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY ym ORDER BY ym");
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) $monthly_volume[] = ['month' => $r['ym'], 'volume' => (float)$r['volume']];
    } elseif ($hasBalanceTx) {
        $stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(amount) as volume FROM balance_transactions WHERE type = 'sell' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY ym ORDER BY ym");
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) $monthly_volume[] = ['month' => $r['ym'], 'volume' => (float)$r['volume']];
    }

    // Top creators by trade volume (last 30 days, based on SELL transactions)
    $top_creators = [];
    if ($hasNfts && $hasBalanceTx) {
        $stmt = $pdo->query(
            "SELECT u.id AS creator_id, u.username, IFNULL(SUM(bt.amount),0) AS volume
             FROM balance_transactions bt
             JOIN nfts n ON bt.nft_id = n.id
             JOIN users u ON n.creator_id = u.id
             WHERE bt.type = 'sell' AND bt.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY u.id, u.username
             ORDER BY volume DESC
             LIMIT 5"
        );
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) {
            $top_creators[] = [
                'creator_id' => (int)$r['creator_id'],
                'username'   => $r['username'],
                'volume'     => (float)$r['volume']
            ];
        }
    }

    echo json_encode([
        'total_users' => $totalUsers,
        'total_nfts' => $totalNfts,
        'total_volume' => $totalVolume,
        'sales_last_30' => $sales_last_30,
        'categories_distribution' => $categories_distribution,
        'mint_activity' => $mint_activity,
        'top_creators' => $top_creators,
        'monthly_volume' => $monthly_volume
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
