# 🏆 FITUR POIN KESANTRIAN & DISIPLIN SISWA

## 📋 Deskripsi Fitur

Fitur **Poin Kesantrian & Disiplin** adalah sistem modern untuk mengelola poin pelanggaran dan penghargaan siswa dengan UI/UX yang indah dan responsif. Fitur ini terintegrasi sempurna dengan modul kesantrian yang ada.

---

## ✨ Fitur Utama

### 1. **Dashboard Poin**
- Tampilan statistik real-time poin minggu ini
- Top performers leaderboard
- Aksi cepat untuk menambah poin pelanggaran atau penghargaan
- Antarmuka glassmorphism modern dengan animasi halus

### 2. **Input Poin**
- Modal form yang user-friendly
- Pilih siswa dari dropdown
- Tab untuk kategori penghargaan dan pelanggaran
- Tambah deskripsi, tanggal, dan jam
- Notifikasi otomatis ke orang tua

### 3. **Leaderboard**
- Ranking siswa berdasarkan total poin
- Filter berdasarkan tipe poin (total, penghargaan, pelanggaran)
- Filter bulan dan tahun
- Desain modern dengan medal emoji untuk top 3

### 4. **Riwayat Poin**
- Tabel lengkap semua pemberian poin
- Filter siswa, tipe poin, dan pencarian
- Tampilan responsif yang mobile-friendly
- Opsi delete untuk menghapus poin yang salah

### 5. **Kategori Poin Default**

#### Poin Pelanggaran:
- Datang Terlambat (1 poin)
- Tidak Mengerjakan PR (2 poin)
- Tidak Berseragam (3 poin)
- Ramai di Kelas (2 poin)
- Tidak Fokus Belajar (1 poin)
- Pelanggaran Tertib (3 poin)

#### Poin Penghargaan:
- Hafalan Surah (5 poin)
- Nilai Sempurna (4 poin)
- Piket Rapi (2 poin)
- Membantu Teman (3 poin)
- Prestasi Akademik (5 poin)
- Kehadiran 100% (4 poin)
- Budi Pekerti Baik (3 poin)

---

## 📂 File Struktur

```
pages/halaqah/
├── poin_dashboard.php      # Dashboard utama poin
├── poin_leaderboard.php    # Halaman leaderboard ranking
├── poin_history.php        # Halaman riwayat poin
└── poin_api.php            # API handler untuk AJAX
```

---

## 🗄️ Database Schema

### Tabel: `siswa_poin_kategori`
Menyimpan kategori poin (pelanggaran & penghargaan)
```sql
CREATE TABLE siswa_poin_kategori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(100) UNIQUE NOT NULL,
    tipe_poin ENUM('pelanggaran', 'penghargaan'),
    deskripsi TEXT,
    nilai_poin INT DEFAULT 1,
    icon VARCHAR(50),              -- Bootstrap icon class
    color VARCHAR(20),             -- Hex color code
    status ENUM('aktif', 'nonaktif'),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Tabel: `siswa_poin_riwayat`
Menyimpan riwayat pemberian poin kepada siswa
```sql
CREATE TABLE siswa_poin_riwayat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT NOT NULL,
    kategori_poin_id INT NOT NULL,
    nilai_poin INT NOT NULL,
    tipe_poin ENUM('pelanggaran', 'penghargaan'),
    deskripsi TEXT,
    bukti_foto VARCHAR(255),
    input_by INT NOT NULL,         -- User ID yang input
    tanggal DATE NOT NULL,
    jam TIME,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id),
    FOREIGN KEY (kategori_poin_id) REFERENCES siswa_poin_kategori(id),
    FOREIGN KEY (input_by) REFERENCES users(id)
);
```

---

## 🎨 UI/UX Design

### Styling Features:
- **Glassmorphism**: Background blur dan transparency untuk efek modern
- **Gradient Colors**: Warna gradien yang elegan untuk berbagai elemen
- **Responsive Design**: Sempurna di desktop, tablet, dan mobile
- **Animasi Halus**: Transisi smooth dan hover effects yang menarik
- **Icon Bootstrap**: Menggunakan Bootstrap Icons untuk visual yang konsisten
- **Dark Mode Support**: Siap untuk dark mode di masa depan

### Color Palette:
- **Primary**: #8b5cf6 (Purple - Disiplin)
- **Success**: #10b981 (Green - Penghargaan)
- **Danger**: #ef4444 (Red - Pelanggaran)
- **Warning**: #f59e0b (Amber - Trophy)
- **Info**: #06b6d4 (Cyan - Info)

---

## 🚀 Cara Instalasi

### 1. Jalankan Migration
```bash
cd /path/to/edu-app
php migrate_poin_feature.php
```

Output yang diharapkan:
```
🔄 Memulai migrasi database untuk fitur Poin...
✅ Tabel siswa_poin_kategori berhasil dibuat/sudah ada
✅ Tabel siswa_poin_riwayat berhasil dibuat/sudah ada
✅ Kategori poin pelanggaran default berhasil diisi
✅ Kategori poin penghargaan default berhasil diisi

🎉 Migrasi Fitur Poin berhasil dilakukan!
```

### 2. Akses Dashboard Poin
- Buka halaman dashboard utama (portal)
- Klik modul "Kesantrian"
- Pilih "Poin Kesantrian & Disiplin"
- Atau akses langsung: `/pages/halaqah/poin_dashboard.php`

---

## 📱 Cara Penggunaan

### Menambah Poin Siswa

1. **Buka Dashboard Poin**
   - Dari modul Kesantrian → Poin Kesantrian & Disiplin
   - Atau klik tombol "Tambah Poin" di halaman

2. **Isi Form**
   - Pilih Siswa dari dropdown
   - Pilih Tab (Penghargaan atau Pelanggaran)
   - Klik Kategori Poin yang diinginkan
   - Tambah Deskripsi (opsional)
   - Ubah Tanggal & Jam jika diperlukan

3. **Simpan**
   - Klik tombol "Simpan Poin"
   - Otomatis notifikasi terkirim ke orang tua

### Melihat Leaderboard

1. **Buka Leaderboard**
   - Klik "Lihat Leaderboard" dari dashboard
   - Atau akses langsung: `/pages/halaqah/poin_leaderboard.php`

2. **Filter & Sort**
   - Pilih tipe poin (Total, Penghargaan, Pelanggaran)
   - Pilih Bulan & Tahun
   - Lihat ranking otomatis update

### Melihat Riwayat Poin

1. **Buka Riwayat**
   - Klik "Riwayat Poin" dari dashboard
   - Atau akses langsung: `/pages/halaqah/poin_history.php`

2. **Filter & Cari**
   - Filter siswa, tipe poin
   - Cari berdasarkan kategori atau deskripsi
   - Edit atau hapus poin jika diperlukan

---

## 🔌 API Endpoints

### GET - `/pages/halaqah/poin_api.php`

#### 1. Get Total Poin Siswa
```
?action=get_total_poin&siswa_id=1
Response: { poin_penghargaan, poin_pelanggaran, total_poin }
```

#### 2. Get Riwayat Poin Siswa
```
?action=get_riwayat_poin&siswa_id=1&limit=20
Response: Array of poin records
```

#### 3. Get Kategori Poin
```
?action=get_kategori&tipe=penghargaan
Response: Array of kategori
```

#### 4. Get Leaderboard
```
?action=get_leaderboard&tipe=total&limit=50&bulan=12&tahun=2026
Response: Array of siswa dengan ranking
```

### POST - `/pages/halaqah/poin_api.php`

#### 1. Tambah Poin Siswa
```
action=tambah_poin
siswa_id=1
kategori_id=5
deskripsi=Deskripsi poin
tanggal=2026-08-04
jam=10:30:00
```

#### 2. Hapus Poin
```
action=hapus_poin
id=123
```

---

## 🛡️ Permission & Security

- Fitur ini dilindungi permission `halaqah`
- Hanya user dengan role admin, guru, atau operator yang bisa akses
- Session-based authentication
- CSRF protection melalui form tokens
- Input validation untuk semua endpoint

---

## 📊 Statistik & Analytics

Dashboard menampilkan:
- Total poin penghargaan minggu ini
- Total poin pelanggaran minggu ini
- Total siswa aktif
- Top 10 performers dengan medal ranking
- Akses cepat ke leaderboard dan history

---

## 🎯 Fitur Pengembangan Masa Depan

- [ ] Export leaderboard ke PDF/Excel
- [ ] Chart analytics poin per siswa
- [ ] Bulk import poin dari file
- [ ] Reminder otomatis poin ke guru
- [ ] Integration dengan sistem reward fisik
- [ ] Parent mobile app notification
- [ ] SMS gateway untuk notifikasi poin
- [ ] Poin reward redemption system

---

## 🐛 Troubleshooting

### Database Error saat Migrasi?
```bash
# Cek koneksi database di config/koneksi.php
# Pastikan MySQL sudah running
# Run migration lagi
php migrate_poin_feature.php
```

### Form tidak bisa submit?
```
- Pastikan JavaScript enabled
- Clear browser cache
- Check browser console untuk error
- Pastikan BASE_URL di config benar
```

### Notifikasi tidak terkirim ke orang tua?
```
- Cek tabel notifikasi_ortu di database
- Pastikan siswa_id valid
- Check users table untuk orang tua link
```

---

## 📞 Support & Feedback

Untuk pertanyaan atau saran fitur:
1. Check documentation ini terlebih dahulu
2. Lihat file poin_api.php untuk detail teknis
3. Contact developer untuk custom features

---

**Dibuat dengan ❤️ menggunakan PHP, Bootstrap, dan JavaScript**
**Last Updated: August 2026**
