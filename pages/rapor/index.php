<?php
/**
 * RAPOR KURIKULUM MERDEKA (PTS & PAS) - SMP Islamic School of Minhaj Al-Ilmi
 * Pengelolaan Rapor Siswa oleh Wali Kelas & Guru
 */
$pageTitle = 'Rapor Siswa (ATS & AAS)';
$activePage = 'rapor';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('rapor');

$pdo = getConnection();
require_once __DIR__ . '/init_rapor.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userRole = strtolower($_SESSION['role'] ?? '');
$isGuruRole = ($userRole === 'guru');

// Ambil data kelas yang diwali oleh guru ini jika role guru
$waliKelasId = 0;
$validKelasIds = [];

if ($isGuruRole) {
    // Guru hanya bisa mengelola kelas yang diwalikannya
    $stmtWk = $pdo->prepare("SELECT * FROM kelas WHERE wali_kelas_id = :uid ORDER BY tingkat ASC, nama_kelas ASC");
    $stmtWk->execute([':uid' => $userId]);
    $kelases = $stmtWk->fetchAll();
    $validKelasIds = array_column($kelases, 'id');
    if (!empty($validKelasIds)) {
        $waliKelasId = $validKelasIds[0];
    }
} else {
    $kelases = $pdo->query("SELECT * FROM kelas ORDER BY tingkat ASC, nama_kelas ASC")->fetchAll();
    $validKelasIds = array_column($kelases, 'id');
}

// Filter Options
if (isset($_GET['kelas_id']) && in_array((int) $_GET['kelas_id'], $validKelasIds)) {
    $selectedKelasId = (int) $_GET['kelas_id'];
} else {
    $selectedKelasId = $validKelasIds[0] ?? 0;
}

$rawTipe = strtolower($_GET['tipe'] ?? 'ats');
$selectedTipe = in_array($rawTipe, ['ats', 'pts', 'tengah']) ? 'ats' : 'pas';
$selectedSem = isset($_GET['semester']) && in_array($_GET['semester'], ['Ganjil', 'Genap']) ? $_GET['semester'] : 'Ganjil';
$selectedTahun = isset($_GET['tahun_ajaran']) ? trim($_GET['tahun_ajaran']) : (date('Y') . '/' . (date('Y') + 1));

// PROSES SIMPAN KETIDAKHADIRAN MASSAL 1 KELAS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_mass_absensi') {
    $postSiswaIds = $_POST['siswa_id'] ?? [];
    $postSakit = $_POST['sakit'] ?? [];
    $postIzin = $_POST['izin'] ?? [];
    $postAlpa = $_POST['tanpa_keterangan'] ?? [];
    $postKelasId = (int) ($_POST['kelas_id'] ?? $selectedKelasId);
    $postTipe = trim($_POST['tipe'] ?? $selectedTipe);
    $postSem = trim($_POST['semester'] ?? $selectedSem);
    $postTahun = trim($_POST['tahun_ajaran'] ?? $selectedTahun);

    try {
        $stmtCheckRapor = $pdo->prepare("
            SELECT id FROM rapor_siswa 
            WHERE siswa_id = :sid AND (tipe_rapor = :tipe OR tipe_rapor = :tipe_alt) AND semester = :sem AND tahun_ajaran = :th 
            LIMIT 1
        ");
        $stmtUpdateRapor = $pdo->prepare("
            UPDATE rapor_siswa 
            SET sakit = :sakit, izin = :izin, tanpa_keterangan = :alpa
            WHERE id = :id
        ");
        $stmtInsertRapor = $pdo->prepare("
            INSERT INTO rapor_siswa 
            (siswa_id, kelas_id, tipe_rapor, semester, tahun_ajaran, sakit, izin, tanpa_keterangan)
            VALUES (:sid, :kid, :tipe, :sem, :th, :sakit, :izin, :alpa)
        ");

        $countSaved = 0;
        foreach ($postSiswaIds as $sid) {
            $sid = (int) $sid;
            $s = max(0, (int) ($postSakit[$sid] ?? 0));
            $i = max(0, (int) ($postIzin[$sid] ?? 0));
            $a = max(0, (int) ($postAlpa[$sid] ?? 0));

            $stmtCheckRapor->execute([
                ':sid' => $sid,
                ':tipe' => $postTipe,
                ':tipe_alt' => ($postTipe === 'ats' ? 'pts' : 'aas'),
                ':sem' => $postSem,
                ':th' => $postTahun
            ]);
            $existingId = $stmtCheckRapor->fetchColumn();

            if ($existingId) {
                $stmtUpdateRapor->execute([
                    ':sakit' => $s,
                    ':izin' => $i,
                    ':alpa' => $a,
                    ':id' => $existingId
                ]);
            } else {
                $stmtInsertRapor->execute([
                    ':sid' => $sid,
                    ':kid' => $postKelasId,
                    ':tipe' => $postTipe,
                    ':sem' => $postSem,
                    ':th' => $postTahun,
                    ':sakit' => $s,
                    ':izin' => $i,
                    ':alpa' => $a
                ]);
            }
            $countSaved++;
        }

        redirect("index.php?kelas_id={$postKelasId}&tipe={$postTipe}&semester={$postSem}&tahun_ajaran=" . urlencode($postTahun), 'success', "Berhasil memperbarui data ketidakhadiran {$countSaved} santri untuk kelas ini.");
    } catch (PDOException $e) {
        $errorMsg = 'Gagal menyimpan absensi massal: ' . $e->getMessage();
    }
}

// Ambil Informasi Detail Kelas Terpilih
$currentKelas = null;
if ($selectedKelasId > 0) {
    $stmtK = $pdo->prepare("
        SELECT k.*, u.nama_lengkap AS nama_wali_kelas, gd.nip AS nip_wali_kelas
        FROM kelas k
        LEFT JOIN users u ON k.wali_kelas_id = u.id
        LEFT JOIN guru_detail gd ON (u.id = gd.user_id OR k.wali_kelas_id = gd.user_id)
        WHERE k.id = :kid
    ");
    $stmtK->execute([':kid' => $selectedKelasId]);
    $currentKelas = $stmtK->fetch();
}

// Ambil Total Mapel untuk Kelas Terpilih (Prioritaskan Plotting Guru jika ada, fallback ke total mata_pelajaran aktif)
$totalMapelAktif = 11;
if ($selectedKelasId > 0) {
    $stmtPlotCount = $pdo->prepare("SELECT COUNT(DISTINCT mapel_id) FROM guru_mapel_kelas WHERE kelas_id = :kid");
    $stmtPlotCount->execute([':kid' => $selectedKelasId]);
    $plottedCount = (int) $stmtPlotCount->fetchColumn();

    if ($plottedCount > 0) {
        $totalMapelAktif = $plottedCount;
    } else {
        $totalMapelAktif = (int) $pdo->query("SELECT COUNT(*) FROM mata_pelajaran WHERE status = 'aktif'")->fetchColumn();
        if ($totalMapelAktif <= 0)
            $totalMapelAktif = 11;
    }
}

// Ambil Daftar Siswa di Kelas Terpilih
$siswaList = [];
if ($selectedKelasId > 0) {
    $stmtSiswa = $pdo->prepare("
        SELECT s.*, 
               r.id AS rapor_id,
               r.catatan_wali_kelas,
               r.sakit, r.izin, r.tanpa_keterangan,
               r.kokurikuler_deskripsi,
               r.ekskul_json,
               r.deskripsi_mapel_json,
               (SELECT COUNT(DISTINCT ns.mapel) FROM nilai_siswa ns 
                JOIN mata_pelajaran mp ON ns.mapel = mp.nama_mapel AND mp.status = 'aktif'
                WHERE ns.siswa_id = s.id 
                  AND ns.semester = :sem 
                  AND ns.tahun_ajaran = :th
                  AND (
                      (:tipe_filter = 'ats' AND ns.ats > 0)
                      OR
                      (:tipe_filter_alt != 'ats' AND (ns.nilai_akhir > 0 OR ns.aas > 0))
                  )
               ) AS total_mapel_dinilai,
               (SELECT AVG(IF(:tipe_calc = 'ats', NULLIF(ns.ats, 0), NULLIF(ns.nilai_akhir, 0))) FROM nilai_siswa ns 
                WHERE ns.siswa_id = s.id 
                  AND ns.semester = :sem2 
                  AND ns.tahun_ajaran = :th2) AS rata_rata_nilai
        FROM siswa s
        LEFT JOIN rapor_siswa r ON (r.siswa_id = s.id AND (r.tipe_rapor = :tipe OR r.tipe_rapor = :tipe_alt) AND r.semester = :sem3 AND r.tahun_ajaran = :th3)
        WHERE s.kelas_id = :kid AND s.status = 'aktif'
        ORDER BY s.nama ASC
    ");
    $stmtSiswa->execute([
        ':sem' => $selectedSem,
        ':th' => $selectedTahun,
        ':tipe_filter' => $selectedTipe,
        ':tipe_filter_alt' => $selectedTipe,
        ':tipe_calc' => $selectedTipe,
        ':sem2' => $selectedSem,
        ':th2' => $selectedTahun,
        ':tipe' => $selectedTipe,
        ':tipe_alt' => ($selectedTipe === 'ats' ? 'pts' : 'aas'),
        ':sem3' => $selectedSem,
        ':th3' => $selectedTahun,
        ':kid' => $selectedKelasId
    ]);
    $siswaList = $stmtSiswa->fetchAll();
}

// Hitung Statistik
$totalSiswa = count($siswaList);
$raporLengkap = 0;
foreach ($siswaList as $s) {
    $mapelCount = (int) ($s['total_mapel_dinilai'] ?? 0);
    $isMapelComplete = ($mapelCount >= $totalMapelAktif);
    if ($selectedTipe === 'ats') {
        if ($isMapelComplete) {
            $raporLengkap++;
        }
    } else {
        $hasCatatan = !empty($s['rapor_id']) && !empty($s['catatan_wali_kelas']);
        if ($hasCatatan && $isMapelComplete) {
            $raporLengkap++;
        }
    }
}

$flash = getFlash();
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- HERO HEADER BANNER -->
    <div class="card border-0 rounded-4 shadow-sm mb-4 text-white overflow-hidden"
        style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 45%, #1e3a8a 100%); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
        <div class="card-body p-4 p-md-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded-4 text-white d-flex align-items-center justify-content-center shadow-sm"
                    style="background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.25); width: 58px; height: 58px;">
                    <i class="<?= $selectedTipe === 'ats' ? 'bi bi-file-earmark-text-fill' : 'bi bi-journal-bookmark-fill' ?> fs-2"></i>
                </div>
                <div>
                    <h3 class="fw-black m-0 text-white" style="letter-spacing: -0.5px;">
                        <?= $selectedTipe === 'ats' ? 'Manajemen Rapor ATS (Tengah Semester)' : 'Manajemen Rapor AAS (Akhir Semester)' ?>
                    </h3>
                    <small class="text-white text-opacity-80">
                        <?= $selectedTipe === 'ats'
                            ? 'Penyusunan Rapor <strong>ATS (Tengah Semester)</strong> ringkas & siap cetak untuk pertemuan wali santri'
                            : 'Penyusunan Rapor <strong>AAS (Akhir Semester)</strong> Kurikulum Merdeka terintegrasi Capaian Pembelajaran & Nilai Akhir' ?>
                    </small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="settings.php"
                    class="btn btn-light rounded-pill px-3.5 py-2 fw-bold text-primary shadow-sm d-inline-flex align-items-center gap-2"
                    style="font-size: 0.85rem;">
                    <i class="bi bi-gear-fill"></i> Pengaturan Rapor & TTD
                </a>
            </div>
        </div>
    </div>

    <!-- SUB TABS NAVIGATION WALI KELAS -->
    <ul class="nav nav-tabs mb-4" style="border-bottom: 2px solid #e2e8f0; gap: 8px;">
        <li class="nav-item">
            <a class="nav-link text-secondary fw-bold"
                style="border:none; border-bottom: 3px solid transparent; padding: 0.75rem 1.25rem;"
                href="<?= BASE_URL ?>/pages/wali-kelas/index.php?kelas_id=<?= $selectedKelasId ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>">
                <i class="bi bi-journal-check fs-5 text-primary"></i> Rekap Nilai & Catatan Guru (Pertemuan Ortu)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-secondary fw-bold"
                style="border:none; border-bottom: 3px solid transparent; padding: 0.75rem 1.25rem;"
                href="<?= BASE_URL ?>/pages/wali-kelas/ranking.php?kelas_id=<?= $selectedKelasId ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>">
                <i class="bi bi-trophy-fill fs-5 text-warning"></i> Ranking
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $selectedTipe === 'ats' ? 'active fw-bold text-primary' : 'text-secondary fw-bold' ?>"
                style="border:none; border-bottom: 3px solid <?= $selectedTipe === 'ats' ? '#1e3a8a' : 'transparent' ?>; padding: 0.75rem 1.25rem; <?= $selectedTipe === 'ats' ? 'background:transparent;' : '' ?>"
                href="<?= BASE_URL ?>/pages/rapor/index.php?tipe=ats&kelas_id=<?= $selectedKelasId ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>">
                <i class="bi bi-file-earmark-text-fill fs-5 text-success"></i> Rapor ATS (Tengah Sem)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $selectedTipe !== 'ats' ? 'active fw-bold text-primary' : 'text-secondary fw-bold' ?>"
                style="border:none; border-bottom: 3px solid <?= $selectedTipe !== 'ats' ? '#1e3a8a' : 'transparent' ?>; padding: 0.75rem 1.25rem; <?= $selectedTipe !== 'ats' ? 'background:transparent;' : '' ?>"
                href="<?= BASE_URL ?>/pages/rapor/index.php?tipe=pas&kelas_id=<?= $selectedKelasId ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>">
                <i class="bi bi-journal-bookmark-fill fs-5" style="color: #6366f1;"></i> Rapor AAS (Akhir Sem)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-secondary fw-bold"
                style="border:none; border-bottom: 3px solid transparent; padding: 0.75rem 1.25rem;"
                href="<?= BASE_URL ?>/pages/rapor/settings.php">
                <i class="bi bi-sliders fs-5 text-secondary"></i> Pengaturan Leger & Tanggal
            </a>
        </li>
    </ul>

    <!-- STATISTIC CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 d-flex flex-row align-items-center gap-3"
                style="border: 1px solid #e2e8f0 !important;">
                <div class="rounded-4 text-white d-flex align-items-center justify-content-center shadow-sm flex-shrink-0"
                    style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); width: 48px; height: 48px;">
                    <i class="bi bi-people-fill fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase"
                        style="font-size: 0.7rem; letter-spacing: 0.4px;">Total Siswa Kelas</div>
                    <h4 class="fw-black mb-0 text-dark" style="font-size: 1.45rem;"><?= number_format($totalSiswa) ?>
                        <span class="fs-7 fw-normal text-muted">Santri</span>
                    </h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 d-flex flex-row align-items-center gap-3"
                style="border: 1px solid #e2e8f0 !important;">
                <div class="rounded-4 text-white d-flex align-items-center justify-content-center shadow-sm flex-shrink-0"
                    style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); width: 48px; height: 48px;">
                    <i class="bi bi-check2-all fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase"
                        style="font-size: 0.7rem; letter-spacing: 0.4px;">Rapor Siap Cetak</div>
                    <h4 class="fw-black mb-0 text-success" style="font-size: 1.45rem;">
                        <?= number_format($raporLengkap) ?> <span class="fs-7 fw-normal text-muted">/
                            <?= $totalSiswa ?></span>
                    </h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 d-flex flex-row align-items-center gap-3"
                style="border: 1px solid #e2e8f0 !important;">
                <div class="rounded-4 text-white d-flex align-items-center justify-content-center shadow-sm flex-shrink-0"
                    style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); width: 48px; height: 48px;">
                    <i class="bi bi-award-fill fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase"
                        style="font-size: 0.7rem; letter-spacing: 0.4px;">Tipe Rapor Aktif</div>
                    <h4 class="fw-black mb-0 text-dark" style="font-size: 1.35rem;"><?= $selectedTipe === 'ats' ? 'ATS' : 'AAS' ?>
                        <span class="fs-7 fw-semibold text-primary">(<?= $selectedSem ?>)</span>
                    </h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 d-flex flex-row align-items-center gap-3"
                style="border: 1px solid #e2e8f0 !important;">
                <div class="rounded-4 text-white d-flex align-items-center justify-content-center shadow-sm flex-shrink-0"
                    style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); width: 48px; height: 48px;">
                    <i class="bi bi-person-workspace fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase"
                        style="font-size: 0.7rem; letter-spacing: 0.4px;">Wali Kelas</div>
                    <div class="fw-bold text-dark text-truncate" style="max-width: 160px; font-size: 0.88rem;">
                        <?= htmlspecialchars($currentKelas['nama_wali_kelas'] ?? 'Belum ditentukan') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTER & ACTION CONTROLLER BAR -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white" style="border: 1px solid #e2e8f0 !important;">
        <div class="card-body p-3.5 p-md-4">
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
                <!-- FORM FILTER -->
                <form method="GET" class="row g-2.5 align-items-end flex-grow-1" style="max-width: 780px;">
                    <input type="hidden" name="tipe" value="<?= $selectedTipe ?>">
                    <div class="col-md-5 col-6">
                        <label class="form-label small fw-bold text-secondary mb-1"
                            style="font-size: 0.75rem; letter-spacing: 0.4px;">
                            <i class="bi bi-building me-1"></i> KELAS
                        </label>
                        <select name="kelas_id" class="form-select fw-semibold"
                            style="height: 42px; font-size: 0.85rem;" onchange="this.form.submit()">
                            <?php foreach ($kelases as $k): ?>
                                <option value="<?= $k['id'] ?>" <?= $selectedKelasId == $k['id'] ? 'selected' : '' ?>>
                                    Kelas <?= htmlspecialchars($k['nama_kelas']) ?> (Tingkat
                                    <?= htmlspecialchars($k['tingkat']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3 col-6">
                        <label class="form-label small fw-bold text-secondary mb-1"
                            style="font-size: 0.75rem; letter-spacing: 0.4px;">
                            <i class="bi bi-calendar3 me-1"></i> SEMESTER
                        </label>
                        <select name="semester" class="form-select fw-semibold"
                            style="height: 42px; font-size: 0.85rem;" onchange="this.form.submit()">
                            <option value="Ganjil" <?= $selectedSem === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                            <option value="Genap" <?= $selectedSem === 'Genap' ? 'selected' : '' ?>>Genap</option>
                        </select>
                    </div>

                    <div class="col-md-2 col-6">
                        <label class="form-label small fw-bold text-secondary mb-1"
                            style="font-size: 0.75rem; letter-spacing: 0.4px;">
                            <i class="bi bi-clock-history me-1"></i> THN AJARAN
                        </label>
                        <input type="text" name="tahun_ajaran" class="form-control font-monospace fw-bold"
                            style="height: 42px; font-size: 0.85rem;" value="<?= htmlspecialchars($selectedTahun) ?>"
                            placeholder="2026/2027">
                    </div>

                    <div class="col-md-2 col-6">
                        <button type="submit"
                            class="btn text-white w-100 fw-bold rounded-3 shadow-sm d-inline-flex align-items-center justify-content-center gap-1"
                            style="height: 42px; background: linear-gradient(135deg, #1e3a8a 0%, #1e1b4b 100%); border: none; font-size: 0.86rem;">
                            <i class="bi bi-filter"></i> Filter
                        </button>
                    </div>
                </form>

                <!-- ACTION BUTTONS -->
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <?php if ($totalSiswa > 0): ?>
                        <?php if ($selectedTipe !== 'ats'): ?>
                            <a href="auto_generate.php?kelas_id=<?= $selectedKelasId ?>&tipe=<?= $selectedTipe ?>&semester=<?= $selectedSem ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>"
                                class="btn btn-success rounded-pill px-3.5 fw-bold text-white shadow-sm d-inline-flex align-items-center gap-1.5"
                                style="height: 42px; font-size: 0.84rem; background: linear-gradient(135deg, #059669 0%, #047857 100%); border: none;"
                                onclick="return confirm('Apakah Anda ingin menyinkronkan seluruh deskripsi capaian dari guru pengampu mata pelajaran ke rapor santri di kelas ini?');">
                                <i class="bi bi-arrow-repeat fs-6"></i> Sinkronkan Deskripsi Guru
                            </a>

                            <a href="auto_generate.php?kelas_id=<?= $selectedKelasId ?>&tipe=<?= $selectedTipe ?>&semester=<?= $selectedSem ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>"
                                class="btn btn-outline-primary rounded-pill px-3.5 fw-bold d-inline-flex align-items-center gap-1.5"
                                style="height: 42px; font-size: 0.84rem;"
                                onclick="return confirm('Apakah Anda ingin men-generate otomatis deskripsi capaian kompetensi untuk seluruh siswa di kelas ini berdasarkan nilai & perangkat ajar? Data deskripsi yang belum diisi akan otomatis terisi.');">
                                <i class="bi bi-magic"></i> Generate Otomatis
                            </a>
                        <?php endif; ?>

                        <button type="button"
                            class="btn text-white rounded-pill px-3.5 fw-bold shadow-sm d-inline-flex align-items-center gap-2"
                            data-bs-toggle="modal" data-bs-target="#modalAbsensiMassal"
                            style="height: 42px; font-size: 0.84rem; background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%); border: none;">
                            <i class="bi bi-calendar-check-fill fs-6"></i> Edit Ketidakhadiran
                        </button>

                        <a href="cetak_massal.php?kelas_id=<?= $selectedKelasId ?>&tipe=<?= $selectedTipe ?>&semester=<?= $selectedSem ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>"
                            target="_blank"
                            class="btn text-white rounded-pill px-4 fw-bold shadow-sm d-inline-flex align-items-center gap-2"
                            style="height: 42px; font-size: 0.84rem; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border: none;">
                            <i class="bi bi-printer-fill"></i> Cetak Rapor 1 Kelas
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN STUDENT LIST TABLE -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-4">
        <div
            class="card-header bg-white p-3.5 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h5 class="fw-extrabold text-dark mb-0">
                    <i class="bi bi-card-checklist text-primary me-2"></i> Daftar Rapor Siswa Kelas
                    <?= htmlspecialchars($currentKelas['nama_kelas'] ?? '') ?>
                </h5>
                <p class="text-muted small mb-0">
                    Tipe: <strong><?= $selectedTipe === 'ats' ? 'ATS (Tengah Semester)' : 'AAS (Akhir Semester)' ?></strong> | Semester:
                    <strong><?= $selectedSem ?></strong> | Tahun:
                    <strong><?= htmlspecialchars($selectedTahun) ?></strong>
                </p>
            </div>
            <div>
                <span
                    class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle rounded-pill px-3 py-1.5 font-monospace">
                    Fase <?= htmlspecialchars($currentKelas['fase'] ?? 'D') ?>
                </span>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small fw-bold text-uppercase" style="letter-spacing: 0.5px;">
                        <tr>
                            <th style="width: 50px;" class="ps-3 text-center">No</th>
                            <th>Identitas Peserta Didik</th>
                            <th>NIS / NISN</th>
                            <th class="text-center">Status Nilai Mapel</th>
                            <th class="text-center">
                                <?= $selectedTipe === 'ats' ? 'Rata-Rata ATS' : 'Rata-Rata Nilai Akhir' ?>
                            </th>
                            <th class="text-center">Ketidakhadiran</th>
                            <th class="text-center">Status Rapor</th>
                            <th class="text-end pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($siswaList)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-folder-x fs-1 d-block mb-2 text-secondary"></i>
                                    Tidak ada data siswa aktif di kelas yang dipilih.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($siswaList as $idx => $s):
                                $isLengkap = !empty($s['rapor_id']) && !empty($s['catatan_wali_kelas']);
                                $mapelRatio = ($s['total_mapel_dinilai'] ?? 0) . ' / ' . $totalMapelAktif . ' Mapel';
                                $avg = (float) ($s['rata_rata_nilai'] ?? 0);
                                ?>
                                <tr>
                                    <td class="ps-3 text-center fw-bold text-muted font-monospace"><?= $idx + 1 ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <?php if (!empty($s['foto']) && file_exists(__DIR__ . '/../../uploads/siswa/' . $s['foto'])): ?>
                                                <img src="<?= BASE_URL ?>/uploads/siswa/<?= htmlspecialchars($s['foto']) ?>"
                                                    alt="<?= htmlspecialchars($s['nama']) ?>"
                                                    class="rounded-circle object-fit-cover border flex-shrink-0 shadow-xs"
                                                    style="width: 40px; height: 40px;">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center border flex-shrink-0 shadow-xs"
                                                    style="width: 40px; height: 40px; font-size: 0.95rem;">
                                                    <?= strtoupper(substr($s['nama'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong
                                                    class="d-block text-dark fw-bold"><?= htmlspecialchars($s['nama']) ?></strong>
                                                <small
                                                    class="text-muted"><?= $s['jenis_kelamin'] === 'P' ? 'Perempuan' : 'Laki-laki' ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span
                                            class="font-monospace text-dark fw-semibold"><?= htmlspecialchars($s['nis'] ?: '—') ?></span>
                                        <?php if (!empty($s['nisn'])): ?>
                                            <small class="text-muted d-block font-monospace">NISN:
                                                <?= htmlspecialchars($s['nisn']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ((int) $s['total_mapel_dinilai'] >= $totalMapelAktif): ?>
                                            <span
                                                class="badge bg-success bg-opacity-10 text-success border border-success-subtle rounded-pill px-2.5 py-1">
                                                <i class="bi bi-check-circle me-1"></i><?= $mapelRatio ?>
                                            </span>
                                        <?php elseif ((int) $s['total_mapel_dinilai'] > 0): ?>
                                            <span
                                                class="badge bg-warning bg-opacity-10 text-warning border border-warning-subtle rounded-pill px-2.5 py-1">
                                                <i class="bi bi-clock-history me-1"></i><?= $mapelRatio ?>
                                            </span>
                                        <?php else: ?>
                                            <span
                                                class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle rounded-pill px-2.5 py-1">
                                                <i class="bi bi-exclamation-triangle me-1"></i>Belum Dinilai
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td
                                        class="text-center font-monospace fw-bold <?= $avg >= 85 ? 'text-success' : ($avg >= 75 ? 'text-primary' : 'text-danger') ?>">
                                        <?= $avg > 0 ? number_format($avg, 1) : '—' ?>
                                    </td>
                                    <td class="text-center small">
                                        <span class="badge bg-light text-secondary border px-2 py-1"
                                            title="Sakit / Izin / Alpha">
                                            S: <?= (int) ($s['sakit'] ?? 0) ?> | I: <?= (int) ($s['izin'] ?? 0) ?> | A:
                                            <?= (int) ($s['tanpa_keterangan'] ?? 0) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $hasCatatan = !empty($s['rapor_id']) && !empty($s['catatan_wali_kelas']);
                                        $mapelCount = (int) ($s['total_mapel_dinilai'] ?? 0);
                                        $isMapelComplete = ($mapelCount >= $totalMapelAktif);
                                        $isSiapCetak = ($selectedTipe === 'ats') ? $isMapelComplete : ($hasCatatan && $isMapelComplete);
                                        $isBelumLengkap = ($selectedTipe === 'ats') ? ($mapelCount > 0 && !$isMapelComplete) : ($hasCatatan && !$isMapelComplete);
                                        ?>
                                        <?php if ($isSiapCetak): ?>
                                            <span class="badge bg-success text-white rounded-pill px-2.5 py-1">
                                                <i class="bi bi-check2-circle me-1"></i> Siap Cetak
                                            </span>
                                        <?php elseif ($isBelumLengkap): ?>
                                            <span
                                                class="badge bg-warning bg-opacity-15 text-dark border border-warning-subtle rounded-pill px-2.5 py-1"
                                                title="Nilai mapel baru <?= $mapelCount ?>/<?= $totalMapelAktif ?>">
                                                <i class="bi bi-exclamation-circle me-1"></i> Nilai Belum Lengkap
                                            </span>
                                        <?php else: ?>
                                            <span
                                                class="badge bg-secondary bg-opacity-10 text-secondary border rounded-pill px-2.5 py-1">
                                                <i class="bi bi-pencil me-1"></i> Draf / Belum Final
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group btn-group-sm">
                                            <a href="edit.php?siswa_id=<?= $s['id'] ?>&tipe=<?= $selectedTipe ?>&semester=<?= $selectedSem ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>"
                                                class="btn btn-outline-primary" title="Kelola & Sesuaikan Rapor Siswa">
                                                <i class="bi bi-pencil-square me-1"></i> Isi Rapor
                                            </a>
                                            <a href="cetak.php?siswa_id=<?= $s['id'] ?>&tipe=<?= $selectedTipe ?>&semester=<?= $selectedSem ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>"
                                                target="_blank" class="btn btn-outline-danger"
                                                title="Pratinjau / Cetak Rapor PDF">
                                                <i class="bi bi-printer-fill"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL EDIT MASSAL KETIDAKHADIRAN 1 KELAS -->
<div class="modal fade" id="modalAbsensiMassal" tabindex="-1" aria-labelledby="modalAbsensiMassalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" style="max-width: 860px;">
        <form method="POST" action="" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" style="max-height: 90vh; display: flex; flex-direction: column;">
            <input type="hidden" name="action" value="save_mass_absensi">
            <input type="hidden" name="kelas_id" value="<?= $selectedKelasId ?>">
            <input type="hidden" name="tipe" value="<?= $selectedTipe ?>">
            <input type="hidden" name="semester" value="<?= $selectedSem ?>">
            <input type="hidden" name="tahun_ajaran" value="<?= htmlspecialchars($selectedTahun) ?>">

            <div class="modal-header text-white p-3.5 flex-shrink-0"
                style="background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);">
                <div>
                    <h5 class="modal-title fw-bold text-white mb-0" id="modalAbsensiMassalLabel">
                        <i class="bi bi-calendar-check-fill me-2"></i> Edit Ketidakhadiran Santri
                    </h5>
                    <small class="text-white-50">
                        Kelas <?= htmlspecialchars($currentKelas['nama_kelas'] ?? '') ?> | Tipe: <span
                            class="badge bg-white text-dark text-uppercase"><?= $selectedTipe ?></span> | Semester
                        <?= $selectedSem ?> <?= htmlspecialchars($selectedTahun) ?>
                    </small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>

            <div class="modal-body p-3.5 bg-light flex-grow-1 overflow-y-auto" style="max-height: calc(90vh - 145px);">
                <div
                    class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 bg-white p-2.5 rounded-3 border">
                    <span class="text-muted small">
                        <i class="bi bi-info-circle text-primary me-1"></i>
                        Input jumlah hari Sakit (S), Izin (I), dan Alpa (A). Data langsung tersinkron ke rapor &
                        lembar cetak.
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3"
                        onclick="resetAllAbsenZero()">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Set Semua 0 (Hadir Penuh)
                    </button>
                </div>

                <div class="card border-0 shadow-sm rounded-3 overflow-hidden bg-white">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0"
                            style="font-size: 0.88rem;">
                            <thead class="bg-light text-muted small fw-bold text-uppercase sticky-top"
                                style="z-index: 2;">
                                <tr>
                                    <th style="width: 45px;" class="text-center">No</th>
                                    <th>Nama Santri</th>
                                    <th style="width: 100px;" class="text-center text-primary">Sakit (S)</th>
                                    <th style="width: 100px;" class="text-center text-info">Izin (I)</th>
                                    <th style="width: 100px;" class="text-center text-danger">Alpa (A)</th>
                                    <th style="width: 95px;" class="text-center">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($siswaList)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">Tidak ada data santri pada
                                            kelas ini.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($siswaList as $idx => $s):
                                        $sakitVal = (int) ($s['sakit'] ?? 0);
                                        $izinVal = (int) ($s['izin'] ?? 0);
                                        $alpaVal = (int) ($s['tanpa_keterangan'] ?? 0);
                                        $totVal = $sakitVal + $izinVal + $alpaVal;
                                        ?>
                                        <tr class="absen-row">
                                            <td class="text-center text-muted fw-bold"><?= $idx + 1 ?></td>
                                            <td>
                                                <input type="hidden" name="siswa_id[]" value="<?= (int) $s['id'] ?>">
                                                <strong
                                                    class="text-dark d-block"><?= htmlspecialchars($s['nama']) ?></strong>
                                                <small class="text-muted font-monospace">NIS:
                                                    <?= htmlspecialchars($s['nis'] ?: '-') ?></small>
                                            </td>
                                            <td class="text-center">
                                                <input type="number" min="0" max="365"
                                                    name="sakit[<?= (int) $s['id'] ?>]" value="<?= (int) $sakitVal ?>"
                                                    class="form-control form-control-sm text-center font-monospace fw-bold input-sakit mx-auto shadow-none"
                                                    style="width: 72px; font-size: 0.95rem; border: 1.5px solid #cbd5e1; border-radius: 6px;"
                                                    oninput="calculateTotalAbsen(this.closest('tr'))">
                                            </td>
                                            <td class="text-center">
                                                <input type="number" min="0" max="365"
                                                    name="izin[<?= (int) $s['id'] ?>]" value="<?= (int) $izinVal ?>"
                                                    class="form-control form-control-sm text-center font-monospace fw-bold input-izin mx-auto shadow-none"
                                                    style="width: 72px; font-size: 0.95rem; border: 1.5px solid #cbd5e1; border-radius: 6px;"
                                                    oninput="calculateTotalAbsen(this.closest('tr'))">
                                            </td>
                                            <td class="text-center">
                                                <input type="number" min="0" max="365"
                                                    name="tanpa_keterangan[<?= (int) $s['id'] ?>]"
                                                    value="<?= (int) $alpaVal ?>"
                                                    class="form-control form-control-sm text-center font-monospace fw-bold input-alpa mx-auto shadow-none"
                                                    style="width: 72px; font-size: 0.95rem; border: 1.5px solid #cbd5e1; border-radius: 6px;"
                                                    oninput="calculateTotalAbsen(this.closest('tr'))">
                                            </td>
                                            <td class="text-center">
                                                <span
                                                    class="badge <?= $totVal === 0 ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger' ?> total-absen-badge font-monospace px-2 py-1">
                                                    <?= $totVal ?> Hari
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-white p-3 border-top flex-shrink-0 d-flex align-items-center justify-content-between">
                <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">
                    Batal
                </button>
                <button type="submit"
                    class="btn btn-primary rounded-pill px-4.5 py-2 fw-bold shadow-sm d-inline-flex align-items-center gap-2"
                    style="background: linear-gradient(135deg, #0f766e 0%, #115e59 100%); border: none;">
                    <i class="bi bi-check2-circle fs-5"></i> Simpan Ketidakhadiran 1 Kelas
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function calculateTotalAbsen(row) {
        if (!row) return;
        var inSakit = row.querySelector('.input-sakit');
        var inIzin = row.querySelector('.input-izin');
        var inAlpa = row.querySelector('.input-alpa');
        var s = inSakit ? parseInt(inSakit.value, 10) || 0 : 0;
        var i = inIzin ? parseInt(inIzin.value, 10) || 0 : 0;
        var a = inAlpa ? parseInt(inAlpa.value, 10) || 0 : 0;
        var totalEl = row.querySelector('.total-absen-badge');
        var tot = s + i + a;
        if (totalEl) {
            totalEl.innerText = tot + ' Hari';
            if (tot === 0) {
                totalEl.className = 'badge bg-success bg-opacity-10 text-success total-absen-badge font-monospace px-2 py-1';
            } else {
                totalEl.className = 'badge bg-danger bg-opacity-10 text-danger total-absen-badge font-monospace px-2 py-1';
            }
        }
    }

    function resetAllAbsenZero() {
        if (confirm('Apakah Anda yakin ingin mengatur seluruh nilai ketidakhadiran santri di kelas ini menjadi 0 (Hadir Penuh)?')) {
            var rows = document.querySelectorAll('#modalAbsensiMassal tbody tr');
            for (var r = 0; r < rows.length; r++) {
                var inps = rows[r].querySelectorAll('input[type="number"]');
                for (var j = 0; j < inps.length; j++) {
                    inps[j].value = 0;
                }
                calculateTotalAbsen(rows[r]);
            }
        }
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>