<?php
header('Content-Type: application/json');
require 'db.php'; // Aici ne conectăm

$response = [
    'success' => false,
    'errors' => [],
    'message' => ''
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nume = trim($_POST['nume'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mesaj = trim($_POST['mesaj'] ?? '');

    // Validare simplă
    if (strlen($nume) < 3) $response['errors']['nume'] = "Minim 3 caractere.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $response['errors']['email'] = "Email invalid.";
    if (strlen($mesaj) < 10) $response['errors']['mesaj'] = "Minim 10 caractere.";

    // Dacă nu sunt erori, INSERĂM în baza de date
    if (empty($response['errors'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO mesaje (nume, email, mesaj) VALUES (:nume, :email, :mesaj)");
            $stmt->execute([':nume' => $nume, ':email' => $email, ':mesaj' => $mesaj]);
            
            $response['success'] = true;
            $response['message'] = "Mesaj salvat în baza de date!";
        } catch (PDOException $e) {
            $response['message'] = "Eroare SQL: " . $e->getMessage();
        }
    }
}
echo json_encode($response);
?>