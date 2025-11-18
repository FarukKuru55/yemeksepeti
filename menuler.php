<?php
// ✅ menuler.php (Tam Düzeltildi, Menü + Resim Tek Sayfa)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/Api/db.php";
require_once __DIR__ . "/vendor/autoload.php";
$config = require_once __DIR__ . "/config.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// --- CORS Ayarları ---
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Token Fonksiyonu ---
function getBearerToken() {
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    }
    foreach ($headers as $name => $value) {
        if (strtolower($name) === 'authorization') {
            if (preg_match('/Bearer\s(\S+)/', $value, $matches)) {
                return $matches[1];
            }
        }
    }
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            return $matches[1];
        }
    }
    return null;
}

// --- Token Doğrulama ---
$token = getBearerToken();
if (!$token) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Authorization başlığı eksik."]);
    exit;
}

try {
    $decoded = JWT::decode($token, new Key($config['jwt_secret'], 'HS256'));
    $rol = $decoded->rol ?? null;
    $restoran_id_token = $decoded->restoran_id ?? null;

    if (!$rol) throw new Exception("Token geçersiz veya rol bulunamadı.");
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode([
        "status" => "error",
        "message" => "Geçersiz token.",
        "details" => $e->getMessage()
    ]);
    exit;
}



$method = $_SERVER['REQUEST_METHOD'];
$columns = "menu_id, restoran_id, ad, aciklama, fiyat, resim_url";

try {
    // 🔹 GET (Veri Listeleme)
    if ($method === "GET") {
        $menu_id = $_GET['id'] ?? null;

        if ($rol === "restoran") {
            if ($menu_id) {
                $stmt = $pdo->prepare("SELECT $columns FROM menuler WHERE menu_id=? AND restoran_id=?");
                $stmt->execute([$menu_id, $restoran_id_token]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->prepare("SELECT $columns FROM menuler WHERE restoran_id=? ORDER BY menu_id DESC");
                $stmt->execute([$restoran_id_token]);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } elseif ($rol === "admin") {
            if ($menu_id) {
                $stmt = $pdo->prepare("SELECT $columns FROM menuler WHERE menu_id=?");
                $stmt->execute([$menu_id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->query("SELECT $columns FROM menuler ORDER BY menu_id DESC");
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "Yetkiniz yok"]);
            exit;
        }

        // --- Resim URL tam hale getir ---
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        $baseUrl = "{$protocol}://{$host}{$basePath}/";

        if (isset($data[0])) {
            foreach ($data as &$menu) {
                $menu['resim_url_tam'] = !empty($menu['resim_url']) ? $baseUrl . $menu['resim_url'] : null;
            }
        } elseif (isset($data['menu_id'])) {
            $data['resim_url_tam'] = !empty($data['resim_url']) ? $baseUrl . $data['resim_url'] : null;
        }

        echo json_encode(["status" => "success", "data" => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 🔹 POST (Ekle / Güncelle / Sil)
    elseif ($method === "POST") {
        $input = json_decode(file_get_contents("php://input"), true);
        $isJson = true;
        if ($input === null) {
            $isJson = false;
            $input = $_POST; // Form-data ile gelirse
        }

        $action = $input['_method'] ?? 'POST';

        // --- PUT (Güncelleme)
        if ($action === 'PUT') {
            if (!in_array($rol, ['restoran', 'admin'])) {
                http_response_code(403);
                echo json_encode(["status" => "error", "message" => "Yetkiniz yok"]);
                exit;
            }

            $menu_id = $input['menu_id'] ?? null;
            if (!$menu_id) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "menu_id gerekli"]);
                exit;
            }

            $fields = [];
            $params = [];
            foreach (['ad', 'aciklama', 'fiyat', 'resim_url'] as $field) {
                if (isset($input[$field])) {
                    $fields[] = "$field = ?";
                    $params[] = $input[$field];
                }
            }
            if ($rol === "admin" && isset($input['restoran_id'])) {
                $fields[] = "restoran_id = ?";
                $params[] = $input['restoran_id'];
            }
            if (empty($fields)) throw new Exception("Güncellenecek alan yok");

            $sql = "UPDATE menuler SET " . implode(", ", $fields) . " WHERE menu_id = ?";
            $params[] = $menu_id;

            if ($rol === "restoran") {
                $sql .= " AND restoran_id = ?";
                $params[] = $restoran_id_token;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode(["status" => "success", "message" => "Menü güncellendi"]);
            exit;
        }

        // --- DELETE (Silme)
        elseif ($action === 'DELETE') {
            if (!in_array($rol, ['restoran', 'admin'])) {
                http_response_code(403);
                echo json_encode(["status" => "error", "message" => "Yetkiniz yok"]);
                exit;
            }

            $menu_id = $input['menu_id'] ?? null;
            if (!$menu_id) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "menu_id gerekli"]);
                exit;
            }

            $stmtSel = $pdo->prepare("SELECT resim_url, restoran_id FROM menuler WHERE menu_id = ?");
            $stmtSel->execute([$menu_id]);
            $row = $stmtSel->fetch(PDO::FETCH_ASSOC);

            if (!$row) throw new Exception("Menü bulunamadı");
            if ($rol === "restoran" && $row['restoran_id'] != $restoran_id_token)
                throw new Exception("Bu menü size ait değil");

            if ($rol === "restoran") {
                $stmt = $pdo->prepare("DELETE FROM menuler WHERE menu_id=? AND restoran_id=?");
                $stmt->execute([$menu_id, $restoran_id_token]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM menuler WHERE menu_id=?");
                $stmt->execute([$menu_id]);
            }

            if ($stmt->rowCount() > 0 && !empty($row['resim_url'])) {
                $path = __DIR__ . "/" . basename($row['resim_url']);
                if (file_exists($path)) @unlink($path);
            }

            echo json_encode(["status" => "success", "message" => "Menü silindi"]);
            exit;
        }

        // --- POST (Yeni Ekleme)
        else {
            if (!in_array($rol, ['restoran', 'admin'])) {
                http_response_code(403);
                echo json_encode(["status" => "error", "message" => "Yetkiniz yok"]);
                exit;
            }

            if (!isset($input['ad'], $input['aciklama'], $input['fiyat'])) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Eksik alanlar"]);
                exit;
            }

            $restoran_id = ($rol === "restoran") ? $restoran_id_token : ($input['restoran_id'] ?? null);
            if (!$restoran_id) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "restoran_id gerekli"]);
                exit;
            }

            // --- Resim yükleme (form-data ile) ---
            $resim_url = null;
            if (!$isJson && isset($_FILES['resim']) && $_FILES['resim']['error'] == 0) {
                $target_dir = __DIR__ . "/img/menu_resimleri/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

                // Dosya adındaki boşlukları _ ile değiştir
                $filename = str_replace(' ', '_', basename($_FILES['resim']['name']));
                $target_file = $target_dir . $filename;

                if (move_uploaded_file($_FILES['resim']['tmp_name'], $target_file)) {
                    $resim_url = "img/menu_resimleri/" . $filename;
                }
            } elseif ($isJson && !empty($input['resim_url'])) {
                $resim_url = $input['resim_url'];
            }

            $stmt = $pdo->prepare("INSERT INTO menuler (restoran_id, ad, aciklama, fiyat, resim_url) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$restoran_id, $input['ad'], $input['aciklama'], $input['fiyat'], $resim_url]);

            echo json_encode([
                "status" => "success",
                "message" => "Menü eklendi",
                "menu_id" => $pdo->lastInsertId(),
                "resim_url" => $resim_url
            ]);
            exit;
        }
    }

    // 🔹 Desteklenmeyen Metod
    else {
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Metod desteklenmiyor"]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit;
}
?>