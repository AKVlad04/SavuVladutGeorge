<?php
session_start();
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validare simplă
    if (empty($email) || empty($password)) {
        header("Location: login.html?error=empty");
        exit();
    }

    try {
        // Căutăm userul
        $stmt = $pdo->prepare("SELECT id, username, password, status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verificăm parola
        if ($user && password_verify($password, $user['password'])) {
            // Verificăm dacă e activ
            if($user['status'] !== 'active') {
                header("Location: login.html?error=inactive");
                exit();
            }

            // --- LOGIN REUȘIT ---
            // Salvăm datele în sesiune
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            // REDIRECT CĂTRE PROFIL
            header("Location: profile.html"); 
            exit();

        } else {
            // Parolă greșită sau user inexistent
            header("Location: login.html?error=invalid");
            exit();
        }
    } catch (PDOException $e) {
        // Eroare tehnică
        header("Location: login.html?error=invalid");
        exit();
    }
}
?>