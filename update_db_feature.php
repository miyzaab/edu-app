<?php
require_once __DIR__ . '/config/koneksi.php';

try {
    $pdo = getConnection();
    echo "Mulai update database...<br>";

    // Add target_uang_pangkal to siswa table
    $check = $pdo->query("SHOW COLUMNS FROM `siswa` LIKE 'target_uang_pangkal'")->fetch();
    if (!$check) {
        $pdo->exec("ALTER TABLE `siswa` ADD COLUMN target_uang_pangkal DECIMAL(15,2) DEFAULT 0 AFTER status");
        echo "✅ Kolom target_uang_pangkal ditambahkan ke tabel `siswa`.<br>";
    } else {
        echo "ℹ️ Kolom target_uang_pangkal sudah ada.<br>";
    }

    echo "<br><b>Selesai!</b>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
