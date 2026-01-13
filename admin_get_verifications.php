<?php
require 'db.php';
header('Content-Type: application/json');

// Only admin/owner can access
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not logged in']);
    exit();
}
$user_id = $_SESSION['user_id'];
$role = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$role->execute([$user_id]);
$role = strtolower($role->fetchColumn() ?: '');
if ($role !== 'admin' && $role !== 'owner') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

// Table: verification_requests (id, user_id, description, image_url, status, created_at)
$tbl = $pdo->query("SHOW TABLES LIKE 'verification_requests'")->rowCount();
if ($tbl === 0) {
    echo json_encode([]);
    exit();
}

$sql = "SELECT v.id, v.user_id, u.username, u.email, v.description, v.image_url, v.status, v.created_at FROM verification_requests v JOIN users u ON v.user_id = u.id WHERE v.status = 'pending' ORDER BY v.created_at ASC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll();

$out = array_map(function($r) {
    return [
        'id' => (int)$r['id'],
        'user_id' => (int)$r['user_id'],
        'username' => $r['username'],
        'email' => $r['email'],
        'description' => $r['description'],
        'image_url' => $r['image_url'],
        'status' => $r['status'],
        'created_at' => $r['created_at']
    ];
}, $rows);

echo json_encode($out);
