<?php
require_once __DIR__ . '/config/koneksi.php';

try {
    $pdo = getConnection();
    echo "Updating database...<br>";

    // 1. Add is_lunas_uang_pangkal to siswa table
    $check = $pdo->query("SHOW COLUMNS FROM `siswa` LIKE 'is_lunas_uang_pangkal'")->fetch();
    if (!$check) {
        $pdo->exec("ALTER TABLE `siswa` ADD COLUMN is_lunas_uang_pangkal TINYINT(1) DEFAULT 0");
        echo "✅ Kolom is_lunas_uang_pangkal ditambahkan.<br>";
    }

    // 2. Add target_uang_pangkal if not exists (redundancy check)
    $check = $pdo->query("SHOW COLUMNS FROM `siswa` LIKE 'target_uang_pangkal'")->fetch();
    if (!$check) {
        $pdo->exec("ALTER TABLE `siswa` ADD COLUMN target_uang_pangkal DECIMAL(15,2) DEFAULT 0");
        echo "✅ Kolom target_uang_pangkal ditambahkan.<br>";
    }

    echo "<b>Selesai!</b>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
