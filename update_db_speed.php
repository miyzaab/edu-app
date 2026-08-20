<?php
/**
 * SCRIPT OPTIMASI KINERJA & INDEKS DATABASE (SPEED BOOST)
 */
require_once __DIR__ . '/config/koneksi.php';

try {
    $pdo = getConnection();
    echo "⚡ Optimizing Database Indexes for Maximum Speed...<br>\n";

    $indexes = [
        ['table' => 'settings', 'index' => 'idx_setting_key', 'cols' => '`setting_key`'],
        ['table' => 'siswa', 'index' => 'idx_siswa_nis', 'cols' => '`nis`'],
        ['table' => 'siswa', 'index' => 'idx_siswa_status_kelas', 'cols' => '`status`, `kelas_id`'],
        ['table' => 'saldo_siswa', 'index' => 'idx_saldo_siswa_id', 'cols' => '`siswa_id`'],
        ['table' => 'kantin_menu', 'index' => 'idx_menu_status_kat', 'cols' => '`status`, `kategori`'],
        ['table' => 'kantin_transaksi', 'index' => 'idx_trx_siswa', 'cols' => '`siswa_id`'],
        ['table' => 'kantin_transaksi', 'index' => 'idx_trx_created', 'cols' => '`created_at`'],
        ['table' => 'kantin_transaksi_detail', 'index' => 'idx_detail_trx', 'cols' => '`transaksi_id`'],
        ['table' => 'pembayaran_spp', 'index' => 'idx_spp_siswa_bulan', 'cols' => '`siswa_id`, `bulan`, `tahun`'],
        ['table' => 'pembayaran_uang_pangkal', 'index' => 'idx_up_siswa', 'cols' => '`siswa_id`'],
        ['table' => 'notifikasi_ortu', 'index' => 'idx_notif_siswa', 'cols' => '`siswa_id`']
    ];

    foreach ($indexes as $idx) {
        $table = $idx['table'];
        $indexName = $idx['index'];
        $cols = $idx['cols'];

        // Check if table exists
        $tblChk = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
        if (!$tblChk) continue;

        // Check if index exists
        $chk = $pdo->query("SHOW INDEX FROM `$table` WHERE Key_name = '$indexName'")->fetch();
        if (!$chk) {
            $pdo->exec("ALTER TABLE `$table` ADD INDEX `$indexName` ($cols)");
            echo "✅ Indeks `$indexName` ditambahkan pada tabel `$table`.<br>\n";
        } else {
            echo "ℹ️ Indeks `$indexName` pada tabel `$table` sudah aktif.<br>\n";
        }
    }

    echo "<b>🎉 Database Indexing Complete! System Performance Boosted.</b>\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
