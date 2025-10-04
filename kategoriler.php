<?php
// kategoriler.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . "/Api/db.php";
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

    } elseif($method === "POST") {
        $input = json_decode(file_get_contents("php://input"), true);
        if(!isset($input['kategori_adi'])) {
            http_response_code(400);
            echo json_encode(["status"=>"error","message"=>"kategori_adi gerekli"]);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO kategoriler (kategori_adi, genel_kategori_id) VALUES (?, ?)");
        $ok = $stmt->execute([$input['kategori_adi'], $input['genel_kategori_id'] ?? null]);
        echo json_encode($ok?["status"=>"success","message"=>"Kategori eklendi","id"=>$pdo->lastInsertId()]:["status"=>"error","message"=>"Eklenemedi"]);

    } elseif($method === "PUT") {
        $id = $_GET['kategori_id'] ?? $_GET['id'] ?? null;
        if(!$id) { http_response_code(400); echo json_encode(["status"=>"error","message"=>"kategori_id gerekli"]); exit; }
        $input = json_decode(file_get_contents("php://input"), true);
        $stmt = $pdo->prepare("UPDATE kategoriler SET kategori_adi=?, genel_kategori_id=? WHERE kategori_id=?");
        $ok = $stmt->execute([
            $input['kategori_adi'] ?? null,
            $input['genel_kategori_id'] ?? null,
            $id
        ]);
        echo json_encode($ok?["status"=>"success","message"=>"Kategori güncellendi"]:["status"=>"error","message"=>"Güncellenemedi"]);

    } elseif($method === "DELETE") {
        $id = $_GET['kategori_id'] ?? $_GET['id'] ?? null;
        if(!$id) { http_response_code(400); echo json_encode(["status"=>"error","message"=>"kategori_id gerekli"]); exit; }
        $stmt = $pdo->prepare("DELETE FROM kategoriler WHERE kategori_id=?");
        $ok = $stmt->execute([$id]);
        echo json_encode($ok?["status"=>"success","message"=>"Kategori silindi"]:["status"=>"error","message"=>"Silinemedi"]);

    } else {
        http_response_code(405);
        echo json_encode(["status"=>"error","message"=>"Geçersiz method"]);
    }
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(["status"=>"error","message"=>"Sunucu hatası: ".$e->getMessage()]);
}
?>
