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
    $hasReports = $pdo->query("SHOW TABLES LIKE 'reports'")->rowCount() > 0;
    if (!$hasReports) { echo json_encode([]); exit(); }

    // Ensure newer columns exist so we can distinguish between NFT and user reports
    $cols = $pdo->query("SHOW COLUMNS FROM reports")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('type', $cols, true)) {
        $pdo->exec("ALTER TABLE reports ADD COLUMN type ENUM('nft','user') NOT NULL DEFAULT 'nft' AFTER id");
    }
    if (!in_array('details', $cols, true)) {
        $pdo->exec("ALTER TABLE reports ADD COLUMN details TEXT NULL AFTER reason");
    }
    if (!in_array('status', $cols, true)) {
        $pdo->exec("ALTER TABLE reports ADD COLUMN status ENUM('open','closed') NOT NULL DEFAULT 'open' AFTER details");
    }

    $status = isset($_GET['status']) ? strtolower($_GET['status']) : 'open';
    $where = '';
    if ($status === 'open') {
        $where = "WHERE r.status = 'open'";
    } elseif ($status === 'closed' || $status === 'history') {
        $where = "WHERE r.status = 'closed'";
    }

    $sql = "SELECT r.id,
                   r.item_id,
                   r.reason,
                   r.reported_by,
                   r.type,
                   r.details,
                   r.status,
                   r.created_at,
                   COALESCE(n.name,'')      AS nft_name,
                   COALESCE(u.username,'')  AS user_name,
                   COALESCE(u_owner.username,'') AS nft_owner_name
            FROM reports r
            LEFT JOIN nfts n       ON (r.type = 'nft'  AND n.id = r.item_id)
            LEFT JOIN users u      ON (r.type = 'user' AND u.id = r.item_id)
            LEFT JOIN users u_owner ON (r.type = 'nft' AND n.owner_id = u_owner.id)
            " . $where . "
            ORDER BY r.created_at DESC, r.id DESC";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
