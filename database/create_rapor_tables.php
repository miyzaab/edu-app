<?php
require_once __DIR__ . '/../config/koneksi.php';
$pdo = getConnection();

// 1. Table rapor_siswa
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

// 2. Table rapor_settings
$pdo->exec("
CREATE TABLE IF NOT EXISTS `rapor_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// 3. Tambahkan kolom nisn ke siswa jika belum ada
try {
  $cols = $pdo->query("SHOW COLUMNS FROM siswa LIKE 'nisn'")->fetchAll();
  if (empty($cols)) {
    $pdo->exec("ALTER TABLE siswa ADD COLUMN `nisn` VARCHAR(20) DEFAULT NULL AFTER `nis`");
  }
} catch (Exception $e) {
}

// 3. Seed default settings
$defaultSettings = [
  'kepsek_nama' => 'Ustadz Ibrahim, M.Pd.',
  'kepsek_nip' => '-',
  'tempat_terbit' => 'Jakarta',
  'tanggal_pts_ganjil' => date('Y') . '-10-15',
  'tanggal_pas_ganjil' => date('Y') . '-12-20',
  'tanggal_pts_genap' => (date('Y') + 1) . '-03-20',
  'tanggal_pas_genap' => (date('Y') + 1) . '-06-25',
  'fase_default' => 'D',
  'kokurikuler_default' => 'Peserta didik aktif berpartisipasi dalam Projek Penguatan Profil Pelajar Pancasila (P5) dengan menunjukkan sikap mandiri, gotong royong, dan bernalar kritis.'
];

$stmt = $pdo->prepare("INSERT IGNORE INTO rapor_settings (setting_key, setting_value) VALUES (:k, :v)");
foreach ($defaultSettings as $k => $v) {
  $stmt->execute([':k' => $k, ':v' => $v]);
}

echo "RAPOR TABLES CREATED AND SEEDED SUCCESSFULLY!\n";
