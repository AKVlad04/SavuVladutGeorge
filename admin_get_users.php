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
    // detect optional columns
    $cols = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM users");
    foreach ($stmt->fetchAll() as $c) $cols[] = $c['Field'];

    $select = ['id','username','email','role','status'];
    if (in_array('wallet', $cols)) $select[] = 'wallet';
    if (in_array('is_verified', $cols)) $select[] = 'is_verified';

    $sql = "SELECT " . implode(',', $select) . " FROM users ORDER BY id ASC";
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll();

    // normalize status -> is_banned for frontend convenience
    foreach ($users as &$u) {
        $u['status'] = isset($u['status']) ? $u['status'] : 'active';
        $u['is_banned'] = ($u['status'] === 'banned') ? 1 : 0;
    }

    echo json_encode($users);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
