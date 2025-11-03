<?php
$jwtSecret = getenv('JWT_SECRET') ?: '1234567890'; 
$jwtIssuer = getenv('JWT_ISSUER') ?: 'https://yemek.wuaze.com';
$jwtExpire = getenv('JWT_EXPIRE') !== false ? (int)getenv('JWT_EXPIRE') : 3600;

return [
    'jwt_secret' => $jwtSecret,
    'jwt_issuer' => $jwtIssuer,
    'jwt_expire' => $jwtExpire,
];
