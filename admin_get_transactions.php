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
    $hasTx = $pdo->query("SHOW TABLES LIKE 'transactions'")->rowCount() > 0;
    if (!$hasTx) { echo json_encode([]); exit(); }

    $sql = "SELECT created_at AS time, actor, action, target, amount FROM transactions ORDER BY created_at DESC LIMIT 200";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();
    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
