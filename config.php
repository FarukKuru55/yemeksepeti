<?php
// config.php
// Güvenlik notu: Üretimde `JWT_SECRET` güçlü ve uzun bir rastgele değer olmalıdır.
// Öneri: .env dosyası veya sunucu ortam değişkenleri kullanın.

$jwtSecret = getenv('JWT_SECRET') ?: '1234567890'; 
$jwtIssuer = getenv('JWT_ISSUER') ?: 'https://yemek.wuaze.com';
$jwtExpire = getenv('JWT_EXPIRE') !== false ? (int)getenv('JWT_EXPIRE') : 3600;

return [
    'jwt_secret' => $jwtSecret,
    'jwt_issuer' => $jwtIssuer,
    'jwt_expire' => $jwtExpire,
];
