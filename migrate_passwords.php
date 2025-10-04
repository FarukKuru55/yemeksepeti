<?php
// migrate_passwords.php
require_once __DIR__ . "/Api/db.php";

$stmt = $pdo->query("SELECT musteri_id, sifre FROM musteriler");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$upd = $pdo->prepare("UPDATE musteriler SET sifre = ? WHERE musteri_id = ?");

foreach ($rows as $r) {
    $pw = $r['sifre'];
    // Basit kontrol: bcrypt ya da argon2 ile hashlenmiş mi?
    if (!preg_match('/^\$2y\$|^\$2a\$|^\$argon2/i', $pw)) {
        $hashed = password_hash($pw, PASSWORD_DEFAULT);
        $upd->execute([$hashed, $r['musteri_id']]);
        echo "Re-hashed id={$r['musteri_id']}\n";
    } else {
        echo "Already hashed id={$r['musteri_id']}\n";
    }
}
