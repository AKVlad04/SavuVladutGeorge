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

$nftId = isset($input['nft_id']) ? (int)$input['nft_id'] : 0;
$price = isset($input['price']) ? (float)$input['price'] : 0;

if ($nftId <= 0 || $price <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_data']);
    exit();
}

$buyerId = (int)$_SESSION['user_id'];

try {
    // Ensure offers table exists and has extended columns (for older databases)
    $tblOffers = $pdo->query("SHOW TABLES LIKE 'offers'")->rowCount();
    if ($tblOffers === 0) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS offers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nft_id INT UNSIGNED NOT NULL,
  seller_id INT NOT NULL,
  buyer_id INT NOT NULL,
  price DECIMAL(16,8) NOT NULL,
  parent_offer_id INT UNSIGNED DEFAULT NULL,
  funds_reserved TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('open','accepted','rejected','cancelled') NOT NULL DEFAULT 'open',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX (nft_id),
  INDEX (seller_id),
  INDEX (buyer_id),
  INDEX (parent_offer_id),
  INDEX (status),
  CONSTRAINT fk_offers_nft FOREIGN KEY (nft_id) REFERENCES nfts(id) ON DELETE CASCADE,
  CONSTRAINT fk_offers_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_offers_buyer FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    } else {
        $colParent = $pdo->query("SHOW COLUMNS FROM offers LIKE 'parent_offer_id'")->rowCount();
        if ($colParent === 0) {
            $pdo->exec("ALTER TABLE offers ADD COLUMN parent_offer_id INT UNSIGNED DEFAULT NULL AFTER price, ADD INDEX (parent_offer_id)");
        }
        $colReserved = $pdo->query("SHOW COLUMNS FROM offers LIKE 'funds_reserved'")->rowCount();
        if ($colReserved === 0) {
            $pdo->exec("ALTER TABLE offers ADD COLUMN funds_reserved TINYINT(1) NOT NULL DEFAULT 1 AFTER parent_offer_id");
        }
    }

    // Verify NFT exists and is owned by someone
    $stmt = $pdo->prepare("SELECT id, owner_id, is_deleted, name FROM nfts WHERE id = ? LIMIT 1");
    $stmt->execute([$nftId]);
    $nft = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$nft) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'nft_not_found']);
        exit();
    }
    if (!empty($nft['is_deleted'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'nft_deleted']);
        exit();
    }

    $sellerId = isset($nft['owner_id']) ? (int)$nft['owner_id'] : 0;
    if ($sellerId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'nft_has_no_owner']);
        exit();
    }

    if ($sellerId === $buyerId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'cannot_offer_on_own_nft']);
        exit();
    }
    // Ensure balance_eth column exists and balance_transactions table is ready
    $colBalance = $pdo->query("SHOW COLUMNS FROM users LIKE 'balance_eth'")->rowCount();
    if ($colBalance === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN balance_eth DECIMAL(16,8) NOT NULL DEFAULT 0");
    }

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

    // Reserve funds immediately when placing the offer
    $pdo->beginTransaction();
    try {
        $stmtBal = $pdo->prepare("SELECT balance_eth FROM users WHERE id = ? FOR UPDATE");
        $stmtBal->execute([$buyerId]);
        $userRow = $stmtBal->fetch(PDO::FETCH_ASSOC);
        if (!$userRow) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'user_not_found']);
            exit();
        }
        $buyerBalance = (float)$userRow['balance_eth'];
        if ($buyerBalance + 1e-8 < $price) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'insufficient_funds', 'balance_eth' => $buyerBalance]);
            exit();
        }

        $newBuyerBalance = $buyerBalance - $price;
        $updBal = $pdo->prepare("UPDATE users SET balance_eth = ? WHERE id = ?");
        $updBal->execute([$newBuyerBalance, $buyerId]);

        $insTx = $pdo->prepare("INSERT INTO balance_transactions (user_id, type, nft_id, nft_name, amount, balance_after) VALUES (?,?,?,?,?,?)");
        $insTx->execute([$buyerId, 'withdraw', $nftId, $nft['name'], $price, $newBuyerBalance]);

        // Buyer-initiated offer: funds are reserved immediately
        $ins = $pdo->prepare("INSERT INTO offers (nft_id, seller_id, buyer_id, price, funds_reserved) VALUES (?,?,?,?,1)");
        $ins->execute([$nftId, $sellerId, $buyerId, $price]);

        $pdo->commit();
        echo json_encode(['ok' => true]);
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
