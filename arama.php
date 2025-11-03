<?php
// arama.php - Restoran ve Yemek Arama API'si
ini_set('display_errors', 1);
error_reporting(E_ALL);

// === Gerekli API Başlıkları ve CORS Ayarları ===
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS"); // Sadece GET ve OPTIONS
header("Access-Control-Allow-Headers: Content-Type"); // Token'a (Authorization) gerek yok

// Preflight OPTIONS isteğine cevap
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// === Veritabanı Bağlantısı ===
require_once __DIR__ . "/Api/db.php";

$method = $_SERVER['REQUEST_METHOD'];

try {
    // Sadece GET isteklerini kabul et
    if ($method === "GET") {

        // 1. Arama terimini al
        $sorgu = $_GET['sorgu'] ?? $_GET['q'] ?? null;

        if (empty($sorgu) || strlen(trim($sorgu)) < 2) {
            http_response_code(400); // Bad Request
            echo json_encode(["status"=>"error","message"=>"Lütfen en az 2 harf içeren bir arama terimi girin."]);
            exit;
        }
        
        // SQL LIKE sorgusu için arama terimini hazırla (örn: "%pide%")
        $searchTerm = '%' . $sorgu . '%';

        // 2. SQL Sorgusu
        // Bu sorgu, iki yerden arama yapar:
        // 1. Restoran adı `LIKE` arama terimi
        // 2. Menü (yemek) adı `LIKE` arama terimi
        // Sonuçları birleştirir ve her restorandan sadece 1 tane getirir (DISTINCT)
        
        $sql = "
            SELECT DISTINCT
                r.restoran_id,
                r.ad,
                r.adres,
                r.logo_url,
                r.puan,
                r.kategori_id
            FROM
                restoranlar r
            LEFT JOIN
                menuler m ON r.restoran_id = m.restoran_id
            WHERE
                r.ad LIKE ? 
                OR m.ad LIKE ?
            ORDER BY
                r.puan DESC, r.ad ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$searchTerm, $searchTerm]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($data) {
            echo json_encode(["status"=>"success","message"=>"Arama sonuçları getirildi","data"=>$data], JSON_UNESCAPED_UNICODE);
        } else {
            // Sonuç bulunamasa bile bu bir hata değildir, boş bir dizidir.
            echo json_encode(["status"=>"success","message"=>"Arama sonucu bulunamadı","data"=>[]], JSON_UNESCAPED_UNICODE);
        }

    } else {
        // GET dışındaki tüm metodları reddet
        http_response_code(405);
        echo json_encode(["status"=>"error","message"=>"Geçersiz method"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status"=>"error","message"=>"Sunucu hatası: ".$e->getMessage()]);
}
?>