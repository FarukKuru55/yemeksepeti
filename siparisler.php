<?php
// siparisler.php - GÜNCELLENMİŞ SÜRÜM (Metod Tünelleme Eklendi)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/Api/db.php";
require_once __DIR__ . "/vendor/autoload.php";
$config = require __DIR__ . "/config.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// === CORS BAŞLIKLARI (DEĞİŞTİ) ===
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
// API'miz artık PUT/DELETE'i doğrudan kabul etmiyor, sadece POST üzerinden tünellemeyi kabul ediyor.
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- TOKEN FONKSİYONU (Aynen kaldı) ---
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
    $restoran_id_token = $decoded->restoran_id ?? null;
    if (!$rol) throw new Exception("Token geçersiz");
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(["status"=>"error","message"=>"Geçersiz token","details"=>$e->getMessage()]);
    exit;
}
// --- TOKEN SONU ---

// --- CRUD ---
$method = $_SERVER['REQUEST_METHOD'];

try {
    // ----------------------------------------------------
    // 🔹 METOD: GET (Sipariş(ler)i Görüntüle)
    // ----------------------------------------------------
    if ($method === "GET") {
        
        // (Bu blokta hiçbir değişiklik yok, zaten mükemmeldi)
        
        $siparis_id = $_GET['id'] ?? null;

        if ($rol === "musteri") {
            // Müşteri sadece kendi siparişlerini görebilir
            if ($siparis_id) {
                $stmt = $pdo->prepare("SELECT * FROM siparisler WHERE siparis_id=? AND musteri_id=?");
                $stmt->execute([$siparis_id, $musteri_id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM siparisler WHERE musteri_id=? ORDER BY siparis_id DESC");
                $stmt->execute([$musteri_id]);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

        } elseif ($rol === "restoran") {
            // Restoran sahibi sadece kendi restoranına ait siparişleri görebilir
            if ($siparis_id) {
                $stmt = $pdo->prepare("SELECT * FROM siparisler WHERE siparis_id=? AND restoran_id=?");
                $stmt->execute([$siparis_id, $restoran_id_token]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM siparisler WHERE restoran_id=? ORDER BY siparis_id DESC");
                $stmt->execute([$restoran_id_token]);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

        } elseif ($rol === "admin") {
            // Admin tüm siparişleri görebilir
            if ($siparis_id) {
                $stmt = $pdo->prepare("SELECT * FROM siparisler WHERE siparis_id=?");
                $stmt->execute([$siparis_id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->query("SELECT * FROM siparisler ORDER BY siparis_id DESC");
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

        } else {
            http_response_code(403);
            echo json_encode(["status"=>"error","message"=>"Yetkiniz yok"]);
            exit;
        }

        if (!$data) {
            http_response_code(404);
            echo json_encode(["status"=>"error","message"=>"Sipariş bulunamadı"]);
        } else {
            echo json_encode(["status"=>"success","data"=>$data], JSON_UNESCAPED_UNICODE);
        }

    // ----------------------------------------------------
    // 🔹 METOD: POST (Sipariş Ekle, Güncelle, Sil) (YAPI DEĞİŞTİ)
    // ----------------------------------------------------
    } elseif ($method === "POST") {
        
        // Veriyi JSON olarak oku (Tüm eylemler için)
        $input = json_decode(file_get_contents("php://input"), true);
        if ($input === null) {
             http_response_code(400);
             echo json_encode(["status"=>"error","message"=>"Geçersiz JSON verisi"]);
             exit;
        }

        // Tünelleme: Hangi eylemi yapacağız?
        $action = $input['_method'] ?? 'POST';

        // -------------------------------
        // 🔹 EYLEM: POST (Yeni Sipariş Ekle)
        // -------------------------------
        if ($action === 'POST') {
            
            // (Orijinal POST kodun)
            if (!isset($input['restoran_id'], $input['toplam_tutar'])) {
                http_response_code(400);
                echo json_encode(["status"=>"error","message"=>"Eksik alan (restoran_id, toplam_tutar)"]);
                exit;
            }

            // GÜVENLİ: musteri_id token'dan geliyor
            $stmt = $pdo->prepare("INSERT INTO siparisler (musteri_id, restoran_id, toplam_tutar, durum, tarih) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([
                $musteri_id,
                $input['restoran_id'],
                $input['toplam_tutar'],
                $input['durum'] ?? 'hazırlanıyor'
            ]);

            http_response_code(201);
            echo json_encode(["status"=>"success","message"=>"Sipariş eklendi","siparis_id"=>$pdo->lastInsertId()]);
        
        // -------------------------------
        // 🔹 EYLEM: PUT (Sipariş Güncelle) (ESKİ PUT KODU BURAYA TAŞINDI)
        // -------------------------------
        } elseif ($action === 'PUT') {
            
            // (Orijinal PUT kodun)
            if (!isset($input['siparis_id'])) {
                http_response_code(400);
                echo json_encode(["status"=>"error","message"=>"JSON body içinde siparis_id gerekli"]);
                exit;
            }

            if ($rol === "musteri") {
                if (($input['durum'] ?? null) !== 'iptal') {
                    http_response_code(403);
                    echo json_encode(["status"=>"error","message"=>"Müşteri sadece siparişi iptal edebilir"]);
                    exit;
                }
                $stmt = $pdo->prepare("UPDATE siparisler SET durum='iptal' WHERE siparis_id=? AND musteri_id=?");
                $stmt->execute([$input['siparis_id'], $musteri_id]);

            } elseif ($rol === "restoran") {
                $stmt = $pdo->prepare("UPDATE siparisler SET durum=COALESCE(?, durum) WHERE siparis_id=? AND restoran_id=?");
                $stmt->execute([$input['durum'] ?? null, $input['siparis_id'], $restoran_id_token]);

            } elseif ($rol === "admin") {
                $stmt = $pdo->prepare("UPDATE siparisler SET durum=COALESCE(?, durum) WHERE siparis_id=?");
                $stmt->execute([$input['durum'] ?? null, $input['siparis_id']]);
            } else {
                http_response_code(403);
                echo json_encode(["status"=>"error","message"=>"Yetkiniz yok"]);
                exit;
            }

            echo json_encode(["status"=>"success","message"=>"Sipariş güncellendi"]);

        // -------------------------------
        // 🔹 EYLEM: DELETE (Sipariş Sil) (ESKİ DELETE KODU BURAYA TAŞINDI)
        // -------------------------------
        } elseif ($action === 'DELETE') {
            
            // (Orijinal DELETE kodun)
            if ($rol !== "admin") {
                http_response_code(403);
                echo json_encode(["status"=>"error","message"=>"Sadece admin siparişi silebilir"]);
                exit;
            }

            // (DEĞİŞTİ: ID artık JSON'dan ($input) alınıyor)
            $siparis_id = $input['siparis_id'] ?? null;
            if (!$siparis_id) {
                http_response_code(400);
                echo json_encode(["status"=>"error","message"=>"JSON body içinde siparis_id gerekli"]);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM siparisler WHERE siparis_id=?");
            $stmt->execute([$siparis_id]);

            echo json_encode(["status"=>"success","message"=>"Sipariş silindi"]);
        
        } else {
            // Eğer _method POST, PUT, DELETE dışında bir şeyse
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Geçersiz '_method' eylemi."]);
        }

    } else {
        // GET veya POST dışındaki tüm metodları reddet
        http_response_code(405);
        echo json_encode(["status"=>"error","message"=>"Sadece GET ve POST metotları desteklenmektedir."]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
}
?>  