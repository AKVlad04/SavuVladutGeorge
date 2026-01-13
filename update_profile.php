<?php
require 'db.php';
session_start();
header('Content-Type: application/json');

// Build a lightweight debug object to return to client (avoid file writes)
$debug = [
    'session_id' => session_id(),
    'cookies' => $_COOKIE,
    'session_user_id' => $_SESSION['user_id'] ?? null,
    'post' => $_POST,
];

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'not_authenticated']);
    exit();
}

$data = $_POST;
$wallet = isset($data['wallet']) ? trim($data['wallet']) : null;

if ($wallet === null) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_wallet']);
    exit();
}

try {
    // Ensure wallet column exists
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'wallet'")->rowCount();
    if ($col === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN wallet VARCHAR(255) DEFAULT NULL");
    }

    $stmt = $pdo->prepare("UPDATE users SET wallet = ? WHERE id = ?");
    $stmt->execute([$wallet, $_SESSION['user_id']]);
    $affected = $stmt->rowCount();

    // Update session (optional)
    $_SESSION['wallet'] = $wallet;

    // Return debug info to help the client verify
    $resp = ['ok' => true, 'wallet' => $wallet, 'rows_affected' => $affected, 'session_user' => $_SESSION['user_id'] ?? null, 'debug' => $debug];
    echo json_encode($resp);
} catch (Exception $e) {
    http_response_code(500);
    $err = ['error' => $e->getMessage(), 'debug' => $debug];
    echo json_encode($err);
}
