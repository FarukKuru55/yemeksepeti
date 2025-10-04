<?php
// degerlendirmeler.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
require_once __DIR__ . "/Api/db.php";
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === "GET") {
        
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT * FROM degerlendirmeler WHERE degerlendirme_id = ?");
            $stmt->execute([$_GET['id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($data) {
                echo json_encode(["status"=>"success","message"=>"Tek değerlendirme getirildi","data"=>$data], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(["status"=>"error","message"=>"Kayıt bulunamadı"]);
            }
        } else {
            $stmt = $pdo->query("SELECT * FROM degerlendirmeler ORDER BY degerlendirme_id ASC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["status"=>"success","message"=>"Tüm değerlendirmeler getirildi","data"=>$data], JSON_UNESCAPED_UNICODE);
        }

    } elseif ($method === "POST") {
        $input = json_decode(file_get_contents("php://input"), true);
        if (!isset($input['musteri_id'], $input['restoran_id'], $input['puan'])) {
            echo json_encode(["status"=>"error","message"=>"Eksik bilgi"]); exit;
        }
        $stmt = $pdo->prepare("INSERT INTO degerlendirmeler (musteri_id, restoran_id, puan, yorum, tarih) VALUES (?, ?, ?, ?, NOW())");
        $ok = $stmt->execute([
            $input['musteri_id'],
            $input['restoran_id'],
            $input['puan'],
            $input['yorum'] ?? null
        ]);
        echo json_encode(["status"=>$ok?"success":"error","message"=>$ok?"Değerlendirme eklendi":"Eklenemedi"]);

    } elseif ($method === "PUT") {
        parse_str(file_get_contents("php://input"), $input);
        if (!isset($input['degerlendirme_id'])) {
            echo json_encode(["status"=>"error","message"=>"degerlendirme_id gerekli"]); exit;
        }
        $stmt = $pdo->prepare("UPDATE degerlendirmeler SET musteri_id=?, restoran_id=?, puan=?, yorum=? WHERE degerlendirme_id=?");
        $ok = $stmt->execute([
            $input['musteri_id'] ?? null,
            $input['restoran_id'] ?? null,
            $input['puan'] ?? null,
            $input['yorum'] ?? null,
            $input['degerlendirme_id']
        ]);
        echo json_encode(["status"=>$ok?"success":"error","message"=>$ok?"Güncellendi":"Güncellenemedi"]);

    } elseif ($method === "DELETE") {
        parse_str(file_get_contents("php://input"), $input);
        if (!isset($input['degerlendirme_id'])) {
            echo json_encode(["status"=>"error","message"=>"degerlendirme_id gerekli"]); exit;
        }
        $stmt = $pdo->prepare("DELETE FROM degerlendirmeler WHERE degerlendirme_id=?");
        $ok = $stmt->execute([$input['degerlendirme_id']]);
        echo json_encode(["status"=>$ok?"success":"error","message"=>$ok?"Silindi":"Silinemedi"]);
    }
} catch (Exception $e) {
    echo json_encode(["status"=>"error","message"=>"Sunucu hatası: ".$e->getMessage()]);
}
