<?php
/**
 * CETAK REKAPITULASI NILAI & CATATAN WALI KELAS (PDF READY)
 * Islamic School of Minhaj Al-Ilmi
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('wali_kelas');

$pdo = getConnection();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$userRole = strtolower($_SESSION['role'] ?? '');
$isGuruRole = ($userRole === 'guru');

$kelasId = (int) ($_GET['kelas_id'] ?? 0);
$filterMapel = trim($_GET['filter_mapel'] ?? 'all');
$filterSumatif = trim($_GET['filter_sumatif'] ?? 'all');
$sem = trim($_GET['semester'] ?? 'Ganjil');
$tahun = trim($_GET['tahun_ajaran'] ?? (date('Y') . '/' . (date('Y') + 1)));

// Validasi akses kelas untuk wali kelas jika role Guru
if ($isGuruRole) {
    $stmtCheck = $pdo->prepare("SELECT id FROM kelas WHERE id = :kid AND wali_kelas_id = :uid LIMIT 1");
    $stmtCheck->execute([':kid' => $kelasId, ':uid' => $userId]);
    if (!$stmtCheck->fetch()) {
        die("<div style='font-family:sans-serif;padding:2rem;text-align:center;color:#ef4444;'><h3>Akses Ditolak</h3><p>Anda tidak terdaftar sebagai Wali Kelas untuk kelas ini.</p></div>");
    }
}

// Load Data Kelas & Wali
$stmtK = $pdo->prepare("
    SELECT k.*, u.nama_lengkap AS nama_wali_kelas, gd.nip AS nip_wali_kelas
    FROM kelas k
    LEFT JOIN users u ON k.wali_kelas_id = u.id
    LEFT JOIN guru_detail gd ON u.id = gd.user_id
    WHERE k.id = :kid
");
$stmtK->execute([':kid' => $kelasId]);
$kelas = $stmtK->fetch();
$namaKelas = $kelas['nama_kelas'] ?? '—';
$namaWali = $kelas['nama_wali_kelas'] ?? ($_SESSION['nama_lengkap'] ?? 'Wali Kelas');
$nipWali = $kelas['nip_wali_kelas'] ?? '-';

// Load Materi Sumatif
$stmtMat = $pdo->prepare("
    SELECT mapel, materi_s1, materi_s2, materi_s3, materi_s4 
    FROM materi_sumatif 
    WHERE kelas_id = :kid AND semester = :sem AND tahun_ajaran = :th
");
$stmtMat->execute([':kid' => $kelasId, ':sem' => $sem, ':th' => $tahun]);
$mapelMateriMap = [];
foreach ($stmtMat->fetchAll() as $mat) {
    $mapelMateriMap[$mat['mapel']] = $mat;
}

// Load List Mapel
$stmtMapel = $pdo->prepare("
    SELECT m.id AS mapel_id, m.nama_mapel, 
           GROUP_CONCAT(DISTINCT u.nama_lengkap SEPARATOR ', ') AS nama_guru_pengampu
    FROM guru_mapel_kelas gmk
    JOIN mata_pelajaran m ON gmk.mapel_id = m.id
    LEFT JOIN users u ON gmk.user_id = u.id
    WHERE gmk.kelas_id = :kid
    GROUP BY m.nama_mapel, m.kelompok
    ORDER BY m.kelompok ASC, m.nama_mapel ASC
");
$stmtMapel->execute([':kid' => $kelasId]);
$rawMapel = $stmtMapel->fetchAll();
if (empty($rawMapel)) {
    $stmtM2 = $pdo->prepare("SELECT DISTINCT mapel AS nama_mapel, '-' AS nama_guru_pengampu FROM nilai_siswa WHERE kelas_id = :kid");
    $stmtM2->execute([':kid' => $kelasId]);
    $rawMapel = $stmtM2->fetchAll();
}

$listMapel = [];
$seenNames = [];
foreach ($rawMapel as $rm) {
    $cleanName = trim($rm['nama_mapel']);
    $upperKey = strtoupper($cleanName);
    if (!in_array($upperKey, $seenNames) && $cleanName !== '') {
        $seenNames[] = $upperKey;
        $rm['nama_mapel'] = $cleanName;
        $listMapel[] = $rm;
    }
}

// Load Siswa & Nilai
$stmtSiswa = $pdo->prepare("SELECT id, nis, nisn, nama, jenis_kelamin FROM siswa WHERE kelas_id = :kid AND status = 'aktif' ORDER BY nama ASC");
$stmtSiswa->execute([':kid' => $kelasId]);
$rawSiswa = $stmtSiswa->fetchAll();

$stmtAllNilai = $pdo->prepare("
    SELECT ns.*, u.nama_lengkap AS nama_guru_input 
    FROM nilai_siswa ns
    LEFT JOIN users u ON ns.user_id = u.id
    WHERE (ns.kelas_id = :kid OR ns.siswa_id IN (SELECT id FROM siswa WHERE kelas_id = :kid2))
      AND (TRIM(LOWER(ns.semester)) = LOWER(:sem) OR ns.semester IS NULL OR TRIM(ns.semester) = '')
      AND (TRIM(LOWER(ns.tahun_ajaran)) = LOWER(:th) OR ns.tahun_ajaran IS NULL OR TRIM(ns.tahun_ajaran) = '')
");
$stmtAllNilai->execute([':kid' => $kelasId, ':kid2' => $kelasId, ':sem' => $sem, ':th' => $tahun]);
$rawNilai = $stmtAllNilai->fetchAll();

$nilaiBySiswa = [];
$nilaiBySiswaNorm = [];
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
}

if (!function_exists('findGradePrint')) {
    function findGradePrint($grades, $normGrades, $mapelName)
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
}

// Konfigurasi Judul Asesmen
$sumatifTitles = [
    'all' => 'SEMUA ASESMEN (SUMATIF 1-4, ATS & AAS)',
    's1' => 'ASESMEN SUMATIF 1 (S1)',
    's2' => 'ASESMEN SUMATIF 2 (S2)',
    's3' => 'ASESMEN SUMATIF 3 (S3)',
    's4' => 'ASESMEN SUMATIF 4 (S4)',
    'ats' => 'ASESMEN TENGAH SEMESTER (ATS)',
    'aas' => 'ASESMEN AKHIR SEMESTER (AAS / PAS)',
];
$judulAsesmen = $sumatifTitles[$filterSumatif] ?? 'REKAPITULASI PENILAIAN';

// School Settings
$namaSekolah = getSetting('nama_sekolah', SCHOOL_NAME);
$alamatSekolah = getSetting('alamat_sekolah', 'Jl. Pendidikan No. 1');
$teleponSekolah = getSetting('telepon_sekolah', '0812-3456-7890');
$namaKepsek = getSetting('nama_kepala_sekolah', 'Kepala Sekolah, M.Pd');
$nipKepsek = getSetting('nip_kepsek', '—');
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap <?= htmlspecialchars($judulAsesmen) ?> - Kelas <?= htmlspecialchars($namaKelas) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Times+New+Roman&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 10.5pt;
            line-height: 1.35;
            color: #000;
            background: #f1f5f9;
            margin: 0;
            padding: 20px 0;
        }

        .action-bar {
            max-width: 1050px;
            margin: 0 auto 20px auto;
            background: #ffffff;
            padding: 14px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: 'Inter', sans-serif;
        }

        .btn-action {
            font-family: 'Inter', sans-serif;
            font-size: 0.88rem;
            font-weight: 700;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            border: none;
        }

        .paper {
            background: #ffffff;
            width: 1050px;
            margin: 0 auto;
            padding: 30px 40px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
            box-sizing: border-box;
        }

        .kop-sekolah {
            display: flex;
            align-items: center;
            gap: 20px;
            border-bottom: 3px double #000;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .kop-logo {
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .kop-text {
            flex-grow: 1;
            text-align: center;
        }

        .kop-text h2 {
            margin: 0;
            font-size: 15pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .kop-text h3 {
            margin: 2px 0;
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .kop-text p {
            margin: 0;
            font-size: 9.5pt;
            font-family: 'Inter', sans-serif;
        }

        .doc-title {
            text-align: center;
            margin-bottom: 16px;
        }

        .doc-title h3 {
            margin: 0;
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-decoration: underline;
        }

        .doc-title p {
            margin: 4px 0 0 0;
            font-size: 10pt;
            font-weight: 600;
        }

        .info-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 9.5pt;
            margin-bottom: 14px;
            font-family: 'Inter', sans-serif;
        }

        .tbl-rekap {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
            margin-bottom: 20px;
        }

        .tbl-rekap th,
        .tbl-rekap td {
            border: 1px solid #334155;
            padding: 5px 6px;
            vertical-align: middle;
        }

        .tbl-rekap th {
            background: #f1f5f9;
            text-align: center;
            font-weight: bold;
            font-size: 9pt;
        }

        .note-text {
            font-size: 8.5pt;
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            background: #eff6ff;
            border-left: 3px solid #2563eb;
            padding: 3px 6px;
            margin-top: 3px;
            border-radius: 2px;
        }

        .ttd-container {
            margin-top: 25px;
            width: 100%;
            page-break-inside: avoid;
        }

        .ttd-table {
            width: 100%;
            border: none;
        }

        .ttd-table td {
            border: none;
            text-align: center;
            vertical-align: top;
            width: 50%;
        }

        @media print {
            body {
                background: none;
                padding: 0;
            }

            .action-bar {
                display: none !important;
            }

            .paper {
                box-shadow: none;
                width: 100%;
                margin: 0;
                padding: 0;
            }

            .tbl-rekap th {
                background: #e2e8f0 !important;
                -webkit-print-color-adjust: exact;
            }

            .note-text {
                background: #f1f5f9 !important;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>

    <!-- ACTION BAR -->
    <div class="action-bar">
        <div>
            <strong>Laporan Evaluasi Wali Kelas</strong> — Kelas <?= htmlspecialchars($namaKelas) ?>
            (<?= htmlspecialchars($judulAsesmen) ?>)
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="<?= BASE_URL ?>/pages/wali-kelas/index.php?kelas_id=<?= $kelasId ?>&filter_mapel=<?= urlencode($filterMapel) ?>&filter_sumatif=<?= urlencode($filterSumatif) ?>&semester=<?= urlencode($sem) ?>&tahun_ajaran=<?= urlencode($tahun) ?>"
                class="btn-action" style="background: #64748b; color: #fff;">
                <i class="bi bi-arrow-left"></i> Kembali ke Menu
            </a>
            <button onclick="window.print()" class="btn-action" style="background: #1e3a8a; color: #fff;">
                <i class="bi bi-printer-fill"></i> Cetak / Simpan PDF
            </button>
        </div>
    </div>

    <!-- KERTAS LAPORAN -->
    <div class="paper">
        <!-- KOP SEKOLAH -->
        <div class="kop-sekolah">
            <div class="kop-logo"><?= getLogoHtml(65) ?></div>
            <div class="kop-text">
                <h2>YAYASAN PENDIDIKAN ISLAM</h2>
                <h3><?= htmlspecialchars($namaSekolah) ?></h3>
                <p><?= htmlspecialchars($alamatSekolah) ?> | Telp: <?= htmlspecialchars($teleponSekolah) ?></p>
            </div>
        </div>

        <!-- JUDUL DOKUMEN -->
        <div class="doc-title">
            <h3>REKAPITULASI HASIL EVALUASI SANTRI & CATATAN GURU</h3>
            <p>
                KELAS: <?= strtoupper(htmlspecialchars($namaKelas)) ?> |
                <?= $filterMapel !== 'all' ? 'MAPEL: ' . strtoupper(htmlspecialchars($filterMapel)) . ' | ' : '' ?>
                <?= htmlspecialchars($judulAsesmen) ?> |
                SEMESTER <?= strtoupper(htmlspecialchars($sem)) ?> TA <?= htmlspecialchars($tahun) ?>
            </p>
        </div>

        <?php if ($filterMapel !== 'all'):
            // ===== CETAK SPESIFIK 1 MATA PELAJARAN =====
            $mat = $mapelMateriMap[$filterMapel] ?? [];
            ?>
            <!-- KETERANGAN BAB JIKA ADA -->
            <?php if (!empty($mat['materi_s1']) || !empty($mat['materi_s2']) || !empty($mat['materi_s3']) || !empty($mat['materi_s4'])): ?>
                <div class="info-box">
                    <strong>Lingkup Materi / Bab yang Diujikan:</strong>
                    <div style="margin-top: 3px;">
                        <?php if (!empty($mat['materi_s1'])): ?><b>S1:</b> <?= htmlspecialchars($mat['materi_s1']) ?>
                            &nbsp;|&nbsp; <?php endif; ?>
                        <?php if (!empty($mat['materi_s2'])): ?><b>S2:</b> <?= htmlspecialchars($mat['materi_s2']) ?>
                            &nbsp;|&nbsp; <?php endif; ?>
                        <?php if (!empty($mat['materi_s3'])): ?><b>S3:</b> <?= htmlspecialchars($mat['materi_s3']) ?>
                            &nbsp;|&nbsp; <?php endif; ?>
                        <?php if (!empty($mat['materi_s4'])): ?><b>S4:</b>
                            <?= htmlspecialchars($mat['materi_s4']) ?>         <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <table class="tbl-rekap">
                <thead>
                    <tr>
                        <th style="width: 30px;">No</th>
                        <th style="width: 75px;">NIS</th>
                        <th style="text-align: left; padding-left: 8px;">Nama Santri</th>

                        <?php if ($filterSumatif === 'all' || $filterSumatif === 's1'): ?>
                            <th style="min-width: 110px;">Sumatif 1 (S1)</th>
                        <?php endif; ?>
                        <?php if ($filterSumatif === 'all' || $filterSumatif === 's2'): ?>
                            <th style="min-width: 110px;">Sumatif 2 (S2)</th>
                        <?php endif; ?>
                        <?php if ($filterSumatif === 'all' || $filterSumatif === 's3'): ?>
                            <th style="min-width: 110px;">Sumatif 3 (S3)</th>
                        <?php endif; ?>
                        <?php if ($filterSumatif === 'all' || $filterSumatif === 's4'): ?>
                            <th style="min-width: 110px;">Sumatif 4 (S4)</th>
                        <?php endif; ?>
                        <?php if ($filterSumatif === 'all' || $filterSumatif === 'ats'): ?>
                            <th style="width: 55px;">ATS</th>
                        <?php endif; ?>
                        <?php if ($filterSumatif === 'all' || $filterSumatif === 'aas'): ?>
                            <th style="width: 55px;">AAS</th>
                        <?php endif; ?>
                        <?php if ($filterSumatif === 'all'): ?>
                            <th style="width: 65px;">Nilai Akhir</th>
                            <th style="width: 45px;">Pred</th>
                            <th style="text-align: left; padding-left: 8px;">Catatan Umum Guru</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rawSiswa)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 15px;">Belum ada data siswa.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rawSiswa as $idx => $s):
                            $g = findGradePrint($nilaiBySiswa[$s['id']] ?? [], $nilaiBySiswaNorm[$s['id']] ?? [], $filterMapel);
                            $s1 = (float) ($g['sumatif_1'] ?? 0);
                            $s2 = (float) ($g['sumatif_2'] ?? 0);
                            $s3 = (float) ($g['sumatif_3'] ?? 0);
                            $s4 = (float) ($g['sumatif_4'] ?? 0);
                            $ats = (float) ($g['ats'] ?? 0);
                            $aas = (float) ($g['aas'] ?? 0);
                            $na = (float) ($g['nilai_akhir'] ?? 0);
                            $pred = $g['predikat'] ?? '—';
                            $cs1 = $g['catatan_s1'] ?? '';
                            $cs2 = $g['catatan_s2'] ?? '';
                            $cs3 = $g['catatan_s3'] ?? '';
                            $cs4 = $g['catatan_s4'] ?? '';
                            $catUmum = $g['catatan'] ?? '';
                            ?>
                            <tr>
                                <td style="text-align: center;"><?= $idx + 1 ?></td>
                                <td style="text-align: center;"><?= htmlspecialchars($s['nis']) ?></td>
                                <td><strong><?= htmlspecialchars($s['nama']) ?></strong></td>

                                <?php if ($filterSumatif === 'all' || $filterSumatif === 's1'): ?>
                                    <td style="text-align: center;">
                                        <strong><?= $s1 > 0 ? $s1 : '-' ?></strong>
                                        <?php if (!empty($cs1)): ?>
                                            <div class="note-text"><?= htmlspecialchars($cs1) ?></div><?php endif; ?>
                                    </td>
                                <?php endif; ?>

                                <?php if ($filterSumatif === 'all' || $filterSumatif === 's2'): ?>
                                    <td style="text-align: center;">
                                        <strong><?= $s2 > 0 ? $s2 : '-' ?></strong>
                                        <?php if (!empty($cs2)): ?>
                                            <div class="note-text"><?= htmlspecialchars($cs2) ?></div><?php endif; ?>
                                    </td>
                                <?php endif; ?>

                                <?php if ($filterSumatif === 'all' || $filterSumatif === 's3'): ?>
                                    <td style="text-align: center;">
                                        <strong><?= $s3 > 0 ? $s3 : '-' ?></strong>
                                        <?php if (!empty($cs3)): ?>
                                            <div class="note-text"><?= htmlspecialchars($cs3) ?></div><?php endif; ?>
                                    </td>
                                <?php endif; ?>

                                <?php if ($filterSumatif === 'all' || $filterSumatif === 's4'): ?>
                                    <td style="text-align: center;">
                                        <strong><?= $s4 > 0 ? $s4 : '-' ?></strong>
                                        <?php if (!empty($cs4)): ?>
                                            <div class="note-text"><?= htmlspecialchars($cs4) ?></div><?php endif; ?>
                                    </td>
                                <?php endif; ?>

                                <?php if ($filterSumatif === 'all' || $filterSumatif === 'ats'): ?>
                                    <td style="text-align: center;"><strong><?= $ats > 0 ? $ats : '-' ?></strong></td>
                                <?php endif; ?>

                                <?php if ($filterSumatif === 'all' || $filterSumatif === 'aas'): ?>
                                    <td style="text-align: center;"><strong><?= $aas > 0 ? $aas : '-' ?></strong></td>
                                <?php endif; ?>

                                <?php if ($filterSumatif === 'all'): ?>
                                    <td style="text-align: center; font-weight: bold; background:#f8fafc;">
                                        <?= $na > 0 ? number_format($na, 1) : '-' ?>
                                    </td>
                                    <td style="text-align: center; font-weight: bold;"><?= $pred ?></td>
                                    <td><?= htmlspecialchars($catUmum ?: '-') ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

        <?php else:
            // ===== CETAK SELURUH MATA PELAJARAN (MATRIKS LENGKAP ATAU PER SANTRI) =====
            ?>
            <table class="tbl-rekap">
                <thead>
                    <tr>
                        <th style="width: 30px;" rowspan="2">No</th>
                        <th style="width: 75px;" rowspan="2">NIS</th>
                        <th style="text-align: left; padding-left: 8px;" rowspan="2">Nama Santri</th>
                        <th colspan="<?= count($listMapel) ?>" style="text-align: center;">Mata Pelajaran
                            (<?= htmlspecialchars($judulAsesmen) ?>)</th>
                        <th style="width: 65px;" rowspan="2">Rata Nilai</th>
                    </tr>
                    <tr>
                        <?php foreach ($listMapel as $m): ?>
                            <th style="font-size: 8pt;"><?= htmlspecialchars($m['nama_mapel']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rawSiswa)): ?>
                        <tr>
                            <td colspan="<?= count($listMapel) + 4 ?>" style="text-align: center; padding: 15px;">Belum ada data
                                siswa.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rawSiswa as $idx => $s):
                            $grades = $nilaiBySiswa[$s['id']] ?? [];
                            $sumScore = 0;
                            $cntScore = 0;
                            ?>
                            <tr>
                                <td style="text-align: center;"><?= $idx + 1 ?></td>
                                <td style="text-align: center;"><?= htmlspecialchars($s['nis']) ?></td>
                                <td><strong><?= htmlspecialchars($s['nama']) ?></strong></td>

                                <?php foreach ($listMapel as $m):
                                    $g = findGradePrint($grades, $nilaiBySiswaNorm[$s['id']] ?? [], $m['nama_mapel']);
                                    $score = 0;
                                    $note = '';

                                    if (!empty($g)) {
                                        if ($filterSumatif === 's1') {
                                            $score = (float) ($g['sumatif_1'] ?? 0);
                                            $c = trim($g['catatan_s1'] ?? '');
                                            if (!empty($c)) {
                                                $note = "S1: $c";
                                            }
                                        } elseif ($filterSumatif === 's2') {
                                            $score = (float) ($g['sumatif_2'] ?? 0);
                                            $c = trim($g['catatan_s2'] ?? '');
                                            if (!empty($c)) {
                                                $note = "S2: $c";
                                            }
                                        } elseif ($filterSumatif === 's3') {
                                            $score = (float) ($g['sumatif_3'] ?? 0);
                                            $c = trim($g['catatan_s3'] ?? '');
                                            if (!empty($c)) {
                                                $note = "S3: $c";
                                            }
                                        } elseif ($filterSumatif === 's4') {
                                            $score = (float) ($g['sumatif_4'] ?? 0);
                                            $c = trim($g['catatan_s4'] ?? '');
                                            if (!empty($c)) {
                                                $note = "S4: $c";
                                            }
                                        } elseif ($filterSumatif === 'ats') {
                                            $score = (float) ($g['ats'] ?? 0);
                                        } elseif ($filterSumatif === 'aas') {
                                            $score = (float) ($g['aas'] ?? 0);
                                        } else {
                                            $score = (float) ($g['nilai_akhir'] ?? 0);
                                            $notesArr = [];
                                            if (!empty(trim($g['catatan'] ?? ''))) {
                                                $notesArr[] = trim($g['catatan']);
                                            }
                                            foreach (['S1' => 'catatan_s1', 'S2' => 'catatan_s2', 'S3' => 'catatan_s3', 'S4' => 'catatan_s4'] as $tag => $fld) {
                                                if (!empty(trim($g[$fld] ?? ''))) {
                                                    $notesArr[] = "$tag: " . trim($g[$fld]);
                                                }
                                            }
                                            if (!empty($notesArr)) {
                                                $note = implode(' | ', $notesArr);
                                            }
                                        }
                                    }

                                    if ($score > 0) {
                                        $sumScore += $score;
                                        $cntScore++;
                                    }
                                    ?>
                                    <td style="text-align: center;">
                                        <strong><?= $score > 0 ? $score : '-' ?></strong>
                                        <?php if (!empty($note)): ?>
                                            <div class="note-text" style="font-size:7.5pt; margin-top:2px;"><?= htmlspecialchars($note) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>

                                <td style="text-align: center; font-weight: bold; background:#f8fafc;">
                                    <?= $cntScore > 0 ? number_format($sumScore / $cntScore, 1) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- TANDA TANGAN -->
        <div class="ttd-container">
            <table class="ttd-table">
                <tr>
                    <td>
                        Mengetahui,<br>
                        Kepala Sekolah<br><br><br><br>
                        <strong><u><?= htmlspecialchars($namaKepsek) ?></u></strong><br>
                        NIP: <?= htmlspecialchars($nipKepsek) ?>
                    </td>
                    <td>
                        Kota Terbit, <?= date('d F Y') ?><br>
                        Wali Kelas <?= htmlspecialchars($namaKelas) ?><br><br><br><br>
                        <strong><u><?= htmlspecialchars($namaWali) ?></u></strong><br>
                        NIP: <?= htmlspecialchars($nipWali) ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>

</body>

</html>