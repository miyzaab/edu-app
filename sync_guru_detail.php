<?php
/**
 * DATABASE MIGRATION - Membuat & Menyinkronkan Tabel guru_detail
 */
require_once __DIR__ . '/config/koneksi.php';

$pdo = getConnection();

try {
    // 1. Buat tabel guru_detail jika belum ada
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
    echo "✓ Tabel guru_detail berhasil disiapkan.\n";

    // 2. Inisialisasi detail untuk semua user role='guru'
    $stmtG = $pdo->query("SELECT id FROM users WHERE role = 'guru'");
    $guruIds = $stmtG->fetchAll(PDO::FETCH_COLUMN);

    $stmtIns = $pdo->prepare("
        INSERT IGNORE INTO guru_detail (user_id) VALUES (:uid)
    ");
    foreach ($guruIds as $uid) {
        $stmtIns->execute([':uid' => $uid]);
    }
    echo "✓ Detail untuk " . count($guruIds) . " guru berhasil diinisialisasi.\n";

} catch (Exception $e) {
    echo "X Error: " . $e->getMessage() . "\n";
}
