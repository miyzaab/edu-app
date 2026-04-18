-- ============================================================
-- DATABASE: e_pembayaran
-- Aplikasi E-Pembayaran SMP Islamic School of Minhaj Al-Ilmi
-- Dibuat: 2026-04-17
-- ============================================================

CREATE DATABASE IF NOT EXISTS e_pembayaran
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

--USE e_pembayaran;

-- ============================================================
-- TABEL: users
-- Menyimpan data user (Bendahara) untuk autentikasi
-- ============================================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,          -- Disimpan dalam bentuk hash (password_hash)
    nama_lengkap VARCHAR(100) NOT NULL,
    role ENUM('bendahara') DEFAULT 'bendahara',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABEL: kelas
-- Master data kelas (VII-A, VII-B, VIII-A, dst.)
-- ============================================================
CREATE TABLE kelas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_kelas VARCHAR(20) NOT NULL UNIQUE,  -- Contoh: VII-A, VIII-B, IX-C
    tingkat ENUM('VII','VIII','IX') NOT NULL, -- Tingkat kelas SMP
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABEL: siswa
-- Master data siswa, terhubung ke tabel kelas
-- ============================================================
CREATE TABLE siswa (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nis VARCHAR(20) NOT NULL UNIQUE,          -- Nomor Induk Siswa
    nama VARCHAR(100) NOT NULL,
    kelas_id INT NOT NULL,
    jenis_kelamin ENUM('L','P') NOT NULL,
    tahun_masuk YEAR NOT NULL,
    status ENUM('aktif','lulus','keluar') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- TABEL: pembayaran_spp
-- Transaksi pembayaran SPP bulanan
-- Constraint UNIQUE agar 1 siswa tidak bayar SPP 2x di bulan yg sama
-- ============================================================
CREATE TABLE pembayaran_spp (
    id INT PRIMARY KEY AUTO_INCREMENT,
    siswa_id INT NOT NULL,
    bulan TINYINT NOT NULL,                  -- 1=Januari, ..., 12=Desember
    tahun YEAR NOT NULL,
    nominal DECIMAL(15,2) NOT NULL,
    tanggal_bayar DATE NOT NULL,
    metode_bayar ENUM('tunai','transfer') DEFAULT 'tunai',
    keterangan TEXT NULL,
    user_id INT NOT NULL,                    -- Bendahara yang menginput
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE KEY unique_spp (siswa_id, bulan, tahun)
) ENGINE=InnoDB;

-- ============================================================
-- TABEL: pembayaran_uang_pangkal
-- Transaksi pembayaran uang pangkal (biaya masuk) siswa baru
-- ============================================================
CREATE TABLE pembayaran_uang_pangkal (
    id INT PRIMARY KEY AUTO_INCREMENT,
    siswa_id INT NOT NULL,
    nominal DECIMAL(15,2) NOT NULL,
    tanggal_bayar DATE NOT NULL,
    metode_bayar ENUM('tunai','transfer') DEFAULT 'tunai',
    keterangan TEXT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- TABEL: jenis_pembayaran
-- Kategori pembayaran dinamis (CRUD) — misal: uang buku, seragam
-- ============================================================
CREATE TABLE jenis_pembayaran (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_pembayaran VARCHAR(100) NOT NULL UNIQUE,
    nominal_default DECIMAL(15,2) DEFAULT 0, -- Nominal default yg bisa di-override saat input
    keterangan TEXT NULL,
    status ENUM('aktif','nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABEL: pembayaran_lain
-- Transaksi pembayaran lain-lain (terhubung ke jenis_pembayaran)
-- ============================================================
CREATE TABLE pembayaran_lain (
    id INT PRIMARY KEY AUTO_INCREMENT,
    siswa_id INT NOT NULL,
    jenis_pembayaran_id INT NOT NULL,
    nominal DECIMAL(15,2) NOT NULL,
    tanggal_bayar DATE NOT NULL,
    metode_bayar ENUM('tunai','transfer') DEFAULT 'tunai',
    keterangan TEXT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (jenis_pembayaran_id) REFERENCES jenis_pembayaran(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- TABEL: petty_cash
-- Transaksi kas kecil (Uang Masuk / Uang Keluar)
-- ============================================================
CREATE TABLE petty_cash (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tanggal DATE NOT NULL,
    jenis ENUM('masuk', 'keluar') NOT NULL,
    nominal DECIMAL(15,2) NOT NULL,
    keterangan TEXT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- DATA AWAL (SEEDER)
-- ============================================================

-- User Bendahara default (password: bendahara123)
INSERT INTO users (username, password, nama_lengkap, role) VALUES
('bendahara', '$2y$10$H2drvmE9iQiqqPuKH2Xnw.aN/WiDtKjFY4bAyBFNii8z7WJZAEm9W', 'Bendahara Sekolah', 'bendahara');

-- Data Kelas
INSERT INTO kelas (nama_kelas, tingkat) VALUES
('VII-A', 'VII'), ('VII-B', 'VII'), ('VII-C', 'VII'),
('VIII-A', 'VIII'), ('VIII-B', 'VIII'), ('VIII-C', 'VIII'),
('IX-A', 'IX'), ('IX-B', 'IX'), ('IX-C', 'IX');

-- Data Siswa Contoh
INSERT INTO siswa (nis, nama, kelas_id, jenis_kelamin, tahun_masuk) VALUES
('2024001', 'Ahmad Fauzan', 1, 'L', 2024),
('2024002', 'Siti Aisyah', 1, 'P', 2024),
('2024003', 'Muhammad Rizki', 2, 'L', 2024),
('2024004', 'Fatimah Azzahra', 2, 'P', 2024),
('2024005', 'Umar Abdullah', 3, 'L', 2024),
('2023001', 'Khadijah Nur', 4, 'P', 2023),
('2023002', 'Ali Imran', 5, 'L', 2023),
('2023003', 'Hafsa Ramadhani', 6, 'P', 2023),
('2022001', 'Bilal Hakim', 7, 'L', 2022),
('2022002', 'Maryam Safira', 8, 'P', 2022);

-- Jenis Pembayaran Contoh
INSERT INTO jenis_pembayaran (nama_pembayaran, nominal_default, keterangan) VALUES
('Uang Buku', 350000, 'Biaya buku paket per semester'),
('Seragam', 500000, 'Biaya seragam sekolah lengkap'),
('Kegiatan OSIS', 150000, 'Iuran kegiatan OSIS per semester'),
('Wisuda', 750000, 'Biaya wisuda kelas IX');

-- ============================================================
-- INDEX TAMBAHAN untuk performa query laporan
-- ============================================================
CREATE INDEX idx_spp_tanggal ON pembayaran_spp(tanggal_bayar);
CREATE INDEX idx_spp_bulan_tahun ON pembayaran_spp(bulan, tahun);
CREATE INDEX idx_up_tanggal ON pembayaran_uang_pangkal(tanggal_bayar);
CREATE INDEX idx_pl_tanggal ON pembayaran_lain(tanggal_bayar);
CREATE INDEX idx_siswa_status ON siswa(status);

-- ============================================================
-- TABEL: pembayaran_pending
-- Menyimpan data upload bukti transfer dari Portal Orang Tua
-- ============================================================
CREATE TABLE IF NOT EXISTS pembayaran_pending (
    id INT PRIMARY KEY AUTO_INCREMENT,
    siswa_id INT NOT NULL,
    jenis ENUM('spp', 'uang_pangkal', 'lainnya') NOT NULL,
    jenis_pembayaran_id INT NULL,
    bulan INT NULL,
    tahun INT NULL,
    nominal DECIMAL(15,2) NOT NULL,
    bukti_transfer VARCHAR(255) NOT NULL,
    catatan TEXT,
    status ENUM('pending', 'disetujui', 'ditolak') DEFAULT 'pending',
    alasan_tolak TEXT,
    verified_by INT NULL,
    verified_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    FOREIGN KEY (jenis_pembayaran_id) REFERENCES jenis_pembayaran(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABEL: settings
-- Menyimpan pengaturan aplikasi (logo, nama kepala sekolah, dll)
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
