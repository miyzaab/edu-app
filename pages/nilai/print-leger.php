<?php
/**
 * AKADEMIK & GURU - Cetak / Export Leger Nilai PDF
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('input_nilai');

$pdo = getConnection();

$kelasId = (int)($_GET['kelas_id'] ?? 0);
$mapel   = trim($_GET['mapel'] ?? 'Matematika');
$sem     = trim($_GET['semester'] ?? 'Ganjil');
$tahun   = trim($_GET['tahun_ajaran'] ?? (date('Y') . '/' . (date('Y') + 1)));

// Load Nama Kelas
$stmtK = $pdo->prepare("SELECT * FROM kelas WHERE id = :id LIMIT 1");
$stmtK->execute([':id' => $kelasId]);
$kelasObj = $stmtK->fetch();
$namaKelas = $kelasObj['nama_kelas'] ?? 'Semua Kelas';

// Load List Siswa & Nilai
$stmtSiswa = $pdo->prepare("
    SELECT s.nis, s.nama,
           n.sumatif_1, n.sumatif_2, n.sumatif_3, n.sumatif_4, n.ats, n.aas, n.nilai_akhir, n.predikat, n.catatan
    FROM siswa s
    LEFT JOIN nilai_siswa n ON (s.id = n.siswa_id AND n.mapel = :mapel AND n.semester = :sem AND n.tahun_ajaran = :th)
    WHERE s.kelas_id = :kid AND s.status = 'aktif'
    ORDER BY s.nama ASC
");
$stmtSiswa->execute([
    ':kid'   => $kelasId,
    ':mapel' => $mapel,
    ':sem'   => $sem,
    ':th'    => $tahun
]);
$listSiswa = $stmtSiswa->fetchAll();

// School Settings
$namaSekolah   = getSetting('nama_sekolah', SCHOOL_NAME);
$alamatSekolah = getSetting('alamat_sekolah', 'Jl. Pendidikan No. 1');
$teleponSekolah = getSetting('telepon_sekolah', '0812-3456-7890');
$namaKepsek    = getSetting('nama_kepala_sekolah', 'Kepala Sekolah, M.Pd');
$nipKepsek     = getSetting('nip_kepsek', '—');
$namaGuru      = $_SESSION['nama_lengkap'] ?? 'Guru Pengampu';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leger Nilai <?= htmlspecialchars($mapel) ?> - <?= htmlspecialchars($namaKelas) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Times+New+Roman&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #000;
            background: #f4f6f9;
            margin: 0;
            padding: 20px 0;
        }

        .action-bar {
            max-width: 950px;
            margin: 0 auto 20px auto;
            background: #fff;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .action-bar button, .action-bar a {
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 8px;
            text-decoration: none;
        }

        .paper {
            background: #fff;
            width: 297mm; /* Landscape A4 */
            min-height: 210mm;
            margin: 0 auto;
            padding: 15mm 15mm 15mm 15mm;
            box-sizing: border-box;
            box-shadow: 0 4px 25px rgba(0,0,0,0.1);
        }

        .kop-sekolah {
            display: flex;
            align-items: center;
            border-bottom: 3px double #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .kop-logo { width: 75px; text-align: center; }
        .kop-text { flex: 1; text-align: center; }
        .kop-text h2 { margin: 0; font-size: 15pt; font-weight: bold; text-transform: uppercase; }
        .kop-text h3 { margin: 2px 0; font-size: 13pt; font-weight: bold; text-transform: uppercase; }
        .kop-text p { margin: 0; font-size: 10pt; font-style: italic; }

        .doc-title { text-align: center; margin-bottom: 15px; }
        .doc-title h3 { margin: 0; font-size: 13pt; font-weight: bold; text-transform: uppercase; text-decoration: underline; }
        .doc-title p { margin: 3px 0 0 0; font-size: 10.5pt; font-weight: bold; }

        table.tbl-leger {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        table.tbl-leger td, table.tbl-leger th {
            border: 1px solid #000;
            padding: 5px 6px;
            font-size: 10pt;
        }

        table.tbl-leger th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }

        .ttd-container {
            margin-top: 30px;
            width: 100%;
            page-break-inside: avoid;
        }

        .ttd-table { width: 100%; border: none; }
        .ttd-table td { border: none; text-align: center; vertical-align: top; width: 50%; }

        @media print {
            body { background: none; padding: 0; }
            .action-bar { display: none !important; }
            .paper { box-shadow: none; width: 100%; margin: 0; padding: 0; }
        }
    </style>
</head>
<body>

    <!-- ACTION BAR -->
    <div class="action-bar">
        <div>
            <strong>Leger Nilai Kurikulum Merdeka</strong> — <?= htmlspecialchars($mapel) ?> (<?= htmlspecialchars($namaKelas) ?>)
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="<?= BASE_URL ?>/pages/nilai/index.php?kelas_id=<?= $kelasId ?>&mapel=<?= urlencode($mapel) ?>&semester=<?= urlencode($sem) ?>&tahun_ajaran=<?= urlencode($tahun) ?>" style="background: #6c757d; color: #fff;">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            <button onclick="window.print()" style="background: #dc3545; color: #fff; border: none; cursor: pointer;">
                <i class="bi bi-printer-fill me-1"></i> Cetak / Simpan PDF
            </button>
        </div>
    </div>

    <!-- KERTAS A4 LANDSCAPE -->
    <div class="paper">
        
        <!-- KOP SEKOLAH -->
        <div class="kop-sekolah">
            <div class="kop-logo"><?= getLogoHtml(60) ?></div>
            <div class="kop-text">
                <h2>YAYASAN PENDIDIKAN ISLAM</h2>
                <h3><?= htmlspecialchars($namaSekolah) ?></h3>
                <p><?= htmlspecialchars($alamatSekolah) ?> | Telp: <?= htmlspecialchars($teleponSekolah) ?></p>
            </div>
        </div>

        <!-- JUDUL LEGER -->
        <div class="doc-title">
            <h3>LEGER REKAPITULASI PENILAIAN SISWA</h3>
            <p>MATA PELAJARAN: <?= htmlspecialchars(strtoupper($mapel)) ?> | KELAS: <?= htmlspecialchars(strtoupper($namaKelas)) ?> | SEMESTER <?= htmlspecialchars(strtoupper($sem)) ?> TA <?= htmlspecialchars($tahun) ?></p>
        </div>

        <!-- TABEL LEGER -->
        <table class="tbl-leger">
            <thead>
                <tr>
                    <th style="width: 30px;">No</th>
                    <th style="width: 80px;">NIS</th>
                    <th>Nama Siswa</th>
                    <th style="width: 55px;">S1</th>
                    <th style="width: 55px;">S2</th>
                    <th style="width: 55px;">S3</th>
                    <th style="width: 55px;">S4</th>
                    <th style="width: 70px;">Rata (80%)</th>
                    <th style="width: 65px;">ATS (10%)</th>
                    <th style="width: 65px;">AAS (10%)</th>
                    <th style="width: 70px;">Nilai Akhir</th>
                    <th style="width: 55px;">Predikat</th>
                    <th>Catatan Capaian Guru</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($listSiswa)): ?>
                <tr><td colspan="13" style="text-align: center; padding: 20px;">Belum ada data siswa.</td></tr>
            <?php else: ?>
                <?php 
                $totalNA = 0;
                $maxNA = -1;
                $minNA = 999;
                $countSiswa = count($listSiswa);

                foreach ($listSiswa as $i => $s): 
                    $s1  = (float)($s['sumatif_1'] ?? 0);
                    $s2  = (float)($s['sumatif_2'] ?? 0);
                    $s3  = (float)($s['sumatif_3'] ?? 0);
                    $s4  = (float)($s['sumatif_4'] ?? 0);
                    $ats = (float)($s['ats'] ?? 0);
                    $aas = (float)($s['aas'] ?? 0);
                    
                    $rSumatif = ($s1 + $s2 + $s3 + $s4) / 4.0;
                    $na = ($rSumatif * 0.80) + ($ats * 0.10) + ($aas * 0.10);
                    $na = round($na, 2);
                    
                    $totalNA += $na;
                    if ($na > $maxNA) $maxNA = $na;
                    if ($na < $minNA) $minNA = $na;

                    if ($na >= 90) $pred = 'A';
                    elseif ($na >= 80) $pred = 'B';
                    elseif ($na >= 70) $pred = 'C';
                    else $pred = 'D';
                ?>
                <tr>
                    <td style="text-align: center;"><?= $i+1 ?></td>
                    <td style="text-align: center;"><?= htmlspecialchars($s['nis']) ?></td>
                    <td><strong><?= htmlspecialchars($s['nama']) ?></strong></td>
                    <td style="text-align: center;"><?= $s1 ?></td>
                    <td style="text-align: center;"><?= $s2 ?></td>
                    <td style="text-align: center;"><?= $s3 ?></td>
                    <td style="text-align: center;"><?= $s4 ?></td>
                    <td style="text-align: center; font-weight: bold; background: #fafafa;"><?= number_format($rSumatif, 2) ?></td>
                    <td style="text-align: center;"><?= $ats ?></td>
                    <td style="text-align: center;"><?= $aas ?></td>
                    <td style="text-align: center; font-weight: bold; background: #f0f0f0;"><?= number_format($na, 2) ?></td>
                    <td style="text-align: center; font-weight: bold;"><?= $pred ?></td>
                    <td><?= htmlspecialchars($s['catatan'] ?: '-') ?></td>
                </tr>
                <?php endforeach; ?>
                
                <!-- SUMMARY ROW -->
                <tr style="font-weight: bold; background-color: #fafafa;">
                    <td colspan="10" style="text-align: right;">RATA-RATA KELAS:</td>
                    <td style="text-align: center; background: #e2e8f0;"><?= $countSiswa > 0 ? number_format($totalNA / $countSiswa, 2) : 0 ?></td>
                    <td colspan="2">Nilai Max: <?= $maxNA >= 0 ? $maxNA : 0 ?> | Min: <?= $minNA < 999 ? $minNA : 0 ?></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>

        <!-- TANDA TANGAN -->
        <div class="ttd-container">
            <table class="ttd-table">
                <tr>
                    <td>
                        Mengetahui,<br>
                        Kepala Sekolah <?= htmlspecialchars($namaSekolah) ?>
                        <br><br><br><br><br>
                        <strong><u><?= htmlspecialchars($namaKepsek) ?></u></strong><br>
                        NIP. <?= htmlspecialchars($nipKepsek) ?>
                    </td>
                    <td>
                        Kota Sekolah, <?= formatTanggal(date('Y-m-d')) ?><br>
                        Guru Mata Pelajaran
                        <br><br><br><br><br>
                        <strong><u><?= htmlspecialchars($namaGuru) ?></u></strong>
                    </td>
                </tr>
            </table>
        </div>

    </div>

</body>
</html>
