<?php

// Hata raporlamayı aç (Geliştirme Ortamı İçin Önerilir)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// === Gerekli API Başlıkları ve CORS Ayarları ===
header("Access-Control-Allow-Origin: *");
header_remove("X-Powered-By"); // Güvenlik için PHP bilgisini gizle
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, API-TEST-MODE"); // API-TEST-MODE eklendi
header("Content-Type: application/json; charset=UTF-8");

// Preflight OPTIONS isteğine cevap (Frontendci için gereklidir)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// HATA KONTROLÜ: PDO bağlantı dosyasını dahil et
// Bu dosya ($pdo) hem canlı hem de yerel ayarları içerir.
try {
    // db.php'den gelen $pdo nesnesini yükler
    require_once __DIR__ . "/Api/db.php";
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception("PDO bağlantı nesnesi (\$pdo) 'db.php' dosyasından yüklenemedi.");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Veritabanı bağlantı hatası: " . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {

        // GET: tüm adminleri listele
        case "GET":
            $stmt = $pdo->query("SELECT admin_id, username, created_at FROM admin ORDER BY admin_id ASC");
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["status" => "success", "data" => $admins], JSON_UNESCAPED_UNICODE);
            break;

        // POST: login veya yeni admin ekleme
        case "POST":
            // JSON veya Form Verisi oku
            $input = json_decode(file_get_contents("php://input"), true);
            if (!$input && !empty($_POST)) {
                $input = $_POST;
            }

            if (!isset($input['action'])) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "POST isteği bir 'action' parametresi içermelidir."]);
                break;
            }
            
            // Parametreleri temizle (trim)
            $action = trim($input['action']);
            $username = isset($input['username']) ? trim($input['username']) : null;
            $password = isset($input['password']) ? $input['password'] : null;


            // --- LOGIN işlemi ---
            if ($action === 'login') {
                if (empty($username) || empty($password)) {
                    http_response_code(400);
                    echo json_encode(["status" => "error", "message" => "Kullanıcı adı ve şifre gereklidir."]);
                    exit;
                }

                $stmt = $pdo->prepare("SELECT admin_id, password FROM admin WHERE username = ?");
                $stmt->execute([$username]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($admin && password_verify($password, $admin['password'])) {
                    echo json_encode(["status" => "success", "message" => "Giriş başarılı", "admin_id" => $admin['admin_id']]);
                } else {
                    http_response_code(401);
                    echo json_encode(["status" => "error", "message" => "Geçersiz kullanıcı adı veya şifre."]);
                }
                exit;
            }

            // --- Yeni admin ekleme (ADD) ---
            if ($action === 'add') {
                if (empty($username) || empty($password)) {
                    http_response_code(400);
                    echo json_encode(["status" => "error", "message" => "Kullanıcı adı ve şifre gereklidir."]);
                    exit;
                }

                // Aynı kullanıcı adının olup olmadığını kontrol et
                $stmt = $pdo->prepare("SELECT admin_id FROM admin WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                     http_response_code(409); // Conflict
                     echo json_encode(["status" => "error", "message" => "Bu kullanıcı adı zaten mevcut."]);
                     exit;
                }
                
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO admin (username, password) VALUES (?, ?)");
                $ok = $stmt->execute([$username, $hash]);

                echo json_encode($ok
                    ? ["status" => "success", "message" => "Admin eklendi", "admin_id" => $pdo->lastInsertId()]
                    : ["status" => "error", "message" => "Admin eklenemedi (Veritabanı hatası)"]);
                exit;
            }

            // Eğer 'action' tanımlı ama 'login' veya 'add' değilse
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Geçersiz action değeri."]);
            break;

        // Tanımlanmamış Methodlar
        default:
            http_response_code(405);
            echo json_encode(["status" => "error", "message" => "Desteklenmeyen Method."]);
    }

} catch (Exception $e) {
    // Diğer tüm PDO/Sorgu hatalarını yakalar
    http_response_code(500);
    // Güvenlik için, detayları sadece test ortamında gösterin.
    echo json_encode(["status" => "error", "message" => "Sunucu Hatası", "details" => $e->getMessage()]);
}
?>