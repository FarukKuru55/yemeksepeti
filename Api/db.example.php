<?php
// db.example.php - Örnek veritabanı yapılandırması (Local / Production)

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Yerel ortam (örnek)
$local = [
    'host'     => '127.0.0.1', 
    'port'     => 3306,        
    'dbname'   => 'ornek_veritabani',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
];

// Canlı ortam (örnek)
$prod = [
    'host'     => 'your-production-host.com',
    'port'     => 3306,
    'dbname'   => 'production_database',
    'username' => 'production_user',
    'password' => 'production_password',
    'charset'  => 'utf8mb4',
];

// Varsayılan ortam
$env = 'prod'; // 'local' veya 'prod' olabilir

if (isset($_SERVER['HTTP_HOST'])) {
    $hostHeader = strtolower($_SERVER['HTTP_HOST']);
    if (strpos($hostHeader, 'localhost') !== false || strpos($hostHeader, '127.0.0.1') !== false) {
        $env = 'local';
    } elseif (strpos($hostHeader, 'trycloudflare.com') !== false) {
        $env = 'local';
    }
} elseif (php_sapi_name() === 'cli') {
    $env = 'local';
}

// Seçilen ortam yapılandırması
$config = ($env === 'local') ? $local : $prod;

// PDO bağlantısı oluştur
$dsn = sprintf(
    "mysql:host=%s;port=%d;dbname=%s;charset=%s",
    $config['host'],
    $config['port'],
    $config['dbname'],
    $config['charset']
);

try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
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
    if ($env === 'local') {
        $response['details'] = $e->getMessage();
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
