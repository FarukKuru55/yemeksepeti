<?php
// ✅ menuler.php - GÜNCELLENMİŞ SÜRÜM (403 Tünelleme Çözümü + Dinamik PUT)

require_once __DIR__ . "/Api/db.php";
require_once __DIR__ . "/vendor/autoload.php";
$config = require_once __DIR__ . "/config.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// --- CORS Başlıkları ---
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Sadece GET, POST, OPTIONS
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
    $restoran_id_token = $decoded->restoran_id ?? null; // Restoran rolü için
    if (!$rol) throw new Exception("Token geçersiz");
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(["status"=>"error","message"=>"Geçersiz token","details"=>$e->getMessage()]);
    exit;
}
// --- TOKEN SONU ---


$method = $_SERVER['REQUEST_METHOD'];

try {
    // 🔹 GET (Okuma) - Aynen kalıyor
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

    // 🔹 POST, PUT, DELETE İÇİN TEK BLOK (YENİ GÜNCELLEME)
    } elseif ($method === "POST") {

        // Gelen JSON verisini oku
        $input = json_decode(file_get_contents("php://input"), true);
        
        // Hangi eylemi yapacağımızı belirle (Tünelleme)
        $action = $input['_method'] ?? 'POST'; // Eğer _method yoksa, normal Ekleme (POST) varsay

        // --- 1. EYLEM: GÜNCELLEME (Eski PUT) ---
        // !!!!!!!!!!!!!!!!!!
        // !!! BAŞLANGIÇ: HATA DÜZELTMESİ BURADA (DİNAMİK PUT)
        // !!!!!!!!!!!!!!!!!!
        if ($action === 'PUT') {
            if ($rol !== "restoran" && $rol !== "admin") {
                http_response_code(403);
                echo json_encode(["status"=>"error","message"=>"Güncelleme için yetkiniz yok"]);
                exit;
            }

            $menu_id = $input['menu_id'] ?? null;
            if (!$menu_id) {
                http_response_code(400);
                echo json_encode(["status"=>"error","message"=>"menu_id gerekli"]);
                exit;
            }

            // --- DİNAMİK SORGULAMA BAŞLANGICI ---
            // Sadece gönderilen alanları güncellemek için
            $fieldsToUpdate = [];
            $params = [];

            if (isset($input['ad'])) {
                $fieldsToUpdate[] = "ad = ?";
                $params[] = $input['ad'];
            }
            if (isset($input['aciklama'])) {
                $fieldsToUpdate[] = "aciklama = ?";
                $params[] = $input['aciklama'];
            }
            if (isset($input['fiyat'])) {
                $fieldsToUpdate[] = "fiyat = ?";
                $params[] = $input['fiyat'];
            }

            // Sadece Admin 'restoran_id'yi değiştirebilir
            // Ve sadece bu alan gönderilmişse sorguya ekle
            if ($rol === "admin" && isset($input['restoran_id'])) {
                $fieldsToUpdate[] = "restoran_id = ?";
                $params[] = $input['restoran_id'];
            }
            
            if (count($fieldsToUpdate) === 0) {
                 throw new Exception("Güncellenecek en az bir alan (ad, fiyat vb.) gönderilmelidir.", 400);
            }

            // Sorguyu dinamik olarak oluştur
            $sql = "UPDATE menuler SET " . implode(", ", $fieldsToUpdate) . " WHERE menu_id = ?";
            $params[] = $menu_id; 

            // Restoran ise, güvenlik için kendi ID'sini de ekle (sadece kendi menüsünü güncelleyebilir)
            if ($rol === "restoran") {
                $sql .= " AND restoran_id = ?";
                $params[] = $restoran_id_token;
            }
            // --- DİNAMİK SORGULAMA SONU ---

            $stmt = $pdo->prepare($sql);
            $ok = $stmt->execute($params);

            echo json_encode(["status"=>"success","message"=>"Menü güncellendi"]);
        // !!!!!!!!!!!!!!!!!!
        // !!! BİTİŞ: HATA DÜZELTMESİ BURADA
        // !!!!!!!!!!!!!!!!!!
        
        // --- 2. EYLEM: SİLME (Eski DELETE) ---
        } elseif ($action === 'DELETE') {
            if ($rol !== "restoran" && $rol !== "admin") {
                http_response_code(403);
                echo json_encode(["status"=>"error","message"=>"Silme için yetkiniz yok"]);
                exit;
            }

            $menu_id = $input['menu_id'] ?? null; 
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
            }
            echo json_encode(["status"=>"success","message"=>"Menü silindi"]);

        // --- 3. EYLEM: EKLEME (Normal POST) ---
        } else {
            if ($rol !== "restoran" && $rol !== "admin") {
                http_response_code(403);
                echo json_encode(["status"=>"error","message"=>"Ekleme için yetkiniz yok"]);
                exit;
            }
            
            if (!isset($input['ad'], $input['aciklama'], $input['fiyat'])) {
                http_response_code(400);
                echo json_encode(["status"=>"error","message"=>"Eksik alan (ad, aciklama, fiyat)"]);
                exit;
            }

            $restoran_id = ($rol === "restoran") ? $restoran_id_token : ($input['restoran_id'] ?? null);
            if (!$restoran_id) {
                http_response_code(400);
                echo json_encode(["status"=>"error","message"=>"Admin rolü için restoran_id gerekli"]);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO menuler (restoran_id, ad, aciklama, fiyat) VALUES (?, ?, ?, ?)");
            $stmt->execute([$restoran_id, $input['ad'], $input['aciklama'], $input['fiyat']]);
            echo json_encode(["status"=>"success","message"=>"Menü eklendi","menu_id"=>$pdo->lastInsertId()]);
        }

    } else {
        http_response_code(405);
        echo json_encode(["status"=>"error","message"=>"Metod desteklenmiyor"]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
}
?>
