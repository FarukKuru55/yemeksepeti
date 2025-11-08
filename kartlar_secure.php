<?php
// kartlar_secure.php - GÜNCELLENMİŞ SÜRÜM (Metod Tünelleme Eklendi)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/Api/db.php";
require_once __DIR__ . "/vendor/autoload.php";
$config = require_once __DIR__ . "/config.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// === CORS BAŞLIKLARI (DEĞİŞTİ) ===
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
// API'miz artık PUT/DELETE'i doğrudan kabul etmiyor
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

// --- TOKEN DOĞRULAMA (Aynen kaldı) ---
$token = getBearerToken();
if (!$token) {
    http_response_code(401);
    echo json_encode(["status"=>"error","message"=>"Token gerekli"]);
    exit;
}
try {
    $decoded = JWT::decode($token, new Key($config['jwt_secret'], 'HS256'));
    $rol = $decoded->rol ?? null;
    $token_musteri_id = $decoded->sub ?? null;
    if (!$rol || !$token_musteri_id) throw new Exception("Token eksik veya geçersiz");
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(["status"=>"error","message"=>"Geçersiz token","details"=>$e->getMessage()]);
    exit;
}
// --- TOKEN SONU ---

$method = $_SERVER['REQUEST_METHOD'];

try {
    // ----------------------------------------------------
    // 🔹 METOD: GET (Kart(lar)ı Görüntüle)
    // ----------------------------------------------------
    if ($method === "GET") {
        
        // (Orijinal GET kodun - Mükemmel çalışıyor, değişiklik yok)
        
        $kart_id = isset($_GET['id']) ? intval($_GET['id']) : null;
        $q_musteri = isset($_GET['musteri_id']) ? intval($_GET['musteri_id']) : null;

        if ($kart_id) {
            // ... (Tek kart getirme mantığı) ...
            $stmt = $pdo->prepare("SELECT kart_id, musteri_id, provider_token, last4, card_brand, exp_month, exp_year, created_at FROM kartlar_secure WHERE kart_id=?");
            $stmt->execute([$kart_id]);
            $card = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$card) {
                http_response_code(404);
                echo json_encode(["status"=>"error","message"=>"Kart bulunamadı"]);
                exit;
            }
            if ($card['musteri_id'] != $token_musteri_id && !in_array($rol, ['admin','super_admin'])) {
                http_response_code(403);
                echo json_encode(["status"=>"error","message"=>"Yetkiniz yok"]);
                exit;
            }
            echo json_encode(["status"=>"success","data"=>$card], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($q_musteri !== null) {
            // ... (Başka müşterinin kartlarını (admin) getirme mantığı) ...
            if ($q_musteri != $token_musteri_id && !in_array($rol, ['admin','super_admin'])) {
                http_response_code(403);
                echo json_encode(["status"=>"error","message"=>"Yetkiniz yok"]);
                exit;
            }
            $stmt = $pdo->prepare("SELECT kart_id, musteri_id, last4, card_brand, exp_month, exp_year, created_at FROM kartlar_secure WHERE musteri_id=? ORDER BY kart_id ASC");
            $stmt->execute([$q_musteri]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["status"=>"success","data"=>$rows], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (in_array($rol, ['admin','super_admin']) && isset($_GET['all']) && $_GET['all'] == '1') {
            // ... (Admin tüm kartları getirme mantığı) ...
            $stmt = $pdo->query("SELECT kart_id, musteri_id, last4, card_brand, exp_month, exp_year, created_at FROM kartlar_secure ORDER BY kart_id ASC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["status"=>"success","data"=>$rows], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // ... (Default: Müşteri kendi kartlarını getirir) ...
        $stmt = $pdo->prepare("SELECT kart_id, musteri_id, last4, card_brand, exp_month, exp_year, created_at FROM kartlar_secure WHERE musteri_id=? ORDER BY kart_id ASC");
        $stmt->execute([$token_musteri_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["status"=>"success","data"=>$rows], JSON_UNESCAPED_UNICODE);
        exit;

    // ----------------------------------------------------
    // 🔹 METOD: POST (Kart Ekle, Sil) (YAPI DEĞİŞTİ)
    // ----------------------------------------------------
    } elseif ($method === "POST") {

        // Tüm eylemler için JSON verisini oku
        $input = json_decode(file_get_contents("php://input"), true);
        if ($input === null) {
             http_response_code(400);
             echo json_encode(["status"=>"error","message"=>"Geçersiz JSON verisi"]);
             exit;
        }
        
        // Tünelleme: Hangi eylemi yapacağız?
        $action = $input['_method'] ?? 'POST';

        // -------------------------------
        // 🔹 EYLEM: POST (Yeni Kart Ekle)
        // -------------------------------
        if ($action === 'POST') {
            
            // (Orijinal POST kodun - Zaten JSON bekliyordu)
            
            $target_musteri_id = $token_musteri_id;
            if (isset($input['musteri_id']) && in_array($rol, ['admin','super_admin'])) {
                $target_musteri_id = intval($input['musteri_id']);
            }

            if (!isset($input['provider_token'], $input['last4'])) {
                http_response_code(400);
                echo json_encode(["status"=>"error","message"=>"provider_token ve last4 gerekli"]);
                exit;
            }

            $provider_token = $input['provider_token'];
            $last4 = preg_replace('/\D/', '', substr($input['last4'], -4));
            $card_brand = $input['card_brand'] ?? null;
            $exp_month = isset($input['exp_month']) ? intval($input['exp_month']) : null;
            $exp_year = isset($input['exp_year']) ? intval($input['exp_year']) : null;

            $stmt = $pdo->prepare("INSERT INTO kartlar_secure (musteri_id, provider_token, last4, card_brand, exp_month, exp_year) VALUES (?, ?, ?, ?, ?, ?)");
            $ok = $stmt->execute([$target_musteri_id, $provider_token, $last4, $card_brand, $exp_month, $exp_year]);

            if ($ok) {
                http_response_code(201);
                echo json_encode(["status"=>"success","message"=>"Kart eklendi","id"=>$pdo->lastInsertId()]);
            } else {
                http_response_code(500);
                echo json_encode(["status"=>"error","message"=>"Kart eklenemedi"]);
            }
            exit;

        // -------------------------------
        // 🔹 EYLEM: DELETE (Kart Sil) (ESKİ DELETE KODU BURAYA TAŞINDI)
        // -------------------------------
        } elseif ($action === 'DELETE') {

            // (Orijinal DELETE kodun - DEĞİŞTİ: Artık JSON'dan okuyor)
            
            // DEĞİŞTİ: 'parse_str' kaldırıldı, $input zaten JSON'dan geliyor.
            $kart_id = $input['kart_id'] ?? null;
            if (!$kart_id) { 
                http_response_code(400); 
                echo json_encode(["status"=>"error","message"=>"JSON body içinde kart_id gerekli"]); 
                exit; 
            }

            $stmt = $pdo->prepare("SELECT musteri_id, provider_token FROM kartlar_secure WHERE kart_id=?");
            $stmt->execute([$kart_id]);
            $card = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$card) { http_response_code(404); echo json_encode(["status"=>"error","message"=>"Kart bulunamadı"]); exit; }

            // Yetki kontrolü (Aynen kaldı, mükemmeldi)
            if ($card['musteri_id'] != $token_musteri_id && !in_array($rol, ['admin','super_admin'])) {
                http_response_code(403);
                echo json_encode(["status"=>"error","message"=>"Yetkiniz yok"]);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM kartlar_secure WHERE kart_id=?");
            $ok = $stmt->execute([$kart_id]);

            if ($ok) {
                echo json_encode(["status"=>"success","message"=>"Kart silindi"]);
            } else {
                http_response_code(500);
                echo json_encode(["status"=>"error","message"=>"Silme başarısız"]);
            }
            exit;
        
        } else {
             http_response_code(400);
             echo json_encode(["status" => "error", "message" => "Geçersiz '_method' eylemi."]);
        }

    } else {
        http_response_code(405);
        echo json_encode(["status"=>"error","message"=>"Sadece GET ve POST metotları desteklenmektedir."]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
}
?>