<?php
// --- Hata Koruması 1: Çıktı Tamponlama ---
ob_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

// API dosyası olduğunu belirt
header("Content-Type: application/json; charset=UTF-8");

// === NİHAİ ÇÖZÜM (Ders 42/44): CORS İZNİ ===
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Tarayıcının 'OPTIONS' (ön kontrol) isteğine yanıt ver
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // --- Veritabanı Bağlantısı ---
    require_once __DIR__ . "/Api/db.php";

    // --- Veriyi Çek ---
    $stmt = $pdo->query("SELECT restoran_id, ad, kategori_id, puan, logo_url FROM restoranlar ORDER BY restoran_id ASC");
    $restoranlar = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Hata Koruması 2: Tamponu Temizle ---
    ob_end_clean();

    // --- Başarılı JSON Yanıtı ---
    echo json_encode([
        "status" => "success",
        "message" => "Restoranlar başarıyla listelendi (restoran_getir.php)",
        "data" => $restoranlar
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    // --- Hata Koruması 3: TÜM Hataları Yakala ---
    if (ob_get_level() > 0) {
        ob_end_clean(); 
    }
    
    $statusCode = $e->getCode();
    header("Access-Control-Allow-Origin: *"); 
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    if (!is_int($statusCode) || $statusCode < 100 || $statusCode > 599) {
        $statusCode = 500; // Varsayılan sunucu hatası
    }
    http_response_code($statusCode);

    // --- Hata JSON Yanıtı ---
    echo json_encode([
        "status" => "error",
        "message" => "Sunucu hatası: " . $e->getMessage(),
        "trace" => $e->getTraceAsString() // Hata ayıklama için detay
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>