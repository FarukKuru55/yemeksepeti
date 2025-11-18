<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/Api/db.php";
$jwtAyarlari = require __DIR__ . "/config.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

function get_user_data_from_token($secret_key)
{
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

    if (!$authHeader && function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? null;
    }

    if (!$authHeader) {
        throw new Exception("Authorization başlığı eksik.", 401);
    }

    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        throw new Exception("Token formatı geçersiz.", 401);
    }

    $token = $matches[1];

    try {
        return (array)JWT::decode($token, new Key($secret_key, 'HS256'));
    } catch (ExpiredException $e) {
        throw new Exception("Oturum süresi doldu.", 401);
    } catch (SignatureInvalidException $e) {
        throw new Exception("Token imzası geçersiz.", 401);
    } catch (Exception $e) {
        throw new Exception("Token çözümlenemedi: ".$e->getMessage(), 401);
    }
}

try {

    $kullanici = get_user_data_from_token($jwtAyarlari['jwt_secret']);
    $musteri_id = $kullanici['sub'] ?? null;
    $rol = $kullanici['rol'] ?? null;

    if ($rol !== 'musteri') {
        throw new Exception("Bu işlem için müşteri yetkisi gerekli.", 403);
    }

    $method = $_SERVER['REQUEST_METHOD'];

    // -----------------------------------------------------
    // GET: Sepeti Listele
    // -----------------------------------------------------
    if ($method === "GET") {

        $stmt = $pdo->prepare("SELECT * FROM sepet WHERE musteri_id = ?");
        $stmt->execute([$musteri_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["status"=>"success", "data" => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // POST için JSON
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) throw new Exception("Geçersiz JSON.", 400);

    $action = $input["_method"] ?? "POST";

    // -----------------------------------------------------
    // POST: Sepete Ürün Ekle
    // -----------------------------------------------------
    if ($action === "POST") {

        if (!isset($input['menu_id'], $input['adet'], $input['toplam_tutar'])) {
            throw new Exception("Eksik bilgi. (menu_id, adet, toplam_tutar zorunlu)", 400);
        }

        $stmt = $pdo->prepare("
            INSERT INTO sepet (musteri_id, menu_id, adet, toplam_tutar)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $musteri_id,
            $input['menu_id'],
            $input['adet'],
            $input['toplam_tutar']
        ]);

        echo json_encode(["status"=>"success", "message"=>"Ürün sepete eklendi", "id"=>$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -----------------------------------------------------
    // PUT: Adet Güncelle
    // -----------------------------------------------------
    if ($action === "PUT") {

        if (!isset($input['sepet_id'], $input['adet'])) {
            throw new Exception("Eksik bilgi. (sepet_id ve adet zorunlu)", 400);
        }

        $stmt = $pdo->prepare("
            UPDATE sepet SET adet = ?
            WHERE sepet_id = ? AND musteri_id = ?
        ");

        $stmt->execute([
            $input['adet'],
            $input['sepet_id'],
            $musteri_id
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception("Güncelleme başarısız: Ürün size ait değil.", 404);
        }

        echo json_encode(["status"=>"success", "message"=>"Güncellendi"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -----------------------------------------------------
    // DELETE: Ürün Sil
    // -----------------------------------------------------
    if ($action === "DELETE") {

        if (!isset($input['sepet_id'])) {
            throw new Exception("sepet_id gerekli.", 400);
        }

        $stmt = $pdo->prepare("DELETE FROM sepet WHERE sepet_id = ? AND musteri_id = ?");
        $stmt->execute([
            $input['sepet_id'],
            $musteri_id
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception("Silinemedi: Ürün bulunamadı veya size ait değil.", 404);
        }

        echo json_encode(["status"=>"success", "message"=>"Silindi"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    throw new Exception("Geçersiz method.", 405);

} catch (Exception $e) {

    $code = $e->getCode();
    if ($code < 400 || $code > 599) $code = 500;

    http_response_code($code);

    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
