<?php
require_once __DIR__ . "/Api/db.php";
require_once __DIR__ . "/vendor/autoload.php";
$config = require_once __DIR__ . "/config.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$SUPER_ADMIN_ID = 345;

function getBearerToken() {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
    if (!$header && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower($name) === 'authorization') $header = $value;
        }
    }
    if (!$header) return null;
    if (preg_match('/Bearer\s(\S+)/', $header, $matches)) return $matches[1];
    return null;
}

$token = getBearerToken();
if (!$token) {
    http_response_code(401);
    echo json_encode(["status"=>"error","message"=>"Token gerekli"]);
    exit;
}

try {
    $decoded = JWT::decode($token, new Key($config['jwt_secret'], 'HS256'));
    $rol = $decoded->rol ?? null;
    $musteri_id = $decoded->sub ?? null;
    if (!$rol || !$musteri_id) throw new Exception("Token geçersiz");
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(["status"=>"error","message"=>"Geçersiz token","details"=>$e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case "GET":
            $id = $_GET['musteri_id'] ?? $_GET['id'] ?? null;
            
            // 👇 Restoran rolü kendi müşterilerini listeleyebilsin
            if ($rol === "musteri") {
                $id = $musteri_id;
            } elseif ($rol === "restoran") {
                // Eğer restoran ise, örneğin kendi müşterilerini listeleme mantığı burada kurgulanabilir.
                // Şimdilik tüm müşterilere erişim verilmez.
                http_response_code(403);
                echo json_encode(["status"=>"error","message"=>"Restoranlar müşteri bilgilerine erişemez"]);
                exit;
            } elseif ($rol !== "admin" && $rol !== "super_admin") {
                http_response_code(403);
                echo json_encode(["status"=>"error","message"=>"Yetkiniz yok"]);
                exit;
            }

            $stmt = $pdo->prepare("SELECT musteri_id, ad, soyad, email, telefon, adres, kayit_tarihi, rol FROM musteriler WHERE musteri_id=?");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$data) {
                http_response_code(404);
                echo json_encode(["status"=>"error","message"=>"Müşteri bulunamadı"]);
            } else {
                echo json_encode(["status"=>"success","data"=>$data], JSON_UNESCAPED_UNICODE);
            }
            break;

        case "POST":
            if ($rol !== "admin" && $rol !== "super_admin") {
                http_response_code(403);
                echo json_encode(["status"=>"error","message"=>"Yetkiniz yok"]);
                exit;
            }

            $input = json_decode(file_get_contents("php://input"), true);
            if (!isset($input['ad'], $input['soyad'], $input['email'], $input['sifre'])) {
                http_response_code(400);
                echo json_encode(["status"=>"error","message"=>"Eksik alan"]);
                exit;
            }

            $sifre = password_hash($input['sifre'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO musteriler (ad, soyad, email, sifre, rol) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$input['ad'], $input['soyad'], $input['email'], $sifre, $input['rol'] ?? 'musteri']);
            echo json_encode(["status"=>"success","message"=>"Müşteri eklendi","musteri_id"=>$pdo->lastInsertId()]);
            break;

        case "PUT":
            $input = json_decode(file_get_contents("php://input"), true);
            $id = $input['musteri_id'] ?? null;

            if ($rol === "musteri") {
                $id = $musteri_id;
            } elseif ($rol === "restoran") {
                http_response_code(403);
                echo json_encode(["status"=>"error","message"=>"Restoranlar müşteri bilgilerini güncelleyemez"]);
                exit;
            } elseif ($rol !== "admin" && $rol !== "super_admin") {
                http_response_code(403);
                echo json_encode(["status"=>"error","message"=>"Yetkiniz yok"]);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE musteriler SET ad=?, soyad=?, email=?, rol=? WHERE musteri_id=?");
            $stmt->execute([
                $input['ad'] ?? null,
                $input['soyad'] ?? null,
                $input['email'] ?? null,
                $input['rol'] ?? 'musteri',
                $id
            ]);
            echo json_encode(["status"=>"success","message"=>"Müşteri güncellendi"]);
            break;

        case "DELETE":
            $id = $_GET['musteri_id'] ?? $_GET['id'] ?? null;
            if ($rol !== "admin" && $rol !== "super_admin") {
                http_response_code(403);
                echo json_encode(["status"=>"error","message"=>"Yetkiniz yok"]);
                exit;
            }

            if (!$id) {
                http_response_code(400);
                echo json_encode(["status"=>"error","message"=>"musteri_id gerekli"]);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM musteriler WHERE musteri_id=?");
            $stmt->execute([$id]);
            echo json_encode(["status"=>"success","message"=>"Müşteri silindi"]);
            break;

        default:
            http_response_code(405);
            echo json_encode(["status"=>"error","message"=>"Metod desteklenmiyor"]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
}
?>
