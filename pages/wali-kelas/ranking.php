<?php
/**
 * MODUL WALI KELAS - LEADERBOARD & PERINGKAT SANTRI
 * Perhitungan Ranking berdasarkan Sumatif 1, 2, 3, 4, ATS, AAS, dan Keseluruhan Nilai
 * Kurikulum Merdeka - Islamic School of Minhaj Al-Ilmi
 */
$pageTitle = 'Wali Kelas - Ranking & Leaderboard Santri';
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

// 1. Ambil daftar kelas binaan / semua kelas (Admin)
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
$selectedKategori = isset($_GET['kategori']) && in_array($_GET['kategori'], ['all', 's1', 's2', 's3', 's4', 'ats', 'aas']) ? $_GET['kategori'] : 'all';
$selectedMapel = isset($_GET['mapel']) ? trim($_GET['mapel']) : 'all';

// 3. Detail Kelas Terpilih
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

// 4. Daftar Mata Pelajaran Aktif untuk Filter
$listMapel = [];
if ($selectedKelasId > 0) {
    $stmtMapel = $pdo->prepare("
        SELECT DISTINCT mp.id AS mapel_id, mp.nama_mapel, mp.kelompok 
        FROM guru_mapel_kelas gmk
        JOIN mata_pelajaran mp ON gmk.mapel_id = mp.id 
        WHERE gmk.kelas_id = :kid AND mp.status = 'aktif' 
        ORDER BY mp.kelompok ASC, mp.nama_mapel ASC
    ");
    $stmtMapel->execute([':kid' => $selectedKelasId]);
    $listMapel = $stmtMapel->fetchAll();

    if (empty($listMapel)) {
        $stmtMapel = $pdo->query("
            SELECT DISTINCT mp.id AS mapel_id, mp.nama_mapel, mp.kelompok 
            FROM mata_pelajaran mp 
            WHERE mp.status = 'aktif' 
            ORDER BY mp.kelompok ASC, mp.nama_mapel ASC
        ");
        $listMapel = $stmtMapel->fetchAll();
    }
}

// 5. Query Siswa & Agregasi Nilai untuk Ranking
$rankedList = [];
$statsTotalSiswa = 0;
$statsAvgKelas = 0;
$statsHighest = 0;
$statsLowest = 100;
$topPerformer = null;

if ($selectedKelasId > 0) {
    // Ambil daftar siswa aktif
    $stmtSiswa = $pdo->prepare("
        SELECT id AS siswa_id, nis, nisn, nama, jenis_kelamin, foto
        FROM siswa
        WHERE kelas_id = :kid AND status = 'aktif'
        ORDER BY nama ASC
    ");
    $stmtSiswa->execute([':kid' => $selectedKelasId]);
    $rawSiswa = $stmtSiswa->fetchAll();
    $statsTotalSiswa = count($rawSiswa);

    // Ambil semua data nilai siswa di kelas ini
    $sqlNilai = "
        SELECT ns.*, mp.kelompok AS mapel_kelompok
        FROM nilai_siswa ns
        JOIN mata_pelajaran mp ON ns.mapel = mp.nama_mapel AND mp.status = 'aktif'
        WHERE ns.kelas_id = :kid 
          AND ns.semester = :sem 
          AND ns.tahun_ajaran = :th
    ";
    $paramsNilai = [
        ':kid' => $selectedKelasId,
        ':sem' => $selectedSem,
        ':th' => $selectedTahun
    ];

    if ($selectedMapel !== 'all' && !empty($selectedMapel)) {
        $sqlNilai .= " AND ns.mapel = :mapel_filter";
        $paramsNilai[':mapel_filter'] = $selectedMapel;
    }

    $stmtNilai = $pdo->prepare($sqlNilai);
    $stmtNilai->execute($paramsNilai);
    $rawNilai = $stmtNilai->fetchAll();

    // Petakan nilai per siswa
    $nilaiBySiswa = [];
    foreach ($rawNilai as $n) {
        $sid = (int) $n['siswa_id'];
        $nilaiBySiswa[$sid][] = $n;
    }

    // Kalkulasi skor per siswa berdasarkan kategori ranking terpilih
    $allScores = [];
    foreach ($rawSiswa as $s) {
        $sid = (int) $s['siswa_id'];
        $records = $nilaiBySiswa[$sid] ?? [];

        $sumS1 = 0;
        $cntS1 = 0;
        $sumS2 = 0;
        $cntS2 = 0;
        $sumS3 = 0;
        $cntS3 = 0;
        $sumS4 = 0;
        $cntS4 = 0;
        $sumAts = 0;
        $cntAts = 0;
        $sumAas = 0;
        $cntAas = 0;
        $sumNA = 0;
        $cntNA = 0;

        foreach ($records as $r) {
            $s1 = (float) ($r['s1'] ?? 0);
            $s2 = (float) ($r['s2'] ?? 0);
            $s3 = (float) ($r['s3'] ?? 0);
            $s4 = (float) ($r['s4'] ?? 0);
            $ats = (float) ($r['ats'] ?? 0);
            $aas = (float) ($r['aas'] ?? 0);
            $na = (float) ($r['nilai_akhir'] ?? 0);

            if ($s1 > 0) {
                $sumS1 += $s1;
                $cntS1++;
            }
            if ($s2 > 0) {
                $sumS2 += $s2;
                $cntS2++;
            }
            if ($s3 > 0) {
                $sumS3 += $s3;
                $cntS3++;
            }
            if ($s4 > 0) {
                $sumS4 += $s4;
                $cntS4++;
            }
            if ($ats > 0) {
                $sumAts += $ats;
                $cntAts++;
            }
            if ($aas > 0) {
                $sumAas += $aas;
                $cntAas++;
            }
            if ($na > 0) {
                $sumNA += $na;
                $cntNA++;
            }
        }

        $avgS1 = $cntS1 > 0 ? round($sumS1 / $cntS1, 1) : 0;
        $avgS2 = $cntS2 > 0 ? round($sumS2 / $cntS2, 1) : 0;
        $avgS3 = $cntS3 > 0 ? round($sumS3 / $cntS3, 1) : 0;
        $avgS4 = $cntS4 > 0 ? round($sumS4 / $cntS4, 1) : 0;
        $avgAts = $cntAts > 0 ? round($sumAts / $cntAts, 1) : 0;
        $avgAas = $cntAas > 0 ? round($sumAas / $cntAas, 1) : 0;
        $avgNA = $cntNA > 0 ? round($sumNA / $cntNA, 1) : 0;

        // Tentukan Score Utama yang dijadikan tolok ukur Ranking
        $primaryScore = 0;
        $primaryTotal = 0;
        $primaryCount = 0;

        switch ($selectedKategori) {
            case 's1':
                $primaryScore = $avgS1;
                $primaryTotal = $sumS1;
                $primaryCount = $cntS1;
                break;
            case 's2':
                $primaryScore = $avgS2;
                $primaryTotal = $sumS2;
                $primaryCount = $cntS2;
                break;
            case 's3':
                $primaryScore = $avgS3;
                $primaryTotal = $sumS3;
                $primaryCount = $cntS3;
                break;
            case 's4':
                $primaryScore = $avgS4;
                $primaryTotal = $sumS4;
                $primaryCount = $cntS4;
                break;
            case 'ats':
                $primaryScore = $avgAts;
                $primaryTotal = $sumAts;
                $primaryCount = $cntAts;
                break;
            case 'aas':
                $primaryScore = $avgAas;
                $primaryTotal = $sumAas;
                $primaryCount = $cntAas;
                break;
            case 'all':
            default:
                $primaryScore = $avgNA;
                $primaryTotal = $sumNA;
                $primaryCount = $cntNA;
                break;
        }

        // Predikat nilai
        $predikat = '-';
        if ($primaryScore >= 90) {
            $predikat = 'A';
        } elseif ($primaryScore >= 80) {
            $predikat = 'B';
        } elseif ($primaryScore >= 70) {
            $predikat = 'C';
        } elseif ($primaryScore > 0) {
            $predikat = 'D';
        }

        $rankedList[] = [
            'siswa_id' => $sid,
            'nis' => $s['nis'],
            'nisn' => $s['nisn'],
            'nama' => $s['nama'],
            'jk' => $s['jenis_kelamin'],
            'foto' => $s['foto'],
            'avg_s1' => $avgS1,
            'avg_s2' => $avgS2,
            'avg_s3' => $avgS3,
            'avg_s4' => $avgS4,
            'avg_ats' => $avgAts,
            'avg_aas' => $avgAas,
            'avg_na' => $avgNA,
            'total_mapel_dinilai' => $primaryCount,
            'total_poin' => $primaryTotal,
            'score_utama' => $primaryScore,
            'predikat' => $predikat
        ];
    }

    // URUTKAN LIST BERDASARKAN SCORE UTAMA (Tertinggi -> Terendah)
    // Jika score sama, urutkan berdasarkan Total Poin, lalu Nama ASC
    usort($rankedList, function ($a, $b) {
        if ($b['score_utama'] != $a['score_utama']) {
            return ($b['score_utama'] > $a['score_utama']) ? 1 : -1;
        }
        if ($b['total_poin'] != $a['total_poin']) {
            return ($b['total_poin'] > $a['total_poin']) ? 1 : -1;
        }
        return strcasecmp($a['nama'], $b['nama']);
    });

    // Berikan posisi rank
    $currentRank = 1;
    $prevScore = -1;
    $prevRank = 1;
    $totalAllScores = 0;
    $countGradedStudents = 0;
    $lowestScoreFound = 100;
    $highestScoreFound = 0;

    foreach ($rankedList as $idx => &$item) {
        $item['rank'] = $idx + 1; // Peringkat ordinal

        if ($item['score_utama'] > 0) {
            $totalAllScores += $item['score_utama'];
            $countGradedStudents++;
            if ($item['score_utama'] > $highestScoreFound) {
                $highestScoreFound = $item['score_utama'];
            }
            if ($item['score_utama'] < $lowestScoreFound) {
                $lowestScoreFound = $item['score_utama'];
            }
        }
    }
    unset($item);

    if ($countGradedStudents > 0) {
        $statsAvgKelas = round($totalAllScores / $countGradedStudents, 1);
        $statsHighest = $highestScoreFound;
        $statsLowest = $lowestScoreFound;
        $topPerformer = $rankedList[0] ?? null;
    } else {
        $statsLowest = 0;
    }
}

// Label Kategori Terpilih
$kategoriTitles = [
    'all' => 'Nilai Akhir Rapor (Kompilasi)',
    's1' => 'Sumatif 1 (TP 1)',
    's2' => 'Sumatif 2 (TP 2)',
    's3' => 'Sumatif 3 (TP 3)',
    's4' => 'Sumatif 4 (TP 4)',
    'ats' => 'Asesmen Tengah Semester (ATS)',
    'aas' => 'Asesmen Akhir Semester (AAS)'
];
$currentKategoriTitle = $kategoriTitles[$selectedKategori] ?? 'Nilai Akhir Rapor';

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    /* ===== LEADERBOARD & RANKING DESIGN SYSTEM ===== */
    .ranking-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 45%, #1e3a8a 100%);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 20px;
        color: #ffffff;
        padding: 1.75rem 2rem;
        box-shadow: 0 14px 35px -8px rgba(15, 23, 42, 0.25);
        position: relative;
        overflow: hidden;
    }

    .ranking-hero::after {
        content: '';
        position: absolute;
        right: -30px;
        bottom: -30px;
        width: 240px;
        height: 240px;
        background: radial-gradient(circle, rgba(245, 158, 11, 0.25) 0%, rgba(255, 255, 255, 0) 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    /* SUB TABS NAVIGATION */
    .nav-tabs-wali {
        border-bottom: 2px solid #e2e8f0;
        gap: 6px;
    }

    .nav-tabs-wali .nav-link {
        color: #64748b;
        font-weight: 700;
        border: none;
        border-bottom: 3px solid transparent;
        padding: 0.8rem 1.3rem;
        border-radius: 0;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
        background: transparent;
        font-size: 0.88rem;
    }

    .nav-tabs-wali .nav-link:hover {
        color: #1e3a8a;
        border-bottom-color: #cbd5e1;
    }

    .nav-tabs-wali .nav-link.active {
        color: #1e3a8a;
        border-bottom-color: #1e3a8a;
        background: transparent;
    }

    /* SEGMENTED CATEGORY PILLS */
    .category-pills-wrap {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        background: #f8fafc;
        padding: 8px;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
    }

    .category-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 12px;
        font-size: 0.82rem;
        font-weight: 700;
        color: #475569;
        text-decoration: none;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        background: #ffffff;
        border: 1px solid #e2e8f0;
    }

    .category-pill:hover {
        color: #1e3a8a;
        border-color: #93c5fd;
        background: #f0fdf4;
        transform: translateY(-1px);
    }

    .category-pill.active {
        background: linear-gradient(135deg, #1e3a8a 0%, #1e1b4b 100%);
        color: #ffffff;
        border-color: transparent;
        box-shadow: 0 4px 12px rgba(30, 58, 138, 0.25);
    }

    /* STAT CARD */
    .stat-box-modern {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 1.2rem 1.3rem;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
        transition: all 0.25s ease;
    }

    .stat-box-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
        border-color: #93c5fd;
    }

    /* 3D PODIUM SHOWCASE */
    .podium-container {
        display: flex;
        align-items: flex-end;
        justify-content: center;
        gap: 16px;
        padding: 2.5rem 1rem 1rem 1rem;
        position: relative;
    }

    .podium-card {
        flex: 1;
        max-width: 260px;
        background: #ffffff;
        border-radius: 20px;
        padding: 1.5rem 1rem 1.25rem 1rem;
        text-align: center;
        position: relative;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        border: 2px solid #f1f5f9;
        transition: all 0.3s ease;
    }

    .podium-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
    }

    .podium-card.rank-1 {
        border-color: #fbbf24;
        background: linear-gradient(180deg, #fffbeb 0%, #ffffff 40%);
        transform: translateY(-14px);
        box-shadow: 0 14px 35px rgba(245, 158, 11, 0.2);
    }

    .podium-card.rank-1:hover {
        transform: translateY(-20px);
    }

    .podium-card.rank-2 {
        border-color: #cbd5e1;
        background: linear-gradient(180deg, #f8fafc 0%, #ffffff 40%);
    }

    .podium-card.rank-3 {
        border-color: #fed7aa;
        background: linear-gradient(180deg, #fff7ed 0%, #ffffff 40%);
    }

    .podium-avatar-wrap {
        position: relative;
        width: 76px;
        height: 76px;
        margin: 0 auto 12px auto;
    }

    .podium-avatar-wrap.rank-1 {
        width: 88px;
        height: 88px;
    }

    .podium-avatar {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #ffffff;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
    }

    .podium-avatar-fallback {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 1.4rem;
        border: 3px solid #ffffff;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
    }

    .crown-icon {
        position: absolute;
        top: -24px;
        left: 50%;
        transform: translateX(-50%);
        font-size: 1.8rem;
        color: #f59e0b;
        filter: drop-shadow(0 2px 6px rgba(245, 158, 11, 0.4));
        animation: floatCrown 2.5s ease-in-out infinite;
    }

    @keyframes floatCrown {

        0%,
        100% {
            transform: translateX(-50%) translateY(0);
        }

        50% {
            transform: translateX(-50%) translateY(-4px);
        }
    }

    .podium-rank-badge {
        position: absolute;
        bottom: -6px;
        left: 50%;
        transform: translateX(-50%);
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.72rem;
        font-weight: 900;
        color: #ffffff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .badge-gold {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }

    .badge-silver {
        background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
    }

    .badge-bronze {
        background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
    }

    /* TABLE RANKING */
    .table-ranking {
        margin-bottom: 0;
    }

    .table-ranking th {
        background: #0f172a;
        color: #ffffff;
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        padding: 0.85rem 0.75rem;
        font-weight: 800;
        vertical-align: middle;
    }

    .table-ranking td {
        padding: 0.85rem 0.75rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.88rem;
    }

    .table-ranking tr:hover td {
        background-color: #f8fafc;
    }

    .table-ranking tr.top-1 td {
        background-color: #fffbeb !important;
    }

    .table-ranking tr.top-2 td {
        background-color: #f8fafc !important;
    }

    .table-ranking tr.top-3 td {
        background-color: #fff7ed !important;
    }

    .rank-circle {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        font-size: 0.85rem;
    }

    .rank-circle-1 {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: #ffffff;
        box-shadow: 0 2px 8px rgba(245, 158, 11, 0.35);
    }

    .rank-circle-2 {
        background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
        color: #ffffff;
        box-shadow: 0 2px 8px rgba(100, 116, 139, 0.35);
    }

    .rank-circle-3 {
        background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
        color: #ffffff;
        box-shadow: 0 2px 8px rgba(234, 88, 12, 0.35);
    }

    .rank-circle-normal {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
    }

    .score-badge-main {
        font-size: 1.05rem;
        font-weight: 900;
        font-family: monospace;
        padding: 4px 12px;
        border-radius: 10px;
        display: inline-block;
    }

    .score-high {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }

    .score-med {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
    }

    .score-low {
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }

    /* PROGRESS BAR */
    .progress-score {
        height: 6px;
        border-radius: 6px;
        background: #e2e8f0;
        overflow: hidden;
        margin-top: 4px;
    }

    .progress-score .progress-bar {
        border-radius: 6px;
    }

    @media print {

        .no-print,
        .btn,
        .nav-tabs-wali,
        .category-pills-wrap,
        form {
            display: none !important;
        }

        .podium-container {
            padding-top: 0 !important;
        }

        body {
            background: #ffffff !important;
        }

        .card {
            border: 1px solid #cbd5e1 !important;
            box-shadow: none !important;
        }
    }
</style>

<div class="container-fluid px-0">
    <!-- HERO HEADER BANNER -->
    <div class="ranking-hero mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded-4 text-white d-flex align-items-center justify-content-center shadow-sm flex-shrink-0"
                    style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); width: 62px; height: 62px;">
                    <i class="bi bi-trophy-fill fs-2"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span
                            class="badge bg-warning bg-opacity-25 text-warning border border-warning rounded-pill px-2.5 py-0.5 small fw-bold">
                            <i class="bi bi-award-fill me-1"></i> Leaderboard Akademik
                        </span>
                        <span
                            class="badge bg-white bg-opacity-15 text-white border border-white border-opacity-25 rounded-pill px-2.5 py-0.5 small font-monospace">
                            Semester <?= htmlspecialchars($selectedSem) ?> <?= htmlspecialchars($selectedTahun) ?>
                        </span>
                    </div>
                    <h3 class="fw-black m-0 text-white" style="letter-spacing: -0.5px;">
                        Peringkat & Leaderboard Santri
                        <?= $currentKelas ? 'Kelas ' . htmlspecialchars($currentKelas['nama_kelas']) : '' ?>
                    </h3>
                    <small class="text-white text-opacity-80">
                        Wali Kelas: <strong><?= htmlspecialchars($currentKelas['nama_wali_kelas'] ?? '—') ?></strong> •
                        Tingkat <?= htmlspecialchars($currentKelas['tingkat'] ?? '-') ?>
                    </small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" onclick="window.print()"
                    class="btn btn-light rounded-pill px-3.5 py-2 fw-bold text-dark shadow-sm d-inline-flex align-items-center gap-2"
                    style="font-size: 0.85rem;">
                    <i class="bi bi-printer-fill text-primary"></i> Cetak Leaderboard (PDF)
                </button>
                <a href="<?= BASE_URL ?>/pages/wali-kelas/index.php?kelas_id=<?= $selectedKelasId ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>"
                    class="btn btn-outline-light rounded-pill px-3.5 py-2 fw-bold d-inline-flex align-items-center gap-2"
                    style="font-size: 0.85rem;">
                    <i class="bi bi-arrow-left"></i> Rekap Nilai
                </a>
            </div>
        </div>
    </div>

    <!-- SUB TABS NAVIGATION WALI KELAS -->
    <ul class="nav nav-tabs nav-tabs-wali mb-4">
        <li class="nav-item">
            <a class="nav-link"
                href="<?= BASE_URL ?>/pages/wali-kelas/index.php?kelas_id=<?= $selectedKelasId ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>">
                <i class="bi bi-journal-check fs-5 text-primary"></i> Rekap Nilai & Catatan Guru (Pertemuan Ortu)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active"
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

    <!-- FILTER & SELECTION BAR -->
    <div class="card border-0 rounded-4 shadow-sm p-3.5 p-md-4 mb-4 bg-white"
        style="border: 1px solid #e2e8f0 !important;">
        <form method="GET" class="row g-3 align-items-end mb-3">
            <input type="hidden" name="kategori" value="<?= htmlspecialchars($selectedKategori) ?>">

            <div class="col-lg-3 col-md-6">
                <label class="form-label small fw-bold text-secondary mb-1"
                    style="font-size: 0.75rem; letter-spacing: 0.4px;">
                    <i class="bi bi-building me-1"></i> 1. KELAS SANTRI
                </label>
                <select name="kelas_id" class="form-select rounded-3 fw-semibold"
                    style="height: 42px; font-size: 0.85rem;" onchange="this.form.submit()">
                    <?php foreach ($waliClasses as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= $selectedKelasId == $k['id'] ? 'selected' : '' ?>>
                            Kelas <?= htmlspecialchars($k['nama_kelas']) ?> (Tingkat <?= htmlspecialchars($k['tingkat']) ?>)
                            <?= !empty($k['nama_wali_kelas']) && !$isGuruRole ? ' - ' . htmlspecialchars($k['nama_wali_kelas']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-4 col-md-6">
                <label class="form-label small fw-bold text-secondary mb-1"
                    style="font-size: 0.75rem; letter-spacing: 0.4px;">
                    <i class="bi bi-book me-1"></i> 2. MATA PELAJARAN
                </label>
                <select name="mapel" class="form-select rounded-3 fw-semibold" style="height: 42px; font-size: 0.85rem;"
                    onchange="this.form.submit()">
                    <option value="all" <?= $selectedMapel === 'all' ? 'selected' : '' ?>>🌟 Semua Mata Pelajaran
                        (Akumulatif)</option>
                    <?php foreach ($listMapel as $m): ?>
                        <option value="<?= htmlspecialchars($m['nama_mapel']) ?>" <?= $selectedMapel === $m['nama_mapel'] ? 'selected' : '' ?>>
                            📘 <?= htmlspecialchars($m['nama_mapel']) ?> (<?= htmlspecialchars($m['kelompok']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-2 col-6">
                <label class="form-label small fw-bold text-secondary mb-1"
                    style="font-size: 0.75rem; letter-spacing: 0.4px;">
                    <i class="bi bi-calendar3 me-1"></i> SEMESTER
                </label>
                <select name="semester" class="form-select rounded-3 fw-semibold"
                    style="height: 42px; font-size: 0.85rem;" onchange="this.form.submit()">
                    <option value="Ganjil" <?= $selectedSem === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                    <option value="Genap" <?= $selectedSem === 'Genap' ? 'selected' : '' ?>>Genap</option>
                </select>
            </div>

            <div class="col-lg-2 col-6">
                <label class="form-label small fw-bold text-secondary mb-1"
                    style="font-size: 0.75rem; letter-spacing: 0.4px;">THN AJARAN</label>
                <input type="text" name="tahun_ajaran" class="form-control font-monospace fw-bold"
                    style="height: 42px; font-size: 0.85rem;" value="<?= htmlspecialchars($selectedTahun) ?>">
            </div>

            <div class="col-lg-1 col-12">
                <button type="submit"
                    class="btn text-white w-100 fw-bold rounded-3 shadow-sm d-inline-flex align-items-center justify-content-center"
                    style="height: 42px; background: linear-gradient(135deg, #1e3a8a 0%, #1e1b4b 100%); border: none; font-size: 0.86rem;">
                    <i class="bi bi-filter"></i>
                </button>
            </div>
        </form>

        <!-- KATEGORI RANKING PILLS -->
        <div>
            <label class="form-label small fw-bold text-secondary mb-2"
                style="font-size: 0.75rem; letter-spacing: 0.4px;">
                <i class="bi bi-award me-1"></i> TOLOK UKUR ASESMEN / KOMPONEN NILAI:
            </label>
            <div class="category-pills-wrap">
                <a href="ranking.php?kelas_id=<?= $selectedKelasId ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>&mapel=<?= urlencode($selectedMapel) ?>&kategori=all"
                    class="category-pill <?= $selectedKategori === 'all' ? 'active' : '' ?>">
                    <i class="bi bi-trophy-fill"></i> 🏆 Keseluruhan (Nilai Akhir Rapor)
                </a>
                <a href="ranking.php?kelas_id=<?= $selectedKelasId ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>&mapel=<?= urlencode($selectedMapel) ?>&kategori=s1"
                    class="category-pill <?= $selectedKategori === 's1' ? 'active' : '' ?>">
                    <i class="bi bi-journal-text"></i> Sumatif 1 (TP 1)
                </a>
                <a href="ranking.php?kelas_id=<?= $selectedKelasId ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>&mapel=<?= urlencode($selectedMapel) ?>&kategori=s2"
                    class="category-pill <?= $selectedKategori === 's2' ? 'active' : '' ?>">
                    <i class="bi bi-journal-text"></i> Sumatif 2 (TP 2)
                </a>
                <a href="ranking.php?kelas_id=<?= $selectedKelasId ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>&mapel=<?= urlencode($selectedMapel) ?>&kategori=s3"
                    class="category-pill <?= $selectedKategori === 's3' ? 'active' : '' ?>">
                    <i class="bi bi-journal-text"></i> Sumatif 3 (TP 3)
                </a>
                <a href="ranking.php?kelas_id=<?= $selectedKelasId ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>&mapel=<?= urlencode($selectedMapel) ?>&kategori=s4"
                    class="category-pill <?= $selectedKategori === 's4' ? 'active' : '' ?>">
                    <i class="bi bi-journal-text"></i> Sumatif 4 (TP 4)
                </a>
                <a href="ranking.php?kelas_id=<?= $selectedKelasId ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>&mapel=<?= urlencode($selectedMapel) ?>&kategori=ats"
                    class="category-pill <?= $selectedKategori === 'ats' ? 'active' : '' ?>">
                    <i class="bi bi-graph-up"></i> 📊 ATS (Tengah Sem)
                </a>
                <a href="ranking.php?kelas_id=<?= $selectedKelasId ?>&semester=<?= urlencode($selectedSem) ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>&mapel=<?= urlencode($selectedMapel) ?>&kategori=aas"
                    class="category-pill <?= $selectedKategori === 'aas' ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-check"></i> 📑 AAS (Akhir Sem)
                </a>
            </div>
        </div>
    </div>

    <!-- STATISTIK RINGKAS KELAS -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-6">
            <div class="stat-box-modern d-flex align-items-center gap-3">
                <div class="rounded-4 text-white d-flex align-items-center justify-content-center shadow-sm flex-shrink-0"
                    style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); width: 50px; height: 50px;">
                    <i class="bi bi-award fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-bold text-uppercase"
                        style="letter-spacing: 0.5px; font-size: 0.7rem;">Peringkat 1 (Tertinggi)</span>
                    <h4 class="fw-black text-dark m-0" style="font-size: 1.4rem;">
                        <?= $statsHighest > 0 ? number_format($statsHighest, 1) : '—' ?>
                        <span class="fs-7 fw-normal text-muted">/ 100</span>
                    </h4>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-6">
            <div class="stat-box-modern d-flex align-items-center gap-3">
                <div class="rounded-4 text-white d-flex align-items-center justify-content-center shadow-sm flex-shrink-0"
                    style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); width: 50px; height: 50px;">
                    <i class="bi bi-graph-up-arrow fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-bold text-uppercase"
                        style="letter-spacing: 0.5px; font-size: 0.7rem;">Rata-rata Kelas</span>
                    <h4 class="fw-black text-dark m-0" style="font-size: 1.4rem;">
                        <?= $statsAvgKelas > 0 ? number_format($statsAvgKelas, 1) : '—' ?>
                        <span class="fs-7 fw-normal text-muted">/ 100</span>
                    </h4>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-6">
            <div class="stat-box-modern d-flex align-items-center gap-3">
                <div class="rounded-4 text-white d-flex align-items-center justify-content-center shadow-sm flex-shrink-0"
                    style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); width: 50px; height: 50px;">
                    <i class="bi bi-people-fill fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-bold text-uppercase"
                        style="letter-spacing: 0.5px; font-size: 0.7rem;">Total Santri Terdata</span>
                    <h4 class="fw-black text-dark m-0" style="font-size: 1.4rem;">
                        <?= number_format($statsTotalSiswa) ?> <span class="fs-7 fw-normal text-muted">Santri</span>
                    </h4>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-6">
            <div class="stat-box-modern d-flex align-items-center gap-3">
                <div class="rounded-4 text-white d-flex align-items-center justify-content-center shadow-sm flex-shrink-0"
                    style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); width: 50px; height: 50px;">
                    <i class="bi bi-bookmark-check-fill fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-bold text-uppercase"
                        style="letter-spacing: 0.5px; font-size: 0.7rem;">Kategori Aktif</span>
                    <h4 class="fw-black text-dark m-0" style="font-size: 1.15rem;">
                        <?= strtoupper($selectedKategori) ?>
                    </h4>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($rankedList) && $statsHighest > 0): ?>
        <!-- TOP 3 PODIUM CELEBRATION -->
        <div class="card border-0 rounded-4 shadow-sm mb-4 bg-white overflow-hidden"
            style="border: 1px solid #e2e8f0 !important;">
            <div class="card-header bg-white p-3.5 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="fw-extrabold text-dark mb-0">
                        <i class="bi bi-trophy text-warning me-2"></i> Podium 3 Besar Santri Berprestasi
                    </h5>
                    <small class="text-muted">Berdasarkan <strong><?= htmlspecialchars($currentKategoriTitle) ?></strong>
                        <?= $selectedMapel !== 'all' ? '• ' . htmlspecialchars($selectedMapel) : '' ?></small>
                </div>
                <span
                    class="badge bg-warning bg-opacity-15 text-dark border border-warning-subtle rounded-pill px-3 py-1 fw-bold">
                    🌟 Top 3 Performers
                </span>
            </div>
            <div class="card-body p-4">
                <div class="podium-container">
                    <!-- JUARA 2 (SILVER) -->
                    <?php if (isset($rankedList[1])):
                        $s2 = $rankedList[1]; ?>
                        <div class="podium-card rank-2">
                            <div class="podium-avatar-wrap">
                                <?php if (!empty($s2['foto']) && file_exists(__DIR__ . '/../../uploads/siswa/' . $s2['foto'])): ?>
                                    <img src="<?= BASE_URL ?>/uploads/siswa/<?= htmlspecialchars($s2['foto']) ?>"
                                        class="podium-avatar" alt="<?= htmlspecialchars($s2['nama']) ?>">
                                <?php else: ?>
                                    <div class="podium-avatar-fallback bg-secondary bg-opacity-10 text-secondary">
                                        <?= strtoupper(substr($s2['nama'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <span class="podium-rank-badge badge-silver">#2 SILVER</span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1 text-truncate" title="<?= htmlspecialchars($s2['nama']) ?>">
                                <?= htmlspecialchars($s2['nama']) ?>
                            </h6>
                            <small class="text-muted d-block font-monospace mb-2">NIS:
                                <?= htmlspecialchars($s2['nis'] ?: '—') ?></small>
                            <div class="score-badge-main score-med">
                                <?= number_format($s2['score_utama'], 1) ?>
                            </div>
                            <small class="d-block text-muted mt-1 fw-semibold">Predikat
                                <?= htmlspecialchars($s2['predikat']) ?></small>
                        </div>
                    <?php endif; ?>

                    <!-- JUARA 1 (GOLD) -->
                    <?php if (isset($rankedList[0])):
                        $s1 = $rankedList[0]; ?>
                        <div class="podium-card rank-1">
                            <div class="podium-avatar-wrap rank-1">
                                <i class="bi bi-award-fill crown-icon"></i>
                                <?php if (!empty($s1['foto']) && file_exists(__DIR__ . '/../../uploads/siswa/' . $s1['foto'])): ?>
                                    <img src="<?= BASE_URL ?>/uploads/siswa/<?= htmlspecialchars($s1['foto']) ?>"
                                        class="podium-avatar" alt="<?= htmlspecialchars($s1['nama']) ?>">
                                <?php else: ?>
                                    <div class="podium-avatar-fallback bg-warning bg-opacity-15 text-warning border-warning">
                                        <?= strtoupper(substr($s1['nama'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <span class="podium-rank-badge badge-gold">👑 #1 GOLD</span>
                            </div>
                            <h5 class="fw-black text-dark mb-1 text-truncate" title="<?= htmlspecialchars($s1['nama']) ?>">
                                <?= htmlspecialchars($s1['nama']) ?>
                            </h5>
                            <small class="text-muted d-block font-monospace mb-2">NIS:
                                <?= htmlspecialchars($s1['nis'] ?: '—') ?></small>
                            <div class="score-badge-main score-high" style="font-size: 1.25rem;">
                                <?= number_format($s1['score_utama'], 1) ?>
                            </div>
                            <small class="d-block text-success mt-1 fw-bold">Predikat <?= htmlspecialchars($s1['predikat']) ?> •
                                Peringkat 1</small>
                        </div>
                    <?php endif; ?>

                    <!-- JUARA 3 (BRONZE) -->
                    <?php if (isset($rankedList[2])):
                        $s3 = $rankedList[2]; ?>
                        <div class="podium-card rank-3">
                            <div class="podium-avatar-wrap">
                                <?php if (!empty($s3['foto']) && file_exists(__DIR__ . '/../../uploads/siswa/' . $s3['foto'])): ?>
                                    <img src="<?= BASE_URL ?>/uploads/siswa/<?= htmlspecialchars($s3['foto']) ?>"
                                        class="podium-avatar" alt="<?= htmlspecialchars($s3['nama']) ?>">
                                <?php else: ?>
                                    <div class="podium-avatar-fallback bg-warning bg-opacity-10 text-danger">
                                        <?= strtoupper(substr($s3['nama'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <span class="podium-rank-badge badge-bronze">#3 BRONZE</span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1 text-truncate" title="<?= htmlspecialchars($s3['nama']) ?>">
                                <?= htmlspecialchars($s3['nama']) ?>
                            </h6>
                            <small class="text-muted d-block font-monospace mb-2">NIS:
                                <?= htmlspecialchars($s3['nis'] ?: '—') ?></small>
                            <div class="score-badge-main score-med">
                                <?= number_format($s3['score_utama'], 1) ?>
                            </div>
                            <small class="d-block text-muted mt-1 fw-semibold">Predikat
                                <?= htmlspecialchars($s3['predikat']) ?></small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- MAIN RANKING TABLE -->
    <div class="card border-0 rounded-4 shadow-sm overflow-hidden bg-white mb-4"
        style="border: 1px solid #e2e8f0 !important;">
        <div
            class="card-header bg-white p-3.5 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h5 class="fw-extrabold text-dark mb-0">
                    <i class="bi bi-list-ol text-primary me-2"></i> Tabel Peringkat Lengkap Santri
                </h5>
                <small class="text-muted">Diurutkan secara otomatis dari nilai tertinggi ke terendah</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="width: 240px;">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="searchSantri" class="form-control border-start-0"
                        placeholder="Cari nama santri...">
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-ranking align-middle mb-0" id="tableLeaderboard">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 75px;">Rank</th>
                            <th style="min-width: 260px;">Nama Santri</th>
                            <th style="width: 120px;">NIS / NISN</th>
                            <?php if ($selectedKategori === 'all'): ?>
                                <th class="text-center" style="width: 80px;" title="Rata-rata Sumatif 1">S1</th>
                                <th class="text-center" style="width: 80px;" title="Rata-rata Sumatif 2">S2</th>
                                <th class="text-center" style="width: 80px;" title="Rata-rata Sumatif 3">S3</th>
                                <th class="text-center" style="width: 80px;" title="Rata-rata Sumatif 4">S4</th>
                                <th class="text-center" style="width: 85px;" title="Asesmen Tengah Semester">ATS</th>
                                <th class="text-center" style="width: 85px;" title="Asesmen Akhir Semester">AAS</th>
                                <th class="text-center" style="width: 100px;">Total Poin</th>
                                <th class="text-center" style="width: 130px;">Rata-rata Rapor</th>
                            <?php else: ?>
                                <th class="text-center" style="width: 140px;">Mapel Dinilai</th>
                                <th class="text-center" style="width: 140px;">Total Skor</th>
                                <th class="text-center" style="width: 160px;">Skor <?= strtoupper($selectedKategori) ?></th>
                            <?php endif; ?>
                            <th class="text-center" style="width: 90px;">Predikat</th>
                            <th style="min-width: 140px;">Visual Performa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rankedList)): ?>
                            <tr>
                                <td colspan="12" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary text-opacity-50"></i>
                                    Belum ada data santri atau nilai pada kelas dan semester yang dipilih.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rankedList as $idx => $s):
                                $rank = $idx + 1;
                                $rowClass = ($rank === 1) ? 'top-1' : (($rank === 2) ? 'top-2' : (($rank === 3) ? 'top-3' : ''));
                                $circleClass = ($rank === 1) ? 'rank-circle-1' : (($rank === 2) ? 'rank-circle-2' : (($rank === 3) ? 'rank-circle-3' : 'rank-circle-normal'));
                                $scoreVal = (float) $s['score_utama'];
                                $scoreClass = ($scoreVal >= 85) ? 'score-high' : (($scoreVal >= 75) ? 'score-med' : 'score-low');
                                $barClass = ($scoreVal >= 85) ? 'bg-success' : (($scoreVal >= 75) ? 'bg-primary' : 'bg-danger');
                                ?>
                                <tr class="<?= $rowClass ?> santri-row"
                                    data-name="<?= strtolower(htmlspecialchars($s['nama'])) ?>">
                                    <td class="text-center">
                                        <span class="rank-circle <?= $circleClass ?>">
                                            <?php if ($rank === 1): ?>
                                                <i class="bi bi-trophy-fill"></i>
                                            <?php elseif ($rank === 2): ?>
                                                2
                                            <?php elseif ($rank === 3): ?>
                                                3
                                            <?php else: ?>
                                                <?= $rank ?>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2.5">
                                            <?php if (!empty($s['foto']) && file_exists(__DIR__ . '/../../uploads/siswa/' . $s['foto'])): ?>
                                                <img src="<?= BASE_URL ?>/uploads/siswa/<?= htmlspecialchars($s['foto']) ?>"
                                                    class="rounded-circle object-fit-cover border flex-shrink-0"
                                                    style="width: 38px; height: 38px;">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center border flex-shrink-0"
                                                    style="width: 38px; height: 38px; font-size: 0.9rem;">
                                                    <?= strtoupper(substr($s['nama'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong
                                                    class="d-block text-dark fw-bold"><?= htmlspecialchars($s['nama']) ?></strong>
                                                <small
                                                    class="text-muted"><?= $s['jk'] === 'P' ? 'Perempuan' : 'Laki-laki' ?></small>
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

                                    <?php if ($selectedKategori === 'all'): ?>
                                        <td class="text-center font-monospace small">
                                            <?= $s['avg_s1'] > 0 ? number_format($s['avg_s1'], 1) : '—' ?>
                                        </td>
                                        <td class="text-center font-monospace small">
                                            <?= $s['avg_s2'] > 0 ? number_format($s['avg_s2'], 1) : '—' ?>
                                        </td>
                                        <td class="text-center font-monospace small">
                                            <?= $s['avg_s3'] > 0 ? number_format($s['avg_s3'], 1) : '—' ?>
                                        </td>
                                        <td class="text-center font-monospace small">
                                            <?= $s['avg_s4'] > 0 ? number_format($s['avg_s4'], 1) : '—' ?>
                                        </td>
                                        <td class="text-center font-monospace small fw-bold text-primary">
                                            <?= $s['avg_ats'] > 0 ? number_format($s['avg_ats'], 1) : '—' ?>
                                        </td>
                                        <td class="text-center font-monospace small fw-bold text-success">
                                            <?= $s['avg_aas'] > 0 ? number_format($s['avg_aas'], 1) : '—' ?>
                                        </td>
                                        <td class="text-center font-monospace small fw-semibold text-secondary">
                                            <?= $s['total_poin'] > 0 ? number_format($s['total_poin'], 1) : '—' ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="score-badge-main <?= $scoreClass ?>">
                                                <?= $scoreVal > 0 ? number_format($scoreVal, 1) : '—' ?>
                                            </span>
                                        </td>
                                    <?php else: ?>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border px-2.5 py-1">
                                                <?= $s['total_mapel_dinilai'] ?> Mapel
                                            </span>
                                        </td>
                                        <td class="text-center font-monospace fw-semibold text-secondary">
                                            <?= $s['total_poin'] > 0 ? number_format($s['total_poin'], 1) : '—' ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="score-badge-main <?= $scoreClass ?>">
                                                <?= $scoreVal > 0 ? number_format($scoreVal, 1) : '—' ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>

                                    <td class="text-center fw-bold font-monospace">
                                        <span
                                            class="badge <?= $scoreVal >= 85 ? 'bg-success' : ($scoreVal >= 75 ? 'bg-primary' : 'bg-secondary') ?> bg-opacity-15 <?= $scoreVal >= 85 ? 'text-success' : ($scoreVal >= 75 ? 'text-primary' : 'text-secondary') ?> border px-2.5 py-1">
                                            <?= htmlspecialchars($s['predikat']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress progress-score flex-grow-1">
                                                <div class="progress-bar <?= $barClass ?>" role="progressbar"
                                                    style="width: <?= min(100, max(0, $scoreVal)) ?>%"></div>
                                            </div>
                                            <small class="font-monospace text-muted fw-bold"
                                                style="font-size: 0.72rem; min-width: 32px;"><?= (int) $scoreVal ?>%</small>
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

<script>
    // Live Search Filter by Student Name
    document.getElementById('searchSantri').addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        const rows = document.querySelectorAll('#tableLeaderboard .santri-row');
        rows.forEach(row => {
            const name = row.getAttribute('data-name') || '';
            if (name.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>