<?php
/**
 * PERANGKAT AJAR KURIKULUM MERDEKA - Cetak / Export PDF Resmi Sesuai Format Standar
 * Format Dokumen: CP, TP, ATP, Modul Ajar, & Paket Lengkap (ALL)
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_ajar');

$pdo = getConnection();
$id      = (int)($_GET['id'] ?? 0);
$docType = strtolower($_GET['doc_type'] ?? 'modul');

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
            <a href='index.php' style='display:inline-block; padding:10px 20px; background:#0d6efd; color:#fff; text-decoration:none; border-radius:6px;'>Kembali ke Perangkat Ajar</a>
         </div>");
}

$modul = json_decode($doc['modul_ajar_json'] ?? '{}', true) ?: [];
$semesterText = !empty($doc['semester']) ? $doc['semester'] : '1 (Ganjil)';

// Fallback & Setting Sekolah
$namaSekolah    = !empty($modul['nama_sekolah']) ? $modul['nama_sekolah'] : getSetting('nama_sekolah', SCHOOL_NAME);
$alamatSekolah  = getSetting('alamat_sekolah', 'Jl. Nakula No. 1, Kota / Kabupaten');
$teleponSekolah = getSetting('telepon_sekolah', '021-0000000');
$emailSekolah   = getSetting('email_sekolah', 'info@sekolah.sch.id');

$namaGuru = !empty($modul['nama_guru']) ? $modul['nama_guru'] : ($doc['nama_guru_user'] ?? $_SESSION['nama_lengkap']);
$nipGuru  = !empty($modul['nip_guru']) ? $modul['nip_guru'] : '—';

$namaKepsek = !empty($modul['nama_kepsek']) ? $modul['nama_kepsek'] : getSetting('nama_kepsek', 'Kepala Sekolah, M.Pd');
$nipKepsek  = !empty($modul['nip_kepsek']) ? $modul['nip_kepsek'] : getSetting('nip_kepsek', '—');

$pancasilaList = is_array($modul['profil_pancasila'] ?? null) ? implode(', ', $modul['profil_pancasila']) : 'Bernalar Kritis, Gotong Royong, Kreatif';

$docTitles = [
    'cp'    => 'CAPAIAN PEMBELAJARAN (CP)',
    'tp'    => 'TUJUAN PEMBELAJARAN (TP)',
    'atp'   => 'ALUR TUJUAN PEMBELAJARAN (ATP)',
    'modul' => 'MODUL AJAR KURIKULUM MERDEKA',
    'all'   => 'PAKET LENGKAP PERANGKAT AJAR'
];
$titleText = $docTitles[$docType] ?? 'MODUL AJAR KURIKULUM MERDEKA';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titleText ?> — <?= htmlspecialchars($doc['mapel']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Times+New+Roman&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.4;
            color: #000;
            background: #cbd5e1;
            margin: 0;
            padding: 30px 0;
        }

        /* BAR AKSI CETAK */
        .action-bar {
            max-width: 900px;
            margin: 0 auto 30px auto;
            background: rgba(255, 255, 255, 0.95);
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
            transition: all 0.2s;
            cursor: pointer;
        }
        .action-bar select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .action-bar button, .action-bar a {
            font-family: 'Inter', sans-serif;
            font-size: 0.88rem;
            font-weight: 600;
            padding: 9px 18px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-print-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff !important;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        }
        .btn-print-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
        }

        .btn-print-secondary {
            background: #ffffff;
            color: #475569 !important;
            border: 1.5px solid #e2e8f0;
        }
        .btn-print-secondary:hover {
            background: #f8fafc;
            color: #0f172a !important;
        }

        .paper-wrapper {
            display: flex;
            flex-direction: column;
            gap: 40px;
            align-items: center;
        }

        .paper {
            background: #ffffff;
            width: 210mm;
            min-height: 297mm;
            padding: 30mm 20mm 25mm 25mm; /* Standard official letter margins: top 3cm, right 2cm, bottom 2.5cm, left 2.5cm */
            box-sizing: border-box;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            position: relative;
        }

        /* TITLE HEADER CENTER */
        .title-header-center {
            text-align: center;
            font-weight: bold;
            font-size: 13pt;
            text-transform: uppercase;
            margin-bottom: 25px;
            line-height: 1.3;
        }

        .meta-info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
            font-size: 11pt;
        }

        .meta-info-table td {
            padding: 4px 0;
            vertical-align: top;
        }

        .meta-info-table td.label-col {
            width: 20%;
            font-weight: bold;
        }

        .meta-info-table td.colon-col {
            width: 3%;
        }

        .meta-info-table td.value-col {
            width: 27%;
        }

        /* HEADING SECTIONS */
        .section-title {
            font-weight: bold;
            font-size: 12pt;
            margin-top: 22px;
            margin-bottom: 8px;
            text-transform: uppercase;
            border-bottom: 1.5px solid #000;
            padding-bottom: 3px;
            display: inline-block;
        }

        /* TABEL FORMAT DOKUMEN */
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 20px;
            font-size: 11pt;
        }

        .table-custom th, .table-custom td {
            border: 1px solid #000;
            padding: 8px 10px;
            vertical-align: top;
            line-height: 1.4;
        }

        .table-custom th {
            background-color: #f8fafc;
            text-align: center;
            font-weight: bold;
        }

        /* BULLET LIST & PARAGRAPH */
        .bullet-list {
            margin: 6px 0 12px 20px;
            padding: 0;
        }

        .bullet-list li {
            margin-bottom: 6px;
            text-align: justify;
            line-height: 1.4;
        }

        /* TANDA TANGAN FORMAL */
        .ttd-container {
            margin-top: 45px;
            page-break-inside: avoid;
        }

        .ttd-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11pt;
        }

        .ttd-table td {
            border: none;
            width: 50%;
            vertical-align: top;
            line-height: 1.4;
        }

        .ttd-space {
            height: 75px;
        }

        @page {
            size: A4;
            margin: 0; /* Let .paper padding handle spacing so screen & print match exactly! */
        }

        @media print {
            body {
                background: none;
                padding: 0;
                margin: 0;
            }
            .action-bar {
                display: none !important;
            }
            .paper {
                box-shadow: none;
                margin: 0;
                width: 210mm;
                min-height: 297mm;
                padding: 30mm 20mm 25mm 25mm; /* Maintain identical spacing on paper */
                page-break-after: always;
            }
            .paper:last-child {
                page-break-after: avoid;
            }
            /* Avoid splitting tables across pages if possible */
            .table-custom, .meta-info-table, tr, td, th {
                page-break-inside: avoid;
            }
            h1, h2, h3, h4, h5, h6, .section-title {
                page-break-after: avoid;
            }
        }
    </style>
</head>
<body>

    <!-- ACTION BAR -->
    <div class="action-bar">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-family: 'Inter', sans-serif; font-weight: 700; color: #0f172a;">
                📄 Dokumen Cetak:
            </span>
            <select onchange="location = 'print.php?id=<?= $id ?>&doc_type=' + this.value;">
                <option value="all" <?= $docType === 'all' ? 'selected' : '' ?>>Paket Lengkap (CP + TP + ATP + Modul)</option>
                <option value="cp" <?= $docType === 'cp' ? 'selected' : '' ?>>Capaian Pembelajaran (CP)</option>
                <option value="tp" <?= $docType === 'tp' ? 'selected' : '' ?>>Tujuan Pembelajaran (TP)</option>
                <option value="atp" <?= $docType === 'atp' ? 'selected' : '' ?>>Alur Tujuan Pembelajaran (ATP)</option>
                <option value="modul" <?= $docType === 'modul' ? 'selected' : '' ?>>Modul Ajar Lengkap</option>
            </select>
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="window.print()" class="btn-print-primary">
                <i class="bi bi-printer-fill"></i> Cetak Dokumen PDF
            </button>
            <a href="index.php" class="btn-print-secondary">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </div>
    </div>

    <!-- HELPER RENDER KOP SURAT SEKOLAH -->
    <?php
    function renderKopSurat($namaSekolah, $alamatSekolah, $teleponSekolah, $emailSekolah) {
        ?>
        <div class="kop-container" style="display: flex; align-items: center; justify-content: center; margin-bottom: 10px; border-bottom: 2.5px solid #000; padding-bottom: 5px; position: relative;">
            <div style="position: absolute; left: 0px; top: 50%; transform: translateY(-50%);">
                <?= getLogoHtml(60) ?>
            </div>
            <div style="text-align: center; width: 100%; padding-left: 80px; padding-right: 80px; box-sizing: border-box;">
                <span style="font-size: 11pt; font-weight: bold; letter-spacing: 1px; text-transform: uppercase;">SATUAN PENDIDIKAN</span><br>
                <span style="font-size: 15pt; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase; line-height: 1.2; display: block; margin: 2px 0;"><?= htmlspecialchars($namaSekolah) ?></span>
                <span style="font-size: 9pt; font-style: italic; font-weight: normal; color: #334155; display: block; line-height: 1.3;">
                    Alamat: <?= htmlspecialchars($alamatSekolah) ?><br>
                    Telp: <?= htmlspecialchars($teleponSekolah) ?> | Email: <?= htmlspecialchars($emailSekolah) ?>
                </span>
            </div>
        </div>
        <div style="border-bottom: 0.75px solid #000; margin-bottom: 20px; margin-top: -8px;"></div>
        <?php
    }

    function renderTTD($namaSekolah, $namaKepsek, $nipKepsek, $namaGuru, $nipGuru, $alamatSekolah = '') {
        $city = 'Bandung';
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

    <!-- PAPER WRAPPER FOR STACKED PREVIEW -->
    <div class="paper-wrapper">

        <?php if ($docType === 'cp' || $docType === 'all'): ?>
            <div class="paper">
            <!-- ================= FORMAT 1: CAPAIAN PEMBELAJARAN (CP) RESMI ================= -->
            <?php renderKopSurat($namaSekolah, $alamatSekolah, $teleponSekolah, $emailSekolah); ?>

            <div class="title-header-center">
                DOKUMEN CAPAIAN PEMBELAJARAN (CP)<br>
                MATA PELAJARAN: <?= strtoupper(htmlspecialchars($doc['mapel'])) ?><br>
                KURIKULUM MERDEKA — FASE <?= htmlspecialchars($doc['fase']) ?>
            </div>

            <table class="meta-info-table" style="margin-bottom: 16px; border-bottom: 1px solid #000; padding-bottom: 8px;">
                <tr>
                    <td class="label-col">Satuan Pendidikan</td>
                    <td class="colon-col">:</td>
                    <td class="value-col"><?= htmlspecialchars($namaSekolah) ?></td>
                    <td class="label-col">Fase / Kelas</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">Fase <?= htmlspecialchars($doc['fase']) ?> (<?= htmlspecialchars($doc['kelas']) ?>)</td>
                </tr>
                <tr>
                    <td class="label-col">Mata Pelajaran</td>
                    <td class="colon-col">:</td>
                    <td class="value-col"><strong><?= htmlspecialchars($doc['mapel']) ?></strong></td>
                    <td class="label-col">Semester / TA</td>
                    <td class="colon-col">:</td>
                    <td class="value-col"><?= htmlspecialchars($semesterText) ?> / <?= htmlspecialchars($doc['tahun_ajaran']) ?></td>
                </tr>
                <tr>
                    <td class="label-col">Guru Pengampu</td>
                    <td class="colon-col">:</td>
                    <td class="value-col"><?= htmlspecialchars($namaGuru) ?></td>
                    <td class="label-col">NIP Guru</td>
                    <td class="colon-col">:</td>
                    <td class="value-col"><?= htmlspecialchars($nipGuru) ?></td>
                </tr>
            </table>

            <!-- 1. RASIONAL -->
            <div class="section-title">1. Rasional Mata Pelajaran <?= htmlspecialchars($doc['mapel']) ?></div>
            <div style="text-align: justify; margin-bottom: 10px;">
                Mata pelajaran <?= htmlspecialchars($doc['mapel']) ?> berupaya membimbing dan membentuk peserta didik agar memiliki pemahaman yang komprehensif, sikap spiritual yang tangguh, serta keahlian bernalar kritis. Pembelajaran ini mengintegrasikan antara penguasaan teori, pengembangan karakter berakhlak mulia, dan implementasi amalan nyata dalam kehidupan bermasyarakat.
            </div>

            <!-- 2. TUJUAN -->
            <div class="section-title">2. Tujuan Mata Pelajaran</div>
            <div>Mata pelajaran <?= htmlspecialchars($doc['mapel']) ?> bertujuan agar peserta didik mampu:</div>
            <ul class="bullet-list" style="list-style-type: disc;">
                <li>Memahami dan menganalisis konsep-konsep dasar <?= htmlspecialchars($doc['mapel']) ?> secara akurat dan bernalar kritis.</li>
                <li>Menerapkan prinsip dan nilai-nilai kebaikan dalam kehidupan sehari-hari secara konsisten dan bertanggung jawab.</li>
                <li>Mengembangkan ketaqwaan, keteladanan akhlak, serta kemampuan memecahkan masalah kontekstual.</li>
            </ul>

            <!-- 3. KARAKTERISTIK -->
            <div class="section-title">3. Karakteristik Mata Pelajaran</div>
            <div style="text-align: justify; margin-bottom: 6px;">
                Mata pelajaran ini dirancang berbasis kompetensi yang menekankan pada 3 (tiga) pilar utama:
            </div>
            <ul class="bullet-list" style="list-style-type: disc;">
                <li><strong>Pemahaman Konseptual:</strong> Kemampuan mendasar dalam memahami kaidah, definisi, dan teori materi.</li>
                <li><strong>Internalisasi Nilai:</strong> Penanaman nilai-nilai akhlak, Profil Pelajar Pancasila, dan kebiasaan positif.</li>
                <li><strong>Praktik & Amalan Nyata:</strong> Kemampuan mengaplikasikan materi pembelajaran dalam bentuk amalan atau karya nyata.</li>
            </ul>

            <!-- 4. CAPAIAN PEMBELAJARAN PER ELEMEN -->
            <div class="section-title">4. Capaian Pembelajaran (CP) Per Elemen / Bab Akhir Fase <?= htmlspecialchars($doc['fase']) ?></div>
            <?php
            $poinRaw = $modul['poin_bab_raw'] ?? '';
            $linesBab = array_filter(array_map('trim', explode("\n", $poinRaw)));
            $cpFullText = $doc['capaian_pembelajaran'] ?? '';
            $mapelName = $doc['mapel'] ?? '';
            $isAgamaPrint = (stripos($mapelName, 'fiqih') !== false || stripos($mapelName, 'agama') !== false || stripos($mapelName, 'pai') !== false);
            $isBahasaPrint = (stripos($mapelName, 'bahasa') !== false || stripos($mapelName, 'indonesia') !== false || stripos($mapelName, 'inggris') !== false);
            ?>
            <table class="table-custom">
                <thead>
                    <tr>
                        <th style="width: 32%;">Elemen / Bab Pembahasan</th>
                        <th style="width: 68%;">Capaian Pembelajaran (Akhir Fase <?= htmlspecialchars($doc['fase']) ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($linesBab)): ?>
                        <?php foreach ($linesBab as $bLine): 
                            $parts = explode(':', $bLine, 2);
                            $elemName = trim($parts[0]);
                            $elemDesc = isset($parts[1]) ? trim($parts[1]) : $elemName;

                            // Cari narasi spesifik dari teks CP jika ada
                            $matchedNarration = "";
                            $cpLines = explode("\n", $cpFullText);
                            foreach ($cpLines as $cLine) {
                                if (stripos($cLine, $elemName) !== false || stripos($cLine, $elemDesc) !== false) {
                                    $matchedNarration = trim(preg_replace('/^[\s•\*\-\d\.]+/u', '', $cLine));
                                    break;
                                }
                            }

                            if (empty($matchedNarration)) {
                                if ($isAgamaPrint) {
                                    $matchedNarration = "Peserta didik memahami syariat " . $elemDesc . ", meyakini landasan hukumnya, serta mampu mengamalkan tata cara pelaksanaannya secara istiqamah dalam kehidupan sehari-hari.";
                                } elseif ($isBahasaPrint) {
                                    $matchedNarration = "Peserta didik menganalisis struktur dan kebahasaan " . $elemDesc . ", menyerap gagasan secara kritis, serta mampu menyajikan karya lisan/tulisan yang efektif.";
                                } else {
                                    $matchedNarration = "Peserta didik memahami konsep mendasar " . $elemDesc . ", menganalisis permasalahan kontekstual, serta menerapkan solusinya secara bernalar kritis.";
                                }
                            }
                        ?>
                            <tr>
                                <td style="font-weight: bold;"><?= htmlspecialchars($elemName) ?><br><small style="font-weight: normal; color: #333;"><?= htmlspecialchars($elemDesc) ?></small></td>
                                <td style="text-align: justify;"><?= htmlspecialchars($matchedNarration) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td style="font-weight: bold;"><?= htmlspecialchars($doc['elemen'] ?: 'Pemahaman & Penerapan') ?></td>
                            <td style="text-align: justify;"><?= nl2br(htmlspecialchars($doc['capaian_pembelajaran'])) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- 5. ALUR DISTRIBUSI PEMBELAJARAN -->
            <div class="section-title">5. Alur Distribusi Pembelajaran</div>
            <ul class="bullet-list" style="list-style-type: disc;">
                <?php if (!empty($linesBab)): ?>
                    <li><strong>Semester Ganjil:</strong> Membahas materi <?= htmlspecialchars($linesBab[0] ?? 'Bab Utama') ?>.</li>
                    <li><strong>Semester Genap:</strong> Pendalaman materi <?= htmlspecialchars(implode(', ', array_slice($linesBab, 1))) ?>.</li>
                <?php else: ?>
                    <li><strong>Cakupan Fase:</strong> Penguasaan konsep dasar, pendalaman materi, dan evaluasi praktik harian.</li>
                <?php endif; ?>
            </ul>

            <?php renderTTD($namaSekolah, $namaKepsek, $nipKepsek, $namaGuru, $nipGuru, $alamatSekolah); ?>
            </div>
        <?php endif; ?>

        <?php if ($docType === 'tp' || $docType === 'all'): ?>
            <div class="paper">
            <!-- ================= FORMAT 2: TUJUAN PEMBELAJARAN (TP) ================= -->
            <?php renderKopSurat($namaSekolah, $alamatSekolah, $teleponSekolah, $emailSekolah); ?>

            <div class="title-header-center">
                DOKUMEN TUJUAN PEMBELAJARAN (TP)<br>
                KURIKULUM MERDEKA
            </div>

            <table class="meta-info-table">
                <tr>
                    <td class="label-col">Mata Pelajaran</td>
                    <td class="colon-col">:</td>
                    <td class="value-col"><strong><?= htmlspecialchars($doc['mapel']) ?></strong></td>
                </tr>
                <tr>
                    <td class="label-col">Satuan Pendidikan</td>
                    <td class="colon-col">:</td>
                    <td class="value-col"><?= htmlspecialchars($namaSekolah) ?></td>
                </tr>
                <tr>
                    <td class="label-col">Tahun Pelajaran</td>
                    <td class="colon-col">:</td>
                    <td class="value-col"><?= htmlspecialchars($doc['tahun_ajaran']) ?></td>
                </tr>
                <tr>
                    <td class="label-col">Fase / Kelas / Semester</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">Fase <?= htmlspecialchars($doc['fase']) ?> / Kelas <?= htmlspecialchars($doc['kelas']) ?> / Semester <?= htmlspecialchars($semesterText) ?></td>
                </tr>
            </table>

            <div class="section-title">1. Acuan Capaian Pembelajaran (CP)</div>
            <div style="border: 1px solid #000; padding: 10px; background: #fafafa; font-style: italic; margin-bottom: 15px;">
                "<?= htmlspecialchars($doc['capaian_pembelajaran']) ?>"
            </div>

            <div class="section-title">2. Rincian Rumusan Tujuan Pembelajaran (TP)</div>
            <table class="table-custom">
                <thead>
                    <tr>
                        <th style="width: 30%;">Topik / Bab Pembahasan</th>
                        <th style="width: 70%;">Rumusan Tujuan Pembelajaran (TP)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="font-weight: bold;"><?= htmlspecialchars($doc['topik']) ?></td>
                        <td><?= nl2br(htmlspecialchars($doc['tujuan_pembelajaran'])) ?></td>
                    </tr>
                </tbody>
            </table>

            <?php renderTTD($namaSekolah, $namaKepsek, $nipKepsek, $namaGuru, $nipGuru, $alamatSekolah); ?>
            </div>
        <?php endif; ?>

        <?php if ($docType === 'atp' || $docType === 'all'): ?>
            <div class="paper">
            <!-- ================= FORMAT 3: ALUR TUJUAN PEMBELAJARAN (ATP) ================= -->
            <?php renderKopSurat($namaSekolah, $alamatSekolah, $teleponSekolah, $emailSekolah); ?>

            <div class="title-header-center">
                ALUR TUJUAN PEMBELAJARAN (ATP)<br>
                KURIKULUM MERDEKA
            </div>

            <table class="meta-info-table">
                <tr>
                    <td class="label-col">Mata Pelajaran</td>
                    <td class="colon-col">:</td>
                    <td class="value-col"><strong><?= htmlspecialchars($doc['mapel']) ?></strong></td>
                </tr>
                <tr>
                    <td class="label-col">Satuan Pendidikan</td>
                    <td class="colon-col">:</td>
                    <td class="value-col"><?= htmlspecialchars($namaSekolah) ?></td>
                </tr>
                <tr>
                    <td class="label-col">Tahun Pelajaran</td>
                    <td class="colon-col">:</td>
                    <td class="value-col"><?= htmlspecialchars($doc['tahun_ajaran']) ?></td>
                </tr>
                <tr>
                    <td class="label-col">Fase / Kelas / Semester</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">Fase <?= htmlspecialchars($doc['fase']) ?> / Kelas <?= htmlspecialchars($doc['kelas']) ?> / Semester <?= htmlspecialchars($semesterText) ?></td>
                </tr>
            </table>

            <div class="section-title">A. Capaian Pembelajaran (CP)</div>
            <div style="margin-bottom: 10px;">
                <strong><?= htmlspecialchars($doc['elemen'] ?: 'Elemen Utama') ?>:</strong><br>
                <?= htmlspecialchars($doc['capaian_pembelajaran']) ?>
            </div>

            <div class="section-title">B. Alur Tujuan Pembelajaran (ATP)</div>
            <table class="table-custom">
                <thead>
                    <tr style="background: #e5e7eb;">
                        <td colspan="3" style="font-weight: bold; text-transform: uppercase;">SEMESTER <?= strtoupper(htmlspecialchars($doc['semester'])) ?></td>
                    </tr>
                    <tr>
                        <th style="width: 25%;">Bab / Topik</th>
                        <th style="width: 45%;">Tujuan Pembelajaran (TP)</th>
                        <th style="width: 30%;">Alur & Kata Kunci Materi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="font-weight: bold;"><?= htmlspecialchars($doc['topik']) ?></td>
                        <td><?= nl2br(htmlspecialchars($doc['tujuan_pembelajaran'])) ?></td>
                        <td><?= nl2br(htmlspecialchars($doc['alur_tujuan_pembelajaran'])) ?></td>
                    </tr>
                </tbody>
            </table>

            <?php renderTTD($namaSekolah, $namaKepsek, $nipKepsek, $namaGuru, $nipGuru, $alamatSekolah); ?>
            </div>
        <?php endif; ?>

        <?php if ($docType === 'modul' || $docType === 'all'): ?>
            <div class="paper">
            <!-- ================= FORMAT 4: MODUL AJAR LENGKAP ================= -->
            <?php renderKopSurat($namaSekolah, $alamatSekolah, $teleponSekolah, $emailSekolah); ?>

            <div class="title-header-center">
                MODUL AJAR KURIKULUM MERDEKA<br>
                <?= strtoupper(htmlspecialchars($doc['mapel'])) ?>
            </div>

            <table class="meta-info-table">
                <tr>
                    <td class="label-col">Satuan Pendidikan</td>
                    <td class="colon-col">:</td>
                    <td class="value-col"><?= htmlspecialchars($namaSekolah) ?></td>
                </tr>
                <tr>
                    <td class="label-col">Penyusun / Guru</td>
                    <td class="colon-col">:</td>
                    <td class="value-col"><?= htmlspecialchars($namaGuru) ?> (NIP. <?= htmlspecialchars($nipGuru) ?>)</td>
                </tr>
                <tr>
                    <td class="label-col">Mata Pelajaran / Topik</td>
                    <td class="colon-col">:</td>
                    <td class="value-col"><strong><?= htmlspecialchars($doc['mapel']) ?> / <?= htmlspecialchars($doc['topik']) ?></strong></td>
                </tr>
                <tr>
                    <td class="label-col">Fase / Kelas / Semester</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">Fase <?= htmlspecialchars($doc['fase']) ?> / Kelas <?= htmlspecialchars($doc['kelas']) ?> / Semester <?= htmlspecialchars($semesterText) ?></td>
                </tr>
                <tr>
                    <td class="label-col">Model Pembelajaran</td>
                    <td class="colon-col">:</td>
                    <td><?= htmlspecialchars($modul['model_pembelajaran'] ?? 'Problem-Based Learning (PBL)') ?></td>
                </tr>
                <tr>
                    <td class="label-col">Profil Pelajar Pancasila</td>
                    <td class="colon-col">:</td>
                    <td><?= htmlspecialchars($pancasilaList) ?></td>
                </tr>
            </table>

            <div class="section-title">I. CAPAIAN & TUJUAN PEMBELAJARAN</div>
            <table class="table-custom">
                <tr>
                    <th style="width: 30%;">Capaian Pembelajaran (CP)</th>
                    <td><?= htmlspecialchars($doc['capaian_pembelajaran']) ?></td>
                </tr>
                <tr>
                    <th>Tujuan Pembelajaran (TP)</th>
                    <td><?= htmlspecialchars($doc['tujuan_pembelajaran']) ?></td>
                </tr>
                <tr>
                    <th>Alur Tujuan Pembelajaran (ATP)</th>
                    <td><?= htmlspecialchars($doc['alur_tujuan_pembelajaran']) ?></td>
                </tr>
            </table>

            <div class="section-title">II. KEGIATAN PEMBELAJARAN & ASESMEN</div>
            <table class="table-custom">
                <tr>
                    <th style="width: 30%;">Pertanyaan Pemantik</th>
                    <td><?= htmlspecialchars($modul['pertanyaan_pemantik'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Kegiatan Inti</th>
                    <td><?= htmlspecialchars($modul['kegiatan_inti'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Asesmen Formatif & Sumatif</th>
                    <td>Formatif: <?= htmlspecialchars($modul['asesmen_formatif'] ?? '-') ?><br>Sumatif: <?= htmlspecialchars($modul['asesmen_sumatif'] ?? '-') ?></td>
                </tr>
            </table>

            <div class="section-title">III. LAMPIRAN & LKPD</div>
            <div style="border: 1px solid #000; padding: 10px; background: #fafafa; font-size: 10pt; margin-bottom: 15px;">
                <strong>LKPD:</strong> <?= htmlspecialchars($modul['lkpd_content'] ?? '-') ?>
            </div>

            <?php renderTTD($namaSekolah, $namaKepsek, $nipKepsek, $namaGuru, $nipGuru, $alamatSekolah); ?>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>
