<?php
require 'db.php';
header('Content-Type: application/json');

try {
    $tbl = $pdo->query("SHOW TABLES LIKE 'nfts'")->rowCount();
    if ($tbl === 0) {
        echo json_encode([]);
        exit();
    }
    $stmt = $pdo->query("SELECT DISTINCT category FROM nfts WHERE category IS NOT NULL AND category != ''");
    $rows = $stmt->fetchAll();
    $out = array_map(function($r){ return $r['category']; }, $rows);
    echo json_encode($out);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
