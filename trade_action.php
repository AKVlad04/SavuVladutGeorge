<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'not_authenticated']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
    exit();
}

$tradeId = isset($input['trade_id']) ? (int)$input['trade_id'] : 0;
$action  = isset($input['action']) ? strtolower(trim($input['action'])) : '';

if ($tradeId <= 0 || !in_array($action, ['accept','cancel'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_data']);
    exit();
}

$userId = (int)$_SESSION['user_id'];

try {
    // Ensure balance_eth column exists
    $colBalance = $pdo->query("SHOW COLUMNS FROM users LIKE 'balance_eth'")->rowCount();
    if ($colBalance === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN balance_eth DECIMAL(16,8) NOT NULL DEFAULT 0");
    }

    // Ensure balance_transactions table and extended schema exist
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

    if ($action === 'cancel') {
        // Seller cancels their own open trade
        $stmt = $pdo->prepare("SELECT id, seller_id, status FROM trades WHERE id = ? LIMIT 1");
        $stmt->execute([$tradeId]);
        $trade = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$trade) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'trade_not_found']);
            exit();
        }
        if ($trade['status'] !== 'open') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'trade_not_open']);
            exit();
        }
        if ((int)$trade['seller_id'] !== $userId) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'not_seller']);
            exit();
        }

        $upd = $pdo->prepare("UPDATE trades SET status = 'cancelled' WHERE id = ? AND status = 'open'");
        $upd->execute([$tradeId]);

        echo json_encode(['ok' => true, 'action' => 'cancel']);
        exit();
    }

    // ACCEPT LOGIC
    $pdo->beginTransaction();
    try {
        // Lock trade and NFT
        $sql = "SELECT t.id, t.nft_id, t.seller_id, t.price, t.status,
                       n.owner_id, n.name AS nft_name
                FROM trades t
                JOIN nfts n ON n.id = t.nft_id
                WHERE t.id = ? FOR UPDATE";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tradeId]);
        $trade = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$trade) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'trade_not_found']);
            exit();
        }
        if ($trade['status'] !== 'open') {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'trade_not_open']);
            exit();
        }

        $sellerId = (int)$trade['seller_id'];
        $buyerId  = $userId;
        $price    = (float)$trade['price'];
        $nftId    = (int)$trade['nft_id'];
        $nftName  = $trade['nft_name'];

        if ($buyerId === $sellerId) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'cannot_accept_own_trade']);
            exit();
        }

        // Ensure NFT still owned by seller
        if ((int)$trade['owner_id'] !== $sellerId) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'nft_not_owned_by_seller']);
            exit();
        }

        // Lock buyer and seller balances
        $stmt = $pdo->prepare("SELECT id, balance_eth FROM users WHERE id IN (?, ?) FOR UPDATE");
        $stmt->execute([$buyerId, $sellerId]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $buyerBalance  = null;
        $sellerBalance = null;
        foreach ($users as $u) {
            if ((int)$u['id'] === $buyerId) {
                $buyerBalance = (float)$u['balance_eth'];
            } elseif ((int)$u['id'] === $sellerId) {
                $sellerBalance = (float)$u['balance_eth'];
            }
        }

        if ($buyerBalance === null || $sellerBalance === null) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'user_not_found']);
            exit();
        }

        if ($buyerBalance + 1e-8 < $price) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'insufficient_funds', 'balance_eth' => $buyerBalance]);
            exit();
        }

        $newBuyerBalance  = $buyerBalance - $price;
        $newSellerBalance = $sellerBalance + $price;

        // Update balances
        $updB = $pdo->prepare("UPDATE users SET balance_eth = ? WHERE id = ?");
        $updB->execute([$newBuyerBalance, $buyerId]);
        $updS = $pdo->prepare("UPDATE users SET balance_eth = ? WHERE id = ?");
        $updS->execute([$newSellerBalance, $sellerId]);

        // Transfer NFT ownership
        $updN = $pdo->prepare("UPDATE nfts SET owner_id = ?, price = ? WHERE id = ?");
        $updN->execute([$buyerId, $price, $nftId]);

        // Mark trade as accepted
        $updT = $pdo->prepare("UPDATE trades SET status = 'accepted', buyer_id = ?, accepted_at = NOW() WHERE id = ? AND status = 'open'");
        $updT->execute([$buyerId, $tradeId]);

        // Insert balance transactions for buyer (buy) and seller (sell)
        $insTx = $pdo->prepare("INSERT INTO balance_transactions (user_id, type, nft_id, nft_name, amount, balance_after) VALUES (?,?,?,?,?,?)");
        $insTx->execute([$buyerId, 'buy', $nftId, $nftName, $price, $newBuyerBalance]);
        $insTx->execute([$sellerId, 'sell', $nftId, $nftName, $price, $newSellerBalance]);

        $pdo->commit();

        echo json_encode(['ok' => true, 'action' => 'accept']);
    } catch (Exception $inner) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $inner;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error', 'details' => $e->getMessage()]);
}
