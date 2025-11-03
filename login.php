<?php
ob_start();

use Firebase\JWT\JWT;

// --- CORS Başlıkları ---
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// CORS preflight isteğini direkt sonlandır
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . "/vendor/autoload.php";
    require_once __DIR__ . "/Api/db.php"; // $pdo bağlantısını sağlar
    $jwtAyarlari = require __DIR__ . "/config.php";
    

    if (
        !is_array($jwtAyarlari) ||
        empty($jwtAyarlari['jwt_secret']) ||
        empty($jwtAyarlari['jwt_issuer']) ||
        empty($jwtAyarlari['jwt_expire'])
    ) {
        throw new Exception("Sunucu konfigürasyon hatası: JWT ayarları eksik.");
    }

    $input = json_decode(file_get_contents("php://input"), true);

    if (!is_array($input) || empty($input['email']) || empty($input['sifre'])) {
        http_response_code(400);
        echo json_encode([
            "status"  => "error",
            "message" => "Email ve şifre alanları zorunludur."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $email = strtolower(trim($input['email']));
    $sifre = trim($input['sifre']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Geçersiz email formatı."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM musteriler WHERE LOWER(email) = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($sifre, $user['sifre'])) {
        http_response_code(401);
        echo json_encode([
            "status"  => "error",
            "message" => "Geçersiz email veya şifre."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $payload = [
        "iss"   => $jwtAyarlari['jwt_issuer'],
        "iat"   => time(),
        "exp"   => time() + $jwtAyarlari['jwt_expire'],
        "sub"   => $user['musteri_id'],
        "email" => $user['email'],
        "rol"   => $user['rol'] ?? "musteri" // 'rol' yoksa 'musteri' ata
    ];

    $token = JWT::encode($payload, $jwtAyarlari['jwt_secret'], 'HS256');


    unset($user['sifre']);
    
    ob_end_clean();


    echo json_encode([
        "status"   => "success",
        "token"    => $token,
        "musteri"  => $user
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);


} catch (PDOException $e) {
    ob_end_clean(); 
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Veritabanı hatası: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);

} catch (\InvalidArgumentException $e) {
    ob_end_clean(); 
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "JWT yapılandırma hatası."], JSON_UNESCAPED_UNICODE);

} catch (\DomainException $e) {
    ob_end_clean(); 
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "JWT imzalama/doğrulama hatası."], JSON_UNESCAPED_UNICODE);

} catch (\UnexpectedValueException $e) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Geçersiz JWT verisi."], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Beklenmedik Sunucu Hatası: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>