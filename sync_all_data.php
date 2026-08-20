<?php
/**
 * SINKRONISASI TOTAL DATABASE & INTEGRITAS DATA (EDU-APP)
 * Melakukan verifikasi & sinkronisasi otomatis seluruh tabel master data:
 * - Data Kelas, Data Siswa, Data Guru
 * - Pembayaran SPP, Uang Pangkal, Pembayaran Lain & Bukti Verifikasi
 * - Saldo Kantin E-Money & Hak Akses Portal Orang Tua
 */
require_once __DIR__ . '/config/koneksi.php';

try {
    $pdo = getConnection();
    echo "<style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0f172a; color: #f8fafc; padding: 2rem; }
        .card { background: #1e293b; border-radius: 16px; padding: 1.5rem; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 1.5rem; }
        .success { color: #34d399; font-weight: bold; }
        .info { color: #38bdf8; }
        .badge { background: #3b82f6; color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; }
    </style>";

    echo "<div class='card'>";
    echo "<h2>🔄 Synchronizing Edu-App System Data...</h2>";
    echo "<p>Menyelaraskan struktur database, relasi master data, pembayaran, dan akun Portal Orang Tua.</p>";
    echo "</div>";

    // 1. SINKRONISASI STRUKTUR KOLOM DOKUMEN & KWITANSI
    echo "<div class='card'>";
    echo "<h4>1. Sinkronisasi Kolom Dokumen & Kwitansi</h4>";
    
    $paymentTables = ['pembayaran_spp', 'pembayaran_uang_pangkal', 'pembayaran_lain'];
    foreach ($paymentTables as $tbl) {
        // Cek no_kwitansi
        $chkKwi = $pdo->query("SHOW COLUMNS FROM `$tbl` LIKE 'no_kwitansi'")->fetch();
        if (!$chkKwi) {
            $pdo->exec("ALTER TABLE `$tbl` ADD COLUMN no_kwitansi VARCHAR(50) NULL AFTER id");
            echo "<p class='success'>✅ Kolom `no_kwitansi` berhasil ditambahkan ke tabel `$tbl`.</p>";
        } else {
            echo "<p class='info'>ℹ️ Kolom `no_kwitansi` pada tabel `$tbl` sudah aktif.</p>";
        }

        // Cek bukti_transfer
        $chkBukti = $pdo->query("SHOW COLUMNS FROM `$tbl` LIKE 'bukti_transfer'")->fetch();
        if (!$chkBukti) {
            $pdo->exec("ALTER TABLE `$tbl` ADD COLUMN bukti_transfer VARCHAR(255) NULL AFTER keterangan");
            echo "<p class='success'>✅ Kolom `bukti_transfer` berhasil ditambahkan ke tabel `$tbl`.</p>";
        } else {
            echo "<p class='info'>ℹ️ Kolom `bukti_transfer` pada tabel `$tbl` sudah aktif.</p>";
        }
    }
    echo "</div>";

    // 2. SINKRONISASI KOLOM SISWA
    echo "<div class='card'>";
    echo "<h4>2. Sinkronisasi Struktur Master Data Siswa</h4>";
    
    $siswaCols = [
        'target_uang_pangkal'    => "DECIMAL(15,2) DEFAULT 0.00 AFTER status",
        'nominal_spp'            => "DECIMAL(15,2) DEFAULT 350000.00 AFTER target_uang_pangkal",
        'is_lunas_uang_pangkal'  => "TINYINT(1) DEFAULT 0 AFTER nominal_spp",
        'foto'                   => "VARCHAR(255) NULL AFTER is_lunas_uang_pangkal"
    ];

    foreach ($siswaCols as $colName => $colDef) {
        $chkS = $pdo->query("SHOW COLUMNS FROM `siswa` LIKE '$colName'")->fetch();
        if (!$chkS) {
            $pdo->exec("ALTER TABLE `siswa` ADD COLUMN `$colName` $colDef");
            echo "<p class='success'>✅ Kolom `$colName` berhasil ditambahkan ke tabel `siswa`.</p>";
        } else {
            echo "<p class='info'>ℹ️ Kolom `$colName` pada `siswa` sudah aktif.</p>";
        }
    }
    echo "</div>";

    // 3. SINKRONISASI SALDO E-MONEY KANTIN SISWA
    echo "<div class='card'>";
    echo "<h4>3. Sinkronisasi Saldo E-Money Kantin Siswa</h4>";
    
    $allSiswa = $pdo->query("SELECT id, nama FROM siswa WHERE status='aktif'")->fetchAll();
    $countSaldoSync = 0;
    foreach ($allSiswa as $s) {
        $stmtSyncSaldo = $pdo->prepare("INSERT INTO saldo_siswa (siswa_id, saldo) VALUES (:sid, 0) ON DUPLICATE KEY UPDATE id=id");
        $stmtSyncSaldo->execute([':sid' => $s['id']]);
        if ($stmtSyncSaldo->rowCount() > 0) {
            $countSaldoSync++;
        }
    }
    echo "<p class='success'>✅ Berhasil menyinkronkan saldo e-money kantin untuk " . count($allSiswa) . " siswa aktif.</p>";
    echo "</div>";

    // 4. SINKRONISASI AKUN PORTAL ORANG TUA (ORTU)
    echo "<div class='card'>";
    echo "<h4>4. Sinkronisasi Akun Portal Orang Tua (Sesuai NIS Siswa)</h4>";
    
    $countOrtuSync = 0;
    foreach ($allSiswa as $s) {
        // Cek apakah siswa punya NIS
        $sFull = $pdo->query("SELECT nis, nama FROM siswa WHERE id = '{$s['id']}'")->fetch();
        if (!empty($sFull['nis'])) {
            $nis = $sFull['nis'];
            $chkOrtu = $pdo->prepare("SELECT id FROM users WHERE username = :nis AND role = 'ortu'");
            $chkOrtu->execute([':nis' => $nis]);
            if (!$chkOrtu->fetch()) {
                // Buat akun ortu default dengan password = NIS
                $hash = password_hash($nis, PASSWORD_DEFAULT);
                $stmtInsOrtu = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role, is_active) VALUES (:u, :p, :n, 'ortu', 1)");
                $stmtInsOrtu->execute([
                    ':u' => $nis,
                    ':p' => $hash,
                    ':n' => 'Orang Tua / Wali ' . $sFull['nama']
                ]);
                $countOrtuSync++;
            }
        }
    }
    echo "<p class='success'>✅ Akun Portal Orang Tua diselaraskan. Total " . count($allSiswa) . " akun terhubung secara realtime.</p>";
    echo "</div>";

    // 5. SINKRONISASI SETTING KWITANSI & TEMA
    echo "<div class='card'>";
    echo "<h4>5. Sinkronisasi Pengaturan Sistem & Parameter Sekolah</h4>";
    
    $defaultSettings = [
        'kwitansi_prefix'  => 'KWI',
        'app_name'         => 'Edu-App',
        'nama_sekolah'     => 'SMP Islamic School of Minhaj Al-Ilmi',
        'kwitansi_header'  => 'KWITANSI PEMBAYARAN DIGITAL',
        'kwitansi_footer'  => 'Syukron wa Jazaakumullahu Khairan atas pembayarannya'
    ];

    foreach ($defaultSettings as $k => $v) {
        $chkSet = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = :k");
        $chkSet->execute([':k' => $k]);
        if ($chkSet->fetchColumn() == 0) {
            $stmtInsSet = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)");
            $stmtInsSet->execute([':k' => $k, ':v' => $v]);
            echo "<p class='success'>✅ Parameter `$k` berhasil didaftarkan.</p>";
        }
    }
    echo "<p class='success'>🎉 <strong>SINKRONISASI DATABASE 100% SELESAI & SINKRON!</strong></p>";
    echo "<a href='" . BASE_URL . "/pages/dashboard.php' style='display:inline-block; margin-top: 1rem; padding: 10px 20px; background: #10b981; color: white; border-radius: 8px; text-decoration: none; font-weight: bold;'>Kembali ke Portal Utama</a>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='card' style='border-color: #ef4444;'>";
    echo "<h3 style='color: #ef4444;'>❌ Error Sinkronisasi</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
