<?php
/**
 * MIGRATION - Menambahkan Fitur Poin Pelanggaran & Penghargaan
 * untuk Modul Kesantrian (Halaqah)
 */

require_once __DIR__ . '/config/koneksi.php';

try {
    $pdo = getConnection();
    
    echo "🔄 Memulai migrasi database untuk fitur Poin...\n";

    // 1. Buat Tabel Kategori Poin
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
    echo "✅ Tabel siswa_poin_kategori berhasil dibuat/sudah ada\n";

    // 2. Buat Tabel Riwayat Poin Siswa
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
            KEY `idx_siswa_tanggal` (`siswa_id`, `tanggal`),
            CONSTRAINT `siswa_poin_riwayat_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `siswa_poin_riwayat_ibfk_2` FOREIGN KEY (`kategori_poin_id`) REFERENCES `siswa_poin_kategori` (`id`) ON UPDATE CASCADE,
            CONSTRAINT `siswa_poin_riwayat_ibfk_3` FOREIGN KEY (`input_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✅ Tabel siswa_poin_riwayat berhasil dibuat/sudah ada\n";

    // 3. Insert Default Kategori Poin Pelanggaran
    $defaultKategoriPelanggaran = [
        ['Datang Terlambat', 'pelanggaran', 'Siswa datang terlambat ke sekolah', 1, 'bi-clock-history', '#ef4444'],
        ['Tidak Mengerjakan PR', 'pelanggaran', 'Tidak mengerjakan pekerjaan rumah', 2, 'bi-exclamation-triangle-fill', '#f97316'],
        ['Tidak Berseragam', 'pelanggaran', 'Tidak berseragam sesuai aturan', 3, 'bi-exclamation-circle-fill', '#f43f5e'],
        ['Ramai di Kelas', 'pelanggaran', 'Membuat keributan di kelas', 2, 'bi-megaphone-fill', '#fb7185'],
        ['Tidak Fokus Belajar', 'pelanggaran', 'Tidak fokus/bermain saat pelajaran', 1, 'bi-eye-slash-fill', '#fca5a5'],
        ['Pelanggaran Tertib', 'pelanggaran', 'Melanggar peraturan tertib sekolah', 3, 'bi-shield-exclamation', '#dc2626'],
    ];

    // 4. Insert Default Kategori Poin Penghargaan
    $defaultKategoriPenghargaan = [
        ['Hafalan Surah', 'penghargaan', 'Berhasil menghafal surah baru', 5, 'bi-book-fill', '#10b981'],
        ['Nilai Sempurna', 'penghargaan', 'Mendapatkan nilai 100 dalam ujian', 4, 'bi-star-fill', '#fbbf24'],
        ['Piket Rapi', 'penghargaan', 'Melakukan piket dengan sempurna', 2, 'bi-check-circle-fill', '#34d399'],
        ['Membantu Teman', 'penghargaan', 'Membantu teman yang kesulitan', 3, 'bi-heart-fill', '#ec4899'],
        ['Prestasi Akademik', 'penghargaan', 'Prestasi akademik luar biasa', 5, 'bi-award-fill', '#8b5cf6'],
        ['Kehadiran 100%', 'penghargaan', 'Tidak pernah absen dalam 1 bulan', 4, 'bi-calendar-check-fill', '#06b6d4'],
        ['Budi Pekerti Baik', 'penghargaan', 'Menunjukkan budi pekerti yang baik', 3, 'bi-hand-thumbs-up-fill', '#14b8a6'],
    ];

    // Cek dan insert kategori pelanggaran
    foreach ($defaultKategoriPelanggaran as $kat) {
        $check = $pdo->prepare("SELECT id FROM siswa_poin_kategori WHERE nama_kategori = ?");
        $check->execute([$kat[0]]);
        if (!$check->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO siswa_poin_kategori 
                (nama_kategori, tipe_poin, deskripsi, nilai_poin, icon, color, status)
                VALUES (?, ?, ?, ?, ?, ?, 'aktif')
            ");
            $stmt->execute($kat);
        }
    }
    echo "✅ Kategori poin pelanggaran default berhasil diisi\n";

    // Cek dan insert kategori penghargaan
    foreach ($defaultKategoriPenghargaan as $kat) {
        $check = $pdo->prepare("SELECT id FROM siswa_poin_kategori WHERE nama_kategori = ?");
        $check->execute([$kat[0]]);
        if (!$check->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO siswa_poin_kategori 
                (nama_kategori, tipe_poin, deskripsi, nilai_poin, icon, color, status)
                VALUES (?, ?, ?, ?, ?, ?, 'aktif')
            ");
            $stmt->execute($kat);
        }
    }
    echo "✅ Kategori poin penghargaan default berhasil diisi\n";

    echo "\n🎉 Migrasi Fitur Poin berhasil dilakukan!\n";
    echo "Silahkan refresh halaman atau reload aplikasi.\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
