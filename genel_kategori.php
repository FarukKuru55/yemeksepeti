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

require_once __DIR__ . "/Api/db.php";
require_once __DIR__ . "/vendor/autoload.php";
$jwtAyarlari = require __DIR__ . "/config.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\ExpiredSignatureException;

// --- GÜVENLİK FONKSİYONU ---
function get_user_data_from_token($secret_key)
{
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    if (!$authHeader) {
        throw new Exception("Authorization başlığı eksik.", 401);
    }

    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
    } else {
        throw new Exception("Token formatı geçersiz.", 401);
    }

    try {
        $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
        return (array) $decoded;
    } catch (ExpiredSignatureException $e) {
        throw new Exception(message: "Oturum süresi doldu.", 401);
    } catch (SignatureInvalidException $e) {
        throw new Exception("Geçersiz token imzası.", 401);
    } catch (Exception $e) {
        throw new Exception("Token çözümlenemedi: " . $e->getMessage(), 401);
    }
}

$method = $_SERVER['REQUEST_METHOD'];

try {

    if ($method === "GET") {

        $id = $_GET['genel_kategori_id'] ?? $_GET['id'] ?? null;

        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM genel_kategoriler WHERE genel_kategori_id = ?");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                echo json_encode(["status" => "success", "message" => "Kategori bulundu", "data" => $data], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Kategori bulunamadı"], JSON_UNESCAPED_UNICODE);
            }

        } else {
            $stmt = $pdo->query("SELECT * FROM genel_kategoriler");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["status" => "success", "message" => "Kategoriler listelendi", "data" => $data], JSON_UNESCAPED_UNICODE);
        }

    } elseif ($method === "POST") {

        $kullanici = get_user_data_from_token($jwtAyarlari['jwt_secret']);

        if ($kullanici['rol'] !== 'admin') {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "Yetkiniz yok"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $input = json_decode(file_get_contents("php://input"), true);
        $action = $input['_method'] ?? 'POST';

        if ($action === 'PUT') {
            $id = $input['genel_kategori_id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "genel_kategori_id gerekli"], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $fieldsToUpdate = [];
            $params = [];

            if (isset($input['kategori_adi'])) {
                $fieldsToUpdate[] = "kategori_adi = ?";
                $params[] = $input['kategori_adi'];
            }

            if (count($fieldsToUpdate) === 0) {
                throw new Exception("Güncellenecek en az bir alan belirtmelisiniz.", 400);
            }

            $sql = "UPDATE genel_kategoriler SET " . implode(", ", $fieldsToUpdate) . " WHERE genel_kategori_id = ?";
            $params[] = $id;

            $stmt = $pdo->prepare($sql);
            $ok = $stmt->execute($params);

            echo json_encode(
                $ok ? ["status" => "success", "message" => "Kategori güncellendi"]
                    : ["status" => "error", "message" => "Güncelleme başarısız"],
                JSON_UNESCAPED_UNICODE
            );

        } elseif ($action === 'DELETE') {

            $id = $input['genel_kategori_id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "genel_kategori_id gerekli"], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM genel_kategoriler WHERE genel_kategori_id = ?");
            $ok = $stmt->execute([$id]);

            echo json_encode(
                $ok ? ["status" => "success", "message" => "Kategori silindi"]
                    : ["status" => "error", "message" => "Silme başarısız"],
                JSON_UNESCAPED_UNICODE
            );

        } else {

            if (!isset($input['kategori_adi'])) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "kategori_adi gerekli"], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO genel_kategoriler (kategori_adi) VALUES (?)");
            $ok = $stmt->execute([$input['kategori_adi']]);

            echo json_encode(
                $ok ? ["status" => "success", "message" => "Kategori eklendi", "id" => $pdo->lastInsertId()]
                    : ["status" => "error", "message" => "Ekleme başarısız"],
                JSON_UNESCAPED_UNICODE
            );
        }

    } else {
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "İzin verilmeyen yöntem"], JSON_UNESCAPED_UNICODE);
        exit;
    }

} catch (Throwable $e) {
    $statusCode = $e->getCode() ?: 500;
    if ($statusCode < 100 || $statusCode > 599) {
        $statusCode = 500;
    }
    http_response_code($statusCode);
    echo json_encode(["status" => "error", "message" => "Sunucu hatası: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

?>