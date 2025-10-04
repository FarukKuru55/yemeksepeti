<?php
// genel_kategori.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . "/Api/db.php";
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === "GET") {
        if (isset($_GET['id'])) {
            // Tek kategori getir
            $stmt = $pdo->prepare("SELECT * FROM genel_kategori WHERE genel_kategori_id = ?");
            $stmt->execute([$_GET['id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                echo json_encode(["status"=>"success","message"=>"Tek kategori getirildi","data"=>$data], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(["status"=>"error","message"=>"Kategori bulunamadı"]);
            }
        } else {
            // Tüm kategorileri getir
            $stmt = $pdo->query("SELECT * FROM genel_kategori ORDER BY genel_kategori_id ASC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["status"=>"success","message"=>"Genel kategoriler getirildi","data"=>$data], JSON_UNESCAPED_UNICODE);
        }

    } elseif ($method === "POST") {
        $input = json_decode(file_get_contents("php://input"), true);
        if (!isset($input['ad'])) {
            echo json_encode(["status"=>"error","message"=>"Kategori adı gerekli"]); exit;
        }
        $stmt = $pdo->prepare("INSERT INTO genel_kategori (ad) VALUES (?)");
        $ok = $stmt->execute([$input['ad']]);
        echo json_encode(["status"=>$ok?"success":"error","message"=>$ok?"Kategori eklendi":"Eklenemedi"]);

    } elseif ($method === "PUT") {
        parse_str(file_get_contents("php://input"), $input);
        if (!isset($input['genel_kategori_id'])) {
            echo json_encode(["status"=>"error","message"=>"genel_kategori_id gerekli"]); exit;
        }
        $stmt = $pdo->prepare("UPDATE genel_kategori SET ad=? WHERE genel_kategori_id=?");
        $ok = $stmt->execute([
            $input['ad'] ?? null,
            $input['genel_kategori_id']
        ]);
        echo json_encode(["status"=>$ok?"success":"error","message"=>$ok?"Güncellendi":"Güncellenemedi"]);

    } elseif ($method === "DELETE") {
        parse_str(file_get_contents("php://input"), $input);
        if (!isset($input['genel_kategori_id'])) {
            echo json_encode(["status"=>"error","message"=>"genel_kategori_id gerekli"]); exit;
        }
        $stmt = $pdo->prepare("DELETE FROM genel_kategori WHERE genel_kategori_id=?");
        $ok = $stmt->execute([$input['genel_kategori_id']]);
        echo json_encode(["status"=>$ok?"success":"error","message"=>$ok?"Silindi":"Silinemedi"]);
    }
} catch (Exception $e) {
    echo json_encode(["status"=>"error","message"=>"Sunucu hatası: ".$e->getMessage()]);
}