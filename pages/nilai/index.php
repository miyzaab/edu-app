<?php
/**
 * AKADEMIK & GURU - Input Nilai Siswa (Kurikulum Merdeka)
 * Bobot: Sumatif (S1-S4) 80%, ATS 10%, AAS 10%
 * UI/UX: Ultra Clean Modern SaaS Aesthetic (Subtle Slate, Pastel Badges, Pixel-Perfect)
 */
$pageTitle = 'Input Nilai Siswa';
$activePage = 'input-nilai';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('input_nilai');

$pdo = getConnection();
$userId = (int) $_SESSION['user_id'];
$userRole = strtolower($_SESSION['role'] ?? '');

// Active Sub-Tab
$activeSubTab = isset($_GET['tab']) && in_array($_GET['tab'], ['nilai', 'deskripsi', 'pengaturan']) ? $_GET['tab'] : 'nilai';

// Filter berdasarkan Role Guru
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

// AJAX Toggle Handler for Portal Nilai Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajax_toggle_portal_nilai') {
    header('Content-Type: application/json');
    $key = trim($_POST['setting_key'] ?? '');
    $val = trim($_POST['setting_value'] ?? '0');
    $allowedKeys = [
        'portal_show_sumatif_1',
        'portal_show_sumatif_2',
        'portal_show_sumatif_3',
        'portal_show_sumatif_4',
        'portal_show_ats',
        'portal_show_aas',
        'portal_show_nilai_akhir',
        'portal_show_predikat_mapel',
        'portal_show_kkm',
        'portal_show_deskripsi_catatan',
        'portal_show_hero_akademik'
    ];
    if (in_array($key, $allowedKeys)) {
        updateSetting($key, $val === '1' ? '1' : '0');
        echo json_encode(['status' => 'success', 'key' => $key, 'value' => $val]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid setting key']);
    }
    exit;
}

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
            // Hitung rata-rata sumatif dari lingkup materi yang telah diisi
            $sumArr = array_filter([$s1, $s2, $s3, $s4], fn($v) => $v > 0);
            $rSumatif = !empty($sumArr) ? (array_sum($sumArr) / count($sumArr)) : 0.0;
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
            $countSaved++;
        }

        redirect("index.php?tab=nilai&kelas_id=$postKelasId&mapel=" . urlencode($postMapel) . "&semester=" . urlencode($postSem) . "&tahun_ajaran=" . urlencode($postTahun), 'success', "Alhamdulillah, data nilai $countSaved siswa berhasil disimpan.");
    } catch (PDOException $e) {
        $flashMsg = "Gagal menyimpan nilai: " . $e->getMessage();
    }
}

// Load Siswa & Existing Nilai
$listSiswa = [];
if ($selectedKelasId > 0 && !empty($selectedMapel)) {
    $stmtSiswa = $pdo->prepare("
        SELECT 
            s.id AS siswa_id, s.nis, s.nama,
            n.sumatif_1, n.sumatif_2, n.sumatif_3, n.sumatif_4,
            n.ats, n.aas, n.nilai_akhir, n.predikat, n.catatan
        FROM siswa s
        LEFT JOIN nilai_siswa n ON s.id = n.siswa_id 
            AND n.mapel = :mapel 
            AND n.semester = :sem 
            AND n.tahun_ajaran = :th
        WHERE s.kelas_id = :kid AND (s.status IS NULL OR s.status = 'aktif')
        ORDER BY s.nama ASC
    ");
    $stmtSiswa->execute([
        ':mapel' => $selectedMapel,
        ':sem' => $selectedSem,
        ':th' => $selectedTahun,
        ':kid' => $selectedKelasId
    ]);
    $listSiswa = $stmtSiswa->fetchAll();
}

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    /* ==========================================================================
       CLEAN EXECUTIVE NEUTRAL THEME (NO DISTRACTING COLORS, TIMELESS TYPOGRAPHY)
       ========================================================================== */
    .subtab-pill-container {
        display: inline-flex;
        background: #f1f5f9;
        padding: 4px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        gap: 4px;
    }

    .subtab-pill {
        padding: 7px 16px;
        font-size: 0.85rem;
        font-weight: 600;
        color: #64748b;
        border-radius: 8px;
        text-decoration: none;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .subtab-pill:hover {
        color: #0f172a;
        background: #ffffff;
    }

    .subtab-pill.active {
        background: #ffffff;
        color: #0f172a;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
    }

    /* Filter Card */
    .filter-card-clean {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    }

    .filter-card-clean .form-select,
    .filter-card-clean .form-control {
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        font-size: 0.88rem;
        color: #0f172a;
        padding: 0.5rem 0.75rem;
        font-weight: 500;
        background-color: #ffffff;
    }

    .filter-card-clean .form-select:focus,
    .filter-card-clean .form-control:focus {
        border-color: #0f172a;
        box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.08);
    }

    /* Grade Table Clean Neutral & Precision Alignment */
    .grade-table-card {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    }

    .table-clean {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
    }

    .table-clean th {
        background: #f8fafc !important;
        color: #475569 !important;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.3px;
        padding: 0.75rem 0.5rem;
        text-align: center !important;
        vertical-align: middle !important;
        border: 1px solid #e2e8f0;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .table-clean td {
        padding: 0.45rem 0.5rem;
        vertical-align: middle !important;
        text-align: center;
        border-bottom: 1px solid #f1f5f9;
        border-right: 1px solid #f1f5f9;
    }

    .table-clean tbody tr:hover {
        background-color: #f8fafc;
    }

    /* Precision Numeric Typography */
    .table-clean th,
    .table-clean td {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }

    .input-grade-clean,
    .cell-r-sumatif,
    .cell-na,
    .cell-nis {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
        font-variant-numeric: tabular-nums;
        font-feature-settings: "tnum";
    }

    /* Hide Chrome, Safari, Edge, Opera spin buttons */
    input.input-grade-clean::-webkit-outer-spin-button,
    input.input-grade-clean::-webkit-inner-spin-button {
        -webkit-appearance: none !important;
        margin: 0 !important;
        display: none !important;
    }

    /* Hide Firefox spin buttons */
    input.input-grade-clean {
        -moz-appearance: textfield !important;
        appearance: textfield !important;
    }

    .input-grade-clean {
        width: 100% !important;
        max-width: 62px !important;
        height: 38px !important;
        font-size: 0.95rem !important;
        font-weight: 600 !important;
        text-align: center !important;
        color: #0f172a !important;
        background: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 6px !important;
        padding: 0 !important;
        margin: 0 auto !important;
        display: block !important;
        line-height: 36px !important;
        transition: all 0.15s ease;
    }

    .input-grade-clean::placeholder {
        color: #cbd5e1 !important;
        font-weight: 400 !important;
        text-align: center !important;
        opacity: 0.7;
    }

    .input-grade-clean:focus {
        background: #ffffff !important;
        border-color: #0f172a !important;
        box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.08) !important;
        outline: none !important;
        color: #000000 !important;
    }

    .input-catatan-clean {
        width: 100%;
        height: 38px;
        font-size: 0.85rem;
        font-weight: 500;
        color: #334155;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 6px 10px;
        transition: all 0.15s ease;
        text-align: left !important;
    }

    .input-catatan-clean::placeholder {
        color: #94a3b8;
    }

    .input-catatan-clean:focus {
        background: #ffffff;
        border-color: #0f172a;
        box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.08);
        outline: none;
        color: #0f172a;
    }

    .cell-r-sumatif {
        font-size: 0.92rem !important;
        font-weight: 600 !important;
        color: #475569 !important;
        text-align: center !important;
        vertical-align: middle !important;
    }

    .cell-na {
        font-size: 0.95rem !important;
        font-weight: 700 !important;
        color: #0f172a !important;
        text-align: center !important;
        vertical-align: middle !important;
    }

    /* Monochrome / Neutral Predikat Badges */
    .badge-pred-clean {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 700;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: #0f172a;
        margin: 0 auto;
    }

    .pred-clean-A,
    .pred-clean-B,
    .pred-clean-C,
    .pred-clean-D {
        background: #f8fafc;
        color: #0f172a;
        border-color: #e2e8f0;
    }

    /* Floating Action Bar (Clean Obsidian / Neutral) */
    .floating-bottom-bar {
        position: sticky;
        bottom: 1.25rem;
        z-index: 1000;
        background: rgba(255, 255, 255, 0.96);
        backdrop-filter: blur(14px);
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.75rem 1.25rem;
        box-shadow: 0 6px 24px rgba(0, 0, 0, 0.06);
    }

    .btn-obsidian-save {
        background: #0f172a;
        color: #ffffff;
        border: 1px solid #0f172a;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.9rem;
        padding: 0.55rem 1.35rem;
        transition: all 0.15s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-obsidian-save:hover {
        background: #1e293b;
        color: #ffffff;
        border-color: #1e293b;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
    }

    .btn-filter-action {
        background: #0f172a;
        color: #ffffff;
        border: 1px solid #0f172a;
        border-radius: 8px;
        font-weight: 600;
        padding: 0.5rem 1rem;
        transition: all 0.15s ease;
    }

    .btn-filter-action:hover {
        background: #1e293b;
        color: #ffffff;
        border-color: #1e293b;
    }

    /* Setting Toggle Switches */
    .setting-box {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.1rem;
        transition: all 0.2s ease;
    }

    .setting-box:hover {
        border-color: #cbd5e1;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    }

    .form-check-input-lg {
        width: 2.8rem;
        height: 1.5rem;
        cursor: pointer;
    }
</style>

<!-- HEADER UTAMA & SUB-TAB NAVIGASI -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
    <div>
        <h4 class="fw-bold m-0 text-dark d-flex align-items-center gap-2" style="letter-spacing: -0.3px;">
            <span
                class="p-2 rounded-3 bg-light text-dark border d-inline-flex align-items-center justify-content-center"
                style="width: 38px; height: 38px;">
                <i class="bi bi-mortarboard fs-5"></i>
            </span>
            Penilaian Akademik Siswa
        </h4>
        <p class="text-muted small mb-0 mt-1">Kurikulum Merdeka — Manajemen Penilaian, Rapor &amp; Portal Ortu</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>/pages/nilai/print-leger.php?kelas_id=<?= $selectedKelasId ?>&mapel=<?= urlencode($selectedMapel) ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>"
            target="_blank"
            class="btn btn-outline-secondary px-3 py-2 fw-semibold rounded-3 d-flex align-items-center gap-2"
            style="border-color: #cbd5e1; color: #334155; font-size: 0.88rem;">
            <i class="bi bi-printer"></i> Cetak Leger PDF
        </a>
    </div>
</div>

<!-- SUB-TAB NAVIGASI -->
<div class="mb-4">
    <div class="subtab-pill-container">
        <a href="index.php?tab=nilai&kelas_id=<?= $selectedKelasId ?>&mapel=<?= urlencode($selectedMapel) ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>"
            class="subtab-pill <?= $activeSubTab === 'nilai' ? 'active' : '' ?>">
            <i class="bi bi-pencil-square"></i> 1. Input Nilai Siswa
        </a>
        <a href="index.php?tab=deskripsi&kelas_id=<?= $selectedKelasId ?>&mapel=<?= urlencode($selectedMapel) ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>"
            class="subtab-pill <?= $activeSubTab === 'deskripsi' ? 'active' : '' ?>">
            <i class="bi bi-card-text"></i> 2. Deskripsi Rapor AAS
        </a>
        <a href="index.php?tab=pengaturan&kelas_id=<?= $selectedKelasId ?>&mapel=<?= urlencode($selectedMapel) ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>"
            class="subtab-pill <?= $activeSubTab === 'pengaturan' ? 'active' : '' ?>">
            <i class="bi bi-sliders"></i> 3. Pengaturan Nilai Portal Ortu
        </a>
    </div>
</div>

<?php if ($activeSubTab === 'nilai'): ?>
    <!-- FILTER BAR -->
    <div class="filter-card-clean p-3 mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="nilai">
            <div class="col-md-3 col-sm-6">
                <label class="form-label fw-bold text-secondary extra-small text-uppercase mb-1"
                    style="font-size: 0.72rem; letter-spacing: 0.5px;">Mata Pelajaran</label>
                <select name="mapel" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($mapelOptions as $m): ?>
                        <option value="<?= htmlspecialchars($m) ?>" <?= $selectedMapel === $m ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-sm-6">
                <label class="form-label fw-bold text-secondary extra-small text-uppercase mb-1"
                    style="font-size: 0.72rem; letter-spacing: 0.5px;">Kelas</label>
                <select name="kelas_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($kelases as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= $selectedKelasId == $k['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($k['nama_kelas']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-sm-6">
                <label class="form-label fw-bold text-secondary extra-small text-uppercase mb-1"
                    style="font-size: 0.72rem; letter-spacing: 0.5px;">Semester</label>
                <select name="semester" class="form-select" onchange="this.form.submit()">
                    <option value="Ganjil" <?= $selectedSem === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                    <option value="Genap" <?= $selectedSem === 'Genap' ? 'selected' : '' ?>>Genap</option>
                </select>
            </div>
            <div class="col-md-2 col-sm-6">
                <label class="form-label fw-bold text-secondary extra-small text-uppercase mb-1"
                    style="font-size: 0.72rem; letter-spacing: 0.5px;">Tahun Ajaran</label>
                <input type="text" name="tahun_ajaran" class="form-control" value="<?= htmlspecialchars($selectedTahun) ?>"
                    onchange="this.form.submit()">
            </div>
            <div class="col-md-2 col-12 d-grid">
                <button type="submit" class="btn btn-filter-action">
                    <i class="bi bi-funnel me-1"></i> Tampilkan
                </button>
            </div>
        </form>
    </div>

    <!-- FORM & TABEL NILAI -->
    <form method="POST" id="formNilaiSiswa">
        <input type="hidden" name="action" value="save_nilai">
        <input type="hidden" name="kelas_id" value="<?= $selectedKelasId ?>">
        <input type="hidden" name="mapel" value="<?= htmlspecialchars($selectedMapel) ?>">
        <input type="hidden" name="semester" value="<?= htmlspecialchars($selectedSem) ?>">
        <input type="hidden" name="tahun_ajaran" value="<?= htmlspecialchars($selectedTahun) ?>">

        <div class="grade-table-card mb-4">
            <div class="p-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2 bg-light">
                <div>
                    <h6 class="fw-bold m-0 text-dark" style="font-size: 0.95rem;">
                        <i class="bi bi-pencil-square text-secondary me-1"></i> Lembar Nilai —
                        <?= htmlspecialchars($selectedMapel) ?>
                    </h6>
                    <small class="text-muted">Kurikulum Merdeka • Semester <?= htmlspecialchars($selectedSem) ?>
                        <?= htmlspecialchars($selectedTahun) ?></small>
                </div>
                <div class="small text-secondary fw-semibold">
                    💡 Tekan tombol <kbd
                        style="background: #ffffff; border: 1px solid #cbd5e1; box-shadow: 0 1px 0 rgba(0,0,0,0.08); border-radius: 4px; padding: 2px 6px; font-size: 0.75rem; color: #334155;">Enter</kbd>
                    atau <kbd
                        style="background: #ffffff; border: 1px solid #cbd5e1; box-shadow: 0 1px 0 rgba(0,0,0,0.08); border-radius: 4px; padding: 2px 6px; font-size: 0.75rem; color: #334155;">↑</kbd>
                    <kbd
                        style="background: #ffffff; border: 1px solid #cbd5e1; box-shadow: 0 1px 0 rgba(0,0,0,0.08); border-radius: 4px; padding: 2px 6px; font-size: 0.75rem; color: #334155;">↓</kbd>
                    untuk berpindah baris.
                </div>
            </div>

            <div class="table-responsive">
                <table class="table-clean" id="tableNilai">
                    <thead>
                        <tr>
                            <th style="width: 45px;">No</th>
                            <th style="width: 105px;">NIS</th>
                            <th style="min-width: 180px;" class="text-start ps-3">Nama Siswa</th>

                            <th style="width: 70px;">S1</th>
                            <th style="width: 70px;">S2</th>
                            <th style="width: 70px;">S3</th>
                            <th style="width: 70px;">S4</th>
                            <th style="width: 95px;">Rata (80%)</th>

                            <th style="width: 85px;">ATS (10%)</th>
                            <th style="width: 85px;">AAS (10%)</th>

                            <th style="width: 100px;">Nilai Akhir</th>
                            <th style="width: 70px;">Predikat</th>
                            <th style="min-width: 160px;" class="text-start ps-3">Catatan Guru</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($listSiswa)): ?>
                            <tr>
                                <td colspan="13" class="text-center py-5 text-muted">
                                    <i class="bi bi-people display-5 d-block mb-2 opacity-25"></i>
                                    <h6 class="fw-bold mb-1 text-dark">Data Siswa Tidak Ditemukan</h6>
                                    <p class="small text-muted mb-0">Silakan pilih Kelas dan Mata Pelajaran yang tersedia.</p>
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

                                $sumArr = array_filter([$s1, $s2, $s3, $s4], fn($v) => $v > 0);
                                $rSumatif = !empty($sumArr) ? (array_sum($sumArr) / count($sumArr)) : 0.0;

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
                                    <td class="text-center fw-semibold text-muted small align-middle"><?= $i + 1 ?></td>
                                    <td class="text-center align-middle">
                                        <span class="cell-nis text-secondary small"><?= htmlspecialchars($s['nis']) ?></span>
                                        <input type="hidden" name="siswa_id[]" value="<?= $s['siswa_id'] ?>">
                                    </td>
                                    <td class="ps-3 fw-bold text-dark align-middle" style="font-size: 0.88rem;">
                                        <?= htmlspecialchars($s['nama']) ?>
                                    </td>

                                    <td class="p-1 text-center align-middle">
                                        <input type="number" step="0.01" min="0" max="100" name="sumatif_1[]"
                                            class="form-control input-grade-clean input-s1"
                                            value="<?= $s1 > 0 ? (float) $s1 : '' ?>" placeholder="0" oninput="calcRow(<?= $i ?>)"
                                            onfocus="this.select()" autocomplete="off">
                                    </td>
                                    <td class="p-1 text-center align-middle">
                                        <input type="number" step="0.01" min="0" max="100" name="sumatif_2[]"
                                            class="form-control input-grade-clean input-s2"
                                            value="<?= $s2 > 0 ? (float) $s2 : '' ?>" placeholder="0" oninput="calcRow(<?= $i ?>)"
                                            onfocus="this.select()" autocomplete="off">
                                    </td>
                                    <td class="p-1 text-center align-middle">
                                        <input type="number" step="0.01" min="0" max="100" name="sumatif_3[]"
                                            class="form-control input-grade-clean input-s3"
                                            value="<?= $s3 > 0 ? (float) $s3 : '' ?>" placeholder="0" oninput="calcRow(<?= $i ?>)"
                                            onfocus="this.select()" autocomplete="off">
                                    </td>
                                    <td class="p-1 text-center align-middle">
                                        <input type="number" step="0.01" min="0" max="100" name="sumatif_4[]"
                                            class="form-control input-grade-clean input-s4"
                                            value="<?= $s4 > 0 ? (float) $s4 : '' ?>" placeholder="0" oninput="calcRow(<?= $i ?>)"
                                            onfocus="this.select()" autocomplete="off">
                                    </td>
                                    <td class="text-center cell-r-sumatif align-middle">
                                        <?= number_format($rSumatif, 2) ?>
                                    </td>

                                    <td class="p-1 text-center align-middle">
                                        <input type="number" step="0.01" min="0" max="100" name="ats[]"
                                            class="form-control input-grade-clean input-ats"
                                            value="<?= $ats > 0 ? (float) $ats : '' ?>" placeholder="0" oninput="calcRow(<?= $i ?>)"
                                            onfocus="this.select()" autocomplete="off">
                                    </td>
                                    <td class="p-1 text-center align-middle">
                                        <input type="number" step="0.01" min="0" max="100" name="aas[]"
                                            class="form-control input-grade-clean input-aas"
                                            value="<?= $aas > 0 ? (float) $aas : '' ?>" placeholder="0" oninput="calcRow(<?= $i ?>)"
                                            onfocus="this.select()" autocomplete="off">
                                    </td>

                                    <td class="text-center cell-na align-middle">
                                        <?= number_format($na, 2) ?>
                                    </td>
                                    <td class="text-center cell-pred align-middle">
                                        <span class="badge-pred-clean pred-clean-<?= $pred ?>"><?= $pred ?></span>
                                    </td>
                                    <td class="p-1 ps-3 align-middle">
                                        <input type="text" name="catatan[]" class="form-control input-catatan-clean"
                                            value="<?= htmlspecialchars($s['catatan'] ?? '') ?>"
                                            placeholder="Catatan perkembangan..." autocomplete="off">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($listSiswa)): ?>
            <!-- FLOATING BOTTOM BAR -->
            <div class="floating-bottom-bar d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2 text-secondary small">
                    <i class="bi bi-info-circle text-muted fs-6"></i>
                    <span>Nilai akhir dan predikat dihitung otomatis secara real-time.</span>
                </div>
                <button type="submit" class="btn btn-obsidian-save">
                    <i class="bi bi-cloud-arrow-up"></i> Simpan Semua Nilai Siswa
                </button>
            </div>
        <?php endif; ?>
    </form>

<?php elseif ($activeSubTab === 'deskripsi'): ?>
    <!-- TAB 2: DESKRIPSI RAPOR AAS -->
    <div class="card border-0 shadow-sm rounded-4 p-4">
        <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-card-text text-secondary me-2"></i>Deskripsi Capaian Kompetensi
            Rapor AAS
        </h5>
        <p class="text-muted small">Kelola deskripsi capaian tertinggi dan perlu bimbingan untuk rapor otomatis.</p>
        <div class="alert alert-light border rounded-3 text-secondary">
            Silakan buka menu <strong>Kelola Rapor</strong> untuk sinkronisasi deskripsi secara otomatis atau edit per
            siswa.
        </div>
    </div>

<?php elseif ($activeSubTab === 'pengaturan'): ?>
    <!-- TAB 3: PENGATURAN NILAI PORTAL ORTU -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="fw-bold m-0 text-dark"><i class="bi bi-sliders text-secondary me-2"></i>Pengaturan Visibilitas
                    Nilai Portal Orang Tua</h5>
                <p class="text-muted small mb-0 mt-1">Atur komponen nilai apa saja yang boleh dilihat oleh orang tua di
                    portal wali santri secara real-time.</p>
            </div>
            <a href="<?= BASE_URL ?>/portal-ortu.php?tab=akademik" target="_blank"
                class="btn btn-outline-secondary px-3 py-2 rounded-3 fw-semibold btn-sm"
                style="border-color: #cbd5e1; color: #334155;">
                <i class="bi bi-box-arrow-up-right me-1"></i> Buka Portal Ortu (Live Check)
            </a>
        </div>

        <?php
        $toggles = [
            ['key' => 'portal_show_sumatif_1', 'title' => 'Nilai Sumatif 1 (S1)', 'desc' => 'Tampilkan rincian nilai sumatif lingkup materi bab 1'],
            ['key' => 'portal_show_sumatif_2', 'title' => 'Nilai Sumatif 2 (S2)', 'desc' => 'Tampilkan rincian nilai sumatif lingkup materi bab 2'],
            ['key' => 'portal_show_sumatif_3', 'title' => 'Nilai Sumatif 3 (S3)', 'desc' => 'Tampilkan rincian nilai sumatif lingkup materi bab 3'],
            ['key' => 'portal_show_sumatif_4', 'title' => 'Nilai Sumatif 4 (S4)', 'desc' => 'Tampilkan rincian nilai sumatif lingkup materi bab 4'],
            ['key' => 'portal_show_ats', 'title' => 'Nilai ATS (Tengah Semester)', 'desc' => 'Tampilkan kartu penilaian Asesmen Tengah Semester'],
            ['key' => 'portal_show_aas', 'title' => 'Nilai AAS (Akhir Semester)', 'desc' => 'Tampilkan kartu penilaian Asesmen Akhir Semester'],
            ['key' => 'portal_show_nilai_akhir', 'title' => 'Nilai Akhir Rapor', 'desc' => 'Tampilkan angka nilai akhir yang telah dibobot'],
            ['key' => 'portal_show_predikat_mapel', 'title' => 'Predikat Mata Pelajaran (A/B/C/D)', 'desc' => 'Tampilkan badge predikat capaian hasil belajar'],
            ['key' => 'portal_show_kkm', 'title' => 'Informasi KKM / Kriteria Ketuntasan', 'desc' => 'Tampilkan batas nilai minimum ketuntasan per mata pelajaran'],
            ['key' => 'portal_show_deskripsi_catatan', 'title' => 'Deskripsi Capaian & Catatan Guru', 'desc' => 'Tampilkan catatan perkembangan santri dari guru pengampu'],
            ['key' => 'portal_show_hero_akademik', 'title' => 'Hero Summary Akademik', 'desc' => 'Tampilkan banner ringkasan rata-rata akademik di beranda portal'],
        ];
        ?>

        <div class="row g-3">
            <?php foreach ($toggles as $t):
                $isActive = getSetting($t['key'], '1') === '1';
                ?>
                <div class="col-md-6">
                    <div class="setting-box d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="fw-bold text-dark mb-1"><?= $t['title'] ?></h6>
                            <small class="text-muted"><?= $t['desc'] ?></small>
                        </div>
                        <div class="form-check form-switch ms-3">
                            <input class="form-check-input form-check-input-lg" type="checkbox" role="switch"
                                id="<?= $t['key'] ?>" <?= $isActive ? 'checked' : '' ?>
                                onchange="toggleSetting('<?= $t['key'] ?>', this.checked)">
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<script>
    // Kalkulasi Baris Nilai Otomatis (Presisi Numerik Tabular)
    function calcRow(idx) {
        const tr = document.querySelector(`tr[data-row="${idx}"]`);
        if (!tr) return;

        const s1 = parseFloat(tr.querySelector('.input-s1').value) || 0;
        const s2 = parseFloat(tr.querySelector('.input-s2').value) || 0;
        const s3 = parseFloat(tr.querySelector('.input-s3').value) || 0;
        const s4 = parseFloat(tr.querySelector('.input-s4').value) || 0;
        const ats = parseFloat(tr.querySelector('.input-ats').value) || 0;
        const aas = parseFloat(tr.querySelector('.input-aas').value) || 0;

        // Rata-rata sumatif hanya dari nilai sumatif yang telah diisi (> 0)
        const validSumatifs = [s1, s2, s3, s4].filter(v => v > 0);
        const rSumatif = validSumatifs.length > 0 ? (validSumatifs.reduce((a, b) => a + b, 0) / validSumatifs.length) : 0;

        const na = (rSumatif * 0.80) + (ats * 0.10) + (aas * 0.10);
        const naRounded = na.toFixed(2);

        let pred = 'D';
        if (na >= 90) pred = 'A';
        else if (na >= 80) pred = 'B';
        else if (na >= 70) pred = 'C';

        tr.querySelector('.cell-r-sumatif').textContent = rSumatif.toFixed(2);
        tr.querySelector('.cell-na').textContent = naRounded;

        const predEl = tr.querySelector('.cell-pred');
        predEl.innerHTML = `<span class="badge-pred-clean pred-clean-${pred}">${pred}</span>`;
    }

    // Navigasi Keyboard Input Nilai (Panah & Enter)
    document.addEventListener('keydown', function (e) {
        if (!['ArrowUp', 'ArrowDown', 'Enter'].includes(e.key)) return;
        const target = e.target;
        if (!target.classList.contains('input-grade-clean')) return;

        const tr = target.closest('tr');
        if (!tr) return;
        const rowIdx = parseInt(tr.getAttribute('data-row'));

        let nextTr = null;
        if (e.key === 'ArrowDown' || e.key === 'Enter') {
            nextTr = document.querySelector(`tr[data-row="${rowIdx + 1}"]`);
        } else if (e.key === 'ArrowUp') {
            nextTr = document.querySelector(`tr[data-row="${rowIdx - 1}"]`);
        }

        if (nextTr) {
            e.preventDefault();
            const className = target.classList[2] || target.name;
            const nextInput = nextTr.querySelector(`.${className}`) || nextTr.querySelector(`input[name="${target.name}"]`);
            if (nextInput) {
                nextInput.focus();
                nextInput.select();
            }
        }
    });

    // Toggle Setting via AJAX
    function toggleSetting(key, isChecked) {
        const val = isChecked ? '1' : '0';
        const formData = new FormData();
        formData.append('action', 'ajax_toggle_portal_nilai');
        formData.append('setting_key', key);
        formData.append('setting_value', val);

        fetch('index.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    // Success silent sync
                }
            })
            .catch(err => console.error(err));
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>