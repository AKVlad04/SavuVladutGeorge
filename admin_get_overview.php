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

    // total volume (from transactions.amount)
    $totalVolume = 0;
    $hasTx = $pdo->query("SHOW TABLES LIKE 'transactions'")->rowCount() > 0;
    if ($hasTx) {
        $stmt = $pdo->query("SELECT IFNULL(SUM(amount),0) FROM transactions");
        $totalVolume = $stmt->fetchColumn();
    }

    // Sales volume last 30 days (transactions)
    $sales_last_30 = [];
    if ($hasTx) {
        $stmt = $pdo->query("SELECT DATE(created_at) as date, SUM(amount) as volume FROM transactions WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY DATE(created_at)");
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

    echo json_encode([
        'total_users' => $totalUsers,
        'total_nfts' => $totalNfts,
        'total_volume' => $totalVolume,
        'sales_last_30' => $sales_last_30,
        'categories_distribution' => $categories_distribution,
        'mint_activity' => $mint_activity
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
