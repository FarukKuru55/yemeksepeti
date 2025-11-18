<?php
// musteriler.php - GÜNCELLENMİŞ SÜRÜM (Metod Tünelleme Eklendi)

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

$token = getBearerToken();
if (!$token) {
    http_response_code(401);
    echo json_encode(["status"=>"error","message"=>"Token gerekli"]);
    exit;
}

try {
    $decoded = JWT::decode($token, new Key($config['jwt_secret'], 'HS256'));
    $rol = $decoded->rol ?? null;
    $musteri_id_token = $decoded->sub ?? null; // Token'daki musteri ID'si
    if (!$rol || !$musteri_id_token) throw new Exception("Token geçersiz");
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        "status" => "error",
        "message" => $config['env'] === 'development' ? $e->getMessage() : "Bir hata oluştu."
    ], JSON_UNESCAPED_UNICODE);
}
// --- TOKEN SONU ---

$method = $_SERVER['REQUEST_METHOD'];

try {
    // 🔹 METOD: GET (Müşteri(ler)i Görüntüle)
    if ($method === "GET") {
        
        // (Orijinal GET kodun - Mükemmel çalışıyor)
        $id = $_GET['musteri_id'] ?? $_GET['id'] ?? null;
            
        if ($rol === "musteri") {
            // Müşteri sadece kendi profilini görebilir
            $id = $musteri_id_token;
        } elseif ($rol === "restoran") {
            // Restoranlar müşteri bilgilerine erişemez
            http_response_code(403);
            echo json_encode(["status"=>"error","message"=>"Restoranlar müşteri bilgilerine erişemez"]);
            exit;
        } elseif ($rol !== "admin" && $rol !== "super_admin") {
            // Diğer roller (eğer varsa) erişemez
            http_response_code(403);
            echo json_encode(["status"=>"error","message"=>"Yetkiniz yok"]);
            exit;
        }
        
        // Buraya sadece "musteri" (kendi ID'si ile) veya "admin" (istediği ID ile) gelebilir
        // Admin tüm listeyi de görebilir
        if ($rol === 'admin' && $id === null) {
            $stmt = $pdo->query("SELECT musteri_id, ad, soyad, email, telefon, adres, kayit_tarihi, rol FROM musteriler");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
             // Müşteri kendi ID'sini veya Admin'in istediği ID'yi alır
            if ($id === null) $id = $musteri_id_token; // Admin'in id yollamadığı durumu düzelt
            
            $stmt = $pdo->prepare("SELECT musteri_id, ad, soyad, email, telefon, adres, kayit_tarihi, rol FROM musteriler WHERE musteri_id=?");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$data) {
            http_response_code(404);
            echo json_encode(["status"=>"error","message"=>"Müşteri bulunamadı"]);
        } else {
            echo json_encode(["status"=>"success","data"=>$data], JSON_UNESCAPED_UNICODE);
        }

     // 🔹 METOD: POST (Müşteri Ekle, Güncelle, Sil) (YAPI DEĞİŞTİ)
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

        // EYLEM: POST (Yeni Müşteri Ekle) - Sadece Admin  
        if ($action === 'POST') {
            
            // (Orijinal POST kodun)
            if ($rol !== "admin" && $rol !== "super_admin") {
                http_response_code(403);
                echo json_encode(["status"=>"error","message"=>"Yetkiniz yok"]);
                exit;
            }

            if (!isset($input['ad'], $input['email'], $input['sifre'])) {
                http_response_code(400);
                echo json_encode(["status"=>"error","message"=>"Eksik alan (ad, email, sifre)"]);
                exit;
            }

            $sifre = password_hash($input['sifre'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO musteriler (ad, soyad, email, sifre, rol) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $input['ad'], 
                $input['soyad'] ?? null, 
                $input['email'], 
                $sifre, 
                $input['rol'] ?? 'musteri'
            ]);
            http_response_code(201);
            echo json_encode(["status"=>"success","message"=>"Müşteri eklendi","musteri_id"=>$pdo->lastInsertId()]);

        // -------------------------------
        // 🔹 EYLEM: PUT (Müşteri Güncelle) (ESKİ PUT KODU BURAYA TAŞINDI)
        // -------------------------------
        } elseif ($action === 'PUT') {
            
            // (Orijinal PUT kodun)
            $id = $input['musteri_id'] ?? null;

            if ($rol === "musteri") {
                // Müşteri sadece kendi hesabını güncelleyebilir
                $id = $musteri_id_token;
            } elseif ($rol === "restoran") {
                http_response_code(403);
                echo json_encode(["status"=>"error","message"=>"Restoranlar müşteri bilgilerini güncelleyemez"]);
                exit;
            } elseif ($rol !== "admin" && $rol !== "super_admin") {
                 // Admin değilse ve müşteri de değilse (veya admin olup ID yollamadıysa)
                if ($id === null) {
                    http_response_code(403);
                    echo json_encode(["status"=>"error","message"=>"Yetkiniz yok veya musteri_id gerekli"]);
                    exit;
                }
            }
            
            // Adminin rol güncellemesine izin ver, müşteri kendi rolünü güncelleyemesin
            $rolGuncelleSQL = "";
            $params = [
                $input['ad'] ?? null,
                $input['soyad'] ?? null,
                $input['email'] ?? null,
                $input['telefon'] ?? null, // telefon ve adres eklendi
                $input['adres'] ?? null   // telefon ve adres eklendi
            ];
            
            if (($rol === "admin" || $rol === "super_admin") && isset($input['rol'])) {
                $rolGuncelleSQL = ", rol = ?";
                $params[] = $input['rol'];
            }
            
            $params[] = $id; // WHERE için ID'yi sona ekle
            
            // Orijinal kodda eksik olan telefon ve adres güncellemeyi ekledim
            $stmt = $pdo->prepare("UPDATE musteriler SET ad=?, soyad=?, email=?, telefon=?, adres=? $rolGuncelleSQL WHERE musteri_id=?");
            $stmt->execute($params);
            
            echo json_encode(["status"=>"success","message"=>"Müşteri güncellendi"]);

        // -------------------------------
        // 🔹 EYLEM: DELETE (Müşteri Sil) (ESKİ DELETE KODU BURAYA TAŞINDI)
        // -------------------------------
        } elseif ($action === 'DELETE') {

            // (Orijinal DELETE kodun)
            if ($rol !== "admin" && $rol !== "super_admin") {
                http_response_code(403);
                echo json_encode(["status"=>"error","message"=>"Yetkiniz yok"]);
                exit;
            }
            
            // (DEĞİŞTİ: ID artık JSON'dan ($input) alınıyor)
            $id = $input['musteri_id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(["status"=>"error","message"=>"JSON body içinde musteri_id gerekli"]);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM musteriler WHERE musteri_id=?");
            $stmt->execute([$id]);
            echo json_encode(["status"=>"success","message"=>"Müşteri silindi"]);
        
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