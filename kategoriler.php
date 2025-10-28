<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // DEĞİŞTİ: PUT/DELETE kaldırıldı
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // DEĞİŞTİ: Authorization eklendi

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// === GEREKLİ KÜTÜPHANELER (EKLENDİ) ===
require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/Api/db.php"; 
$jwtAyarlari = require __DIR__ . "/config.php";

use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// --- GÜVENLİK FONKSİYONU (EKLENDİ) ---
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
        return (array)$decoded;
    } 
    catch (ExpiredException $e) { 
        throw new Exception("Oturum süresi doldu.", 401);
    } 
    catch (SignatureInvalidException $e) { 
        throw new Exception("Geçersiz token imzası.", 401);
    } catch (Exception $e) {
        throw new Exception("Token çözümlenemedi: " . $e->getMessage(), 401);
    }
}
// --- GÜVENLİK FONKSİYONU SONU ---

$method = $_SERVER['REQUEST_METHOD'];

try {
    if($method === "GET") {
        $id = $_GET['kategori_id'] ?? $_GET['id'] ?? null;

        if($id) {
            $stmt = $pdo->prepare("SELECT * FROM kategoriler WHERE kategori_id=?");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if($data) {
                echo json_encode(["status"=>"success","message"=>"Kategori bulundu","data"=>$data], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(404);
                echo json_encode(["status"=>"error","message"=>"Kategori bulunamadı"]);
            }
        } else {
            $stmt = $pdo->query("SELECT * FROM kategoriler ORDER BY kategori_id ASC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["status"=>"success","message"=>"Kategoriler listelendi","data"=>$data], JSON_UNESCAPED_UNICODE);
        }

    // 🔹 POST, PUT, DELETE İÇİN TEK BLOK (TAMAMEN DEĞİŞTİ)
    } elseif($method === "POST") {
       $kullanici = get_user_data_from_token($jwtAyarlari['jwt_secret']);
        if ($kullanici['rol'] !== 'admin') {
            http_response_code(403); 
            echo json_encode(["status" => "error", "message" => "Yetkisiz işlem: Sadece adminler işlem yapabilir."], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 2. VERİ ALMA VE TÜNELLEME
        $input = json_decode(file_get_contents("php://input"), true);
        $action = $input['_method'] ?? 'POST'; // Tünelleme

        if ($action === 'PUT') {
            
           
            $id = $input['kategori_id'] ?? null;
            if(!$id) { http_response_code(400); echo json_encode(["status"=>"error","message"=>"Güncelleme için kategori_id gerekli"]); exit; }

            
            $fieldsToUpdate = [];
            $params = [];

            if (isset($input['kategori_adi'])) {
                $fieldsToUpdate[] = "kategori_adi = ?";
                $params[] = $input['kategori_adi'];
            }
            if (isset($input['genel_kategori_id'])) {
                $fieldsToUpdate[] = "genel_kategori_id = ?";
                $params[] = $input['genel_kategori_id'];
            }
            
            if (count($fieldsToUpdate) === 0) {
                 throw new Exception("Güncellenecek en az bir alan (kategori_adi vb.) gönderilmelidir.", 400);
            }

            $sql = "UPDATE kategoriler SET " . implode(", ", $fieldsToUpdate) . " WHERE kategori_id = ?";
            $params[] = $id; 

            $stmt = $pdo->prepare($sql);
            $ok = $stmt->execute($params);

            echo json_encode($ok?["status"=>"success","message"=>"Kategori güncellendi"]:["status"=>"error","message"=>"Güncellenemedi"]);

        // EĞER EYLEM SİLME İSE (Eski DELETE)
        } elseif ($action === 'DELETE') {
            
            // DEĞİŞTİ: ID artık $_GET'ten değil, $input (body) içinden alınıyor
            $id = $input['kategori_id'] ?? null;
            if(!$id) { http_response_code(400); echo json_encode(["status"=>"error","message"=>"Silme için kategori_id gerekli"]); exit; }
            
            $stmt = $pdo->prepare("DELETE FROM kategoriler WHERE kategori_id=?");
            $ok = $stmt->execute([$id]);
            echo json_encode($ok?["status"=>"success","message"=>"Kategori silindi"]:["status"=>"error","message"=>"Silinemedi"]);

        } else {
            // (Bu senin orijinal POST kodun, sadece güvenlik kontrolü eklendi)
            if(!isset($input['kategori_adi'])) {
                http_response_code(400);
                echo json_encode(["status"=>"error","message"=>"kategori_adi gerekli"]);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO kategoriler (kategori_adi, genel_kategori_id) VALUES (?, ?)");
            $ok = $stmt->execute([$input['kategori_adi'], $input['genel_kategori_id'] ?? null]);
            echo json_encode($ok?["status"=>"success","message"=>"Kategori eklendi","id"=>$pdo->lastInsertId()]:["status"=>"error","message"=>"Eklenemedi"]);
        }
    } else {
        http_response_code(405);
        echo json_encode(["status"=>"error","message"=>"Geçersiz method"]);
    }
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(["status"=>"error","message"=>"Sunucu hatası: ".$e->getMessage()]);
}
?>
