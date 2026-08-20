<?php
require_once __DIR__ . '/config/koneksi.php';

try {
    $pdo = getConnection();
    echo "Mulai sinkronisasi database...<br>";

    $tables = ['pembayaran_spp', 'pembayaran_uang_pangkal', 'pembayaran_lain'];
    
    foreach ($tables as $table) {
        // Cek apakah kolom sudah ada
        $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE 'bukti_transfer'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN bukti_transfer VARCHAR(255) NULL AFTER keterangan");
            echo "✅ Kolom bukti_transfer ditambahkan ke tabel `$table`.<br>";
        } else {
            echo "ℹ️ Kolom bukti_transfer sudah ada di tabel `$table`.<br>";
        }
    }

    echo "<br><b>Selesai!</b> Silakan hapus file ini.";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
