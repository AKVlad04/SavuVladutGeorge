<?php
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validare
    if (empty($username) || empty($email) || empty($password)) {
        header("Location: signup.html?error=empty");
        exit();
    }
    
    if ($password !== $confirm_password) {
        header("Location: signup.html?error=mismatch");
        exit();
    }

    try {
        // Verificare duplicat
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        
        if ($stmt->rowCount() > 0) {
            header("Location: signup.html?error=exists");
            exit();
        }

        // Inserare
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, email, password, status) VALUES (?, ?, ?, 'active')";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$username, $email, $hashed_password])) {
            header("Location: login.html?success=registered");
            exit();
        } else {
            header("Location: signup.html?error=db");
            exit();
        }

    } catch (PDOException $e) {
    // Comentăm redirecționarea
    // header("Location: signup.html?error=db");
    // exit();
    
    // Afișăm eroarea brută pe ecran
    die("EROARE DETALIATĂ: " . $e->getMessage()); 
}
}
?>