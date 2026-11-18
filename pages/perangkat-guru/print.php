<?php
/**
 * CETAK NASKAH SOAL UJIAN (A4 PRINT / PDF READY)
 * Presisi Format Sesuai Standar SMP ISMI
 * - Kertas: A4 Portrait
 * - Font Latin: Cambria 12pt, Line Height 1.5
 * - Font Arab: Scheherazade New 15pt, Line Height 1.0
 * - Nomor Halaman & Dynamic Footer: [Nama Mapel] [Tingkat Kelas] (e.g. Aqidah VIII)
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
function isTeksArab($text)
{
    if ($text === null || $text === '')
        return false;
    return (bool) preg_match('/[\p{Arabic}]/u', $text);
}

// Helper untuk Render Teks Soal & Deteksi Font Arab
function renderTeksSoal($text, $isParentRtl = false)
{
    if ($text === null || $text === '')
        return '';
    $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // Jika parent sudah RTL, teks Arab mengalir alami dari kanan ke kiri
    if ($isParentRtl) {
        return nl2br($safe);
    }

    // Regex deteksi blok teks Arab (huruf, harakat, tanda baca Arab)
    $pattern = '/([\p{Arabic}][\p{Arabic}\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}\s\p{P}\d]*[\p{Arabic}]|[\p{Arabic}])/u';

    $wrapped = preg_replace_callback($pattern, function ($m) {
        $clean = trim($m[0]);
        if ($clean === '')
            return $m[0];
        return '<span class="teks-arab" dir="rtl">' . $clean . '</span>';
    }, $safe);

    return nl2br($wrapped);
}

// Pengaturan KOP dari rapor_settings (fallback jika belum upload KOP PNG)
$settingsRaw = $pdo->query("SELECT setting_key, setting_value FROM rapor_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$kopSoalMode = $settingsRaw['kop_soal_mode'] ?? 'vector'; // default vector agar 100% anti-pecah!

$kopNamaSekolah = !empty($settingsRaw['kop_nama_sekolah']) ? $settingsRaw['kop_nama_sekolah'] : 'SMP ISMI';
$kopYayasan = !empty($settingsRaw['kop_yayasan']) ? $settingsRaw['kop_yayasan'] : 'ISLAMIC SCHOOL OF MINHAJ AL-ILMI';
$kopSlogan = !empty($settingsRaw['kop_slogan']) ? $settingsRaw['kop_slogan'] : 'Berakhlak Mulia, Berilmu, Mahir IT';
$kopAlamat = !empty($settingsRaw['kop_alamat']) ? $settingsRaw['kop_alamat'] : 'Jl. Nakula No.1 Harjamukti Cimanggis Depok';
$kopTelp = !empty($settingsRaw['kop_telp']) ? $settingsRaw['kop_telp'] : 'Telp. (021) 4867 8793';
$kopEmail = !empty($settingsRaw['kop_email']) ? $settingsRaw['kop_email'] : 'smp.minhaj.al.ilmi@gmail.com';
$kopWebsite = !empty($settingsRaw['kop_website']) ? $settingsRaw['kop_website'] : 'sekolahismi.sch.id & ismi.sch.id';
$kopWa = !empty($settingsRaw['kop_wa']) ? $settingsRaw['kop_wa'] : 'Admin WA 0899-2244-111';

$uploadDirSoal = __DIR__ . '/../../uploads/soal';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        Naskah_Soal_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $naskah['nama_mapel']) ?>_Kelas_<?= $naskah['tingkat_kelas'] ?>
    </title>

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
                content: "<?= addslashes($footerText) ?>";
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

        :root {
            --font-latin-size: 12pt;
            --font-arab-size: 15pt;
        }

        body {
            font-family: 'Cambria', Georgia, serif;
            font-size: var(--font-latin-size, 12pt);
            line-height: 1.5;
            color: #000;
            background-color: #f1f5f9;
            margin: 0;
            padding: 0;
        }

        /* FONT ARAB: Scheherazade New */
        .teks-arab {
            font-family: 'Scheherazade New', 'Amiri', 'Traditional Arabic', serif !important;
            font-size: var(--font-arab-size, 15pt) !important;
            line-height: 1.85 !important;
            direction: rtl;
            unicode-bidi: isolate;
            display: inline;
        }

        .no-print {
            display: block;
        }

        /* PRINT CONTROL BAR */
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

        /* PAPER A4 CONTAINER */
        .page-sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: #ffffff;
            padding: 15mm 16mm 22mm 16mm;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
            position: relative;
            box-sizing: border-box;
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

            .screen-footer {
                display: none !important;
            }

            .print-footer-bar {
                display: flex !important;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                justify-content: space-between;
                align-items: center;
                font-family: 'Cambria', Georgia, serif;
                font-size: 10pt;
                border-top: 0.75pt solid #888;
                padding-top: 4px;
                background: transparent;
            }

            .wacana-box {
                background-color: #fcfcfc !important;
                border: 1px solid #777 !important;
                border-left: 3.5pt solid #111 !important;
            }
            .wacana-box.wacana-arab {
                border-left: 1px solid #777 !important;
                border-right: 3.5pt solid #111 !important;
            }
        }

        /* KOP SURAT */
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3px;
        }

        .kop-table td {
            vertical-align: middle;
        }

        .kop-logo-box {
            width: 65px;
            text-align: left;
        }

        .kop-logo-box img {
            width: 58px;
            height: 58px;
            object-fit: contain;
        }

        .kop-title-box {
            padding-left: 12px;
        }

        .kop-school-name {
            font-size: 17pt;
            font-weight: bold;
            line-height: 1.05;
            color: #000;
            letter-spacing: -0.3px;
        }

        .kop-sub-yayasan {
            font-size: 11pt;
            font-weight: bold;
            line-height: 1.15;
            color: #000;
        }

        .kop-sub-yayasan-green {
            color: #0d9488;
            font-weight: bold;
        }

        .kop-slogan {
            font-size: 8.5pt;
            font-style: italic;
            color: #333;
            margin-top: 2px;
        }

        .kop-contact-box {
            text-align: right;
            font-size: 8pt;
            line-height: 1.25;
            color: #222;
        }

        .kop-divider {
            border-bottom: 2.2px solid #000000;
            margin-bottom: 12px;
        }

        /* JUDUL & KOTAK NILAI */
        .title-main {
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            line-height: 1.25;
        }

        .title-sub {
            font-size: 11.5pt;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-top: 2px;
            line-height: 1.25;
        }

        .nilai-box {
            display: inline-block;
            width: 76px;
            height: 64px;
            border: 1.5px solid #000;
            text-align: center;
            padding-top: 4px;
            box-sizing: border-box;
            background: #ffffff;
        }

        .nilai-box-title {
            font-size: 10pt;
            font-weight: bold;
        }

        /* TABEL IDENTITAS PESERTA (2 KOLOM) */
        .identitas-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11pt;
            line-height: 1.35;
            margin-bottom: 14px;
            border: 1.5px solid #000000;
        }

        .identitas-table td {
            border: 1px solid #000000;
            padding: 4px 8px;
            vertical-align: middle;
        }

        /* SECTIONS & SOAL */
        .section-header {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 18px;
            margin-bottom: 6px;
            border-bottom: 1px dashed #666;
            padding-bottom: 3px;
            line-height: 1.35;
        }

        .section-desc {
            font-size: 10pt;
            font-style: italic;
            margin-bottom: 14px;
            line-height: 1.4;
        }

        .soal-item {
            margin-bottom: 22px;
            page-break-inside: avoid;
        }

        .soal-table {
            width: 100%;
            border-collapse: collapse;
        }

        .soal-table td {
            vertical-align: top;
            padding: 2px 0;
        }

        .soal-no {
            width: 28px;
            font-weight: bold;
            text-align: left;
            font-size: var(--font-latin-size, 12pt);
            line-height: 1.6;
            padding-right: 6px;
        }

        .soal-text {
            font-size: var(--font-latin-size, 12pt);
            line-height: 1.65;
            text-align: justify;
        }

        .soal-img-box {
            margin: 8px 0;
        }

        .soal-img-box img {
            max-height: 160px;
            max-width: 280px;
            object-fit: contain;
            border: 1px solid #ccc;
        }

        /* PILIHAN A - D */
        .opsi-table {
            width: 100%;
            margin-top: 12px;
            margin-left: 2px;
            border-collapse: collapse;
        }

        .opsi-table td {
            vertical-align: top;
            padding: 6px 10px;
            width: 50%;
            font-size: var(--font-latin-size, 12pt);
            line-height: 1.65;
        }

        .opsi-letter {
            font-weight: bold;
            display: inline-block;
            width: 26px;
            margin-right: 4px;
            font-size: var(--font-latin-size, 12pt);
        }

        /* TATA LETAK BUTIR SOAL ARAB (KANAN KE KIRI / RTL) */
        .soal-table.soal-arab {
            direction: rtl !important;
        }

        .soal-table.soal-arab .soal-no {
            text-align: right !important;
            padding-left: 8px !important;
            padding-right: 0 !important;
            font-size: calc(var(--font-arab-size, 15pt) - 2pt) !important;
            line-height: 1.85 !important;
        }

        .soal-text.soal-arab {
            direction: rtl !important;
            text-align: right !important;
            text-align-last: right !important;
            font-family: 'Scheherazade New', 'Amiri', 'Traditional Arabic', serif !important;
            font-size: var(--font-arab-size, 15pt) !important;
            line-height: 1.85 !important;
        }

        .opsi-table.opsi-arab {
            direction: rtl !important;
            margin-top: 12px;
            margin-right: 2px;
            margin-left: 0;
        }

        .opsi-table.opsi-arab td {
            direction: rtl !important;
            text-align: right !important;
            font-family: 'Scheherazade New', 'Amiri', 'Traditional Arabic', serif !important;
            font-size: var(--font-arab-size, 15pt) !important;
            line-height: 1.85 !important;
        }

        .opsi-table.opsi-arab .opsi-letter {
            direction: ltr !important;
            display: inline-block;
            margin-left: 8px;
            margin-right: 0;
            width: 26px;
            font-family: 'Cambria', Georgia, serif !important;
            font-size: var(--font-latin-size, 12pt) !important;
        }

        /* OVERRIDE JIKA USER MEMILIH MODE LTR */
        body.force-arabic-ltr .soal-table.soal-arab {
            direction: ltr !important;
        }
        body.force-arabic-ltr .soal-table.soal-arab .soal-no {
            text-align: left !important;
            padding-right: 6px !important;
            padding-left: 0 !important;
        }
        body.force-arabic-ltr .soal-text.soal-arab {
            direction: ltr !important;
            text-align: justify !important;
            text-align-last: left !important;
        }
        body.force-arabic-ltr .opsi-table.opsi-arab {
            direction: ltr !important;
            margin-left: 2px;
            margin-right: 0;
        }
        body.force-arabic-ltr .opsi-table.opsi-arab td {
            direction: ltr !important;
            text-align: left !important;
        }
        body.force-arabic-ltr .opsi-table.opsi-arab .opsi-letter {
            margin-right: 4px;
            margin-left: 0;
        }

        /* BLOK WACANA / TEKS CERITA BERSAMA (KONSEP A) */
        .wacana-box {
            margin: 12px 0 10px 0;
            padding: 10px 14px;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-left: 3.5px solid #0d9488;
            border-radius: 4px;
            font-size: calc(var(--font-latin-size, 12pt) - 0.5pt);
            line-height: 1.6;
            page-break-inside: avoid;
        }

        .wacana-box.wacana-arab {
            direction: rtl !important;
            text-align: right !important;
            text-align-last: right !important;
            border-left: 1px solid #cbd5e1;
            border-right: 3.5px solid #0d9488;
            font-family: 'Scheherazade New', 'Amiri', 'Traditional Arabic', serif !important;
            font-size: var(--font-arab-size, 15pt) !important;
            line-height: 1.85 !important;
        }

        .wacana-title {
            font-size: calc(var(--font-latin-size, 12pt) - 1pt);
            margin-bottom: 6px;
            color: #1e293b;
            font-style: italic;
        }

        .wacana-arab .wacana-title {
            font-family: 'Scheherazade New', 'Amiri', 'Traditional Arabic', serif !important;
            font-size: calc(var(--font-arab-size, 15pt) - 1pt) !important;
            font-style: normal;
            color: #0f172a;
        }

        .wacana-content {
            text-align: justify;
            line-height: 1.65;
        }

        .wacana-arab .wacana-content {
            direction: rtl !important;
            text-align: right !important;
            line-height: 1.85 !important;
        }

        body.force-arabic-ltr .wacana-box.wacana-arab {
            direction: ltr !important;
            text-align: left !important;
            border-right: 1px solid #cbd5e1;
            border-left: 3.5px solid #0d9488;
        }
        body.force-arabic-ltr .wacana-arab .wacana-content {
            direction: ltr !important;
            text-align: justify !important;
        }

        /* BENAR / SALAH BRACKET */
        .bs-bracket {
            font-weight: bold;
            font-family: 'Cambria', Georgia, serif;
            letter-spacing: 2px;
            display: inline-block;
            min-width: 42px;
            font-size: var(--font-latin-size, 12pt);
        }

        /* TABEL MENJODOHKAN (SOAL SILANG) */
        .matching-print-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 6px;
        }
        .matching-print-table td {
            vertical-align: middle;
            padding: 5px 6px;
            font-size: calc(var(--font-latin-size, 12pt) - 0.5pt);
            line-height: 1.5;
        }
        .matching-bullet {
            font-size: 14pt;
            font-weight: bold;
            color: #333;
            text-align: center;
            width: 30px;
        }
        .matching-table-box {
            background: #fafafa;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px 12px;
            margin-top: 8px;
        }
        @media print {
            .matching-table-box {
                background: none !important;
                border: 0.75pt solid #555 !important;
            }
        }

        /* GAYA ARAB PADA TABEL MENJODOHKAN (SERAGAM DENGAN WACANA & SOAL LAIN) */
        .matching-print-table.soal-arab {
            direction: rtl !important;
        }
        .matching-print-table.soal-arab td {
            direction: rtl !important;
            text-align: right !important;
            font-family: 'Scheherazade New', 'Amiri', 'Traditional Arabic', serif !important;
            font-size: var(--font-arab-size, 15pt) !important;
            line-height: 1.85 !important;
        }
        .matching-print-table.soal-arab .matching-bullet {
            font-family: Arial, sans-serif !important;
            font-size: calc(var(--font-arab-size, 15pt) + 1pt) !important;
            line-height: 1 !important;
            text-align: center !important;
            color: #222 !important;
            direction: ltr !important;
        }
        .matching-print-table.soal-arab .matching-item-no,
        .matching-print-table.soal-arab .matching-item-letter {
            font-family: 'Cambria', Georgia, serif !important;
            font-size: var(--font-latin-size, 12pt) !important;
            font-weight: bold !important;
            direction: ltr !important;
            display: inline-block;
        }
        .matching-print-table.soal-arab .matching-item-no {
            margin-left: 6px;
        }
        .matching-print-table.soal-arab .matching-item-letter {
            margin-left: 6px;
        }

        body.force-arabic-ltr .matching-print-table.soal-arab {
            direction: ltr !important;
        }
        body.force-arabic-ltr .matching-print-table.soal-arab td {
            direction: ltr !important;
            text-align: left !important;
        }
        body.force-arabic-ltr .matching-print-table.soal-arab .matching-item-no,
        body.force-arabic-ltr .matching-print-table.soal-arab .matching-item-letter {
            margin-right: 6px;
            margin-left: 0;
        }

        /* FOOTER ON SCREEN PREVIEW */
        .screen-footer {
            margin-top: 30px;
            padding-top: 8px;
            border-top: 1px solid #ccc;
            font-size: 10pt;
            color: #555;
            display: flex;
            justify-content: space-between;
            font-style: italic;
        }

        /* PRINT FOOTER FALLBACK */
        .print-footer-bar {
            display: none;
        }
    </style>
</head>

<body>

    <!-- PRINT CONTROL BAR (SCREEN ONLY) -->
    <div class="print-control-bar no-print">
        <div class="d-flex align-items-center flex-wrap gap-2">
            <a href="edit.php?id=<?= $naskahId ?>" class="btn btn-outline-light btn-sm rounded-pill px-3 fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Editor
            </a>
            <span class="badge bg-primary px-3 py-1.5 rounded-pill font-monospace d-none d-sm-inline">
                <?= htmlspecialchars($naskah['nama_mapel']) ?> • Kelas <?= htmlspecialchars($naskah['tingkat_kelas']) ?>
            </span>
        </div>

        <!-- PENGATUR UKURAN FONT (LATIN & ARAB) -->
        <div class="d-flex align-items-center flex-wrap gap-2">
            <!-- PENGATUR FONT LATIN -->
            <div class="d-flex align-items-center bg-dark bg-opacity-75 border border-secondary rounded-pill px-2.5 py-1 gap-1.5 shadow-sm" title="Sesuaikan Ukuran Font Latin / Umum">
                <span class="text-white-50 small fw-semibold" style="font-size: 0.76rem;">Font Latin:</span>
                <button type="button" class="btn btn-sm btn-outline-light border-0 p-0 px-1 text-light" onclick="adjustFontSize('latin', -0.5)" title="Perkecil Font Latin (-0.5pt)">
                    <i class="bi bi-dash-circle fs-6"></i>
                </button>
                <span id="labelFontLatin" class="fw-bold text-warning font-monospace px-1" style="font-size: 0.85rem; min-width: 44px; text-align: center;">12pt</span>
                <button type="button" class="btn btn-sm btn-outline-light border-0 p-0 px-1 text-light" onclick="adjustFontSize('latin', 0.5)" title="Perbesar Font Latin (+0.5pt)">
                    <i class="bi bi-plus-circle fs-6"></i>
                </button>
            </div>

            <!-- PENGATUR FONT ARAB -->
            <div class="d-flex align-items-center bg-dark bg-opacity-75 border border-secondary rounded-pill px-2.5 py-1 gap-1.5 shadow-sm" title="Sesuaikan Ukuran Font Arab">
                <span class="text-white-50 small fw-semibold" style="font-size: 0.76rem;">Font Arab:</span>
                <button type="button" class="btn btn-sm btn-outline-light border-0 p-0 px-1 text-light" onclick="adjustFontSize('arab', -1)" title="Perkecil Font Arab (-1pt)">
                    <i class="bi bi-dash-circle fs-6"></i>
                </button>
                <span id="labelFontArab" class="fw-bold text-warning font-monospace px-1" style="font-size: 0.85rem; min-width: 44px; text-align: center;">15pt</span>
                <button type="button" class="btn btn-sm btn-outline-light border-0 p-0 px-1 text-light" onclick="adjustFontSize('arab', 1)" title="Perbesar Font Arab (+1pt)">
                    <i class="bi bi-plus-circle fs-6"></i>
                </button>
            </div>

            <!-- RESET FONT -->
            <button type="button" onclick="resetFontSizes()" class="btn btn-outline-secondary btn-sm rounded-pill px-2.5 py-1 text-light" title="Reset Ukuran Font ke Default (12pt & 15pt)">
                <i class="bi bi-arrow-counterclockwise"></i> <span class="d-none d-md-inline" style="font-size: 0.76rem;">Reset</span>
            </button>
        </div>

        <div class="d-flex align-items-center flex-wrap gap-2">
            <button type="button" onclick="toggleArabicDirection()" class="btn btn-outline-warning btn-sm rounded-pill px-3 fw-semibold" id="btnToggleDir" title="Ganti Arah Teks Arab (Kanan ke Kiri / Kiri ke Kanan)">
                <i class="bi bi-arrow-left-right me-1"></i> Arah: RTL
            </button>
            <a href="kop.php" class="btn btn-outline-light btn-sm rounded-pill px-2.5 fw-semibold d-none d-md-inline" title="Kelola KOP Lembar Ujian">
                <i class="bi bi-image me-1"></i> KOP
            </a>
            <a href="export_word.php?id=<?= $naskahId ?>" id="btnDownloadWord" class="btn btn-outline-info btn-sm rounded-pill px-3 fw-bold" title="Unduh Lembar Soal ke Format Microsoft Word">
                <i class="bi bi-file-earmark-word-fill me-1"></i> Word (.doc)
            </a>
            <button onclick="window.print()" class="btn btn-danger btn-sm rounded-pill px-3.5 py-1.5 fw-bold shadow-sm"
                style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border:none;">
                <i class="bi bi-printer-fill me-1"></i> Cetak / Simpan PDF
            </button>
        </div>
    </div>

    <!-- LEMBAR DOKUMEN A4 -->
    <div class="page-sheet">
        <!-- 1. KOP SURAT (PILIHAN: TEKS VEKTOR ANTI-PECAH ATAU GAMBAR PNG) -->
        <?php
        $kopImgPath = __DIR__ . '/../../assets/img/kop_soal_ismi.png';
        $cubeLogo = __DIR__ . '/../../assets/img/logo_ismi_cube.png';

        if ($kopSoalMode === 'image' && file_exists($kopImgPath)):
            ?>
            <div style="width: 100%; margin-bottom: 12px; text-align: center;">
                <img src="<?= BASE_URL ?>/assets/img/kop_soal_ismi.png?v=<?= filemtime($kopImgPath) ?>"
                    alt="KOP Ujian SMP ISMI" style="max-width: 100%; height: auto; display: block; margin: 0 auto;">
            </div>
        <?php else: ?>
            <table class="kop-table" style="width: 100%; border-collapse: collapse; margin-bottom: 2px;">
                <tr>
                    <td class="kop-logo-box" style="width: 78px; vertical-align: middle; text-align: left;">
                        <?php if (file_exists($cubeLogo)): ?>
                            <img src="<?= BASE_URL ?>/assets/img/logo_ismi_cube.png?v=<?= filemtime($cubeLogo) ?>"
                                alt="Logo SMP ISMI" style="width: 70px; height: 70px; object-fit: contain; display: block;">
                        <?php elseif (file_exists(__DIR__ . '/../../assets/img/logo_1776435927.png')): ?>
                            <img src="<?= BASE_URL ?>/assets/img/logo_1776435927.png" alt="Logo SMP ISMI"
                                style="width: 70px; height: 70px; object-fit: contain; display: block;">
                        <?php else: ?>
                            <svg width="68" height="68" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M50 5L90 28V72L50 95L10 72V28L50 5Z" stroke="#0d9488" stroke-width="5.5" fill="none" />
                                <path d="M50 18L78 34V66L50 82L22 66V34L50 18Z" stroke="#0d9488" stroke-width="4.5"
                                    fill="none" />
                                <path d="M35 38H65M35 50H65M35 62H65M50 30V70" stroke="#0d9488" stroke-width="4"
                                    stroke-linecap="round" />
                            </svg>
                        <?php endif; ?>
                    </td>
                    <td class="kop-title-box" style="vertical-align: middle; padding-left: 10px;">
                        <div
                            style="font-family: Arial, Helvetica, sans-serif; font-size: 18pt; font-weight: 800; line-height: 1.1; color: #000; letter-spacing: -0.3px;">
                            SMP ISMI</div>
                        <div
                            style="font-family: Arial, Helvetica, sans-serif; font-size: 11.5pt; font-weight: 800; line-height: 1.15; color: #000;">
                            ISLAMIC SCHOOL OF</div>
                        <div
                            style="font-family: Arial, Helvetica, sans-serif; font-size: 11.5pt; font-weight: 800; line-height: 1.15; color: #0d9488;">
                            MINHAJ AL-ILMI</div>
                        <div
                            style="font-family: 'Segoe Print', 'Caveat', cursive, sans-serif; font-size: 8.5pt; font-style: italic; color: #222; margin-top: 2px;">
                            Berakhlak Mulia, Berilmu, Mahir IT</div>
                    </td>
                    <td class="kop-contact-box"
                        style="vertical-align: middle; text-align: right; font-family: Arial, Helvetica, sans-serif; font-size: 8.5pt; line-height: 1.35; color: #111;">
                        <div style="font-weight: 700;">Jl. Nakula No.1 Harjamukti Cimanggis Depok</div>
                        <div style="font-weight: 700;">Telp. (021) 4867 8793</div>
                        <div>Email : <span style="color: #0284c7;"><?= htmlspecialchars($kopEmail) ?></span></div>
                        <div>Website : <span style="color: #0284c7;"><?= htmlspecialchars($kopWebsite) ?></span></div>
                        <div style="font-weight: 700;">Admin WA 0899-2244-111</div>
                    </td>
                </tr>
            </table>
            <div style="border-bottom: 2.5px solid #000000; margin-top: 6px; margin-bottom: 14px;"></div>
        <?php endif; ?>

        <!-- 2. JUDUL DOKUMEN & KOTAK NILAI (3 KOLOM PRESISI) -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 14px;">
            <tr>
                <td style="width: 80px; vertical-align: top;">&nbsp;</td>
                <td style="text-align: center; vertical-align: middle;">
                    <div class="title-main"><?= htmlspecialchars($naskah['judul_ujian']) ?></div>
                    <div class="title-sub">SEMESTER <?= strtoupper($naskah['semester']) ?> TP.
                        <?= htmlspecialchars($naskah['tahun_ajaran']) ?></div>
                </td>
                <td style="width: 80px; vertical-align: top; text-align: right;">
                    <div class="nilai-box">
                        <div class="nilai-box-title">Nilai</div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- 3. TABEL IDENTITAS PESERTA (2 KOLOM PERSIS REFERENSI) -->
        <table class="identitas-table">
            <tr>
                <td style="width: 13%;">Nama</td>
                <td style="width: 2%; text-align: center;">:</td>
                <td style="width: 35%;"></td>
                <td style="width: 17%;">Mata Pelajaran</td>
                <td style="width: 2%; text-align: center;">:</td>
                <td style="width: 31%; font-weight: bold;"><?= htmlspecialchars($naskah['nama_mapel']) ?></td>
            </tr>
            <tr>
                <td>Kelas</td>
                <td style="text-align: center;">:</td>
                <td>Kelas <?= htmlspecialchars($cleanKelas) ?></td>
                <td>Tanggal</td>
                <td style="text-align: center;">:</td>
                <td><?= !empty($naskah['tanggal_ujian']) ? htmlspecialchars($naskah['tanggal_ujian']) : '' ?></td>
            </tr>
            <tr>
                <td>No. Absen</td>
                <td style="text-align: center;">:</td>
                <td></td>
                <td>Waktu</td>
                <td style="text-align: center;">:</td>
                <td><?= (int) $naskah['alokasi_waktu'] ?> Menit</td>
            </tr>
        </table>

        <!-- ================================================================ -->
        <!-- BAGIAN A: PILIHAN GANDA -->
        <!-- ================================================================ -->
        <?php if (!empty($soalPg)): ?>
            <div class="section-header">A. PILIHAN GANDA</div>
            <div class="section-desc">Pilihlah salah satu jawaban yang paling tepat dengan memberi tanda silang (X) pada
                huruf a, b, c, atau d!</div>

            <?php foreach ($soalPg as $idx => $s): 
                $isArab = isTeksArab($s['pertanyaan']) || isTeksArab($s['pilihan_a'] ?? '');
            ?>
                <div class="soal-item">
                    <?php if (!empty(trim($s['wacana_teks'] ?? ''))): 
                        $isWacanaArab = isTeksArab($s['wacana_teks']) || isTeksArab($s['wacana_judul'] ?? '');
                    ?>
                        <div class="wacana-box <?= $isWacanaArab ? 'wacana-arab' : '' ?>" <?= $isWacanaArab ? 'dir="rtl"' : '' ?>>
                            <?php if (!empty(trim($s['wacana_judul'] ?? ''))): ?>
                                <div class="wacana-title"><strong><?= renderTeksSoal($s['wacana_judul'], $isWacanaArab) ?></strong></div>
                            <?php endif; ?>
                            <div class="wacana-content"><?= renderTeksSoal($s['wacana_teks'], $isWacanaArab) ?></div>
                        </div>
                    <?php endif; ?>
                    <table class="soal-table <?= $isArab ? 'soal-arab' : '' ?>" <?= $isArab ? 'dir="rtl"' : '' ?>>
                        <tr>
                            <td class="soal-no"><?= $idx + 1 ?>.</td>
                            <td class="soal-text <?= $isArab ? 'soal-arab' : '' ?>">
                                <?= renderTeksSoal($s['pertanyaan'], $isArab) ?>

                                <?php if (!empty($s['gambar']) && file_exists($uploadDirSoal . '/' . $s['gambar'])): ?>
                                    <div class="soal-img-box">
                                        <img src="<?= BASE_URL ?>/uploads/soal/<?= htmlspecialchars($s['gambar']) ?>"
                                            alt="Soal <?= $idx + 1 ?>">
                                    </div>
                                <?php endif; ?>

                                <!-- OPSI A, B, C, D (2 KOLOM BERDAMPINGAN) -->
                                <table class="opsi-table <?= $isArab ? 'opsi-arab' : '' ?>" <?= $isArab ? 'dir="rtl"' : '' ?>>
                                    <tr>
                                        <td>
                                            <span class="opsi-letter">a.</span> <?= renderTeksSoal($s['pilihan_a'] ?? '', $isArab) ?>
                                        </td>
                                        <td>
                                            <span class="opsi-letter">c.</span> <?= renderTeksSoal($s['pilihan_c'] ?? '', $isArab) ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <span class="opsi-letter">b.</span> <?= renderTeksSoal($s['pilihan_b'] ?? '', $isArab) ?>
                                        </td>
                                        <td>
                                            <span class="opsi-letter">d.</span> <?= renderTeksSoal($s['pilihan_d'] ?? '', $isArab) ?>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- ================================================================ -->
        <!-- BAGIAN B: ISIAN SINGKAT -->
        <!-- ================================================================ -->
        <?php if (!empty($soalIsian)): ?>
            <div class="section-header" style="margin-top: 18px;">B. ISIAN SINGKAT</div>
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
                    $isArabGroup = isTeksArab($firstItem['pertanyaan']) || isTeksArab($firstItem['kunci_jawaban'] ?? '');

                    // Acak posisi kolom kanan dengan seed konsisten
                    $countGroup = count($matchingGroup);
                    $shuffledIdxs = range(0, max(0, $countGroup - 1));
                    srand((int)$firstItem['id'] * 79 + 17);
                    shuffle($shuffledIdxs);
            ?>
                    <div class="soal-item">
                        <?php if (!empty(trim($firstItem['wacana_teks'] ?? ''))): 
                            $isWacanaArab = isTeksArab($firstItem['wacana_teks']) || isTeksArab($firstItem['wacana_judul'] ?? '');
                        ?>
                            <div class="wacana-box <?= $isWacanaArab ? 'wacana-arab' : '' ?>" <?= $isWacanaArab ? 'dir="rtl"' : '' ?>>
                                <?php if (!empty(trim($firstItem['wacana_judul'] ?? ''))): ?>
                                    <div class="wacana-title"><strong><?= renderTeksSoal($firstItem['wacana_judul'], $isWacanaArab) ?></strong></div>
                                <?php endif; ?>
                                <div class="wacana-content"><?= renderTeksSoal($firstItem['wacana_teks'], $isWacanaArab) ?></div>
                            </div>
                        <?php endif; ?>

                        <div class="matching-table-box">
                            <table class="matching-print-table <?= $isArabGroup ? 'soal-arab' : '' ?>" <?= $isArabGroup ? 'dir="rtl"' : '' ?>>
                                <?php foreach ($matchingGroup as $mGIdx => $mG): 
                                    $mNo = $mG['no'];
                                    $mLeft = $mG['item']['pertanyaan'];
                                    $rTargetIdx = $shuffledIdxs[$mGIdx] ?? $mGIdx;
                                    $mRight = $matchingGroup[$rTargetIdx]['item']['kunci_jawaban'] ?? '';
                                    $mLetter = chr(65 + $mGIdx);
                                ?>
                                    <tr>
                                        <!-- KOLOM KIRI (KATA / PREMIS SESUAI NOMOR URUT) -->
                                        <td style="width: 44%;">
                                            <strong class="matching-item-no"><?= $mNo ?>.</strong> <?= renderTeksSoal($mLeft, $isArabGroup) ?>
                                        </td>
                                        <!-- TITIK PELURU KIRI -->
                                        <td class="matching-bullet" style="width: 6%;">•</td>
                                        <!-- TITIK PELURU KANAN -->
                                        <td class="matching-bullet" style="width: 6%;">•</td>
                                        <!-- KOLOM KANAN (JAWABAN DIACAK POSISINYA) -->
                                        <td style="width: 44%;">
                                            <strong class="matching-item-letter"><?= $mLetter ?>.</strong> <?= renderTeksSoal($mRight, $isArabGroup) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    </div>

                <?php else: 
                    // Soal Isian Rumpang atau Benar/Salah (Single Item)
                    $isArab = isTeksArab($s['pertanyaan']);
                    $noSoal = $currIdx + 1;
                    $currIdx++;
                ?>
                    <div class="soal-item">
                        <?php if (!empty(trim($s['wacana_teks'] ?? ''))): 
                            $isWacanaArab = isTeksArab($s['wacana_teks']) || isTeksArab($s['wacana_judul'] ?? '');
                        ?>
                            <div class="wacana-box <?= $isWacanaArab ? 'wacana-arab' : '' ?>" <?= $isWacanaArab ? 'dir="rtl"' : '' ?>>
                                <?php if (!empty(trim($s['wacana_judul'] ?? ''))): ?>
                                    <div class="wacana-title"><strong><?= renderTeksSoal($s['wacana_judul'], $isWacanaArab) ?></strong></div>
                                <?php endif; ?>
                                <div class="wacana-content"><?= renderTeksSoal($s['wacana_teks'], $isWacanaArab) ?></div>
                            </div>
                        <?php endif; ?>

                        <table class="soal-table <?= $isArab ? 'soal-arab' : '' ?>" <?= $isArab ? 'dir="rtl"' : '' ?>>
                            <tr>
                                <td class="soal-no"><?= $noSoal ?>.</td>
                                <td class="soal-text <?= $isArab ? 'soal-arab' : '' ?>">
                                    <?php if ($subTipe === 'benar_salah'): ?>
                                        <span class="bs-bracket">( &nbsp; &nbsp; &nbsp; &nbsp; )</span> &nbsp; <?= renderTeksSoal($s['pertanyaan'], $isArab) ?>
                                    <?php else: ?>
                                        <?= renderTeksSoal($s['pertanyaan'], $isArab) ?>
                                    <?php endif; ?>

                                    <?php if (!empty($s['gambar']) && file_exists($uploadDirSoal . '/' . $s['gambar'])): ?>
                                        <div class="soal-img-box">
                                            <img src="<?= BASE_URL ?>/uploads/soal/<?= htmlspecialchars($s['gambar']) ?>"
                                                alt="Soal <?= $noSoal ?>">
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endwhile; ?>
        <?php endif; ?>

        <!-- ================================================================ -->
        <!-- BAGIAN C: URAIAN / ESSAY -->
        <!-- ================================================================ -->
        <?php if (!empty($soalEssay)): ?>
            <div class="section-header" style="margin-top: 18px;">C. URAIAN / ESSAY</div>
            <div class="section-desc">Jawablah pertanyaan-pertanyaan di bawah ini secara jelas, lengkap, dan sistematis!
            </div>

            <?php foreach ($soalEssay as $idx => $s): 
                $isArab = isTeksArab($s['pertanyaan']);
            ?>
                <div class="soal-item">
                    <?php if (!empty(trim($s['wacana_teks'] ?? ''))): 
                        $isWacanaArab = isTeksArab($s['wacana_teks']) || isTeksArab($s['wacana_judul'] ?? '');
                    ?>
                        <div class="wacana-box <?= $isWacanaArab ? 'wacana-arab' : '' ?>" <?= $isWacanaArab ? 'dir="rtl"' : '' ?>>
                            <?php if (!empty(trim($s['wacana_judul'] ?? ''))): ?>
                                <div class="wacana-title"><strong><?= renderTeksSoal($s['wacana_judul'], $isWacanaArab) ?></strong></div>
                            <?php endif; ?>
                            <div class="wacana-content"><?= renderTeksSoal($s['wacana_teks'], $isWacanaArab) ?></div>
                        </div>
                    <?php endif; ?>
                    <table class="soal-table <?= $isArab ? 'soal-arab' : '' ?>" <?= $isArab ? 'dir="rtl"' : '' ?>>
                        <tr>
                            <td class="soal-no"><?= $idx + 1 ?>.</td>
                            <td class="soal-text <?= $isArab ? 'soal-arab' : '' ?>">
                                <?= renderTeksSoal($s['pertanyaan'], $isArab) ?>

                                <?php if (!empty($s['gambar']) && file_exists($uploadDirSoal . '/' . $s['gambar'])): ?>
                                    <div class="soal-img-box">
                                        <img src="<?= BASE_URL ?>/uploads/soal/<?= htmlspecialchars($s['gambar']) ?>"
                                            alt="Soal <?= $idx + 1 ?>">
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- FOOTER TAMPILAN LAYAR (PRATINJAU) -->
        <div class="screen-footer">
            <span><?= htmlspecialchars($footerText) ?></span>
            <span>1</span>
        </div>

        <!-- PRINT FOOTER BAR (UNTUK PRINTER/BROWSER FALLBACK) -->
        <div class="print-footer-bar">
            <span><?= htmlspecialchars($footerText) ?></span>
            <span>Naskah Ujian SMP ISMI</span>
        </div>
    </div>

    <script>
    let currentLatinSize = parseFloat(localStorage.getItem('soal_font_latin') || '12');
    let currentArabSize = parseFloat(localStorage.getItem('soal_font_arab') || '15');

    function applyFontSizes() {
        document.documentElement.style.setProperty('--font-latin-size', currentLatinSize + 'pt');
        document.documentElement.style.setProperty('--font-arab-size', currentArabSize + 'pt');
        
        const labelLatin = document.getElementById('labelFontLatin');
        if (labelLatin) labelLatin.textContent = currentLatinSize + 'pt';
        
        const labelArab = document.getElementById('labelFontArab');
        if (labelArab) labelArab.textContent = currentArabSize + 'pt';

        // Update link Unduh Word agar format Word mengikuti ukuran font yang dipilih
        const linkWord = document.getElementById('btnDownloadWord');
        if (linkWord) {
            let href = linkWord.getAttribute('href');
            href = href.replace(/[?&]font_latin=[^&]*/, '').replace(/[?&]font_arab=[^&]*/, '');
            const sep = href.includes('?') ? '&' : '?';
            linkWord.setAttribute('href', href + sep + 'font_latin=' + currentLatinSize + '&font_arab=' + currentArabSize);
        }
    }

    function adjustFontSize(type, delta) {
        if (type === 'latin') {
            currentLatinSize = Math.max(8, Math.min(20, Math.round((currentLatinSize + delta) * 10) / 10));
            localStorage.setItem('soal_font_latin', currentLatinSize);
        } else if (type === 'arab') {
            currentArabSize = Math.max(10, Math.min(26, Math.round((currentArabSize + delta) * 10) / 10));
            localStorage.setItem('soal_font_arab', currentArabSize);
        }
        applyFontSizes();
    }

    function resetFontSizes() {
        currentLatinSize = 12;
        currentArabSize = 15;
        localStorage.removeItem('soal_font_latin');
        localStorage.removeItem('soal_font_arab');
        applyFontSizes();
    }

    function toggleArabicDirection() {
        const isLtr = document.body.classList.toggle('force-arabic-ltr');
        const btn = document.getElementById('btnToggleDir');
        if (btn) {
            if (isLtr) {
                btn.innerHTML = '<i class="bi bi-arrow-left-right me-1"></i> Arah: LTR';
                btn.classList.remove('btn-outline-warning');
                btn.classList.add('btn-warning', 'text-dark');
            } else {
                btn.innerHTML = '<i class="bi bi-arrow-left-right me-1"></i> Arah: RTL';
                btn.classList.remove('btn-warning', 'text-dark');
                btn.classList.add('btn-outline-warning');
            }
        }
    }

    // Inisialisasi saat halaman dibuka
    document.addEventListener('DOMContentLoaded', applyFontSizes);
    applyFontSizes();
    </script>
</body>
</html>