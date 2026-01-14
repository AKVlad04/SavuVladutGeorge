<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'not_authenticated']);
    exit();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$action = isset($data['action']) ? strtolower(trim($data['action'])) : '';
$amount = isset($data['amount']) ? $data['amount'] : null;

if (!in_array($action, ['deposit', 'withdraw'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_action']);
    exit();
}

if (!is_numeric($amount)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_amount']);
    exit();
}

$amount = (float)$amount;
if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'amount_must_be_positive']);
    exit();
}

$userId = (int)$_SESSION['user_id'];

try {
    // Ensure balance_eth column exists (for older databases)
    $colBalance = $pdo->query("SHOW COLUMNS FROM users LIKE 'balance_eth'")->rowCount();
    if ($colBalance === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN balance_eth DECIMAL(16,8) NOT NULL DEFAULT 0");
    }

    // Ensure wallet column exists and user has a wallet set
    $colWallet = $pdo->query("SHOW COLUMNS FROM users LIKE 'wallet'")->rowCount();
    if ($colWallet === 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'wallet_required']);
        exit();
    }

    $stmt = $pdo->prepare("SELECT wallet, balance_eth FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'user_not_found']);
        exit();
    }

    $wallet = isset($row['wallet']) ? trim($row['wallet']) : '';
    if ($wallet === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'wallet_required']);
        exit();
    }

    $currentBalance = isset($row['balance_eth']) ? (float)$row['balance_eth'] : 0.0;

    if ($action === 'withdraw' && $amount > $currentBalance + 1e-8) { // small epsilon
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'insufficient_funds', 'balance_eth' => $currentBalance]);
        exit();
    }

    if ($action === 'deposit') {
        $newBalance = $currentBalance + $amount;
    } else { // withdraw
        $newBalance = $currentBalance - $amount;
        if ($newBalance < 0) {
            $newBalance = 0;
        }
    }

    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare("UPDATE users SET balance_eth = ? WHERE id = ?");
        $upd->execute([$newBalance, $userId]);

        // Ensure balance_transactions table exists
        $tblTx = $pdo->query("SHOW TABLES LIKE 'balance_transactions'")->rowCount();
        if ($tblTx === 0) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS balance_transactions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  type ENUM('deposit','withdraw','buy','sell') NOT NULL,
  nft_id INT UNSIGNED DEFAULT NULL,
  nft_name VARCHAR(191) DEFAULT NULL,
  amount DECIMAL(16,8) NOT NULL,
  balance_after DECIMAL(16,8) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX (user_id),
  INDEX (created_at),
  CONSTRAINT fk_balance_tx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } else {
            // Ensure enum supports buy/sell and trade-related columns exist
            $colType = $pdo->query("SHOW COLUMNS FROM balance_transactions LIKE 'type'")->fetch(PDO::FETCH_ASSOC);
            if ($colType && isset($colType['Type']) && strpos($colType['Type'], "'buy'") === false) {
                $pdo->exec("ALTER TABLE balance_transactions MODIFY COLUMN type ENUM('deposit','withdraw','buy','sell') NOT NULL");
            }
            $colNftId = $pdo->query("SHOW COLUMNS FROM balance_transactions LIKE 'nft_id'")->rowCount();
            if ($colNftId === 0) {
                $pdo->exec("ALTER TABLE balance_transactions ADD COLUMN nft_id INT UNSIGNED DEFAULT NULL AFTER type");
            }
            $colNftName = $pdo->query("SHOW COLUMNS FROM balance_transactions LIKE 'nft_name'")->rowCount();
            if ($colNftName === 0) {
                $pdo->exec("ALTER TABLE balance_transactions ADD COLUMN nft_name VARCHAR(191) DEFAULT NULL AFTER nft_id");
            }
        }

        $ins = $pdo->prepare("INSERT INTO balance_transactions (user_id, type, amount, balance_after) VALUES (?,?,?,?)");
        $ins->execute([$userId, $action, $amount, $newBalance]);

        $pdo->commit();
    } catch (Exception $inner) {
        $pdo->rollBack();
        throw $inner;
    }

    echo json_encode(['ok' => true, 'balance_eth' => $newBalance]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error', 'details' => $e->getMessage()]);
}
