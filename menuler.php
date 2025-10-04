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

// --- TOKEN ---
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
    $restoran_id_token = $decoded->restoran_id ?? null;
    if (!$rol) throw new Exception("Token geçersiz");
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(["status"=>"error","message"=>"Geçersiz token","details"=>$e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === "GET") {
        $menu_id = $_GET['id'] ?? null;

        if ($rol === "restoran") {
            if ($menu_id) {
                $stmt = $pdo->prepare("SELECT * FROM menuler WHERE menu_id=? AND restoran_id=?");
                $stmt->execute([$menu_id, $restoran_id_token]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM menuler WHERE restoran_id=? ORDER BY menu_id DESC");
                $stmt->execute([$restoran_id_token]);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } elseif ($rol === "admin") {
            if ($menu_id) {
                $stmt = $pdo->prepare("SELECT * FROM menuler WHERE menu_id=?");
                $stmt->execute([$menu_id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->query("SELECT * FROM menuler ORDER BY menu_id DESC");
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            http_response_code(403);
            echo json_encode(["status"=>"error","message"=>"Yetkiniz yok"]);
            exit;
        }

        if (!$data) {
            http_response_code(404);
            echo json_encode(["status"=>"error","message"=>"Menü bulunamadı"]);
        } else {
            echo json_encode(["status"=>"success","data"=>$data], JSON_UNESCAPED_UNICODE);
        }

    } elseif ($method === "POST") {
        if ($rol !== "restoran" && $rol !== "admin") {
            http_response_code(403);
            echo json_encode(["status"=>"error","message"=>"Yetkiniz yok"]);
            exit;
        }

        $input = json_decode(file_get_contents("php://input"), true);
        if (!isset($input['ad'], $input['aciklama'], $input['fiyat'])) {
            http_response_code(400);
            echo json_encode(["status"=>"error","message"=>"Eksik alan"]);
            exit;
        }

        $restoran_id = ($rol === "restoran") ? $restoran_id_token : ($input['restoran_id'] ?? null);
        if (!$restoran_id) {
            http_response_code(400);
            echo json_encode(["status"=>"error","message"=>"restoran_id gerekli"]);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO menuler (restoran_id, ad, aciklama, fiyat) VALUES (?, ?, ?, ?)");
        $stmt->execute([$restoran_id, $input['ad'], $input['aciklama'], $input['fiyat']]);
        echo json_encode(["status"=>"success","message"=>"Menü eklendi","menu_id"=>$pdo->lastInsertId()]);

    } elseif ($method === "PUT") {
        $input = json_decode(file_get_contents("php://input"), true);
        $menu_id = $input['menu_id'] ?? null;
        if (!$menu_id) {
            http_response_code(400);
            echo json_encode(["status"=>"error","message"=>"menu_id gerekli"]);
            exit;
        }

        if ($rol === "restoran") {
            $stmt = $pdo->prepare("UPDATE menuler SET ad=?, aciklama=?, fiyat=? WHERE menu_id=? AND restoran_id=?");
            $stmt->execute([
                $input['ad'] ?? null,
                $input['aciklama'] ?? null,
                $input['fiyat'] ?? null,
                $menu_id,
                $restoran_id_token
            ]);
        } elseif ($rol === "admin") {
            $stmt = $pdo->prepare("UPDATE menuler SET ad=?, aciklama=?, fiyat=?, restoran_id=? WHERE menu_id=?");
            $stmt->execute([
                $input['ad'] ?? null,
                $input['aciklama'] ?? null,
                $input['fiyat'] ?? null,
                $input['restoran_id'] ?? null,
                $menu_id
            ]);
        } else {
            http_response_code(403);
            echo json_encode(["status"=>"error","message"=>"Yetkiniz yok"]);
            exit;
        }

        echo json_encode(["status"=>"success","message"=>"Menü güncellendi"]);

    } elseif ($method === "DELETE") {
        $menu_id = $_GET['id'] ?? null;
        if (!$menu_id) {
            http_response_code(400);
            echo json_encode(["status"=>"error","message"=>"menu_id gerekli"]);
            exit;
        }

        if ($rol === "restoran") {
            $stmt = $pdo->prepare("DELETE FROM menuler WHERE menu_id=? AND restoran_id=?");
            $stmt->execute([$menu_id, $restoran_id_token]);
        } elseif ($rol === "admin") {
            $stmt = $pdo->prepare("DELETE FROM menuler WHERE menu_id=?");
            $stmt->execute([$menu_id]);
        } else {
            http_response_code(403);
            echo json_encode(["status"=>"error","message"=>"Yetkiniz yok"]);
            exit;
        }

        echo json_encode(["status"=>"success","message"=>"Menü silindi"]);

    } else {
        http_response_code(405);
        echo json_encode(["status"=>"error","message"=>"Metod desteklenmiyor"]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
}
?>
