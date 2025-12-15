<?php
require_once '../db.php'; 

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];


switch ($method) {
    case 'GET':
        $sql = "SELECT id, nume, anul_studiu, media_generala FROM studenti ORDER BY nume ASC";
        
        try {
            $stmt = $pdo->query($sql);
            $studenti = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($studenti as &$student) {
                 $student['media_generala'] = (float) $student['media_generala'];
            }
            unset($student); 

            echo json_encode($studenti);

        } catch (\PDOException $e) {
            http_response_code(500);
            error_log("Eroare PDO la SELECT: " . $e->getMessage());
            die(json_encode(["error" => "Eroare la baza de date (GET)."]));
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['nume'], $data['anul_studiu'], $data['media_generala'])) {
            http_response_code(400); 
            echo json_encode(["success" => false, "message" => "Date incomplete."]);
            exit;
        }

        $nume = $data['nume'];
        $anul_studiu = intval($data['anul_studiu']);
        $media_generala = floatval($data['media_generala']);

        if ($anul_studiu < 1 || $anul_studiu > 4 || $media_generala < 1.0 || $media_generala > 10.0) {
            http_response_code(400); 
            echo json_encode(["success" => false, "message" => "Anul sau media sunt in afara intervalului permis."]);
            exit;
        }

        $sql = "INSERT INTO studenti (nume, anul_studiu, media_generala) VALUES (?, ?, ?)";
        
        try {
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$nume, $anul_studiu, $media_generala])) {
                echo json_encode(["success" => true, "message" => "Studentul a fost adăugat cu succes."]);
            } else {
                http_response_code(500); 
                echo json_encode(["success" => false, "message" => "Eroare la executarea inserării."]);
            }
        } catch (\PDOException $e) {
            http_response_code(500);
            error_log("Eroare PDO la INSERT: " . $e->getMessage());
            die(json_encode(["error" => "Eroare la baza de date (POST)."]));
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['id'], $data['nume'], $data['anul_studiu'], $data['media_generala'])) {
            http_response_code(400); 
            echo json_encode(["success" => false, "message" => "Date incomplete pentru actualizare."]);
            exit;
        }

        $student_id = intval($data['id']);
        $nume = $data['nume'];
        $anul_studiu = intval($data['anul_studiu']);
        $media_generala = floatval($data['media_generala']);

        if ($anul_studiu < 1 || $anul_studiu > 4 || $media_generala < 1.0 || $media_generala > 10.0) {
            http_response_code(400); 
            echo json_encode(["success" => false, "message" => "Anul sau media sunt in afara intervalului permis."]);
            exit;
        }

        $sql = "UPDATE studenti SET nume = ?, anul_studiu = ?, media_generala = ? WHERE id = ?";
        
        try {
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$nume, $anul_studiu, $media_generala, $student_id])) {
                if ($stmt->rowCount() > 0) {
                    echo json_encode(["success" => true, "message" => "Studentul cu ID-ul $student_id a fost actualizat."]);
                } else {
                    echo json_encode(["success" => false, "message" => "Studentul nu a fost găsit sau nu au existat modificări de salvat."]);
                }
            } else {
                 http_response_code(500); 
                 echo json_encode(["success" => false, "message" => "Eroare la executarea actualizării."]);
            }
        } catch (\PDOException $e) {
            http_response_code(500);
            error_log("Eroare PDO la UPDATE: " . $e->getMessage());
            die(json_encode(["error" => "Eroare la baza de date (PUT)."]));
        }
        break;

    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['id'])) {
            http_response_code(400); 
            echo json_encode(["success" => false, "message" => "ID-ul studentului lipsește."]);
            exit;
        }

        $student_id = intval($data['id']);

        $sql = "DELETE FROM studenti WHERE id = ?";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$student_id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Studentul cu ID-ul $student_id a fost șters."]);
            } else {
                http_response_code(404); // Not Found
                echo json_encode(["success" => false, "message" => "Studentul cu ID-ul $student_id nu a fost găsit."]);
            }
        } catch (\PDOException $e) {
            http_response_code(500);
            error_log("Eroare PDO la DELETE: " . $e->getMessage());
            die(json_encode(["error" => "Eroare la baza de date (DELETE)."]));
        }
        break;

    default:
        http_response_code(405); 
        echo json_encode(["success" => false, "message" => "Metoda nu este permisă."]);
        break;
}

?>