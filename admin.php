<?php
// Hata raporlamayı aç (geliştirme için)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// CORS ve JSON ayarları
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Preflight OPTIONS isteğine cevap
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// PDO ile veritabanı bağlantısı
require_once __DIR__ . "/Api/db.php"; // PDO bağlantın burada olmalı

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {

        // GET: tüm adminleri listele
        case "GET":
            $stmt = $pdo->query("SELECT admin_id, username, created_at FROM admin ORDER BY admin_id ASC");
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["status"=>"success","data"=>$admins], JSON_UNESCAPED_UNICODE);
            break;

        // POST: login veya yeni admin ekleme
        case "POST":
            $input = json_decode(file_get_contents("php://input"), true);
            if (!$input && !empty($_POST)) $input = $_POST;

            // LOGIN işlemi
            if(isset($input['action']) && $input['action'] === 'login'){
                if(empty($input['username']) || empty($input['password'])){
                    http_response_code(400);
                    echo json_encode(["status"=>"error","message"=>"Kullanıcı adı ve şifre gerekli"]);
                    exit;
                }

                $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
                $stmt->execute([$input['username']]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                if($admin && password_verify($input['password'], $admin['password'])){
                    echo json_encode(["status"=>"success","message"=>"Giriş başarılı","admin_id"=>$admin['admin_id']]);
                } else {
                    http_response_code(401);
                    echo json_encode(["status"=>"error","message"=>"Geçersiz kullanıcı adı veya şifre"]);
                }
                exit;
            }

            // Yeni admin ekleme
            if(isset($input['action']) && $input['action'] === 'add'){
                if(empty($input['username']) || empty($input['password'])){
                    http_response_code(400);
                    echo json_encode(["status"=>"error","message"=>"Kullanıcı adı ve şifre gerekli"]);
                    exit;
                }

                $hash = password_hash($input['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO admin (username, password) VALUES (?, ?)");
                $ok = $stmt->execute([$input['username'], $hash]);

                echo json_encode($ok 
                    ? ["status"=>"success","message"=>"Admin eklendi","admin_id"=>$pdo->lastInsertId()] 
                    : ["status"=>"error","message"=>"Eklenemedi"]);
                exit;
            }

            http_response_code(400);
            echo json_encode(["status"=>"error","message"=>"Geçersiz action"]);
            break;

        default:
            http_response_code(405);
            echo json_encode(["status"=>"error","message"=>"Geçersiz method"]);
    }

} catch(Exception $e){
    http_response_code(500);
    echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
}
?>