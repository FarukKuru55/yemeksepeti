<?php
$password = 'test1234'; // Kullanmak istediğiniz yeni şifre
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Yeni Şifre: " . $password . "<br>";
echo "Yeni Hash: " . $hash;
?>