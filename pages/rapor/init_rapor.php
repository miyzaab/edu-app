<?php
/**
 * Auto-migration & Database Init for Rapor Kurikulum Merdeka
 * Menjamin tabel rapor_siswa, rapor_settings, dan kolom nisn selalu ada di database server manapun.
 */

if (!defined('RAPOR_INIT_RUN')) {
    define('RAPOR_INIT_RUN', true);

    try {
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            require_once __DIR__ . '/../../config/koneksi.php';
            $pdo = getConnection();
        }

        require_once __DIR__ . '/../../config/db_auto_sync.php';
        runDatabaseAutoSync($pdo);

        // 1. Buat Tabel rapor_siswa jika belum ada
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `rapor_siswa` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `siswa_id` int(11) NOT NULL,
              `kelas_id` int(11) NOT NULL,
              `tipe_rapor` varchar(20) NOT NULL DEFAULT 'ats',
              `semester` enum('Ganjil','Genap') NOT NULL DEFAULT 'Ganjil',
              `tahun_ajaran` varchar(20) NOT NULL DEFAULT '2026/2027',
              `fase` varchar(10) NOT NULL DEFAULT 'D',
              `sakit` int(11) NOT NULL DEFAULT 0,
              `izin` int(11) NOT NULL DEFAULT 0,
              `tanpa_keterangan` int(11) NOT NULL DEFAULT 0,
              `catatan_wali_kelas` text DEFAULT NULL,
              `tanggapan_ortu` text DEFAULT NULL,
              `kokurikuler_deskripsi` text DEFAULT NULL,
              `ekskul_json` text DEFAULT NULL,
              `deskripsi_mapel_json` longtext DEFAULT NULL,
              `wali_kelas_nama` varchar(150) DEFAULT NULL,
              `wali_kelas_nip` varchar(50) DEFAULT NULL,
              `kepsek_nama` varchar(150) DEFAULT NULL,
              `kepsek_nip` varchar(50) DEFAULT NULL,
              `tanggal_rapor` date DEFAULT NULL,
              `tempat_terbit` varchar(100) DEFAULT 'Jakarta',
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `idx_siswa_rapor` (`siswa_id`,`tipe_rapor`,`semester`,`tahun_ajaran`),
              KEY `idx_kelas` (`kelas_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        try {
            $pdo->exec("ALTER TABLE `rapor_siswa` MODIFY COLUMN `tipe_rapor` VARCHAR(20) NOT NULL DEFAULT 'ats'");
        } catch (Exception $eAlter) {
        }

        // 2. Buat Tabel rapor_settings jika belum ada
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `rapor_settings` (
              `setting_key` varchar(100) NOT NULL,
              `setting_value` text DEFAULT NULL,
              PRIMARY KEY (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 3. Tambahkan kolom nisn ke tabel siswa jika belum ada
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM siswa LIKE 'nisn'")->fetchAll();
            if (empty($cols)) {
                $pdo->exec("ALTER TABLE siswa ADD COLUMN `nisn` VARCHAR(20) DEFAULT NULL AFTER `nis`");
            }
        } catch (Exception $eNisn) {
        }

        // 4. Pastikan direktori uploads/rapor siap
        $uploadRaporDir = __DIR__ . '/../../uploads/rapor';
        if (!file_exists($uploadRaporDir)) {
            @mkdir($uploadRaporDir, 0777, true);
        }

        // 5. Seed default settings jika belum ada
        $defaultSettings = [
            'kop_image' => '',
            'use_uploaded_kop' => '0',
            'kop_nama_sekolah' => 'SMP ISMI',
            'kop_yayasan' => 'ISLAMIC SCHOOL OF MINHAJ AL-ILMI',
            'kop_slogan' => 'Berakhlak Mulia, Berilmu, Mahir IT',
            'kop_alamat' => 'Jl. Nakula No.1 Harjamukti Cimanggis Depok',
            'kop_telp' => 'Telp. (021) 4867 8793',
            'kop_email' => 'smp.minhaj.al.ilmi@gmail.com',
            'kop_website' => 'sekolahismi.sch.id & ismi.sch.id',
            'kop_wa' => 'Admin WA 0899-2244-111',
            'kop_logo' => '',
            'kepsek_jabatan' => 'Mudir SMP',
            'kepsek_lembaga' => 'Islamic School of Minhaj Al Ilmi',
            'kepsek_nama' => 'Riki Kaptanto, Lc',
            'kepsek_nip' => '20240711001',
            'kepsek_ttd' => '',
            'stempel_image' => '',
            'tempat_terbit' => 'Depok',
            'watermark_enabled' => '0',
            'watermark_image' => '',
            'watermark_opacity' => '12',
            'watermark_size' => '360',
            'tanggal_pts_ganjil' => date('Y') . '-10-11',
            'tanggal_ats_ganjil' => date('Y') . '-10-11',
            'tanggal_pas_ganjil' => date('Y') . '-12-20',
            'tanggal_aas_ganjil' => date('Y') . '-12-20',
            'tanggal_pts_genap' => (date('Y') + 1) . '-03-20',
            'tanggal_ats_genap' => (date('Y') + 1) . '-03-20',
            'tanggal_pas_genap' => (date('Y') + 1) . '-06-25',
            'tanggal_aas_genap' => (date('Y') + 1) . '-06-25',
            'fase_default' => 'D',
            'kokurikuler_default' => 'Peserta didik aktif berpartisipasi dalam Projek Penguatan Profil Pelajar Pancasila (P5) dengan menunjukkan sikap mandiri, gotong royong, dan bernalar kritis.'
        ];

        $stmtSeed = $pdo->prepare("INSERT IGNORE INTO rapor_settings (setting_key, setting_value) VALUES (:k, :v)");
        foreach ($defaultSettings as $k => $v) {
            $stmtSeed->execute([':k' => $k, ':v' => $v]);
        }

        // 6. Sinkronisasi otomatis NIP Wali Kelas dari data Guru ke rapor_siswa jika di rapor masih '-' atau kosong
        try {
            $pdo->exec("
                UPDATE rapor_siswa rs
                JOIN siswa s ON rs.siswa_id = s.id
                JOIN kelas k ON s.kelas_id = k.id
                JOIN users u ON k.wali_kelas_id = u.id
                JOIN guru_detail gd ON (u.id = gd.user_id OR k.wali_kelas_id = gd.user_id)
                SET rs.wali_kelas_nip = gd.nip
                WHERE (rs.wali_kelas_nip IS NULL OR rs.wali_kelas_nip = '' OR rs.wali_kelas_nip = '-')
                  AND gd.nip IS NOT NULL AND gd.nip != '' AND gd.nip != '-'
            ");
        } catch (Exception $eSyncNip) {
        }

    } catch (Exception $eMigration) {
        // Log silently or ignore
        error_log("Rapor Migration Error: " . $eMigration->getMessage());
    }
}

/**
 * Helper mengubah angka nilai menjadi kalimat terbilang bahasa Indonesia (misal: 85 -> "Delapan Puluh Lima")
 */
if (!function_exists('angkaKeTerbilang')) {
    function angkaKeTerbilang($num)
    {
        if ($num === null || $num === '' || (float) $num <= 0) {
            return '-';
        }

        $num = (float) $num;
        $words = [
            0 => 'Nol',
            1 => 'Satu',
            2 => 'Dua',
            3 => 'Tiga',
            4 => 'Empat',
            5 => 'Lima',
            6 => 'Enam',
            7 => 'Tujuh',
            8 => 'Delapan',
            9 => 'Sembilan',
            10 => 'Sepuluh',
            11 => 'Sebelas'
        ];

        $intPart = (int) floor($num);
        $result = '';

        if ($intPart < 12) {
            $result = $words[$intPart];
        } elseif ($intPart < 20) {
            $result = $words[$intPart - 10] . ' Belas';
        } elseif ($intPart < 100) {
            $puluh = (int) floor($intPart / 10);
            $sisa = $intPart % 10;
            $result = $words[$puluh] . ' Puluh' . ($sisa > 0 ? ' ' . $words[$sisa] : '');
        } elseif ($intPart == 100) {
            $result = 'Seratus';
        } elseif ($intPart < 200) {
            $result = 'Seratus ' . angkaKeTerbilang($intPart - 100);
        } elseif ($intPart < 1000) {
            $ratus = (int) floor($intPart / 100);
            $sisa = $intPart % 100;
            $result = $words[$ratus] . ' Ratus' . ($sisa > 0 ? ' ' . angkaKeTerbilang($sisa) : '');
        } else {
            $result = (string) $intPart;
        }

        // Jika ada angka desimal dibelakang koma (misal: 78.5 -> Delapan Puluh Koma Lima)
        $fraction = round($num - $intPart, 1);
        if ($fraction > 0) {
            $fracDigit = (int) round($fraction * 10);
            if ($fracDigit > 0 && isset($words[$fracDigit])) {
                $result .= ' Koma ' . $words[$fracDigit];
            }
        }

        return $result;
    }
}

/**
 * Helper untuk mencocokkan baris nilai_siswa dengan nama mapel secara cerdas (mendukung alias nama mapel)
 */
if (!function_exists('matchMapelNilai')) {
    function matchMapelNilai($rawNilaiRows, $targetMapelName)
    {
        if (empty($rawNilaiRows))
            return null;
        $targetClean = trim($targetMapelName);
        $targetAlpha = preg_replace('/[^a-z0-9]/', '', mb_strtolower($targetClean));

        // 1. Exact match
        foreach ($rawNilaiRows as $row) {
            if (isset($row['mapel']) && strcasecmp(trim($row['mapel']), $targetClean) === 0) {
                return $row;
            }
        }

        // 2. Alphanumeric clean match
        foreach ($rawNilaiRows as $row) {
            if (isset($row['mapel'])) {
                $rowAlpha = preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim($row['mapel'])));
                if ($rowAlpha === $targetAlpha && $rowAlpha !== '') {
                    return $row;
                }
            }
        }

        // 3. Known Aliases Mapel Sekolah Islam & Umum
        $aliases = [
            'fiqih' => ['fiqih', 'fikih', 'fiqh', 'fiqihvii', 'fiqihviii', 'fiqihix', 'fiqih7', 'fiqih8', 'fiqih9', 'paifiqih'],
            'fikih' => ['fiqih', 'fikih', 'fiqh', 'fiqihvii', 'fiqihviii', 'fiqihix', 'fiqih7', 'fiqih8', 'fiqih9', 'paifiqih'],
            'aqidah' => ['aqidah', 'akidah', 'aqidahakhlak', 'akidahakhlak', 'aqidahislam'],
            'akidah' => ['aqidah', 'akidah', 'aqidahakhlak', 'akidahakhlak', 'aqidahislam'],
            'adabakhlak' => ['adabakhlak', 'adab', 'akhlak', 'akhlaq', 'adabdanakhlak'],
            'alquran' => ['alquran', 'quran', 'alquranhadits', 'quranhadits', 'tahfidz', 'tahfidzulquran'],
            'tahfidz' => ['tahfidz', 'tahfidzulquran', 'alquran', 'quran'],
            'bahasaindonesia' => ['bahasaindonesia', 'bindo', 'bindonesia'],
            'bahasainggris' => ['bahasainggris', 'bing', 'binggris'],
            'bahasaarab' => ['bahasaarab', 'barab'],
            'pjok' => ['pjok', 'penjas', 'penjaskes', 'olahraga', 'penjaskesor'],
            'ppkn' => ['ppkn', 'pancasila', 'pendidikanpancasila', 'pkn', 'pendidikanpancasilappkn'],
            'ipa' => ['ipa', 'ilmupengetahuanalam', 'ilmuilmualam'],
            'ips' => ['ips', 'ilmupengetahuansosial', 'ilmuilmusosial'],
            'pai' => ['pai', 'pendidikanagamaislam', 'agamaislam', 'pendidikanagamaislambudipekerti']
        ];

        foreach ($aliases as $mainKey => $varList) {
            if (in_array($targetAlpha, $varList)) {
                foreach ($rawNilaiRows as $row) {
                    if (isset($row['mapel'])) {
                        $rowAlpha = preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim($row['mapel'])));
                        if (in_array($rowAlpha, $varList)) {
                            return $row;
                        }
                    }
                }
            }
        }

        // 4. Substring containment if length >= 4
        if (strlen($targetAlpha) >= 4) {
            foreach ($rawNilaiRows as $row) {
                if (isset($row['mapel'])) {
                    $rowAlpha = preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim($row['mapel'])));
                    if (strlen($rowAlpha) >= 4 && (strpos($rowAlpha, $targetAlpha) !== false || strpos($targetAlpha, $rowAlpha) !== false)) {
                        return $row;
                    }
                }
            }
        }

        return null;
    }
}

