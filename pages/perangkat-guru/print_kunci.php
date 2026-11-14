<?php
/**
 * CETAK LEMBAR KUNCI JAWABAN & PEDOMAN PENSKORAN (PEGANGAN GURU)
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_guru');

$pdo = getConnection();
require_once __DIR__ . '/init_soal.php';

$naskahId = (int) ($_GET['id'] ?? 0);
if ($naskahId <= 0) {
    die('ID Naskah tidak valid.');
}

// Ambil Data Naskah & Guru
$stmt = $pdo->prepare("
    SELECT sn.*, u.nama_lengkap AS nama_guru, gd.nip AS nip_guru
    FROM soal_naskah sn
    LEFT JOIN users u ON sn.guru_id = u.id
    LEFT JOIN guru_detail gd ON u.id = gd.user_id
    WHERE sn.id = :id 
    LIMIT 1
");
$stmt->execute([':id' => $naskahId]);
$naskah = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$naskah) {
    die('Naskah soal tidak ditemukan.');
}

// Ambil Butir Soal
$stmtSoal = $pdo->prepare("SELECT * FROM soal_butir WHERE naskah_id = :nid ORDER BY FIELD(tipe_soal, 'pg', 'isian', 'essay'), nomor_urut ASC");
$stmtSoal->execute([':nid' => $naskahId]);
$allSoal = $stmtSoal->fetchAll(PDO::FETCH_ASSOC);

$soalPg = [];
$soalIsian = [];
$soalEssay = [];

$sumPgPoin = 0;
$sumIsianPoin = 0;
$sumEssayPoin = 0;

foreach ($allSoal as $s) {
    if ($s['tipe_soal'] === 'pg') {
        $soalPg[] = $s;
        $sumPgPoin += (float) $s['poin'];
    } elseif ($s['tipe_soal'] === 'isian') {
        $soalIsian[] = $s;
        $sumIsianPoin += (float) $s['poin'];
    } else {
        $soalEssay[] = $s;
        $sumEssayPoin += (float) $s['poin'];
    }
}

// KOP Settings
$settingsRaw = $pdo->query("SELECT setting_key, setting_value FROM rapor_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

$kopNamaSekolah = !empty($settingsRaw['kop_nama_sekolah']) ? $settingsRaw['kop_nama_sekolah'] : 'SMP ISMI';
$kopYayasan = !empty($settingsRaw['kop_yayasan']) ? $settingsRaw['kop_yayasan'] : 'ISLAMIC SCHOOL OF MINHAJ AL-ILMI';
$kopSlogan = !empty($settingsRaw['kop_slogan']) ? $settingsRaw['kop_slogan'] : 'Berakhlak Mulia, Berilmu, Mahir IT';
$kopAlamat = !empty($settingsRaw['kop_alamat']) ? $settingsRaw['kop_alamat'] : 'Jl. Nakula No.1 Harjamukti Cimanggis Depok';
$kopTelp = !empty($settingsRaw['kop_telp']) ? $settingsRaw['kop_telp'] : 'Telp. (021) 4867 8793';
$kopEmail = !empty($settingsRaw['kop_email']) ? $settingsRaw['kop_email'] : 'smp.minhaj.al.ilmi@gmail.com';
$kopWebsite = !empty($settingsRaw['kop_website']) ? $settingsRaw['kop_website'] : 'sekolahismi.sch.id & ismi.sch.id';
$kopWa = !empty($settingsRaw['kop_wa']) ? $settingsRaw['kop_wa'] : 'Admin WA 0899-2244-111';

$kepsekJabatan = !empty($settingsRaw['kepsek_jabatan']) ? $settingsRaw['kepsek_jabatan'] : 'Mudir SMP';
$kepsekNama = !empty($settingsRaw['kepsek_nama']) ? $settingsRaw['kepsek_nama'] : 'Riki Kaptanto, Lc';
$kepsekNip = !empty($settingsRaw['kepsek_nip']) ? $settingsRaw['kepsek_nip'] : '20240711001';
$tempatTerbit = !empty($settingsRaw['tempat_terbit']) ? $settingsRaw['tempat_terbit'] : 'Depok';

// Dynamic Footer
$cleanKelas = trim(preg_replace('/^Kelas\s+/i', '', $naskah['tingkat_kelas'] ?? ''));
$footerText = trim(($naskah['nama_mapel'] ?? '') . ' ' . $cleanKelas);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kunci_Jawaban_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $naskah['nama_mapel']) ?>_Kelas_<?= $naskah['tingkat_kelas'] ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <!-- GOOGLE FONT: Scheherazade New untuk Font Teks Arab -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Scheherazade+New:wght@400;700&display=swap" rel="stylesheet">

    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 15mm 20mm 15mm;
            @bottom-left {
                content: "<?= addslashes($footerText) ?> • Kunci Jawaban";
                font-family: 'Cambria', Georgia, serif;
                font-size: 10pt;
                font-style: italic;
                color: #222222;
            }
            @bottom-right {
                content: "Halaman " counter(page);
                font-family: 'Cambria', Georgia, serif;
                font-size: 10pt;
                color: #222222;
            }
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: 'Cambria', Georgia, serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #000;
            background-color: #f1f5f9;
            margin: 0;
            padding: 0;
        }

        .teks-arab {
            font-family: 'Scheherazade New', 'Amiri', 'Traditional Arabic', serif !important;
            font-size: 15pt !important;
            line-height: 1.0 !important;
            direction: rtl;
            unicode-bidi: isolate;
            display: inline-block;
        }

        .no-print {
            display: block;
        }

        .print-control-bar {
            background: #0f172a;
            color: #ffffff;
            padding: 10px 24px;
            position: sticky;
            top: 0;
            z-index: 1050;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        .page-sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: #ffffff;
            padding: 14mm 16mm 14mm 16mm;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
        }

        @media print {
            body {
                background: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .page-sheet {
                width: 100% !important;
                max-width: 100% !important;
                min-height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
            }
        }

        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3px;
        }
        .kop-table td {
            vertical-align: middle;
        }
        .kop-divider {
            border-bottom: 2.2px solid #000000;
            margin-bottom: 14px;
        }

        .title-wrapper {
            text-align: center;
            margin-bottom: 14px;
        }
        .title-main {
            font-size: 12.5pt;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .title-sub {
            font-size: 11pt;
            font-weight: bold;
            margin-top: 2px;
        }

        .identitas-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
            margin-bottom: 14px;
            border: 1px solid #000;
        }
        .identitas-table td {
            border: 1px solid #000;
            padding: 4px 8px;
        }

        .kunci-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 9pt;
        }
        .kunci-grid th, .kunci-grid td {
            border: 1px solid #000;
            padding: 5px 6px;
            text-align: center;
        }
        .kunci-grid th {
            background: #f8fafc;
            font-weight: bold;
        }

        .rubrik-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 9pt;
        }
        .rubrik-table th, .rubrik-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            vertical-align: top;
        }
        .rubrik-table th {
            background: #f8fafc;
            font-weight: bold;
            text-align: center;
        }

        .sig-table {
            width: 100%;
            margin-top: 25px;
            page-break-inside: avoid;
        }
        .sig-table td {
            vertical-align: top;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- PRINT CONTROL BAR -->
    <div class="print-control-bar no-print">
        <div class="d-flex align-items-center gap-3">
            <a href="edit.php?id=<?= $naskahId ?>" class="btn btn-outline-light btn-sm rounded-pill px-3 fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Editor
            </a>
            <span class="badge bg-success px-3 py-1.5 rounded-pill font-monospace">
                PEGANGAN GURU • KUNCI &amp; PEDOMAN PENSKORAN
            </span>
        </div>
        <button onclick="window.print()" class="btn btn-danger btn-sm rounded-pill px-4 py-1.5 fw-bold shadow-sm"
            style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border:none;">
            <i class="bi bi-printer-fill me-1"></i> Cetak Kunci Jawaban
        </button>
    </div>

    <div class="page-sheet">
        <!-- KOP (UPLOADED PNG) -->
        <?php 
        $kopImgPath = __DIR__ . '/../../assets/img/kop_soal_ismi.png';
        if (file_exists($kopImgPath)):
        ?>
            <div style="width: 100%; margin-bottom: 12px;">
                <img src="<?= BASE_URL ?>/assets/img/kop_soal_ismi.png?v=<?= filemtime($kopImgPath) ?>" alt="KOP Ujian SMP ISMI" style="width: 100%; height: auto; display: block;">
            </div>
        <?php else: ?>
            <table class="kop-table">
                <tr>
                    <td style="width: 65px;">
                        <?php if (file_exists(__DIR__ . '/../../assets/img/logo_1776435927.png')): ?>
                            <img src="<?= BASE_URL ?>/assets/img/logo_1776435927.png" width="58" height="58" alt="Logo">
                        <?php endif; ?>
                    </td>
                    <td style="padding-left: 12px;">
                        <div style="font-size: 16pt; font-weight: bold;"><?= htmlspecialchars($kopNamaSekolah) ?></div>
                        <div style="font-size: 10.5pt; font-weight: bold;">ISLAMIC SCHOOL OF <span style="color: #0d9488;">MINHAJ AL-ILMI</span></div>
                        <div style="font-size: 8.5pt; font-style: italic; color: #444;"><?= htmlspecialchars($kopSlogan) ?></div>
                    </td>
                    <td style="text-align: right; font-size: 8pt; line-height: 1.25;">
                        <div><?= htmlspecialchars($kopAlamat) ?></div>
                        <div><?= htmlspecialchars($kopTelp) ?></div>
                        <div>Email : <?= htmlspecialchars($kopEmail) ?></div>
                        <div style="font-weight: bold;"><?= htmlspecialchars($kopWa) ?></div>
                    </td>
                </tr>
            </table>
            <div class="kop-divider"></div>
        <?php endif; ?>

        <!-- JUDUL -->
        <div class="title-wrapper">
            <div class="title-main">KUNCI JAWABAN &amp; PEDOMAN PENSKORAN</div>
            <div class="title-sub"><?= htmlspecialchars($naskah['judul_ujian']) ?> - TP. <?= htmlspecialchars($naskah['tahun_ajaran']) ?></div>
        </div>

        <!-- IDENTITAS -->
        <table class="identitas-table">
            <tr>
                <td style="width: 20%; font-weight: bold;">Mata Pelajaran</td>
                <td style="width: 30%;"><?= htmlspecialchars($naskah['nama_mapel']) ?></td>
                <td style="width: 20%; font-weight: bold;">Tingkat Kelas</td>
                <td style="width: 30%;">Kelas <?= htmlspecialchars($naskah['tingkat_kelas']) ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Semester</td>
                <td><?= htmlspecialchars($naskah['semester']) ?></td>
                <td style="font-weight: bold;">Alokasi Waktu</td>
                <td><?= (int)$naskah['alokasi_waktu'] ?> Menit</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Total Bobot Poin</td>
                <td><strong><?= (float)$naskah['total_poin'] ?> Poin</strong></td>
                <td style="font-weight: bold;">Guru Pengampu</td>
                <td><?= htmlspecialchars($naskah['nama_guru'] ?? 'Guru Pengampu') ?></td>
            </tr>
        </table>

        <!-- A. KUNCI PILIHAN GANDA -->
        <?php if (!empty($soalPg)): ?>
            <div style="font-weight: bold; font-size: 10.5pt; margin-bottom: 4px;">A. KUNCI JAWABAN PILIHAN GANDA (Total: <?= $sumPgPoin ?> Poin)</div>
            <table class="kunci-grid">
                <thead>
                    <tr>
                        <th style="width: 8%;">No</th>
                        <th style="width: 12%;">Kunci</th>
                        <th style="width: 13%;">Poin</th>
                        <th style="width: 8%;">No</th>
                        <th style="width: 12%;">Kunci</th>
                        <th style="width: 13%;">Poin</th>
                        <th style="width: 8%;">No</th>
                        <th style="width: 12%;">Kunci</th>
                        <th style="width: 14%;">Poin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $pgCount = count($soalPg);
                    $rows = ceil($pgCount / 3);
                    for ($r = 0; $r < $rows; $r++):
                    ?>
                        <tr>
                            <?php for ($col = 0; $col < 3; $col++):
                                $itemIdx = $r + ($col * $rows);
                                if ($itemIdx < $pgCount):
                                    $it = $soalPg[$itemIdx];
                            ?>
                                <td style="font-weight: bold;"><?= $itemIdx + 1 ?></td>
                                <td style="font-weight: bold; font-size: 11pt; color: #0f172a;"><?= strtoupper(htmlspecialchars($it['kunci_jawaban'] ?: '-')) ?></td>
                                <td style="font-family: monospace;"><?= (float)$it['poin'] ?></td>
                            <?php else: ?>
                                <td>-</td><td>-</td><td>-</td>
                            <?php endif; endfor; ?>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- B. KUNCI ISIAN SINGKAT -->
        <?php if (!empty($soalIsian)): ?>
            <div style="font-weight: bold; font-size: 10.5pt; margin-top: 14px; margin-bottom: 4px;">B. KUNCI JAWABAN ISIAN SINGKAT (Total: <?= $sumIsianPoin ?> Poin)</div>
            <table class="rubrik-table">
                <thead>
                    <tr>
                        <th style="width: 6%;">No</th>
                        <th style="width: 50%;">Pertanyaan Ringkas</th>
                        <th style="width: 32%;">Kunci Jawaban</th>
                        <th style="width: 12%;">Poin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($soalIsian as $idx => $s): ?>
                        <tr>
                            <td style="text-align: center; font-weight: bold;"><?= $idx + 1 ?></td>
                            <td><?= htmlspecialchars(mb_strimwidth($s['pertanyaan'], 0, 90, '...')) ?></td>
                            <td style="font-weight: bold; color: #0f172a;"><?= htmlspecialchars($s['kunci_jawaban'] ?: '-') ?></td>
                            <td style="text-align: center; font-family: monospace; font-weight: bold;"><?= (float)$s['poin'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- C. PEDOMAN PENSKORAN ESSAY -->
        <?php if (!empty($soalEssay)): ?>
            <div style="font-weight: bold; font-size: 10.5pt; margin-top: 14px; margin-bottom: 4px;">C. PEDOMAN PENSKORAN URAIAN / ESSAY (Total: <?= $sumEssayPoin ?> Poin)</div>
            <table class="rubrik-table">
                <thead>
                    <tr>
                        <th style="width: 6%;">No</th>
                        <th style="width: 44%;">Pertanyaan Uraian</th>
                        <th style="width: 38%;">Pedoman Penskoran / Kriteria Jawaban</th>
                        <th style="width: 12%;">Maks Poin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($soalEssay as $idx => $s): ?>
                        <tr>
                            <td style="text-align: center; font-weight: bold;"><?= $idx + 1 ?></td>
                            <td><?= htmlspecialchars($s['pertanyaan']) ?></td>
                            <td><?= nl2br(htmlspecialchars($s['kunci_jawaban'] ?: 'Jawaban lengkap, tepat, dan sistematis sesuai materi.')) ?></td>
                            <td style="text-align: center; font-family: monospace; font-weight: bold;"><?= (float)$s['poin'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- REKAPITULASI PENILAIAN -->
        <table style="width: 100%; border: 1.5px solid #000; margin-top: 16px; margin-bottom: 20px; font-size: 9.5pt; border-collapse: collapse;">
            <tr style="background: #f1f5f9;">
                <td colspan="4" style="padding: 6px 10px; font-weight: bold; border-bottom: 1px solid #000;">
                    REKAPITULASI SKOR MAKSIMAL:
                </td>
            </tr>
            <tr>
                <td style="padding: 6px 10px; width: 25%; border-right: 1px solid #000;">Pilihan Ganda: <strong><?= $sumPgPoin ?> Poin</strong></td>
                <td style="padding: 6px 10px; width: 25%; border-right: 1px solid #000;">Isian Singkat: <strong><?= $sumIsianPoin ?> Poin</strong></td>
                <td style="padding: 6px 10px; width: 25%; border-right: 1px solid #000;">Uraian / Essay: <strong><?= $sumEssayPoin ?> Poin</strong></td>
                <td style="padding: 6px 10px; width: 25%; font-weight: bold; background: #e0f2fe; text-align: center;">TOTAL: <?= (float)$naskah['total_poin'] ?> POIN</td>
            </tr>
        </table>

        <!-- TANDA TANGAN -->
        <table class="sig-table">
            <tr>
                <td style="width: 50%;">
                    Mengetahui,<br>
                    <strong><?= htmlspecialchars($kepsekJabatan) ?></strong>
                    <div style="height: 60px;"></div>
                    <div><strong><u><?= htmlspecialchars($kepsekNama) ?></u></strong></div>
                    <div style="font-size: 8.5pt;">NIP. <?= htmlspecialchars($kepsekNip ?: '-') ?></div>
                </td>
                <td style="width: 50%;">
                    <?= htmlspecialchars($tempatTerbit) ?>, <?= date('d F Y') ?><br>
                    <strong>Guru Mata Pelajaran</strong>
                    <div style="height: 60px;"></div>
                    <div><strong><u><?= htmlspecialchars($naskah['nama_guru'] ?? 'Guru Pengampu') ?></u></strong></div>
                    <div style="font-size: 8.5pt;">NIP. <?= htmlspecialchars($naskah['nip_guru'] ?: '-') ?></div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
