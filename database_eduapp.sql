-- EDU-APP COMPLETE DATABASE DUMP
-- Generated: 2026-07-22 17:15:25
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `jenis_pembayaran` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_pembayaran` varchar(100) NOT NULL,
  `nominal_default` decimal(15,2) DEFAULT 0.00,
  `keterangan` text DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nama_pembayaran` (`nama_pembayaran`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO jenis_pembayaran (id, nama_pembayaran, nominal_default, keterangan, status, created_at, updated_at) VALUES ('5', 'Daftar Ulang', '1000000.00', 'Pembayaran Daftar Ulang', 'aktif', '2026-04-17 23:10:04', '2026-04-17 23:10:50');

CREATE TABLE `kelas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_kelas` varchar(20) NOT NULL,
  `is_locked` tinyint(1) DEFAULT 0,
  `tingkat` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nama_kelas` (`nama_kelas`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO kelas (id, nama_kelas, is_locked, tingkat, created_at, updated_at) VALUES ('1', 'VII', '0', 'VII', '2026-04-17 20:43:06', '2026-04-18 00:27:05');
INSERT INTO kelas (id, nama_kelas, is_locked, tingkat, created_at, updated_at) VALUES ('4', 'VIII-A', '0', 'VIII', '2026-04-17 20:43:06', '2026-04-17 20:43:06');
INSERT INTO kelas (id, nama_kelas, is_locked, tingkat, created_at, updated_at) VALUES ('5', 'VIII-B', '0', 'VIII', '2026-04-17 20:43:06', '2026-04-17 20:43:06');

CREATE TABLE `pembayaran_lain` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `siswa_id` int(11) NOT NULL,
  `jenis_pembayaran_id` int(11) NOT NULL,
  `nominal` decimal(15,2) NOT NULL,
  `tanggal_bayar` date NOT NULL,
  `metode_bayar` enum('tunai','transfer') DEFAULT 'tunai',
  `keterangan` text DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `siswa_id` (`siswa_id`),
  KEY `jenis_pembayaran_id` (`jenis_pembayaran_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_pl_tanggal` (`tanggal_bayar`),
  CONSTRAINT `pembayaran_lain_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `pembayaran_lain_ibfk_2` FOREIGN KEY (`jenis_pembayaran_id`) REFERENCES `jenis_pembayaran` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `pembayaran_lain_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `pembayaran_pending` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `siswa_id` int(11) NOT NULL,
  `jenis` varchar(50) NOT NULL,
  `jenis_pembayaran_id` int(11) DEFAULT NULL,
  `bulan` tinyint(4) DEFAULT NULL,
  `tahun` year(4) DEFAULT NULL,
  `nominal` decimal(15,2) NOT NULL,
  `bukti_transfer` varchar(255) NOT NULL,
  `catatan` text DEFAULT NULL,
  `status` enum('pending','disetujui','ditolak') DEFAULT 'pending',
  `alasan_tolak` text DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `real_payment_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `siswa_id` (`siswa_id`),
  KEY `verified_by` (`verified_by`),
  KEY `idx_pending_status` (`status`),
  CONSTRAINT `pembayaran_pending_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`),
  CONSTRAINT `pembayaran_pending_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO pembayaran_pending (id, siswa_id, jenis, jenis_pembayaran_id, bulan, tahun, nominal, bukti_transfer, catatan, status, alasan_tolak, verified_by, verified_at, real_payment_id, created_at) VALUES ('7', '12', 'spp', NULL, '3', '2026', '500000.00', '/edu-app/assets/uploads/bukti/bukti_1776477186_2197.jpg', '', 'ditolak', 'Tidak bisa', '1', '2026-04-18 08:53:21', NULL, '2026-04-18 08:53:06');

CREATE TABLE `pembayaran_spp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `siswa_id` int(11) NOT NULL,
  `bulan` tinyint(4) NOT NULL,
  `tahun` year(4) NOT NULL,
  `nominal` decimal(15,2) NOT NULL,
  `tanggal_bayar` date NOT NULL,
  `metode_bayar` enum('tunai','transfer') DEFAULT 'tunai',
  `keterangan` text DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_spp` (`siswa_id`,`bulan`,`tahun`),
  KEY `user_id` (`user_id`),
  KEY `idx_spp_tanggal` (`tanggal_bayar`),
  KEY `idx_spp_bulan_tahun` (`bulan`,`tahun`),
  CONSTRAINT `pembayaran_spp_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `pembayaran_spp_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO pembayaran_spp (id, siswa_id, bulan, tahun, nominal, tanggal_bayar, metode_bayar, keterangan, user_id, created_at, updated_at) VALUES ('20', '12', '4', '2026', '350000.00', '2026-04-17', 'tunai', '', '1', '2026-04-18 00:30:42', '2026-04-18 00:30:42');

CREATE TABLE `pembayaran_uang_pangkal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `siswa_id` int(11) NOT NULL,
  `nominal` decimal(15,2) NOT NULL,
  `tanggal_bayar` date NOT NULL,
  `metode_bayar` enum('tunai','transfer') DEFAULT 'tunai',
  `keterangan` text DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `siswa_id` (`siswa_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_up_tanggal` (`tanggal_bayar`),
  CONSTRAINT `pembayaran_uang_pangkal_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `pembayaran_uang_pangkal_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `perangkat_ajar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `mapel` varchar(100) NOT NULL,
  `kelas` varchar(20) NOT NULL,
  `fase` varchar(10) DEFAULT 'D',
  `semester` enum('Ganjil','Genap') DEFAULT 'Ganjil',
  `tahun_ajaran` varchar(20) NOT NULL,
  `topik` varchar(255) NOT NULL,
  `elemen` varchar(255) DEFAULT NULL,
  `alokasi_waktu` varchar(50) DEFAULT '2 JP x 40 Menit',
  `capaian_pembelajaran` text DEFAULT NULL,
  `tujuan_pembelajaran` text DEFAULT NULL,
  `alur_tujuan_pembelajaran` text DEFAULT NULL,
  `modul_ajar_json` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `perangkat_ajar_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO perangkat_ajar (id, user_id, mapel, kelas, fase, semester, tahun_ajaran, topik, elemen, alokasi_waktu, capaian_pembelajaran, tujuan_pembelajaran, alur_tujuan_pembelajaran, modul_ajar_json, created_at, updated_at) VALUES ('1', '3', 'Bahasa Indonesia', 'VII (Tujuh)', 'D', 'Ganjil', '2026/2027', 'Menulis Teks Laporan Hasil Observasi (LHO)', 'Menyimak & Membaca', '2 JP x 40 Menit', 'Peserta didik mampu menganalisis dan mengevaluasi informasi berupa gagasan, pikiran, perasaan, atau pesan dari berbagai jenis teks deskripsi, narasi, dan laporan hasil observasi.', '1. Peserta didik mampu mengidentifikasi struktur teks Laporan Hasil Observasi.\r\n2. Peserta didik mampu menganalisis kebahasaan teks LHO (kata baku, kalimat definisi).\r\n3. Peserta didik mampu menyusun teks Laporan Hasil Observasi secara sistematis.', 'Tahap 1: Membaca & Menganalisis Contoh Teks LHO (2 JP)\r\nTahap 2: Pengamatan Lingkungan Sekolah (2 JP)\r\nTahap 3: Menyusun Kerangka & Menulis Teks LHO (4 JP)', '{\"nama_sekolah\":\"SMP Islamic School of Minhaj Al-Ilmi\",\"nama_guru\":\"Zia Abdurrofi\",\"nip_guru\":\"12345678\",\"nama_kepsek\":\"Kepala Sekolah, M.Pd\",\"nip_kepsek\":\"\",\"model_pembelajaran\":\"Problem-Based Learning (PBL)\",\"profil_pancasila\":[\"Bernalar Kritis\",\"Gotong Royong\",\"Kreatif\"],\"kompetensi_awal\":\"Peserta didik memahami perbedaan kalimat fakta dan opini.\",\"sarana_prasarana\":\"Buku Siswa Bahasa Indonesia SMP, Lingkungan Sekolah, Lembar Observasi.\",\"target_siswa\":\"Peserta didik reguler \\/ tipikal (28-32 siswa)\",\"pemahaman_bermakna\":\"Laporan hasil observasi menyajikan fakta obyektif lingkungan sekitar secara ilmiah.\",\"pertanyaan_pemantik\":\"Apa perbedaan antara cerita fiksi dengan teks laporan hasil pengamatan langsung?\",\"kegiatan_pendahuluan\":\"1. Salam, doa, dan ice breaking.\\r\\n2. Review materi sebelumnya dan apersepsi topik observasi.\",\"kegiatan_inti\":\"1. Siswa membaca contoh teks LHO lingkungan sekolah.\\r\\n2. Identifikasi bagian definisi umum, deskripsi bagian, dan kesimpulan.\\r\\n3. Observasi lapangan singkat di taman sekolah secara berkelompok.\",\"kegiatan_penutup\":\"1. Refleksi pembelajaran.\\r\\n2. Penyampaian rencana sesi penulisan di pertemuan berikutnya.\",\"asesmen_diagnostik\":\"Pertanyaan pemantik tentang objek di sekitar sekolah.\",\"asesmen_formatif\":\"Penilaian draf tabel observasi kelompok.\",\"asesmen_sumatif\":\"Produk teks Laporan Hasil Observasi (rubrik struktur & kebahasaan).\",\"lkpd_content\":\"LKPD Observasi Taman Sekolah: Catatlah 3 fakta fisik objek taman sekolah yang kamu amati!\",\"bahan_bacaan\":\"\",\"glosarium\":\"Observasi: Pengamatan langsung terhadap objek.\\r\\nObjektif: Berdasarkan fakta sebenarnya tanpa prasangka.\",\"daftar_pustaka\":\"Kemendikbudristek. (2022). Bahasa Indonesia SMP Kelas VII. Jakarta.\"}', '2026-07-22 21:56:45', '2026-07-22 21:56:45');
INSERT INTO perangkat_ajar (id, user_id, mapel, kelas, fase, semester, tahun_ajaran, topik, elemen, alokasi_waktu, capaian_pembelajaran, tujuan_pembelajaran, alur_tujuan_pembelajaran, modul_ajar_json, created_at, updated_at) VALUES ('2', '5', 'Pendidikan Agama Islam & Budi Pekerti', 'VII (Tujuh)', 'D', 'Ganjil', '2026/2027', 'Memahami Iman kepada Allah', 'Iman Kepada Allah', '2 JP x 40 Menit', 'Pada akhir fase D, peserta didik mampu memahami, menganalisis, dan mengaplikasikan konsep-konsep kunci dalam mata pelajaran Pendidikan Agama Islam & Budi Pekerti secara mandiri maupun kolaboratif.', '1. Peserta didik mampu menjelaskan konsep dasar Memahami Iman kepada Allah.\r\n2. Peserta didik mampu mengaplikasikan prosedur pemecahan masalah sesuai materi.\r\n3. Peserta didik mampu merefleksikan hasil pembelajaran dalam kehidupan sehari-hari.', 'Tahap 1: Pendalaman Konsep Dasar (2 JP)\r\nTahap 2: Diskusi & Penyelesaian Studi Kasus (4 JP)\r\nTahap 3: Asesmen dan Evaluasi Capaian (2 JP)', '{\"nama_sekolah\":\"SMP Islamic School of Minhaj Al-Ilmi\",\"nama_guru\":\"Ustadz Tamimi\",\"nip_guru\":\"2025612105\",\"nama_kepsek\":\"Ustadz Ibrahim\",\"nip_kepsek\":\"\",\"model_pembelajaran\":\"Problem-Based Learning (PBL)\",\"profil_pancasila\":[\"Bernalar Kritis\",\"Gotong Royong\",\"Kreatif\"],\"kompetensi_awal\":\"Peserta didik memahami pengetahuan dasar materi prasyarat.\",\"sarana_prasarana\":\"Buku Siswa Pendidikan Agama Islam & Budi Pekerti, PPT, Laptop, LCD Projector, LKPD.\",\"target_siswa\":\"Peserta didik reguler \\/ tipikal (28-32 siswa)\",\"pemahaman_bermakna\":\"Mempelajari Memahami Iman kepada Allah memberikan wawasan dan keterampilan praktis untuk memecahkan masalah.\",\"pertanyaan_pemantik\":\"Bagaimana relevansi topik Memahami Iman kepada Allah dengan kehidupan sehari-hari kita?\",\"kegiatan_pendahuluan\":\"1. Salam pembuka, doa bersama, dan presensi.\\r\\n2. Apersepsi dan penyampaian tujuan pembelajaran.\",\"kegiatan_inti\":\"1. Orientasi peserta didik pada masalah nyata.\\r\\n2. Mengorganisasikan siswa untuk belajar dalam kelompok.\\r\\n3. Membimbing penyelidikan individu\\/kelompok.\\r\\n4. Mengembangkan dan menyajikan hasil karya.\\r\\n5. Menganalisis dan mengevaluasi proses pemecahan masalah.\",\"kegiatan_penutup\":\"1. Rangkuman bersama peserta didik.\\r\\n2. Refleksi dan penugasan.\",\"asesmen_diagnostik\":\"Tes pertanyaan lisan awal pembelajaran.\",\"asesmen_formatif\":\"Penilaian proses diskusi dan tugas LKPD.\",\"asesmen_sumatif\":\"Tes akhir modul \\/ pilihan ganda dan esai.\",\"lkpd_content\":\"LKPD 1: Lembar Tugas Kelompok Memahami Iman kepada Allah.\",\"bahan_bacaan\":\"\",\"glosarium\":\"Istilah Penting: Definisi ringkas materi pokok.\",\"daftar_pustaka\":\"Kemendikbudristek. (2022). Buku Panduan Guru Pendidikan Agama Islam & Budi Pekerti SMP. Jakarta.\"}', '2026-07-22 22:01:22', '2026-07-22 22:01:22');

CREATE TABLE `petty_cash` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `jenis` enum('masuk','keluar') NOT NULL,
  `nominal` decimal(15,2) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `petty_cash_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO petty_cash (id, tanggal, jenis, nominal, keterangan, user_id, created_at, updated_at) VALUES ('1', '2026-04-17', 'masuk', '1000000.00', 'Dana BOS', '1', '2026-04-18 00:36:44', '2026-04-18 00:36:44');

CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (id, setting_key, setting_value, updated_at) VALUES ('1', 'nama_sekolah', 'SMP Islamic School of Minhaj Al-Ilmi', '2026-04-17 21:01:26');
INSERT INTO settings (id, setting_key, setting_value, updated_at) VALUES ('2', 'alamat_sekolah', 'Jl. Nakula No. 1', '2026-04-17 23:52:59');
INSERT INTO settings (id, setting_key, setting_value, updated_at) VALUES ('3', 'telepon_sekolah', '021-0000000', '2026-04-17 21:01:26');
INSERT INTO settings (id, setting_key, setting_value, updated_at) VALUES ('4', 'logo_path', '/edu-app/assets/img/logo_1776435927.png', '2026-04-17 21:25:27');
INSERT INTO settings (id, setting_key, setting_value, updated_at) VALUES ('5', 'kwitansi_header', 'KWITANSI PEMBAYARAN', '2026-04-17 21:01:26');
INSERT INTO settings (id, setting_key, setting_value, updated_at) VALUES ('6', 'kwitansi_footer', 'Syukron wa Jazaakumullahu Khairan  atas pembayarannya', '2026-04-17 23:52:59');
INSERT INTO settings (id, setting_key, setting_value, updated_at) VALUES ('18', 'app_name', 'Edu-App', '2026-07-22 22:08:44');
INSERT INTO settings (id, setting_key, setting_value, updated_at) VALUES ('22', 'whatsapp_admin', '628123456789', '2026-07-22 21:46:49');
INSERT INTO settings (id, setting_key, setting_value, updated_at) VALUES ('23', 'kota_sekolah', '', '2026-07-22 21:46:49');
INSERT INTO settings (id, setting_key, setting_value, updated_at) VALUES ('24', 'nama_kepala_sekolah', '', '2026-07-22 21:46:49');
INSERT INTO settings (id, setting_key, setting_value, updated_at) VALUES ('25', 'kwitansi_prefix', 'KWI', '2026-07-22 21:46:49');
INSERT INTO settings (id, setting_key, setting_value, updated_at) VALUES ('28', 'pdf_judul', 'BUKU KAS UMUM (PETTY CASH)', '2026-07-22 21:46:49');
INSERT INTO settings (id, setting_key, setting_value, updated_at) VALUES ('29', 'pdf_footer', '', '2026-07-22 21:46:49');
INSERT INTO settings (id, setting_key, setting_value, updated_at) VALUES ('30', 'wa_share_template', 'Halo Bapak/Ibu Wali Murid {nama},\r\n\r\nBerikut adalah rincian pembayaran Anda di {sekolah}:\r\n\r\n*{judul}*\r\nNo: {no}\r\nTotal: *{nominal}*\r\nStatus: *LUNAS*\r\n\r\nLink Kwitansi Digital: {link}\r\n\r\nTerima kasih.', '2026-07-22 21:46:49');
INSERT INTO settings (id, setting_key, setting_value, updated_at) VALUES ('31', 'theme_color', '#0dcaf0', '2026-07-22 21:46:49');
INSERT INTO settings (id, setting_key, setting_value, updated_at) VALUES ('32', 'login_bg_color', '#212121', '2026-07-22 22:12:20');

CREATE TABLE `siswa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nis` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `kelas_id` int(11) NOT NULL,
  `jenis_kelamin` enum('L','P') NOT NULL,
  `tahun_masuk` year(4) NOT NULL,
  `status` enum('aktif','lulus','keluar') DEFAULT 'aktif',
  `target_uang_pangkal` decimal(15,2) DEFAULT 0.00,
  `nominal_spp` decimal(15,2) DEFAULT 350000.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nis` (`nis`),
  KEY `kelas_id` (`kelas_id`),
  KEY `idx_siswa_status` (`status`),
  CONSTRAINT `siswa_ibfk_1` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO siswa (id, nis, nama, kelas_id, jenis_kelamin, tahun_masuk, status, target_uang_pangkal, nominal_spp, created_at, updated_at) VALUES ('12', '12345', 'Budi', '1', 'L', '2026', 'aktif', '0.00', '350000.00', '2026-04-18 00:24:49', '2026-04-18 00:24:49');

CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `permission_key` varchar(50) NOT NULL,
  `is_allowed` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_perm` (`user_id`,`permission_key`),
  CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=176 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('1', '1', 'dashboard', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('2', '1', 'verifikasi', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('3', '1', 'riwayat', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('4', '1', 'rekap_spp', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('5', '1', 'spp', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('6', '1', 'uang_pangkal', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('7', '1', 'pembayaran_lain', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('8', '1', 'kelas', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('9', '1', 'siswa', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('10', '1', 'jenis_pembayaran', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('11', '1', 'petty_cash', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('12', '1', 'laporan', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('13', '1', 'users', '0', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('14', '1', 'pengaturan', '0', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('15', '2', 'dashboard', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('16', '2', 'verifikasi', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('17', '2', 'riwayat', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('18', '2', 'rekap_spp', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('19', '2', 'spp', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('20', '2', 'uang_pangkal', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('21', '2', 'pembayaran_lain', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('22', '2', 'kelas', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('23', '2', 'siswa', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('24', '2', 'jenis_pembayaran', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('25', '2', 'petty_cash', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('26', '2', 'laporan', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('27', '2', 'users', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('28', '2', 'pengaturan', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('29', '3', 'dashboard', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('30', '3', 'verifikasi', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('31', '3', 'riwayat', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('32', '3', 'rekap_spp', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('33', '3', 'spp', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('34', '3', 'uang_pangkal', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('35', '3', 'pembayaran_lain', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('36', '3', 'kelas', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('37', '3', 'siswa', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('38', '3', 'jenis_pembayaran', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('39', '3', 'petty_cash', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('40', '3', 'laporan', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('41', '3', 'users', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('42', '3', 'pengaturan', '1', '2026-07-22 20:14:12', '2026-07-22 20:14:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('43', '4', 'dashboard', '0', '2026-07-22 21:43:12', '2026-07-22 21:43:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('44', '4', 'verifikasi', '0', '2026-07-22 21:43:12', '2026-07-22 21:43:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('45', '4', 'riwayat', '0', '2026-07-22 21:43:12', '2026-07-22 21:43:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('46', '4', 'rekap_spp', '0', '2026-07-22 21:43:12', '2026-07-22 21:43:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('47', '4', 'spp', '0', '2026-07-22 21:43:12', '2026-07-22 21:43:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('48', '4', 'uang_pangkal', '0', '2026-07-22 21:43:12', '2026-07-22 21:43:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('49', '4', 'pembayaran_lain', '0', '2026-07-22 21:43:12', '2026-07-22 21:43:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('50', '4', 'kelas', '0', '2026-07-22 21:43:12', '2026-07-22 21:43:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('51', '4', 'siswa', '0', '2026-07-22 21:43:12', '2026-07-22 21:43:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('52', '4', 'jenis_pembayaran', '0', '2026-07-22 21:43:12', '2026-07-22 21:43:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('53', '4', 'petty_cash', '0', '2026-07-22 21:43:12', '2026-07-22 21:43:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('54', '4', 'laporan', '0', '2026-07-22 21:43:12', '2026-07-22 21:43:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('55', '4', 'users', '0', '2026-07-22 21:43:12', '2026-07-22 21:43:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('56', '4', 'pengaturan', '0', '2026-07-22 21:43:12', '2026-07-22 21:43:12');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('57', '5', 'dashboard', '0', '2026-07-22 21:58:13', '2026-07-22 21:58:32');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('58', '5', 'verifikasi', '0', '2026-07-22 21:58:13', '2026-07-22 21:58:13');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('59', '5', 'riwayat', '0', '2026-07-22 21:58:13', '2026-07-22 21:58:32');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('60', '5', 'rekap_spp', '0', '2026-07-22 21:58:13', '2026-07-22 21:58:32');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('61', '5', 'rekap_uang_pangkal', '0', '2026-07-22 21:58:13', '2026-07-22 21:58:32');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('62', '5', 'rekap_daftar_ulang', '0', '2026-07-22 21:58:13', '2026-07-22 21:58:32');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('63', '5', 'spp', '0', '2026-07-22 21:58:13', '2026-07-22 21:58:13');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('64', '5', 'uang_pangkal', '0', '2026-07-22 21:58:13', '2026-07-22 21:58:13');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('65', '5', 'pembayaran_lain', '0', '2026-07-22 21:58:13', '2026-07-22 21:58:13');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('66', '5', 'kelas', '0', '2026-07-22 21:58:13', '2026-07-22 21:58:32');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('67', '5', 'siswa', '0', '2026-07-22 21:58:13', '2026-07-22 21:58:32');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('68', '5', 'jenis_pembayaran', '0', '2026-07-22 21:58:13', '2026-07-22 21:58:13');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('69', '5', 'perangkat_ajar', '1', '2026-07-22 21:58:13', '2026-07-22 21:58:13');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('70', '5', 'petty_cash', '0', '2026-07-22 21:58:13', '2026-07-22 21:58:13');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('71', '5', 'laporan', '0', '2026-07-22 21:58:13', '2026-07-22 21:58:13');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('72', '5', 'users', '0', '2026-07-22 21:58:13', '2026-07-22 21:58:13');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('73', '5', 'pengaturan', '0', '2026-07-22 21:58:13', '2026-07-22 21:58:13');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('95', '1', 'rekap_uang_pangkal', '1', '2026-07-22 22:02:45', '2026-07-22 22:02:45');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('96', '1', 'rekap_daftar_ulang', '1', '2026-07-22 22:02:45', '2026-07-22 22:02:45');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('103', '1', 'perangkat_ajar', '1', '2026-07-22 22:02:45', '2026-07-22 22:02:45');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('112', '2', 'rekap_uang_pangkal', '1', '2026-07-22 22:02:45', '2026-07-22 22:02:45');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('113', '2', 'rekap_daftar_ulang', '1', '2026-07-22 22:02:45', '2026-07-22 22:02:45');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('120', '2', 'perangkat_ajar', '1', '2026-07-22 22:02:45', '2026-07-22 22:02:45');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('129', '3', 'rekap_uang_pangkal', '1', '2026-07-22 22:02:45', '2026-07-22 22:02:45');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('130', '3', 'rekap_daftar_ulang', '1', '2026-07-22 22:02:45', '2026-07-22 22:02:45');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('137', '3', 'perangkat_ajar', '1', '2026-07-22 22:02:45', '2026-07-22 22:02:45');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('146', '4', 'rekap_uang_pangkal', '0', '2026-07-22 22:02:45', '2026-07-22 22:02:45');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('147', '4', 'rekap_daftar_ulang', '0', '2026-07-22 22:02:45', '2026-07-22 22:02:45');
INSERT INTO user_permissions (id, user_id, permission_key, is_allowed, created_at, updated_at) VALUES ('154', '4', 'perangkat_ajar', '0', '2026-07-22 22:02:45', '2026-07-22 22:02:45');

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `role` enum('admin','bendahara','guru','ortu') DEFAULT 'bendahara',
  `siswa_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `fk_users_siswa` (`siswa_id`),
  CONSTRAINT `fk_users_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (id, username, password, nama_lengkap, role, siswa_id, is_active, last_login, created_at, updated_at) VALUES ('1', 'bendahara', '$2y$10$ek8K9PSS67ha5e87jVUnKOetlS3fDvR9hn40s61SiKDH.WjtpIDPS', 'Bendahara Sekolah', 'bendahara', NULL, '1', '2026-07-22 21:35:18', '2026-04-17 20:43:06', '2026-07-22 21:35:18');
INSERT INTO users (id, username, password, nama_lengkap, role, siswa_id, is_active, last_login, created_at, updated_at) VALUES ('2', 'ziaabdurrofi', '$2y$10$YRSA4OluC66o2hFjMqk5Le8yy.AXtppTaH37aQZM5.nIOcfc0lnT.', 'Muhammad Zia Abdurrofi', 'admin', NULL, '1', '2026-04-18 13:17:28', '2026-04-17 21:10:27', '2026-07-22 21:42:00');
INSERT INTO users (id, username, password, nama_lengkap, role, siswa_id, is_active, last_login, created_at, updated_at) VALUES ('3', 'admin', '$2y$10$YRSA4OluC66o2hFjMqk5Le8yy.AXtppTaH37aQZM5.nIOcfc0lnT.', 'Administrator', 'admin', NULL, '1', '2026-07-22 22:03:07', '2026-07-22 19:32:56', '2026-07-22 22:03:07');
INSERT INTO users (id, username, password, nama_lengkap, role, siswa_id, is_active, last_login, created_at, updated_at) VALUES ('4', 'budi', '$2y$10$tGSY7vNg8Vrc4ov.DXKxbea4fKkELfQyGsj2/uxMhjBzgAIBjVf7a', 'Orang Tua Budi', 'ortu', '12', '1', '2026-07-22 21:43:42', '2026-07-22 21:43:12', '2026-07-22 21:43:42');
INSERT INTO users (id, username, password, nama_lengkap, role, siswa_id, is_active, last_login, created_at, updated_at) VALUES ('5', 'tamimi', '$2y$10$xaYBeJMsT/adOwWaLbOsTuYMkyKCzBIbdvC5KrkkX2yWTaATZiS5m', 'Ustadz Tamimi', 'guru', NULL, '1', '2026-07-22 22:03:01', '2026-07-22 21:58:13', '2026-07-22 22:03:01');

SET FOREIGN_KEY_CHECKS=1;
