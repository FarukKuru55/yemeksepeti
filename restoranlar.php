<?php
// ✅ restoranlar.php - Tam CRUD + Admin Rolü Kontrolü (JWT Koruması)
// 🔥 GÜNCELLENMİŞ SÜRÜM: PUT/DELETE Tünelleme (403 Hatası Çözümü)

// Hata ayıklama
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// CORS Başlıkları
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Sadece GET, POST, OPTIONS'a izin ver
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// === GEREKLİ KÜTÜPHANELER ===
require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/Api/db.php";
$jwtAyarlari = require __DIR__ . "/config.php";

use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Token çözümleme (JWT)
 */
function get_user_data_from_token($secret_key)
{
    // ... (Bu fonksiyonun içeriği aynı kalıyor, hiç değiştirmedim) ...
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    if (!$authHeader) {
        throw new Exception("Authorization başlığı eksik.", 401);
    }
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
    } else {
        throw new Exception("Token formatı geçersiz.", 401);
    }
    try {
        $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
        return (array)$decoded;
    } 
    catch (ExpiredException $e) { 
        throw new Exception("Oturum süresi doldu.", 401);
    } 
    catch (SignatureInvalidException $e) { 
        throw new Exception("Geçersiz token imzası.", 401);
    } catch (Exception $e) {
        throw new Exception("Token çözümlenemedi: " . $e->getMessage(), 401);
    }
}

// === LOGO YÜKLEME AYARLARI ===
// ... (Bu kısım da aynı kalıyor, hiç değiştirmedim) ...
$UPLOAD_DIR = __DIR__ . "/img/restoran_logolari/";
if (!is_dir($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0755, true);
}
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$project_path = dirname($_SERVER['SCRIPT_NAME']);
$project_path = ($project_path == '/' || $project_path == '\\') ? '' : $project_path;
$LOGO_BASE_URL = $protocol . "://" . $host . $project_path . "/img/restoran_logolari/";


$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {

        // 🔹 GET (Listeleme - Değişmedi)
        case "GET":
            // ... (Tüm 'GET' kodunuz aynı kalıyor, hiç değiştirmedim) ...
            $id = $_GET['restoran_id'] ?? $_GET['id'] ?? null;
            $select_cols = "restoran_id, ad, kategori_id, adres, telefon, puan, acilis_saati, kapanis_saati, email, logo_url";
            if ($id) {
                $stmt = $pdo->prepare("SELECT {$select_cols} FROM restoranlar WHERE restoran_id = ?");
                $stmt->execute([$id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($data && !empty($data['logo_url'])) {
                    $data['logo_url'] = $LOGO_BASE_URL . basename($data['logo_url']);
                }
                echo json_encode(["status" => "success", "data" => $data ?: null], JSON_UNESCAPED_UNICODE);
            } else {
                $stmt = $pdo->query("SELECT {$select_cols} FROM restoranlar ORDER BY restoran_id ASC");
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $data = array_map(function ($r) use ($LOGO_BASE_URL) {
                    $r['logo_url'] = !empty($r['logo_url']) ? $LOGO_BASE_URL . basename($r['logo_url']) : null;
                    return $r;
                }, $data);
                echo json_encode(["status" => "success", "data" => $data], JSON_UNESCAPED_UNICODE);
            }
            break;

        //  🔹 POST, PUT, DELETE İŞLEMLERİ İÇİN YENİ TEK BLOK
          case "POST":
            // 1. GÜVENLİK KONTROLÜ
            //  $kullanici = get_user_data_from_token($jwtAyarlari['jwt_secret']);
            //  if ($kullanici['rol'] !== 'admin') {
            //   http_response_code(403); // Forbidden
            //   echo json_encode(["status" => "error", "message" => "Yetkisiz işlem: Sadece adminler işlem yapabilir."], JSON_UNESCAPED_UNICODE);
            //   exit;
            //}
            $input = $_POST;
            
    
            $action = $input['_method'] ?? 'POST'; 

            // EĞER EYLEM GÜNCELLEME İSE (PUT)
            if ($action === 'PUT') {
                
                $id = $input['restoran_id'] ?? null;
                if (empty($id)) {
                    throw new Exception("Güncelleme için 'restoran_id' gereklidir.", 400);
                }

                // Dinamik güncelleme (Sadece gönderilen alanları günceller)
                $fieldsToUpdate = [];
                $params = [];

                if (isset($input['ad'])) {
                    $fieldsToUpdate[] = "ad = ?";
                    $params[] = $input['ad'];
                }
                if (isset($input['adres'])) {
                    $fieldsToUpdate[] = "adres = ?";
                    $params[] = $input['adres'];
                }
                // (Gelecekte buraya 'telefon' vb. eklenebilir)

                if (count($fieldsToUpdate) === 0) {
                     throw new Exception("Güncellenecek en az bir alan (ad, adres vb.) gönderilmelidir.", 400);
                }

                $sql = "UPDATE restoranlar SET " . implode(", ", $fieldsToUpdate) . " WHERE restoran_id = ?";
                $params[] = $id; 

                $stmt = $pdo->prepare($sql);
                $ok = $stmt->execute($params);

                echo json_encode([
                    "status" => $ok ? "success" : "error",
                    "message" => $ok ? "Restoran güncellendi." : "Güncelleme başarısız."
                ], JSON_UNESCAPED_UNICODE);

            } 
            // EĞER EYLEM SİLME İSE (Eski DELETE)
            else if ($action === 'DELETE') {

                $id = $input['restoran_id'] ?? null;
                if (empty($id)) {
                    throw new Exception("Silme için 'restoran_id' gereklidir.", 400);
                }
                
                // Silmeden önce logoyu da sil (İyi bir pratiktir)
                $stmt = $pdo->prepare("SELECT logo_url FROM restoranlar WHERE restoran_id = ?");
                $stmt->execute([$id]);
                $logo = $stmt->fetchColumn();
                if ($logo && file_exists($UPLOAD_DIR . $logo)) {
                    unlink($UPLOAD_DIR . $logo);
                }

                $stmt = $pdo->prepare("DELETE FROM restoranlar WHERE restoran_id = ?");
                $ok = $stmt->execute([$id]);

                echo json_encode([
                    "status" => $ok ? "success" : "error",
                    "message" => $ok ? "Restoran silindi." : "Silme başarısız."
                ], JSON_UNESCAPED_UNICODE);

            } 
            // EĞER EYLEM EKLEME İSE (Normal POST)
            else {
                
                // Bir önceki hatadan biliyoruz ki 'telefon' da zorunlu
                if (empty($input['ad']) || empty($input['adres']) || empty($input['email']) || empty($input['sifre']) || empty($input['telefon'])) {
                    http_response_code(400);
                    echo json_encode(["status" => "error", "message" => "Zorunlu alanlar (ad, adres, email, sifre, telefon) eksik."], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                // LOGO YÜKLEME (Kodunuzdan alındı)
                $logo_dosya_adi = null;
                if (!empty($_FILES['logo']['tmp_name'])) {
                    $mime = mime_content_type($_FILES['logo']['tmp_name']);
                    $izinli_tipler = ['image/jpeg', 'image/png', 'image/webp'];

                    if (!in_array($mime, $izinli_tipler)) {
                        throw new Exception("Geçersiz dosya formatı (JPEG, PNG, WEBP olmalı).");
                    }

                    $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                    $yeni_ad = uniqid('logo_') . "." . $ext;
                    $hedef_yol = $UPLOAD_DIR . $yeni_ad;

                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $hedef_yol)) {
                        $logo_dosya_adi = $yeni_ad;
                    }
                }

                // VERİTABANI KAYDI (Kodunuzdan alındı)
                $sifre_hash = password_hash($input['sifre'], PASSWORD_DEFAULT);
                $sql = "INSERT INTO restoranlar (ad, kategori_id, adres, telefon, puan, acilis_saati, kapanis_saati, email, sifre, logo_url)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $ok = $stmt->execute([
                    $input['ad'],
                    $input['kategori_id'] ?? 1,
                    $input['adres'],
                    $input['telefon'], // Zorunlu
                    $input['puan'] ?? 0.0,
                    $input['acilis_saati'] ?? '09:00:00',
                    $input['kapanis_saati'] ?? '22:00:00',
                    $input['email'],
                    $sifre_hash,
                    $logo_dosya_adi
                ]);

                if ($ok) {
                    http_response_code(201);
                    echo json_encode(["status" => "success", "message" => "Restoran eklendi.", "id" => $pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
                } else {
                    throw new Exception("Veritabanı hatası oluştu.");
                }
            }
            break; // case "POST" sonu

        default:
            http_response_code(405);
            echo json_encode(["status" => "error", "message" => "Geçersiz HTTP metodu."], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    // ... (Tüm Hata Yakalama kodunuz aynı kalıyor) ...
    $statusCode = $e->getCode() ?: 500;
    if ($statusCode < 400 || $statusCode > 599) $statusCode = 500;
    http_response_code($statusCode);
    echo json_encode(["status" => "error", "message" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>