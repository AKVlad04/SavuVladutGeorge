<?php
$host = 'mysql';      // Numele serviciului din docker-compose
$db   = 'studenti';   // Baza de date
$user = 'user';       // User
$pass = 'password';   // Parola
$port = 3306;
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    echo json_encode(['success' => false, 'message' => "Eroare DB: " . $e->getMessage()]);
    exit;
}
?>