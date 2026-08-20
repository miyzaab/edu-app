<?php
/**
 * AKADEMIK & GURU - Input Nilai Siswa (Kurikulum Merdeka)
 * Bobot: Sumatif (S1-S4) 80%, ATS 10%, AAS 10%
 */
$pageTitle = 'Input Nilai Siswa';
$activePage = 'input-nilai';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('input_nilai');

$pdo = getConnection();
$userId = (int) $_SESSION['user_id'];
$userRole = strtolower($_SESSION['role'] ?? '');

// Filter berdasarkan Role: Jika Guru, hanya tampilkan mapel & kelas yang diampu
$isGuruRole = ($userRole === 'guru');
$assignedMapels = [];
$assignedKelasIds = [];

if ($isGuruRole) {
    $stmtPlot = $pdo->prepare("
        SELECT DISTINCT m.nama_mapel, gmk.kelas_id
        FROM guru_mapel_kelas gmk
        JOIN mata_pelajaran m ON gmk.mapel_id = m.id
        WHERE gmk.user_id = :uid
    ");
    $stmtPlot->execute([':uid' => $userId]);
    $plotRows = $stmtPlot->fetchAll();

    foreach ($plotRows as $pr) {
        if (!in_array($pr['nama_mapel'], $assignedMapels)) {
            $assignedMapels[] = $pr['nama_mapel'];
        }
        if (!in_array((int) $pr['kelas_id'], $assignedKelasIds)) {
            $assignedKelasIds[] = (int) $pr['kelas_id'];
        }
    }
}

// Load Daftar Kelas
if ($isGuruRole) {
    if (!empty($assignedKelasIds)) {
        $inKelas = implode(',', $assignedKelasIds);
        $kelases = $pdo->query("SELECT * FROM kelas WHERE id IN ($inKelas) ORDER BY nama_kelas ASC")->fetchAll();
    } else {
        $kelases = [];
    }
} else {
    $kelases = $pdo->query("SELECT * FROM kelas ORDER BY nama_kelas ASC")->fetchAll();
}

// Load Mapel Options
if ($isGuruRole) {
    $mapelOptions = $assignedMapels;
} else {
    try {
        $dbMapel = $pdo->query("SELECT nama_mapel FROM mata_pelajaran WHERE status = 'aktif' ORDER BY kelompok ASC, kode_mapel ASC")->fetchAll(PDO::FETCH_COLUMN);
        $mapelOptions = !empty($dbMapel) ? $dbMapel : [
            'Matematika',
            'Bahasa Indonesia',
            'Bahasa Inggris',
            'Ilmu Pengetahuan Alam (IPA)',
            'Ilmu Pengetahuan Sosial (IPS)',
            'Pendidikan Agama Islam & Budi Pekerti',
            'Pendidikan Pancasila / PPKn',
            'Informatika',
            'PJOK',
            'Seni Budaya & Prakarya',
            'Bahasa Daerah'
        ];
    } catch (Exception $e) {
        $mapelOptions = [
            'Matematika',
            'Bahasa Indonesia',
            'Bahasa Inggris',
            'Ilmu Pengetahuan Alam (IPA)',
            'Ilmu Pengetahuan Sosial (IPS)',
            'Pendidikan Agama Islam & Budi Pekerti',
            'Pendidikan Pancasila / PPKn',
            'Informatika',
            'PJOK',
            'Seni Budaya & Prakarya',
            'Bahasa Daerah'
        ];
    }
}

// Default Filter
$selectedKelasId = isset($_GET['kelas_id']) ? (int) $_GET['kelas_id'] : ($kelases[0]['id'] ?? 0);
$selectedMapel = isset($_GET['mapel']) ? trim($_GET['mapel']) : ($mapelOptions[0] ?? 'Matematika');
$selectedSem = isset($_GET['semester']) ? trim($_GET['semester']) : 'Ganjil';
$selectedTahun = isset($_GET['tahun_ajaran']) ? trim($_GET['tahun_ajaran']) : (date('Y') . '/' . (date('Y') + 1));

// Process Bulk Save
$flashMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_nilai') {
    $siswaIds = $_POST['siswa_id'] ?? [];
    $s1Arr = $_POST['sumatif_1'] ?? [];
    $s2Arr = $_POST['sumatif_2'] ?? [];
    $s3Arr = $_POST['sumatif_3'] ?? [];
    $s4Arr = $_POST['sumatif_4'] ?? [];
    $atsArr = $_POST['ats'] ?? [];
    $aasArr = $_POST['aas'] ?? [];
    $catArr = $_POST['catatan'] ?? [];

    $postKelasId = (int) ($_POST['kelas_id'] ?? $selectedKelasId);
    $postMapel = trim($_POST['mapel'] ?? $selectedMapel);
    $postSem = trim($_POST['semester'] ?? $selectedSem);
    $postTahun = trim($_POST['tahun_ajaran'] ?? $selectedTahun);

    try {
        $stmtUpsert = $pdo->prepare("
            INSERT INTO nilai_siswa 
            (siswa_id, kelas_id, mapel, semester, tahun_ajaran, sumatif_1, sumatif_2, sumatif_3, sumatif_4, ats, aas, nilai_akhir, predikat, catatan, user_id)
            VALUES 
            (:sid, :kid, :mapel, :sem, :th, :s1, :s2, :s3, :s4, :ats, :aas, :na, :pred, :cat, :uid)
            ON DUPLICATE KEY UPDATE
            sumatif_1 = VALUES(sumatif_1),
            sumatif_2 = VALUES(sumatif_2),
            sumatif_3 = VALUES(sumatif_3),
            sumatif_4 = VALUES(sumatif_4),
            ats = VALUES(ats),
            aas = VALUES(aas),
            nilai_akhir = VALUES(nilai_akhir),
            predikat = VALUES(predikat),
            catatan = VALUES(catatan),
            user_id = VALUES(user_id)
        ");

        $countSaved = 0;
        foreach ($siswaIds as $idx => $sid) {
            $s1 = (float) ($s1Arr[$idx] ?? 0);
            $s2 = (float) ($s2Arr[$idx] ?? 0);
            $s3 = (float) ($s3Arr[$idx] ?? 0);
            $s4 = (float) ($s4Arr[$idx] ?? 0);
            $ats = (float) ($atsArr[$idx] ?? 0);
            $aas = (float) ($aasArr[$idx] ?? 0);
            $cat = trim($catArr[$idx] ?? '');

            // Formula Pembobotan: Sumatif 80%, ATS 10%, AAS 10%
            $rSumatif = ($s1 + $s2 + $s3 + $s4) / 4.0;
            $na = ($rSumatif * 0.80) + ($ats * 0.10) + ($aas * 0.10);
            $na = round($na, 2);

            // Predikat
            if ($na >= 90)
                $pred = 'A';
            elseif ($na >= 80)
                $pred = 'B';
            elseif ($na >= 70)
                $pred = 'C';
            else
                $pred = 'D';

            $stmtUpsert->execute([
                ':sid' => (int) $sid,
                ':kid' => $postKelasId,
                ':mapel' => $postMapel,
                ':sem' => $postSem,
                ':th' => $postTahun,
                ':s1' => $s1,
                ':s2' => $s2,
                ':s3' => $s3,
                ':s4' => $s4,
                ':ats' => $ats,
                ':aas' => $aas,
                ':na' => $na,
                ':pred' => $pred,
                ':cat' => $cat,
                ':uid' => $userId
            ]);

            // Kirim notifikasi ke portal ortu
            try {
                $stmtNotif = $pdo->prepare("INSERT INTO notifikasi_ortu (siswa_id, judul, pesan, tipe, icon) VALUES (:s, :j, :p, 'nilai', 'bi-journal-check')");
                $stmtNotif->execute([
                    ':s' => (int) $sid,
                    ':j' => 'Nilai Mapel ' . $postMapel,
                    ':p' => 'Guru telah memperbarui nilai ' . $postMapel . ' Semester ' . $postSem . ' (Nilai Akhir: ' . number_format($na, 1) . ', Predikat: ' . $pred . ').'
                ]);
            } catch (Exception $exNotif) {
            }

            $countSaved++;
        }

        redirect(BASE_URL . "/pages/nilai/index.php?kelas_id=$postKelasId&mapel=" . urlencode($postMapel) . "&semester=" . urlencode($postSem) . "&tahun_ajaran=" . urlencode($postTahun), 'success', "Berhasil menyimpan nilai $countSaved siswa!");
    } catch (PDOException $e) {
        $errorMsg = 'Gagal menyimpan nilai: ' . $e->getMessage();
    }
}

// Load List Siswa & Nilai yang Sudah Ada
$listSiswa = [];
if ($selectedKelasId > 0) {
    $stmtSiswa = $pdo->prepare("
        SELECT s.id AS siswa_id, s.nis, s.nama, k.nama_kelas,
               n.sumatif_1, n.sumatif_2, n.sumatif_3, n.sumatif_4, n.ats, n.aas, n.nilai_akhir, n.predikat, n.catatan
        FROM siswa s
        LEFT JOIN kelas k ON s.kelas_id = k.id
        LEFT JOIN nilai_siswa n ON (s.id = n.siswa_id AND n.mapel = :mapel AND n.semester = :sem AND n.tahun_ajaran = :th)
        WHERE s.kelas_id = :kid AND s.status = 'aktif'
        ORDER BY s.nama ASC
    ");
    $stmtSiswa->execute([
        ':kid' => $selectedKelasId,
        ':mapel' => $selectedMapel,
        ':sem' => $selectedSem,
        ':th' => $selectedTahun
    ]);
    $listSiswa = $stmtSiswa->fetchAll();
}

// End load list siswa

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    /* Modern UI Styles for Input Nilai */
    .grade-card {
        background: #ffffff;
        border: none;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
        overflow: hidden;
    }

    .weight-card {
        border-radius: 16px;
        padding: 1.1rem 1.25rem;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: #ffffff;
    }

    .weight-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 25px rgba(0, 0, 0, 0.06);
    }

    /* High-Contrast Weight Badges */
    .badge-weight-indigo {
        background-color: #4f46e5 !important;
        color: #ffffff !important;
        font-weight: 800 !important;
        font-size: 0.75rem !important;
        padding: 0.35rem 0.75rem !important;
        border-radius: 8px !important;
        letter-spacing: 0.5px;
        display: inline-block;
        box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25);
    }

    .badge-weight-amber {
        background-color: #d97706 !important;
        color: #ffffff !important;
        font-weight: 800 !important;
        font-size: 0.75rem !important;
        padding: 0.35rem 0.75rem !important;
        border-radius: 8px !important;
        letter-spacing: 0.5px;
        display: inline-block;
        box-shadow: 0 2px 6px rgba(217, 119, 6, 0.25);
    }

    .badge-weight-emerald {
        background-color: #059669 !important;
        color: #ffffff !important;
        font-weight: 800 !important;
        font-size: 0.75rem !important;
        padding: 0.35rem 0.75rem !important;
        border-radius: 8px !important;
        letter-spacing: 0.5px;
        display: inline-block;
        box-shadow: 0 2px 6px rgba(5, 150, 105, 0.25);
    }

    /* High-Contrast Keyboard Tags */
    kbd {
        background-color: #0f172a !important;
        color: #ffffff !important;
        font-weight: 800 !important;
        font-size: 0.8rem !important;
        padding: 0.2rem 0.55rem !important;
        border-radius: 6px !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important;
        border: 1px solid #334155 !important;
        display: inline-block;
    }

    /* ENLARGED INPUT NILAI S1-S4 & ATS/AAS - ANGKANYA JELAS DAN BESAR */
    .input-grade {
        width: 100% !important;
        min-width: 80px !important;
        height: 46px !important;
        font-size: 1.1rem !important;
        font-weight: 800 !important;
        color: #0f172a !important;
        background-color: #f8fafc !important;
        border: 2px solid #cbd5e1 !important;
        border-radius: 12px !important;
        text-align: center !important;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
        padding: 0 0.3rem !important;
    }

    .input-grade:hover {
        border-color: #94a3b8 !important;
        background-color: #ffffff !important;
    }

    .input-grade:focus {
        border-color: #6366f1 !important;
        background-color: #ffffff !important;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.2) !important;
        outline: none !important;
        transform: scale(1.05);
        font-size: 1.15rem !important;
        color: #4338ca !important;
        z-index: 10;
        position: relative;
    }

    /* Sumatif Column Highlight */
    .col-s-header {
        background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%) !important;
        color: #ffffff !important;
    }

    .col-s-sub {
        background: rgba(99, 102, 241, 0.08) !important;
        color: #4338ca !important;
        font-weight: 700 !important;
    }

    /* Remove number input spinners */
    .input-grade::-webkit-outer-spin-button,
    .input-grade::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .input-grade[type=number] {
        -moz-appearance: textfield;
    }

    /* Modern Table Header & Cells */
    .table-nilai-modern {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
    }

    .table-nilai-modern th {
        font-size: 0.78rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        padding: 1rem 0.6rem;
        vertical-align: middle;
        border-bottom: 2px solid #e2e8f0;
    }

    .table-nilai-modern td {
        padding: 0.85rem 0.6rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }

    .table-nilai-modern tbody tr {
        transition: background-color 0.15s ease;
    }

    .table-nilai-modern tbody tr:hover {
        background-color: rgba(241, 245, 249, 0.7);
    }

    /* Modern Badges for Predikat */
    .badge-pred {
        font-size: 0.85rem;
        font-weight: 800;
        padding: 0.4rem 0.8rem;
        border-radius: 10px;
        min-width: 42px;
        display: inline-block;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.06);
        letter-spacing: 0.5px;
    }

    .pred-A {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #ffffff;
    }

    .pred-B {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: #ffffff;
    }

    .pred-C {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: #ffffff;
    }

    .pred-D {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: #ffffff;
    }

    /* Dynamic Filter Form */
    .filter-card {
        background: #ffffff;
        border-radius: 18px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
    }

    .filter-card .form-select,
    .filter-card .form-control {
        border-radius: 12px;
        border: 1.5px solid #cbd5e1;
        padding: 0.65rem 0.9rem;
        font-weight: 600;
        color: #1e293b;
    }

    .filter-card .form-select:focus,
    .filter-card .form-control:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
    }

    /* Sticky Bottom Action Bar */
    .sticky-action-bar {
        position: sticky;
        bottom: 1rem;
        z-index: 999;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(15, 23, 42, 0.12);
        padding: 1rem 1.5rem;
    }
</style>

<!-- HEADER INFORMASI -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="fw-black m-0 text-dark d-flex align-items-center gap-2">
            <span
                class="p-2 rounded-3 bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center"
                style="width: 42px; height: 42px;">
                <i class="bi bi-calculator-fill fs-4"></i>
            </span>
            Input Nilai Siswa
        </h4>
        <p class="text-muted small mb-0 mt-1">Kurikulum Merdeka — Manajemen Penilaian & Leger Raport Digital</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/pages/nilai/print-leger.php?kelas_id=<?= $selectedKelasId ?>&mapel=<?= urlencode($selectedMapel) ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>"
            target="_blank"
            class="btn btn-outline-danger px-3 py-2 fw-bold rounded-3 d-flex align-items-center gap-2 shadow-sm">
            <i class="bi bi-file-earmark-pdf-fill fs-5"></i> Cetak Leger PDF
        </a>
    </div>
</div>

<?php if ($isGuruRole && (empty($mapelOptions) || empty($kelases))): ?>
    <div class="alert alert-warning border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center gap-3 p-3">
        <i class="bi bi-exclamation-triangle-fill fs-2 text-warning"></i>
        <div>
            <h6 class="fw-bold mb-1 text-dark">Anda Belum Memiliki Penugasan Kelas & Mata Pelajaran</h6>
            <p class="mb-0 small text-muted">Akun Guru Anda belum dialokasikan untuk mengampu Mata Pelajaran atau Kelas
                tertentu. Silakan hubungi Administrator Sekolah untuk mengatur alokasi jam mengajar Anda pada menu
                <strong>Plotting Guru Pengampu</strong>.</p>
        </div>
    </div>
<?php endif; ?>

<!-- FILTER BAR -->
<div class="filter-card p-4 mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3 col-sm-6">
            <label class="form-label fw-bold text-secondary small text-uppercase mb-1"><i
                    class="bi bi-journal-text me-1 text-primary"></i> Mata Pelajaran</label>
            <select name="mapel" class="form-select" onchange="this.form.submit()">
                <?php foreach ($mapelOptions as $m): ?>
                    <option value="<?= htmlspecialchars($m) ?>" <?= $selectedMapel === $m ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 col-sm-6">
            <label class="form-label fw-bold text-secondary small text-uppercase mb-1"><i
                    class="bi bi-building me-1 text-cyan"></i> Kelas</label>
            <select name="kelas_id" class="form-select" onchange="this.form.submit()">
                <?php foreach ($kelases as $k): ?>
                    <option value="<?= $k['id'] ?>" <?= $selectedKelasId == $k['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 col-sm-6">
            <label class="form-label fw-bold text-secondary small text-uppercase mb-1"><i
                    class="bi bi-calendar-range me-1 text-amber"></i> Semester</label>
            <select name="semester" class="form-select" onchange="this.form.submit()">
                <option value="Ganjil" <?= $selectedSem === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                <option value="Genap" <?= $selectedSem === 'Genap' ? 'selected' : '' ?>>Genap</option>
            </select>
        </div>
        <div class="col-md-2 col-sm-6">
            <label class="form-label fw-bold text-secondary small text-uppercase mb-1"><i
                    class="bi bi-calendar-event me-1 text-emerald"></i> Tahun Ajaran</label>
            <input type="text" name="tahun_ajaran" class="form-control" value="<?= htmlspecialchars($selectedTahun) ?>"
                onchange="this.form.submit()">
        </div>
        <div class="col-md-2 col-12 d-grid">
            <button type="submit" class="btn btn-primary fw-bold rounded-3 py-2 text-white shadow-sm"
                style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border: none;">
                <i class="bi bi-funnel-fill me-1"></i> Tampilkan
            </button>
        </div>
    </form>
</div>

<!-- INFORMASI BOBOT PENILAIAN CARD -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="weight-card d-flex align-items-center gap-3">
            <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white"
                style="background: linear-gradient(135deg, #6366f1, #4f46e5); width: 48px; height: 48px; flex-shrink: 0;">
                <i class="bi bi-journal-check fs-4"></i>
            </div>
            <div>
                <span class="badge-weight-indigo mb-1">Bobot 80%</span>
                <h6 class="fw-bold text-dark m-0">Sumatif (S1 - S4)</h6>
                <small class="text-muted">Rata-rata Nilai Lingkup Materi 1-4</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="weight-card d-flex align-items-center gap-3">
            <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white"
                style="background: linear-gradient(135deg, #f59e0b, #d97706); width: 48px; height: 48px; flex-shrink: 0;">
                <i class="bi bi-hourglass-split fs-4"></i>
            </div>
            <div>
                <span class="badge-weight-amber mb-1">Bobot 10%</span>
                <h6 class="fw-bold text-dark m-0">ATS (Tengah Semester)</h6>
                <small class="text-muted">Asesmen Tengah Semester</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="weight-card d-flex align-items-center gap-3">
            <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white"
                style="background: linear-gradient(135deg, #10b981, #059669); width: 48px; height: 48px; flex-shrink: 0;">
                <i class="bi bi-award-fill fs-4"></i>
            </div>
            <div>
                <span class="badge-weight-emerald mb-1">Bobot 10%</span>
                <h6 class="fw-bold text-dark m-0">AAS (Akhir Semester)</h6>
                <small class="text-muted">Asesmen Akhir Semester</small>
            </div>
        </div>
    </div>
</div>

<!-- FORM INPUT & TABEL NILAI -->
<form method="POST" id="formNilaiSiswa">
    <input type="hidden" name="action" value="save_nilai">
    <input type="hidden" name="kelas_id" value="<?= $selectedKelasId ?>">
    <input type="hidden" name="mapel" value="<?= htmlspecialchars($selectedMapel) ?>">
    <input type="hidden" name="semester" value="<?= htmlspecialchars($selectedSem) ?>">
    <input type="hidden" name="tahun_ajaran" value="<?= htmlspecialchars($selectedTahun) ?>">

    <div class="grade-card mb-4">
        <div
            class="p-3 p-md-4 border-bottom bg-light bg-opacity-50 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="fw-bold m-0 text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-pencil-square text-primary"></i>
                    Lembar Nilai — <?= htmlspecialchars($selectedMapel) ?>
                </h5>
                <small class="text-muted">Kurikulum Merdeka | Semester <?= htmlspecialchars($selectedSem) ?>
                    <?= htmlspecialchars($selectedTahun) ?></small>
            </div>
            <div class="small text-dark bg-white px-3 py-1.5 rounded-pill border shadow-sm fw-bold">
                💡 <span class="fw-bold text-primary">Tips:</span> Tekan tombol <kbd>Enter</kbd> atau <kbd>↑</kbd>
                <kbd>↓</kbd> di keyboard untuk pindah baris nilai dengan cepat.
            </div>
        </div>

        <div class="table-responsive">
            <table class="table-nilai-modern align-middle" id="tableNilai">
                <thead>
                    <tr class="text-center bg-slate-900 text-white">
                        <th style="width: 45px;" class="bg-dark text-white">No</th>
                        <th style="width: 110px;" class="bg-dark text-white">NIS</th>
                        <th style="min-width: 200px;" class="text-start bg-dark text-white ps-3">Nama Siswa</th>

                        <!-- HEADER KELOMPOK SUMATIF (S1 - S4) -->
                        <th style="min-width: 95px; width: 95px;" class="col-s-header">S1</th>
                        <th style="min-width: 95px; width: 95px;" class="col-s-header">S2</th>
                        <th style="min-width: 95px; width: 95px;" class="col-s-header">S3</th>
                        <th style="min-width: 95px; width: 95px;" class="col-s-header">S4</th>
                        <th style="width: 100px; background: #312e81; color: #fff;">Rata (80%)</th>

                        <!-- HEADER ATS & AAS -->
                        <th style="min-width: 95px; width: 95px; background: #78350f; color: #fff;">ATS (10%)</th>
                        <th style="min-width: 95px; width: 95px; background: #064e3b; color: #fff;">AAS (10%)</th>

                        <!-- FINAL GRADE & PREDIKAT -->
                        <th style="width: 110px; background: #0f172a; color: #fff;">Nilai Akhir</th>
                        <th style="width: 80px;" class="bg-dark text-white">Predikat</th>
                        <th style="min-width: 160px;" class="text-start bg-dark text-white ps-3">Catatan Guru</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($listSiswa)): ?>
                        <tr>
                            <td colspan="13" class="text-center py-5 text-muted">
                                <i class="bi bi-people display-4 d-block mb-3 opacity-40"></i>
                                <h6 class="fw-bold mb-1">Data Siswa Tidak Ditemukan</h6>
                                <p class="small text-muted mb-0">Silakan pilih kombinasi Kelas dan Mata Pelajaran lain di
                                    atas.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($listSiswa as $i => $s):
                            $s1 = (float) ($s['sumatif_1'] ?? 0);
                            $s2 = (float) ($s['sumatif_2'] ?? 0);
                            $s3 = (float) ($s['sumatif_3'] ?? 0);
                            $s4 = (float) ($s['sumatif_4'] ?? 0);
                            $ats = (float) ($s['ats'] ?? 0);
                            $aas = (float) ($s['aas'] ?? 0);

                            $rSumatif = ($s1 + $s2 + $s3 + $s4) / 4.0;
                            $na = ($rSumatif * 0.80) + ($ats * 0.10) + ($aas * 0.10);
                            $na = round($na, 2);

                            if ($na >= 90)
                                $pred = 'A';
                            elseif ($na >= 80)
                                $pred = 'B';
                            elseif ($na >= 70)
                                $pred = 'C';
                            else
                                $pred = 'D';
                            ?>
                            <tr data-row="<?= $i ?>">
                                <td class="text-center fw-bold text-secondary small"><?= $i + 1 ?></td>
                                <td class="text-center">
                                    <span
                                        class="font-monospace fw-bold text-dark px-2 py-1 rounded bg-light border"><?= htmlspecialchars($s['nis']) ?></span>
                                    <input type="hidden" name="siswa_id[]" value="<?= $s['siswa_id'] ?>">
                                </td>
                                <td class="ps-3">
                                    <span class="fw-bold text-dark d-block"><?= htmlspecialchars($s['nama']) ?></span>
                                </td>

                                <!-- ENLARGED INPUT NILAI SUMATIF 1-4 -->
                                <td class="col-s-sub p-2 text-center">
                                    <input type="number" step="0.01" min="0" max="100" name="sumatif_1[]"
                                        class="form-control input-grade input-s1" value="<?= $s1 ?>"
                                        oninput="calcRow(<?= $i ?>)" onfocus="this.select()" autocomplete="off">
                                </td>
                                <td class="col-s-sub p-2 text-center">
                                    <input type="number" step="0.01" min="0" max="100" name="sumatif_2[]"
                                        class="form-control input-grade input-s2" value="<?= $s2 ?>"
                                        oninput="calcRow(<?= $i ?>)" onfocus="this.select()" autocomplete="off">
                                </td>
                                <td class="col-s-sub p-2 text-center">
                                    <input type="number" step="0.01" min="0" max="100" name="sumatif_3[]"
                                        class="form-control input-grade input-s3" value="<?= $s3 ?>"
                                        oninput="calcRow(<?= $i ?>)" onfocus="this.select()" autocomplete="off">
                                </td>
                                <td class="col-s-sub p-2 text-center">
                                    <input type="number" step="0.01" min="0" max="100" name="sumatif_4[]"
                                        class="form-control input-grade input-s4" value="<?= $s4 ?>"
                                        oninput="calcRow(<?= $i ?>)" onfocus="this.select()" autocomplete="off">
                                </td>

                                <!-- RATA-RATA SUMATIF (AUTOMATIC) -->
                                <td class="text-center fw-black text-indigo fs-6 bg-indigo-subtle cell-rsumatif">
                                    <?= number_format($rSumatif, 2) ?>
                                </td>

                                <!-- INPUT ATS & AAS -->
                                <td class="p-2 text-center" style="background-color: rgba(245, 158, 11, 0.05);">
                                    <input type="number" step="0.01" min="0" max="100" name="ats[]"
                                        class="form-control input-grade input-ats" value="<?= $ats ?>"
                                        oninput="calcRow(<?= $i ?>)" onfocus="this.select()" autocomplete="off"
                                        style="border-color: #fde68a !important; color: #92400e !important;">
                                </td>
                                <td class="p-2 text-center" style="background-color: rgba(16, 185, 129, 0.05);">
                                    <input type="number" step="0.01" min="0" max="100" name="aas[]"
                                        class="form-control input-grade input-aas" value="<?= $aas ?>"
                                        oninput="calcRow(<?= $i ?>)" onfocus="this.select()" autocomplete="off"
                                        style="border-color: #a7f3d0 !important; color: #065f46 !important;">
                                </td>

                                <!-- NILAI AKHIR (NA) & PREDIKAT -->
                                <td class="text-center fw-black fs-5 text-dark bg-slate-100 cell-na">
                                    <?= number_format($na, 2) ?>
                                </td>
                                <td class="text-center cell-pred">
                                    <span class="badge-pred pred-<?= $pred ?>">
                                        <?= $pred ?>
                                    </span>
                                </td>
                                <td class="ps-3 pe-3">
                                    <input type="text" name="catatan[]" class="form-control form-control-sm border-1 rounded-3"
                                        value="<?= htmlspecialchars($s['catatan'] ?? '') ?>"
                                        placeholder="Catatan perkembangan siswa...">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- STICKY BOTTOM ACTION BAR -->
    <?php if (!empty($listSiswa)): ?>
        <div class="sticky-action-bar d-flex align-items-center justify-content-between gap-3">
            <div class="d-none d-md-flex align-items-center gap-2 text-muted small">
                <i class="bi bi-info-circle-fill text-primary fs-5"></i>
                <span>Nilai otomatis dihitung secara real-time. Klik <strong>Simpan Semua Nilai</strong> untuk menyimpan ke
                    database.</span>
            </div>
            <div class="d-flex gap-2 ms-auto w-100 w-md-auto justify-content-end">
                <button type="submit"
                    class="btn btn-lg px-5 py-2.5 fw-black text-white rounded-3 shadow-lg d-flex align-items-center gap-2"
                    style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none;">
                    <i class="bi bi-cloud-arrow-up-fill fs-5"></i> Simpan Semua Nilai Siswa
                </button>
            </div>
        </div>
    <?php endif; ?>
</form>

<script>
    // Real-Time Calculation Logic
    function calcRow(rowIndex) {
        const row = document.querySelector(`tr[data-row="${rowIndex}"]`);
        if (!row) return;

        const s1 = parseFloat(row.querySelector('.input-s1').value) || 0;
        const s2 = parseFloat(row.querySelector('.input-s2').value) || 0;
        const s3 = parseFloat(row.querySelector('.input-s3').value) || 0;
        const s4 = parseFloat(row.querySelector('.input-s4').value) || 0;
        const ats = parseFloat(row.querySelector('.input-ats').value) || 0;
        const aas = parseFloat(row.querySelector('.input-aas').value) || 0;

        // Sumatif 80%, ATS 10%, AAS 10%
        const rSumatif = (s1 + s2 + s3 + s4) / 4.0;
        const na = (rSumatif * 0.80) + (ats * 0.10) + (aas * 0.10);

        // Predikat calculation
        let pred = 'D';
        if (na >= 90) { pred = 'A'; }
        else if (na >= 80) { pred = 'B'; }
        else if (na >= 70) { pred = 'C'; }

        // Update cells with smooth animation
        row.querySelector('.cell-rsumatif').textContent = rSumatif.toFixed(2);
        row.querySelector('.cell-na').textContent = na.toFixed(2);
        row.querySelector('.cell-pred').innerHTML = `<span class="badge-pred pred-${pred}">${pred}</span>`;
    }

    // Quick Keyboard Arrow & Enter Navigation (Super fast data entry)
    document.addEventListener('keydown', function (e) {
        if (e.target.classList.contains('input-grade')) {
            const input = e.target;
            const td = input.closest('td');
            const tr = input.closest('tr');
            if (!tr || !td) return;

            const colIndex = Array.from(tr.children).indexOf(td);

            if (e.key === 'Enter' || e.key === 'ArrowDown') {
                e.preventDefault();
                const nextTr = tr.nextElementSibling;
                if (nextTr && nextTr.children[colIndex]) {
                    const nextInput = nextTr.children[colIndex].querySelector('.input-grade');
                    if (nextInput) {
                        nextInput.focus();
                        nextInput.select();
                    }
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const prevTr = tr.previousElementSibling;
                if (prevTr && prevTr.children[colIndex]) {
                    const prevInput = prevTr.children[colIndex].querySelector('.input-grade');
                    if (prevInput) {
                        prevInput.focus();
                        prevInput.select();
                    }
                }
            }
        }
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>