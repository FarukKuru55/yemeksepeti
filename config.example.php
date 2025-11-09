<?php
// config.example.php - JWT ve uygulama ayarları için örnek yapı

$jwtSecret = getenv('JWT_SECRET') ?: 'your_jwt_secret_here';
$jwtIssuer = getenv('JWT_ISSUER') ?: 'https://example.com';
$jwtExpire = getenv('JWT_EXPIRE') !== false ? (int)getenv('JWT_EXPIRE') : 3600;

return [
    'jwt_secret' => $jwtSecret,
    'jwt_issuer' => $jwtIssuer,
    'jwt_expire' => $jwtExpire,
];
