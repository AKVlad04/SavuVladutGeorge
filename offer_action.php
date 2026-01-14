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

$offerId = isset($input['offer_id']) ? (int)$input['offer_id'] : 0;
$action  = isset($input['action']) ? strtolower(trim($input['action'])) : '';

if ($offerId <= 0 || !in_array($action, ['accept','reject','cancel','counter'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_data']);
    exit();
}

$userId = (int)$_SESSION['user_id'];

try {
    // Ensure balance_eth, balance_transactions and extended offers schema are present
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

    // Ensure offers has parent_offer_id and funds_reserved columns
    $tblOffers = $pdo->query("SHOW TABLES LIKE 'offers'")->rowCount();
    if ($tblOffers > 0) {
        $colParent = $pdo->query("SHOW COLUMNS FROM offers LIKE 'parent_offer_id'")->rowCount();
        if ($colParent === 0) {
            $pdo->exec("ALTER TABLE offers ADD COLUMN parent_offer_id INT UNSIGNED DEFAULT NULL AFTER price, ADD INDEX (parent_offer_id)");
        }
        $colReserved = $pdo->query("SHOW COLUMNS FROM offers LIKE 'funds_reserved'")->rowCount();
        if ($colReserved === 0) {
            $pdo->exec("ALTER TABLE offers ADD COLUMN funds_reserved TINYINT(1) NOT NULL DEFAULT 1 AFTER parent_offer_id");
        }
    }

    if ($action === 'reject') {
        // The party who did NOT initiate this offer rejects it and, if needed, buyer gets funds back.
        $pdo->beginTransaction();
        try {
            $sql = "SELECT o.id, o.nft_id, o.seller_id, o.buyer_id, o.price, o.status, o.funds_reserved,
                           n.name AS nft_name
                    FROM offers o
                    JOIN nfts n ON n.id = o.nft_id
                    WHERE o.id = ? FOR UPDATE";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$offerId]);
            $offer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$offer) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'offer_not_found']);
                exit();
            }
            if ($offer['status'] !== 'open') {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'offer_not_open']);
                exit();
            }

            $sellerId = (int)$offer['seller_id'];
            $buyerId  = (int)$offer['buyer_id'];
            $price    = (float)$offer['price'];
            $nftId    = (int)$offer['nft_id'];
            $nftName  = $offer['nft_name'];
            $fundsReserved = isset($offer['funds_reserved']) ? (int)$offer['funds_reserved'] : 1;

            // Original buyer-initiated offers have funds_reserved = 1
            $isBuyerInitiated = ($fundsReserved === 1);

            // For a buyer-initiated offer, only seller may reject.
            // For a seller counter-offer (funds_reserved = 0), only buyer may reject.
            $allowedRejectUserId = $isBuyerInitiated ? $sellerId : $buyerId;

            if ($allowedRejectUserId !== $userId) {
                $pdo->rollBack();
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'not_authorized_for_reject']);
                exit();
            }

            // Refund buyer only if funds were reserved for this offer
            if ($fundsReserved === 1) {
                $stmtBuyer = $pdo->prepare("SELECT balance_eth FROM users WHERE id = ? FOR UPDATE");
                $stmtBuyer->execute([$buyerId]);
                $buyerRow = $stmtBuyer->fetch(PDO::FETCH_ASSOC);
                if (!$buyerRow) {
                    $pdo->rollBack();
                    http_response_code(404);
                    echo json_encode(['ok' => false, 'error' => 'user_not_found']);
                    exit();
                }
                $buyerBalance = (float)$buyerRow['balance_eth'];
                $newBuyerBalance = $buyerBalance + $price;

                $updB = $pdo->prepare("UPDATE users SET balance_eth = ? WHERE id = ?");
                $updB->execute([$newBuyerBalance, $buyerId]);

                $insTx = $pdo->prepare("INSERT INTO balance_transactions (user_id, type, nft_id, nft_name, amount, balance_after) VALUES (?,?,?,?,?,?)");
                $insTx->execute([$buyerId, 'deposit', $nftId, $nftName, $price, $newBuyerBalance]);
            }

            $updO = $pdo->prepare("UPDATE offers SET status = 'rejected' WHERE id = ? AND status = 'open'");
            $updO->execute([$offerId]);

            $pdo->commit();
            echo json_encode(['ok' => true, 'action' => 'reject']);
            exit();
        } catch (Exception $inner) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $inner;
        }
    }

    if ($action === 'cancel') {
        // The party who initiated this offer cancels it and, if needed, buyer gets funds back.
        $pdo->beginTransaction();
        try {
            $sql = "SELECT o.id, o.nft_id, o.seller_id, o.buyer_id, o.price, o.status, o.funds_reserved,
                           n.name AS nft_name
                    FROM offers o
                    JOIN nfts n ON n.id = o.nft_id
                    WHERE o.id = ? FOR UPDATE";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$offerId]);
            $offer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$offer) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'offer_not_found']);
                exit();
            }
            if ($offer['status'] !== 'open') {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'offer_not_open']);
                exit();
            }

            $sellerId = (int)$offer['seller_id'];
            $buyerId  = (int)$offer['buyer_id'];
            $price    = (float)$offer['price'];
            $nftId    = (int)$offer['nft_id'];
            $nftName  = $offer['nft_name'];
            $fundsReserved = isset($offer['funds_reserved']) ? (int)$offer['funds_reserved'] : 1;

            // Original buyer-initiated offers have funds_reserved = 1
            $isBuyerInitiated = ($fundsReserved === 1);

            // For a buyer-initiated offer, buyer may cancel.
            // For a seller counter-offer (funds_reserved = 0), seller may cancel their own counter.
            $allowedCancelUserId = $isBuyerInitiated ? $buyerId : $sellerId;

            if ($allowedCancelUserId !== $userId) {
                $pdo->rollBack();
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'not_authorized_for_cancel']);
                exit();
            }

            // Refund buyer only if funds were reserved for this offer
            if ($fundsReserved === 1) {
                $stmtBuyer = $pdo->prepare("SELECT balance_eth FROM users WHERE id = ? FOR UPDATE");
                $stmtBuyer->execute([$buyerId]);
                $buyerRow = $stmtBuyer->fetch(PDO::FETCH_ASSOC);
                if (!$buyerRow) {
                    $pdo->rollBack();
                    http_response_code(404);
                    echo json_encode(['ok' => false, 'error' => 'user_not_found']);
                    exit();
                }
                $buyerBalance = (float)$buyerRow['balance_eth'];
                $newBuyerBalance = $buyerBalance + $price;

                $updB = $pdo->prepare("UPDATE users SET balance_eth = ? WHERE id = ?");
                $updB->execute([$newBuyerBalance, $buyerId]);

                $insTx = $pdo->prepare("INSERT INTO balance_transactions (user_id, type, nft_id, nft_name, amount, balance_after) VALUES (?,?,?,?,?,?)");
                $insTx->execute([$buyerId, 'deposit', $nftId, $nftName, $price, $newBuyerBalance]);
            }

            $updO = $pdo->prepare("UPDATE offers SET status = 'cancelled' WHERE id = ? AND status = 'open'");
            $updO->execute([$offerId]);

            $pdo->commit();
            echo json_encode(['ok' => true, 'action' => 'cancel']);
            exit();
        } catch (Exception $inner) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $inner;
        }
    }

    // COUNTER-OFFER LOGIC (seller proposes a new price without reserving buyer funds yet)
    if ($action === 'counter') {
        $newPrice = isset($input['new_price']) ? (float)$input['new_price'] : 0;
        if ($newPrice <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'invalid_price']);
            exit();
        }

        $pdo->beginTransaction();
        try {
            $sql = "SELECT o.id, o.nft_id, o.seller_id, o.buyer_id, o.price, o.status, o.funds_reserved,
                           n.name AS nft_name
                    FROM offers o
                    JOIN nfts n ON n.id = o.nft_id
                    WHERE o.id = ? FOR UPDATE";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$offerId]);
            $offer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$offer) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'offer_not_found']);
                exit();
            }
            if ($offer['status'] !== 'open') {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'offer_not_open']);
                exit();
            }

            $sellerId = (int)$offer['seller_id'];
            $buyerId  = (int)$offer['buyer_id'];
            $price    = (float)$offer['price'];
            $nftId    = (int)$offer['nft_id'];
            $nftName  = $offer['nft_name'];
            $fundsReserved = isset($offer['funds_reserved']) ? (int)$offer['funds_reserved'] : 1;

            if ($sellerId !== $userId) {
                $pdo->rollBack();
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'not_seller']);
                exit();
            }

            // If original offer had reserved funds, refund them
            if ($fundsReserved === 1) {
                $stmtBuyer = $pdo->prepare("SELECT balance_eth FROM users WHERE id = ? FOR UPDATE");
                $stmtBuyer->execute([$buyerId]);
                $buyerRow = $stmtBuyer->fetch(PDO::FETCH_ASSOC);
                if (!$buyerRow) {
                    $pdo->rollBack();
                    http_response_code(404);
                    echo json_encode(['ok' => false, 'error' => 'user_not_found']);
                    exit();
                }
                $buyerBalance = (float)$buyerRow['balance_eth'];
                $newBuyerBalance = $buyerBalance + $price;

                $updB = $pdo->prepare("UPDATE users SET balance_eth = ? WHERE id = ?");
                $updB->execute([$newBuyerBalance, $buyerId]);

                $insTx = $pdo->prepare("INSERT INTO balance_transactions (user_id, type, nft_id, nft_name, amount, balance_after) VALUES (?,?,?,?,?,?)");
                $insTx->execute([$buyerId, 'deposit', $nftId, $nftName, $price, $newBuyerBalance]);
            }

            // Mark original offer as rejected
            $updO = $pdo->prepare("UPDATE offers SET status = 'rejected' WHERE id = ? AND status = 'open'");
            $updO->execute([$offerId]);

            // Create new counter-offer without reserving funds yet
            $ins = $pdo->prepare("INSERT INTO offers (nft_id, seller_id, buyer_id, price, parent_offer_id, funds_reserved) VALUES (?,?,?,?,?,0)");
            $ins->execute([$nftId, $sellerId, $buyerId, $newPrice, $offerId]);

            $pdo->commit();
            echo json_encode(['ok' => true, 'action' => 'counter']);
            exit();
        } catch (Exception $inner) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $inner;
        }
    }

    // ACCEPT LOGIC
    $pdo->beginTransaction();
    try {
        $sql = "SELECT o.id, o.nft_id, o.seller_id, o.buyer_id, o.price, o.status, o.funds_reserved,
                       n.owner_id, n.name AS nft_name
                FROM offers o
                JOIN nfts n ON n.id = o.nft_id
                WHERE o.id = ? FOR UPDATE";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$offerId]);
        $offer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$offer) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'offer_not_found']);
            exit();
        }
        if ($offer['status'] !== 'open') {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'offer_not_open']);
            exit();
        }

        $sellerId = (int)$offer['seller_id'];
        $buyerId  = (int)$offer['buyer_id'];
        $price    = (float)$offer['price'];
        $nftId    = (int)$offer['nft_id'];
        $nftName  = $offer['nft_name'];
        $fundsReserved = isset($offer['funds_reserved']) ? (int)$offer['funds_reserved'] : 1;

        // Determine who must accept this offer:
        // - Buyer-initiated (funds_reserved = 1): seller must accept.
        // - Seller counter-offer (funds_reserved = 0): buyer must accept.
        $isBuyerInitiated = ($fundsReserved === 1);
        $allowedAcceptUserId = $isBuyerInitiated ? $sellerId : $buyerId;

        if ($allowedAcceptUserId !== $userId) {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'not_authorized_for_accept']);
            exit();
        }

        if ((int)$offer['owner_id'] !== $sellerId) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'nft_not_owned_by_seller']);
            exit();
        }

        // If funds were not reserved (counter-offer), charge buyer now, otherwise only credit seller
        $newSellerBalance = null;

        if ($fundsReserved === 0) {
            // Charge buyer now
            $stmtBuyer = $pdo->prepare("SELECT balance_eth FROM users WHERE id = ? FOR UPDATE");
            $stmtBuyer->execute([$buyerId]);
            $buyerRow = $stmtBuyer->fetch(PDO::FETCH_ASSOC);
            if (!$buyerRow) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'user_not_found']);
                exit();
            }
            $buyerBalance = (float)$buyerRow['balance_eth'];
            if ($buyerBalance + 1e-8 < $price) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'insufficient_funds', 'balance_eth' => $buyerBalance]);
                exit();
            }

            $newBuyerBalance = $buyerBalance - $price;
            $updB = $pdo->prepare("UPDATE users SET balance_eth = ? WHERE id = ?");
            $updB->execute([$newBuyerBalance, $buyerId]);

            $insTxBuyer = $pdo->prepare("INSERT INTO balance_transactions (user_id, type, nft_id, nft_name, amount, balance_after) VALUES (?,?,?,?,?,?)");
            $insTxBuyer->execute([$buyerId, 'buy', $nftId, $nftName, $price, $newBuyerBalance]);
        }

        // Credit seller
        $stmtSeller = $pdo->prepare("SELECT balance_eth FROM users WHERE id = ? FOR UPDATE");
        $stmtSeller->execute([$sellerId]);
        $sellerRow = $stmtSeller->fetch(PDO::FETCH_ASSOC);
        if (!$sellerRow) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'user_not_found']);
            exit();
        }
        $sellerBalance = (float)$sellerRow['balance_eth'];
        $newSellerBalance = $sellerBalance + $price;

        $updS = $pdo->prepare("UPDATE users SET balance_eth = ? WHERE id = ?");
        $updS->execute([$newSellerBalance, $sellerId]);

        // Transfer NFT ownership (do not overwrite base price)
        $updN = $pdo->prepare("UPDATE nfts SET owner_id = ? WHERE id = ?");
        $updN->execute([$buyerId, $nftId]);

        // Mark offer as accepted
        $updO = $pdo->prepare("UPDATE offers SET status = 'accepted' WHERE id = ? AND status = 'open'");
        $updO->execute([$offerId]);

        // Insert balance transaction for seller (funds received)
        $insTx = $pdo->prepare("INSERT INTO balance_transactions (user_id, type, nft_id, nft_name, amount, balance_after) VALUES (?,?,?,?,?,?)");
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
