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

// Helper untuk Render Teks Soal & Deteksi Font Arab
function renderTeksSoal($text) {
    if ($text === null || $text === '') return '';
    $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    
    // Regex deteksi blok teks Arab (huruf, harakat, tanda baca Arab)
    $pattern = '/([\p{Arabic}][\p{Arabic}\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}\s\p{P}\d]*[\p{Arabic}]|[\p{Arabic}])/u';
    
    $wrapped = preg_replace_callback($pattern, function($m) {
        $clean = trim($m[0]);
        if ($clean === '') return $m[0];
        return '<span class="teks-arab" dir="rtl">' . $clean . '</span>';
    }, $safe);
    
    return nl2br($wrapped);
}

// Pengaturan KOP dari rapor_settings (fallback jika belum upload KOP PNG)
$settingsRaw = $pdo->query("SELECT setting_key, setting_value FROM rapor_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

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
    <title>Naskah_Soal_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $naskah['nama_mapel']) ?>_Kelas_<?= $naskah['tingkat_kelas'] ?></title>

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

        body {
            font-family: 'Cambria', Georgia, serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #000;
            background-color: #f1f5f9;
            margin: 0;
            padding: 0;
        }

        /* FONT ARAB: Scheherazade New 15pt, spasi 1.0 */
        .teks-arab {
            font-family: 'Scheherazade New', 'Amiri', 'Traditional Arabic', serif !important;
            font-size: 15pt !important;
            line-height: 1.0 !important;
            direction: rtl;
            unicode-bidi: isolate;
            display: inline-block;
            vertical-align: middle;
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
            margin-top: 14px;
            margin-bottom: 4px;
            border-bottom: 1px dashed #666;
            padding-bottom: 2px;
            line-height: 1.35;
        }
        .section-desc {
            font-size: 10pt;
            font-style: italic;
            margin-bottom: 10px;
            line-height: 1.35;
        }

        .soal-item {
            margin-bottom: 12px;
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
            width: 26px;
            font-weight: bold;
            text-align: left;
            font-size: 12pt;
            line-height: 1.5;
        }
        .soal-text {
            font-size: 12pt;
            line-height: 1.5;
            text-align: justify;
        }
        .soal-img-box {
            margin: 6px 0;
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
            margin-top: 6px;
            margin-left: 2px;
            border-collapse: collapse;
        }
        .opsi-table td {
            vertical-align: top;
            padding: 3px 6px;
            width: 50%;
            font-size: 12pt;
            line-height: 1.5;
        }

        .opsi-letter {
            font-weight: bold;
            display: inline-block;
            width: 20px;
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
        <div class="d-flex align-items-center gap-3">
            <a href="edit.php?id=<?= $naskahId ?>" class="btn btn-outline-light btn-sm rounded-pill px-3 fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Editor
            </a>
            <span class="badge bg-primary px-3 py-1.5 rounded-pill font-monospace">
                <?= htmlspecialchars($naskah['nama_mapel']) ?> • Kelas <?= htmlspecialchars($naskah['tingkat_kelas']) ?>
            </span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="kop.php" class="btn btn-outline-warning btn-sm rounded-pill px-3 fw-semibold">
                <i class="bi bi-image me-1"></i> Upload KOP (PNG)
            </a>
            <a href="export_word.php?id=<?= $naskahId ?>" class="btn btn-outline-info btn-sm rounded-pill px-3 fw-bold">
                <i class="bi bi-file-earmark-word-fill me-1"></i> Unduh File Word (.doc)
            </a>
            <button onclick="window.print()" class="btn btn-danger btn-sm rounded-pill px-4 py-1.5 fw-bold shadow-sm"
                style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border:none;">
                <i class="bi bi-printer-fill me-1"></i> Cetak / Simpan PDF
            </button>
        </div>
    </div>

    <!-- LEMBAR DOKUMEN A4 -->
    <div class="page-sheet">
        <!-- 1. KOP SURAT (UPLOADED PNG) -->
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
                    <td class="kop-logo-box">
                        <?php if (file_exists(__DIR__ . '/../../assets/img/logo_1776435927.png')): ?>
                            <img src="<?= BASE_URL ?>/assets/img/logo_1776435927.png" alt="Logo SMP ISMI">
                        <?php else: ?>
                            <svg width="58" height="58" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M50 5L90 28V72L50 95L10 72V28L50 5Z" stroke="#0d9488" stroke-width="5.5" fill="none" />
                                <path d="M50 18L78 34V66L50 82L22 66V34L50 18Z" stroke="#0d9488" stroke-width="4.5" fill="none" />
                                <path d="M35 38H65M35 50H65M35 62H65M50 30V70" stroke="#0d9488" stroke-width="4" stroke-linecap="round" />
                            </svg>
                        <?php endif; ?>
                    </td>
                    <td class="kop-title-box">
                        <div class="kop-school-name"><?= htmlspecialchars($kopNamaSekolah) ?></div>
                        <div class="kop-sub-yayasan">ISLAMIC SCHOOL OF</div>
                        <div class="kop-sub-yayasan kop-sub-yayasan-green">MINHAJ AL-ILMI</div>
                        <div class="kop-slogan"><?= htmlspecialchars($kopSlogan) ?></div>
                    </td>
                    <td class="kop-contact-box">
                        <div><?= htmlspecialchars($kopAlamat) ?></div>
                        <div><?= htmlspecialchars($kopTelp) ?></div>
                        <div>Email : <span style="color: #0284c7;"><?= htmlspecialchars($kopEmail) ?></span></div>
                        <div>Website : <span style="color: #0284c7;"><?= htmlspecialchars($kopWebsite) ?></span></div>
                        <div style="font-weight: bold;"><?= htmlspecialchars($kopWa) ?></div>
                    </td>
                </tr>
            </table>
            <div class="kop-divider"></div>
        <?php endif; ?>

        <!-- 2. JUDUL DOKUMEN & KOTAK NILAI (3 KOLOM PRESISI) -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 14px;">
            <tr>
                <td style="width: 80px; vertical-align: top;">&nbsp;</td>
                <td style="text-align: center; vertical-align: middle;">
                    <div class="title-main"><?= htmlspecialchars($naskah['judul_ujian']) ?></div>
                    <div class="title-sub">SEMESTER <?= strtoupper($naskah['semester']) ?> TP. <?= htmlspecialchars($naskah['tahun_ajaran']) ?></div>
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
                <td><?= (int)$naskah['alokasi_waktu'] ?> Menit</td>
            </tr>
        </table>

        <!-- ================================================================ -->
        <!-- BAGIAN A: PILIHAN GANDA -->
        <!-- ================================================================ -->
        <?php if (!empty($soalPg)): ?>
            <div class="section-header">A. PILIHAN GANDA</div>
            <div class="section-desc">Pilihlah salah satu jawaban yang paling tepat dengan memberi tanda silang (X) pada huruf a, b, c, atau d!</div>

            <?php foreach ($soalPg as $idx => $s): ?>
                <div class="soal-item">
                    <table class="soal-table">
                        <tr>
                            <td class="soal-no"><?= $idx + 1 ?>.</td>
                            <td class="soal-text">
                                <?= renderTeksSoal($s['pertanyaan']) ?>

                                <?php if (!empty($s['gambar']) && file_exists($uploadDirSoal . '/' . $s['gambar'])): ?>
                                    <div class="soal-img-box">
                                        <img src="<?= BASE_URL ?>/uploads/soal/<?= htmlspecialchars($s['gambar']) ?>" alt="Soal <?= $idx + 1 ?>">
                                    </div>
                                <?php endif; ?>

                                <!-- OPSI A, B, C, D (2 KOLOM BERDAMPINGAN) -->
                                <table class="opsi-table">
                                    <tr>
                                        <td>
                                            <span class="opsi-letter">a.</span> <?= renderTeksSoal($s['pilihan_a'] ?? '') ?>
                                        </td>
                                        <td>
                                            <span class="opsi-letter">c.</span> <?= renderTeksSoal($s['pilihan_c'] ?? '') ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <span class="opsi-letter">b.</span> <?= renderTeksSoal($s['pilihan_b'] ?? '') ?>
                                        </td>
                                        <td>
                                            <span class="opsi-letter">d.</span> <?= renderTeksSoal($s['pilihan_d'] ?? '') ?>
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

            <?php foreach ($soalIsian as $idx => $s): ?>
                <div class="soal-item">
                    <table class="soal-table">
                        <tr>
                            <td class="soal-no"><?= $idx + 1 ?>.</td>
                            <td class="soal-text">
                                <?= renderTeksSoal($s['pertanyaan']) ?>

                                <?php if (!empty($s['gambar']) && file_exists($uploadDirSoal . '/' . $s['gambar'])): ?>
                                    <div class="soal-img-box">
                                        <img src="<?= BASE_URL ?>/uploads/soal/<?= htmlspecialchars($s['gambar']) ?>" alt="Soal <?= $idx + 1 ?>">
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- ================================================================ -->
        <!-- BAGIAN C: URAIAN / ESSAY -->
        <!-- ================================================================ -->
        <?php if (!empty($soalEssay)): ?>
            <div class="section-header" style="margin-top: 18px;">C. URAIAN / ESSAY</div>
            <div class="section-desc">Jawablah pertanyaan-pertanyaan di bawah ini secara jelas, lengkap, dan sistematis!</div>

            <?php foreach ($soalEssay as $idx => $s): ?>
                <div class="soal-item">
                    <table class="soal-table">
                        <tr>
                            <td class="soal-no"><?= $idx + 1 ?>.</td>
                            <td class="soal-text">
                                <?= renderTeksSoal($s['pertanyaan']) ?>

                                <?php if (!empty($s['gambar']) && file_exists($uploadDirSoal . '/' . $s['gambar'])): ?>
                                    <div class="soal-img-box">
                                        <img src="<?= BASE_URL ?>/uploads/soal/<?= htmlspecialchars($s['gambar']) ?>" alt="Soal <?= $idx + 1 ?>">
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
            <span>Halaman 1</span>
        </div>

        <!-- PRINT FOOTER BAR (UNTUK PRINTER/BROWSER FALLBACK) -->
        <div class="print-footer-bar">
            <span><?= htmlspecialchars($footerText) ?></span>
            <span>Naskah Ujian SMP ISMI</span>
        </div>
    </div>

</body>
</html>
