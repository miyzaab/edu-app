<?php
/**
 * EKSPOR NASKAH SOAL KE MICROSOFT WORD (.DOC)
 * Kompatibel Langsung Dibuka di MS Word, WPS Office & Google Docs
 * - Ukuran Kertas: A4 Portrait
 * - Font Latin: Cambria 12pt, Spasi 1.5
 * - Font Arab: Scheherazade New 15pt, Spasi 1.0
 * - Footer Otomatis: [Nama Mapel] [Tingkat Kelas] & Nomor Halaman
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_guru');

$pdo = getConnection();
require_once __DIR__ . '/init_soal.php';

$naskahId = (int) ($_GET['id'] ?? 0);
if ($naskahId <= 0) {
    die('ID Naskah tidak valid.');
}

// Ambil Data Naskah
$stmt = $pdo->prepare("SELECT * FROM soal_naskah WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $naskahId]);
$naskah = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$naskah) {
    die('Naskah soal tidak ditemukan.');
}

// Opsi Ukuran Font Kustom (dari Pengatur Font di Cetak/PDF)
$fontLatin = isset($_GET['font_latin']) ? max(8, min(24, (float)$_GET['font_latin'])) : 12;
$fontArab = isset($_GET['font_arab']) ? max(10, min(28, (float)$_GET['font_arab'])) : 15;

// Opsi Kerapatan / Spasi Kustom (dari Pengatur Spasi di Cetak/PDF)
// Presets: sgt_rapat, rapat (default), sedang, renggang
$spacingMode = strtolower(trim($_GET['spacing'] ?? 'rapat'));
switch ($spacingMode) {
    case 'sgt_rapat':
        $wordItemMargin = '6pt';
        $wordLineHeight = '1.2';
        $wordOpsiMargin = '3pt';
        $wordOpsiPadding = '1.5pt 4pt';
        $wordArabLineHeight = '1.45';
        $wordMatchingPadding = '1.5pt 3pt';
        break;
    case 'sedang':
        $wordItemMargin = '15pt';
        $wordLineHeight = '1.5';
        $wordOpsiMargin = '7pt';
        $wordOpsiPadding = '3.5pt 5pt';
        $wordArabLineHeight = '1.7';
        $wordMatchingPadding = '3.5pt 5pt';
        break;
    case 'renggang':
        $wordItemMargin = '20pt';
        $wordLineHeight = '1.65';
        $wordOpsiMargin = '10pt';
        $wordOpsiPadding = '5pt 6pt';
        $wordArabLineHeight = '1.85';
        $wordMatchingPadding = '5pt 6pt';
        break;
    case 'rapat':
    default:
        $spacingMode = 'rapat';
        $wordItemMargin = '9pt';
        $wordLineHeight = '1.35';
        $wordOpsiMargin = '4pt';
        $wordOpsiPadding = '2pt 4pt';
        $wordArabLineHeight = '1.55';
        $wordMatchingPadding = '2pt 4pt';
        break;
}

// Dynamic Footer Text (Contoh: "Aqidah VIII")
$cleanKelas = trim(preg_replace('/^Kelas\s+/i', '', $naskah['tingkat_kelas'] ?? ''));
$footerText = trim(($naskah['nama_mapel'] ?? '') . ' ' . $cleanKelas);

// Ambil Butir Soal
$stmtSoal = $pdo->prepare("SELECT * FROM soal_butir WHERE naskah_id = :nid ORDER BY FIELD(tipe_soal, 'pg', 'isian', 'essay'), nomor_urut ASC");
$stmtSoal->execute([':nid' => $naskahId]);
$allSoal = $stmtSoal->fetchAll(PDO::FETCH_ASSOC);

$soalPg = [];
$soalIsian = [];
$soalEssay = [];

foreach ($allSoal as $s) {
    if ($s['tipe_soal'] === 'pg') {
        $soalPg[] = $s;
    } elseif ($s['tipe_soal'] === 'isian') {
        $soalIsian[] = $s;
    } else {
        $soalEssay[] = $s;
    }
}

// Helper untuk Deteksi Teks Arab
function isTeksArabWord($text)
{
    if ($text === null || $text === '')
        return false;
    return (bool) preg_match('/[\p{Arabic}]/u', $text);
}

// Helper untuk Render Teks Soal & Deteksi Font Arab di Word
function renderTeksWord($text, $isParentRtl = false)
{
    if ($text === null || $text === '')
        return '';
    $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    if ($isParentRtl) {
        return nl2br($safe);
    }

    // Regex deteksi blok teks Arab
    $pattern = '/([\p{Arabic}][\p{Arabic}\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}\s\p{P}\d]*[\p{Arabic}]|[\p{Arabic}])/u';

    $wrapped = preg_replace_callback($pattern, function ($m) {
        $clean = trim($m[0]);
        if ($clean === '')
            return $m[0];
        return '<span class="teks-arab" dir="rtl" style="font-family:\'Scheherazade New\', \'Traditional Arabic\', serif; font-size:15pt; line-height:1.85;">' . $clean . '</span>';
    }, $safe);

    return nl2br($wrapped);
}

// KOP Settings
$settingsRaw = $pdo->query("SELECT setting_key, setting_value FROM rapor_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$kopSoalMode = $settingsRaw['kop_soal_mode'] ?? 'vector'; // default vector agar anti-pecah!

$kopNamaSekolah = !empty($settingsRaw['kop_nama_sekolah']) ? $settingsRaw['kop_nama_sekolah'] : 'SMP ISMI';
$kopYayasan = !empty($settingsRaw['kop_yayasan']) ? $settingsRaw['kop_yayasan'] : 'ISLAMIC SCHOOL OF MINHAJ AL-ILMI';
$kopSlogan = !empty($settingsRaw['kop_slogan']) ? $settingsRaw['kop_slogan'] : 'Berakhlak Mulia, Berilmu, Mahir IT';
$kopAlamat = !empty($settingsRaw['kop_alamat']) ? $settingsRaw['kop_alamat'] : 'Jl. Nakula No.1 Harjamukti Cimanggis Depok';
$kopTelp = !empty($settingsRaw['kop_telp']) ? $settingsRaw['kop_telp'] : 'Telp. (021) 4867 8793';
$kopEmail = !empty($settingsRaw['kop_email']) ? $settingsRaw['kop_email'] : 'smp.minhaj.al.ilmi@gmail.com';
$kopWebsite = !empty($settingsRaw['kop_website']) ? $settingsRaw['kop_website'] : 'sekolahismi.sch.id & ismi.sch.id';
$kopWa = !empty($settingsRaw['kop_wa']) ? $settingsRaw['kop_wa'] : 'Admin WA 0899-2244-111';

// Base64 KOP PNG for Word compatibility
$kopBase64 = '';
$kopPath = __DIR__ . '/../../assets/img/kop_soal_ismi.png';
if (file_exists($kopPath)) {
    $kopData = file_get_contents($kopPath);
    $kopBase64 = 'data:image/png;base64,' . base64_encode($kopData);
}

// Base64 logo for Word compatibility
$logoBase64 = '';
$logoPath = __DIR__ . '/../../assets/img/logo_1776435927.png';
if (file_exists($logoPath)) {
    $logoData = file_get_contents($logoPath);
    $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
}

// Base64 cube logo
$cubeBase64 = '';
$cubePath = __DIR__ . '/../../assets/img/logo_ismi_cube.png';
if (file_exists($cubePath)) {
    $cubeData = file_get_contents($cubePath);
    $cubeBase64 = 'data:image/png;base64,' . base64_encode($cubeData);
}

$cleanMapel = preg_replace('/[^a-zA-Z0-9]/', '_', $naskah['nama_mapel']);
$filename = "Soal_AAS_" . $cleanMapel . "_Kelas_" . $naskah['tingkat_kelas'] . "_" . date('Ymd');

header("Content-Type: application/vnd.ms-word; charset=utf-8");
header("Content-Disposition: attachment; filename=\"{$filename}.doc\"");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
?>
<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word'
    xmlns='http://www.w3.org/TR/REC-html40'>

<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($filename) ?></title>
    <!--[if gte mso 9]>
    <xml>
        <w:WordDocument>
            <w:View>Print</w:View>
            <w:Zoom>100</w:Zoom>
            <w:DoNotOptimizeForBrowser/>
        </w:WordDocument>
    </xml>
    <![endif]-->
    <style>
        @page Section1 {
            size: 21.0cm 29.7cm;
            /* Ukuran A4 */
            margin: 1.5cm 2.0cm 2.0cm 2.0cm;
            mso-page-orientation: portrait;
            mso-header-margin: 1.0cm;
            mso-footer-margin: 1.0cm;
            mso-footer: f1;
        }

        div.Section1 {
            page: Section1;
        }

        body {
            font-family: 'Cambria', Georgia, serif;
            font-size: <?= $fontLatin ?>pt;
            line-height: <?= $wordLineHeight ?>;
            color: #000000;
        }

        p,
        div,
        td {
            font-family: 'Cambria', Georgia, serif;
            font-size: <?= $fontLatin ?>pt;
            line-height: <?= $wordLineHeight ?>;
        }

        .teks-arab {
            font-family: 'Scheherazade New', 'Amiri', 'Traditional Arabic', serif !important;
            font-size: <?= $fontArab ?>pt !important;
            line-height: <?= $wordArabLineHeight ?> !important;
            direction: rtl;
        }

        table {
            border-collapse: collapse;
        }

        .kop-table {
            width: 100%;
            margin-bottom: 4px;
        }

        .kop-table td {
            vertical-align: middle;
        }

        .kop-school-name {
            font-size: 18pt;
            font-weight: bold;
            line-height: 1.1;
        }

        .kop-sub {
            font-size: 11pt;
            font-weight: bold;
        }

        .kop-slogan {
            font-size: 9pt;
            font-style: italic;
            color: #333333;
        }

        .kop-contact {
            text-align: right;
            font-size: 8.5pt;
            line-height: 1.25;
        }

        .kop-divider {
            border-bottom: 2.5pt solid #000000;
            margin-bottom: 14px;
        }

        .title-main {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.2;
            text-align: center;
        }

        .title-sub {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.2;
            text-align: center;
        }

        .nilai-box {
            width: 76px;
            height: 60px;
            border: 1.5pt solid #000000;
            text-align: center;
            padding-top: 4pt;
        }

        .nilai-box-title {
            font-size: 10pt;
            font-weight: bold;
        }

        .identitas-table {
            width: 100%;
            border: 1.5pt solid #000000;
            margin-bottom: 14px;
        }

        .identitas-table td {
            border: 1pt solid #000000;
            padding: 4pt 6pt;
            font-size: 11pt;
            line-height: 1.35;
        }

        .section-title {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 14px;
            margin-bottom: 3px;
            line-height: 1.35;
        }

        .section-desc {
            font-size: 10pt;
            font-style: italic;
            margin-bottom: 8px;
            line-height: 1.35;
        }

        .soal-table {
            width: 100%;
            margin-bottom: <?= $wordItemMargin ?>;
        }

        .soal-table td {
            vertical-align: top;
            padding: 1.5pt 0;
            font-size: <?= $fontLatin ?>pt;
            line-height: <?= $wordLineHeight ?>;
        }

        .opsi-table {
            width: 100%;
            margin-top: <?= $wordOpsiMargin ?>;
        }

        .opsi-table td {
            width: 50%;
            padding: <?= $wordOpsiPadding ?>;
            font-size: <?= $fontLatin ?>pt;
            line-height: <?= $wordLineHeight ?>;
        }

        .opsi-letter {
            font-weight: bold;
            display: inline-block;
            width: 24px;
            font-size: <?= $fontLatin ?>pt;
        }

        .soal-table.soal-arab {
            direction: rtl;
        }

        .soal-table.soal-arab .soal-no {
            text-align: right;
            padding-left: 8pt;
            padding-right: 0;
            font-size: <?= max(8, $fontArab - 2) ?>pt;
            line-height: <?= $wordArabLineHeight ?>;
        }

        .soal-text.soal-arab {
            direction: rtl;
            text-align: right;
            font-family: 'Scheherazade New', 'Amiri', 'Traditional Arabic', serif !important;
            font-size: <?= $fontArab ?>pt !important;
            line-height: <?= $wordArabLineHeight ?> !important;
        }

        .opsi-table.opsi-arab {
            direction: rtl;
            margin-top: <?= $wordOpsiMargin ?>;
            margin-right: 2pt;
            margin-left: 0;
        }

        .opsi-table.opsi-arab td {
            direction: rtl;
            text-align: right;
            font-family: 'Scheherazade New', 'Amiri', 'Traditional Arabic', serif !important;
            font-size: <?= $fontArab ?>pt !important;
            line-height: <?= $wordArabLineHeight ?> !important;
        }

        .opsi-table.opsi-arab .opsi-letter {
            direction: ltr;
            display: inline-block;
            margin-left: 6pt;
            margin-right: 0;
            width: 24px;
            font-family: 'Cambria', serif !important;
            font-size: <?= $fontLatin ?>pt !important;
        }

        /* WACANA / TEKS CERITA BERSAMA (WORD COMPATIBLE) */
        .wacana-box {
            margin: 10pt 0 8pt 0;
            padding: 8pt 10pt;
            background-color: #f8fafc;
            border: 1pt solid #cbd5e1;
            border-left: 3.5pt solid #0d9488;
            font-size: <?= max(8, $fontLatin - 0.5) ?>pt;
            page-break-inside: avoid;
        }

        .wacana-box.wacana-arab {
            direction: rtl;
            text-align: right;
            border-left: 1pt solid #cbd5e1;
            border-right: 3.5pt solid #0d9488;
            font-family: 'Scheherazade New', 'Amiri', 'Traditional Arabic', serif !important;
            font-size: <?= $fontArab ?>pt !important;
            line-height: 1.85 !important;
        }

        .wacana-title {
            font-size: <?= max(8, $fontLatin - 1) ?>pt;
            margin-bottom: 4pt;
            color: #1e293b;
            font-style: italic;
            font-weight: bold;
        }

        .wacana-arab .wacana-title {
            font-family: 'Scheherazade New', 'Amiri', 'Traditional Arabic', serif !important;
            font-size: <?= max(9, $fontArab - 1) ?>pt !important;
            font-style: normal;
        }

        .wacana-content {
            text-align: justify;
            line-height: 1.5;
        }

        .wacana-arab .wacana-content {
            direction: rtl;
            text-align: right;
            line-height: 1.85 !important;
        }

        /* BENAR / SALAH BRACKET (WORD) */
        .bs-bracket {
            font-weight: bold;
            font-family: 'Cambria', serif;
            letter-spacing: 2pt;
            font-size: <?= $fontLatin ?>pt;
        }

        /* MENJODOHKAN / SOAL SILANG (WORD) */
        .matching-word-table {
            width: 100%;
            border-collapse: collapse;
            margin: 6pt 0;
        }

        .matching-word-table td {
            vertical-align: middle;
            padding: <?= $wordMatchingPadding ?>;
            font-size: <?= max(8, $fontLatin - 0.5) ?>pt;
            line-height: <?= $wordLineHeight ?>;
        }

        .matching-word-table.soal-arab {
            direction: rtl;
        }

        .matching-word-table.soal-arab td {
            direction: rtl;
            text-align: right;
            font-family: 'Scheherazade New', 'Amiri', 'Traditional Arabic', serif !important;
            font-size: <?= $fontArab ?>pt !important;
            line-height: 1.85 !important;
        }

        .matching-word-table.soal-arab .matching-bullet {
            font-family: Arial, sans-serif !important;
            font-size: <?= max(10, $fontArab) ?>pt !important;
            text-align: center !important;
            direction: ltr;
        }

        .matching-word-table.soal-arab .matching-item-no,
        .matching-word-table.soal-arab .matching-item-letter {
            font-family: 'Cambria', serif !important;
            font-size: <?= $fontLatin ?>pt !important;
            font-weight: bold !important;
            direction: ltr;
            display: inline-block;
        }

        .matching-word-table.soal-arab .matching-item-no {
            margin-left: 6pt;
        }

        .matching-word-table.soal-arab .matching-item-letter {
            margin-left: 6pt;
        }

        .matching-bullet {
            font-size: 13pt;
            font-weight: bold;
            text-align: center;
            width: 25px;
        }

        /* NATIVE WORD FOOTER STYLES */
        p.MsoFooter,
        li.MsoFooter,
        div.MsoFooter {
            margin: 0;
            margin-bottom: .0001pt;
            font-size: 10pt;
            font-family: 'Cambria', serif;
        }
    </style>
</head>

<body>

    <div class="Section1">

        <!-- 1. KOP SURAT (PILIHAN: TEKS VEKTOR ANTI-PECAH ATAU GAMBAR PNG) -->
        <?php if ($kopSoalMode === 'image' && !empty($kopBase64)): ?>
            <p align="center" style="margin-top: 0; margin-bottom: 12pt;">
                <img src="<?= $kopBase64 ?>" style="width: 100%; max-width: 650px; height: auto;" alt="KOP Ujian SMP ISMI">
            </p>
        <?php else: ?>
            <table width="100%" style="width: 100%; border-collapse: collapse; margin-bottom: 4pt;">
                <tr>
                    <td width="75" style="width: 75px; vertical-align: middle; text-align: left; border: none;">
                        <?php if (!empty($cubeBase64)): ?>
                            <img src="<?= $cubeBase64 ?>" width="68" height="68" alt="Logo">
                        <?php elseif (!empty($logoBase64)): ?>
                            <img src="<?= $logoBase64 ?>" width="68" height="68" alt="Logo">
                        <?php endif; ?>
                    </td>
                    <td style="vertical-align: middle; padding-left: 8pt; border: none;">
                        <div
                            style="font-family: Arial, sans-serif; font-size: 17pt; font-weight: bold; line-height: 1.1; color: #000000;">
                            SMP ISMI</div>
                        <div
                            style="font-family: Arial, sans-serif; font-size: 11pt; font-weight: bold; line-height: 1.15; color: #000000;">
                            ISLAMIC SCHOOL OF</div>
                        <div
                            style="font-family: Arial, sans-serif; font-size: 11pt; font-weight: bold; line-height: 1.15; color: #0d9488;">
                            MINHAJ AL-ILMI</div>
                        <div
                            style="font-family: Arial, sans-serif; font-size: 8.5pt; font-style: italic; color: #333333; margin-top: 2pt;">
                            Berakhlak Mulia, Berilmu, Mahir IT</div>
                    </td>
                    <td
                        style="vertical-align: middle; text-align: right; font-family: Arial, sans-serif; font-size: 8.5pt; line-height: 1.3; color: #000000; border: none;">
                        <div><b>Jl. Nakula No.1 Harjamukti Cimanggis Depok</b></div>
                        <div><b>Telp. (021) 4867 8793</b></div>
                        <div>Email : <span style="color: #0284c7;">smp.minhaj.al.ilmi@gmail.com</span></div>
                        <div>Website : <span style="color: #0284c7;">sekolahismi.sch.id &amp; ismi.sch.id</span></div>
                        <div><b>Admin WA 0899-2244-111</b></div>
                    </td>
                </tr>
            </table>
            <div style="border-bottom: 2.5pt solid #000000; margin-top: 4pt; margin-bottom: 14pt;"></div>
        <?php endif; ?>

        <!-- 2. JUDUL DOKUMEN & KOTAK NILAI (3 KOLOM PRESISI) -->
        <table width="100%" style="width: 100%; margin-bottom: 14pt;">
            <tr>
                <td width="80" style="width: 80px;">&nbsp;</td>
                <td align="center" style="text-align: center;">
                    <div class="title-main"><?= htmlspecialchars($naskah['judul_ujian']) ?></div>
                    <div class="title-sub">SEMESTER <?= strtoupper($naskah['semester']) ?> TP.
                        <?= htmlspecialchars($naskah['tahun_ajaran']) ?></div>
                </td>
                <td width="80" align="right" style="width: 80px; text-align: right;">
                    <div class="nilai-box">
                        <div class="nilai-box-title">Nilai</div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- 3. TABEL IDENTITAS PESERTA (2 KOLOM) -->
        <table class="identitas-table">
            <tr>
                <td width="13%" style="width: 13%;">Nama</td>
                <td width="2%" align="center" style="width: 2%; text-align: center;">:</td>
                <td width="35%" style="width: 35%;"></td>
                <td width="17%" style="width: 17%;">Mata Pelajaran</td>
                <td width="2%" align="center" style="width: 2%; text-align: center;">:</td>
                <td width="31%" style="width: 31%; font-weight: bold;"><?= htmlspecialchars($naskah['nama_mapel']) ?>
                </td>
            </tr>
            <tr>
                <td>Kelas</td>
                <td align="center" style="text-align: center;">:</td>
                <td>Kelas <?= htmlspecialchars($cleanKelas) ?></td>
                <td>Tanggal</td>
                <td align="center" style="text-align: center;">:</td>
                <td><?= !empty($naskah['tanggal_ujian']) ? htmlspecialchars($naskah['tanggal_ujian']) : '' ?></td>
            </tr>
            <tr>
                <td>No. Absen</td>
                <td align="center" style="text-align: center;">:</td>
                <td></td>
                <td>Waktu</td>
                <td align="center" style="text-align: center;">:</td>
                <td><?= (int) $naskah['alokasi_waktu'] ?> Menit</td>
            </tr>
        </table>

        <!-- ================================================================ -->
        <!-- BAGIAN A: PILIHAN GANDA -->
        <!-- ================================================================ -->
        <?php if (!empty($soalPg)): ?>
            <div class="section-title">A. PILIHAN GANDA</div>
            <div class="section-desc">Pilihlah salah satu jawaban yang paling tepat dengan memberi tanda silang (X) pada
                huruf a, b, c, atau d!</div>

            <?php foreach ($soalPg as $idx => $s):
                $isArab = isTeksArabWord($s['pertanyaan']) || isTeksArabWord($s['pilihan_a'] ?? '');
                ?>
                <?php if (!empty(trim($s['wacana_teks'] ?? ''))):
                    $isWacanaArab = isTeksArabWord($s['wacana_teks']) || isTeksArabWord($s['wacana_judul'] ?? '');
                    ?>
                    <div class="wacana-box <?= $isWacanaArab ? 'wacana-arab' : '' ?>" <?= $isWacanaArab ? 'dir="rtl"' : '' ?>>
                        <?php if (!empty(trim($s['wacana_judul'] ?? ''))): ?>
                            <div class="wacana-title"><?= renderTeksWord($s['wacana_judul'], $isWacanaArab) ?></div>
                        <?php endif; ?>
                        <div class="wacana-content"><?= renderTeksWord($s['wacana_teks'], $isWacanaArab) ?></div>
                    </div>
                <?php endif; ?>
                <table class="soal-table <?= $isArab ? 'soal-arab' : '' ?>" <?= $isArab ? 'dir="rtl"' : '' ?>>
                    <tr>
                        <td class="soal-no" style="width: 26px; font-weight: bold;"><?= $idx + 1 ?>.</td>
                        <td class="soal-text <?= $isArab ? 'soal-arab' : '' ?>">
                            <div><?= renderTeksWord($s['pertanyaan'], $isArab) ?></div>
                            <?php
                            if (!empty($s['gambar'])):
                                $sImgPath = __DIR__ . '/../../uploads/soal/' . $s['gambar'];
                                if (file_exists($sImgPath)):
                                    $sImgData = file_get_contents($sImgPath);
                                    $sImgExt = pathinfo($sImgPath, PATHINFO_EXTENSION);
                                    $sImgBase64 = 'data:image/' . $sImgExt . ';base64,' . base64_encode($sImgData);
                                    ?>
                                    <div style="margin: 6pt 0;">
                                        <img src="<?= $sImgBase64 ?>" style="max-height: 150px; max-width: 260px;"
                                            alt="Soal <?= $idx + 1 ?>">
                                    </div>
                                <?php endif; endif; ?>

                            <!-- OPSI JAWABAN (2 KOLOM BERDAMPINGAN) -->
                            <table class="opsi-table <?= $isArab ? 'opsi-arab' : '' ?>" <?= $isArab ? 'dir="rtl"' : '' ?>>
                                <tr>
                                    <td>
                                        <span class="opsi-letter"><b>a.</b></span>
                                        <?= renderTeksWord($s['pilihan_a'] ?? '', $isArab) ?>
                                    </td>
                                    <td>
                                        <span class="opsi-letter"><b>c.</b></span>
                                        <?= renderTeksWord($s['pilihan_c'] ?? '', $isArab) ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <span class="opsi-letter"><b>b.</b></span>
                                        <?= renderTeksWord($s['pilihan_b'] ?? '', $isArab) ?>
                                    </td>
                                    <td>
                                        <span class="opsi-letter"><b>d.</b></span>
                                        <?= renderTeksWord($s['pilihan_d'] ?? '', $isArab) ?>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- ================================================================ -->
        <!-- BAGIAN B: ISIAN SINGKAT -->
        <!-- ================================================================ -->
        <?php if (!empty($soalIsian)): ?>
            <div class="section-title" style="margin-top: 16px;">B. ISIAN SINGKAT</div>
            <div class="section-desc">Isilah titik-titik di bawah ini dengan jawaban yang benar dan singkat!</div>

            <?php
            $totalIsian = count($soalIsian);
            $currIdx = 0;
            while ($currIdx < $totalIsian):
                $s = $soalIsian[$currIdx];
                $subTipe = $s['sub_tipe'] ?? 'isian_biasa';

                if ($subTipe === 'menjodohkan'):
                    // Kumpulkan SEMUA butir menjodohkan yang berurutan
                    $matchingGroup = [];
                    while ($currIdx < $totalIsian && ($soalIsian[$currIdx]['sub_tipe'] ?? '') === 'menjodohkan') {
                        $matchingGroup[] = [
                            'no' => $currIdx + 1,
                            'item' => $soalIsian[$currIdx]
                        ];
                        $currIdx++;
                    }

                    $firstItem = $matchingGroup[0]['item'];
                    $isArabGroup = isTeksArabWord($firstItem['pertanyaan']) || isTeksArabWord($firstItem['kunci_jawaban'] ?? '');

                    // Acak posisi kolom kanan dengan seed konsisten
                    $countGroup = count($matchingGroup);
                    $shuffledIdxs = range(0, max(0, $countGroup - 1));
                    srand((int) $firstItem['id'] * 79 + 17);
                    shuffle($shuffledIdxs);
                    ?>
                    <?php if (!empty(trim($firstItem['wacana_teks'] ?? ''))):
                        $isWacanaArab = isTeksArabWord($firstItem['wacana_teks']) || isTeksArabWord($firstItem['wacana_judul'] ?? '');
                        ?>
                        <div class="wacana-box <?= $isWacanaArab ? 'wacana-arab' : '' ?>" <?= $isWacanaArab ? 'dir="rtl"' : '' ?>>
                            <?php if (!empty(trim($firstItem['wacana_judul'] ?? ''))): ?>
                                <div class="wacana-title"><?= renderTeksWord($firstItem['wacana_judul'], $isWacanaArab) ?></div>
                            <?php endif; ?>
                            <div class="wacana-content"><?= renderTeksWord($firstItem['wacana_teks'], $isWacanaArab) ?></div>
                        </div>
                    <?php endif; ?>

                    <div style="border: 0.75pt solid #888888; padding: 6pt 10pt; margin: 6pt 0; background-color: #fafafa;">
                        <table class="matching-word-table <?= $isArabGroup ? 'soal-arab' : '' ?>" <?= $isArabGroup ? 'dir="rtl"' : '' ?> width="100%">
                            <?php foreach ($matchingGroup as $mGIdx => $mG):
                                $mNo = $mG['no'];
                                $mLeft = $mG['item']['pertanyaan'];
                                $rTargetIdx = $shuffledIdxs[$mGIdx] ?? $mGIdx;
                                $mRight = $matchingGroup[$rTargetIdx]['item']['kunci_jawaban'] ?? '';
                                $mLetter = chr(65 + $mGIdx);
                                ?>
                                <tr>
                                    <td style="width: 44%; vertical-align: middle;">
                                        <span class="matching-item-no"><b><?= $mNo ?>.</b></span> <?= renderTeksWord($mLeft, $isArabGroup) ?>
                                    </td>
                                    <td class="matching-bullet" style="width: 6%; text-align: center; vertical-align: middle;">•</td>
                                    <td class="matching-bullet" style="width: 6%; text-align: center; vertical-align: middle;">•</td>
                                    <td style="width: 44%; vertical-align: middle;">
                                        <span class="matching-item-letter"><b><?= $mLetter ?>.</b></span> <?= renderTeksWord($mRight, $isArabGroup) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>

                <?php else:
                    // Soal Isian Rumpang atau Benar/Salah (Single Item)
                    $isArab = isTeksArabWord($s['pertanyaan']);
                    $noSoal = $currIdx + 1;
                    $currIdx++;
                    ?>
                    <?php if (!empty(trim($s['wacana_teks'] ?? ''))):
                        $isWacanaArab = isTeksArabWord($s['wacana_teks']) || isTeksArabWord($s['wacana_judul'] ?? '');
                        ?>
                        <div class="wacana-box <?= $isWacanaArab ? 'wacana-arab' : '' ?>" <?= $isWacanaArab ? 'dir="rtl"' : '' ?>>
                            <?php if (!empty(trim($s['wacana_judul'] ?? ''))): ?>
                                <div class="wacana-title"><?= renderTeksWord($s['wacana_judul'], $isWacanaArab) ?></div>
                            <?php endif; ?>
                            <div class="wacana-content"><?= renderTeksWord($s['wacana_teks'], $isWacanaArab) ?></div>
                        </div>
                    <?php endif; ?>

                    <table class="soal-table <?= $isArab ? 'soal-arab' : '' ?>" <?= $isArab ? 'dir="rtl"' : '' ?>>
                        <tr>
                            <td class="soal-no" style="width: 26px; font-weight: bold;"><?= $noSoal ?>.</td>
                            <td class="soal-text <?= $isArab ? 'soal-arab' : '' ?>">
                                <?php if ($subTipe === 'benar_salah'): ?>
                                    <div><span class="bs-bracket">( &nbsp; &nbsp; &nbsp; &nbsp; )</span> &nbsp;
                                        <?= renderTeksWord($s['pertanyaan'], $isArab) ?></div>
                                <?php else: ?>
                                    <div><?= renderTeksWord($s['pertanyaan'], $isArab) ?></div>
                                <?php endif; ?>

                                <?php
                                if (!empty($s['gambar'])):
                                    $sImgPath = __DIR__ . '/../../uploads/soal/' . $s['gambar'];
                                    if (file_exists($sImgPath)):
                                        $sImgData = file_get_contents($sImgPath);
                                        $sImgExt = pathinfo($sImgPath, PATHINFO_EXTENSION);
                                        $sImgBase64 = 'data:image/' . $sImgExt . ';base64,' . base64_encode($sImgData);
                                        ?>
                                        <div style="margin: 6pt 0;">
                                            <img src="<?= $sImgBase64 ?>" style="max-height: 150px; max-width: 260px;"
                                                alt="Soal <?= $noSoal ?>">
                                        </div>
                                    <?php endif; endif; ?>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>
            <?php endwhile; ?>
        <?php endif; ?>

        <!-- ================================================================ -->
        <!-- BAGIAN C: URAIAN / ESSAY -->
        <!-- ================================================================ -->
        <?php if (!empty($soalEssay)): ?>
            <div class="section-title" style="margin-top: 16px;">C. URAIAN / ESSAY</div>
            <div class="section-desc">Jawablah pertanyaan-pertanyaan di bawah ini secara jelas, lengkap, dan sistematis!
            </div>

            <?php foreach ($soalEssay as $idx => $s):
                $isArab = isTeksArabWord($s['pertanyaan']);
                ?>
                <?php if (!empty(trim($s['wacana_teks'] ?? ''))):
                    $isWacanaArab = isTeksArabWord($s['wacana_teks']) || isTeksArabWord($s['wacana_judul'] ?? '');
                    ?>
                    <div class="wacana-box <?= $isWacanaArab ? 'wacana-arab' : '' ?>" <?= $isWacanaArab ? 'dir="rtl"' : '' ?>>
                        <?php if (!empty(trim($s['wacana_judul'] ?? ''))): ?>
                            <div class="wacana-title"><?= renderTeksWord($s['wacana_judul'], $isWacanaArab) ?></div>
                        <?php endif; ?>
                        <div class="wacana-content"><?= renderTeksWord($s['wacana_teks'], $isWacanaArab) ?></div>
                    </div>
                <?php endif; ?>
                <table class="soal-table <?= $isArab ? 'soal-arab' : '' ?>" <?= $isArab ? 'dir="rtl"' : '' ?>>
                    <tr>
                        <td class="soal-no" style="width: 26px; font-weight: bold;"><?= $idx + 1 ?>.</td>
                        <td class="soal-text <?= $isArab ? 'soal-arab' : '' ?>">
                            <div><?= renderTeksWord($s['pertanyaan'], $isArab) ?></div>
                            <?php
                            if (!empty($s['gambar'])):
                                $sImgPath = __DIR__ . '/../../uploads/soal/' . $s['gambar'];
                                if (file_exists($sImgPath)):
                                    $sImgData = file_get_contents($sImgPath);
                                    $sImgExt = pathinfo($sImgPath, PATHINFO_EXTENSION);
                                    $sImgBase64 = 'data:image/' . $sImgExt . ';base64,' . base64_encode($sImgData);
                                    ?>
                                    <div style="margin: 6pt 0;">
                                        <img src="<?= $sImgBase64 ?>" style="max-height: 150px; max-width: 260px;"
                                            alt="Soal <?= $idx + 1 ?>">
                                    </div>
                                <?php endif; endif; ?>
                        </td>
                    </tr>
                </table>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- NATIVE MICROSOFT WORD FOOTER ELEMENT -->
        <div style='mso-element:footer' id='f1'>
            <p class='MsoFooter' style='margin:0; font-family:"Cambria",serif; font-size:10pt;'>
            <table width='100%' style='width:100%; border:none; border-top:0.75pt solid #888888; padding-top:4pt;'>
                <tr>
                    <td
                        style='border:none; text-align:left; font-style:italic; font-family:"Cambria",serif; font-size:10pt;'>
                        <?= htmlspecialchars($footerText) ?>
                    </td>
                    <td style='border:none; text-align:right; font-family:"Cambria",serif; font-size:10pt;'>
                        Halaman <span style='mso-field-code:" PAGE "'></span> dari <span
                            style='mso-field-code:" NUMPAGES "'></span>
                    </td>
                </tr>
            </table>
            </p>
        </div>

    </div>

</body>

</html>