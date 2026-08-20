<?php
require_once __DIR__ . '/config/koneksi.php';

try {
    $pdo = getConnection();
    echo "Mulai update database untuk fitur verifikasi...<br>";

    // Add real_payment_id to pembayaran_pending table
    $check = $pdo->query("SHOW COLUMNS FROM `pembayaran_pending` LIKE 'real_payment_id'")->fetch();
    if (!$check) {
        $pdo->exec("ALTER TABLE `pembayaran_pending` ADD COLUMN real_payment_id INT NULL AFTER verified_at");
        echo "✅ Kolom real_payment_id ditambahkan ke tabel `pembayaran_pending`.<br>";
    } else {
        echo "ℹ️ Kolom real_payment_id sudah ada.<br>";
    }

    echo "<br><b>Selesai!</b>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
