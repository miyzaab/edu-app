<?php
/**
 * PERANGKAT AJAR KURIKULUM MERDEKA - CETAK & EXPORT PDF RESMI
 * Format Dokumen Berstandar Kemendikbudristek & Kemenag:
 * - all: Bundel Lengkap 1 Tahun (Cover + CP + ATP + TP + Semua Modul Ajar)
 * - cp: Capaian Pembelajaran
 * - atp: Alur Tujuan Pembelajaran 1 Tahun
 * - tp: Tujuan Pembelajaran 1 Tahun
 * - modul_sem1: Seluruh Modul Ajar Semester 1
 * - modul_sem2: Seluruh Modul Ajar Semester 2
 * - modul_bab: Modul Ajar Khusus 1 Bab Tertentu
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_ajar');

$pdo = getConnection();
$id      = (int)($_GET['id'] ?? 0);
$docType = strtolower($_GET['doc_type'] ?? 'all');
$semParam = (int)($_GET['sem'] ?? 1);
$babIdxParam = (int)($_GET['bab_idx'] ?? 0);

$stmt = $pdo->prepare("
    SELECT p.*, u.nama_lengkap AS nama_guru_user 
    FROM perangkat_ajar p 
    LEFT JOIN users u ON p.user_id = u.id 
    WHERE p.id = :id LIMIT 1
");
$stmt->execute([':id' => $id]);
$doc = $stmt->fetch();

if (!$doc) {
    die("<div style='font-family:sans-serif; padding:40px; text-align:center;'>
            <h2>⚠️ Dokumen Tidak Ditemukan</h2>
            <p>Dokumen Perangkat Ajar dengan ID #{$id} tidak ditemukan di database.</p>
            <a href='index.php' style='display:inline-block; padding:10px 20px; background:#0d6efd; color:#fff; text-decoration:none; border-radius:6px;'>Kembali ke Dashboard</a>
         </div>");
}

$modul = json_decode($doc['modul_ajar_json'] ?? '{}', true) ?: [];
$smt1  = $modul['semester_1'] ?? ['bab_list' => []];
$smt2  = $modul['semester_2'] ?? ['bab_list' => []];

$allBabList = array_merge(
    $smt1['bab_list'] ?? [],
    $smt2['bab_list'] ?? []
);

// Fallback & Setting Sekolah
$namaSekolah    = !empty($modul['nama_sekolah']) ? $modul['nama_sekolah'] : getSetting('nama_sekolah', SCHOOL_NAME);
$alamatSekolah  = getSetting('alamat_sekolah', 'Jl. Nakula No. 1, Kota / Kabupaten');
$teleponSekolah = getSetting('telepon_sekolah', '021-0000000');
$emailSekolah   = getSetting('email_sekolah', 'info@sekolah.sch.id');

$namaGuru   = !empty($modul['nama_guru']) ? $modul['nama_guru'] : ($doc['nama_guru_user'] ?? $_SESSION['nama_lengkap']);
$nipGuru    = !empty($modul['nip_guru']) ? $modul['nip_guru'] : '—';
$namaKepsek = !empty($modul['nama_kepsek']) ? $modul['nama_kepsek'] : getSetting('nama_kepsek', 'Kepala Sekolah, M.Pd');
$nipKepsek  = !empty($modul['nip_kepsek']) ? $modul['nip_kepsek'] : getSetting('nip_kepsek', '—');
$jpPerPekan = (int)($doc['jp_per_pekan'] ?? ($modul['jp_per_pekan'] ?? 4));

$docTitles = [
    'all'        => 'BUNDEL PERANGKAT AJAR LENGKAP 1 TAHUN',
    'cp'         => 'CAPAIAN PEMBELAJARAN (CP)',
    'tp'         => 'TUJUAN PEMBELAJARAN (TP)',
    'atp'        => 'ALUR TUJUAN PEMBELAJARAN (ATP)',
    'modul_sem1' => 'MODUL AJAR SEMESTER 1 (GANJIL)',
    'modul_sem2' => 'MODUL AJAR SEMESTER 2 (GENAP)',
    'modul_bab'  => 'MODUL AJAR BAB'
];

$titleText = $docTitles[$docType] ?? 'PERANGKAT AJAR KURIKULUM MERDEKA';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titleText ?> — <?= htmlspecialchars($doc['mapel']) ?> Kelas <?= htmlspecialchars($doc['kelas']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Times+New+Roman&family=Inter:wght@400;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            line-height: 1.45;
            color: #000;
            background: #cbd5e1;
            margin: 0;
            padding: 25px 0;
        }

        /* BAR AKSI CETAK DI LAYAR */
        .action-bar {
            max-width: 920px;
            margin: 0 auto 25px auto;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
            padding: 12px 24px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.8);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .action-bar select {
            font-family: 'Inter', sans-serif;
            font-size: 0.88rem;
            font-weight: 600;
            border: 1.5px solid #e2e8f0;
            background: #ffffff;
            color: #1e293b;
            padding: 8px 16px;
            border-radius: 10px;
            outline: none;
            cursor: pointer;
        }

        .action-bar button, .action-bar a {
            font-family: 'Inter', sans-serif;
            font-size: 0.88rem;
            font-weight: 700;
            padding: 9px 18px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .btn-print {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
            border: none;
        }
        .btn-print:hover {
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
            transform: translateY(-1px);
        }

        .btn-back {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }
        .btn-back:hover {
            background: #e2e8f0;
        }

        /* LEMBAR KERJA A4 RESMI */
        .paper-wrapper {
            max-width: 920px;
            margin: 0 auto;
        }

        .paper {
            background: #ffffff;
            width: 100%;
            min-height: 297mm;
            padding: 20mm 20mm 22mm 20mm;
            margin-bottom: 25px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            box-sizing: border-box;
            position: relative;
        }

        /* KOP SURAT */
        .kop-table {
            width: 100%;
            border-bottom: 3px double #000;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .kop-table td {
            vertical-align: middle;
        }
        .kop-logo {
            width: 75px;
            text-align: center;
        }
        .kop-logo img {
            max-width: 65px;
            height: auto;
        }
        .kop-text {
            text-align: center;
        }
        .kop-instansi {
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .kop-nama-sekolah {
            font-size: 15pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 2px 0;
            letter-spacing: 0.8px;
        }
        .kop-alamat {
            font-size: 9pt;
            color: #333;
            font-style: italic;
        }

        /* HEADER JUDUL DOKUMEN */
        .title-header-center {
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 15px 0 20px 0;
            line-height: 1.35;
            letter-spacing: 0.5px;
        }

        /* TABEL IDENTITAS */
        .meta-info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 10.5pt;
        }
        .meta-info-table td {
            padding: 3px 0;
            vertical-align: top;
        }
        .meta-info-table .label-col {
            width: 20%;
            font-weight: bold;
        }
        .meta-info-table .colon-col {
            width: 2%;
            text-align: center;
        }
        .meta-info-table .value-col {
            width: 28%;
        }

        /* SECTION TITLE */
        .section-title {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1.5px solid #000;
            padding-bottom: 3px;
            margin-top: 20px;
            margin-bottom: 10px;
            letter-spacing: 0.3px;
        }

        /* TABEL DATA */
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 16px;
            font-size: 10pt;
        }
        .table-custom th, .table-custom td {
            border: 1px solid #000;
            padding: 6px 8px;
            vertical-align: top;
        }
        .table-custom th {
            background-color: #f1f5f9;
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
        }

        /* TANDA TANGAN */
        .ttd-container {
            width: 100%;
            margin-top: 35px;
            page-break-inside: avoid;
        }
        .ttd-table {
            width: 100%;
            border-collapse: collapse;
        }
        .ttd-table td {
            width: 50%;
            vertical-align: top;
            font-size: 10.5pt;
        }
        .ttd-space {
            height: 65px;
        }

        /* PRINT MEDIA STYLES */
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .action-bar {
                display: none !important;
            }
            .paper {
                box-shadow: none;
                margin: 0;
                padding: 0;
                width: 100%;
                min-height: auto;
                page-break-after: always;
            }
            .paper:last-child {
                page-break-after: avoid;
            }
            @page {
                size: A4 portrait;
                margin: 1.8cm 1.5cm 1.8cm 1.5cm;
            }
        }
    </style>
</head>
<body>

    <!-- BAR AKSI CETAK -->
    <div class="action-bar">
        <div style="display: flex; align-items: center; gap: 10px;">
            <a href="view.php?id=<?= $id ?>" class="btn-back">
                <i class="bi bi-arrow-left"></i> Kembali ke Dokumen
            </a>
            <select onchange="window.location.href='print.php?id=<?= $id ?>&doc_type=' + this.value">
                <option value="all" <?= $docType === 'all' ? 'selected' : '' ?>>📦 Bundel Lengkap 1 Tahun</option>
                <option value="cp" <?= $docType === 'cp' ? 'selected' : '' ?>>📄 1. Capaian Pembelajaran (CP)</option>
                <option value="atp" <?= $docType === 'atp' ? 'selected' : '' ?>>📄 2. Alur Pembelajaran (ATP 1 Tahun)</option>
                <option value="tp" <?= $docType === 'tp' ? 'selected' : '' ?>>📄 3. Tujuan Pembelajaran (TP)</option>
                <option value="modul_sem1" <?= $docType === 'modul_sem1' ? 'selected' : '' ?>>📂 Modul Ajar Semester 1</option>
                <option value="modul_sem2" <?= $docType === 'modul_sem2' ? 'selected' : '' ?>>📂 Modul Ajar Semester 2</option>
            </select>
        </div>

        <button onclick="window.print()" class="btn-print">
            <i class="bi bi-printer-fill"></i> Cetak / Simpan PDF
        </button>
    </div>

    <?php
    // Helper Render Kop Surat
    function renderKopSurat($namaSekolah, $alamatSekolah, $teleponSekolah, $emailSekolah) {
        ?>
        <table class="kop-table">
            <tr>
                <td class="kop-logo">
                    <img src="../../assets/img/logo.png" alt="Logo" onerror="this.style.display='none'">
                </td>
                <td class="kop-text">
                    <div class="kop-instansi">YAYASAN PENDIDIKAN DAN SOSIAL ISLAM</div>
                    <div class="kop-nama-sekolah"><?= htmlspecialchars($namaSekolah) ?></div>
                    <div class="kop-alamat">
                        <?= htmlspecialchars($alamatSekolah) ?> | Telp: <?= htmlspecialchars($teleponSekolah) ?> | Email: <?= htmlspecialchars($emailSekolah) ?>
                    </div>
                </td>
            </tr>
        </table>
        <?php
    }

    // Helper Render Tanda Tangan
    function renderTTD($namaSekolah, $namaKepsek, $nipKepsek, $namaGuru, $nipGuru, $alamatSekolah) {
        $city = 'Kota / Kabupaten';
        if (!empty($alamatSekolah)) {
            $parts = explode(',', $alamatSekolah);
            if (count($parts) > 1) {
                $possibleCity = trim(end($parts));
                $possibleCity = preg_replace('/\b\d{5}\b/', '', $possibleCity);
                $possibleCity = trim($possibleCity);
                if (!empty($possibleCity) && $possibleCity !== 'Kota / Kabupaten') {
                    $city = $possibleCity;
                }
            }
        }

        $months = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        $day = date('j');
        $monthNum = date('n');
        $year = date('Y');
        $indonesianDate = $day . ' ' . $months[$monthNum] . ' ' . $year;
        ?>
        <div class="ttd-container">
            <table class="ttd-table">
                <tr>
                    <td>
                        Mengetahui,<br>
                        Kepala Sekolah <?= htmlspecialchars($namaSekolah) ?>
                        <div class="ttd-space"></div>
                        <strong><u><?= htmlspecialchars($namaKepsek) ?></u></strong><br>
                        NIP. <?= htmlspecialchars($nipKepsek) ?>
                    </td>
                    <td style="text-align: right;">
                        <?= htmlspecialchars($city) ?>, <?= $indonesianDate ?><br>
                        Guru Mata Pelajaran
                        <div class="ttd-space"></div>
                        <strong><u><?= htmlspecialchars($namaGuru) ?></u></strong><br>
                        NIP. <?= htmlspecialchars($nipGuru) ?>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    ?>

    <div class="paper-wrapper">

        <!-- ==================================================================== -->
        <!-- 1. COVER PAGE JIKA DOKUMEN BUNDEL 1 TAHUN (DOC_TYPE == ALL) -->
        <!-- ==================================================================== -->
        <?php if ($docType === 'all'): ?>
            <div class="paper" style="display: flex; flex-direction: column; justify-content: space-between; text-align: center; padding-top: 40mm; padding-bottom: 40mm;">
                <div>
                    <h2 style="font-size: 18pt; font-weight: bold; margin-bottom: 6px; letter-spacing: 1px;">
                        PERANGKAT AJAR LENGKAP
                    </h2>
                    <h1 style="font-size: 24pt; font-weight: bold; margin-bottom: 12px; text-transform: uppercase;">
                        KURIKULUM MERDEKA
                    </h1>
                    <div style="font-size: 13pt; font-weight: bold; color: #333; margin-bottom: 30px;">
                        FASE D — KELAS <?= htmlspecialchars($doc['kelas']) ?> (SMP/MTS)
                    </div>

                    <div style="width: 140px; height: 3px; background: #000; margin: 0 auto 35px auto;"></div>

                    <div style="font-size: 15pt; font-weight: bold; text-transform: uppercase; margin-bottom: 8px;">
                        MATA PELAJARAN: <?= htmlspecialchars($doc['mapel']) ?>
                    </div>
                    <div style="font-size: 12pt; color: #444;">
                        Tahun Ajaran <?= htmlspecialchars($doc['tahun_ajaran']) ?> | Beban Belajar: <?= htmlspecialchars($doc['alokasi_waktu']) ?>
                    </div>
                </div>

                <div style="margin: 40px auto; max-width: 480px; text-align: left; border: 1.5px solid #000; padding: 18px 24px; font-size: 11pt;">
                    <table style="width: 100%;">
                        <tr>
                            <td style="width: 40%; font-weight: bold;">Satuan Pendidikan</td>
                            <td style="width: 5%;">:</td>
                            <td><?= htmlspecialchars($namaSekolah) ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">Guru Pengampu</td>
                            <td>:</td>
                            <td><?= htmlspecialchars($namaGuru) ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">NIP Guru</td>
                            <td>:</td>
                            <td><?= htmlspecialchars($nipGuru) ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">Kepala Sekolah</td>
                            <td>:</td>
                            <td><?= htmlspecialchars($namaKepsek) ?></td>
                        </tr>
                    </table>
                </div>

                <div style="font-size: 10.5pt; font-weight: bold; text-transform: uppercase;">
                    <?= htmlspecialchars($namaSekolah) ?><br>
                    <?= htmlspecialchars($alamatSekolah) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ==================================================================== -->
        <!-- 2. DOKUMEN CAPAIAN PEMBELAJARAN (CP) -->
        <!-- ==================================================================== -->
        <?php if ($docType === 'cp' || $docType === 'all'): ?>
            <div class="paper">
                <?php renderKopSurat($namaSekolah, $alamatSekolah, $teleponSekolah, $emailSekolah); ?>

                <div class="title-header-center">
                    CAPAIAN PEMBELAJARAN (CP)<br>
                    MATA PELAJARAN: <?= strtoupper(htmlspecialchars($doc['mapel'])) ?><br>
                    KURIKULUM MERDEKA — FASE <?= htmlspecialchars($doc['fase']) ?> (KELAS <?= htmlspecialchars($doc['kelas']) ?>)
                </div>

                <table class="meta-info-table" style="border-bottom: 1px solid #000; padding-bottom: 8px;">
                    <tr>
                        <td class="label-col">Satuan Pendidikan</td>
                        <td class="colon-col">:</td>
                        <td class="value-col"><?= htmlspecialchars($namaSekolah) ?></td>
                        <td class="label-col">Fase / Kelas</td>
                        <td class="colon-col">:</td>
                        <td class="value-col">Fase <?= htmlspecialchars($doc['fase']) ?> (Kelas <?= htmlspecialchars($doc['kelas']) ?>)</td>
                    </tr>
                    <tr>
                        <td class="label-col">Mata Pelajaran</td>
                        <td class="colon-col">:</td>
                        <td class="value-col"><strong><?= htmlspecialchars($doc['mapel']) ?></strong></td>
                        <td class="label-col">Tahun Ajaran</td>
                        <td class="colon-col">:</td>
                        <td class="value-col"><?= htmlspecialchars($doc['tahun_ajaran']) ?></td>
                    </tr>
                    <tr>
                        <td class="label-col">Beban Belajar</td>
                        <td class="colon-col">:</td>
                        <td class="value-col"><?= htmlspecialchars($doc['alokasi_waktu']) ?></td>
                        <td class="label-col">Guru Pengampu</td>
                        <td class="colon-col">:</td>
                        <td class="value-col"><?= htmlspecialchars($namaGuru) ?></td>
                    </tr>
                </table>

                <div class="section-title">A. Deskripsi Capaian Pembelajaran Akhir Fase <?= htmlspecialchars($doc['fase']) ?></div>
                <div style="text-align: justify; margin-bottom: 15px; line-height: 1.6;">
                    <?= nl2br(htmlspecialchars($modul['cp_global'] ?? $doc['capaian_pembelajaran'])) ?>
                </div>

                <div class="section-title">B. Matriks Capaian Pembelajaran Per Bab Pembahasan</div>
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th style="width: 28%;">Bab / Materi Pokok</th>
                            <th style="width: 15%;">Semester</th>
                            <th style="width: 57%;">Rumusan Capaian Pembelajaran (CP)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($allBabList)): ?>
                            <?php foreach ($allBabList as $b): ?>
                                <tr>
                                    <td style="font-weight: bold;">
                                        Bab <?= htmlspecialchars($b['nomor']) ?>: <?= htmlspecialchars($b['judul_bersih']) ?>
                                    </td>
                                    <td style="text-align: center;">
                                        Semester <?= $b['semester'] ?>
                                    </td>
                                    <td style="text-align: justify; line-height: 1.5;">
                                        <?= htmlspecialchars($b['cp_teks']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center;">Tidak ada data bab.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php renderTTD($namaSekolah, $namaKepsek, $nipKepsek, $namaGuru, $nipGuru, $alamatSekolah); ?>
            </div>
        <?php endif; ?>

        <!-- ==================================================================== -->
        <!-- 3. DOKUMEN ALUR TUJUAN PEMBELAJARAN (ATP) -->
        <!-- ==================================================================== -->
        <?php if ($docType === 'atp' || $docType === 'all'): ?>
            <div class="paper">
                <?php renderKopSurat($namaSekolah, $alamatSekolah, $teleponSekolah, $emailSekolah); ?>

                <div class="title-header-center">
                    ALUR TUJUAN PEMBELAJARAN (ATP)<br>
                    MATA PELAJARAN: <?= strtoupper(htmlspecialchars($doc['mapel'])) ?><br>
                    KURIKULUM MERDEKA — TAHUN AJARAN <?= htmlspecialchars($doc['tahun_ajaran']) ?>
                </div>

                <table class="meta-info-table" style="border-bottom: 1px solid #000; padding-bottom: 8px;">
                    <tr>
                        <td class="label-col">Satuan Pendidikan</td>
                        <td class="colon-col">:</td>
                        <td class="value-col"><?= htmlspecialchars($namaSekolah) ?></td>
                        <td class="label-col">Fase / Kelas</td>
                        <td class="colon-col">:</td>
                        <td class="value-col">Fase <?= htmlspecialchars($doc['fase']) ?> (Kelas <?= htmlspecialchars($doc['kelas']) ?>)</td>
                    </tr>
                    <tr>
                        <td class="label-col">Mata Pelajaran</td>
                        <td class="colon-col">:</td>
                        <td class="value-col"><strong><?= htmlspecialchars($doc['mapel']) ?></strong></td>
                        <td class="label-col">Alokasi Waktu</td>
                        <td class="colon-col">:</td>
                        <td class="value-col"><?= htmlspecialchars($doc['alokasi_waktu']) ?></td>
                    </tr>
                </table>

                <div class="section-title">Matriks Alur Tujuan Pembelajaran (ATP) 1 Tahun Ajaran</div>
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Bab &amp; Semester</th>
                            <th style="width: 38%;">Tujuan Pembelajaran (TP)</th>
                            <th style="width: 27%;">Alur Aktivitas Pembelajaran</th>
                            <th style="width: 15%; text-align: center;">Alokasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($allBabList)): ?>
                            <?php foreach ($allBabList as $b): ?>
                                <tr>
                                    <td>
                                        <strong>Bab <?= htmlspecialchars($b['nomor']) ?></strong><br>
                                        <?= htmlspecialchars($b['judul_bersih']) ?><br>
                                        <small style="font-style: italic; color: #444;">Semester <?= $b['semester'] ?></small>
                                    </td>
                                    <td>
                                        <ul style="margin: 0; padding-left: 16px; line-height: 1.5;">
                                            <?php foreach ($b['tp_list'] ?? [] as $tpItem): ?>
                                                <li><?= htmlspecialchars($tpItem) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </td>
                                    <td style="text-align: justify; line-height: 1.45;">
                                        <?= htmlspecialchars($b['atp_teks']) ?>
                                    </td>
                                    <td style="text-align: center; font-weight: bold;">
                                        <?= htmlspecialchars($b['alokasi_jp']) ?> JP<br>
                                        <small style="font-weight: normal; color: #444;">(<?= htmlspecialchars($b['jumlah_pertemuan']) ?> Pertemuan)</small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php renderTTD($namaSekolah, $namaKepsek, $nipKepsek, $namaGuru, $nipGuru, $alamatSekolah); ?>
            </div>
        <?php endif; ?>

        <!-- ==================================================================== -->
        <!-- 4. DOKUMEN MODUL AJAR (SEMESTER 1, SEMESTER 2, ATAU BAB SPESIFIK) -->
        <!-- ==================================================================== -->
        <?php
        $renderModulList = [];

        if ($docType === 'all') {
            $renderModulList = $allBabList;
        } elseif ($docType === 'modul_sem1') {
            $renderModulList = $smt1['bab_list'] ?? [];
        } elseif ($docType === 'modul_sem2') {
            $renderModulList = $smt2['bab_list'] ?? [];
        } elseif ($docType === 'modul_bab') {
            $targetSemList = ($semParam === 2) ? ($smt2['bab_list'] ?? []) : ($smt1['bab_list'] ?? []);
            if (isset($targetSemList[$babIdxParam])) {
                $renderModulList = [$targetSemList[$babIdxParam]];
            }
        }
        ?>

        <?php if (!empty($renderModulList)): ?>
            <?php foreach ($renderModulList as $b): ?>
                <?php $m = $b['modul'] ?? []; ?>
                <div class="paper">
                    <?php renderKopSurat($namaSekolah, $alamatSekolah, $teleponSekolah, $emailSekolah); ?>

                    <div class="title-header-center">
                        MODUL AJAR KURIKULUM MERDEKA<br>
                        BAB <?= htmlspecialchars($b['nomor']) ?>: <?= strtoupper(htmlspecialchars($b['judul_bersih'])) ?><br>
                        SEMESTER <?= $b['semester'] ?> (<?= $b['semester'] === 1 ? 'GANJIL' : 'GENAP' ?>)
                    </div>

                    <div class="section-title">I. INFORMASI UMUM</div>
                    <table class="meta-info-table">
                        <tr>
                            <td class="label-col">Satuan Pendidikan</td>
                            <td class="colon-col">:</td>
                            <td class="value-col"><?= htmlspecialchars($namaSekolah) ?></td>
                            <td class="label-col">Fase / Kelas</td>
                            <td class="colon-col">:</td>
                            <td class="value-col">Fase <?= htmlspecialchars($doc['fase']) ?> (Kelas <?= htmlspecialchars($doc['kelas']) ?>)</td>
                        </tr>
                        <tr>
                            <td class="label-col">Mata Pelajaran</td>
                            <td class="colon-col">:</td>
                            <td class="value-col"><strong><?= htmlspecialchars($doc['mapel']) ?></strong></td>
                            <td class="label-col">Alokasi Waktu</td>
                            <td class="colon-col">:</td>
                            <td class="value-col"><strong><?= htmlspecialchars($m['alokasi_waktu'] ?? "{$b['alokasi_jp']} JP") ?></strong></td>
                        </tr>
                        <tr>
                            <td class="label-col">Guru Pengampu</td>
                            <td class="colon-col">:</td>
                            <td class="value-col"><?= htmlspecialchars($namaGuru) ?></td>
                            <td class="label-col">Model Pembelajaran</td>
                            <td class="colon-col">:</td>
                            <td class="value-col"><?= htmlspecialchars($m['model_pembelajaran'] ?? 'PBL') ?></td>
                        </tr>
                    </table>

                    <table class="table-custom" style="margin-top: 5px;">
                        <tr>
                            <th style="width: 25%; text-align: left;">Kompetensi Awal</th>
                            <td style="text-align: justify;"><?= htmlspecialchars($m['kompetensi_awal'] ?? '—') ?></td>
                        </tr>
                        <tr>
                            <th style="text-align: left;">Profil Pelajar Pancasila</th>
                            <td><?= htmlspecialchars(is_array($m['profil_pancasila'] ?? null) ? implode(', ', $m['profil_pancasila']) : ($m['profil_pancasila'] ?? 'Bernalar Kritis, Mandiri')) ?></td>
                        </tr>
                        <tr>
                            <th style="text-align: left;">Sarana &amp; Prasarana</th>
                            <td><?= htmlspecialchars($m['sarana_prasarana'] ?? 'Buku Siswa, Papan Tulis, LKPD') ?></td>
                        </tr>
                        <tr>
                            <th style="text-align: left;">Target Peserta Didik</th>
                            <td><?= htmlspecialchars($m['target_siswa'] ?? 'Peserta didik reguler / tipikal') ?></td>
                        </tr>
                    </table>

                    <div class="section-title">II. KOMPONEN INTI</div>
                    <table class="table-custom">
                        <tr>
                            <th style="width: 25%; text-align: left;">Tujuan Pembelajaran</th>
                            <td>
                                <ul style="margin: 0; padding-left: 16px; line-height: 1.5;">
                                    <?php foreach ($b['tp_list'] ?? [] as $tpItem): ?>
                                        <li><?= htmlspecialchars($tpItem) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                        </tr>
                        <tr>
                            <th style="text-align: left;">Pemahaman Bermakna</th>
                            <td style="text-align: justify;"><?= nl2br(htmlspecialchars($m['pemahaman_bermakna'] ?? '—')) ?></td>
                        </tr>
                        <tr>
                            <th style="text-align: left;">Pertanyaan Pemantik</th>
                            <td style="text-align: justify;"><?= nl2br(htmlspecialchars($m['pertanyaan_pemantik'] ?? '—')) ?></td>
                        </tr>
                    </table>

                    <div style="font-weight: bold; margin-top: 10px; margin-bottom: 4px;">Rencana Kegiatan Pembelajaran:</div>
                    <table class="table-custom">
                        <tr>
                            <th style="width: 25%; text-align: left;">1. Pendahuluan</th>
                            <td style="text-align: justify; line-height: 1.5;"><?= nl2br(htmlspecialchars($m['kegiatan_pendahuluan'] ?? '—')) ?></td>
                        </tr>
                        <tr>
                            <th style="text-align: left;">2. Kegiatan Inti</th>
                            <td style="text-align: justify; line-height: 1.5;"><?= nl2br(htmlspecialchars($m['kegiatan_inti'] ?? '—')) ?></td>
                        </tr>
                        <tr>
                            <th style="text-align: left;">3. Penutup</th>
                            <td style="text-align: justify; line-height: 1.5;"><?= nl2br(htmlspecialchars($m['kegiatan_penutup'] ?? '—')) ?></td>
                        </tr>
                    </table>

                    <div style="font-weight: bold; margin-top: 10px; margin-bottom: 4px;">Asesmen Pembelajaran:</div>
                    <table class="table-custom">
                        <tr>
                            <th style="width: 25%; text-align: left;">Asesmen Diagnostik</th>
                            <td><?= htmlspecialchars($m['asesmen_diagnostik'] ?? 'Tanya jawab pemantik awal pertemuan.') ?></td>
                        </tr>
                        <tr>
                            <th style="text-align: left;">Asesmen Formatif</th>
                            <td><?= htmlspecialchars($m['asesmen_formatif'] ?? 'Observasi keaktifan diskusi dan pengerjaan LKPD.') ?></td>
                        </tr>
                        <tr>
                            <th style="text-align: left;">Asesmen Sumatif</th>
                            <td><?= htmlspecialchars($m['asesmen_sumatif'] ?? 'Ujian pemecahan masalah / tes tertulis akhir bab.') ?></td>
                        </tr>
                    </table>

                    <div class="section-title">III. LAMPIRAN (LKPD &amp; GLOSARIUM)</div>
                    <div style="border: 1px solid #000; padding: 10px 14px; background: #fafafa; margin-bottom: 10px; font-size: 9.5pt; line-height: 1.5;">
                        <strong style="text-transform: uppercase;">Lembar Kerja Peserta Didik (LKPD):</strong><br>
                        <?= nl2br(htmlspecialchars($m['lkpd_content'] ?? 'Tersedia pada lampiran terpisah.')) ?>
                    </div>

                    <table class="table-custom" style="font-size: 9.5pt;">
                        <tr>
                            <th style="width: 25%; text-align: left;">Glosarium</th>
                            <td><?= nl2br(htmlspecialchars($m['glosarium'] ?? '—')) ?></td>
                        </tr>
                        <tr>
                            <th style="text-align: left;">Daftar Pustaka</th>
                            <td><?= nl2br(htmlspecialchars($m['daftar_pustaka'] ?? '—')) ?></td>
                        </tr>
                    </table>

                    <?php renderTTD($namaSekolah, $namaKepsek, $nipKepsek, $namaGuru, $nipGuru, $alamatSekolah); ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>

</body>
</html>
