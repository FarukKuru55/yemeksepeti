<?php

// === 1. ORTAM KONTROLÜ (Kimlik Kontrolü) ===
// Postman'den özel başlık (Header) gelip gelmediğini kontrol et.
// Bu, Postman'de test yaparken kullanacağınız gizli anahtarınız olacak.
$is_postman_test = false;
// $_SERVER['HTTP_API_TEST_MODE'] değişkeni, Postman'den gönderdiğiniz 
// API-TEST-MODE başlıgının değerini tutar.
if (isset($_SERVER['HTTP_API_TEST_MODE']) && $_SERVER['HTTP_API_TEST_MODE'] === 'BEN-BACKENDCIYIM-123') {
    $is_postman_test = true;
}

if ($is_postman_test) {
    // === 2. POSTMAN TEST AYARLARI (YEREL XAMPP/WAMP DB) ===
    $host = "localhost"; 
    $dbname = "yemeksepeti"; 
    $username = "root";   
    $password = "";      
    
} else {
    // === 3. CANLI SUNUCU AYARLARI (FRONTENDCİ ERİŞİMİ İÇİN) ===
    $host = "sql211.infinityfree.com"; 
    $dbname = "if0_40087914_yemeksepeti";
    $username = "if0_40087914";
    $password = "SuMUKrWUpO4v"; 
}


// === 4. PDO BAĞLANTISI (Her iki ortam için de aynı) ===
$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
$pdo = null;

try { 
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, 
    ]);
    
} catch (PDOException $e) {
    // Bağlantı hatası durumunda HTTP 500 ve hata detayı gönderilir
    http_response_code(500); 
    header('Content-Type: application/json');

    // Hata durumunda ana uygulamayı durdur ve bir API hatası döndür
    echo json_encode([
        "status" => "error",
        "message" => "Sunucu veritabanı bağlantısı kurulamadı. Lütfen sunucu ayarlarınızı kontrol edin.",
        "details" => $e->getMessage() 
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

?>