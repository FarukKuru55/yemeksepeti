<?php
// migrate_kartlar.php
header("Content-Type: text/plain; charset=UTF-8");

require_once __DIR__ . "/Api/db.php";

try {
    // 1. Tablo oluşturma (eğer yoksa)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kartlar_secure (
            kart_id INT AUTO_INCREMENT PRIMARY KEY,
            musteri_id INT NOT NULL,
            provider_token VARCHAR(255) DEFAULT NULL,
            last4 CHAR(4) DEFAULT NULL,              
            card_brand VARCHAR(50) DEFAULT NULL,
            exp_month TINYINT NULL,
            exp_year SMALLINT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "Tablo kartlar_secure oluşturuldu veya zaten mevcut.\n";

    // 2. Test verilerini ekleme
    $testData = [
        [101, 'tok_test_1_abcd', '4242', 'Visa', 4, 2026],
        [102, 'tok_test_2_efgh', '1111', 'MasterCard', 12, 2025],
        [103, 'tok_test_3_ijkl', '3782', 'Amex', 7, 2027],
        [104, 'tok_test_4_mnop', '6011', 'Discover', 1, 2028],
        [105, 'tok_test_5_qrst', '5100', 'MasterCard', 10, 2026],
        [106, 'tok_test_6_uvwx', '4012', 'Visa', 3, 2029],
        [107, 'tok_test_7_yz01', '3530', 'JCB', 6, 2025],
        [108, 'tok_test_8_2345', '5454', 'MasterCard', 11, 2027],
        [109, 'tok_test_9_6789', '4000', 'Visa', 9, 2026],
        [110, 'tok_test_10_ab12', '2222', 'Visa', 5, 2028],
        [111, 'tok_test_11_cd34', '9999', 'Maestro', 8, 2029],
        [112, 'tok_test_12_ef56', '8888', 'Visa', 2, 2026]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO kartlar_secure (musteri_id, provider_token, last4, card_brand, exp_month, exp_year)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($testData as $row) {
        $stmt->execute($row);
    }

    echo "Test verileri başarıyla eklendi.\n";

} catch (PDOException $e) {
    echo "Hata: " . $e->getMessage();
}
?>
