<?php
// sepet.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . "/Api/db.php";
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === "GET") {
        // Tüm sepeti getir
        $stmt = $pdo->query("SELECT * FROM sepet ORDER BY sepet_id ASC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["status"=>"success","message"=>"Sepet getirildi","data"=>$data], JSON_UNESCAPED_UNICODE);

    } elseif ($method === "POST") {
        // Yeni ürün sepete ekle
        $input = json_decode(file_get_contents("php://input"), true);

        if (!isset($input['musteri_id'], $input['urun_id'], $input['adet'])) {
            echo json_encode(["status"=>"error","message"=>"Eksik bilgi"]);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO sepet (musteri_id, urun_id, adet, eklenme_tarihi) VALUES (?, ?, ?, NOW())");
        $ok = $stmt->execute([
            $input['musteri_id'],
            $input['urun_id'],
            $input['adet']
        ]);

        echo json_encode(["status"=>$ok?"success":"error","message"=>$ok?"Ürün sepete eklendi":"Ürün eklenemedi"]);

    } elseif ($method === "PUT") {
        // Sepet güncelle (ör. adet değiştir)
        parse_str(file_get_contents("php://input"), $input);

        if (!isset($input['sepet_id'])) {
            echo json_encode(["status"=>"error","message"=>"sepet_id gerekli"]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE sepet SET musteri_id=?, urun_id=?, adet=? WHERE sepet_id=?");
        $ok = $stmt->execute([
            $input['musteri_id'] ?? null,
            $input['urun_id'] ?? null,
            $input['adet'] ?? null,
            $input['sepet_id']
        ]);

        echo json_encode(["status"=>$ok?"success":"error","message"=>$ok?"Sepet güncellendi":"Güncellenemedi"]);

    } elseif ($method === "DELETE") {
        // Sepetten ürün sil
        parse_str(file_get_contents("php://input"), $input);

        if (!isset($input['sepet_id'])) {
            echo json_encode(["status"=>"error","message"=>"sepet_id gerekli"]);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM sepet WHERE sepet_id=?");
        $ok = $stmt->execute([$input['sepet_id']]);

        echo json_encode(["status"=>$ok?"success":"error","message"=>$ok?"Ürün sepetten silindi":"Silinemedi"]);
    }
} catch (Exception $e) {
    echo json_encode(["status"=>"error","message"=>"Sunucu hatası: ".$e->getMessage()]);
}
