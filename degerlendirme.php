<?php
// --- Hata Koruması 1: Çıktı Tamponlamayı Başlat ---
// 'headers already sent' (BOM) hatasını engeller
ob_start();

// Hata ayıklama (Sadece geliştirme aşamasında açık olmalı)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- Hata Koruması 2: CORS İZİNLERİ (Failed to fetch Çözümü) ---
// Frontend'cinin (localhost) bağlanabilmesi için
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
// DİKKAT: Sadece POST'a izin veriyoruz (GET ve OPTIONS ile birlikte)
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// 'OPTIONS' (ön kontrol) isteğine izin ver
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


// --- GÜVENLİK FONKSİYONU ---
// (Bu fonksiyonu tüm korumalı dosyalara kopyalıyoruz)
function get_user_data_from_token($secret_key)
{
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


try {
    // --- Hata Koruması 3: Tamponu Temizle ---
    // Gerçek JSON'u göndermeden hemen önce,
    // hafızadaki 'BOM' dahil tüm istenmeyen çıktıları sil.
    ob_end_clean();

    $method = $_SERVER['REQUEST_METHOD'];

    // ----------------------------------------------------
    // 🔹 METOD: GET (Listeleme - HERKESE AÇIK)
    // ----------------------------------------------------
    if ($method === "GET") {
        
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT * FROM degerlendirmeler WHERE degerlendirme_id = ?");
            $stmt->execute([$_GET['id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($data) {
                echo json_encode(["status"=>"success","message"=>"Tek değerlendirme getirildi","data"=>$data], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(404);
                echo json_encode(["status"=>"error","message"=>"Kayıt bulunamadı"], JSON_UNESCAPED_UNICODE);
            }
        } else {
            // GENEL LİSTELEME
            $stmt = $pdo->query("SELECT * FROM degerlendirmeler ORDER BY degerlendirme_id ASC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["status"=>"success","message"=>"Tüm değerlendirmeler getirildi","data"=>$data], JSON_UNESCAPED_UNICODE);
        }

    // ----------------------------------------------------
    // 🔹 METOD: POST (Ekle, Güncelle, Sil - KORUMALI)
    // ----------------------------------------------------
    } elseif ($method === "POST") {

        // VERİ TİPİ: Bu dosya JSON ile çalışır (Ders 30)
        $input = json_decode(file_get_contents("php://input"), true);
        if ($input === null) {
            throw new Exception("Geçersiz JSON verisi.", 400);
        }

        // METOD TÜNELLEME (Ders 31)
        $action = $input['_method'] ?? 'POST';

        // GÜVENLİK: TÜM POST/PUT/DELETE İÇİN TOKEN KONTROLÜ
        $kullanici = get_user_data_from_token($jwtAyarlari['jwt_secret']);

        // -------------------------------
        // 🔹 EYLEM: POST (EKLEME)
        // -------------------------------
        if ($action === 'POST') {
            
            if (!isset($input['restoran_id'], $input['puan'])) {
                throw new Exception("Eksik bilgi: restoran_id ve puan zorunludur.", 400);
            }

            // GÜVENLİK (Ders 33.5): Müşteri ID'si input'tan DEĞİL, token'dan alınır.
            $musteri_id_from_token = $kullanici['sub']; 

            $stmt = $pdo->prepare("INSERT INTO degerlendirmeler (musteri_id, restoran_id, puan, yorum, tarih) VALUES (?, ?, ?, ?, NOW())");
            $ok = $stmt->execute([
                $musteri_id_from_token,
                $input['restoran_id'],
                $input['puan'],
                $input['yorum'] ?? null
            ]);
            
            http_response_code(201); // 201 Created
            echo json_encode(["status"=>"success", "message"=>"Değerlendirme eklendi", "id"=>$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);

        // -------------------------------
        // 🔹 EYLEM: PUT (GÜNCELLEME)
        // -------------------------------
        } elseif ($action === 'PUT') {
            
            // GÜVENLİK (Ders 33): Sadece Adminler güncelleyebilir (Basit kural)
            if ($kullanici['rol'] !== 'admin') {
                throw new Exception("Yetkisiz işlem: Sadece adminler güncelleyebilir.", 403);
            }

            if (!isset($input['degerlendirme_id'], $input['yorum'])) {
                 throw new Exception("Eksik bilgi: degerlendirme_id ve yorum zorunludur.", 400);
            }

            // (Sadece 'yorum' güncellenebilir varsayıyoruz)
            $stmt = $pdo->prepare("UPDATE degerlendirmeler SET yorum = ? WHERE degerlendirme_id = ?");
            $ok = $stmt->execute([
                $input['yorum'],
                $input['degerlendirme_id']
            ]);

            echo json_encode(["status"=>"success", "message"=>"Değerlendirme güncellendi."], JSON_UNESCAPED_UNICODE);

        // -------------------------------
        // 🔹 EYLEM: DELETE (SİLME)
        // -------------------------------
        } elseif ($action === 'DELETE') {

            // GÜVENLİK (Ders 33): Sadece Adminler silebilir
             if ($kullanici['rol'] !== 'admin') {
                throw new Exception("Yetkisiz işlem: Sadece adminler silebilir.", 403);
            }

            if (!isset($input['degerlendirme_id'])) {
                throw new Exception("Eksik bilgi: degerlendirme_id zorunludur.", 400);
            }

            $stmt = $pdo->prepare("DELETE FROM degerlendirmeler WHERE degerlendirme_id=?");
            $ok = $stmt->execute([$input['degerlendirme_id']]);

            echo json_encode(["status"=>"success", "message"=>"Değerlendirme silindi."], JSON_UNESCAPED_UNICODE);
        }

    } else {
        // GET veya POST dışındaki (PUT, DELETE gibi) doğrudan istekleri reddet
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "İzin verilmeyen yöntem. (PUT/DELETE için POST ve _method kullanın)"], JSON_UNESCAPED_UNICODE);
    }

} catch (Throwable $e) {
    // --- Hata Koruması 4: Nihai Hata Yakalayıcı ---
    ob_end_clean(); // Hata oluşursa tamponu (BOM, <b>Warning</b>) temizle

    // Hata kodunun "42S02" (metin) gibi gelme ihtimaline karşı (Ders 22)
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
?>
