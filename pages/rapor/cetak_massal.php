<?php
/**
 * CETAK MASSAL RAPOR KURIKULUM MERDEKA (1 KELAS)
 * Presisi Format A4 Sesuai Standar SMP ISMI - Islamic School of Minhaj Al-Ilmi
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('rapor');

$pdo = getConnection();
require_once __DIR__ . '/init_rapor.php';
require_once __DIR__ . '/../../includes/rapor_helpers.php';

$kelasId = (int) ($_GET['kelas_id'] ?? 0);
$rawTipe = strtolower($_GET['tipe'] ?? 'ats');
$isAts = in_array($rawTipe, ['ats', 'pts', 'tengah']);
$tipeRapor = $isAts ? 'ats' : 'pas';
$semester = isset($_GET['semester']) && in_array($_GET['semester'], ['Ganjil', 'Genap']) ? $_GET['semester'] : 'Ganjil';
$tahunAjaran = isset($_GET['tahun_ajaran']) ? trim($_GET['tahun_ajaran']) : (date('Y') . '/' . (date('Y') + 1));

if ($kelasId <= 0) {
    die('ID Kelas tidak valid.');
}

// Ambil Data Kelas
$stmtK = $pdo->prepare("
    SELECT k.*, u.nama_lengkap AS nama_wali_kelas, 
           gd.nip AS nip_wali_kelas
    FROM kelas k
    LEFT JOIN users u ON k.wali_kelas_id = u.id
    LEFT JOIN guru_detail gd ON (u.id = gd.user_id OR k.wali_kelas_id = gd.user_id)
    WHERE k.id = :id
");
$stmtK->execute([':id' => $kelasId]);
$kelas = $stmtK->fetch();

if (!$kelas) {
    die('Data kelas tidak ditemukan.');
}

// Ambil Seluruh Siswa di Kelas
$stmtS = $pdo->prepare("SELECT * FROM siswa WHERE kelas_id = :kid AND status = 'aktif' ORDER BY nama ASC");
$stmtS->execute([':kid' => $kelasId]);
$siswaList = $stmtS->fetchAll();

if (empty($siswaList)) {
    die('Tidak ada data siswa aktif di kelas ini.');
}

// Ambil Pengaturan Rapor & KOP
$settingsRaw = $pdo->query("SELECT setting_key, setting_value FROM rapor_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

$useUploadedKop = ($settingsRaw['use_uploaded_kop'] ?? '0') === '1';
$kopImage = $settingsRaw['kop_image'] ?? '';
$kopLogo = $settingsRaw['kop_logo'] ?? '';
$kopNamaSekolah = !empty($settingsRaw['kop_nama_sekolah']) ? $settingsRaw['kop_nama_sekolah'] : 'SMP ISMI';
$kopYayasan = !empty($settingsRaw['kop_yayasan']) ? $settingsRaw['kop_yayasan'] : 'ISLAMIC SCHOOL OF MINHAJ AL-ILMI';
$kopSlogan = !empty($settingsRaw['kop_slogan']) ? $settingsRaw['kop_slogan'] : 'Berakhlak Mulia, Berilmu, Mahir IT';
$kopAlamat = !empty($settingsRaw['kop_alamat']) ? $settingsRaw['kop_alamat'] : 'Jl. Nakula No.1 Harjamukti Cimanggis Depok';
$kopTelp = !empty($settingsRaw['kop_telp']) ? $settingsRaw['kop_telp'] : 'Telp. (021) 4867 8793';
$kopEmail = !empty($settingsRaw['kop_email']) ? $settingsRaw['kop_email'] : 'smp.minhaj.al.ilmi@gmail.com';
$kopWebsite = !empty($settingsRaw['kop_website']) ? $settingsRaw['kop_website'] : 'sekolahismi.sch.id & ismi.sch.id';
$kopWa = !empty($settingsRaw['kop_wa']) ? $settingsRaw['kop_wa'] : 'Admin WA 0899-2244-111';

$kepsekJabatan = !empty($settingsRaw['kepsek_jabatan']) ? $settingsRaw['kepsek_jabatan'] : 'Mudir SMP';
$kepsekLembaga = !empty($settingsRaw['kepsek_lembaga']) ? $settingsRaw['kepsek_lembaga'] : 'Islamic School of Minhaj Al Ilmi';
$kepsekNama = !empty($settingsRaw['kepsek_nama']) ? $settingsRaw['kepsek_nama'] : 'Riki Kaptanto, Lc';
$kepsekNip = !empty($settingsRaw['kepsek_nip']) ? $settingsRaw['kepsek_nip'] : '20240711001';
$stempelImage = $settingsRaw['stempel_image'] ?? '';

$tempatTerbit = !empty($settingsRaw['tempat_terbit']) ? $settingsRaw['tempat_terbit'] : 'Depok';
$defaultFase = $settingsRaw['fase_default'] ?? 'D';

$uploadDir = __DIR__ . '/../../uploads/rapor';
$tahunAjaranFormatted = str_replace('/', ' - ', $tahunAjaran);

$semLower = strtolower($semester);
$defaultTglKey1 = 'tanggal_' . ($isAts ? 'ats' : 'aas') . '_' . $semLower;
$defaultTglKey2 = 'tanggal_' . ($isAts ? 'pts' : 'pas') . '_' . $semLower;
$defaultTglKey3 = 'tanggal_' . $tipeRapor . '_' . $semLower;
$tglSetting = $settingsRaw[$defaultTglKey1] ?? $settingsRaw[$defaultTglKey2] ?? $settingsRaw[$defaultTglKey3] ?? null;

$defaultTglRaw = !empty($tglSetting) ? $tglSetting : date('Y-m-d');
$defaultTglFormat = formatTanggal($defaultTglRaw);

// Watermark Settings
$watermarkEnabled = ($settingsRaw['watermark_enabled'] ?? '0') === '1';
$watermarkImg = trim($settingsRaw['watermark_image'] ?? '');
if ($watermarkEnabled && empty($watermarkImg) && !empty($settingsRaw['kop_logo'])) {
    $watermarkImg = trim($settingsRaw['kop_logo']);
}
$watermarkOpacity = max(0.01, min(1.0, ((float) ($settingsRaw['watermark_opacity'] ?? 12)) / 100));
$watermarkSize = max(100, min(1000, (int) ($settingsRaw['watermark_size'] ?? 450)));

$watermarkSrc = '';
if (!empty($watermarkImg)) {
    if (strpos($watermarkImg, 'http://') === 0 || strpos($watermarkImg, 'https://') === 0) {
        $watermarkSrc = $watermarkImg;
    } elseif (strpos($watermarkImg, '/') === 0) {
        $watermarkSrc = (defined('BASE_URL') ? BASE_URL : '') . $watermarkImg;
    } else {
        $watermarkSrc = (defined('BASE_URL') ? BASE_URL : '') . '/uploads/rapor/' . $watermarkImg;
    }
}

// Ambil Mata Pelajaran Aktif khusus untuk kelas ini (mendukung plotting per kelas)
$stmtMapels = $pdo->prepare("
    SELECT DISTINCT m.id AS mapel_id, m.id, m.nama_mapel, m.kelompok, m.kode_mapel 
    FROM guru_mapel_kelas gmk
    JOIN mata_pelajaran m ON gmk.mapel_id = m.id 
    WHERE gmk.kelas_id = :kid AND m.status = 'aktif' 
    ORDER BY m.kelompok ASC, m.kode_mapel ASC, m.nama_mapel ASC
");
$stmtMapels->execute([':kid' => $kelasId]);
$mapelList = $stmtMapels->fetchAll();

// Fallback jika belum di-plotting sama sekali
if (empty($mapelList)) {
    $mapelList = $pdo->query("SELECT id, id AS mapel_id, nama_mapel, kelompok, kode_mapel FROM mata_pelajaran WHERE status = 'aktif' ORDER BY kelompok ASC, kode_mapel ASC, nama_mapel ASC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        Rapor_Massal_Kelas_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $kelas['nama_kelas']) ?>_<?= strtoupper($tipeRapor) ?>
    </title>

    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        /* CSS RESET & A4 PAGE SIZING */
        @page {
            size: A4 portrait;
            margin: 8mm 12mm 8mm 12mm;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: 'Cambria', Georgia, serif;
            font-size: 9.5pt;
            line-height: 1.25;
            color: #000;
            background-color: #f1f5f9;
            margin: 0;
            padding: 0;
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
        }

        /* A4 PAGE CONTAINER PER SISWA */
        .page-container {
            width: 210mm;
            min-height: 297mm;
            max-height: 297mm;
            margin: 20px auto;
            background: #ffffff;
            padding: 10mm 14mm 10mm 14mm;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
            position: relative;
            font-family: 'Cambria', Georgia, serif;
            page-break-after: always;
            break-after: page;
        }

        /* PRINT STYLES */
        @media print {
            body {
                background: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .no-print {
                display: none !important;
            }

            .page-container {
                width: 100% !important;
                max-width: 100% !important;
                min-height: auto !important;
                height: auto !important;
                max-height: none !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                page-break-after: always !important;
                break-after: page !important;
                page-break-inside: avoid !important;
                overflow: visible !important;
            }
        }

        /* WATERMARK BACKGROUND */
        .watermark-container {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
            z-index: 0;
            display: flex !important;
            align-items: center;
            justify-content: center;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .watermark-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .kop-wrapper,
        .title-section,
        .identity-table,
        .report-table,
        .attendance-table,
        .sig-container {
            position: relative;
            z-index: 1;
        }

        .report-table,
        .report-table th,
        .report-table td,
        .identity-table,
        .identity-table td,
        .attendance-table,
        .attendance-table th,
        .attendance-table td {
            background: transparent !important;
            background-color: transparent !important;
        }

        /* KOP SURAT */
        .kop-wrapper {
            width: 100%;
            margin-bottom: 12px;
        }

        .kop-img {
            width: 100%;
            height: auto;
            display: block;
        }

        .kop-table {
            width: 100%;
            border-collapse: collapse;
            padding-bottom: 4px;
        }

        .kop-table td {
            vertical-align: middle;
            padding-bottom: 4px;
        }

        .kop-logo-box {
            width: 60px;
        }

        .kop-title-box {
            padding-left: 10px;
        }

        .kop-school-name {
            font-size: 17pt;
            font-weight: 900;
            line-height: 1.05;
            color: #1e293b;
            letter-spacing: -0.5px;
        }

        .kop-sub-1 {
            font-size: 10.5pt;
            font-weight: 800;
            line-height: 1.1;
            color: #0f766e;
            letter-spacing: 0.4px;
        }

        .kop-slogan {
            font-size: 8.5pt;
            font-style: italic;
            font-family: 'Cambria', Georgia, serif;
            color: #475569;
            margin-top: 1px;
        }

        .kop-contact-box {
            text-align: right;
            font-size: 7.5pt;
            line-height: 1.3;
            color: #334155;
        }

        /* TITLE */
        .title-section {
            text-align: center;
            margin: 12px 0 14px 0;
        }

        .title-main {
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }

        .title-sub {
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            margin-top: 1px;
        }

        /* IDENTITY */
        .identity-table {
            width: 100%;
            font-size: 9pt;
            font-weight: bold;
            margin-bottom: 6px;
            border-collapse: collapse;
        }

        .identity-table td {
            padding: 1px 0;
            vertical-align: middle;
        }

        /* MAIN REPORT TABLE */
        .report-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            font-size: 8.5pt;
            margin-bottom: 10px;
            border: 1.5px solid #000;
        }

        .report-table th,
        .report-table td {
            border: 1px solid #000;
            padding: 2.2px 3.5px;
            vertical-align: middle;
        }

        .report-table thead th {
            text-align: center !important;
            vertical-align: middle !important;
            font-weight: bold;
            background: #fff;
        }

        .report-table thead tr:nth-child(2) th:last-child {
            border-right: 1px solid #000 !important;
        }

        /* Target ONLY true rightmost column to ensure balanced border thickness */
        .report-table thead tr:first-child th:last-child,
        .report-table tbody tr td:last-child {
            border-right: 2px solid #000 !important;
        }

        .report-table tbody td.text-center {
            text-align: center;
        }

        .report-table tbody tr.row-summary td {
            font-weight: bold;
            padding: 2.5px 3.5px;
        }

        /* ATTENDANCE TABLE */
        .attendance-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            font-size: 8.5pt;
            margin-top: 18px;
            margin-bottom: 22px;
            border: 1.5px solid #000;
        }

        .attendance-table th,
        .attendance-table td {
            border: 1px solid #000;
            padding: 3.5px 5px;
            vertical-align: middle;
        }

        .attendance-table thead th {
            text-align: center !important;
            vertical-align: middle !important;
            font-weight: bold;
            background: #fff;
        }

        .attendance-table thead th:last-child,
        .attendance-table tbody tr td:last-child {
            border-right: 2px solid #000 !important;
        }

        /* SIGNATURE SECTION */
        .sig-container {
            width: 100%;
            font-size: 9pt;
            font-family: 'Cambria', Georgia, serif;
            margin-top: 16px;
            page-break-inside: avoid;
        }

        .sig-table {
            width: 100%;
            border: none;
            border-collapse: collapse;
            text-align: center;
        }

        .sig-table td {
            border: none;
            vertical-align: top;
            padding: 0 4px;
        }

        .sig-space {
            height: 58px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sig-name {
            font-weight: bold;
            text-decoration: underline;
        }

        .stamp-img {
            position: absolute;
            max-height: 58px;
            max-width: 95px;
            object-fit: contain;
            opacity: 0.95;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
        }

        .font-times {
            font-family: 'Times New Roman', Times, Georgia, serif !important;
        }
        .page-aas {
            padding: 16mm 18mm 16mm 18mm !important;
            box-sizing: border-box;
            background: #ffffff;
            color: #000000;
        }
        .table-aas-border th,
        .table-aas-border td {
            border: 1px solid #000000 !important;
        }
    </style>
</head>

<body>
    <!-- PRINT CONTROL BAR (NO PRINT) -->
    <div class="print-control-bar no-print">
        <div class="d-flex align-items-center gap-3">
            <a href="index.php?kelas_id=<?= $kelasId ?>&tipe=<?= $tipeRapor ?>&semester=<?= $semester ?>&tahun_ajaran=<?= urlencode($tahunAjaran) ?>"
                class="btn btn-outline-light btn-sm rounded-pill px-3 fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Menu Rapor
            </a>
            <span class="badge bg-primary px-3 py-1.5 rounded-pill font-monospace">
                Kelas <?= htmlspecialchars($kelas['nama_kelas']) ?> | <?= count($siswaList) ?> Santri (<?= strtoupper($tipeRapor) ?>)
            </span>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-danger rounded-pill px-4 py-1.5 fw-bold shadow-sm"
                style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border:none;">
                <i class="bi bi-printer-fill me-1.5"></i> Cetak Seluruh Rapor (<?= count($siswaList) * ($isAts ? 1 : 2) ?> Halaman)
            </button>
        </div>
    </div>

    <!-- LOOP PRINT SETIAP SISWA DALAM KELAS -->
    <?php foreach ($siswaList as $siswa):
        $siswaId = (int) $siswa['id'];

        // Ambil Data Rapor Siswa
        $stmtR = $pdo->prepare("SELECT * FROM rapor_siswa WHERE siswa_id = :sid AND (tipe_rapor = :tipe OR tipe_rapor = :tipe_alt) AND semester = :sem AND tahun_ajaran = :th LIMIT 1");
        $stmtR->execute([
            ':sid' => $siswaId,
            ':tipe' => $tipeRapor,
            ':tipe_alt' => ($isAts ? 'pts' : 'aas'),
            ':sem' => $semester,
            ':th' => $tahunAjaran
        ]);
        $rapor = $stmtR->fetch();

        // Ambil Seluruh Nilai Siswa untuk semester & tahun ajaran ini
        $stmtAllNilai = $pdo->prepare("SELECT * FROM nilai_siswa WHERE siswa_id = :sid AND semester = :sem AND tahun_ajaran = :th");
        $stmtAllNilai->execute([':sid' => $siswaId, ':sem' => $semester, ':th' => $tahunAjaran]);
        $rawStudentNilai = $stmtAllNilai->fetchAll();

        $nilaiRows = [];
        foreach ($mapelList as $m) {
            $matched = matchMapelNilai($rawStudentNilai, $m['nama_mapel']);
            $nilaiRows[] = [
                'nama_mapel' => $m['nama_mapel'],
                'kelompok' => $m['kelompok'],
                'ats' => $matched['ats'] ?? null,
                'aas' => $matched['aas'] ?? null,
                'nilai_akhir' => $matched['nilai_akhir'] ?? null,
                'predikat' => $matched['predikat'] ?? '',
                'deskripsi_ats' => $matched['deskripsi_ats'] ?? '',
                'deskripsi_aas' => $matched['deskripsi_aas'] ?? ''
            ];
        }

        $sakit = (int) ($rapor['sakit'] ?? 0);
        $izin = (int) ($rapor['izin'] ?? 0);
        $alpa = (int) ($rapor['tanpa_keterangan'] ?? 0);

        $tglRaporRaw = !empty($defaultTglRaw) ? $defaultTglRaw : (!empty($rapor['tanggal_rapor']) ? $rapor['tanggal_rapor'] : date('Y-m-d'));
        $tglRaporFormat = formatTanggal($tglRaporRaw);

        // Ambil Wali Kelas & NIP cerdas (prioritaskan NIP valid dari rapor jika bukan '-', fallback ke master data guru_detail)
        $rawRaporWaliNama = trim($rapor['wali_kelas_nama'] ?? '');
        $rawKelasNama = trim($kelas['nama_wali_kelas'] ?? '');
        $waliNama = ($rawRaporWaliNama !== '' && $rawRaporWaliNama !== 'Wali Kelas') ? $rawRaporWaliNama : ($rawKelasNama ?: 'Wali Kelas');

        $rawRaporWaliNip = trim($rapor['wali_kelas_nip'] ?? '');
        $rawKelasNip = trim($kelas['nip_wali_kelas'] ?? '');
        $waliNip = ($rawRaporWaliNip !== '' && $rawRaporWaliNip !== '-') ? $rawRaporWaliNip : $rawKelasNip;

        $savedDeskripsi = !empty($rapor['deskripsi_mapel_json']) ? json_decode($rapor['deskripsi_mapel_json'], true) : [];
        $fase = $siswa['fase'] ?? ($kelas['fase'] ?? ($defaultFase ?: 'D'));
        ?>
        <?php if ($isAts): ?>
        <div class="page-container">
            <!-- WATERMARK BACKGROUND (IF CONFIGURED) -->
            <?php if ($watermarkEnabled && !empty($watermarkSrc)): ?>
                <div class="watermark-container"
                    style="width: <?= (int) $watermarkSize ?>px !important; height: <?= (int) $watermarkSize ?>px !important; max-width: 95% !important; max-height: 90% !important; opacity: <?= (float) $watermarkOpacity ?> !important;">
                    <img src="<?= htmlspecialchars($watermarkSrc) ?>" alt="Watermark Rapor"
                        style="width: 100% !important; height: 100% !important; object-fit: contain;">
                </div>
            <?php endif; ?>

            <!-- 1. KOP SURAT -->
            <div class="kop-wrapper">
                <?php if ($useUploadedKop && !empty($kopImage) && file_exists($uploadDir . '/' . $kopImage)): ?>
                    <img src="<?= BASE_URL ?>/uploads/rapor/<?= htmlspecialchars($kopImage) ?>" alt="KOP Surat" class="kop-img">
                <?php else: ?>
                    <table class="kop-table">
                        <tr>
                            <td class="kop-logo-box">
                                <?php if (!empty($kopLogo) && file_exists($uploadDir . '/' . $kopLogo)): ?>
                                    <img src="<?= BASE_URL ?>/uploads/rapor/<?= htmlspecialchars($kopLogo) ?>" alt="Logo"
                                        style="width: 52px; height: 52px; object-fit: contain;">
                                <?php else: ?>
                                    <svg width="52" height="52" viewBox="0 0 100 100" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path d="M50 5L90 28V72L50 95L10 72V28L50 5Z" stroke="#0d9488" stroke-width="5.5"
                                            fill="none" />
                                        <path d="M50 18L78 34V66L50 82L22 66V34L50 18Z" stroke="#0d9488" stroke-width="4.5"
                                            fill="none" />
                                        <path d="M35 38H65M35 50H65M35 62H65M50 30V70" stroke="#0d9488" stroke-width="4"
                                            stroke-linecap="round" />
                                    </svg>
                                <?php endif; ?>
                            </td>
                            <td class="kop-title-box">
                                <div class="kop-school-name"><?= htmlspecialchars($kopNamaSekolah) ?></div>
                                <div class="kop-sub-1"><?= htmlspecialchars($kopYayasan) ?></div>
                                <div class="kop-slogan"><?= htmlspecialchars($kopSlogan) ?></div>
                            </td>
                            <td class="kop-contact-box">
                                <div><?= htmlspecialchars($kopAlamat) ?></div>
                                <div><?= htmlspecialchars($kopTelp) ?></div>
                                <div>Email : <span style="color: #0284c7;"><?= htmlspecialchars($kopEmail) ?></span></div>
                                <div>Website : <span style="color: #0284c7;"><?= htmlspecialchars($kopWebsite) ?></span></div>
                                <div style="font-weight: bold; color: #0f172a;"><?= htmlspecialchars($kopWa) ?></div>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>
            </div>

            <!-- 2. JUDUL DOKUMEN -->
            <div class="title-section">
                <div class="title-main">LAPORAN HASIL BELAJAR UJIAN TENGAH SEMESTER</div>
                <div class="title-sub">SEMESTER <?= strtoupper($semester) ?> TAHUN PELAJARAN <?= $tahunAjaranFormatted ?>
                </div>
            </div>

            <!-- 3. IDENTITAS PESERTA DIDIK -->
            <table class="identity-table">
                <tr>
                    <td style="width: 55px;">NAMA</td>
                    <td style="width: 12px; text-align: center;">:</td>
                    <td style="text-transform: uppercase;"><?= htmlspecialchars($siswa['nama']) ?></td>
                    <td style="width: 50px; padding-left: 25px;">NIS</td>
                    <td style="width: 12px; text-align: center;">:</td>
                    <td style="width: 140px;"><?= htmlspecialchars($siswa['nis'] ?: '-') ?></td>
                </tr>
                <tr>
                    <td>KELAS</td>
                    <td style="text-align: center;">:</td>
                    <td style="text-transform: uppercase;"><?= htmlspecialchars($kelas['nama_kelas']) ?></td>
                    <td style="padding-left: 25px;">NISN</td>
                    <td style="text-align: center;">:</td>
                    <td><?= htmlspecialchars($siswa['nisn'] ?: '-') ?></td>
                </tr>
            </table>

            <!-- 4. TABEL NILAI (NO KKM - PERSIS REFERENSI) -->
            <table class="report-table">
                <colgroup>
                    <col style="width: 5%;">
                    <col style="width: 38%;">
                    <col style="width: 7.5%;">
                    <col style="width: 32.5%;">
                    <col style="width: 17%;">
                </colgroup>
                <thead>
                    <tr>
                        <th rowspan="2">NO</th>
                        <th rowspan="2" style="text-align: center;">MATA PELAJARAN</th>
                        <th colspan="2" style="text-align: center;">NILAI</th>
                        <th rowspan="2">Deskripsi</th>
                    </tr>
                    <tr>
                        <th>ANGKA</th>
                        <th>HURUF</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalScore = 0;
                    $countMapel = 0;
                    foreach ($nilaiRows as $idx => $nl):
                        $mName = $nl['nama_mapel'];
                        $score = ($nl['ats'] !== null && $nl['ats'] !== '' && (float) $nl['ats'] > 0) ? (float) $nl['ats'] : 0;

                        if ($score > 0) {
                            $displayScore = (int) round($score);
                            $totalScore += $displayScore;
                            $countMapel++;
                            $angkaText = (string) $displayScore;
                            $hurufText = angkaKeTerbilang($displayScore);
                            $deskripsiText = 'TUNTAS';
                        } else {
                            $angkaText = '-';
                            $hurufText = '-';
                            $deskripsiText = '-';
                        }
                        ?>
                        <tr>
                            <td class="text-center"><?= $idx + 1 ?></td>
                            <td><?= htmlspecialchars($mName) ?></td>
                            <td class="text-center font-monospace" style="font-weight: 600;"><?= $angkaText ?></td>
                            <td class="text-center" style="font-size: 7.8pt;"><?= $hurufText ?></td>
                            <td class="text-center" style="font-size: 7.8pt; font-weight: 600;"><?= $deskripsiText ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- BARIS JUMLAH -->
                    <tr class="row-summary">
                        <td colspan="2" class="text-center">Jumlah</td>
                        <td colspan="3" class="text-center font-monospace"><?= $totalScore > 0 ? $totalScore : '-' ?></td>
                    </tr>

                    <!-- BARIS RATA-RATA -->
                    <tr class="row-summary">
                        <td colspan="2" class="text-center">Rata - rata</td>
                        <td colspan="3" class="text-center font-monospace">
                            <?= $countMapel > 0 ? number_format($totalScore / $countMapel, 1, ',', '') : '-' ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- 5. TABEL KETIDAK HADIRAN -->
            <table class="attendance-table">
                <colgroup>
                    <col style="width: 5%;">
                    <col style="width: 68%;">
                    <col style="width: 10%;">
                    <col style="width: 17%;">
                </colgroup>
                <thead>
                    <tr>
                        <th colspan="4">Ketidakhadiran</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align: center;">1</td>
                        <td>Sakit</td>
                        <td style="text-align: center; font-weight: bold;"><?= ($sakit > 0) ? $sakit : '-' ?></td>
                        <td style="text-align: center;">Hari</td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">2</td>
                        <td>Izin</td>
                        <td style="text-align: center; font-weight: bold;"><?= ($izin > 0) ? $izin : '-' ?></td>
                        <td style="text-align: center;">Hari</td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">3</td>
                        <td>Tanpa Keterangan</td>
                        <td style="text-align: center; font-weight: bold;"><?= ($alpa > 0) ? $alpa : '-' ?></td>
                        <td style="text-align: center;">Hari</td>
                    </tr>
                </tbody>
            </table>

            <!-- 6. TANDA TANGAN (3 BLOK: ORANG TUA, WALI KELAS, MUDIR SMP) -->
            <div class="sig-container">
                <table class="sig-table" style="margin-bottom: 22px;">
                    <tr>
                        <td style="width: 48%;">
                            Mengetahui,<br>
                            <strong>Orang Tua/Wali</strong>
                            <div class="sig-space"></div>
                            <div>( ........................................ )</div>
                        </td>
                        <td style="width: 4%;"></td>
                        <td style="width: 48%;">
                            <?= htmlspecialchars($tempatTerbit) ?>, <?= $tglRaporFormat ?><br>
                            <strong>Wali Kelas</strong>
                            <div class="sig-space"></div>
                            <div class="sig-name"><?= htmlspecialchars($waliNama) ?></div>
                            <div style="font-size: 8pt;"><?= htmlspecialchars($waliNip ?: '-') ?></div>
                        </td>
                    </tr>
                </table>

                <table class="sig-table">
                    <tr>
                        <td style="width: 100%;">
                            Mengetahui,<br>
                            <strong><?= htmlspecialchars($kepsekJabatan) ?></strong><br>
                            <span style="font-size: 8pt; font-weight: bold;"><?= htmlspecialchars($kepsekLembaga) ?></span>
                            <div class="sig-space">
                                <?php if (!empty($stempelImage) && file_exists($uploadDir . '/' . $stempelImage)): ?>
                                    <img src="<?= BASE_URL ?>/uploads/rapor/<?= htmlspecialchars($stempelImage) ?>"
                                        alt="Stempel" class="stamp-img">
                                <?php endif; ?>
                            </div>
                            <div class="sig-name"><?= htmlspecialchars($kepsekNama) ?></div>
                            <div style="font-size: 8pt;"><?= htmlspecialchars($kepsekNip ?: '-') ?></div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php else: ?>
        <!-- ======================================================= -->
        <!-- RAPOR AAS (AKHIR SEMESTER) - FORMAT 2 HALAMAN KURIKULUM MERDEKA -->
        <!-- ======================================================= -->

        <!-- HALAMAN 1: NILAI AKADEMIK & CAPAIAN KOMPETENSI -->
        <div class="page-container page-aas font-times">
            <!-- HEADER LAPORAN -->
            <div style="text-align: center; margin-bottom: 22px;">
                <h2 style="font-size: 13.5pt; font-weight: bold; margin: 0; letter-spacing: 0.5px; text-transform: uppercase;">LAPORAN HASIL BELAJAR</h2>
                <h3 style="font-size: 12.5pt; font-weight: bold; margin: 3px 0 0 0;">(RAPOR)</h3>
            </div>

            <!-- IDENTITAS PESERTA DIDIK (2 KOLOM) -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 18px; font-size: 9.5pt; line-height: 1.45;">
                <tr>
                    <td style="width: 17%;">Nama Peserta Didik</td>
                    <td style="width: 2%;">:</td>
                    <td style="width: 41%; font-weight: bold;"><?= htmlspecialchars($siswa['nama']) ?></td>
                    <td style="width: 16%; padding-left: 10px;">Kelas</td>
                    <td style="width: 2%;">:</td>
                    <td style="width: 22%;"><?= htmlspecialchars($kelas['nama_kelas']) ?></td>
                </tr>
                <tr>
                    <td>NISN</td>
                    <td>:</td>
                    <td><?= htmlspecialchars($siswa['nisn'] ?: ($siswa['nis'] ?: '-')) ?></td>
                    <td style="padding-left: 10px;">Fase</td>
                    <td>:</td>
                    <td><?= htmlspecialchars($fase ?: ($defaultFase ?: 'D')) ?></td>
                </tr>
                <tr>
                    <td>Sekolah</td>
                    <td>:</td>
                    <td><?= htmlspecialchars($kopYayasan ?: ($kopNamaSekolah ?: 'SMP Islamic School of Minhaj Al-Ilmi')) ?></td>
                    <td style="padding-left: 10px;">Semester</td>
                    <td>:</td>
                    <td><?= $semester === 'Ganjil' ? 'I (Satu)' : 'II (Dua)' ?></td>
                </tr>
                <tr>
                    <td style="vertical-align: top;">Alamat</td>
                    <td style="vertical-align: top;">:</td>
                    <td style="vertical-align: top;"><?= htmlspecialchars($kopAlamat ?: 'Jl. Nakula 1, Harjamukti, Cimanggis, Kota Depok') ?></td>
                    <td style="vertical-align: top; padding-left: 10px;">Tahun Pelajaran</td>
                    <td style="vertical-align: top;">:</td>
                    <td style="vertical-align: top;"><?= htmlspecialchars($tahunAjaran) ?></td>
                </tr>
            </table>

            <!-- TABEL NILAI AKHIR & CAPAIAN KOMPETENSI -->
            <table class="table-aas-border" style="width: 100%; border-collapse: collapse; font-size: 9pt;">
                <thead>
                    <tr style="background: #ffffff; text-align: center;">
                        <th style="width: 6%; border: 1.5px solid #000; padding: 7px 4px; font-weight: bold;">No</th>
                        <th style="width: 28%; border: 1.5px solid #000; padding: 7px 8px; font-weight: bold;">Muatan Pelajaran</th>
                        <th style="width: 11%; border: 1.5px solid #000; padding: 7px 4px; font-weight: bold;">Nilai Akhir</th>
                        <th style="width: 55%; border: 1.5px solid #000; padding: 7px 8px; font-weight: bold;">Capaian Kompetensi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($nilaiRows as $idx => $nl):
                        $mName = $nl['nama_mapel'];
                        $score = ($nl['nilai_akhir'] !== null && $nl['nilai_akhir'] !== '' && (float)$nl['nilai_akhir'] > 0)
                            ? (float)$nl['nilai_akhir']
                            : (($nl['aas'] !== null && (float)$nl['aas'] > 0) ? (float)$nl['aas'] : 0);
                        $scoreDisplay = $score > 0 ? (int)round($score) : '-';

                        // Deskripsi capaian
                        $desc = $savedDeskripsi[$mName] ?? '';
                        if (empty($desc) && $score > 0) {
                            $materi = getMateriMapelAas($mName, $pdo, $kelasId, $semester);
                            $desc = buildDeskripsiAas($score, $materi);
                        }
                    ?>
                    <tr>
                        <td style="border: 1px solid #000; padding: 7px 4px; text-align: center; vertical-align: top;"><?= $idx + 1 ?></td>
                        <td style="border: 1px solid #000; padding: 7px 8px; vertical-align: top; font-weight: 500;"><?= htmlspecialchars($mName) ?></td>
                        <td style="border: 1px solid #000; padding: 7px 4px; text-align: center; vertical-align: top; font-weight: bold; font-family: monospace, sans-serif; font-size: 10pt;"><?= $scoreDisplay ?></td>
                        <td style="border: 1px solid #000; padding: 7px 8px; vertical-align: top; text-align: justify; line-height: 1.38;">
                            <?= !empty($desc) ? htmlspecialchars($desc) : '-' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- HALAMAN 2: EKSTRAKURIKULER, ABSENSI, CATATAN, TTD -->
        <div class="page-container page-aas font-times" style="margin-top: 25px;">
            <!-- TABEL EKSTRAKURIKULER -->
            <table class="table-aas-border" style="width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 22px;">
                <thead>
                    <tr style="background: #ffffff; text-align: center;">
                        <th style="width: 6%; border: 1.5px solid #000; padding: 7px 4px; font-weight: bold;">No</th>
                        <th style="width: 28%; border: 1.5px solid #000; padding: 7px 8px; font-weight: bold;">Ekstrakurikuler</th>
                        <th style="width: 14%; border: 1.5px solid #000; padding: 7px 4px; font-weight: bold;">Predikat</th>
                        <th style="width: 52%; border: 1.5px solid #000; padding: 7px 8px; font-weight: bold;">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $savedEkskul = !empty($rapor['ekskul_json']) ? json_decode($rapor['ekskul_json'], true) : [];
                    if (empty($savedEkskul)) {
                        $savedEkskul = [
                            ['nama' => 'Bela Diri', 'predikat' => 'A', 'keterangan' => 'Ananda mampu dan Aktif mengikuti kegiatan bela diri serta menunjukkan disiplin dan sportivitas yang baik dalam latihan dan Menunjukkan kemampuan yang baik dalam mengikuti latihan bela diri, disiplin dalam berlatih, serta memiliki sikap sportivitas dan tanggung jawab yang baik.']
                        ];
                    }
                    foreach ($savedEkskul as $eIdx => $ek):
                    ?>
                    <tr>
                        <td style="border: 1px solid #000; padding: 7px 4px; text-align: center; vertical-align: top;"><?= $eIdx + 1 ?></td>
                        <td style="border: 1px solid #000; padding: 7px 8px; vertical-align: top;"><?= htmlspecialchars($ek['nama'] ?? '-') ?></td>
                        <td style="border: 1px solid #000; padding: 7px 4px; text-align: center; vertical-align: top; font-weight: bold;"><?= htmlspecialchars($ek['predikat'] ?? '-') ?></td>
                        <td style="border: 1px solid #000; padding: 7px 8px; vertical-align: top; text-align: justify; line-height: 1.38;">
                            <?= htmlspecialchars($ek['keterangan'] ?? '-') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- TABEL BERDAMPINGAN: KETIDAKHADIRAN & CATATAN WALI KELAS -->
            <table class="table-aas-border" style="width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 22px;">
                <thead>
                    <tr>
                        <th style="width: 45%; border: 1.5px solid #000; padding: 7px; text-align: center; font-weight: bold;">Ketidakhadiran</th>
                        <th style="width: 55%; border: 1.5px solid #000; padding: 7px; text-align: center; font-weight: bold;">Catatan Wali Kelas</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="border: 1.5px solid #000; padding: 0; vertical-align: top;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 9pt;">
                                <tr>
                                    <td style="width: 14%; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 7px 4px; text-align: center;">1</td>
                                    <td style="width: 50%; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 7px 8px;">Sakit</td>
                                    <td style="width: 20%; border-bottom: 1px solid #000; padding: 7px 4px; text-align: center; font-weight: bold;"><?= $sakit > 0 ? $sakit : '-' ?></td>
                                    <td style="width: 16%; border-bottom: 1px solid #000; padding: 7px 4px;">hari</td>
                                </tr>
                                <tr>
                                    <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 7px 4px; text-align: center;">2</td>
                                    <td style="border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 7px 8px;">Izin</td>
                                    <td style="border-bottom: 1px solid #000; padding: 7px 4px; text-align: center; font-weight: bold;"><?= $izin > 0 ? $izin : '-' ?></td>
                                    <td style="border-bottom: 1px solid #000; padding: 7px 4px;">hari</td>
                                </tr>
                                <tr>
                                    <td style="border-right: 1px solid #000; padding: 7px 4px; text-align: center;">3</td>
                                    <td style="border-right: 1px solid #000; padding: 7px 8px;">Tanpa Keterangan</td>
                                    <td style="padding: 7px 4px; text-align: center; font-weight: bold;"><?= $alpa > 0 ? $alpa : '-' ?></td>
                                    <td style="padding: 7px 4px;">hari</td>
                                </tr>
                            </table>
                        </td>
                        <td style="border: 1.5px solid #000; padding: 9px 12px; vertical-align: top; text-align: justify; line-height: 1.42;">
                            <?= nl2br(htmlspecialchars(!empty($rapor['catatan_wali_kelas']) ? $rapor['catatan_wali_kelas'] : 'Ananda mampu memahami materi dengan cukup baik dan menyelesaikan tugas secara bertanggung jawab. Dalam aspek akhlak dan ibadah, Ananda telah mengikuti program pembiasaan keagamaan sekolah dengan baik serta menunjukkan sikap hormat kepada guru dan teman. Ananda perlu meningkatkan konsistensi belajar agar potensi yang dimiliki dapat berkembang secara lebih optimal.')) ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- KOTAK TANGGAPAN ORANG TUA / WALI MURID -->
            <table class="table-aas-border" style="width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 28px;">
                <thead>
                    <tr>
                        <th style="border: 1.5px solid #000; padding: 7px; text-align: center; font-weight: bold;">Tanggapan Orang Tua / Wali Murid</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="border: 1.5px solid #000; height: 58px; padding: 8px 12px; vertical-align: top; font-size: 8.5pt;">
                            <?= !empty($rapor['tanggapan_ortu']) ? nl2br(htmlspecialchars($rapor['tanggapan_ortu'])) : '' ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- TANDA TANGAN 3 PIHAK (ORANG TUA, WALI KELAS, KEPALA SEKOLAH) -->
            <div style="width: 100%; font-size: 9.5pt;">
                <!-- BARIS ATAS: ORANG TUA & WALI KELAS -->
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
                    <tr>
                        <td style="width: 45%; vertical-align: top;">
                            Orang Tua / Wali Murid,
                            <div style="height: 55px;"></div>
                            <div>........................................................</div>
                        </td>
                        <td style="width: 10%;"></td>
                        <td style="width: 45%; vertical-align: top; text-align: right;">
                            <?= htmlspecialchars($tempatTerbit) ?>, <?= $tglRaporFormat ?><br>
                            Wali Kelas,
                            <div style="height: 55px;"></div>
                            <div><strong><u><?= htmlspecialchars($waliNama) ?></u></strong></div>
                            <div style="font-size: 8.5pt;">NIP. <?= htmlspecialchars($waliNip ?: '-') ?></div>
                        </td>
                    </tr>
                </table>

                <!-- BARIS BAWAH: MENGETAHUI KEPALA SEKOLAH (CENTER) -->
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="text-align: center; vertical-align: top;">
                            Mengetahui,<br>
                            <?= htmlspecialchars($kepsekJabatan ?: 'Kepala Sekolah') ?><br>
                            <div style="height: 65px; position: relative; display: flex; align-items: center; justify-content: center; margin: 2px auto;">
                                <?php 
                                $stempelOfficial = getSetting('stempel_path', '');
                                $stempelImgSrc = '';
                                if (!empty($stempelOfficial)) {
                                    $stempelImgSrc = BASE_URL . $stempelOfficial . '?v=' . time();
                                } elseif (!empty($stempelImage) && file_exists($uploadDir . '/' . $stempelImage)) {
                                    $stempelImgSrc = BASE_URL . '/uploads/rapor/' . $stempelImage . '?v=' . time();
                                }
                                if (!empty($stempelImgSrc)):
                                ?>
                                    <img src="<?= $stempelImgSrc ?>" alt="Stempel & TTD" style="max-height: 68px; max-width: 220px; object-fit: contain;">
                                <?php endif; ?>
                            </div>
                            <div><strong><u><?= htmlspecialchars($kepsekNama) ?></u></strong></div>
                            <div style="font-size: 8.5pt;">NIP. <?= htmlspecialchars($kepsekNip ?: '-') ?></div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>
</body>

</html>