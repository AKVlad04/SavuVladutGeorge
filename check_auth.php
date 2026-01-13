<?php
session_start();
header('Content-Type: application/json'); // Răspundem în format JSON (pentru JS)
require 'db.php';

if (isset($_SESSION['user_id'])) {
    $role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

    // Încercăm întotdeauna să citim rolul curent din DB (dacă coloana există)
    try {
        $colStmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'role'");
        $colStmt->execute();
        if ($colStmt->rowCount() > 0) {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $r = $stmt->fetch();
            if ($r && isset($r['role'])) {
                $role = $r['role'];
                $_SESSION['role'] = $role;
            }
        }
    } catch (Exception $e) {
        // ignore DB errors and fallback to existing session value or 'user'
    }

    if (!$role) $role = 'user';

    echo json_encode([
        'logged_in' => true,
        'username' => $_SESSION['username'],
        'role' => $role
    ]);
} else {
    echo json_encode([
        'logged_in' => false
    ]);
}
?>