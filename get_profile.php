<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

// 1. Verificăm dacă e logat
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['logged_in' => false]);
    exit();
}

// 2. Extragem datele
try {
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT username, email, status FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user) {
        // Generăm inițiala
        $initial = strtoupper(substr($user['username'], 0, 1));

        echo json_encode([
            'logged_in' => true,
            'id' => $user_id,
            'username' => $user['username'],
            'email' => $user['email'],
            'status' => ucfirst($user['status']),
            'initial' => $initial
        ]);
    } else {
        echo json_encode(['logged_in' => false]);
    }
} catch (Exception $e) {
    echo json_encode(['logged_in' => false, 'error' => $e->getMessage()]);
}