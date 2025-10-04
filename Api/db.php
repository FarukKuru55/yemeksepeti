<?php
$host = "127.0.0.1";   // localhost yerine 127.0.0.1 kullan => TCP bağlantısı zorlanır
$port = 3307;          // HeidiSQL'de bağlandığın port
$dbname = "yemeksepeti";
$username = "root";
$password = "";   

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

try { 
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo json_encode(["status" => "ok", "message" => "DB bağlandı"]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        "status" => "error",
        "message" => "DB bağlantı hatası",
        "details" => $e->getMessage()
    ]);
    exit;
}
?>
