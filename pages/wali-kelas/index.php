<?php
/**
 * MODUL WALI KELAS - REKAP NILAI & CATATAN GURU
 * Kurikulum Merdeka - Islamic School of Minhaj Al-Ilmi
 */
$pageTitle = 'Wali Kelas - Rekap Nilai & Catatan Santri';
$activePage = 'wali-kelas';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('wali_kelas');

$pdo = getConnection();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$userRole = strtolower($_SESSION['role'] ?? '');
$isGuruRole = ($userRole === 'guru');

// Auto-Migration Self Healing jika database belum diperbarui
require_once __DIR__ . '/../../config/db_auto_sync.php';
runDatabaseAutoSync($pdo);

// 1. Cek Kelas yang diampu oleh Wali Kelas ini
$stmtWk = $pdo->prepare("SELECT * FROM kelas WHERE wali_kelas_id = :uid ORDER BY tingkat ASC, nama_kelas ASC");
$stmtWk->execute([':uid' => $userId]);
$myWaliClasses = $stmtWk->fetchAll();

if ($userRole === 'admin') {
    $stmtAll = $pdo->query("
        SELECT k.*, u.nama_lengkap AS nama_wali_kelas 
        FROM kelas k 
        LEFT JOIN users u ON k.wali_kelas_id = u.id 
        ORDER BY k.tingkat ASC, k.nama_kelas ASC
    ");
    $waliClasses = $stmtAll->fetchAll();
} else {
    if (!empty($myWaliClasses)) {
        $waliClasses = $myWaliClasses;
    } else {
        $stmtAll = $pdo->query("
            SELECT k.*, u.nama_lengkap AS nama_wali_kelas 
            FROM kelas k 
            LEFT JOIN users u ON k.wali_kelas_id = u.id 
            ORDER BY k.tingkat ASC, k.nama_kelas ASC
        ");
        $waliClasses = $stmtAll->fetchAll();
    }
}
$validKelasIds = array_column($waliClasses, 'id');

// 2. Filter Request
$selectedKelasId = 0;
if (isset($_GET['kelas_id']) && in_array((int) $_GET['kelas_id'], $validKelasIds)) {
    $selectedKelasId = (int) $_GET['kelas_id'];
} else {
    $selectedKelasId = $validKelasIds[0] ?? 0;
}

$selectedSem = isset($_GET['semester']) && in_array($_GET['semester'], ['Ganjil', 'Genap']) ? $_GET['semester'] : 'Ganjil';
$selectedTahun = isset($_GET['tahun_ajaran']) ? trim($_GET['tahun_ajaran']) : (date('Y') . '/' . (date('Y') + 1));
$selectedMapelFilter = isset($_GET['filter_mapel']) ? trim($_GET['filter_mapel']) : 'all';
$selectedSumatif = isset($_GET['filter_sumatif']) && in_array($_GET['filter_sumatif'], ['all', 's1', 's2', 's3', 's4', 'ats', 'aas']) ? $_GET['filter_sumatif'] : 'all';

// 3. Ambil Detail Kelas Terpilih
$currentKelas = null;
if ($selectedKelasId > 0) {
    $stmtK = $pdo->prepare("
        SELECT k.*, u.nama_lengkap AS nama_wali_kelas, gd.nip AS nip_wali_kelas
        FROM kelas k
        LEFT JOIN users u ON k.wali_kelas_id = u.id
        LEFT JOIN guru_detail gd ON u.id = gd.user_id
        WHERE k.id = :kid
    ");
    $stmtK->execute([':kid' => $selectedKelasId]);
    $currentKelas = $stmtK->fetch();
}

// 4. Ambil Daftar Mata Pelajaran yang diajarkan di kelas ini
$listMapel = [];
if ($selectedKelasId > 0) {
    $stmtMapel = $pdo->prepare("
        SELECT m.id AS mapel_id, m.nama_mapel, m.kelompok, 
               GROUP_CONCAT(DISTINCT u.nama_lengkap SEPARATOR ', ') AS nama_guru_pengampu
        FROM guru_mapel_kelas gmk
        JOIN mata_pelajaran m ON gmk.mapel_id = m.id
        LEFT JOIN users u ON gmk.user_id = u.id
        WHERE gmk.kelas_id = :kid
        GROUP BY m.nama_mapel, m.kelompok
        ORDER BY m.kelompok ASC, m.nama_mapel ASC
    ");
    $stmtMapel->execute([':kid' => $selectedKelasId]);
    $rawMapels = $stmtMapel->fetchAll();

    // Fallback jika belum di-plotting tapi sudah ada nilai
    if (empty($rawMapels)) {
        $stmtM2 = $pdo->prepare("
            SELECT DISTINCT mapel AS nama_mapel, 'Umum' AS kelompok, '-' AS nama_guru_pengampu 
            FROM nilai_siswa 
            WHERE kelas_id = :kid
        ");
        $stmtM2->execute([':kid' => $selectedKelasId]);
        $rawMapels = $stmtM2->fetchAll();
    }

    // Pastikan 100% unik berdasarkan nama_mapel (mencegah kolom duplikat)
    $seenNames = [];
    foreach ($rawMapels as $rm) {
        $cleanName = trim($rm['nama_mapel']);
        $upperKey = strtoupper($cleanName);
        if (!in_array($upperKey, $seenNames) && $cleanName !== '') {
            $seenNames[] = $upperKey;
            $rm['nama_mapel'] = $cleanName;
            $listMapel[] = $rm;
        }
    }
}

// 5. Ambil Daftar Bab / Lingkup Materi Sumatif untuk kelas ini
$mapelMateriMap = [];
if ($selectedKelasId > 0) {
    $stmtMat = $pdo->prepare("
        SELECT mapel, materi_s1, materi_s2, materi_s3, materi_s4 
        FROM materi_sumatif 
        WHERE kelas_id = :kid AND semester = :sem AND tahun_ajaran = :th
    ");
    $stmtMat->execute([
        ':kid' => $selectedKelasId,
        ':sem' => $selectedSem,
        ':th' => $selectedTahun
    ]);
    foreach ($stmtMat->fetchAll() as $mat) {
        $mapelMateriMap[$mat['mapel']] = $mat;
    }
}

// 6. Ambil Daftar Siswa dan Seluruh Nilai
$listSiswa = [];
$statsTotalSiswa = 0;
$statsAvgKelas = 0;
$statsSantriCatatan = 0;

if ($selectedKelasId > 0) {
    $stmtSiswa = $pdo->prepare("
        SELECT s.id AS siswa_id, s.nis, s.nisn, s.nama, s.jenis_kelamin, s.foto,
               k.nama_kelas
        FROM siswa s
        JOIN kelas k ON s.kelas_id = k.id
        WHERE s.kelas_id = :kid AND s.status = 'aktif'
        ORDER BY s.nama ASC
    ");
    $stmtSiswa->execute([':kid' => $selectedKelasId]);
    $rawSiswa = $stmtSiswa->fetchAll();
    $statsTotalSiswa = count($rawSiswa);

    // Ambil seluruh nilai siswa di kelas ini
    $stmtAllNilai = $pdo->prepare("
        SELECT ns.*, u.nama_lengkap AS nama_guru_input
        FROM nilai_siswa ns
        LEFT JOIN users u ON ns.user_id = u.id
        WHERE (ns.kelas_id = :kid OR ns.siswa_id IN (SELECT id FROM siswa WHERE kelas_id = :kid2))
          AND (TRIM(LOWER(ns.semester)) = LOWER(:sem) OR ns.semester IS NULL OR TRIM(ns.semester) = '')
          AND (TRIM(LOWER(ns.tahun_ajaran)) = LOWER(:th) OR ns.tahun_ajaran IS NULL OR TRIM(ns.tahun_ajaran) = '')
    ");
    $stmtAllNilai->execute([
        ':kid' => $selectedKelasId,
        ':kid2' => $selectedKelasId,
        ':sem' => $selectedSem,
        ':th' => $selectedTahun
    ]);
    $rawNilai = $stmtAllNilai->fetchAll();

    // Grouping nilai by siswa_id — gunakan key trimmed + normalized fallback
    $nilaiBySiswa = [];      // [siswa_id][trimmed_mapel] = row
    $nilaiBySiswaNorm = [];  // [siswa_id][lowercase_trimmed_mapel] = row (fallback)
    $totalAllNA = 0;
    $countAllNA = 0;

    foreach ($rawNilai as $n) {
        $sid = (int) $n['siswa_id'];
        $mapelKey = trim($n['mapel']);
        $cleanAlpha = preg_replace('/[^a-z0-9]/', '', mb_strtolower($mapelKey));
        $nilaiBySiswa[$sid][$mapelKey] = $n;
        $nilaiBySiswaNorm[$sid][mb_strtolower($mapelKey)] = $n;
        $nilaiBySiswaNorm[$sid][$cleanAlpha] = $n;

        // Map Fiqih & Agama aliases directly
        if (in_array($cleanAlpha, ['fiqih', 'fikih', 'fiqh', 'fiqihvii', 'fiqihviii', 'fiqihix', 'fiqih7', 'fiqih8', 'fiqih9', 'paifiqih'])) {
            $nilaiBySiswaNorm[$sid]['fiqih'] = $n;
            $nilaiBySiswaNorm[$sid]['fikih'] = $n;
            $nilaiBySiswaNorm[$sid]['fiqh'] = $n;
        }

        if ((float) $n['nilai_akhir'] > 0) {
            $totalAllNA += (float) $n['nilai_akhir'];
            $countAllNA++;
        }
    }

    if ($countAllNA > 0) {
        $statsAvgKelas = round($totalAllNA / $countAllNA, 1);
    }

    // Helper: cari grade by mapel name (exact match -> normalized -> alias -> substring fallback)
    function findGrade($grades, $normGrades, $mapelName)
    {
        if (empty($grades) && empty($normGrades))
            return [];
        $trimmed = trim($mapelName);
        if (isset($grades[$trimmed]))
            return $grades[$trimmed];
        $lower = mb_strtolower($trimmed);
        if (isset($normGrades[$lower]))
            return $normGrades[$lower];

        // 1. Alphanumeric match (hapus spasi, simbol, tanda minus)
        $cleanAlpha = preg_replace('/[^a-z0-9]/', '', $lower);
        if (isset($normGrades[$cleanAlpha])) {
            return $normGrades[$cleanAlpha];
        }
        foreach ($normGrades as $k => $v) {
            if (preg_replace('/[^a-z0-9]/', '', $k) === $cleanAlpha) {
                return $v;
            }
        }

        // 2. Alias / Variasi Penamaan Khusus Mapel Agama & Umum
        $aliases = [
            'fiqih' => ['fiqih', 'fikih', 'fiqh', 'fiqihvii', 'fiqihviii', 'fiqihix', 'fiqih7', 'fiqih8', 'fiqih9', 'paifiqih'],
            'fikih' => ['fiqih', 'fikih', 'fiqh', 'fiqihvii', 'fiqihviii', 'fiqihix', 'fiqih7', 'fiqih8', 'fiqih9', 'paifiqih'],
            'aqidah' => ['aqidah', 'akidah', 'aqidahakhlak', 'akidahakhlak'],
            'akidah' => ['aqidah', 'akidah', 'aqidahakhlak', 'akidahakhlak'],
            'adabakhlak' => ['adabakhlak', 'adab', 'akhlak', 'akhlaq'],
            'alquran' => ['alquran', 'quran', 'alquranhadits', 'quranhadits', 'tahfidz'],
            'tahfidz' => ['tahfidz', 'tahfidzulquran', 'alquran', 'quran'],
            'bahasaindonesia' => ['bahasaindonesia', 'bindo', 'bindonesia'],
            'bahasainggris' => ['bahasainggris', 'bing', 'binggris'],
            'bahasaarab' => ['bahasaarab', 'barab'],
            'pjok' => ['pjok', 'penjas', 'penjaskes', 'olahraga'],
            'ppkn' => ['ppkn', 'pancasila', 'pendidikanpancasila', 'pkn'],
            'ipa' => ['ipa', 'ilmupengetahuanalam'],
            'ips' => ['ips', 'ilmupengetahuansosial'],
            'pai' => ['pai', 'pendidikanagamaislam', 'agamaislam', 'pendidikanagamaislambudipekerti']
        ];

        foreach ($aliases as $mainKey => $varList) {
            if (in_array($cleanAlpha, $varList)) {
                foreach ($normGrades as $k => $v) {
                    $kAlpha = preg_replace('/[^a-z0-9]/', '', $k);
                    if (in_array($kAlpha, $varList)) {
                        return $v;
                    }
                }
            }
        }

        // 3. Containment fallback (hanya jika string >= 5 karakter agar tidak salah cocok)
        if (strlen($cleanAlpha) >= 5) {
            foreach ($normGrades as $k => $v) {
                $kAlpha = preg_replace('/[^a-z0-9]/', '', $k);
                if (strlen($kAlpha) >= 5) {
                    if (strpos($kAlpha, $cleanAlpha) !== false || strpos($cleanAlpha, $kAlpha) !== false) {
                        return $v;
                    }
                }
            }
        }

        return [];
    }

    // Bangun list siswa terstruktur
    foreach ($rawSiswa as $s) {
        $sid = $s['siswa_id'];
        $grades = $nilaiBySiswa[$sid] ?? [];
        $normGrades = $nilaiBySiswaNorm[$sid] ?? [];

        $sumNA = 0;
        $cntNA = 0;
        $hasNoteCount = 0;

        foreach ($grades as $mp => $g) {
            if ((float) $g['nilai_akhir'] > 0) {
                $sumNA += (float) $g['nilai_akhir'];
                $cntNA++;
            }
            if (!empty(trim($g['catatan'] ?? '')) || !empty(trim($g['catatan_s1'] ?? '')) || !empty(trim($g['catatan_s2'] ?? '')) || !empty(trim($g['catatan_s3'] ?? '')) || !empty(trim($g['catatan_s4'] ?? ''))) {
                $hasNoteCount++;
            }
        }

        $avgSiswa = $cntNA > 0 ? round($sumNA / $cntNA, 1) : 0;
        if ($hasNoteCount > 0) {
            $statsSantriCatatan++;
        }

        $listSiswa[] = [
            'siswa_id' => $s['siswa_id'],
            'nis' => $s['nis'],
            'nisn' => $s['nisn'],
            'nama' => $s['nama'],
            'jk' => $s['jenis_kelamin'],
            'foto' => $s['foto'],
            'grades' => $grades,
            'norm_grades' => $normGrades,
            'avg_na' => $avgSiswa,
            'total_mapel_dinilai' => $cntNA,
            'has_note_count' => $hasNoteCount
        ];
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    /* ===== MODERN WALI KELAS DASHBOARD & GRADE VIEWER ===== */
    .wali-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 45%, #1e3a8a 100%);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 20px;
        color: #ffffff;
        padding: 1.75rem 2rem;
        box-shadow: 0 14px 35px -8px rgba(15, 23, 42, 0.25);
        position: relative;
        overflow: hidden;
    }

    .wali-hero::after {
        content: '';
        position: absolute;
        right: -30px;
        bottom: -30px;
        width: 220px;
        height: 220px;
        background: radial-gradient(circle, rgba(99, 102, 241, 0.2) 0%, rgba(255, 255, 255, 0) 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .stat-box-modern {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 1.25rem 1.4rem;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
        transition: all 0.25s ease;
    }

    .stat-box-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
        border-color: #93c5fd;
    }

    .nav-tabs-wali {
        border-bottom: 2px solid #e2e8f0;
        gap: 8px;
    }

    .nav-tabs-wali .nav-link {
        border: none;
        border-bottom: 3px solid transparent;
        color: #64748b;
        font-weight: 700;
        font-size: 0.92rem;
        padding: 0.75rem 1.25rem;
        border-radius: 0;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }

    .nav-tabs-wali .nav-link:hover {
        color: #1e3a8a;
        border-bottom-color: #bfdbfe;
    }

    .nav-tabs-wali .nav-link.active {
        color: #1e3a8a;
        border-bottom-color: #1e3a8a;
        background: transparent;
    }

    .santri-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        transition: all 0.2s ease;
        overflow: hidden;
    }

    .santri-card:hover {
        border-color: #93c5fd;
        box-shadow: 0 10px 25px -5px rgba(30, 58, 138, 0.08);
    }

    .pill-score {
        font-weight: 800;
        font-size: 0.95rem;
        padding: 0.25rem 0.65rem;
        border-radius: 8px;
        display: inline-block;
    }

    .pill-score-na {
        background: #0f172a;
        color: #ffffff;
        font-weight: 900;
    }

    .table-rekap-mapel th {
        background: #0f172a;
        color: #ffffff;
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 0.75rem 0.6rem;
    }

    .table-rekap-mapel td {
        padding: 0.65rem 0.6rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }

    .note-quote-box {
        background: #f8fafc;
        border-left: 3.5px solid #2563eb;
        border-radius: 4px 8px 8px 4px;
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
        color: #334155;
        margin-top: 4px;
    }

    .badge-materi {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
        font-size: 0.7rem;
        font-weight: 700;
        padding: 0.2rem 0.5rem;
        border-radius: 6px;
    }

    /* MOBILE OPTIMIZATIONS (<768px) */
    @media (max-width: 767.98px) {
        .wali-hero {
            padding: 1.25rem !important;
            border-radius: 16px !important;
        }

        .nav-tabs-wali {
            flex-wrap: nowrap !important;
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 4px;
        }

        .nav-tabs-wali .nav-link {
            white-space: nowrap !important;
            font-size: 0.82rem !important;
            padding: 0.6rem 1rem !important;
        }

        .table-responsive {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .table-bordered th:nth-child(1),
        .table-bordered td:nth-child(1) {
            position: sticky;
            left: 0;
            z-index: 2;
            background: #ffffff;
            box-shadow: 1px 0 0 #e2e8f0;
        }

        .table-bordered th:nth-child(3),
        .table-bordered td:nth-child(3) {
            position: sticky;
            left: 45px;
            z-index: 2;
            background: #ffffff;
            box-shadow: 2px 0 6px rgba(0, 0, 0, 0.05);
            min-width: 140px !important;
            max-width: 160px !important;
        }

        .table-bordered thead th:nth-child(1),
        .table-bordered thead th:nth-child(3) {
            z-index: 4;
            background: #0f172a;
            color: #ffffff;
        }
    }
</style>

<!-- ALERT JIKA BUKAN WALI KELAS -->
<?php if ($isGuruRole && empty($waliClasses)): ?>
    <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4 mb-4 d-flex align-items-center gap-3">
        <div class="p-3 bg-warning bg-opacity-20 rounded-circle text-warning flex-shrink-0">
            <i class="bi bi-person-exclamation fs-1"></i>
        </div>
        <div>
            <h5 class="fw-bold text-dark mb-1">Penugasan Wali Kelas Belum Ditemukan</h5>
            <p class="text-muted small mb-0">
                Akun Guru Anda (<strong><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Guru') ?></strong>) belum
                ditetapkan sebagai Wali Kelas untuk rombongan belajar manapun.
                Silakan hubungi Administrator atau Bagian Akademik untuk mengatur penugasan Anda pada menu <strong>Data
                    Kelas</strong>.
            </p>
        </div>
    </div>
<?php else: ?>

    <!-- HERO BANNER WALI KELAS -->
    <div class="wali-hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded-4 text-white d-flex align-items-center justify-content-center shadow-sm"
                    style="background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.25); width: 62px; height: 62px;">
                    <i class="bi bi-person-badge-fill fs-2"></i>
                </div>
                <div>
                    <h3 class="fw-black m-0 text-white" style="letter-spacing: -0.5px;">
                        Kelas <?= htmlspecialchars($currentKelas['nama_kelas'] ?? '—') ?>
                    </h3>
                    <small class="text-white text-opacity-80">
                        Wali Kelas:
                        <strong><?= htmlspecialchars($currentKelas['nama_wali_kelas'] ?? $_SESSION['nama_lengkap']) ?></strong>
                        &bull; Semester <?= htmlspecialchars($selectedSem) ?>     <?= htmlspecialchars($selectedTahun) ?>
                    </small>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= BASE_URL ?>/pages/wali-kelas/print-rekap.php?kelas_id=<?= $selectedKelasId ?>&filter_mapel=<?= urlencode($selectedMapelFilter) ?>&filter_sumatif=<?= urlencode($selectedSumatif) ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>"
                    target="_blank"
                    class="btn btn-light text-primary fw-bold px-3.5 py-2 rounded-pill shadow-sm d-flex align-items-center gap-2"
                    style="font-size: 0.85rem;">
                    <i class="bi bi-printer-fill text-primary"></i> Cetak Rekap (PDF)
                </a>
                <a href="<?= BASE_URL ?>/pages/rapor/index.php?kelas_id=<?= $selectedKelasId ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>"
                    class="btn fw-bold px-3.5 py-2 rounded-pill shadow-sm d-flex align-items-center gap-2 text-white"
                    style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); font-size: 0.85rem; border: none;">
                    <i class="bi bi-file-earmark-person-fill"></i> Buka Modul Rapor
                </a>
            </div>
        </div>
    </div>

    <!-- SUB TABS NAVIGATION -->
    <ul class="nav nav-tabs nav-tabs-wali mb-4">
        <li class="nav-item">
            <a class="nav-link active"
                href="<?= BASE_URL ?>/pages/wali-kelas/index.php?kelas_id=<?= $selectedKelasId ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>">
                <i class="bi bi-journal-check fs-5 text-primary"></i> Rekap Nilai & Catatan Guru (Pertemuan Ortu)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link"
                href="<?= BASE_URL ?>/pages/wali-kelas/ranking.php?kelas_id=<?= $selectedKelasId ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>">
                <i class="bi bi-trophy-fill fs-5 text-warning"></i> Ranking
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link"
                href="<?= BASE_URL ?>/pages/rapor/index.php?tipe=ats&kelas_id=<?= $selectedKelasId ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>">
                <i class="bi bi-file-earmark-text-fill fs-5 text-success"></i> Rapor ATS (Tengah Sem)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link"
                href="<?= BASE_URL ?>/pages/rapor/index.php?tipe=pas&kelas_id=<?= $selectedKelasId ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>">
                <i class="bi bi-journal-bookmark-fill fs-5" style="color: #6366f1;"></i> Rapor AAS (Akhir Sem)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?= BASE_URL ?>/pages/rapor/settings.php">
                <i class="bi bi-sliders fs-5 text-secondary"></i> Pengaturan Leger & Tanggal
            </a>
        </li>
    </ul>

    <!-- STATISTIK RINGKAS KELAS -->
    <div class="row g-3 mb-4">
        <div class="col-md-4 col-sm-6">
            <div class="stat-box-modern d-flex align-items-center gap-3">
                <div class="rounded-4 text-white d-flex align-items-center justify-content-center shadow-sm flex-shrink-0"
                    style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); width:52px; height:52px;">
                    <i class="bi bi-people-fill fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-bold text-uppercase"
                        style="letter-spacing: 0.5px; font-size: 0.72rem;">Santri Binaan</span>
                    <h4 class="fw-black text-dark m-0" style="font-size: 1.55rem;"><?= $statsTotalSiswa ?> <span
                            class="fs-6 fw-semibold text-muted">Santri</span></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="stat-box-modern d-flex align-items-center gap-3">
                <div class="rounded-4 text-white d-flex align-items-center justify-content-center shadow-sm flex-shrink-0"
                    style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); width:52px; height:52px;">
                    <i class="bi bi-graph-up-arrow fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-bold text-uppercase"
                        style="letter-spacing: 0.5px; font-size: 0.72rem;">Rata-rata Nilai Kelas</span>
                    <h4 class="fw-black text-dark m-0" style="font-size: 1.55rem;">
                        <?= $statsAvgKelas > 0 ? number_format($statsAvgKelas, 1) : '—' ?>
                        <span class="fs-6 fw-semibold text-muted">/ 100</span>
                    </h4>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="stat-box-modern d-flex align-items-center gap-3">
                <div class="rounded-4 text-white d-flex align-items-center justify-content-center shadow-sm flex-shrink-0"
                    style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); width:52px; height:52px;">
                    <i class="bi bi-chat-square-quote-fill fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-bold text-uppercase"
                        style="letter-spacing: 0.5px; font-size: 0.72rem;">Santri Ada Catatan Guru</span>
                    <h4 class="fw-black text-dark m-0" style="font-size: 1.55rem;"><?= $statsSantriCatatan ?> <span
                            class="fs-6 fw-semibold text-muted">Santri</span></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="card border-0 rounded-4 shadow-sm p-3.5 p-md-4 mb-4 bg-white" style="border: 1px solid #e2e8f0 !important;">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label class="form-label small fw-bold text-secondary mb-1"
                    style="font-size: 0.75rem; letter-spacing: 0.4px;">1. KELAS BINAAN</label>
                <select name="kelas_id" class="form-select rounded-3 fw-semibold" style="height: 42px; font-size: 0.85rem;"
                    onchange="this.form.submit()">
                    <?php foreach ($waliClasses as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= $selectedKelasId == $k['id'] ? 'selected' : '' ?>>
                            Kelas <?= htmlspecialchars($k['nama_kelas']) ?>
                            <?= !empty($k['nama_wali_kelas']) && !$isGuruRole ? '(' . htmlspecialchars($k['nama_wali_kelas']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label small fw-bold text-secondary mb-1"
                    style="font-size: 0.75rem; letter-spacing: 0.4px;">2. PILIH MATA PELAJARAN</label>
                <select name="filter_mapel" class="form-select rounded-3 fw-semibold"
                    style="height: 42px; font-size: 0.85rem;" onchange="this.form.submit()">
                    <option value="all" <?= $selectedMapelFilter === 'all' ? 'selected' : '' ?>>📊 Semua Mata Pelajaran (Rekap
                        Santri)</option>
                    <?php foreach ($listMapel as $m): ?>
                        <option value="<?= htmlspecialchars($m['nama_mapel']) ?>" <?= $selectedMapelFilter === $m['nama_mapel'] ? 'selected' : '' ?>>
                            📘 <?= htmlspecialchars($m['nama_mapel']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label small fw-bold text-secondary mb-1"
                    style="font-size: 0.75rem; letter-spacing: 0.4px;">3. PILIH ASESMEN</label>
                <select name="filter_sumatif" class="form-select rounded-3 fw-semibold"
                    style="height: 42px; font-size: 0.85rem;" onchange="this.form.submit()">
                    <option value="all" <?= $selectedSumatif === 'all' ? 'selected' : '' ?>>📋 Semua Asesmen (S1-S4, ATS, AAS)
                    </option>
                    <option value="s1" <?= $selectedSumatif === 's1' ? 'selected' : '' ?>>⭐ Hanya Sumatif 1 (S1)</option>
                    <option value="s2" <?= $selectedSumatif === 's2' ? 'selected' : '' ?>>⭐ Hanya Sumatif 2 (S2)</option>
                    <option value="s3" <?= $selectedSumatif === 's3' ? 'selected' : '' ?>>⭐ Hanya Sumatif 3 (S3)</option>
                    <option value="s4" <?= $selectedSumatif === 's4' ? 'selected' : '' ?>>⭐ Hanya Sumatif 4 (S4)</option>
                    <option value="ats" <?= $selectedSumatif === 'ats' ? 'selected' : '' ?>>📊 Hanya ATS (Tengah Sem)</option>
                    <option value="aas" <?= $selectedSumatif === 'aas' ? 'selected' : '' ?>>📑 Hanya AAS / PAS (Akhir Sem)
                    </option>
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label small fw-bold text-secondary mb-1"
                    style="font-size: 0.75rem; letter-spacing: 0.4px;">4. SEMESTER & TA</label>
                <select name="semester" class="form-select rounded-3 fw-semibold" style="height: 42px; font-size: 0.85rem;"
                    onchange="this.form.submit()">
                    <option value="Ganjil" <?= $selectedSem === 'Ganjil' ? 'selected' : '' ?>>Ganjil (<?= $selectedTahun ?>)
                    </option>
                    <option value="Genap" <?= $selectedSem === 'Genap' ? 'selected' : '' ?>>Genap (<?= $selectedTahun ?>)
                    </option>
                </select>
                <input type="hidden" name="tahun_ajaran" value="<?= htmlspecialchars($selectedTahun) ?>">
            </div>
            <div class="col-lg-2 col-md-4">
                <button type="submit"
                    class="btn text-white w-100 fw-bold rounded-3 shadow-sm d-inline-flex align-items-center justify-content-center gap-1.5"
                    style="height: 42px; background: linear-gradient(135deg, #1e3a8a 0%, #1e1b4b 100%); border: none; font-size: 0.88rem;">
                    <i class="bi bi-search"></i> Tampilkan
                </button>
            </div>
        </form>
    </div>

    <?php if ($selectedMapelFilter !== 'all'):
        // ===== MODE 1: TAMPILAN PER MATA PELAJARAN (DETAIL SUMATIF 1-4 & CATATAN PER SUMATIF) =====
        $activeMateri = $mapelMateriMap[$selectedMapelFilter] ?? [];
        ?>
        <div class="card border-0 rounded-4 shadow-sm mb-4 bg-white overflow-hidden"
            style="border: 1px solid #cbd5e1 !important;">
            <div
                class="p-3.5 p-md-4 border-bottom bg-light bg-opacity-50 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-primary px-2.5 py-1 rounded-pill fw-bold">Mata Pelajaran</span>
                        <h5 class="fw-black m-0 text-dark"><?= htmlspecialchars($selectedMapelFilter) ?></h5>
                        <?php if ($selectedSumatif !== 'all'): ?>
                            <span class="badge bg-warning text-dark px-2.5 py-1 rounded-pill fw-bold font-monospace">
                                Filter: <?= strtoupper($selectedSumatif) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted">Menampilkan rincian nilai sumatif, bab yang diujikan, serta catatan khusus santri
                        yang telah diisi oleh guru mata pelajaran.</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL ?>/pages/wali-kelas/print-rekap.php?kelas_id=<?= $selectedKelasId ?>&filter_mapel=<?= urlencode($selectedMapelFilter) ?>&filter_sumatif=<?= urlencode($selectedSumatif) ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>"
                        target="_blank" class="btn btn-outline-primary btn-sm rounded-3 fw-bold px-3 shadow-xs">
                        <i class="bi bi-printer-fill me-1"></i> Cetak Rekap Ini (PDF)
                    </a>
                    <a href="?kelas_id=<?= $selectedKelasId ?>&filter_mapel=all&filter_sumatif=<?= urlencode($selectedSumatif) ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>"
                        class="btn btn-outline-secondary btn-sm rounded-3 fw-bold px-3">
                        <i class="bi bi-grid-3x3-gap me-1"></i> Tampilkan Semua Mapel
                    </a>
                </div>
            </div>

            <!-- BOX INFO BAB YANG DIUJIKAN PADA MAPEL INI -->
            <?php if (!empty($activeMateri['materi_s1']) || !empty($activeMateri['materi_s2']) || !empty($activeMateri['materi_s3']) || !empty($activeMateri['materi_s4'])): ?>
                <div class="p-3 bg-light border-bottom">
                    <div class="row g-2">
                        <?php if ($selectedSumatif === 'all' || $selectedSumatif === 's1'): ?>
                            <div class="col-md-3 col-6">
                                <div class="p-2 bg-white rounded-3 border">
                                    <span class="extra-small text-muted fw-bold d-block"><span class="badge bg-dark me-1">S1</span> Bab
                                        Sumatif 1:</span>
                                    <strong
                                        class="text-dark small"><?= nl2br(htmlspecialchars($activeMateri['materi_s1'] ?: '—')) ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($selectedSumatif === 'all' || $selectedSumatif === 's2'): ?>
                            <div class="col-md-3 col-6">
                                <div class="p-2 bg-white rounded-3 border">
                                    <span class="extra-small text-muted fw-bold d-block"><span class="badge bg-dark me-1">S2</span> Bab
                                        Sumatif 2:</span>
                                    <strong
                                        class="text-dark small"><?= nl2br(htmlspecialchars($activeMateri['materi_s2'] ?: '—')) ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($selectedSumatif === 'all' || $selectedSumatif === 's3'): ?>
                            <div class="col-md-3 col-6">
                                <div class="p-2 bg-white rounded-3 border">
                                    <span class="extra-small text-muted fw-bold d-block"><span class="badge bg-dark me-1">S3</span> Bab
                                        Sumatif 3:</span>
                                    <strong
                                        class="text-dark small"><?= nl2br(htmlspecialchars($activeMateri['materi_s3'] ?: '—')) ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($selectedSumatif === 'all' || $selectedSumatif === 's4'): ?>
                            <div class="col-md-3 col-6">
                                <div class="p-2 bg-white rounded-3 border">
                                    <span class="extra-small text-muted fw-bold d-block"><span class="badge bg-dark me-1">S4</span> Bab
                                        Sumatif 4:</span>
                                    <strong
                                        class="text-dark small"><?= nl2br(htmlspecialchars($activeMateri['materi_s4'] ?: '—')) ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0" style="font-size:0.88rem;">
                    <thead style="background:#0f172a; color:#ffffff;">
                        <tr class="text-center">
                            <th style="width: 45px;" rowspan="2">No</th>
                            <th style="width: 95px;" rowspan="2">NIS</th>
                            <th class="text-start ps-3" style="min-width: 170px;" rowspan="2">Nama Santri</th>

                            <?php if ($selectedSumatif === 'all'): ?>
                                <th colspan="4" style="background:#1e3a8a;">Asesmen Sumatif Lingkup Materi & Catatan Guru</th>
                                <th style="width: 70px;" rowspan="2">ATS</th>
                                <th style="width: 70px;" rowspan="2">AAS</th>
                                <th style="width: 85px; background:#134e4a;" rowspan="2">Nilai Akhir</th>
                                <th style="width: 60px; background:#134e4a;" rowspan="2">Pred</th>
                                <th class="text-start ps-3" style="min-width: 190px;" rowspan="2">Catatan Umum Guru</th>
                            <?php elseif ($selectedSumatif === 's1'): ?>
                                <th style="background:#1e3a8a;">Sumatif 1 (S1) & Catatan Guru</th>
                                <th style="width: 90px; background:#134e4a;" rowspan="2">Nilai Akhir</th>
                                <th style="width: 60px; background:#134e4a;" rowspan="2">Pred</th>
                            <?php elseif ($selectedSumatif === 's2'): ?>
                                <th style="background:#1e3a8a;">Sumatif 2 (S2) & Catatan Guru</th>
                                <th style="width: 90px; background:#134e4a;" rowspan="2">Nilai Akhir</th>
                                <th style="width: 60px; background:#134e4a;" rowspan="2">Pred</th>
                            <?php elseif ($selectedSumatif === 's3'): ?>
                                <th style="background:#1e3a8a;">Sumatif 3 (S3) & Catatan Guru</th>
                                <th style="width: 90px; background:#134e4a;" rowspan="2">Nilai Akhir</th>
                                <th style="width: 60px; background:#134e4a;" rowspan="2">Pred</th>
                            <?php elseif ($selectedSumatif === 's4'): ?>
                                <th style="background:#1e3a8a;">Sumatif 4 (S4) & Catatan Guru</th>
                                <th style="width: 90px; background:#134e4a;" rowspan="2">Nilai Akhir</th>
                                <th style="width: 60px; background:#134e4a;" rowspan="2">Pred</th>
                            <?php elseif ($selectedSumatif === 'ats'): ?>
                                <th style="width: 120px; background:#1e3a8a;" rowspan="2">ATS (Tengah Sem)</th>
                                <th style="width: 90px; background:#134e4a;" rowspan="2">Nilai Akhir</th>
                                <th style="width: 60px; background:#134e4a;" rowspan="2">Pred</th>
                            <?php elseif ($selectedSumatif === 'aas'): ?>
                                <th style="width: 120px; background:#1e3a8a;" rowspan="2">AAS (Akhir Sem)</th>
                                <th style="width: 90px; background:#134e4a;" rowspan="2">Nilai Akhir</th>
                                <th style="width: 60px; background:#134e4a;" rowspan="2">Pred</th>
                            <?php endif; ?>
                        </tr>
                        <?php if ($selectedSumatif === 'all'): ?>
                            <tr class="text-center" style="background:#172554; font-size:0.75rem;">
                                <th style="min-width: 135px;">S1 (Bab 1)</th>
                                <th style="min-width: 135px;">S2 (Bab 2)</th>
                                <th style="min-width: 135px;">S3 (Bab 3)</th>
                                <th style="min-width: 135px;">S4 (Bab 4)</th>
                            </tr>
                        <?php elseif (in_array($selectedSumatif, ['s1', 's2', 's3', 's4'])): ?>
                            <tr class="text-center" style="background:#172554; font-size:0.75rem;">
                                <th>Nilai & Catatan Evaluasi Bab</th>
                            </tr>
                        <?php endif; ?>
                    </thead>
                    <tbody>
                        <?php if (empty($listSiswa)): ?>
                            <tr>
                                <td colspan="12" class="text-center py-5 text-muted">Belum ada data siswa di kelas ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($listSiswa as $i => $s):
                                $g = findGrade($s['grades'], $s['norm_grades'], $selectedMapelFilter);
                                $s1 = (float) ($g['sumatif_1'] ?? 0);
                                $s2 = (float) ($g['sumatif_2'] ?? 0);
                                $s3 = (float) ($g['sumatif_3'] ?? 0);
                                $s4 = (float) ($g['sumatif_4'] ?? 0);
                                $ats = (float) ($g['ats'] ?? 0);
                                $aas = (float) ($g['aas'] ?? 0);
                                $na = (float) ($g['nilai_akhir'] ?? 0);
                                $pred = $g['predikat'] ?? '—';
                                $catS1 = $g['catatan_s1'] ?? '';
                                $catS2 = $g['catatan_s2'] ?? '';
                                $catS3 = $g['catatan_s3'] ?? '';
                                $catS4 = $g['catatan_s4'] ?? '';
                                $catUmum = $g['catatan'] ?? '';
                                ?>
                                <tr>
                                    <td class="text-center fw-bold text-secondary small"><?= $i + 1 ?></td>
                                    <td class="text-center font-monospace fw-bold text-dark">
                                        <span
                                            class="bg-light px-2 py-0.5 rounded border small"><?= htmlspecialchars($s['nis']) ?></span>
                                    </td>
                                    <td class="ps-3">
                                        <span class="fw-bold text-dark"><?= htmlspecialchars($s['nama']) ?></span>
                                    </td>

                                    <?php if ($selectedSumatif === 'all' || $selectedSumatif === 's1'): ?>
                                        <!-- S1 -->
                                        <td class="p-2">
                                            <div class="text-center fw-extrabold text-primary fs-6">
                                                <?= $s1 > 0 ? $s1 : '<span class="text-muted opacity-50">—</span>' ?>
                                            </div>
                                            <?php if (!empty($catS1)): ?>
                                                <div class="note-quote-box mt-1">
                                                    <i class="bi bi-chat-quote-fill text-primary me-1"></i><?= htmlspecialchars($catS1) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>

                                    <?php if ($selectedSumatif === 'all' || $selectedSumatif === 's2'): ?>
                                        <!-- S2 -->
                                        <td class="p-2">
                                            <div class="text-center fw-extrabold text-primary fs-6">
                                                <?= $s2 > 0 ? $s2 : '<span class="text-muted opacity-50">—</span>' ?>
                                            </div>
                                            <?php if (!empty($catS2)): ?>
                                                <div class="note-quote-box mt-1">
                                                    <i class="bi bi-chat-quote-fill text-primary me-1"></i><?= htmlspecialchars($catS2) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>

                                    <?php if ($selectedSumatif === 'all' || $selectedSumatif === 's3'): ?>
                                        <!-- S3 -->
                                        <td class="p-2">
                                            <div class="text-center fw-extrabold text-primary fs-6">
                                                <?= $s3 > 0 ? $s3 : '<span class="text-muted opacity-50">—</span>' ?>
                                            </div>
                                            <?php if (!empty($catS3)): ?>
                                                <div class="note-quote-box mt-1">
                                                    <i class="bi bi-chat-quote-fill text-primary me-1"></i><?= htmlspecialchars($catS3) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>

                                    <?php if ($selectedSumatif === 'all' || $selectedSumatif === 's4'): ?>
                                        <!-- S4 -->
                                        <td class="p-2">
                                            <div class="text-center fw-extrabold text-primary fs-6">
                                                <?= $s4 > 0 ? $s4 : '<span class="text-muted opacity-50">—</span>' ?>
                                            </div>
                                            <?php if (!empty($catS4)): ?>
                                                <div class="note-quote-box mt-1">
                                                    <i class="bi bi-chat-quote-fill text-primary me-1"></i><?= htmlspecialchars($catS4) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>

                                    <?php if ($selectedSumatif === 'all' || $selectedSumatif === 'ats'): ?>
                                        <!-- ATS -->
                                        <td class="text-center fw-bold"><?= $ats > 0 ? $ats : '<span class="text-muted">—</span>' ?></td>
                                    <?php endif; ?>

                                    <?php if ($selectedSumatif === 'all' || $selectedSumatif === 'aas'): ?>
                                        <!-- AAS -->
                                        <td class="text-center fw-bold"><?= $aas > 0 ? $aas : '<span class="text-muted">—</span>' ?></td>
                                    <?php endif; ?>

                                    <!-- NILAI AKHIR & PREDIKAT -->
                                    <td class="text-center">
                                        <?php if ($na > 0): ?>
                                            <span class="pill-score pill-score-na"
                                                style="font-size:0.88rem; padding:0.25rem 0.6rem;"><?= number_format($na, 1) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center fw-black fs-6"><?= $pred ?></td>

                                    <?php if ($selectedSumatif === 'all'): ?>
                                        <!-- CATATAN UMUM GURU -->
                                        <td class="ps-3 pe-3">
                                            <?php if (!empty($catUmum)): ?>
                                                <div class="fw-semibold text-dark small"><i
                                                        class="bi bi-pencil-fill text-muted me-1"></i><?= htmlspecialchars($catUmum) ?></div>
                                            <?php else: ?>
                                                <span class="text-muted extra-small fst-italic">—</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else:
        // ===== MODE 2: TAMPILAN SEMUA MATA PELAJARAN (REKAP SANTRI BINAAN LENGKAP) =====
        ?>
        <div class="card border-0 rounded-4 shadow-sm mb-4 bg-white overflow-hidden"
            style="border: 1px solid #cbd5e1 !important;">
            <div
                class="p-3.5 p-md-4 border-bottom bg-light bg-opacity-50 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-primary px-2.5 py-1 rounded-pill fw-bold">Matriks Rekap Kelas</span>
                        <h5 class="fw-black m-0 text-dark">Rekapitulasi Nilai Seluruh Mata Pelajaran & Catatan Guru</h5>
                        <?php if ($selectedSumatif !== 'all'): ?>
                            <span class="badge bg-warning text-dark px-2.5 py-1 rounded-pill fw-bold font-monospace">
                                Filter: <?= strtoupper($selectedSumatif) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted">Wali Kelas dapat melihat nilai dan catatan dari seluruh guru mata pelajaran secara
                        langsung pada tabel di bawah ini.</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL ?>/pages/wali-kelas/print-rekap.php?kelas_id=<?= $selectedKelasId ?>&filter_mapel=all&filter_sumatif=<?= urlencode($selectedSumatif) ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>"
                        target="_blank" class="btn btn-primary btn-sm rounded-3 fw-bold px-3 shadow-xs"
                        style="background:#1e3a8a; border-color:#1e3a8a;">
                        <i class="bi bi-printer-fill me-1"></i> Cetak Rekap Matriks (PDF)
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0" style="font-size: 0.85rem;">
                    <thead style="background:#0f172a; color:#ffffff;">
                        <tr class="text-center">
                            <th style="width: 40px;" rowspan="2">No</th>
                            <th style="width: 90px;" rowspan="2">NIS</th>
                            <th class="text-start ps-3" style="min-width: 170px;" rowspan="2">Nama Santri</th>
                            <th colspan="<?= count($listMapel) ?>" style="background: #1e3a8a;">
                                Nilai & Catatan Guru per Mata Pelajaran
                                (<?= $selectedSumatif === 'all' ? 'Nilai Akhir & Catatan' : 'Evaluasi ' . strtoupper($selectedSumatif) ?>)
                            </th>
                            <th style="width: 80px; background: #134e4a;" rowspan="2">Rata Nilai</th>
                            <th style="width: 50px; background: #134e4a;" rowspan="2">Pred</th>
                            <th style="width: 110px;" rowspan="2">Aksi</th>
                        </tr>
                        <tr class="text-center" style="background:#172554; font-size:0.75rem;">
                            <?php foreach ($listMapel as $m): ?>
                                <th style="min-width: 130px;" title="<?= htmlspecialchars($m['nama_mapel']) ?>">
                                    <?= htmlspecialchars($m['nama_mapel']) ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($listSiswa)): ?>
                            <tr>
                                <td colspan="<?= count($listMapel) + 6 ?>" class="text-center py-5 text-muted">
                                    <i class="bi bi-people display-4 d-block mb-3 opacity-40"></i>
                                    <h6 class="fw-bold mb-1">Belum Ada Data Siswa</h6>
                                    <p class="small text-muted mb-0">Tidak ada santri aktif yang terdaftar pada kelas ini.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($listSiswa as $i => $s):
                                $grades = $s['grades'] ?? [];
                                $sumScore = 0;
                                $cntScore = 0;
                                ?>
                                <tr>
                                    <td class="text-center fw-bold text-secondary small"><?= $i + 1 ?></td>
                                    <td class="text-center font-monospace fw-bold text-dark">
                                        <span
                                            class="bg-light px-2 py-0.5 rounded border small"><?= htmlspecialchars($s['nis']) ?></span>
                                    </td>
                                    <td class="ps-3">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($s['nama']) ?></div>
                                        <small class="text-muted"><?= $s['jk'] === 'L' ? 'Ikhwan (L)' : 'Akhwat (P)' ?></small>
                                    </td>

                                    <!-- LOOP NILAI & CATATAN TIAP MAPEL -->
                                    <?php foreach ($listMapel as $m):
                                        $mName = $m['nama_mapel'];
                                        $g = findGrade($grades, $s['norm_grades'], $mName);

                                        $score = 0;
                                        $noteHtml = '';

                                        if ($selectedSumatif === 's1') {
                                            $score = (float) ($g['sumatif_1'] ?? 0);
                                            $c = trim($g['catatan_s1'] ?? '');
                                            if (!empty($c))
                                                $noteHtml = htmlspecialchars($c);
                                        } elseif ($selectedSumatif === 's2') {
                                            $score = (float) ($g['sumatif_2'] ?? 0);
                                            $c = trim($g['catatan_s2'] ?? '');
                                            if (!empty($c))
                                                $noteHtml = htmlspecialchars($c);
                                        } elseif ($selectedSumatif === 's3') {
                                            $score = (float) ($g['sumatif_3'] ?? 0);
                                            $c = trim($g['catatan_s3'] ?? '');
                                            if (!empty($c))
                                                $noteHtml = htmlspecialchars($c);
                                        } elseif ($selectedSumatif === 's4') {
                                            $score = (float) ($g['sumatif_4'] ?? 0);
                                            $c = trim($g['catatan_s4'] ?? '');
                                            if (!empty($c))
                                                $noteHtml = htmlspecialchars($c);
                                        } elseif ($selectedSumatif === 'ats') {
                                            $score = (float) ($g['ats'] ?? 0);
                                        } elseif ($selectedSumatif === 'aas') {
                                            $score = (float) ($g['aas'] ?? 0);
                                        } else {
                                            $score = (float) ($g['nilai_akhir'] ?? 0);
                                            if ($score <= 0) {
                                                $validSumatifs = array_filter([
                                                    (float) ($g['sumatif_1'] ?? 0),
                                                    (float) ($g['sumatif_2'] ?? 0),
                                                    (float) ($g['sumatif_3'] ?? 0),
                                                    (float) ($g['sumatif_4'] ?? 0)
                                                ], function ($v) {
                                                    return $v > 0;
                                                });
                                                if (!empty($validSumatifs)) {
                                                    $score = round(array_sum($validSumatifs) / count($validSumatifs), 1);
                                                }
                                            }

                                            $notesArr = [];
                                            if (!empty(trim($g['catatan'] ?? ''))) {
                                                $notesArr[] = htmlspecialchars(trim($g['catatan']));
                                            }
                                            foreach (['S1' => 'catatan_s1', 'S2' => 'catatan_s2', 'S3' => 'catatan_s3', 'S4' => 'catatan_s4'] as $tag => $fld) {
                                                if (!empty(trim($g[$fld] ?? ''))) {
                                                    $notesArr[] = "<strong>$tag:</strong> " . htmlspecialchars(trim($g[$fld]));
                                                }
                                            }
                                            if (!empty($notesArr)) {
                                                $noteHtml = implode('<br>', $notesArr);
                                            }
                                        }

                                        if ($score > 0) {
                                            $sumScore += $score;
                                            $cntScore++;
                                        }
                                        ?>
                                        <td class="p-2 text-center">
                                            <?php if ($score > 0): ?>
                                                <div class="fw-extrabold text-primary fs-6"><?= $score ?></div>
                                            <?php else: ?>
                                                <span class="text-muted opacity-40">—</span>
                                            <?php endif; ?>
                                            <?php if (!empty($noteHtml)): ?>
                                                <div class="note-quote-box mt-1 text-start" style="font-size:0.75rem;">
                                                    <i class="bi bi-chat-quote-fill text-primary me-1"></i><?= $noteHtml ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>

                                    <!-- RATA-RATA & PREDIKAT -->
                                    <?php
                                    $avgScore = $cntScore > 0 ? round($sumScore / $cntScore, 1) : 0;
                                    if ($avgScore >= 90)
                                        $pred = 'A';
                                    elseif ($avgScore >= 80)
                                        $pred = 'B';
                                    elseif ($avgScore >= 70)
                                        $pred = 'C';
                                    elseif ($avgScore > 0)
                                        $pred = 'D';
                                    else
                                        $pred = '—';
                                    ?>
                                    <td class="text-center">
                                        <?php if ($avgScore > 0): ?>
                                            <span class="pill-score pill-score-na"
                                                style="font-size:0.85rem; padding:0.2rem 0.5rem;"><?= number_format($avgScore, 1) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center fw-black"><?= $pred ?></td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-primary fw-bold rounded-2 px-2.5 py-1 shadow-xs"
                                            style="background:#1e3a8a; border-color:#1e3a8a; font-size:0.75rem;"
                                            onclick='showSantriDetail(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode($listMapel, JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode($mapelMateriMap, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            <i class="bi bi-eye-fill me-1"></i> Detail
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<!-- MODAL DETAIL REKAPITULASI NILAI & CATATAN SANTRI -->
<div class="modal fade" id="modalDetailSantri" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-2xl rounded-4 overflow-hidden">
            <div class="modal-header border-bottom px-4 py-3 text-white" style="background: #0f172a;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle p-2 bg-white bg-opacity-10 text-white">
                        <i class="bi bi-person-lines-fill fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-black mb-0" id="modalDetailNama">—</h5>
                        <small class="text-white-50" id="modalDetailSubtitle">Rekapitulasi Nilai Sumatif & Catatan Guru
                            untuk Pertemuan Orang Tua</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light bg-opacity-40">
                <!-- INFO PROFIL SINGKAT SANTRI -->
                <div class="card border-0 rounded-3 shadow-xs p-3 mb-4 bg-white border">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <span class="extra-small text-muted fw-bold text-uppercase d-block">NIS / NISN:</span>
                            <span class="fw-bold text-dark font-monospace" id="modalDetailNis">—</span>
                        </div>
                        <div class="col-md-3">
                            <span class="extra-small text-muted fw-bold text-uppercase d-block">Kelas:</span>
                            <span
                                class="fw-bold text-dark"><?= htmlspecialchars($currentKelas['nama_kelas'] ?? '-') ?></span>
                        </div>
                        <div class="col-md-3">
                            <span class="extra-small text-muted fw-bold text-uppercase d-block">Semester & TA:</span>
                            <span class="fw-bold text-dark"><?= htmlspecialchars($selectedSem) ?>
                                <?= htmlspecialchars($selectedTahun) ?></span>
                        </div>
                        <div class="col-md-3">
                            <span class="extra-small text-muted fw-bold text-uppercase d-block">Rata-rata Nilai:</span>
                            <span class="fw-bold text-primary fs-6" id="modalDetailAvg">—</span>
                        </div>
                    </div>
                </div>

                <!-- TABEL RINCIAN MAPEL -->
                <div class="card border-0 rounded-4 shadow-sm bg-white overflow-hidden border">
                    <div class="table-responsive">
                        <table class="table table-bordered table-rekap-mapel align-middle mb-0" id="modalTableDetail">
                            <thead>
                                <tr class="text-center">
                                    <th style="width: 35px;">No</th>
                                    <th class="text-start ps-3" style="min-width: 180px;">Mata Pelajaran & Guru</th>
                                    <th style="width: 85px;">S1</th>
                                    <th style="width: 85px;">S2</th>
                                    <th style="width: 85px;">S3</th>
                                    <th style="width: 85px;">S4</th>
                                    <th style="width: 75px;">ATS</th>
                                    <th style="width: 75px;">AAS</th>
                                    <th style="width: 85px;">Nilai Akhir</th>
                                    <th style="width: 60px;">Pred</th>
                                    <th class="text-start ps-3" style="min-width: 220px;">Catatan Perkembangan Guru</th>
                                </tr>
                            </thead>
                            <tbody id="modalTableDetailBody">
                                <!-- Populated dynamically by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top px-4 py-3 bg-white d-flex justify-content-between">
                <button type="button" class="btn btn-light rounded-3 px-3.5 border fw-bold"
                    data-bs-dismiss="modal">Tutup</button>
                <div class="d-flex gap-2">
                    <a id="btnLinkRapor" href="#" class="btn btn-primary rounded-3 px-4 fw-bold shadow-sm"
                        style="background:#1e3a8a; border-color:#1e3a8a;">
                        <i class="bi bi-file-earmark-text-fill me-1"></i> Edit Rapor Santri Ini
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function showSantriDetail(santri, mapelList, materiMap) {
        document.getElementById('modalDetailNama').textContent = santri.nama;
        document.getElementById('modalDetailNis').textContent = (santri.nis || '—') + ' / ' + (santri.nisn || '—');
        document.getElementById('modalDetailAvg').textContent = santri.avg_na > 0 ? santri.avg_na.toFixed(1) : '—';
        document.getElementById('btnLinkRapor').href = `<?= BASE_URL ?>/pages/rapor/edit.php?siswa_id=${santri.siswa_id}&tipe=pas&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>`;

        const tbody = document.getElementById('modalTableDetailBody');
        tbody.innerHTML = '';

        if (!mapelList || mapelList.length === 0) {
            tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-muted">Belum ada data mata pelajaran untuk kelas ini.</td></tr>`;
        } else {
            mapelList.forEach((m, idx) => {
                const mapelName = m.nama_mapel;
                const cleanAlpha = mapelName.toLowerCase().replace(/[^a-z0-9]/g, '');

                // Cari data nilai dengan alias fallback
                let g = santri.grades[mapelName] || {};
                if ((!g || Object.keys(g).length === 0) && santri.norm_grades) {
                    g = santri.norm_grades[cleanAlpha] || santri.norm_grades[mapelName.toLowerCase()] || {};
                }

                const mat = materiMap[mapelName] || {};

                const s1 = parseFloat(g.sumatif_1) || 0;
                const s2 = parseFloat(g.sumatif_2) || 0;
                const s3 = parseFloat(g.sumatif_3) || 0;
                const s4 = parseFloat(g.sumatif_4) || 0;
                const ats = parseFloat(g.ats) || 0;
                const aas = parseFloat(g.aas) || 0;
                const na = parseFloat(g.nilai_akhir) || 0;
                const pred = g.predikat || '—';

                // Catatan per sumatif & Deskripsi Rapor Guru
                const catS1 = g.catatan_s1 ? `<div class="note-quote-box"><b>S1:</b> ${escapeHtml(g.catatan_s1)}</div>` : '';
                const catS2 = g.catatan_s2 ? `<div class="note-quote-box"><b>S2:</b> ${escapeHtml(g.catatan_s2)}</div>` : '';
                const catS3 = g.catatan_s3 ? `<div class="note-quote-box"><b>S3:</b> ${escapeHtml(g.catatan_s3)}</div>` : '';
                const catS4 = g.catatan_s4 ? `<div class="note-quote-box"><b>S4:</b> ${escapeHtml(g.catatan_s4)}</div>` : '';
                const catUmum = g.catatan ? `<div class="fw-semibold text-dark mb-1">💬 ${escapeHtml(g.catatan)}</div>` : '';

                const deskAtsBox = g.deskripsi_ats ? `<div class="note-quote-box text-primary mt-1 border-primary"><b class="text-primary"><i class="bi bi-file-earmark-text me-1"></i>Deskripsi ATS:</b> ${escapeHtml(g.deskripsi_ats)}</div>` : '';
                const deskAasBox = g.deskripsi_aas ? `<div class="note-quote-box text-success mt-1 border-success"><b class="text-success"><i class="bi bi-file-earmark-check me-1"></i>Deskripsi AAS:</b> ${escapeHtml(g.deskripsi_aas)}</div>` : '';

                const allNotes = (catUmum + catS1 + catS2 + catS3 + catS4 + deskAtsBox + deskAasBox) || '<span class="text-muted small fst-italic">Tidak ada catatan khusus</span>';

                // Format Nilai Cell with bab tooltip
                const renderScore = (score, babName) => {
                    if (score <= 0) return '<span class="text-muted">—</span>';
                    const babBadge = babName ? `<br><span class="badge-materi text-truncate" style="max-width:75px;" title="${escapeHtml(babName)}">${escapeHtml(babName)}</span>` : '';
                    return `<strong>${score}</strong>${babBadge}`;
                };

                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td class="text-center fw-bold text-secondary small">${idx + 1}</td>
                <td class="ps-3">
                    <div class="fw-bold text-dark">${escapeHtml(mapelName)}</div>
                    <small class="text-muted"><i class="bi bi-person me-1"></i>${escapeHtml(m.nama_guru_pengampu || g.nama_guru_input || 'Guru Pengampu')}</small>
                </td>
                <td class="text-center">${renderScore(s1, mat.materi_s1)}</td>
                <td class="text-center">${renderScore(s2, mat.materi_s2)}</td>
                <td class="text-center">${renderScore(s3, mat.materi_s3)}</td>
                <td class="text-center">${renderScore(s4, mat.materi_s4)}</td>
                <td class="text-center">${ats > 0 ? ats : '—'}</td>
                <td class="text-center">${aas > 0 ? aas : '—'}</td>
                <td class="text-center"><span class="pill-score pill-score-na" style="font-size:0.85rem; padding:0.2rem 0.5rem;">${na > 0 ? na.toFixed(1) : '—'}</span></td>
                <td class="text-center fw-bold">${pred}</td>
                <td class="ps-3 pe-3">${allNotes}</td>
            `;
                tbody.appendChild(tr);
            });
        }

        const modalEl = document.getElementById('modalDetailSantri');
        const modalObj = new bootstrap.Modal(modalEl);
        modalObj.show();
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>