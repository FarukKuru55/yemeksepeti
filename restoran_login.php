<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// --- OPTIONS isteği (CORS preflight) ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Hata Ayıklama Modu ---
$DEBUG = true;

// --- Gerekli Dosyalar ---
try {
    require_once __DIR__ . "/Api/db.php";          // PDO bağlantısı
    require_once __DIR__ . "/vendor/autoload.php"; // JWT kütüphanesi
    $config = require __DIR__ . "/config.php";     // JWT ayarları
} catch (Throwable $e) {
    http_response_code(500);
    die(json_encode([
        "status" => "error",
        "message" => "KRİTİK HATA: Gerekli dosyalar yüklenemedi.",
        "details" => $DEBUG ? $e->getMessage() : null
    ], JSON_UNESCAPED_UNICODE));
}

use Firebase\JWT\JWT;

// --- JSON POST Verisini Al ---
$input = json_decode(file_get_contents("php://input"), true);

if ($DEBUG) {
    error_log("🟢 [DEBUG] Gelen Veri: " . print_r($input, true));
}

// --- Email & Şifre Kontrolü ---
if (empty($input['email']) || empty($input['sifre'])) {
    http_response_code(400);
    die(json_encode([
        "status" => "error",
        "message" => $DEBUG ? "Email veya şifre alanı boş." : "Email ve şifre gerekli."
    ], JSON_UNESCAPED_UNICODE));
}

// --- Restoran Kaydını Bul ---
try {
    $stmt = $pdo->prepare("SELECT * FROM restoranlar WHERE email = ?");
    $stmt->execute([$input['email']]);
    $restoran = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    die(json_encode([
        "status" => "error",
        "message" => "Veritabanı hatası.",
        "details" => $DEBUG ? $e->getMessage() : null
    ], JSON_UNESCAPED_UNICODE));
}

if ($DEBUG) {
    error_log("🟢 [DEBUG] Veritabanı Kaydı: " . print_r($restoran, true));
}

// --- Restoran Var mı? ---
if (!$restoran) {
    http_response_code(401);
    die(json_encode([
        "status" => "error",
        "message" => $DEBUG ? "Restoran bulunamadı." : "Geçersiz email veya şifre."
    ], JSON_UNESCAPED_UNICODE));
}

// --- Şifre Doğrulama ---
if (!password_verify($input['sifre'], $restoran['sifre'])) {
    http_response_code(401);
    die(json_encode([
        "status" => "error",
        "message" => $DEBUG ? "Şifre eşleşmedi." : "Geçersiz email veya şifre."
    ], JSON_UNESCAPED_UNICODE));
}

if ($DEBUG) {
    error_log("✅ [DEBUG] Şifre doğrulandı, token oluşturuluyor...");
}

// --- JWT Token Oluştur ---
$restoran['rol'] = 'restoran';
$payload = [
    "iss"          => $config['jwt_issuer'],
    "iat"          => time(),
    "exp"          => time() + $config['jwt_expire'],
    "sub"          => $restoran['restoran_id'],
    "restoran_id"  => $restoran['restoran_id'], // 🔹 menuler.php ile uyumlu
    "email"        => $restoran['email'],
    "rol"          => $restoran['rol']
];

try {
    $token = JWT::encode($payload, $config['jwt_secret'], 'HS256');
} catch (Throwable $e) {
    http_response_code(500);
    die(json_encode([
        "status" => "error",
        "message" => "Token oluşturulamadı.",
        "details" => $DEBUG ? $e->getMessage() : null
    ], JSON_UNESCAPED_UNICODE));
}

// --- Şifreyi Çıkar ve Yanıt Gönder ---
unset($restoran['sifre']);

echo json_encode([
    "status"   => "success",
    "message"  => "Giriş başarılı.",
    "token"    => $token,
    "restoran" => $restoran
], JSON_UNESCAPED_UNICODE);

exit;
?>
