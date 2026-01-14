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
    // Check if wallet / avatar columns exist
    $hasWallet  = $pdo->query("SHOW COLUMNS FROM users LIKE 'wallet'")->rowCount() > 0;
    $hasAvatar  = $pdo->query("SHOW COLUMNS FROM users LIKE 'avatar_url'")->rowCount() > 0;
    $hasBalance = $pdo->query("SHOW COLUMNS FROM users LIKE 'balance_eth'")->rowCount() > 0;

    $cols = "username, email, status, role";
    if ($hasWallet)  { $cols .= ", wallet"; }
    if ($hasBalance) { $cols .= ", balance_eth"; }

    $stmt = $pdo->prepare("SELECT $cols FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user) {
        // Generăm inițiala
        $initial = strtoupper(substr($user['username'], 0, 1));

        $out = [
            'logged_in' => true,
            'id' => $user_id,
            'username' => $user['username'],
            'email' => $user['email'],
            'status' => ucfirst($user['status']),
            'role' => ucfirst($user['role']),
            'initial' => $initial
        ];
        // include is_verified if column exists
        $hasVerified = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_verified'")->rowCount() > 0;
        if ($hasVerified) {
            $vstmt = $pdo->prepare("SELECT is_verified FROM users WHERE id = ?");
            $vstmt->execute([$user_id]);
            $vr = $vstmt->fetch();
            $out['is_verified'] = ($vr && isset($vr['is_verified']) && $vr['is_verified']) ? 1 : 0;
        } else {
            $out['is_verified'] = 0;
        }
        if ($hasWallet) {
            $out['wallet'] = isset($user['wallet']) ? $user['wallet'] : '';
        }
        if ($hasBalance) {
            $out['balance_eth'] = isset($user['balance_eth']) ? (float)$user['balance_eth'] : 0.0;
        }
        if ($hasAvatar) {
            $astmt = $pdo->prepare("SELECT avatar_url FROM users WHERE id = ?");
            $astmt->execute([$user_id]);
            $ar = $astmt->fetch();
            if ($ar && !empty($ar['avatar_url'])) {
                $out['avatar_url'] = $ar['avatar_url'];
            }
        }
        echo json_encode($out);
    } else {
        echo json_encode(['logged_in' => false]);
    }
} catch (Exception $e) {
    echo json_encode(['logged_in' => false, 'error' => $e->getMessage()]);
}