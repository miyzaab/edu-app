<?php
/**
 * SCRIPT INSTALASI TABEL KANTIN
 * Digunakan jika tabel kantin belum ada di server produksi.
 */
require_once __DIR__ . '/config/koneksi.php';

try {
    $pdo = getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h3>Proses Instalasi Tabel Kantin...</h3>";

    // 1. kantin_menu
    $pdo->exec("CREATE TABLE IF NOT EXISTS `kantin_menu` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nama_item` varchar(255) NOT NULL,
        `kategori` varchar(100) NOT NULL,
        `harga` decimal(15,2) NOT NULL DEFAULT '0.00',
        `stok` int(11) NOT NULL DEFAULT '0',
        `foto` varchar(255) DEFAULT NULL,
        `status` enum('tersedia','habis') NOT NULL DEFAULT 'tersedia',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "✅ Tabel <b>kantin_menu</b> berhasil dipastikan ada.<br>";

    // 2. kantin_transaksi
    $pdo->exec("CREATE TABLE IF NOT EXISTS `kantin_transaksi` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `kode_transaksi` varchar(50) NOT NULL,
        `siswa_id` int(11) NOT NULL,
        `total_harga` decimal(15,2) NOT NULL,
        `metode_bayar` varchar(50) NOT NULL DEFAULT 'e-money',
        `user_id` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `kode_transaksi` (`kode_transaksi`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "✅ Tabel <b>kantin_transaksi</b> berhasil dipastikan ada.<br>";

    // 3. kantin_transaksi_detail
    $pdo->exec("CREATE TABLE IF NOT EXISTS `kantin_transaksi_detail` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `transaksi_id` int(11) NOT NULL,
        `menu_id` int(11) NOT NULL,
        `harga_satuan` decimal(15,2) NOT NULL,
        `qty` int(11) NOT NULL,
        `subtotal` decimal(15,2) NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "✅ Tabel <b>kantin_transaksi_detail</b> berhasil dipastikan ada.<br>";

    // 4. kantin_topup
    $pdo->exec("CREATE TABLE IF NOT EXISTS `kantin_topup` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `siswa_id` int(11) NOT NULL,
        `nominal` decimal(15,2) NOT NULL,
        `metode_bayar` varchar(50) NOT NULL DEFAULT 'cash',
        `user_id` int(11) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "✅ Tabel <b>kantin_topup</b> berhasil dipastikan ada.<br>";

    // 5. saldo_siswa
    $pdo->exec("CREATE TABLE IF NOT EXISTS `saldo_siswa` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `siswa_id` int(11) NOT NULL,
        `saldo` decimal(15,2) NOT NULL DEFAULT '0.00',
        `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `siswa_id` (`siswa_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "✅ Tabel <b>saldo_siswa</b> berhasil dipastikan ada.<br>";

    echo "<br><b style='color:green'>Selesai! Anda sudah bisa menggunakan menu Kantin.</b>";

} catch (Exception $e) {
    echo "<b style='color:red'>❌ Error:</b> " . $e->getMessage();
}
