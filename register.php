<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/Api/db.php";

// JSON verisini al
$input = json_decode(file_get_contents("php://input"), true);

// Gerekli alanlar var mı?
if (!isset($input['ad'], $input['email'], $input['sifre'])) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Ad, email ve şifre zorunludur"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Aynı email var mı kontrol et
$stmt = $pdo->prepare("SELECT * FROM musteriler WHERE email = ?");
$stmt->execute([$input['email']]);
if ($stmt->fetch()) {
    http_response_code(409); 
    echo json_encode([
        "status" => "error",
        "message" => "Bu email zaten kayıtlı"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}


$hashedPassword = password_hash($input['sifre'], PASSWORD_DEFAULT);


$stmt = $pdo->prepare("
    INSERT INTO musteriler (ad, soyad, email, sifre, telefon, adres)
    VALUES (?, ?, ?, ?, ?, ?)
");

$ok = $stmt->execute([
    $input['ad'],
    $input['soyad'] ?? null ,
    $input['email'],
    $hashedPassword,
    $input['telefon'] ?? null,
    $input['adres'] ?? null
]);

if ($ok) {
    echo json_encode([
        "status" => "success",
        "message" => "Kayıt başarılı",
        "id" => $pdo->lastInsertId()
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Kayıt sırasında hata oluştu"
    ], JSON_UNESCAPED_UNICODE);
}
