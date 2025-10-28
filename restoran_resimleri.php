<?php
// Hata ayıklama ayarları (Canlıda KAPALI tutulmalıdır)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Veritabanı bağlantısı
// db.php dosyasının doğru yolu: Sizin dosya yapınıza göre düzeltin.
require_once __DIR__ . "/Api/db.php"; 

// Başlıklar (CORS ve JSON Çıktısı için)
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Eğer bir OPTIONS isteği gelirse, CORS yanıtını hemen döndür
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// RESTORAN RESİMLERİNİN BASE URL'si
// LOGO_BASE_URL'inizle aynı veya benzer olmalıdır. Kontrol ediniz.
// Örnek: $BASE_URL = "http://yemek.wuaze.com/img/restoran_resimler/";
$BASE_URL = "http://19.2.28.84/yemeksepeti/img/restoran_logolar/"; 


// Sadece GET metotlarını kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Sadece GET metodu desteklenmektedir."], JSON_UNESCAPED_UNICODE);
    exit;
}


// URL'den restoran_id parametresini al
$restoran_id = $_GET['restoran_id'] ?? null;

if (empty($restoran_id) || !is_numeric($restoran_id)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "restoran_id parametresi zorunludur ve sayı olmalıdır."], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Resimleri veritabanından çekme sorgusu
    $stmt = $pdo->prepare("
        SELECT resim_adi, resim_yolu
        FROM restoranlar_resimleri
        WHERE restoran_id = ? 
        ORDER BY sira_no ASC
    ");
    
    $stmt->execute([$restoran_id]);
    $resimler = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Resim URL'lerini oluşturma ve formatlama
    $resim_listesi = [];
    foreach ($resimler as $resim) {
        $resim_listesi[] = [
            'url' => $BASE_URL . $resim['resim_yolu'] . $resim['resim_adi']
        ];
    }
    
    if (empty($resim_listesi)) {
         // Veri bulunamazsa 404
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Bu restorana ait resim bulunamadı."], JSON_UNESCAPED_UNICODE);
    } else {
        // Başarılı JSON çıktısı
        http_response_code(200);
        echo json_encode(["status" => "ok", "resimler" => $resim_listesi], JSON_UNESCAPED_UNICODE);
    }


} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Veritabanı hatası",
        "details" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

?>