<?php
require 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['owner','admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit();
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || !isset($body['action']) || !isset($body['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid payload']);
    exit();
}

$action = $body['action'];
$id = $body['id'];

try {
    // fetch caller role and target role/status to enforce restrictions (for user actions)
    $callerRole = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : null;
    $targetRole = null;
    $targetStatus = null;

    if (in_array($action, ['ban','promote','demote','verify'])) {
        $uRow = $pdo->prepare("SELECT role, status FROM users WHERE id = ?");
        $uRow->execute([$id]);
        $rRow = $uRow->fetch();
        if ($rRow) {
            if (isset($rRow['role'])) $targetRole = strtolower($rRow['role']);
            if (isset($rRow['status'])) $targetStatus = $rRow['status'];
        }
    }

    // BAN / UNBAN
    if ($action === 'ban') {
        if ($targetStatus === 'disabled') {
            http_response_code(403);
            echo json_encode(['error' => 'cannot perform actions on disabled user']);
            exit();
        }
        // admin can ban only regular users
        if ($callerRole === 'admin') {
            if (!($targetRole && $targetRole === 'user')) {
                http_response_code(403);
                echo json_encode(['error' => 'admins can ban only regular users']);
                exit();
            }
        }
        // toggle using status field
        $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        $new = ($row && $row['status'] === 'banned') ? 'active' : 'banned';
        $u = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $u->execute([$new, $id]);
        echo json_encode(['ok' => true, 'action' => 'ban', 'status' => $new]);
        exit();
    }

    // PROMOTE
    if ($action === 'promote') {
        if ($targetStatus === 'disabled') {
            http_response_code(403);
            echo json_encode(['error' => 'cannot perform actions on disabled user']);
            exit();
        }
        if ($callerRole === 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'admins cannot promote users']);
            exit();
        }
        $q = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
        $q->execute([$id]);
        echo json_encode(['ok' => true, 'action' => 'promote']);
        exit();
    }

    // DEMOTE
    if ($action === 'demote') {
        if ($targetStatus === 'disabled') {
            http_response_code(403);
            echo json_encode(['error' => 'cannot perform actions on disabled user']);
            exit();
        }
        if ($callerRole === 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'admins cannot demote users']);
            exit();
        }
        // protect owner
        if ($targetRole && strtolower($targetRole) === 'owner') {
            http_response_code(403);
            echo json_encode(['error' => 'cannot demote owner']);
            exit();
        }
        $q = $pdo->prepare("UPDATE users SET role = 'user' WHERE id = ?");
        $q->execute([$id]);
        echo json_encode(['ok' => true, 'action' => 'demote']);
        exit();
    }

    // VERIFY USER
    if ($action === 'verify') {
        // ensure column exists
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_verified'")->rowCount();
        if ($col === 0) $pdo->exec("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) DEFAULT 0");
        $q = $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
        $q->execute([$id]);
        echo json_encode(['ok' => true, 'action' => 'verify']);
        exit();
    }

    // SOFT DELETE NFT (is_deleted = 1)
    if ($action === 'delete_nft') {
        $col = $pdo->query("SHOW COLUMNS FROM nfts LIKE 'is_deleted'")->rowCount();
        if ($col === 0) $pdo->exec("ALTER TABLE nfts ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");
        $q = $pdo->prepare("UPDATE nfts SET is_deleted = 1 WHERE id = ?");
        $q->execute([$id]);
        echo json_encode(['ok' => true, 'action' => 'delete_nft']);
        exit();
    }

    // FEATURE NFT (is_featured = 1)
    if ($action === 'feature_nft') {
        $col = $pdo->query("SHOW COLUMNS FROM nfts LIKE 'is_featured'")->rowCount();
        if ($col === 0) $pdo->exec("ALTER TABLE nfts ADD COLUMN is_featured TINYINT(1) DEFAULT 0");
        $pdo->prepare("UPDATE nfts SET is_featured = 1 WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true, 'action' => 'feature_nft']);
        exit();
    }

    // UNFEATURE NFT (is_featured = 0)
    if ($action === 'unfeature_nft') {
        $col = $pdo->query("SHOW COLUMNS FROM nfts LIKE 'is_featured'")->rowCount();
        if ($col === 0) $pdo->exec("ALTER TABLE nfts ADD COLUMN is_featured TINYINT(1) DEFAULT 0");
        $pdo->prepare("UPDATE nfts SET is_featured = 0 WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true, 'action' => 'unfeature_nft']);
        exit();
    }

    // NFT APPROVAL (is_approved = 1)
    if ($action === 'nft_approve') {
        $col = $pdo->query("SHOW COLUMNS FROM nfts LIKE 'is_approved'")->rowCount();
        if ($col === 0) $pdo->exec("ALTER TABLE nfts ADD COLUMN is_approved TINYINT(1) DEFAULT 0 AFTER is_deleted");
        $pdo->prepare("UPDATE nfts SET is_approved = 1 WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true, 'action' => 'nft_approve']);
        exit();
    }

    // NFT REJECTION (keeps is_approved = 0, hides NFT by soft delete)
    if ($action === 'nft_reject') {
        $col = $pdo->query("SHOW COLUMNS FROM nfts LIKE 'is_approved'")->rowCount();
        if ($col === 0) $pdo->exec("ALTER TABLE nfts ADD COLUMN is_approved TINYINT(1) DEFAULT 0 AFTER is_deleted");
        // also mark NFT as deleted so it doesn't appear in lists
        $delCol = $pdo->query("SHOW COLUMNS FROM nfts LIKE 'is_deleted'")->rowCount();
        if ($delCol === 0) $pdo->exec("ALTER TABLE nfts ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");
        $pdo->prepare("UPDATE nfts SET is_deleted = 1 WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true, 'action' => 'nft_reject']);
        exit();
    }

    // DISMISS REPORT
    if ($action === 'dismiss_report') {
        $pdo->prepare("DELETE FROM reports WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true, 'action' => 'dismiss_report']);
        exit();
    }

    // VERIFICATION REQUEST APPROVE
    if ($action === 'verification_approve') {
        $req = $pdo->prepare("SELECT user_id FROM verification_requests WHERE id = ? AND status = 'pending'");
        $req->execute([$id]);
        $row = $req->fetch();
        if ($row && $row['user_id']) {
            $userId = $row['user_id'];
            $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_verified'")->rowCount();
            if ($col === 0) $pdo->exec("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) DEFAULT 0");
            $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?")->execute([$userId]);
            $pdo->prepare("UPDATE verification_requests SET status = 'approved' WHERE id = ?")->execute([$id]);
            echo json_encode(['ok' => true, 'action' => 'verification_approve']);
            exit();
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'request not found']);
            exit();
        }
    }

    // VERIFICATION REQUEST REJECT
    if ($action === 'verification_reject') {
        $pdo->prepare("UPDATE verification_requests SET status = 'rejected' WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true, 'action' => 'verification_reject']);
        exit();
    }

    echo json_encode(['error' => 'unknown action']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
