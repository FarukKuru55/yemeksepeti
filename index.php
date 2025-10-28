<?php
// DENEME İNDEX.PHP
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/Api/db.php";

$stmt = $pdo->query("SELECT restoran_id, ad, kategori_id, adres, telefon, puan, acilis_saati, kapanis_saati 
                     FROM restoranlar 
                     ORDER BY restoran_id ASC");
$restoranlar = $stmt->fetchAll(PDO::FETCH_ASSOC);

header("Access-Control-Allow-Origin: *");
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <title>Yemeksepeti - Restoranlar</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f8f8f8;
      margin: 0;
      padding: 20px;
    }
    h1 {
      text-align: center;
      color: #333;
    }
    .container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-top: 30px;
    }
    .card {
      background: #fff;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      transition: 0.3s;
    }
    .card:hover {
      transform: translateY(-5px);
    }
    .card h2 {
      margin: 0 0 10px;
      color: #e74c3c;
    }
    .info {
      font-size: 14px;
      color: #555;
      margin: 4px 0;
    }
    .btn {
      display: inline-block;
      margin-top: 10px;
      padding: 8px 15px;
      background: #27ae60;
      color: #fff;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      transition: 0.2s;
    }
    .btn:hover {
      background: #219150;
    }
  </style>
</head>
<body>
  <h1>🍔 Yemeksepeti - Restoranlar</h1>

  <div class="container">
    <?php foreach ($restoranlar as $r): ?>
      <div class="card">
        <h2><?= htmlspecialchars($r['ad']) ?></h2>
        <p class="info">📍 <?= htmlspecialchars($r['adres']) ?></p>
        <p class="info">📞 <?= htmlspecialchars($r['telefon']) ?></p>
        <p class="info">⭐ Puan: <?= htmlspecialchars($r['puan']) ?></p>
        <p class="info">⏰ <?= $r['acilis_saati'] ?> - <?= $r['kapanis_saati'] ?></p>
        <button class="btn" onclick="detayGoster(<?= $r['restoran_id'] ?>, '<?= addslashes($r['ad']) ?>', '<?= addslashes($r['adres']) ?>', '<?= addslashes($r['telefon']) ?>')">
          Detay Göster
        </button>
      </div>
    <?php endforeach; ?>
  </div>

  <script>
    function detayGoster(id, ad, adres, telefon) {
      alert(`📌 Restoran ID: ${id}\n🏪 Ad: ${ad}\n📍 Adres: ${adres}\n📞 Telefon: ${telefon}`);
    }
  </script>
</body>
</html>
