<?php
/**
 * MODUL HALAQAH & TAHFIDZ - PENCATATAN SETORAN SISWA (MOBILE FRIENDLY & AUTOMATED SURAH SELECTOR)
 */
$pageTitle  = 'Pencatatan Setoran Halaqah';
$activePage = 'halaqah';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('halaqah');

$pdo    = getConnection();
$userId = $_SESSION['user_id'];
$flash  = getFlash();

// Daftar 114 Surah Al-Qur'an Lengkap
$surahAlquran = [
    1 => ["name" => "Al-Fatihah", "verses" => 7], 2 => ["name" => "Al-Baqarah", "verses" => 286], 3 => ["name" => "Ali 'Imran", "verses" => 200],
    4 => ["name" => "An-Nisa'", "verses" => 176], 5 => ["name" => "Al-Ma'idah", "verses" => 120], 6 => ["name" => "Al-An'am", "verses" => 165],
    7 => ["name" => "Al-A'raf", "verses" => 206], 8 => ["name" => "Al-Anfal", "verses" => 75], 9 => ["name" => "At-Tawbah", "verses" => 129],
    10 => ["name" => "Yunus", "verses" => 109], 11 => ["name" => "Hud", "verses" => 123], 12 => ["name" => "Yusuf", "verses" => 111],
    13 => ["name" => "Ar-Ra'd", "verses" => 43], 14 => ["name" => "Ibrahim", "verses" => 52], 15 => ["name" => "Al-Hijr", "verses" => 99],
    16 => ["name" => "An-Nahl", "verses" => 128], 17 => ["name" => "Al-Isra'", "verses" => 111], 18 => ["name" => "Al-Kahf", "verses" => 110],
    19 => ["name" => "Maryam", "verses" => 98], 20 => ["name" => "Taha", "verses" => 135], 21 => ["name" => "Al-Anbiya'", "verses" => 112],
    22 => ["name" => "Al-Hajj", "verses" => 78], 23 => ["name" => "Al-Mu'minun", "verses" => 118], 24 => ["name" => "An-Nur", "verses" => 64],
    25 => ["name" => "Al-Furqan", "verses" => 77], 26 => ["name" => "Ash-Shu'ara'", "verses" => 227], 27 => ["name" => "An-Naml", "verses" => 93],
    28 => ["name" => "Al-Qasas", "verses" => 88], 29 => ["name" => "Al-'Ankabut", "verses" => 69], 30 => ["name" => "Ar-Rum", "verses" => 60],
    31 => ["name" => "Luqman", "verses" => 34], 32 => ["name" => "As-Sajdah", "verses" => 30], 33 => ["name" => "Al-Ahzab", "verses" => 73],
    34 => ["name" => "Saba'", "verses" => 54], 35 => ["name" => "Fatir", "verses" => 45], 36 => ["name" => "Ya-Sin", "verses" => 83],
    37 => ["name" => "As-Saffat", "verses" => 182], 38 => ["name" => "Sad", "verses" => 88], 39 => ["name" => "Az-Zumar", "verses" => 75],
    40 => ["name" => "Ghafir", "verses" => 85], 41 => ["name" => "Fussilat", "verses" => 54], 42 => ["name" => "Ash-Shura", "verses" => 53],
    43 => ["name" => "Az-Zukhruf", "verses" => 89], 44 => ["name" => "Ad-Dukhan", "verses" => 59], 45 => ["name" => "Al-Jathiyah", "verses" => 37],
    46 => ["name" => "Al-Ahqaf", "verses" => 35], 47 => ["name" => "Muhammad", "verses" => 38], 48 => ["name" => "Al-Fath", "verses" => 29],
    49 => ["name" => "Al-Hujurat", "verses" => 18], 50 => ["name" => "Qaf", "verses" => 45], 51 => ["name" => "Adh-Dhariyat", "verses" => 60],
    52 => ["name" => "At-Tur", "verses" => 49], 53 => ["name" => "An-Najm", "verses" => 62], 54 => ["name" => "Al-Qamar", "verses" => 55],
    55 => ["name" => "Ar-Rahman", "verses" => 78], 56 => ["name" => "Al-Waqi'ah", "verses" => 96], 57 => ["name" => "Al-Hadid", "verses" => 29],
    58 => ["name" => "Al-Mujadila", "verses" => 22], 59 => ["name" => "Al-Hashr", "verses" => 24], 60 => ["name" => "Al-Mumtahanah", "verses" => 13],
    61 => ["name" => "As-Saff", "verses" => 14], 62 => ["name" => "Al-Jumu'ah", "verses" => 11], 63 => ["name" => "Al-Munafiqun", "verses" => 11],
    64 => ["name" => "At-Taghabun", "verses" => 18], 65 => ["name" => "At-Talaq", "verses" => 12], 66 => ["name" => "At-Tahrim", "verses" => 12],
    67 => ["name" => "Al-Mulk", "verses" => 30], 68 => ["name" => "Al-Qalam", "verses" => 52], 69 => ["name" => "Al-Haqqah", "verses" => 52],
    70 => ["name" => "Al-Ma'arij", "verses" => 44], 71 => ["name" => "Nuh", "verses" => 28], 72 => ["name" => "Al-Jinn", "verses" => 28],
    73 => ["name" => "Al-Muzzammil", "verses" => 20], 74 => ["name" => "Al-Muddaththir", "verses" => 56], 75 => ["name" => "Al-Qiyamah", "verses" => 40],
    76 => ["name" => "Al-Insan", "verses" => 31], 77 => ["name" => "Al-Mursalat", "verses" => 50], 78 => ["name" => "An-Naba'", "verses" => 40],
    79 => ["name" => "An-Nazi'at", "verses" => 46], 80 => ["name" => "'Abasa", "verses" => 42], 81 => ["name" => "At-Takwir", "verses" => 29],
    82 => ["name" => "Al-Infitar", "verses" => 19], 83 => ["name" => "Al-Mutaffifin", "verses" => 36], 84 => ["name" => "Al-Inshiqaq", "verses" => 25],
    85 => ["name" => "Al-Buruj", "verses" => 22], 86 => ["name" => "At-Tariq", "verses" => 17], 87 => ["name" => "Al-A'la", "verses" => 19],
    88 => ["name" => "Al-Ghashiyah", "verses" => 26], 89 => ["name" => "Al-Fajr", "verses" => 30], 90 => ["name" => "Al-Balad", "verses" => 20],
    91 => ["name" => "Ash-Shams", "verses" => 15], 92 => ["name" => "Al-Layl", "verses" => 21], 93 => ["name" => "Ad-Duha", "verses" => 11],
    94 => ["name" => "Ash-Sharh", "verses" => 8], 95 => ["name" => "At-Tin", "verses" => 8], 96 => ["name" => "Al-'Alaq", "verses" => 19],
    97 => ["name" => "Al-Qadr", "verses" => 5], 98 => ["name" => "Al-Bayyinah", "verses" => 8], 99 => ["name" => "Az-Zalzalah", "verses" => 8],
    100 => ["name" => "Al-'Adiyat", "verses" => 11], 101 => ["name" => "Al-Qari'ah", "verses" => 11], 102 => ["name" => "At-Takathur", "verses" => 8],
    103 => ["name" => "Al-'Asr", "verses" => 3], 104 => ["name" => "Al-Humazah", "verses" => 9], 105 => ["name" => "Al-Fil", "verses" => 5],
    106 => ["name" => "Quraysh", "verses" => 4], 107 => ["name" => "Al-Ma'un", "verses" => 7], 108 => ["name" => "Al-Kawthar", "verses" => 3],
    109 => ["name" => "Al-Kafirun", "verses" => 6], 110 => ["name" => "An-Nasr", "verses" => 3], 111 => ["name" => "Al-Masad", "verses" => 5],
    112 => ["name" => "Al-Ikhlas", "verses" => 4], 113 => ["name" => "Al-Falaq", "verses" => 5], 114 => ["name" => "An-Nas", "verses" => 6]
];

// Fetch Kategori & Kelompok
$kategoriList = $pdo->query("SELECT * FROM halaqah_kategori ORDER BY nama_kategori ASC")->fetchAll();
$kelompokList = $pdo->query("
    SELECT hk.*, hkat.nama_kategori, u.nama_lengkap AS nama_musyrif
    FROM halaqah_kelompok hk
    LEFT JOIN halaqah_kategori hkat ON hk.kategori_id = hkat.id
    LEFT JOIN users u ON hk.musyrif_user_id = u.id
    ORDER BY hk.nama_halaqah ASC
")->fetchAll();

$selectedKelompokId = (int)($_GET['kelompok_id'] ?? 0);

if ($selectedKelompokId > 0) {
    $stmtS = $pdo->prepare("
        SELECT s.id, s.nis, s.nama, k.nama_kelas
        FROM halaqah_anggota ha
        JOIN siswa s ON ha.siswa_id = s.id
        JOIN kelas k ON s.kelas_id = k.id
        WHERE ha.kelompok_id = :kid AND s.status = 'aktif'
        ORDER BY s.nama ASC
    ");
    $stmtS->execute([':kid' => $selectedKelompokId]);
    $siswaList = $stmtS->fetchAll();
} else {
    $siswaList = $pdo->query("
        SELECT s.id, s.nis, s.nama, k.nama_kelas
        FROM siswa s
        JOIN kelas k ON s.kelas_id = k.id
        WHERE s.status = 'aktif'
        ORDER BY s.nama ASC
        LIMIT 150
    ")->fetchAll();
}

// PROSES SIMPAN SETORAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'simpan_setoran') {
    $siswaId       = (int)($_POST['siswa_id'] ?? 0);
    $kelompokId    = !empty($_POST['kelompok_id']) ? (int)$_POST['kelompok_id'] : null;
    $kategoriId    = !empty($_POST['kategori_id']) ? (int)$_POST['kategori_id'] : null;
    $tanggal       = $_POST['tanggal'] ?? date('Y-m-d');
    $tipeSetoran   = $_POST['tipe_setoran'] ?? 'ziyadah';
    $metodeInput   = $_POST['metode_input'] ?? 'ayat';
    $materiSetoran = trim($_POST['materi_setoran'] ?? '');
    $penilaian     = $_POST['penilaian'] ?? 'mumtaz';
    $statusSetoran = $_POST['status_setoran'] ?? 'lulus';
    $catatanOrtu   = trim($_POST['catatan_ortu'] ?? '');

    try {
        if ($siswaId <= 0) throw new Exception("Silakan pilih siswa terlebih dahulu!");
        if (empty($materiSetoran)) throw new Exception("Detail materi setoran wajib terisi!");

        $stmtIns = $pdo->prepare("
            INSERT INTO halaqah_setoran 
            (siswa_id, kelompok_id, kategori_id, musyrif_id, tanggal, tipe_setoran, metode_input, materi_setoran, penilaian, status_setoran, catatan_ortu)
            VALUES 
            (:sid, :kid, :katid, :mid, :tgl, :tipe, :metode, :materi, :nilai, :status, :catatan)
        ");
        $stmtIns->execute([
            ':sid'     => $siswaId,
            ':kid'     => $kelompokId,
            ':katid'   => $kategoriId,
            ':mid'     => $userId,
            ':tgl'     => $tanggal,
            ':tipe'    => $tipeSetoran,
            ':metode'  => $metodeInput,
            ':materi'  => $materiSetoran,
            ':nilai'   => $penilaian,
            ':status'  => $statusSetoran,
            ':catatan' => $catatanOrtu
        ]);

        // Get Siswa Info for Notification
        $stmtS = $pdo->prepare("SELECT nama FROM siswa WHERE id = :id");
        $stmtS->execute([':id' => $siswaId]);
        $namaSiswa = $stmtS->fetchColumn();

        $tipeLabelMap  = ['ziyadah' => 'Nambah Hafalan (Ziyadah)', 'murojaah' => 'Muroja\'ah', 'tahsin' => 'Tahsin', 'ujian' => 'Ujian Tahfidz'];
        $nilaiLabelMap = ['mumtaz' => 'Mumtaz (Sangat Baik)', 'jayyid_jiddan' => 'Jayyid Jiddan (Baik Sekali)', 'jayyid' => 'Jayyid (Baik)', 'rasib' => 'Rasib (Mengulang)'];

        try {
            $pesanNotif = "Anak Anda {$namaSiswa} telah menyelesaikan setoran {$tipeLabelMap[$tipeSetoran]} materi: {$materiSetoran}. Penilaian: {$nilaiLabelMap[$penilaian]} ({$statusSetoran}).";
            if (!empty($catatanOrtu)) $pesanNotif .= " Catatan Musyrif: \"{$catatanOrtu}\"";
            
            $stmtN = $pdo->prepare("INSERT INTO notifikasi_ortu (siswa_id, judul, pesan, tipe, icon) VALUES (:s, 'Setoran Tahfidz & Kesantrian', :p, 'akademik', 'bi-book-half')");
            $stmtN->execute([':s' => $siswaId, ':p' => $pesanNotif]);
        } catch (Exception $eN) {}

        redirect('index.php?kelompok_id=' . $selectedKelompokId, 'success', '✨ Setoran halaqah berhasil dicatat dan notifikasi terkirim ke Orang Tua!');
    } catch (Exception $e) {
        redirect('index.php?kelompok_id=' . $selectedKelompokId, 'danger', $e->getMessage());
    }
}

// HAPUS SETORAN
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $pdo->prepare("DELETE FROM halaqah_setoran WHERE id = :id")->execute([':id' => (int)$_GET['id']]);
        redirect('index.php', 'success', 'Data setoran berhasil dihapus.');
    } catch (Exception $e) {
        redirect('index.php', 'danger', 'Gagal menghapus: ' . $e->getMessage());
    }
}

// Fetch Recent Setoran
$riwayatSetoran = $pdo->query("
    SELECT hs.*, s.nama AS nama_siswa, s.nis, k.nama_kelas, hk.nama_halaqah, hkat.nama_kategori, u.nama_lengkap AS nama_musyrif
    FROM halaqah_setoran hs
    JOIN siswa s ON hs.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN halaqah_kelompok hk ON hs.kelompok_id = hk.id
    LEFT JOIN halaqah_kategori hkat ON hs.kategori_id = hkat.id
    LEFT JOIN users u ON hs.musyrif_id = u.id
    ORDER BY hs.tanggal DESC, hs.id DESC
    LIMIT 25
")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    /* Ultra Mobile Friendly Touch Target Styles */
    @media (max-width: 768px) {
        .form-control-lg-mobile, .form-select-lg-mobile {
            padding: 0.75rem 1rem !important;
            font-size: 0.95rem !important;
            border-radius: 12px !important;
        }
        .btn-touch-target {
            padding: 0.85rem 1.25rem !important;
            font-size: 1rem !important;
            border-radius: 14px !important;
        }
        .method-radio-card {
            padding: 0.65rem 0.5rem !important;
            font-size: 0.78rem !important;
        }
    }
</style>

<!-- ACTION HEADER BREADCRUMB -->
<div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle p-3 d-flex align-items-center justify-content-center text-white" style="width: 48px; height: 48px; background: linear-gradient(135deg, #8b5cf6, #6d28d9);">
                <i class="bi bi-moon-stars-fill fs-4"></i>
            </div>
            <div>
                <h5 class="fw-extrabold text-dark mb-0">Kesantrian: Setoran Tahfidz</h5>
                <p class="text-muted extra-small mb-0">Pencatatan setoran hafalan, muroja'ah, tahsin & ujian siswa</p>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 w-100-mobile">
            <a href="index.php" class="btn text-white px-3 py-2 rounded-3 fw-bold small flex-fill-mobile" style="background: #8b5cf6;">
                <i class="bi bi-journal-plus me-1"></i> Input Setoran
            </a>
            <a href="manage.php" class="btn btn-outline-purple px-3 py-2 rounded-3 fw-bold small flex-fill-mobile" style="color: #8b5cf6; border-color: #8b5cf6;">
                <i class="bi bi-gear-fill me-1"></i> Pengaturan
            </a>
            <a href="laporan.php" class="btn btn-outline-secondary px-3 py-2 rounded-3 fw-bold small flex-fill-mobile">
                <i class="bi bi-file-earmark-bar-graph me-1"></i> Laporan
            </a>
        </div>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?= $flash['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- LEFT COLUMN: FORM INPUT SETORAN MOBILE FRIENDLY -->
    <div class="col-lg-5 col-12">
        <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-extrabold text-dark mb-0"><i class="bi bi-pencil-fill me-2" style="color: #8b5cf6;"></i>Form Input Setoran Musyrif</h6>
                <span class="badge bg-purple-subtle text-purple extra-small fw-bold px-2 py-1" style="background: #f3e8ff; color: #7c3aed;">Mobile Mode</span>
            </div>

            <!-- FILTER KELOMPOK HALAQAH -->
            <div class="mb-3 p-3 rounded-3 bg-light border">
                <label class="form-label extra-small fw-bold text-muted mb-1"><i class="bi bi-funnel me-1"></i> Filter Kelompok Musyrif</label>
                <select class="form-select bg-white border-0 fw-bold extra-small form-select-lg-mobile" onchange="window.location.href='index.php?kelompok_id='+this.value">
                    <option value="0">-- Semua Siswa (Tanpa Filter) --</option>
                    <?php foreach ($kelompokList as $hk): ?>
                        <option value="<?= $hk['id'] ?>" <?= $selectedKelompokId == $hk['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($hk['nama_halaqah']) ?> (<?= htmlspecialchars($hk['nama_kategori'] ?: 'Umum') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <form method="POST" action="index.php?kelompok_id=<?= $selectedKelompokId ?>" id="formSetoranHalaqah">
                <input type="hidden" name="action" value="simpan_setoran">
                <?php if ($selectedKelompokId > 0): ?>
                    <input type="hidden" name="kelompok_id" value="<?= $selectedKelompokId ?>">
                <?php endif; ?>

                <!-- NAMA SISWA -->
                <div class="mb-3">
                    <label class="form-label extra-small fw-bold text-muted">Nama Siswa <span class="text-danger">*</span></label>
                    <select name="siswa_id" class="form-select bg-light border-0 fw-bold form-select-lg-mobile" required>
                        <option value="">-- Pilih Nama Siswa --</option>
                        <?php foreach ($siswaList as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nama']) ?> (Kelas <?= htmlspecialchars($s['nama_kelas']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- TIPE & KATEGORI -->
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label extra-small fw-bold text-muted">Tipe Setoran <span class="text-danger">*</span></label>
                        <select name="tipe_setoran" class="form-select bg-light border-0 fw-bold form-select-lg-mobile" required>
                            <option value="ziyadah">Ziyadah (Nambah)</option>
                            <option value="murojaah">Muroja'ah</option>
                            <option value="tahsin">Tahsin Bacaan</option>
                            <option value="ujian">Ujian Tahfidz</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label extra-small fw-bold text-muted">Tanggal Input</label>
                        <input type="date" name="tanggal" class="form-control bg-light border-0 fw-bold form-control-lg-mobile" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <!-- METODE INPUT SETORAN -->
                <div class="mb-3">
                    <label class="form-label extra-small fw-bold text-muted mb-2">Metode Input Setoran <span class="text-danger">*</span></label>
                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <input type="radio" class="btn-check" name="metode_input" id="metodeAyat" value="ayat" checked onchange="switchMetodeInput()">
                            <label class="btn btn-outline-primary w-100 py-2 rounded-3 extra-small fw-bold method-radio-card" for="metodeAyat">
                                <i class="bi bi-book d-block fs-6 mb-1"></i> Berdasar Ayat
                            </label>
                        </div>
                        <div class="col-4">
                            <input type="radio" class="btn-check" name="metode_input" id="metodeHalaman" value="halaman" onchange="switchMetodeInput()">
                            <label class="btn btn-outline-primary w-100 py-2 rounded-3 extra-small fw-bold method-radio-card" for="metodeHalaman">
                                <i class="bi bi-file-earmark-text d-block fs-6 mb-1"></i> Halaman
                            </label>
                        </div>
                        <div class="col-4">
                            <input type="radio" class="btn-check" name="metode_input" id="metodeBaris" value="baris" onchange="switchMetodeInput()">
                            <label class="btn btn-outline-primary w-100 py-2 rounded-3 extra-small fw-bold method-radio-card" for="metodeBaris">
                                <i class="bi bi-list-ol d-block fs-6 mb-1"></i> Baris
                            </label>
                        </div>
                    </div>
                </div>

                <!-- DYNAMIC INPUT BLOCK: AYAT -->
                <div id="blockAyat" class="p-3 rounded-3 bg-light border mb-3">
                    <label class="form-label extra-small fw-bold text-muted mb-1">Pilih Surah Al-Qur'an (114 Surah)</label>
                    <select id="selectSurahAyat" class="form-select bg-white border-0 fw-bold mb-2 form-select-lg-mobile" onchange="autoFormatMateri()">
                        <option value="">-- Pilih Surah --</option>
                        <?php foreach ($surahAlquran as $num => $sur): ?>
                            <option value="<?= $num ?>" data-verses="<?= $sur['verses'] ?>"><?= $num ?>. Surah <?= htmlspecialchars($sur['name']) ?> (<?= $sur['verses'] ?> ayat)</option>
                        <?php endforeach; ?>
                    </select>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label extra-small text-muted mb-1">Dari Ayat</label>
                            <input type="number" id="ayatMulai" class="form-control bg-white border-0 fw-bold" value="1" min="1" oninput="autoFormatMateri()">
                        </div>
                        <div class="col-6">
                            <label class="form-label extra-small text-muted mb-1">Sampai Ayat</label>
                            <input type="number" id="ayatSelesai" class="form-control bg-white border-0 fw-bold" value="10" min="1" oninput="autoFormatMateri()">
                        </div>
                    </div>
                </div>

                <!-- DYNAMIC INPUT BLOCK: HALAMAN -->
                <div id="blockHalaman" class="p-3 rounded-3 bg-light border mb-3 d-none">
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label extra-small text-muted mb-1">Dari Halaman</label>
                            <input type="number" id="halMulai" class="form-control bg-white border-0 fw-bold" value="1" min="1" max="604" oninput="autoFormatMateri()">
                        </div>
                        <div class="col-6">
                            <label class="form-label extra-small text-muted mb-1">Sampai Halaman</label>
                            <input type="number" id="halSelesai" class="form-control bg-white border-0 fw-bold" value="2" min="1" max="604" oninput="autoFormatMateri()">
                        </div>
                    </div>
                    <label class="form-label extra-small text-muted mb-1">Surah (Opsional)</label>
                    <select id="selectSurahHalaman" class="form-select bg-white border-0 extra-small" onchange="autoFormatMateri()">
                        <option value="">-- Pilih Surah (Opsional) --</option>
                        <?php foreach ($surahAlquran as $num => $sur): ?>
                            <option value="<?= $num ?>"><?= $num ?>. Surah <?= htmlspecialchars($sur['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- DYNAMIC INPUT BLOCK: BARIS -->
                <div id="blockBaris" class="p-3 rounded-3 bg-light border mb-3 d-none">
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label extra-small text-muted mb-1">Dari Baris Ke-</label>
                            <input type="number" id="barisMulai" class="form-control bg-white border-0 fw-bold" value="1" min="1" oninput="autoFormatMateri()">
                        </div>
                        <div class="col-6">
                            <label class="form-label extra-small text-muted mb-1">Sampai Baris Ke-</label>
                            <input type="number" id="barisSelesai" class="form-control bg-white border-0 fw-bold" value="15" min="1" oninput="autoFormatMateri()">
                        </div>
                    </div>
                    <label class="form-label extra-small text-muted mb-1">Surah (Opsional)</label>
                    <select id="selectSurahBaris" class="form-select bg-white border-0 extra-small" onchange="autoFormatMateri()">
                        <option value="">-- Pilih Surah (Opsional) --</option>
                        <?php foreach ($surahAlquran as $num => $sur): ?>
                            <option value="<?= $num ?>"><?= $num ?>. Surah <?= htmlspecialchars($sur['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- DETAIL MATERI SETORAN TERFORMAT (AUTO GENERATED) -->
                <div class="mb-3">
                    <label class="form-label extra-small fw-bold text-muted">Text Detail Materi Setoran <span class="text-danger">*</span></label>
                    <input type="text" name="materi_setoran" id="inputMateriSetoran" class="form-control bg-light border-0 fw-extrabold text-primary form-control-lg-mobile" placeholder="Otomatis terisi dari pilihan di atas" required>
                </div>

                <!-- PENILAIAN & STATUS -->
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label extra-small fw-bold text-muted">Kategori Penilaian</label>
                        <select name="penilaian" class="form-select bg-light border-0 fw-bold form-select-lg-mobile">
                            <option value="mumtaz">⭐ Mumtaz (Sangat Baik)</option>
                            <option value="jayyid_jiddan">🌟 Jayyid Jiddan (Baik Sekali)</option>
                            <option value="jayyid">👍 Jayyid (Baik)</option>
                            <option value="rasib">🔄 Rasib (Mengulang)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label extra-small fw-bold text-muted">Status Setoran</label>
                        <select name="status_setoran" class="form-select bg-light border-0 fw-bold form-select-lg-mobile">
                            <option value="lulus">🟢 Lulus</option>
                            <option value="mengulang">🔴 Mengulang</option>
                        </select>
                    </div>
                </div>

                <!-- CATATAN ORTU -->
                <div class="mb-4">
                    <label class="form-label extra-small fw-bold text-muted">Catatan Pembimbing untuk Ortu</label>
                    <textarea name="catatan_ortu" class="form-control bg-light border-0 extra-small" rows="2" placeholder="Catatan tajwid, makhraj, atau kelancaran hafalan..."></textarea>
                </div>

                <button type="submit" class="btn w-100 py-3 fw-extrabold shadow text-white btn-touch-target" style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); border: none;">
                    <i class="bi bi-send-fill me-2"></i> Simpan & Kirim ke Orang Tua
                </button>
            </form>
        </div>
    </div>

    <!-- RIGHT COLUMN: RIWAYAT SETORAN -->
    <div class="col-lg-7 col-12">
        <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history me-2" style="color: #8b5cf6;"></i>Riwayat Setoran Terbaru</h6>
                <span class="badge bg-light text-muted border extra-small">25 Terakhir</span>
            </div>

            <?php if (empty($riwayatSetoran)): ?>
                <div class="text-center py-5 text-muted small">
                    <i class="bi bi-journal-x fs-1 d-block mb-1 opacity-50"></i>
                    Belum ada riwayat setoran. Silakan isi form setoran di samping!
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle extra-small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tgl</th>
                                <th>Siswa</th>
                                <th>Tipe & Materi</th>
                                <th>Nilai & Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($riwayatSetoran as $rs): ?>
                                <?php 
                                    $nBadge = [
                                        'mumtaz'        => 'bg-success-subtle text-success border-success',
                                        'jayyid_jiddan' => 'bg-info-subtle text-info border-info',
                                        'jayyid'        => 'bg-warning-subtle text-warning border-warning',
                                        'rasib'         => 'bg-danger-subtle text-danger border-danger'
                                    ][$rs['penilaian']] ?? 'bg-light';
                                ?>
                                <tr>
                                    <td class="text-muted text-nowrap"><?= date('d/m', strtotime($rs['tanggal'])) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($rs['nama_siswa']) ?></strong><br>
                                        <small class="text-muted extra-small"><?= htmlspecialchars($rs['nama_kelas']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border mb-1"><?= ucfirst($rs['tipe_setoran']) ?></span><br>
                                        <strong><?= htmlspecialchars($rs['materi_setoran']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge <?= $nBadge ?> border extra-small mb-1"><?= strtoupper(str_replace('_', ' ', $rs['penilaian'])) ?></span><br>
                                        <span class="badge <?= $rs['status_setoran'] === 'lulus' ? 'bg-success' : 'bg-danger' ?> extra-small"><?= ucfirst($rs['status_setoran']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <a href="index.php?action=delete&id=<?= $rs['id'] ?>" class="btn btn-sm btn-outline-danger py-1 px-2 rounded-2" onclick="return confirm('Hapus setoran ini?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function switchMetodeInput() {
    let m = $('input[name="metode_input"]:checked').val();
    $('#blockAyat, #blockHalaman, #blockBaris').addClass('d-none');
    if (m === 'ayat') $('#blockAyat').removeClass('d-none');
    if (m === 'halaman') $('#blockHalaman').removeClass('d-none');
    if (m === 'baris') $('#blockBaris').removeClass('d-none');
    autoFormatMateri();
}

function autoFormatMateri() {
    let m = $('input[name="metode_input"]:checked').val();
    let str = '';
    
    if (m === 'ayat') {
        let surahText = $('#selectSurahAyat option:selected').text();
        let surahVal = $('#selectSurahAyat').val();
        let a1 = $('#ayatMulai').val() || '1';
        let a2 = $('#ayatSelesai').val() || '1';
        if (surahVal) {
            let sClean = surahText.replace(/^\d+\.\s*/, '').replace(/\s*\(\d+\s*ayat\)/, '');
            str = 'Surah ' + sClean + ' (Ayat ' + a1 + ' - ' + a2 + ')';
        }
    } else if (m === 'halaman') {
        let h1 = $('#halMulai').val() || '1';
        let h2 = $('#halSelesai').val() || '1';
        let surahText = $('#selectSurahHalaman option:selected').text();
        let surahVal = $('#selectSurahHalaman').val();
        str = 'Halaman ' + h1 + ' - ' + h2;
        if (surahVal) {
            let sClean = surahText.replace(/^\d+\.\s*/, '');
            str += ' (' + sClean + ')';
        }
    } else if (m === 'baris') {
        let b1 = $('#barisMulai').val() || '1';
        let b2 = $('#barisSelesai').val() || '15';
        let surahText = $('#selectSurahBaris option:selected').text();
        let surahVal = $('#selectSurahBaris').val();
        str = 'Baris ' + b1 + ' - ' + b2;
        if (surahVal) {
            let sClean = surahText.replace(/^\d+\.\s*/, '');
            str += ' (' + sClean + ')';
        }
    }
    
    if (str) {
        $('#inputMateriSetoran').val(str);
    }
}

$(document).ready(function() {
    autoFormatMateri();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
