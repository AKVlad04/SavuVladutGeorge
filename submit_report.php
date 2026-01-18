<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'not_authenticated']);
    exit();
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
    exit();
}

$targetUserId = isset($data['target_user_id']) ? (int)$data['target_user_id'] : 0;
$reason       = isset($data['reason']) ? trim($data['reason']) : '';
$details      = isset($data['details']) ? trim($data['details']) : '';

if ($targetUserId <= 0 || $reason === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_fields']);
    exit();
}

$reporterId = (int)$_SESSION['user_id'];

try {
    // Ensure reports table exists with flexible schema supporting user and NFT reports
    $hasReports = $pdo->query("SHOW TABLES LIKE 'reports'")->rowCount() > 0;
    if (!$hasReports) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  type ENUM('nft','user') NOT NULL DEFAULT 'user',
  item_id INT UNSIGNED DEFAULT NULL,
  reporter_id INT NOT NULL,
  reported_by VARCHAR(191) NOT NULL,
  reason VARCHAR(191) NOT NULL,
  details TEXT DEFAULT NULL,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX (type),
  INDEX (item_id),
  INDEX (reporter_id),
  INDEX (status),
  INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    } else {
        // Best-effort: ensure new columns exist on older schemas
        $cols = $pdo->query("SHOW COLUMNS FROM reports")->fetchAll(PDO::FETCH_COLUMN, 0);
        if (!in_array('type', $cols, true)) {
            $pdo->exec("ALTER TABLE reports ADD COLUMN type ENUM('nft','user') NOT NULL DEFAULT 'user' AFTER id");
        }
        if (!in_array('reporter_id', $cols, true)) {
            $pdo->exec("ALTER TABLE reports ADD COLUMN reporter_id INT NOT NULL DEFAULT 0 AFTER item_id");
        }
        if (!in_array('details', $cols, true)) {
            $pdo->exec("ALTER TABLE reports ADD COLUMN details TEXT NULL AFTER reason");
        }
        if (!in_array('status', $cols, true)) {
            $pdo->exec("ALTER TABLE reports ADD COLUMN status ENUM('open','closed') NOT NULL DEFAULT 'open' AFTER details");
        }
    }

    // Resolve reporter username for convenience in dashboard listing
    $uStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $uStmt->execute([$reporterId]);
    $u = $uStmt->fetch(PDO::FETCH_ASSOC);
    $reporterName = $u && isset($u['username']) ? $u['username'] : ('user#' . $reporterId);

    // Prevent self-reporting
    if ($targetUserId === $reporterId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'cannot_report_self']);
        exit();
    }

    $ins = $pdo->prepare("INSERT INTO reports (type, item_id, reporter_id, reported_by, reason, details) VALUES ('user', ?, ?, ?, ?, ?)");
    $ins->execute([$targetUserId, $reporterId, $reporterName, $reason, $details]);

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error', 'details' => $e->getMessage()]);
}
