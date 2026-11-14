<?php
/**
 * AUTO-MIGRATION & DATABASE INIT PERANGKAT GURU (SOAL & ASESMEN)
 * Islamic School of Minhaj Al-Ilmi
 */

if (!defined('SOAL_INIT_RUN')) {
    define('SOAL_INIT_RUN', true);

    try {
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            require_once __DIR__ . '/../../config/koneksi.php';
            $pdo = getConnection();
        }

        // 1. Buat Tabel soal_naskah jika belum ada
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `soal_naskah` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `guru_id` int(11) NOT NULL,
              `mapel_id` int(11) DEFAULT NULL,
              `nama_mapel` varchar(150) NOT NULL,
              `tingkat_kelas` varchar(20) NOT NULL DEFAULT 'VII',
              `kelas_id` int(11) DEFAULT NULL,
              `tipe_ujian` varchar(30) NOT NULL DEFAULT 'AAS',
              `judul_ujian` varchar(255) NOT NULL DEFAULT 'UJIAN AKHIR SEMESTER',
              `semester` enum('Ganjil','Genap') NOT NULL DEFAULT 'Ganjil',
              `tahun_ajaran` varchar(30) NOT NULL DEFAULT '2025 – 2026',
              `alokasi_waktu` int(11) NOT NULL DEFAULT 90,
              `tanggal_ujian` varchar(50) DEFAULT NULL,
              `petunjuk_umum` text DEFAULT NULL,
              `total_poin` decimal(6,2) NOT NULL DEFAULT 0.00,
              `total_pg` int(11) NOT NULL DEFAULT 0,
              `total_isian` int(11) NOT NULL DEFAULT 0,
              `total_essay` int(11) NOT NULL DEFAULT 0,
              `status` enum('draft','siap_cetak') NOT NULL DEFAULT 'draft',
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_guru` (`guru_id`),
              KEY `idx_mapel` (`nama_mapel`),
              KEY `idx_semester_thn` (`semester`,`tahun_ajaran`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 2. Buat Tabel soal_butir jika belum ada
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `soal_butir` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `naskah_id` int(11) NOT NULL,
              `nomor_urut` int(11) NOT NULL DEFAULT 1,
              `tipe_soal` enum('pg','isian','essay') NOT NULL DEFAULT 'pg',
              `pertanyaan` longtext NOT NULL,
              `gambar` varchar(255) DEFAULT NULL,
              `pilihan_a` text DEFAULT NULL,
              `pilihan_b` text DEFAULT NULL,
              `pilihan_c` text DEFAULT NULL,
              `pilihan_d` text DEFAULT NULL,
              `kunci_jawaban` text DEFAULT NULL,
              `poin` decimal(5,2) NOT NULL DEFAULT 1.00,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_naskah` (`naskah_id`),
              KEY `idx_tipe` (`tipe_soal`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 3. Buat Tabel soal_hasil_koreksi (Meja Koreksi Cepat & Rekap Otomatis)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `soal_hasil_koreksi` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `naskah_id` int(11) NOT NULL,
              `siswa_id` int(11) NOT NULL,
              `kelas_id` int(11) NOT NULL,
              `benar_pg` int(11) NOT NULL DEFAULT 0,
              `skor_pg` decimal(6,2) NOT NULL DEFAULT 0.00,
              `skor_isian` decimal(6,2) NOT NULL DEFAULT 0.00,
              `skor_essay` decimal(6,2) NOT NULL DEFAULT 0.00,
              `nilai_akhir` decimal(6,2) NOT NULL DEFAULT 0.00,
              `catatan` text DEFAULT NULL,
              `detail_pg` longtext DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_naskah_siswa` (`naskah_id`, `siswa_id`),
              KEY `idx_naskah_kelas` (`naskah_id`, `kelas_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Pastikan folder upload tersedia
        $uploadDir = __DIR__ . '/../../uploads/soal';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

    } catch (Exception $e) {
        error_log("Error init soal: " . $e->getMessage());
    }
}

/**
 * Recalculate stats & points for an exam paper
 */
if (!function_exists('rehitungPoinNaskah')) {
    function rehitungPoinNaskah(int $naskahId, PDO $pdo): array
    {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN tipe_soal = 'pg' THEN 1 END) AS count_pg,
                COUNT(CASE WHEN tipe_soal = 'isian' THEN 1 END) AS count_isian,
                COUNT(CASE WHEN tipe_soal = 'essay' THEN 1 END) AS count_essay,
                COALESCE(SUM(poin), 0) AS total_poin
            FROM soal_butir
            WHERE naskah_id = :nid
        ");
        $stmt->execute([':nid' => $naskahId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalPoin = (float) ($res['total_poin'] ?? 0);
        $totalPg = (int) ($res['count_pg'] ?? 0);
        $totalIsian = (int) ($res['count_isian'] ?? 0);
        $totalEssay = (int) ($res['count_essay'] ?? 0);

        $upd = $pdo->prepare("
            UPDATE soal_naskah 
            SET total_poin = :poin, 
                total_pg = :pg, 
                total_isian = :isian, 
                total_essay = :essay,
                updated_at = NOW()
            WHERE id = :nid
        ");
        $upd->execute([
            ':poin' => $totalPoin,
            ':pg' => $totalPg,
            ':isian' => $totalIsian,
            ':essay' => $totalEssay,
            ':nid' => $naskahId
        ]);

        return [
            'total_poin' => $totalPoin,
            'total_pg' => $totalPg,
            'total_isian' => $totalIsian,
            'total_essay' => $totalEssay
        ];
    }
}
