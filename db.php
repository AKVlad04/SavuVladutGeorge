<?php
// ATENȚIE: Când rulezi acest script prin Docker (site-ul tău), host-ul este 'db'.
// Dacă ai încerca să rulezi acest script de pe laptopul tău (fără docker), host-ul ar fi '127.0.0.1'.
$host = 'db'; 

$port = '3306';      
$user = 'root';
$pass = 'parola'; 
$db   = 'savuvladutgeorge';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // Poți decomenta linia de mai jos pentru a verifica vizual conexiunea în browser
    // echo "Conexiune reușită la baza de date!";
} catch (\PDOException $e) {
    die("Eroare conexiune DB: " . $e->getMessage());
}
?>