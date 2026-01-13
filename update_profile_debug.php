<?php
// Temporary debug endpoint to set wallet for a given user_id.
// Usage (unsafe for production):
// POST/GET to update_profile_debug.php?token=letmein_debug_2026&user_id=2&wallet=0x...

require 'db.php';
header('Content-Type: application/json');

$expected = 'letmein_debug_2026';
$token = isset($_REQUEST['token']) ? $_REQUEST['token'] : null;
if ($token !== $expected) {
    http_response_code(403);
    echo json_encode(['error' => 'invalid_token']);
    exit();
}

$user_id = isset($_REQUEST['user_id']) ? intval($_REQUEST['user_id']) : 0;
$wallet = isset($_REQUEST['wallet']) ? trim($_REQUEST['wallet']) : null;
if ($user_id <= 0 || $wallet === null) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_params']);
    exit();
}

try {
    // Ensure column exists
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'wallet'")->rowCount();
    if ($col === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN wallet VARCHAR(255) DEFAULT NULL");
    }

    $stmt = $pdo->prepare("UPDATE users SET wallet = ? WHERE id = ?");
    $stmt->execute([$wallet, $user_id]);
    echo json_encode(['ok' => true, 'user_id' => $user_id, 'wallet' => $wallet, 'rows' => $stmt->rowCount()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
