<?php
// sepet.php - GÜVENLİ VE TOKEN KORUMALI SÜRÜM
ini_set('display_errors', 1);
error_reporting(E_ALL);

// === Gerekli API Başlıkları ve CORS Ayarları ===
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Sadece GET/POST/OPTIONS
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Authorization (Token) eklendi

// Preflight OPTIONS isteğine cevap
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

// --- GÜVENLİK FONKSİYONU (JWT Token Çözümleme) ---
function get_user_data_from_token($secret_key)
{
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    if (!$authHeader && function_exists('getallheaders')) {
         $headers = getallheaders();
         $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
    }
    
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
// --- GÜVENLİK FONKSİYONU SONU ---

try {
    // === 1. ADIM: KULLANICI KİMLİĞİNİ DOĞRULA (TÜM İŞLEMLER İÇİN) ===
    $kullanici = get_user_data_from_token($jwtAyarlari['jwt_secret']);
    $musteri_id = $kullanici['sub'] ?? null; // Token'dan musteri_id (sub) alınıyor
    $rol = $kullanici['rol'] ?? null;
    
    // Sadece 'musteri' rolündekiler sepet işlemi yapabilir
    if ($rol !== 'musteri' || !$musteri_id) {
         throw new Exception("Bu işlem için 'musteri' yetkisi gereklidir.", 403);
    }

    $method = $_SERVER['REQUEST_METHOD'];

    // === 2. ADIM: İSTEĞİ YÖNET (CRUD) ===

    // ----------------------------------------------------
    // 🔹 METOD: GET (Sepetimi Görüntüle)
    // ----------------------------------------------------
    if ($method === "GET") {
        
        // GÜVENLİK: Müşteri SADECE KENDİ sepetini görebilir.
        $stmt = $pdo->prepare("SELECT * FROM sepet WHERE musteri_id = ? ORDER BY sepet_id ASC");
        $stmt->execute([$musteri_id]); // Token'dan alınan musteri_id kullanılıyor
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["status"=>"success","message"=>"Sepet getirildi","data"=>$data], JSON_UNESCAPED_UNICODE);

    // ----------------------------------------------------
    // 🔹 METOD: POST (Sepete Ekle, Güncelle, Sil)
    // ----------------------------------------------------
    } elseif ($method === "POST") {
        
        // TUTARLILIK: Diğer API'ler gibi JSON verisi bekleniyor
        $input = json_decode(file_get_contents("php://input"), true);
        if ($input === null) {
            throw new Exception("Geçersiz JSON verisi.", 400);
        }
        
        // Tünelleme: _method ile PUT veya DELETE yapılacak mı?
        $action = $input['_method'] ?? 'POST';

        // -------------------------------
        // 🔹 EYLEM: POST (Sepete Ekle)
        // -------------------------------
        if ($action === 'POST') {
            if (!isset($input['urun_id'], $input['adet'])) {
                throw new Exception("Eksik bilgi: urun_id ve adet zorunludur.", 400);
            }

            // GÜVENLİK: musteri_id, $input'tan değil, token'dan alınıyor.
            $stmt = $pdo->prepare("INSERT INTO sepet (musteri_id, urun_id, adet, eklenme_tarihi) VALUES (?, ?, ?, NOW())");
            $ok = $stmt->execute([
                $musteri_id, // GÜVENLİ: Token'dan
                $input['urun_id'],
                $input['adet']
            ]);

            http_response_code(201); // 201 Created
            echo json_encode(["status"=>"success", "message"=>"Ürün sepete eklendi", "id"=>$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);

        // -------------------------------
        // 🔹 EYLEM: PUT (Adet Güncelle)
        // -------------------------------
        } elseif ($action === 'PUT') {
            
            if (!isset($input['sepet_id'], $input['adet'])) {
                throw new Exception("Eksik bilgi: sepet_id ve adet zorunludur.", 400);
            }

            // GÜVENLİK: Sadece kendi sepetindeki ürünü güncelleyebilir (WHERE musteri_id = ?)
            $stmt = $pdo->prepare("UPDATE sepet SET adet = ? WHERE sepet_id = ? AND musteri_id = ?");
            $stmt->execute([
                $input['adet'],
                $input['sepet_id'],
                $musteri_id // GÜVENLİ: Token'dan
            ]);

            // rowCount() ile gerçekten bir güncelleme yapıldı mı kontrol et
            if ($stmt->rowCount() > 0) {
                 echo json_encode(["status"=>"success", "message"=>"Sepet güncellendi."], JSON_UNESCAPED_UNICODE);
            } else {
                 throw new Exception("Güncelleme başarısız. Ürün bulunamadı veya size ait değil.", 404);
            }

        // -------------------------------
        // 🔹 EYLEM: DELETE (Sepetten Sil)
        // -------------------------------
        } elseif ($action === 'DELETE') {

            if (!isset($input['sepet_id'])) {
                throw new Exception("Eksik bilgi: sepet_id zorunludur.", 400);
            }

            // GÜVENLİK: Sadece kendi sepetindeki ürünü silebilir (WHERE musteri_id = ?)
            $stmt = $pdo->prepare("DELETE FROM sepet WHERE sepet_id = ? AND musteri_id = ?");
            $stmt->execute([
                $input['sepet_id'],
                $musteri_id // GÜVENLİ: Token'dan
            ]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(["status"=>"success", "message"=>"Ürün sepetten silindi."], JSON_UNESCAPED_UNICODE);
            } else {
                 throw new Exception("Silme başarısız. Ürün bulunamadı veya size ait değil.", 404);
            }
        
        } else {
            throw new Exception("Geçersiz '_method' eylemi.", 400);
        }

    } else {
        // GET veya POST dışındaki (PUT, DELETE gibi) doğrudan istekleri reddet
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "İzin verilmeyen yöntem. (PUT/DELETE için POST ve _method kullanın)"], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    // === 3. ADIM: TÜM HATA YAKALAYICI ===
    $statusCode = $e->getCode();
    if (!is_int($statusCode) || $statusCode < 400 || $statusCode > 599) {
        $statusCode = 500; // Varsayılan sunucu hatası
    }
    http_response_code($statusCode);

    echo json_encode([
        "status" => "error",
        "message" => "Sunucu hatası: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}