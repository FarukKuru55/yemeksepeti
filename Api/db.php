<?php
// db.php - Local / Production (Düzeltilmiş Ortam Algılamalı)

error_reporting(E_ALL);
ini_set('display_errors', 1);

$local = [
    'host'     => '127.0.0.1',  // Localhost MySQL
    'port'     => 3308,         // XAMPP portun
    'dbname'   => 'yemeksepeti',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
];

$prod = [
    'host'     => 'sql211.infinityfree.com',
    'port'     => 3306,
    'dbname'   => 'if0_40087914_yemeksepeti',
    'username' => 'if0_40087914',
    'password' => 'SuMUKrWUpO4v',
    'charset'  => 'utf8mb4',
];


$env = 'prod'; // Varsayılan olarak CANLI (prod) ayarla

if (isset($_SERVER['HTTP_HOST'])) {
    $hostHeader = strtolower($_SERVER['HTTP_HOST']);

    // 1. Kural: Doğrudan localhost erişimi
    if (strpos($hostHeader, 'localhost') !== false || strpos($hostHeader, '127.0.0.1') !== false) {
        $env = 'local';
    } 
    // 2. Kural: Cloudflare Tüneli (test için)
    else if (strpos($hostHeader, 'trycloudflare.com') !== false) {
        $env = 'local';
    }
    // Diğer tüm durumlar (yemek.wuaze.com dahil) 'prod' olarak kalır.
} 
// Eğer komut satırından çalıştırılırsa (ileri düzey kullanım)
elseif (php_sapi_name() === 'cli') {
    $env = 'local';
}
// Ortam değişkeni (getenv) kontrollerini şimdilik basitleştirdik.

// === Seçilen config ===
$config = ($env === 'local') ? $local : $prod;

// === DSN oluştur ===
$dsn = sprintf(
    "mysql:host=%s;port=%d;dbname=%s;charset=%s",
    $config['host'],
    $config['port'],
    $config['dbname'],
    $config['charset']
);

// === PDO bağlantısı ===
try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES  => false,
    ]);
} catch (PDOException $e) {
    error_log("DB connection error ({$env}): " . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    $response = [
        "status"  => "error",
        "message" => "Veritabanı bağlantısı başarısız ({$env})",
    ];
    // Sadece yerel ortamda detaylı hata göster
    if ($env === 'local') {
        $response['details'] = $e->getMessage();
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
?>