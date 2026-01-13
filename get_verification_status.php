<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'none']);
    exit();
}
$user_id = $_SESSION['user_id'];
$tbl = $pdo->query("SHOW TABLES LIKE 'verification_requests'")->rowCount();
if (!$tbl) {
    echo json_encode(['status' => 'none']);
    exit();
}
$stmt = $pdo->prepare("SELECT status FROM verification_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$user_id]);
$row = $stmt->fetch();
if ($row && $row['status']) {
    echo json_encode(['status' => $row['status']]);
} else {
    echo json_encode(['status' => 'none']);
}
