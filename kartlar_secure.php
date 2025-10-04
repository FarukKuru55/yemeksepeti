<?php
// kartlar_secure.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . "/Api/db.php";
require_once __DIR__ . "/vendor/autoload.php";
$config = require_once __DIR__ . "/config.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Helper: Bearer token al
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

// OPTIONS ön uç preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// JWT doğrulama
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

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === "GET") {
        // ?id= kart_id veya ?musteri_id= gibi parametreler
        $kart_id = isset($_GET['id']) ? intval($_GET['id']) : null;
        $q_musteri = isset($_GET['musteri_id']) ? intval($_GET['musteri_id']) : null;

        if ($kart_id) {
            // Tek kart: sahibi ise veya admin/super_admin ise görebilir
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

        // Eğer musterı_id verilmişse ve token sahibi değilse sadece admin görebilir
        if ($q_musteri !== null) {
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

        // Default: normal kullanıcı kendi kartlarını görür; admin görebilir tüm kartları
        if (in_array($rol, ['admin','super_admin']) && isset($_GET['all']) && $_GET['all'] == '1') {
            $stmt = $pdo->query("SELECT kart_id, musteri_id, last4, card_brand, exp_month, exp_year, created_at FROM kartlar_secure ORDER BY kart_id ASC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["status"=>"success","data"=>$rows], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // normal kullanıcı: sadece kendi kartları
        $stmt = $pdo->prepare("SELECT kart_id, musteri_id, last4, card_brand, exp_month, exp_year, created_at FROM kartlar_secure WHERE musteri_id=? ORDER BY kart_id ASC");
        $stmt->execute([$token_musteri_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["status"=>"success","data"=>$rows], JSON_UNESCAPED_UNICODE);
        exit;

    } elseif ($method === "POST") {
        // Yeni kart ekle (frontend'den provider_token gelmeli)
        $input = json_decode(file_get_contents("php://input"), true);
        if (!is_array($input)) { http_response_code(400); echo json_encode(["status"=>"error","message"=>"Geçersiz JSON"]); exit; }

        // Normal kullanıcı kendi hesabına kart ekler; admin token'ı ile musteri_id verilebilir.
        $target_musteri_id = $token_musteri_id;
        if (isset($input['musteri_id']) && in_array($rol, ['admin','super_admin'])) {
            $target_musteri_id = intval($input['musteri_id']);
        }

        // Gerekli alanlar
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
            echo json_encode(["status"=>"success","message"=>"Kart eklendi","id"=>$pdo->lastInsertId()]);
        } else {
            http_response_code(500);
            echo json_encode(["status"=>"error","message"=>"Kart eklenemedi"]);
        }
        exit;

    } elseif ($method === "DELETE") {
        // Kart sil (sadece sahibi veya admin)
        parse_str(file_get_contents("php://input"), $input);
        $kart_id = isset($input['kart_id']) ? intval($input['kart_id']) : null;
        if (!$kart_id) { http_response_code(400); echo json_encode(["status"=>"error","message"=>"kart_id gerekli"]); exit; }

        $stmt = $pdo->prepare("SELECT musteri_id, provider_token FROM kartlar_secure WHERE kart_id=?");
        $stmt->execute([$kart_id]);
        $card = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$card) { http_response_code(404); echo json_encode(["status"=>"error","message"=>"Kart bulunamadı"]); exit; }

        // yetki kontrolü
        if ($card['musteri_id'] != $token_musteri_id && !in_array($rol, ['admin','super_admin'])) {
            http_response_code(403);
            echo json_encode(["status"=>"error","message"=>"Yetkiniz yok"]);
            exit;
        }

        // (Opsiyonel) provider üzerinde token silme isteği atılabilir -- burada sadece DB'den siliyoruz.
        $stmt = $pdo->prepare("DELETE FROM kartlar_secure WHERE kart_id=?");
        $ok = $stmt->execute([$kart_id]);

        if ($ok) {
            echo json_encode(["status"=>"success","message"=>"Kart silindi"]);
        } else {
            http_response_code(500);
            echo json_encode(["status"=>"error","message"=>"Silme başarısız"]);
        }
        exit;
    }

    // Diğer methodlara izin verme
    http_response_code(405);
    echo json_encode(["status"=>"error","message"=>"Geçersiz method"]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
}
