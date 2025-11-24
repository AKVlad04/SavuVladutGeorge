<?php
// host.docker.internal se referă la mașina fizică (gazda) din interiorul Docker
$host = 'host.docker.internal'; 
$port = '3307';      // Portul pe care l-ai deschis la BattleBoats
$db   = 'nexus_db';
$user = 'root';
$pass = 'parola';
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
    die("Eroare conexiune DB: " . $e->getMessage());
}