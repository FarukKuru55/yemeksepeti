<?php
// === HATA AYIKLAMA ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// === CORS ===
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// === GEREKLİ DOSYALAR ===
require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/Api/db.php";
$jwtAyarlari = require __DIR__ . "/config.php";

use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// === TOKEN FONKSİYONU ===
function get_user_data_from_token($secret_key)
{
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    if (!$authHeader) throw new Exception("Authorization başlığı eksik.", 401);
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
    } else {
        throw new Exception("Token formatı geçersiz.", 401);
    }
    try {
        $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
        return (array)$decoded;
    } catch (ExpiredException $e) {
        throw new Exception("Oturum süresi doldu.", 401);
    } catch (SignatureInvalidException $e) {
        throw new Exception("Geçersiz token imzası.", 401);
    } catch (Exception $e) {
        throw new Exception("Token çözümlenemedi: " . $e->getMessage(), 401);
    }
}

// === LOGO YÜKLEME DİZİNİ ===
$UPLOAD_DIR = __DIR__ . "/img/restoran_logolari/";
if (!is_dir($UPLOAD_DIR)) mkdir($UPLOAD_DIR, 0755, true);

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$project_path = dirname($_SERVER['SCRIPT_NAME']);
$project_path = ($project_path == '/' || $project_path == '\\') ? '' : $project_path;
$LOGO_BASE_URL = $protocol . "://" . $host . $project_path . "/img/restoran_logolari/";

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        // 🔹 GET (listeleme)
        case "GET":
            $id = $_GET['restoran_id'] ?? $_GET['id'] ?? null;
            $cols = "restoran_id, ad, kategori_id, adres, telefon, puan, acilis_saati, kapanis_saati, email, logo_url";
            if ($id) {
                $stmt = $pdo->prepare("SELECT $cols FROM restoranlar WHERE restoran_id = ?");
                $stmt->execute([$id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($data && !empty($data['logo_url'])) {
                    $data['logo_url'] = $LOGO_BASE_URL . basename($data['logo_url']);
                }
                echo json_encode(["status" => "success", "data" => $data ?: null], JSON_UNESCAPED_UNICODE);
            } else {
                $stmt = $pdo->query("SELECT $cols FROM restoranlar ORDER BY restoran_id ASC");
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($data as &$r) {
                    $r['logo_url'] = !empty($r['logo_url']) ? $LOGO_BASE_URL . basename($r['logo_url']) : null;
                }
                echo json_encode(["status" => "success", "data" => $data], JSON_UNESCAPED_UNICODE);
            }
            break;

        // 🔹 POST (ekleme / güncelleme / silme)
        case "POST":
            // 🔒 Token kontrolü
            $kullanici = get_user_data_from_token($jwtAyarlari['jwt_secret']);
            if ($kullanici['rol'] !== 'admin') {
                http_response_code(403);
                echo json_encode(["status" => "error", "message" => "Sadece adminler işlem yapabilir."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $input = $_POST;
            $action = $input['_method'] ?? 'POST';

            // 🔸 EKLEME (POST)
            if ($action === 'POST') {
                if (empty($input['ad']) || empty($input['adres']) || empty($input['email']) || empty($input['sifre']) || empty($input['telefon'])) {
                    throw new Exception("Zorunlu alanlar eksik (ad, adres, email, sifre, telefon).", 400);
                }

                // LOGO YÜKLEME
                $logo_dosya_adi = null;
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
                    $mime = mime_content_type($_FILES['logo']['tmp_name']);
                    $izinli_tipler = ['image/jpeg', 'image/png', 'image/webp'];
                    if (!in_array($mime, $izinli_tipler)) throw new Exception("Geçersiz dosya formatı (sadece JPG, PNG, WEBP).");
                    $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                    $yeni_ad = uniqid('logo_') . "." . $ext;
                    $hedef_yol = $UPLOAD_DIR . $yeni_ad;
                    if (!move_uploaded_file($_FILES['logo']['tmp_name'], $hedef_yol)) throw new Exception("Logo yükleme başarısız.");
                    $logo_dosya_adi = $yeni_ad;
                }

                $sifre_hash = password_hash($input['sifre'], PASSWORD_DEFAULT);
                $sql = "INSERT INTO restoranlar (ad, kategori_id, adres, telefon, puan, acilis_saati, kapanis_saati, email, sifre, logo_url)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $ok = $stmt->execute([
                    $input['ad'],
                    $input['kategori_id'] ?? 1,
                    $input['adres'],
                    $input['telefon'],
                    $input['puan'] ?? 0.0,
                    $input['acilis_saati'] ?? '09:00:00',
                    $input['kapanis_saati'] ?? '22:00:00',
                    $input['email'],
                    $sifre_hash,
                    $logo_dosya_adi
                ]);

                if ($ok) {
                    http_response_code(201);
                    echo json_encode([
                        "status" => "success",
                        "message" => "Restoran eklendi.",
                        "restoran_id" => $pdo->lastInsertId(),
                        "logo_url" => $logo_dosya_adi ? $LOGO_BASE_URL . $logo_dosya_adi : null
                    ], JSON_UNESCAPED_UNICODE);
                } else {
                    throw new Exception("Veritabanı hatası oluştu.");
                }
            }

            // 🔸 GÜNCELLEME (PUT)
            else if ($action === 'PUT') {
                $id = $input['restoran_id'] ?? null;
                if (!$id) throw new Exception("Güncelleme için 'restoran_id' gerekli.", 400);

                $fields = [];
                $params = [];

                foreach (['ad', 'adres', 'telefon', 'email', 'puan', 'acilis_saati', 'kapanis_saati', 'kategori_id'] as $col) {
                    if (isset($input[$col])) {
                        $fields[] = "$col = ?";
                        $params[] = $input[$col];
                    }
                }

                if (count($fields) === 0) throw new Exception("Güncellenecek alan belirtilmedi.", 400);
                $params[] = $id;

                $stmt = $pdo->prepare("UPDATE restoranlar SET " . implode(", ", $fields) . " WHERE restoran_id = ?");
                $ok = $stmt->execute($params);

                echo json_encode([
                    "status" => $ok ? "success" : "error",
                    "message" => $ok ? "Restoran güncellendi." : "Güncelleme başarısız."
                ], JSON_UNESCAPED_UNICODE);
            }

            // 🔸 SİLME (DELETE)
            else if ($action === 'DELETE') {
                $id = $input['restoran_id'] ?? null;
                if (!$id) throw new Exception("Silme için 'restoran_id' gerekli.", 400);

                // Önce logoyu sil
                $stmt = $pdo->prepare("SELECT logo_url FROM restoranlar WHERE restoran_id = ?");
                $stmt->execute([$id]);
                $logo = $stmt->fetchColumn();
                if ($logo && file_exists($UPLOAD_DIR . $logo)) unlink($UPLOAD_DIR . $logo);

                $stmt = $pdo->prepare("DELETE FROM restoranlar WHERE restoran_id = ?");
                $ok = $stmt->execute([$id]);

                echo json_encode([
                    "status" => $ok ? "success" : "error",
                    "message" => $ok ? "Restoran silindi." : "Silme başarısız."
                ], JSON_UNESCAPED_UNICODE);
            }

            break;

        default:
            http_response_code(405);
            echo json_encode(["status" => "error", "message" => "Geçersiz HTTP metodu."], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>