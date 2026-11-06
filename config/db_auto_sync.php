<?php
/**
 * ============================================================
 * DATABASE AUTO-SYNC & SELF-HEALING HELPER
 * ============================================================
 * Menjamin semua tabel dan kolom yang dibutuhkan oleh sistem
 * (Users, Permissions, Akademik, Guru, Wali Kelas, Rapor, Poin)
 * otomatis dibuat dan disinkronkan di server manapun tanpa error 500.
 * ============================================================
 */

if (!defined('DB_AUTO_SYNC_RUN')) {
    define('DB_AUTO_SYNC_RUN', true);

    function runDatabaseAutoSync(?PDO $pdo = null): array
    {
        if ($pdo === null) {
            $pdo = getConnection();
        }

        $log = [];

        try {
            // 1. TABEL USERS (role varchar, siswa_id, is_active)
            try {
                $checkRole = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
                if ($checkRole) {
                    $pdo->exec("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'bendahara'");
                    $pdo->exec("UPDATE users SET role = 'bendahara' WHERE role IS NULL OR role = '' OR role = '0'");
                    try {
                        $pdo->exec("UPDATE users u JOIN guru_detail gd ON u.id = gd.user_id SET u.role = 'guru' WHERE u.role NOT IN ('admin', 'bendahara') AND gd.user_id IS NOT NULL");
                    } catch (Exception $eG) {
                    }
                }

                $checkSiswaId = $pdo->query("SHOW COLUMNS FROM users LIKE 'siswa_id'")->fetch();
                if (!$checkSiswaId) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN siswa_id INT NULL AFTER role");
                }

                $checkActive = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_active'")->fetch();
                if (!$checkActive) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER siswa_id");
                }

                $checkLastLogin = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_login'")->fetch();
                if (!$checkLastLogin) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN last_login DATETIME NULL AFTER is_active");
                }
                $log[] = 'Tabel users diperbarui (role, siswa_id, is_active, last_login)';
            } catch (Exception $e) {
                $log[] = 'Users update note: ' . $e->getMessage();
            }

            // 2. TABEL USER_PERMISSIONS
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS user_permissions (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    permission_key VARCHAR(50) NOT NULL,
                    is_allowed TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
                    UNIQUE KEY unique_user_perm (user_id, permission_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            $log[] = 'Tabel user_permissions siap';

            // 3. TABEL KELAS (wali_kelas_id, status, is_active)
            try {
                $checkWk = $pdo->query("SHOW COLUMNS FROM kelas LIKE 'wali_kelas_id'")->fetch();
                if (!$checkWk) {
                    $pdo->exec("ALTER TABLE kelas ADD COLUMN wali_kelas_id INT NULL AFTER tingkat");
                }
                $checkStatusKelas = $pdo->query("SHOW COLUMNS FROM kelas LIKE 'status'")->fetch();
                if (!$checkStatusKelas) {
                    $pdo->exec("ALTER TABLE kelas ADD COLUMN status ENUM('aktif','nonaktif') DEFAULT 'aktif' AFTER wali_kelas_id");
                }
                $checkActiveKelas = $pdo->query("SHOW COLUMNS FROM kelas LIKE 'is_active'")->fetch();
                if (!$checkActiveKelas) {
                    $pdo->exec("ALTER TABLE kelas ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER status");
                }
                $log[] = 'Tabel kelas diperbarui (wali_kelas_id, status, is_active)';
            } catch (Exception $e) {
                $log[] = 'Kelas update note: ' . $e->getMessage();
            }

            // 4. TABEL SISWA (nisn, foto, no_hp_ortu, nominal_spp, target_uang_pangkal, bulan_mulai_spp)
            try {
                $checkNisn = $pdo->query("SHOW COLUMNS FROM siswa LIKE 'nisn'")->fetch();
                if (!$checkNisn) {
                    $pdo->exec("ALTER TABLE siswa ADD COLUMN nisn VARCHAR(20) DEFAULT NULL");
                }

                $checkFoto = $pdo->query("SHOW COLUMNS FROM siswa LIKE 'foto'")->fetch();
                if (!$checkFoto) {
                    $pdo->exec("ALTER TABLE siswa ADD COLUMN foto VARCHAR(255) NULL");
                }

                $checkNoHpOrtu = $pdo->query("SHOW COLUMNS FROM siswa LIKE 'no_hp_ortu'")->fetch();
                if (!$checkNoHpOrtu) {
                    $pdo->exec("ALTER TABLE siswa ADD COLUMN no_hp_ortu VARCHAR(30) NULL");
                }

                $checkNominalSpp = $pdo->query("SHOW COLUMNS FROM siswa LIKE 'nominal_spp'")->fetch();
                if (!$checkNominalSpp) {
                    $pdo->exec("ALTER TABLE siswa ADD COLUMN nominal_spp DECIMAL(12,2) NULL DEFAULT 150000");
                }

                $checkTargetUp = $pdo->query("SHOW COLUMNS FROM siswa LIKE 'target_uang_pangkal'")->fetch();
                if (!$checkTargetUp) {
                    $pdo->exec("ALTER TABLE siswa ADD COLUMN target_uang_pangkal DECIMAL(12,2) NULL DEFAULT 0");
                }

                $checkBulanSpp = $pdo->query("SHOW COLUMNS FROM siswa LIKE 'bulan_mulai_spp'")->fetch();
                if (!$checkBulanSpp) {
                    $pdo->exec("ALTER TABLE siswa ADD COLUMN bulan_mulai_spp INT NULL DEFAULT 7");
                }

                $log[] = 'Tabel siswa diperbarui (nisn, no_hp_ortu, foto, nominal_spp, target_uang_pangkal, bulan_mulai_spp)';
            } catch (Exception $e) {
                $log[] = 'Siswa update note: ' . $e->getMessage();
            }

            // TABEL JENIS_PEMBAYARAN & SISWA_BEBAS_PEMBAYARAN
            try {
                $checkTargetTingkat = $pdo->query("SHOW COLUMNS FROM jenis_pembayaran LIKE 'target_tingkat'")->fetch();
                if (!$checkTargetTingkat) {
                    $pdo->exec("ALTER TABLE jenis_pembayaran ADD COLUMN target_tingkat VARCHAR(50) DEFAULT 'all'");
                }

                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS siswa_bebas_pembayaran (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        siswa_id INT NOT NULL,
                        jenis_pembayaran_id INT NOT NULL,
                        keterangan VARCHAR(255) NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_siswa_bebas (siswa_id, jenis_pembayaran_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");

                // Check is_dismissed di pembayaran_pending
                $checkDismiss = $pdo->query("SHOW COLUMNS FROM pembayaran_pending LIKE 'is_dismissed'")->fetch();
                if (!$checkDismiss) {
                    $pdo->exec("ALTER TABLE pembayaran_pending ADD COLUMN is_dismissed TINYINT(1) DEFAULT 0 AFTER status");
                }

                $log[] = 'Tabel jenis_pembayaran & siswa_bebas_pembayaran siap';
            } catch (Exception $e) {
                $log[] = 'Jenis pembayaran update note: ' . $e->getMessage();
            }

            // 5. TABEL GURU_DETAIL
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS guru_detail (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNIQUE NOT NULL,
                    nip VARCHAR(50) DEFAULT '',
                    gelar_depan VARCHAR(50) DEFAULT '',
                    gelar_belakang VARCHAR(50) DEFAULT '',
                    tempat_lahir VARCHAR(100) DEFAULT '',
                    tanggal_lahir DATE NULL,
                    jenis_kelamin ENUM('L', 'P') DEFAULT 'L',
                    alamat TEXT DEFAULT NULL,
                    no_hp VARCHAR(30) DEFAULT '',
                    foto VARCHAR(255) DEFAULT '',
                    status_kepegawaian VARCHAR(100) DEFAULT 'GTY (Guru Tetap Yayasan)',
                    pendidikan_jenjang VARCHAR(50) DEFAULT 'S1',
                    pendidikan_jurusan VARCHAR(100) DEFAULT '',
                    pendidikan_kampus VARCHAR(150) DEFAULT '',
                    spesialisasi VARCHAR(150) DEFAULT '',
                    jabatan_sekolah VARCHAR(100) DEFAULT 'Guru Mata Pelajaran',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
            $log[] = 'Tabel guru_detail siap';

            // 6. TABEL MATA_PELAJARAN
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS mata_pelajaran (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nama_mapel VARCHAR(100) NOT NULL UNIQUE,
                    kelompok ENUM('Umum', 'Agama / Kepesantrenan', 'Mulok / Keahlian') DEFAULT 'Umum',
                    urutan INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
            $log[] = 'Tabel mata_pelajaran siap';

            // Auto-clean whitespace pada nilai_siswa & materi_sumatif & Auto-Sync Skala Nilai Akhir
            try {
                $pdo->exec("UPDATE nilai_siswa SET mapel = TRIM(mapel) WHERE mapel != TRIM(mapel)");
                $pdo->exec("UPDATE materi_sumatif SET mapel = TRIM(mapel) WHERE mapel != TRIM(mapel)");
                $pdo->exec("
                    UPDATE nilai_siswa 
                    SET nilai_akhir = ROUND((((sumatif_1 + sumatif_2 + sumatif_3 + sumatif_4) / 4.0) * 0.80) + (ats * 0.10) + (aas * 0.10), 2)
                    WHERE (sumatif_1 > 0 OR sumatif_2 > 0 OR sumatif_3 > 0 OR sumatif_4 > 0 OR ats > 0 OR aas > 0)
                ");
            } catch (Exception $eTrim) {
            }

            // 7. TABEL GURU_MAPEL_KELAS
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS guru_mapel_kelas (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    mapel_id INT NOT NULL,
                    kelas_id INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_guru_mapel_kelas (user_id, mapel_id, kelas_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
            $log[] = 'Tabel guru_mapel_kelas siap';

            // 8. TABEL NILAI_SISWA
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS nilai_siswa (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    siswa_id INT NOT NULL,
                    kelas_id INT NOT NULL,
                    user_id INT NULL,
                    mapel VARCHAR(100) NOT NULL,
                    semester ENUM('Ganjil', 'Genap') NOT NULL DEFAULT 'Ganjil',
                    tahun_ajaran VARCHAR(20) NOT NULL,
                    sumatif_1 DECIMAL(5,2) DEFAULT 0,
                    sumatif_2 DECIMAL(5,2) DEFAULT 0,
                    sumatif_3 DECIMAL(5,2) DEFAULT 0,
                    sumatif_4 DECIMAL(5,2) DEFAULT 0,
                    ats DECIMAL(5,2) DEFAULT 0,
                    aas DECIMAL(5,2) DEFAULT 0,
                    nilai_akhir DECIMAL(5,2) DEFAULT 0,
                    predikat VARCHAR(5) DEFAULT '',
                    catatan TEXT NULL,
                    catatan_s1 TEXT NULL,
                    catatan_s2 TEXT NULL,
                    catatan_s3 TEXT NULL,
                    catatan_s4 TEXT NULL,
                    catatan_ats TEXT NULL,
                    catatan_aas TEXT NULL,
                    deskripsi_ats TEXT NULL,
                    deskripsi_aas TEXT NULL,
                    materi_ats_tinggi VARCHAR(255) NULL,
                    materi_ats_rendah VARCHAR(255) NULL,
                    materi_aas_tinggi VARCHAR(255) NULL,
                    materi_aas_rendah VARCHAR(255) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_nilai_siswa (siswa_id, kelas_id, mapel, semester, tahun_ajaran)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            // Pastikan kolom catatan & deskripsi ada jika nilai_siswa tabel lama
            $checkCols = [
                'catatan_s1' => 'TEXT NULL',
                'catatan_s2' => 'TEXT NULL',
                'catatan_s3' => 'TEXT NULL',
                'catatan_s4' => 'TEXT NULL',
                'catatan_ats' => 'TEXT NULL',
                'catatan_aas' => 'TEXT NULL',
                'deskripsi_ats' => 'TEXT NULL',
                'deskripsi_aas' => 'TEXT NULL',
                'materi_ats_tinggi' => 'VARCHAR(255) NULL',
                'materi_ats_rendah' => 'VARCHAR(255) NULL',
                'materi_aas_tinggi' => 'VARCHAR(255) NULL',
                'materi_aas_rendah' => 'VARCHAR(255) NULL',
            ];
            foreach ($checkCols as $col => $definition) {
                try {
                    $check = $pdo->query("SHOW COLUMNS FROM nilai_siswa LIKE '$col'")->fetch();
                    if (!$check) {
                        $pdo->exec("ALTER TABLE nilai_siswa ADD COLUMN $col $definition");
                    }
                } catch (Exception $eCol) {
                }
            }
            $log[] = 'Tabel nilai_siswa siap';

            // 9. TABEL MATERI_SUMATIF
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS materi_sumatif (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    kelas_id INT NOT NULL,
                    mapel VARCHAR(100) NOT NULL,
                    semester VARCHAR(10) NOT NULL DEFAULT 'Ganjil',
                    tahun_ajaran VARCHAR(20) NOT NULL,
                    materi_s1 VARCHAR(255) NULL,
                    materi_s2 VARCHAR(255) NULL,
                    materi_s3 VARCHAR(255) NULL,
                    materi_s4 VARCHAR(255) NULL,
                    materi_ats VARCHAR(255) NULL,
                    materi_aas VARCHAR(255) NULL,
                    tpl_ats_85 TEXT NULL,
                    tpl_ats_75 TEXT NULL,
                    tpl_ats_60 TEXT NULL,
                    tpl_ats_59 TEXT NULL,
                    tpl_aas_85 TEXT NULL,
                    tpl_aas_75 TEXT NULL,
                    tpl_aas_60 TEXT NULL,
                    tpl_aas_59 TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_mapel_kelas (kelas_id, mapel, semester, tahun_ajaran)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            // Pastikan kolom template pada materi_sumatif ada jika tabel lama
            $checkColsMateri = [
                'materi_s1' => 'VARCHAR(255) NULL',
                'materi_s2' => 'VARCHAR(255) NULL',
                'materi_s3' => 'VARCHAR(255) NULL',
                'materi_s4' => 'VARCHAR(255) NULL',
                'materi_ats' => 'VARCHAR(255) NULL',
                'materi_aas' => 'VARCHAR(255) NULL',
                'tpl_ats_85' => 'TEXT NULL',
                'tpl_ats_75' => 'TEXT NULL',
                'tpl_ats_60' => 'TEXT NULL',
                'tpl_ats_59' => 'TEXT NULL',
                'tpl_aas_85' => 'TEXT NULL',
                'tpl_aas_75' => 'TEXT NULL',
                'tpl_aas_60' => 'TEXT NULL',
                'tpl_aas_59' => 'TEXT NULL',
            ];
            foreach ($checkColsMateri as $col => $definition) {
                try {
                    $check = $pdo->query("SHOW COLUMNS FROM materi_sumatif LIKE '$col'")->fetch();
                    if (!$check) {
                        $pdo->exec("ALTER TABLE materi_sumatif ADD COLUMN $col $definition");
                    }
                } catch (Exception $eColM) {
                }
            }
            $log[] = 'Tabel materi_sumatif siap';

            // 10. TABEL RAPOR_SISWA & RAPOR_SETTINGS
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `rapor_siswa` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `siswa_id` int(11) NOT NULL,
                  `kelas_id` int(11) NOT NULL,
                  `tipe_rapor` enum('pts','pas') NOT NULL DEFAULT 'pas',
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

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `rapor_settings` (
                  `setting_key` varchar(100) NOT NULL,
                  `setting_value` text DEFAULT NULL,
                  PRIMARY KEY (`setting_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            $log[] = 'Tabel rapor_siswa & rapor_settings siap';

            // 11. TABEL POIN SISWA (siswa_poin_kategori, siswa_poin_riwayat)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `siswa_poin_kategori` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `nama_kategori` varchar(100) NOT NULL,
                    `tipe_poin` enum('pelanggaran','penghargaan') NOT NULL DEFAULT 'pelanggaran',
                    `deskripsi` text DEFAULT NULL,
                    `nilai_poin` int(11) NOT NULL DEFAULT 1,
                    `icon` varchar(50) DEFAULT 'bi-star-fill',
                    `color` varchar(20) DEFAULT '#fbbf24',
                    `status` enum('aktif','nonaktif') DEFAULT 'aktif',
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `nama_kategori` (`nama_kategori`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `siswa_poin_riwayat` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `siswa_id` int(11) NOT NULL,
                    `kategori_poin_id` int(11) NOT NULL,
                    `nilai_poin` int(11) NOT NULL,
                    `tipe_poin` enum('pelanggaran','penghargaan') NOT NULL,
                    `deskripsi` text DEFAULT NULL,
                    `bukti_foto` varchar(255) DEFAULT NULL,
                    `input_by` int(11) NOT NULL,
                    `tanggal` date NOT NULL DEFAULT CAST(CURDATE() AS CHAR),
                    `jam` time DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                    PRIMARY KEY (`id`),
                    KEY `siswa_id` (`siswa_id`),
                    KEY `kategori_poin_id` (`kategori_poin_id`),
                    KEY `input_by` (`input_by`),
                    KEY `idx_siswa_tanggal` (`siswa_id`, `tanggal`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            // 12. TABEL KANTIN & SALDO SISWA (saldo_siswa, kantin_topup, notifikasi_ortu, pembayaran_pending)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `saldo_siswa` (
                    `siswa_id` INT(11) NOT NULL PRIMARY KEY,
                    `saldo` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    `limit_tipe` VARCHAR(20) DEFAULT 'none',
                    `limit_nominal` DECIMAL(15,2) DEFAULT 0.00,
                    `limit_aktif` TINYINT(1) DEFAULT 0,
                    `limit_updated_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `kantin_topup` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `siswa_id` INT(11) NOT NULL,
                    `nominal` DECIMAL(15,2) NOT NULL,
                    `metode_bayar` VARCHAR(50) NOT NULL DEFAULT 'transfer',
                    `bukti_transfer` VARCHAR(255) DEFAULT NULL,
                    `keterangan` VARCHAR(255) DEFAULT NULL,
                    `user_id` INT(11) DEFAULT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            try {
                $checkBuktiTopup = $pdo->query("SHOW COLUMNS FROM kantin_topup LIKE 'bukti_transfer'")->fetch();
                if (!$checkBuktiTopup) {
                    $pdo->exec("ALTER TABLE kantin_topup ADD COLUMN bukti_transfer VARCHAR(255) NULL AFTER metode_bayar");
                }
                $checkKetTopup = $pdo->query("SHOW COLUMNS FROM kantin_topup LIKE 'keterangan'")->fetch();
                if (!$checkKetTopup) {
                    $pdo->exec("ALTER TABLE kantin_topup ADD COLUMN keterangan VARCHAR(255) NULL AFTER bukti_transfer");
                }
            } catch (Exception $eTopupCols) {
            }

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `notifikasi_ortu` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `siswa_id` INT(11) DEFAULT NULL,
                    `judul` VARCHAR(150) NOT NULL,
                    `pesan` TEXT NOT NULL,
                    `tipe` VARCHAR(50) DEFAULT 'info',
                    `icon` VARCHAR(50) DEFAULT 'bi-bell-fill',
                    `is_read` TINYINT(1) DEFAULT 0,
                    `is_dismissed` TINYINT(1) DEFAULT 0,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY `idx_notif_siswa` (`siswa_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `pembayaran_pending` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `siswa_id` INT(11) NOT NULL,
                    `jenis` VARCHAR(50) NOT NULL,
                    `jenis_pembayaran_id` INT(11) DEFAULT NULL,
                    `bulan` TINYINT(4) DEFAULT NULL,
                    `tahun` YEAR(4) DEFAULT NULL,
                    `nominal` DECIMAL(15,2) NOT NULL,
                    `bukti_transfer` VARCHAR(255) NOT NULL,
                    `catatan` TEXT DEFAULT NULL,
                    `status` ENUM('pending','disetujui','ditolak') DEFAULT 'pending',
                    `alasan_tolak` TEXT DEFAULT NULL,
                    `verified_by` INT(11) DEFAULT NULL,
                    `verified_at` DATETIME DEFAULT NULL,
                    `real_payment_id` INT(11) DEFAULT NULL,
                    `is_dismissed` TINYINT(1) DEFAULT 0,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY `siswa_id` (`siswa_id`),
                    KEY `idx_pending_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            try {
                $checkDismiss = $pdo->query("SHOW COLUMNS FROM pembayaran_pending LIKE 'is_dismissed'")->fetch();
                if (!$checkDismiss) {
                    $pdo->exec("ALTER TABLE pembayaran_pending ADD COLUMN is_dismissed TINYINT(1) DEFAULT 0 AFTER real_payment_id");
                }
            } catch (Exception $eDismiss) {
            }

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `mutasi_saldo` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `siswa_id` INT(11) NOT NULL,
                    `jenis` ENUM('topup','pembelian','refund') NOT NULL,
                    `jumlah` DECIMAL(15,2) NOT NULL,
                    `saldo_sebelum` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    `saldo_sesudah` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    `keterangan` TEXT DEFAULT NULL,
                    `ref_transaksi_id` INT(11) DEFAULT NULL,
                    `created_by` INT(11) DEFAULT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY `idx_mutasi_siswa` (`siswa_id`),
                    FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `kantin_transaksi` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `siswa_id` INT(11) DEFAULT NULL,
                    `kasir_user_id` INT(11) DEFAULT NULL,
                    `total_harga` DECIMAL(15,2) NOT NULL,
                    `metode_bayar` ENUM('saldo','tunai') NOT NULL DEFAULT 'saldo',
                    `status` ENUM('sukses','batal') NOT NULL DEFAULT 'sukses',
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY `idx_kt_siswa` (`siswa_id`),
                    KEY `idx_kt_kasir` (`kasir_user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $log[] = 'Tabel saldo_siswa, kantin_topup, mutasi_saldo, kantin_transaksi, notifikasi_ortu & pembayaran_pending siap';

        } catch (Exception $e) {
            $log[] = 'Sync Exception: ' . $e->getMessage();
        }

        return $log;
    }
}
