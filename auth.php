<?php
// auth.php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/vendor/autoload.php";
$config = require __DIR__ . "/config.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$header = getBearerToken();
error_log("HEADER: " . $header);



function getBearerToken() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];

    if (isset($headers['Authorization'])) return trim($headers['Authorization']);
    if (isset($headers['authorization'])) return trim($headers['authorization']);
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) return trim($_SERVER['HTTP_AUTHORIZATION']);

    return null;
}

function requireAuth() {
    global $config;

    $header = getBearerToken();
    if (!$header) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Authorization header gerekli"]);
        exit;
    }

    if (!preg_match('/Bearer\s(\S+)/', $header, $matches)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Geçersiz Authorization formatı"]);
        exit;
    }

    $jwt = $matches[1];
    try {
        $decoded = JWT::decode($jwt, new Key($config['jwt_secret'], 'HS256'));
        return (array) $decoded; // sub, email vs. döner
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Token geçersiz: " . $e->getMessage()]);
        exit;
    }
}
