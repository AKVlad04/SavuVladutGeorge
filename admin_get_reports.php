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

    $sql = "SELECT r.id, r.item_id, r.reason, r.reported_by, COALESCE(n.name,'') AS item_name FROM reports r LEFT JOIN nfts n ON n.id = r.item_id ORDER BY r.id DESC";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();
    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
