<?php
// siparisler.php - GÜNCELLENMİŞ SÜRÜM (Metod Tünelleme Eklendi)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/Api/db.php";
require_once __DIR__ . "/vendor/autoload.php";
$config = require __DIR__ . "/config.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// === CORS BAŞLIKLARI (DEĞİŞTİ) ===
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- TOKEN FONKSİYONU ---
function getBearerToken() {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
    if (!$header && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower($name) === 'authorization') $header = $value;
        }
    }
    if (!$header) return null;
    if (preg_match('/Bearer\s(\S+)/', $header, $matches)) return $matches[1];
    return null;
}

$token = getBearerToken();
if (!$token) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Token gerekli"]);
    exit;
}

try {
    $decoded = JWT::decode($token, new Key($config['jwt_secret'], 'HS256'));
    $rol = $decoded->rol ?? null;
    $musteri_id = $decoded->sub ?? null;
    $restoran_id_token = $decoded->restoran_id ?? null;
    if (!$rol) throw new Exception("Token geçersiz");
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(["status"=>"error", "message"=>"Geçersiz token", "details"=>$e->getMessage()]);
    exit;
}

// --- CRUD ---
$method = $_SERVER['REQUEST_METHOD'];

try {

    // ----------------------------------------------------
    // GET
    // ----------------------------------------------------
    if ($method === "GET") {

        $siparis_id = $_GET['id'] ?? null;

        if ($rol === "musteri") {

            if ($siparis_id) {
                $stmt = $pdo->prepare("SELECT * FROM siparisler WHERE siparis_id=? AND musteri_id=?");
                $stmt->execute([$siparis_id, $musteri_id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM siparisler WHERE musteri_id=? ORDER BY siparis_id DESC");
                $stmt->execute([$musteri_id]);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

        } elseif ($rol === "restoran") {

            if ($siparis_id) {
                $stmt = $pdo->prepare("SELECT * FROM siparisler WHERE siparis_id=? AND restoran_id=?");
                $stmt->execute([$siparis_id, $restoran_id_token]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM siparisler WHERE restoran_id=? ORDER BY siparis_id DESC");
                $stmt->execute([$restoran_id_token]);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

        } elseif ($rol === "admin") {

            if ($siparis_id) {
                $stmt = $pdo->prepare("SELECT * FROM siparisler WHERE siparis_id=?");
                $stmt->execute([$siparis_id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->query("SELECT * FROM siparisler ORDER BY siparis_id DESC");
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

        } else {
            http_response_code(403);
            echo json_encode(["status"=>"error", "message"=>"Yetkiniz yok"]);
            exit;
        }

        if (!$data) {
            http_response_code(404);
            echo json_encode(["status"=>"error", "message"=>"Sipariş bulunamadı"]);
        } else {
            echo json_encode(["status"=>"success", "data"=>$data], JSON_UNESCAPED_UNICODE);
        }

    // ----------------------------------------------------
    // POST (Tünellenmiş POST / PUT / DELETE)
    // ----------------------------------------------------
    } elseif ($method === "POST") {

        $input = json_decode(file_get_contents("php://input"), true);
        if ($input === null) {
            http_response_code(400);
            echo json_encode(["status"=>"error", "message"=>"Geçersiz JSON verisi"]);
            exit;
        }

        $action = $input['_method'] ?? 'POST';

        // -------------------------------
        // POST → Yeni sipariş ekle
        // -------------------------------
        if ($action === 'POST') {

            if (!isset($input['restoran_id'], $input['toplam_tutar'])) {
                http_response_code(400);
                echo json_encode(["status"=>"error","message"=>"Eksik alan (restoran_id, toplam_tutar)"]);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO siparisler (musteri_id, restoran_id, toplam_tutar, durum, tarih)
                                   VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([
                $musteri_id,
                $input['restoran_id'],
                $input['toplam_tutar'],
                $input['durum'] ?? 'hazırlanıyor'
            ]);

            http_response_code(201);
            echo json_encode(["status"=>"success","message"=>"Sipariş eklendi","siparis_id"=>$pdo->lastInsertId()]);

        // -------------------------------
        // PUT → Sipariş güncelle
        // -------------------------------
        } elseif ($action === 'PUT') {

            if (!isset($input['siparis_id'])) {
                http_response_code(400);
                echo json_encode(["status"=>"error","message"=>"siparis_id gerekli"]);
                exit;
            }

            if ($rol === "musteri") {

                if (($input['durum'] ?? null) !== 'iptal') {
                    http_response_code(403);
                    echo json_encode(["status"=>"error","message"=>"Müşteri sadece siparişi iptal edebilir"]);
                    exit;
                }

                $stmt = $pdo->prepare("UPDATE siparisler SET durum='iptal' WHERE siparis_id=? AND musteri_id=?");
                $stmt->execute([$input['siparis_id'], $musteri_id]);

            } elseif ($rol === "restoran") {

                // 🔥 GÜNCEL → toplam_tutar eklendi
                $stmt = $pdo->prepare("
                    UPDATE siparisler 
                    SET 
                        durum = COALESCE(?, durum),
                        toplam_tutar = COALESCE(?, toplam_tutar)
                    WHERE siparis_id=? AND restoran_id=?
                ");

                $stmt->execute([
                    $input['durum'] ?? null,
                    $input['toplam_tutar'] ?? null,
                    $input['siparis_id'],
                    $restoran_id_token
                ]);

            } elseif ($rol === "admin") {

                // 🔥 GÜNCEL → toplam_tutar eklendi
                $stmt = $pdo->prepare("
                    UPDATE siparisler 
                    SET 
                        durum = COALESCE(?, durum),
                        toplam_tutar = COALESCE(?, toplam_tutar)
                    WHERE siparis_id=?
                ");

                $stmt->execute([
                    $input['durum'] ?? null,
                    $input['toplam_tutar'] ?? null,
                    $input['siparis_id']
                ]);

            } else {
                http_response_code(403);
                echo json_encode(["status"=>"error","message"=>"Yetkiniz yok"]);
                exit;
            }

            echo json_encode(["status"=>"success","message"=>"Sipariş güncellendi"]);

        // -------------------------------
        // DELETE → Sipariş sil
        // -------------------------------
        } elseif ($action === 'DELETE') {

            if ($rol !== "admin") {
                http_response_code(403);
                echo json_encode(["status"=>"error","message"=>"Sadece admin silebilir"]);
                exit;
            }

            $siparis_id = $input['siparis_id'] ?? null;
            if (!$siparis_id) {
                http_response_code(400);
                echo json_encode(["status"=>"error","message"=>"siparis_id gerekli"]);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM siparisler WHERE siparis_id=?");
            $stmt->execute([$siparis_id]);

            echo json_encode(["status"=>"success","message"=>"Sipariş silindi"]);

        } else {
            http_response_code(400);
            echo json_encode(["status"=>"error","message"=>"Geçersiz _method"]);
        }

    } else {
        http_response_code(405);
        echo json_encode(["status"=>"error","message"=>"Sadece GET ve POST destekleniyor"]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
}
?>