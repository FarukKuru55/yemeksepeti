<?php
// login.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . "/Api/db.php";           // PDO bağlantısı
require_once __DIR__ . "/vendor/autoload.php"; // Composer autoload
$config = require __DIR__ . "/config.php";

use Firebase\JWT\JWT;

// POST body oku
$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['email'], $input['sifre'])) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Email ve şifre gerekli"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Kullanıcıyı bul
$stmt = $pdo->prepare("SELECT * FROM musteriler WHERE email = ?");
$stmt->execute([$input['email']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Şifre kontrolü
if (!$user || !password_verify($input['sifre'], $user['sifre'])) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Geçersiz email veya şifre"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// JWT payload
$payload = [
    "iss"   => $config['jwt_issuer'],           // issuer
    "iat"   => time(),                          // issued at
    "exp"   => time() + $config['jwt_expire'], // expire time
    "sub"   => $user['musteri_id'],            // subject (id)
    "email" => $user['email'],
    "rol"   => $user['rol']                     // <-- buraya rol eklendi
];
$token = JWT::encode($payload, $config['jwt_secret'], 'HS256');

// Şifreyi geri dönme
unset($user['sifre']);

// Yanıt
echo json_encode([
    "status"  => "success",
    "token"   => $token,
    "musteri" => $user
], JSON_UNESCAPED_UNICODE);
