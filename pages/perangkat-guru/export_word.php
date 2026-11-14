<?php
/**
 * EKSPOR NASKAH SOAL KE MICROSOFT WORD (.DOC)
 * Kompatibel Langsung Dibuka di MS Word, WPS Office & Google Docs
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

$cleanMapel = preg_replace('/[^a-zA-Z0-9]/', '_', $naskah['nama_mapel']);
$filename = "Soal_AAS_" . $cleanMapel . "_Kelas_" . $naskah['tingkat_kelas'] . "_" . date('Ymd');

header("Content-Type: application/vnd.ms-word; charset=utf-8");
header("Content-Disposition: attachment; filename=\"{$filename}.doc\"");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
?>
<html xmlns:o='urn:schemas-microsoft-com:office:office'
      xmlns:w='urn:schemas-microsoft-com:office:word'
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
        @page {
            size: 21.0cm 29.7cm;
            margin: 1.5cm 2.0cm 1.5cm 2.0cm;
            mso-page-orientation: portrait;
        }
        body {
            font-family: 'Cambria', Georgia, serif;
            font-size: 11pt;
            line-height: 1.35;
            color: #000000;
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
        .title-section {
            text-align: center;
            margin-bottom: 12px;
        }
        .title-main {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .title-sub {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .identitas-table {
            width: 100%;
            border: 1.5pt solid #000000;
            margin-bottom: 14px;
        }
        .identitas-table td {
            border: 1pt solid #000000;
            padding: 4pt 6pt;
            font-size: 10pt;
        }
        .section-title {
            font-size: 11.5pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 14px;
            margin-bottom: 3px;
        }
        .section-desc {
            font-size: 9.5pt;
            font-style: italic;
            margin-bottom: 8px;
        }
        .soal-table {
            width: 100%;
            margin-bottom: 10px;
        }
        .soal-table td {
            vertical-align: top;
            padding: 1pt 0;
        }
        .opsi-table {
            width: 100%;
            margin-top: 4pt;
        }
        .opsi-table td {
            width: 50%;
            padding: 2pt 4pt;
            font-size: 10.5pt;
        }
    </style>
</head>
<body>

    <!-- KOP SURAT RESMI (PNG) -->
    <?php if (!empty($kopBase64)): ?>
        <p align="center" style="margin-top: 0; margin-bottom: 12pt;">
            <img src="<?= $kopBase64 ?>" width="670" alt="KOP Ujian SMP ISMI">
        </p>
    <?php else: ?>
        <table class="kop-table">
            <tr>
                <?php if (!empty($logoBase64)): ?>
                    <td style="width: 70px;">
                        <img src="<?= $logoBase64 ?>" width="60" height="60" alt="Logo">
                    </td>
                <?php endif; ?>
                <td style="padding-left: 10px;">
                    <div class="kop-school-name"><?= htmlspecialchars($kopNamaSekolah) ?></div>
                    <div class="kop-sub">ISLAMIC SCHOOL OF <span style="color: #0d9488;">MINHAJ AL-ILMI</span></div>
                    <div class="kop-slogan"><?= htmlspecialchars($kopSlogan) ?></div>
                </td>
                <td class="kop-contact">
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

    <!-- JUDUL DOKUMEN & KOTAK NILAI -->
    <table style="width: 100%; margin-bottom: 12px;">
        <tr>
            <td style="text-align: center; vertical-align: middle;">
                <div class="title-main"><?= htmlspecialchars($naskah['judul_ujian']) ?></div>
                <div class="title-sub">SEMESTER <?= strtoupper($naskah['semester']) ?> TP. <?= htmlspecialchars($naskah['tahun_ajaran']) ?></div>
            </td>
            <td style="width: 75px; text-align: center; vertical-align: top;">
                <table style="width: 75px; height: 65px; border: 1.5pt solid #000000; border-collapse: collapse;">
                    <tr>
                        <td style="height: 18px; text-align: center; font-weight: bold; font-size: 9.5pt; border-bottom: 1pt solid #000000;">Nilai</td>
                    </tr>
                    <tr>
                        <td style="height: 45px;">&nbsp;</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- TABEL IDENTITAS PESERTA -->
    <table class="identitas-table">
        <tr>
            <td style="width: 15%;">Nama</td>
            <td style="width: 3%; text-align: center;">:</td>
            <td style="width: 32%;"></td>
            <td style="width: 18%;">Mata Pelajaran</td>
            <td style="width: 3%; text-align: center;">:</td>
            <td style="width: 29%; font-weight: bold;"><?= htmlspecialchars($naskah['nama_mapel']) ?></td>
        </tr>
        <tr>
            <td>Kelas</td>
            <td style="text-align: center;">:</td>
            <td>Kelas <?= htmlspecialchars($naskah['tingkat_kelas']) ?></td>
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

    <!-- BAGIAN A: PILIHAN GANDA -->
    <?php if (!empty($soalPg)): ?>
        <div class="section-title">A. PILIHAN GANDA</div>
        <div class="section-desc">Pilihlah salah satu jawaban yang paling tepat dengan memberi tanda silang (X) pada huruf a, b, c, atau d!</div>

        <?php foreach ($soalPg as $idx => $s): ?>
            <table class="soal-table">
                <tr>
                    <td style="width: 22px; font-weight: bold;"><?= $idx + 1 ?>.</td>
                    <td>
                        <div><?= nl2br(htmlspecialchars($s['pertanyaan'])) ?></div>
                        
                        <?php 
                        if (!empty($s['gambar'])):
                            $sImgPath = __DIR__ . '/../../uploads/soal/' . $s['gambar'];
                            if (file_exists($sImgPath)):
                                $sImgData = file_get_contents($sImgPath);
                                $sImgExt = pathinfo($sImgPath, PATHINFO_EXTENSION);
                                $sImgBase64 = 'data:image/' . $sImgExt . ';base64,' . base64_encode($sImgData);
                        ?>
                            <div style="margin: 6pt 0;">
                                <img src="<?= $sImgBase64 ?>" style="max-height: 150px; max-width: 260px;" alt="Soal <?= $idx + 1 ?>">
                            </div>
                        <?php endif; endif; ?>

                        <table class="opsi-table">
                            <tr>
                                <td><b>a.</b> <?= htmlspecialchars($s['pilihan_a'] ?? '') ?></td>
                                <td><b>c.</b> <?= htmlspecialchars($s['pilihan_c'] ?? '') ?></td>
                            </tr>
                            <tr>
                                <td><b>b.</b> <?= htmlspecialchars($s['pilihan_b'] ?? '') ?></td>
                                <td><b>d.</b> <?= htmlspecialchars($s['pilihan_d'] ?? '') ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- BAGIAN B: ISIAN SINGKAT -->
    <?php if (!empty($soalIsian)): ?>
        <div class="section-title" style="margin-top: 16px;">B. ISIAN SINGKAT</div>
        <div class="section-desc">Isilah titik-titik di bawah ini dengan jawaban yang benar dan singkat!</div>

        <?php foreach ($soalIsian as $idx => $s): ?>
            <table class="soal-table">
                <tr>
                    <td style="width: 22px; font-weight: bold;"><?= $idx + 1 ?>.</td>
                    <td>
                        <div><?= nl2br(htmlspecialchars($s['pertanyaan'])) ?></div>
                        <?php 
                        if (!empty($s['gambar'])):
                            $sImgPath = __DIR__ . '/../../uploads/soal/' . $s['gambar'];
                            if (file_exists($sImgPath)):
                                $sImgData = file_get_contents($sImgPath);
                                $sImgExt = pathinfo($sImgPath, PATHINFO_EXTENSION);
                                $sImgBase64 = 'data:image/' . $sImgExt . ';base64,' . base64_encode($sImgData);
                        ?>
                            <div style="margin: 6pt 0;">
                                <img src="<?= $sImgBase64 ?>" style="max-height: 150px; max-width: 260px;" alt="Soal <?= $idx + 1 ?>">
                            </div>
                        <?php endif; endif; ?>
                    </td>
                </tr>
            </table>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- BAGIAN C: URAIAN / ESSAY -->
    <?php if (!empty($soalEssay)): ?>
        <div class="section-title" style="margin-top: 16px;">C. URAIAN / ESSAY</div>
        <div class="section-desc">Jawablah pertanyaan-pertanyaan di bawah ini secara jelas, lengkap, dan sistematis!</div>

        <?php foreach ($soalEssay as $idx => $s): ?>
            <table class="soal-table">
                <tr>
                    <td style="width: 22px; font-weight: bold;"><?= $idx + 1 ?>.</td>
                    <td>
                        <div><?= nl2br(htmlspecialchars($s['pertanyaan'])) ?></div>
                        <?php 
                        if (!empty($s['gambar'])):
                            $sImgPath = __DIR__ . '/../../uploads/soal/' . $s['gambar'];
                            if (file_exists($sImgPath)):
                                $sImgData = file_get_contents($sImgPath);
                                $sImgExt = pathinfo($sImgPath, PATHINFO_EXTENSION);
                                $sImgBase64 = 'data:image/' . $sImgExt . ';base64,' . base64_encode($sImgData);
                        ?>
                            <div style="margin: 6pt 0;">
                                <img src="<?= $sImgBase64 ?>" style="max-height: 150px; max-width: 260px;" alt="Soal <?= $idx + 1 ?>">
                            </div>
                        <?php endif; endif; ?>
                    </td>
                </tr>
            </table>
        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>
