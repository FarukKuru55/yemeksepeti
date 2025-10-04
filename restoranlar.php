<?php
// Hata raporlamayı aç (geliştirme için)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// JSON ve CORS ayarları
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Preflight (OPTIONS) isteğine cevap
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/Api/db.php";

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {

        case "GET":
            $id = $_GET['restoran_id'] ?? $_GET['id'] ?? null;

            if ($id) {
                $stmt = $pdo->prepare("SELECT * FROM restoranlar WHERE restoran_id = ?");
                $stmt->execute([$id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($data) {
                    echo json_encode(["status" => "success", "data" => $data], JSON_UNESCAPED_UNICODE);
                } else {
                    http_response_code(404);
                    echo json_encode(["status" => "error", "message" => "Restoran bulunamadı"]);
                }
            } else {
                $stmt = $pdo->query("SELECT * FROM restoranlar ORDER BY restoran_id ASC");
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(["status" => "success", "data" => $data], JSON_UNESCAPED_UNICODE);
            }
            break;

        case "POST":
            $input = json_decode(file_get_contents("php://input"), true);
            if (!$input && !empty($_POST)) $input = $_POST;

            if (empty($input['ad']) || empty($input['adres'])) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Restoran adı ve adresi gerekli"]);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO restoranlar (ad, adres, telefon, acilis_saati, kapanis_saati, email, sifre) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $ok = $stmt->execute([
                $input['ad'],
                $input['adres'],
                $input['telefon'] ?? null,
                $input['acilis_saati'] ?? null,
                $input['kapanis_saati'] ?? null,
                $input['email'] ?? null,
                $input['sifre'] ?? null,
            ]);

            echo json_encode(
                $ok
                    ? ["status" => "success", "message" => "Restoran eklendi", "id" => $pdo->lastInsertId()]
                    : ["status" => "error", "message" => "Restoran eklenemedi"]
            );
            break;

        case "PUT":
            $id = $_GET['restoran_id'] ?? $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "ID gerekli"]);
                exit;
            }

            $input = json_decode(file_get_contents("php://input"), true);
            if (!$input) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Güncellenecek veriler gerekli"]);
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE restoranlar 
                SET ad=?, adres=?, telefon=?, puan=?, acilis_saati=?, kapanis_saati=? 
                WHERE restoran_id=?
            ");
            $ok = $stmt->execute([
                $input['ad'] ?? null,
                $input['adres'] ?? null,
                $input['telefon'] ?? null,
                $input['puan'] ?? null,
                $input['acilis_saati'] ?? null,
                $input['kapanis_saati'] ?? null,
                $id
            ]);

            echo json_encode($ok 
                ? ["status" => "success", "message" => "Restoran güncellendi"] 
                : ["status" => "error", "message" => "Güncellenemedi"]
            );
            break;

        case "DELETE":
            $id = $_GET['restoran_id'] ?? $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "ID gerekli"]);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM restoranlar WHERE restoran_id=?");
            $ok = $stmt->execute([$id]);

            echo json_encode($ok 
                ? ["status" => "success", "message" => "Restoran silindi"] 
                : ["status" => "error", "message" => "Silinemedi"]
            );
            break;

        default:
            http_response_code(405);
            echo json_encode(["status" => "error", "message" => "Geçersiz method"]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
