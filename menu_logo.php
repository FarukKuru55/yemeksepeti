<?php
// menu_logo.php
// Bu API, menü öğelerine resim (logo) yükler veya siler.
// JSON değil, multipart/form-data bekler.

ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- Gerekli Dosyalar ---
require_once __DIR__ . "/Api/db.php";
require_once __DIR__ . "/vendor/autoload.php";
$config = require __DIR__ . "/config.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// --- CORS ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Preflight kontrol
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header("Content-Type: application/json; charset=UTF-8");

// --- Bearer Token alma fonksiyonu ---
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

// --- MIME türleri (güvenlik için) ---
$mime_to_ext = [
    'image/jpeg' => 'jpg',
    'image/pjpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

try {
    // --- 1. Token kontrolü ---
    $token = getBearerToken();
    if (!$token) throw new Exception("Token gerekli", 401);

    try {
        $decoded = JWT::decode($token, new Key($config['jwt_secret'], 'HS256'));
    } catch (Throwable $je) {
        throw new Exception("Geçersiz token: " . $je->getMessage(), 401);
    }

    $rol = $decoded->rol ?? null;
    $restoran_id_token = $decoded->restoran_id ?? null;

    if (!in_array($rol, ['admin', 'restoran'])) {
        throw new Exception("Yetkisiz işlem", 403);
    }

    // --- 2. Girdi ---
    $input = $_POST;
    $action = strtoupper($input['_method'] ?? 'POST'); // POST veya DELETE

    // --- 3. Dosya yolu ayarları ---
    $UPLOAD_DIR = __DIR__ . "/img/menu_resimleri/";
    if (!is_dir($UPLOAD_DIR) && !mkdir($UPLOAD_DIR, 0755, true)) {
        throw new Exception("Yükleme klasörü oluşturulamadı.", 500);
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $RESIM_BASE_URL = "{$protocol}://{$host}{$basePath}/img/menu_resimleri/";

    // Menü ID kontrol fonksiyonu
    $validate_menu_id = fn($id) => isset($id) && preg_match('/^\d+$/', $id);

    // --- 4. İşlem türü ---
    if ($action === 'POST') {
        // 🔹 RESİM EKLE veya GÜNCELLE
        if (empty($input['menu_id']) || !isset($_FILES['resim'])) {
            throw new Exception("menu_id ve 'resim' zorunludur.", 400);
        }

        $menu_id = $input['menu_id'];
        if (!$validate_menu_id($menu_id)) throw new Exception("Geçersiz menu_id.", 400);

        $MAX_BYTES = 5 * 1024 * 1024;
        $file = $_FILES['resim'];

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception("Yüklenen dosya hatalı.", 400);
        }

        if ($file['size'] > $MAX_BYTES) throw new Exception("Dosya çok büyük (max 5MB).", 400);

        $mime = mime_content_type($file['tmp_name']) ?: $file['type'] ?? null;
        if (!$mime || !isset($mime_to_ext[$mime])) {
            throw new Exception("Geçersiz format. Sadece JPG, PNG, WEBP destekleniyor.", 400);
        }

        $ext = $mime_to_ext[$mime];
        $yeni_ad = uniqid('menu_', true) . ".$ext";
        $hedef_yol = $UPLOAD_DIR . $yeni_ad;

        // Mevcut resmi bul
        $stmt = $pdo->prepare("SELECT resim_url, restoran_id FROM menuler WHERE menu_id = ?");
        $stmt->execute([$menu_id]);
        $kayit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$kayit) throw new Exception("Menü bulunamadı.", 404);
        if ($rol === 'restoran' && $kayit['restoran_id'] != $restoran_id_token)
            throw new Exception("Bu menüyü düzenleme yetkiniz yok.", 403);

        // Dosyayı taşı
        if (!move_uploaded_file($file['tmp_name'], $hedef_yol))
            throw new Exception("Dosya yükleme hatası.", 500);

        // DB güncelle
        $sql = "UPDATE menuler SET resim_url=? WHERE menu_id=?";
        $params = [$yeni_ad, $menu_id];
        if ($rol === 'restoran') {
            $sql .= " AND restoran_id=?";
            $params[] = $restoran_id_token;
        }
        $upd = $pdo->prepare($sql);
        $upd->execute($params);

        if ($upd->rowCount() > 0) {
            // Eski resmi sil
            if (!empty($kayit['resim_url']) && file_exists($UPLOAD_DIR . $kayit['resim_url'])) {
                @unlink($UPLOAD_DIR . $kayit['resim_url']);
            }
            echo json_encode([
                "status" => "success",
                "message" => "Menü logosu güncellendi",
                "resim_url" => $RESIM_BASE_URL . $yeni_ad
            ]);
        } else {
            @unlink($hedef_yol);
            throw new Exception("Veritabanı güncellenemedi.", 500);
        }

    } elseif ($action === 'DELETE') {
        // 🔹 RESMİ SİL
        if (empty($input['menu_id'])) throw new Exception("menu_id zorunludur.", 400);

        $menu_id = $input['menu_id'];
        if (!$validate_menu_id($menu_id)) throw new Exception("Geçersiz menu_id.", 400);

        $stmt = $pdo->prepare("SELECT resim_url, restoran_id FROM menuler WHERE menu_id=?");
        $stmt->execute([$menu_id]);
        $kayit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$kayit) throw new Exception("Menü bulunamadı.", 404);
        if ($rol === 'restoran' && $kayit['restoran_id'] != $restoran_id_token)
            throw new Exception("Bu menüyü silme yetkiniz yok.", 403);

        $sql = "UPDATE menuler SET resim_url=NULL WHERE menu_id=?";
        $params = [$menu_id];
        if ($rol === 'restoran') {
            $sql .= " AND restoran_id=?";
            $params[] = $restoran_id_token;
        }
        $del = $pdo->prepare($sql);
        $del->execute($params);

        if ($del->rowCount() > 0) {
            if (!empty($kayit['resim_url']) && file_exists($UPLOAD_DIR . $kayit['resim_url'])) {
                @unlink($UPLOAD_DIR . $kayit['resim_url']);
            }
            echo json_encode(["status" => "success", "message" => "Menü logosu silindi."]);
        } else {
            throw new Exception("İşlem başarısız. Menü bulunamadı veya size ait değil.", 404);
        }

    } else {
        throw new Exception("Geçersiz _method parametresi.", 400);
    }

} catch (Exception $e) {
    http_response_code(($e->getCode() >= 400 && $e->getCode() <= 599) ? $e->getCode() : 500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
