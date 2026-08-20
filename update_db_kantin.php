<?php
/**
 * DATABASE UPDATE SCRIPT FOR KANTIN (Foto & Barcode)
 */
require_once __DIR__ . '/config/koneksi.php';

try {
    $pdo = getConnection();
    echo "Updating database structure for Kantin...<br>\n";

    // 1. Check if foto column exists in kantin_menu
    $checkFoto = $pdo->query("SHOW COLUMNS FROM `kantin_menu` LIKE 'foto'")->fetch();
    if (!$checkFoto) {
        $pdo->exec("ALTER TABLE `kantin_menu` ADD COLUMN `foto` VARCHAR(255) NULL AFTER `stok`");
        echo "✅ Kolom `foto` berhasil ditambahkan ke tabel `kantin_menu`.<br>\n";
    } else {
        echo "ℹ️ Kolom `foto` pada tabel `kantin_menu` sudah ada.<br>\n";
    }

    // 2. Ensure uploads/kantin directory exists
    $uploadDir = __DIR__ . '/uploads/kantin/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        echo "✅ Direktori `uploads/kantin/` berhasil dibuat.<br>\n";
    } else {
        echo "ℹ️ Direktori `uploads/kantin/` sudah tersedia.<br>\n";
    }

    echo "<b>Update Database Kantin Selesai!</b>\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
