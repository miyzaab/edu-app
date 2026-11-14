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
    SELECT p.*, u.nama_lengkap AS nama_guru_user, gd.nip AS nip_guru_user 
    FROM perangkat_ajar p 
    LEFT JOIN users u ON p.user_id = u.id 
    LEFT JOIN guru_detail gd ON u.id = gd.user_id 
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

// Fallback & Setting Sekolah Resmi
$settingNamaSekolah = getSetting('nama_sekolah', '');
if (empty($settingNamaSekolah)) {
    $settingNamaSekolah = getSetting('identitas_nama_sekolah', '');
}
$namaSekolah = !empty($settingNamaSekolah) ? $settingNamaSekolah : (!empty($modul['nama_sekolah']) ? $modul['nama_sekolah'] : SCHOOL_NAME);

$alamatSekolah  = getSetting('alamat_sekolah', 'Jl. Nakula 1, Harjamukti, Cimanggis, Depok, Jawa Barat');
$teleponSekolah = getSetting('telepon_sekolah', '');
$websiteSekolah = getSetting('website_sekolah', '');
if ($websiteSekolah === '') {
    try {
        $stmtWeb = $pdo->query("SELECT setting_value FROM rapor_settings WHERE setting_key = 'kop_website' LIMIT 1");
        $valWeb = $stmtWeb ? $stmtWeb->fetchColumn() : '';
        if (!empty($valWeb)) {
            $websiteSekolah = $valWeb;
        }
    } catch (Exception $e) {
        // Lewati jika tabel rapor_settings belum ada
    }
}
if ($websiteSekolah === '') {
    $websiteSekolah = 'sekolahismi.sch.id & ismi.sch.id';
}

// Nama Guru: Utamakan nama lengkap resmi dari tabel users (dengan gelar akademik)
$rawGuruName = !empty($doc['nama_guru_user']) ? $doc['nama_guru_user'] : (!empty($modul['nama_guru']) ? $modul['nama_guru'] : ($_SESSION['nama_lengkap'] ?? 'Guru Pengampu'));
// Bersihkan sebutan non-formal seperti "Ustadz / Ustadzah" jika ada agar sesuai standar dokumen resmi
$namaGuru = preg_replace('/^(ustadz|ustadzah|ust|ustz)\.?\s+/i', '', trim($rawGuruName));

// NIP Guru: Ambil dari database guru_detail resmi
$nipGuru = !empty($doc['nip_guru_user']) && $doc['nip_guru_user'] !== '—' && $doc['nip_guru_user'] !== '-'
    ? $doc['nip_guru_user']
    : (!empty($modul['nip_guru']) && $modul['nip_guru'] !== '—' && $modul['nip_guru'] !== '-' ? $modul['nip_guru'] : '—');

// Prioritas Kepala Sekolah dari Settings Admin Resmi (Bukan Dummy Placeholder)
$settingNamaKepsek = getSetting('nama_kepsek', '');
if (empty($settingNamaKepsek)) {
    $settingNamaKepsek = getSetting('nama_kepala_sekolah', '');
}

if (!empty($settingNamaKepsek) && $settingNamaKepsek !== 'Kepala Sekolah, M.Pd') {
    $namaKepsek = $settingNamaKepsek;
} elseif (!empty($modul['nama_kepsek']) && $modul['nama_kepsek'] !== 'Kepala Sekolah, M.Pd') {
    $namaKepsek = $modul['nama_kepsek'];
} elseif (!empty($settingNamaKepsek)) {
    $namaKepsek = $settingNamaKepsek;
} else {
    $namaKepsek = 'Kepala Sekolah';
}

$settingNipKepsek = getSetting('nip_kepsek', '');
if ($settingNipKepsek !== '' && $settingNipKepsek !== null && $settingNipKepsek !== '—' && $settingNipKepsek !== '-') {
    $nipKepsek = $settingNipKepsek;
} elseif (!empty($modul['nip_kepsek']) && $modul['nip_kepsek'] !== '—' && $modul['nip_kepsek'] !== '-') {
    $nipKepsek = $modul['nip_kepsek'];
} elseif ($settingNipKepsek !== '') {
    $nipKepsek = $settingNipKepsek;
} else {
    $nipKepsek = '—';
}

$jpPerPekan = (int)($doc['jp_per_pekan'] ?? ($modul['jp_per_pekan'] ?? 4));
if ($jpPerPekan <= 0) $jpPerPekan = 2;

// Auto-heal jika dokumen lama belum memiliki bab terstruktur (cegah tabel ATP / Modul kosong)
if (empty($allBabList)) {
    require_once __DIR__ . '/../../includes/perangkat_generator.php';
    $rawBab = $modul['poin_bab_raw'] ?? '';
    $rawS1 = '';
    $rawS2 = '';
    if (!empty($rawBab)) {
        $lines = preg_split('/[\r\n]+/', trim($rawBab));
        $lines = array_filter(array_map('trim', $lines));
        $half = max(1, (int)ceil(count($lines) / 2));
        $rawS1 = implode("\n", array_slice($lines, 0, $half));
        $rawS2 = implode("\n", array_slice($lines, $half));
    }

    $gen = PerangkatGenerator::generatePerangkatLengkap([
        'mapel'          => $doc['mapel'],
        'kelas'          => $doc['kelas'],
        'jp_per_pekan'   => $jpPerPekan,
        'tahun_ajaran'   => $doc['tahun_ajaran'] ?? (date('Y') . '/' . (date('Y') + 1)),
        'nama_sekolah'   => $namaSekolah,
        'nama_guru'      => $namaGuru,
        'nip_guru'       => $nipGuru,
        'nama_kepsek'    => $namaKepsek,
        'nip_kepsek'     => $nipKepsek,
        'bab_semester_1' => $rawS1,
        'bab_semester_2' => $rawS2
    ]);

    try {
        $upd = $pdo->prepare("
            UPDATE perangkat_ajar 
            SET modul_ajar_json = :json,
                capaian_pembelajaran = :cp,
                tujuan_pembelajaran = :tp,
                alur_tujuan_pembelajaran = :atp,
                alokasi_waktu = :alokasi,
                jp_per_pekan = :jp
            WHERE id = :id
        ");
        $upd->execute([
            ':json'    => json_encode($gen['modul_json'], JSON_UNESCAPED_UNICODE),
            ':cp'      => $gen['capaian_pembelajaran'],
            ':tp'      => $gen['tujuan_pembelajaran'],
            ':atp'     => $gen['alur_tujuan_pembelajaran'],
            ':alokasi' => $gen['alokasi_waktu'],
            ':jp'      => $jpPerPekan,
            ':id'      => $id
        ]);
        $modul = $gen['modul_json'];
        $smt1  = $modul['semester_1'] ?? ['bab_list' => []];
        $smt2  = $modul['semester_2'] ?? ['bab_list' => []];
        $allBabList = array_merge($smt1['bab_list'] ?? [], $smt2['bab_list'] ?? []);
    } catch (Exception $e) {
        $modul = $gen['modul_json'];
        $smt1  = $modul['semester_1'] ?? ['bab_list' => []];
        $smt2  = $modul['semester_2'] ?? ['bab_list' => []];
        $allBabList = array_merge($smt1['bab_list'] ?? [], $smt2['bab_list'] ?? []);
    }
}

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

// Normalisasi singkatan SAW -> shallallahu 'alaihi wa sallam pada seluruh isi dokumen
$normalizeShalawat = static function (&$val) {
    if (is_string($val)) {
        $val = preg_replace('/\b(?:S\.A\.W\.?|SAW)\b/', "shallallahu 'alaihi wa sallam", $val);
    }
};
array_walk_recursive($modul, $normalizeShalawat);
array_walk_recursive($smt1, $normalizeShalawat);
array_walk_recursive($smt2, $normalizeShalawat);
array_walk_recursive($allBabList, $normalizeShalawat);
foreach (['capaian_pembelajaran', 'tujuan_pembelajaran', 'alur_tujuan_pembelajaran'] as $colKey) {
    if (!empty($doc[$colKey])) {
        $normalizeShalawat($doc[$colKey]);
    }
}

$rawFilename = $titleText . '_' . ($doc['mapel'] ?? 'Mapel') . '_Kelas_' . ($doc['kelas'] ?? '');
$pdfFilename = trim(preg_replace('/[^A-Za-z0-9_-]+/', '_', $rawFilename), '_') . '.pdf';

$autoloadPath  = __DIR__ . '/../../vendor/autoload.php';
$hasMpdf       = file_exists($autoloadPath);
$isPdfDownload = $hasMpdf && (($_GET['download'] ?? '') === 'pdf');

// Helper resolve URL atau path file lokal untuk logo/stempel
function resolveAssetSrc(string $settingPath, bool $forMpdf, string $fallbackRel = ''): string
{
    $rootDir = realpath(__DIR__ . '/../../') ?: (__DIR__ . '/../..');
    $clean   = ltrim(trim($settingPath), '/');

    if ($forMpdf) {
        if ($clean !== '' && stripos($clean, 'http') !== 0) {
            $local = $rootDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $clean);
            if (is_file($local)) {
                return $local;
            }
        }
        if ($fallbackRel !== '') {
            $fbLocal = $rootDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($fallbackRel, '/'));
            if (is_file($fbLocal)) {
                return $fbLocal;
            }
        }
        return '';
    }

    if ($clean !== '') {
        return stripos($clean, 'http') === 0 ? $settingPath : '../../' . $clean;
    }
    return $fallbackRel !== '' ? '../../' . ltrim($fallbackRel, '/') : '';
}

// Helper Render Kop Surat Resmi & Simetris
function renderKopSurat($namaSekolah, $alamatSekolah, $teleponSekolah, $websiteSekolah, bool $forMpdf = false) {
    $logoUrl     = resolveAssetSrc(getSetting('logo_path', ''), $forMpdf, 'assets/img/logo.png');
    $fallbackUrl = $forMpdf ? '' : '../../assets/img/logo.png';
    ?>
    <table class="kop-table">
        <tr>
            <td class="kop-logo-left">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo"<?= $fallbackUrl !== '' ? ' onerror="this.src=\'' . htmlspecialchars($fallbackUrl, ENT_QUOTES) . '\'"' : '' ?> style="width:74px; height:74px; object-fit:contain;">
                <?php endif; ?>
            </td>
            <td class="kop-text">
                <div class="kop-nama-sekolah"><?= htmlspecialchars($namaSekolah) ?></div>
                <div class="kop-alamat"><?= htmlspecialchars($alamatSekolah) ?></div>
                <?php if (!empty($teleponSekolah) || !empty($websiteSekolah)): ?>
                    <div class="kop-kontak">
                        <?php if (!empty($teleponSekolah)): ?>Telp: <?= htmlspecialchars($teleponSekolah) ?><?php endif; ?>
                        <?php if (!empty($teleponSekolah) && !empty($websiteSekolah)): ?> &nbsp;|&nbsp; <?php endif; ?>
                        <?php if (!empty($websiteSekolah)): ?>Website: <?= htmlspecialchars($websiteSekolah) ?><?php endif; ?>
                    </div>
                <?php endif; ?>
            </td>
            <td class="kop-logo-right">&nbsp;</td>
        </tr>
    </table>
    <table style="width:100%; border-collapse:collapse; margin:8px 0 14px 0;">
        <tr>
            <td style="border-top:2.5px solid #000; border-bottom:0.8px solid #000; height:2px; font-size:1px; line-height:1px; padding:0;">&nbsp;</td>
        </tr>
    </table>
    <?php
}

// Helper Render Tanda Tangan
function renderTTD($namaSekolah, $namaKepsek, $nipKepsek, $namaGuru, $nipGuru, $alamatSekolah, bool $forMpdf = false) {
    $citySetting = getSetting('kota_sekolah', '');
    if (!empty($citySetting)) {
        $city = $citySetting;
    } else {
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
    }

    $customDate = getSetting('titimangsa_dokumen', '');
    if (!empty($customDate)) {
        $indonesianDate = $customDate;
    } else {
        $months = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        $indonesianDate = date('j') . ' ' . $months[(int)date('n')] . ' ' . date('Y');
    }

    $stempelUrl    = resolveAssetSrc(getSetting('stempel_path', ''), $forMpdf);
    $jabatanKepsek = 'Kepala Sekolah';
    ?>
    <div class="ttd-container">
        <table class="ttd-table">
            <tr>
                <td style="width:50%; text-align:left; vertical-align:top; padding:0;">
                    Mengetahui,<br>
                    <?= $jabatanKepsek ?>
                </td>
                <td style="width:50%; text-align:right; vertical-align:top; padding:0;">
                    <?= htmlspecialchars($city) ?>, <?= $indonesianDate ?><br>
                    Guru Mata Pelajaran
                </td>
            </tr>
            <tr>
                <td class="ttd-space-cell" style="height:23mm; text-align:left; vertical-align:middle; padding:0;<?= $forMpdf ? '' : ' position:relative;' ?>">
                    <?php if ($stempelUrl !== ''): ?>
                        <?php if ($forMpdf): ?>
                            <img src="<?= htmlspecialchars($stempelUrl) ?>" alt="Stempel" style="height:82px; max-width:280px; vertical-align:middle;">
                        <?php else: ?>
                            <img src="<?= htmlspecialchars($stempelUrl) ?>" alt="Stempel" style="max-height:85px; max-width:280px; position:absolute; left:5px; top:50%; transform:translateY(-50%); opacity:0.95;">
                        <?php endif; ?>
                    <?php else: ?>
                        &nbsp;
                    <?php endif; ?>
                </td>
                <td class="ttd-space-cell" style="height:23mm; text-align:right; vertical-align:middle; padding:0;">&nbsp;</td>
            </tr>
            <tr>
                <td style="text-align:left; vertical-align:top; padding:0;">
                    <strong><u><?= htmlspecialchars($namaKepsek) ?></u></strong><br>
                    NIP. <?= !empty($nipKepsek) && $nipKepsek !== '—' && $nipKepsek !== '-' ? htmlspecialchars($nipKepsek) : '—' ?>
                </td>
                <td style="text-align:right; vertical-align:top; padding:0;">
                    <strong><u><?= htmlspecialchars($namaGuru) ?></u></strong><br>
                    NIP. <?= !empty($nipGuru) && $nipGuru !== '—' && $nipGuru !== '-' ? htmlspecialchars($nipGuru) : '—' ?>
                </td>
            </tr>
        </table>
    </div>
    <?php
}

// Bangun potongan HTML per dokumen/bab sekali saja (digunakan baik untuk Preview HTML maupun mPDF Vektor)
$paperSections = [];

// 1. COVER PAGE (DOC_TYPE == ALL)
if ($docType === 'all') {
    $coverLogoUrl = resolveAssetSrc(getSetting('logo_path', ''), $isPdfDownload, 'assets/img/logo.png');
    $coverFallbackUrl = $isPdfDownload ? '' : '../../assets/img/logo.png';
    ob_start();
    ?>
    <div style="text-align:center; padding-top:16mm;">
        <?php if ($coverLogoUrl !== ''): ?>
            <div style="margin-bottom:18px;">
                <img src="<?= htmlspecialchars($coverLogoUrl) ?>" alt="Logo Sekolah"<?= $coverFallbackUrl !== '' ? ' onerror="this.src=\'' . htmlspecialchars($coverFallbackUrl, ENT_QUOTES) . '\'"' : '' ?> style="width:96px; height:96px; object-fit:contain;">
            </div>
        <?php endif; ?>
        <div style="font-size:18pt; font-weight:bold; margin-bottom:6px; letter-spacing:1px;">
            PERANGKAT AJAR LENGKAP
        </div>
        <div style="font-size:24pt; font-weight:bold; margin-bottom:12px; text-transform:uppercase;">
            KURIKULUM MERDEKA
        </div>
        <div style="font-size:13pt; font-weight:bold; color:#333333; margin-bottom:20px;">
            FASE D — KELAS <?= htmlspecialchars($doc['kelas']) ?> (SMP/MTS)
        </div>

        <table style="width:140px; margin:0 auto 24px auto; border-collapse:collapse;">
            <tr><td style="border-top:3px solid #000000; height:2px; font-size:1px; line-height:1px; padding:0;">&nbsp;</td></tr>
        </table>

        <div style="font-size:15pt; font-weight:bold; text-transform:uppercase; margin-bottom:8px;">
            MATA PELAJARAN: <?= htmlspecialchars($doc['mapel']) ?>
        </div>
        <div style="font-size:12pt; color:#444444;">
            Tahun Ajaran <?= htmlspecialchars($doc['tahun_ajaran']) ?> | Beban Belajar: <?= htmlspecialchars($doc['alokasi_waktu']) ?>
        </div>
    </div>

    <table style="width:76%; margin:22mm auto 26mm auto; border:1.5px solid #000000; border-collapse:collapse; font-size:11pt; text-align:left;">
        <tr>
            <td style="width:38%; font-weight:bold; padding:7px 12px;">Satuan Pendidikan</td>
            <td style="width:5%; padding:7px 4px;">:</td>
            <td style="padding:7px 12px;"><?= htmlspecialchars($namaSekolah) ?></td>
        </tr>
        <tr>
            <td style="font-weight:bold; padding:5px 12px;">Guru Pengampu</td>
            <td style="padding:5px 4px;">:</td>
            <td style="padding:5px 12px;"><?= htmlspecialchars($namaGuru) ?></td>
        </tr>
        <tr>
            <td style="font-weight:bold; padding:5px 12px;">NIP Guru</td>
            <td style="padding:5px 4px;">:</td>
            <td style="padding:5px 12px;"><?= htmlspecialchars($nipGuru) ?></td>
        </tr>
        <tr>
            <td style="font-weight:bold; padding:7px 12px;">Kepala Sekolah</td>
            <td style="padding:7px 4px;">:</td>
            <td style="padding:7px 12px;"><?= htmlspecialchars($namaKepsek) ?></td>
        </tr>
    </table>

    <div style="text-align:center; font-size:10.5pt; font-weight:bold; text-transform:uppercase; margin-top:16mm;">
        <?= htmlspecialchars($namaSekolah) ?><br>
        <?= htmlspecialchars($alamatSekolah) ?>
    </div>
    <?php
    $paperSections[] = ['class' => 'paper paper-cover', 'html' => ob_get_clean()];
}

// 2. DOKUMEN CAPAIAN PEMBELAJARAN (CP)
if ($docType === 'cp' || $docType === 'all') {
    ob_start();
    renderKopSurat($namaSekolah, $alamatSekolah, $teleponSekolah, $websiteSekolah, $isPdfDownload);
    ?>
    <div class="title-header-center">
        CAPAIAN PEMBELAJARAN (CP)<br>
        MATA PELAJARAN: <?= strtoupper(htmlspecialchars($doc['mapel'])) ?><br>
        KURIKULUM MERDEKA — FASE <?= htmlspecialchars($doc['fase']) ?> (KELAS <?= htmlspecialchars($doc['kelas']) ?>)
    </div>

    <table class="meta-info-table" style="border-bottom: 1.5px solid #000; padding-bottom: 8px;">
        <tr>
            <td class="label-col">Satuan Pendidikan</td>
            <td class="colon-col">:</td>
            <td class="value-col"><?= htmlspecialchars($namaSekolah) ?></td>
            <td class="label-col-r">Fase / Kelas</td>
            <td class="colon-col-r">:</td>
            <td class="value-col-r">Fase <?= htmlspecialchars($doc['fase']) ?> (Kelas <?= htmlspecialchars($doc['kelas']) ?>)</td>
        </tr>
        <tr>
            <td class="label-col">Mata Pelajaran</td>
            <td class="colon-col">:</td>
            <td class="value-col"><strong><?= htmlspecialchars($doc['mapel']) ?></strong></td>
            <td class="label-col-r">Tahun Ajaran</td>
            <td class="colon-col-r">:</td>
            <td class="value-col-r"><?= htmlspecialchars($doc['tahun_ajaran']) ?></td>
        </tr>
        <tr>
            <td class="label-col">Beban Belajar</td>
            <td class="colon-col">:</td>
            <td class="value-col"><?= htmlspecialchars($doc['alokasi_waktu']) ?></td>
            <td class="label-col-r">Guru Pengampu</td>
            <td class="colon-col-r">:</td>
            <td class="value-col-r"><?= htmlspecialchars($namaGuru) ?></td>
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

    <?php
    renderTTD($namaSekolah, $namaKepsek, $nipKepsek, $namaGuru, $nipGuru, $alamatSekolah, $isPdfDownload);
    $paperSections[] = ['class' => 'paper', 'html' => ob_get_clean()];
}

// 3. DOKUMEN TUJUAN PEMBELAJARAN (TP)
if ($docType === 'tp' || $docType === 'all') {
    ob_start();
    renderKopSurat($namaSekolah, $alamatSekolah, $teleponSekolah, $websiteSekolah, $isPdfDownload);
    ?>
    <div class="title-header-center">
        TUJUAN PEMBELAJARAN (TP)<br>
        MATA PELAJARAN: <?= strtoupper(htmlspecialchars($doc['mapel'])) ?><br>
        KURIKULUM MERDEKA — TAHUN AJARAN <?= htmlspecialchars($doc['tahun_ajaran']) ?>
    </div>

    <table class="meta-info-table" style="border-bottom: 1.5px solid #000; padding-bottom: 8px;">
        <tr>
            <td class="label-col">Satuan Pendidikan</td>
            <td class="colon-col">:</td>
            <td class="value-col"><?= htmlspecialchars($namaSekolah) ?></td>
            <td class="label-col-r">Fase / Kelas</td>
            <td class="colon-col-r">:</td>
            <td class="value-col-r">Fase <?= htmlspecialchars($doc['fase']) ?> (Kelas <?= htmlspecialchars($doc['kelas']) ?>)</td>
        </tr>
        <tr>
            <td class="label-col">Mata Pelajaran</td>
            <td class="colon-col">:</td>
            <td class="value-col"><strong><?= htmlspecialchars($doc['mapel']) ?></strong></td>
            <td class="label-col-r">Tahun Ajaran</td>
            <td class="colon-col-r">:</td>
            <td class="value-col-r"><?= htmlspecialchars($doc['tahun_ajaran']) ?></td>
        </tr>
        <tr>
            <td class="label-col">Beban Belajar</td>
            <td class="colon-col">:</td>
            <td class="value-col"><?= htmlspecialchars($doc['alokasi_waktu']) ?></td>
            <td class="label-col-r">Guru Pengampu</td>
            <td class="colon-col-r">:</td>
            <td class="value-col-r"><?= htmlspecialchars($namaGuru) ?></td>
        </tr>
    </table>

    <div class="section-title">Matriks Rumusan Tujuan Pembelajaran (TP) Per Bab Pembahasan</div>
    <table class="table-custom">
        <thead>
            <tr>
                <th style="width: 20%;">Bab / Materi Pokok</th>
                <th style="width: 13%; text-align: center;">Semester</th>
                <th style="width: 53%;">Rumusan Tujuan Pembelajaran (TP)</th>
                <th style="width: 14%; text-align: center;">Alokasi JP</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($allBabList)): ?>
                <?php foreach ($allBabList as $b): ?>
                    <tr>
                        <td>
                            <strong>Bab <?= htmlspecialchars($b['nomor']) ?></strong><br>
                            <?= htmlspecialchars($b['judul_bersih']) ?>
                        </td>
                        <td style="text-align: center; vertical-align: middle;">
                            Semester <?= $b['semester'] ?><br>
                            <small style="color: #555;"><?= $b['semester'] == 1 ? '(Ganjil)' : '(Genap)' ?></small>
                        </td>
                        <td>
                            <ul class="tp-ul" style="margin: 0; padding-left: 16px; line-height: 1.55;">
                                <?php foreach ($b['tp_list'] ?? [] as $tpItem): ?>
                                    <li style="margin-bottom: 4px; text-align: justify;"><?= htmlspecialchars($tpItem) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                        <td style="text-align: center; vertical-align: middle; font-weight: bold;">
                            <?= htmlspecialchars($b['alokasi_jp']) ?> JP<br>
                            <small style="font-weight: normal; color: #555;">(<?= htmlspecialchars($b['jumlah_pertemuan']) ?> Pertemuan)</small>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 20px; color: #888;">
                        Belum ada data Tujuan Pembelajaran.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php
    renderTTD($namaSekolah, $namaKepsek, $nipKepsek, $namaGuru, $nipGuru, $alamatSekolah, $isPdfDownload);
    $paperSections[] = ['class' => 'paper', 'html' => ob_get_clean()];
}

// 4. DOKUMEN ALUR TUJUAN PEMBELAJARAN (ATP)
if ($docType === 'atp' || $docType === 'all') {
    ob_start();
    renderKopSurat($namaSekolah, $alamatSekolah, $teleponSekolah, $websiteSekolah, $isPdfDownload);
    ?>
    <div class="title-header-center">
        ALUR TUJUAN PEMBELAJARAN (ATP)<br>
        MATA PELAJARAN: <?= strtoupper(htmlspecialchars($doc['mapel'])) ?><br>
        KURIKULUM MERDEKA — TAHUN AJARAN <?= htmlspecialchars($doc['tahun_ajaran']) ?>
    </div>

    <table class="meta-info-table" style="border-bottom: 1.5px solid #000; padding-bottom: 8px;">
        <tr>
            <td class="label-col">Satuan Pendidikan</td>
            <td class="colon-col">:</td>
            <td class="value-col"><?= htmlspecialchars($namaSekolah) ?></td>
            <td class="label-col-r">Fase / Kelas</td>
            <td class="colon-col-r">:</td>
            <td class="value-col-r">Fase <?= htmlspecialchars($doc['fase']) ?> (Kelas <?= htmlspecialchars($doc['kelas']) ?>)</td>
        </tr>
        <tr>
            <td class="label-col">Mata Pelajaran</td>
            <td class="colon-col">:</td>
            <td class="value-col"><strong><?= htmlspecialchars($doc['mapel']) ?></strong></td>
            <td class="label-col-r">Alokasi Waktu</td>
            <td class="colon-col-r">:</td>
            <td class="value-col-r"><?= htmlspecialchars($doc['alokasi_waktu']) ?></td>
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
                            <ul class="tp-ul" style="margin: 0; padding-left: 16px; line-height: 1.5;">
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

    <?php
    renderTTD($namaSekolah, $namaKepsek, $nipKepsek, $namaGuru, $nipGuru, $alamatSekolah, $isPdfDownload);
    $paperSections[] = ['class' => 'paper', 'html' => ob_get_clean()];
}

// 5. DOKUMEN MODUL AJAR
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

foreach ($renderModulList as $b) {
    $m = $b['modul'] ?? [];
    ob_start();
    renderKopSurat($namaSekolah, $alamatSekolah, $teleponSekolah, $websiteSekolah, $isPdfDownload);
    ?>
    <div class="title-header-center">
        MODUL AJAR KURIKULUM MERDEKA<br>
        BAB <?= htmlspecialchars($b['nomor']) ?>: <?= strtoupper(htmlspecialchars($b['judul_bersih'])) ?><br>
        SEMESTER <?= $b['semester'] ?> (<?= $b['semester'] === 1 ? 'GANJIL' : 'GENAP' ?>)
    </div>

    <div class="section-title">I. INFORMASI UMUM</div>
    <table class="meta-info-table" style="border-bottom: 1.5px solid #000; padding-bottom: 8px;">
        <tr>
            <td class="label-col">Satuan Pendidikan</td>
            <td class="colon-col">:</td>
            <td class="value-col"><?= htmlspecialchars($namaSekolah) ?></td>
            <td class="label-col-r">Fase / Kelas</td>
            <td class="colon-col-r">:</td>
            <td class="value-col-r">Fase <?= htmlspecialchars($doc['fase']) ?> (Kelas <?= htmlspecialchars($doc['kelas']) ?>)</td>
        </tr>
        <tr>
            <td class="label-col">Mata Pelajaran</td>
            <td class="colon-col">:</td>
            <td class="value-col"><strong><?= htmlspecialchars($doc['mapel']) ?></strong></td>
            <td class="label-col-r">Alokasi Waktu</td>
            <td class="colon-col-r">:</td>
            <td class="value-col-r"><strong><?= htmlspecialchars($m['alokasi_waktu'] ?? "{$b['alokasi_jp']} JP") ?></strong></td>
        </tr>
        <tr>
            <td class="label-col">Guru Pengampu</td>
            <td class="colon-col">:</td>
            <td class="value-col"><?= htmlspecialchars($namaGuru) ?></td>
            <td class="label-col-r">Model Pembelajaran</td>
            <td class="colon-col-r">:</td>
            <td class="value-col-r"><?= htmlspecialchars(!empty($m['model_pembelajaran']) ? $m['model_pembelajaran'] : ($modul['model_pembelajaran'] ?? 'Problem-Based Learning (PBL)')) ?></td>
        </tr>
    </table>

    <table class="table-custom" style="margin-top: 5px;">
        <tr>
            <th style="width: 25%; text-align: left;">Kompetensi Awal</th>
            <td style="text-align: justify;"><?= htmlspecialchars($m['kompetensi_awal'] ?? '—') ?></td>
        </tr>
        <tr>
            <th style="text-align: left;">Profil Pelajar Pancasila</th>
            <td><?php
                $profilText = is_array($m['profil_pancasila'] ?? null)
                    ? implode(', ', $m['profil_pancasila'])
                    : ($m['profil_pancasila'] ?? 'Bernalar Kritis, Mandiri');
                $profilText = str_replace(
                    ['Beriman & Bertakwa kepada Tuhan YME, Akhlak Mulia, Mandiri, Bernalar Kritis', 'Beriman & Bertakwa kepada Tuhan YME'],
                    ["Beriman & Bertakwa kepada Allah Ta'ala, berakhlak mulia, mandiri dan bernalar kritis", "Beriman & Bertakwa kepada Allah Ta'ala"],
                    $profilText
                );
                echo htmlspecialchars($profilText);
            ?></td>
        </tr>
        <tr>
            <th style="text-align: left;">Sarana &amp; Prasarana</th>
            <td><?php
                $saranaText = $m['sarana_prasarana'] ?? 'Kitab Rujukan/Buku Pegangan Siswa, Papan Tulis, dan alat tulis';
                $saranaText = str_replace('Kitab Rujukan/Buku Pegangan Siswa, Papan Tulis, Lembar Studi Kasus Amaliah, Alat Peraga Praktik Ibadah.', 'Kitab Rujukan/Buku Pegangan Siswa, Papan Tulis, dan alat tulis', $saranaText);
                echo htmlspecialchars($saranaText);
            ?></td>
        </tr>
        <tr>
            <th style="text-align: left;">Target Peserta Didik</th>
            <td><?= htmlspecialchars(!empty($m['target_siswa']) ? $m['target_siswa'] : ($modul['target_siswa'] ?? 'Peserta didik reguler / santri (30 siswa)')) ?></td>
        </tr>
    </table>

    <div class="section-title">II. KOMPONEN INTI</div>
    <table class="table-custom">
        <tr>
            <th style="width: 25%; text-align: left;">Tujuan Pembelajaran</th>
            <td>
                <ul class="tp-ul" style="margin: 0; padding-left: 16px; line-height: 1.5;">
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

    <div class="section-subtitle" style="font-weight: bold; margin-top: 10px; margin-bottom: 4px;">Rencana Kegiatan Pembelajaran:</div>
    <table class="table-custom">
        <tr>
            <th style="width: 25%; text-align: left;">1. Pendahuluan</th>
            <td style="text-align: justify; line-height: 1.5;"><?php
                $pendTeks = $m['kegiatan_pendahuluan'] ?? '—';
                $pendTeks = str_replace('Guru mengucapkan salam hangat dan mengajak peserta didik berdoa bersama/membaca shalawat thibil qulub.', 'Guru mengucapkan salam hangat kepada peserta ajar.', $pendTeks);
                echo nl2br(htmlspecialchars($pendTeks));
            ?></td>
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

    <div class="section-subtitle" style="font-weight: bold; margin-top: 10px; margin-bottom: 4px;">Asesmen Pembelajaran:</div>
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
    <div class="lkpd-box" style="border: 1px solid #000; padding: 10px 14px; background: #fafafa; margin-bottom: 10px; font-size: 9.5pt; line-height: 1.5;">
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
            <td><?= nl2br(htmlspecialchars(!empty($m['daftar_pustaka']) ? $m['daftar_pustaka'] : ($modul['daftar_pustaka'] ?? '—'))) ?></td>
        </tr>
    </table>

    <?php
    renderTTD($namaSekolah, $namaKepsek, $nipKepsek, $namaGuru, $nipGuru, $alamatSekolah, $isPdfDownload);
    $paperSections[] = ['class' => 'paper', 'html' => ob_get_clean()];
}

// ============================================================================
// EXPORT LANGSUNG KE PDF VEKTOR (TAJAM, UKURAN KECIL, PROSES CEPAT) VIA MPDF
// ============================================================================
if ($isPdfDownload && !empty($paperSections)) {
    @ini_set('pcre.backtrack_limit', '10000000');
    @ini_set('memory_limit', '512M');
    require_once $autoloadPath;

    $mpdfCss = "
        body { font-family: freeserif; font-size: 10.5pt; line-height: 1.42; color: #000000; }
        .kop-table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        .kop-table td { vertical-align: middle; padding: 0; }
        .kop-logo-left { width: 82px; text-align: left; }
        .kop-logo-right { width: 82px; }
        .kop-text { text-align: center; vertical-align: middle; padding: 0 8px; }
        .kop-nama-sekolah { font-size: 14.5pt; font-weight: bold; text-transform: uppercase; margin: 0 0 2px 0; line-height: 1.25; color: #000; }
        .kop-alamat { font-size: 9.5pt; color: #111; line-height: 1.35; }
        .kop-kontak { font-size: 9pt; color: #222; line-height: 1.35; margin-top: 2px; }
        .title-header-center { text-align: center; font-size: 12.5pt; font-weight: bold; text-transform: uppercase; margin: 10px 0 14px 0; line-height: 1.35; }
        .meta-info-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 9.8pt; line-height: 1.4; }
        .meta-info-table td { padding: 3px 0; vertical-align: top; }
        .meta-info-table .label-col { width: 18%; font-weight: bold; white-space: nowrap; }
        .meta-info-table .colon-col { width: 2%; text-align: center; }
        .meta-info-table .value-col { width: 38%; }
        .meta-info-table .label-col-r { width: 16%; font-weight: bold; white-space: nowrap; padding-left: 8px; }
        .meta-info-table .colon-col-r { width: 2%; text-align: center; }
        .meta-info-table .value-col-r { width: 24%; }
        .section-title { font-size: 10.5pt; font-weight: bold; text-transform: uppercase; border-bottom: 1.5px solid #000; padding-bottom: 3px; margin-top: 14px; margin-bottom: 8px; page-break-after: avoid; }
        .section-subtitle { page-break-after: avoid; }
        .table-custom { width: 100%; border-collapse: collapse; margin-top: 6px; margin-bottom: 12px; font-size: 9.8pt; }
        .table-custom tr { page-break-inside: avoid; }
        .table-custom th, .table-custom td { border: 1px solid #000; padding: 6px 8px; vertical-align: top; }
        .table-custom th { background-color: #f1f5f9; text-align: center; font-weight: bold; text-transform: uppercase; }
        .lkpd-box { page-break-inside: avoid; }
        .ttd-container { width: 100%; margin-top: 8mm; page-break-inside: avoid; }
        .ttd-table { width: 100%; border-collapse: collapse; margin-top: 8mm; page-break-inside: avoid; }
        .ttd-table td { width: 50%; vertical-align: top; font-size: 10pt; border: none; padding: 0; }
        ul.tp-ul { margin: 0; padding-left: 15px; }
        ul.tp-ul li { margin-bottom: 3px; }
    ";

    $mpdf = new \Mpdf\Mpdf([
        'mode'          => 'utf-8',
        'format'        => 'A4',
        'margin_left'   => 11,
        'margin_right'  => 11,
        'margin_top'    => 12,
        'margin_bottom' => 13,
        'default_font'  => 'freeserif',
        'tempDir'       => sys_get_temp_dir(),
    ]);
    $mpdf->autoScriptToLang = true;
    $mpdf->autoLangToFont   = true;
    $mpdf->SetTitle($titleText . ' — ' . ($doc['mapel'] ?? ''));
    $mpdf->WriteHTML($mpdfCss, \Mpdf\HTMLParserMode::HEADER_CSS);

    foreach ($paperSections as $idx => $sec) {
        if ($idx > 0) {
            $mpdf->AddPage();
        }
        $mpdf->WriteHTML($sec['html'], \Mpdf\HTMLParserMode::HTML_BODY);
    }

    if (ob_get_length()) {
        @ob_end_clean();
    }
    $mpdf->Output($pdfFilename, \Mpdf\Output\Destination::DOWNLOAD);
    exit;
}
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

        /* BAR AKSI DOWNLOAD PDF DI LAYAR */
        .action-bar {
            width: 210mm;
            max-width: calc(100vw - 24px);
            margin: 0 auto 22px auto;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
            padding: 12px 20px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.8);
            box-sizing: border-box;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
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

        /* LEMBAR KERJA A4 RESMI (210mm x 297mm) */
        .paper-wrapper {
            width: 210mm;
            max-width: calc(100vw - 24px);
            margin: 0 auto;
        }

        .paper {
            background: #ffffff;
            width: 100%;
            min-height: 297mm;
            padding: 12mm 10mm;
            margin-bottom: 22px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            box-sizing: border-box;
            position: relative;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .paper-cover {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
            padding: 28mm 14mm;
        }

        /* STAGE OFF-SCREEN KHUSUS EXPORT PDF A4 RATA */
        .pdf-export-stage {
            position: fixed;
            left: -99999px;
            top: 0;
            width: 190mm;
            background: #ffffff;
            z-index: -1;
        }

        .pdf-page-box {
            width: 190mm;
            padding: 2px 4px;
            box-sizing: border-box;
            background: #ffffff;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .pdf-page-box.pdf-page-cover {
            height: 271mm;
            padding: 20mm 10mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
        }

        .pdf-page-box > :first-child {
            margin-top: 0 !important;
        }

        /* KOP SURAT RESMI & SIMETRIS */
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        .kop-table td {
            vertical-align: middle;
            padding: 0;
        }
        .kop-logo-left {
            width: 90px;
            text-align: left;
        }
        .kop-logo-left img {
            width: 78px;
            height: 78px;
            object-fit: contain;
            display: block;
        }
        .kop-logo-right {
            width: 90px;
        }
        .kop-text {
            text-align: center;
            vertical-align: middle;
            padding: 0 10px;
        }
        .kop-nama-sekolah {
            font-size: 15pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 3px 0;
            letter-spacing: 0.8px;
            line-height: 1.25;
            color: #000;
        }
        .kop-alamat {
            font-size: 9.5pt;
            color: #111;
            line-height: 1.35;
        }
        .kop-kontak {
            font-size: 9pt;
            color: #222;
            line-height: 1.35;
            margin-top: 2px;
        }
        .kop-garis {
            margin-top: 10px;
            margin-bottom: 18px;
        }
        .kop-garis .line-thick {
            border-top: 2.5px solid #000;
            margin-bottom: 1.5px;
        }
        .kop-garis .line-thin {
            border-top: 0.8px solid #000;
        }

        /* HEADER JUDUL DOKUMEN */
        .title-header-center {
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 14px 0 18px 0;
            line-height: 1.35;
            letter-spacing: 0.5px;
        }

        /* TABEL IDENTITAS */
        .meta-info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            font-size: 10pt;
            line-height: 1.45;
        }
        .meta-info-table td {
            padding: 3.5px 0;
            vertical-align: top;
        }
        .meta-info-table tr td:nth-child(1), .meta-info-table .label-col {
            width: 18%;
            font-weight: bold;
            white-space: nowrap;
        }
        .meta-info-table tr td:nth-child(2), .meta-info-table .colon-col {
            width: 2%;
            text-align: center;
        }
        .meta-info-table tr td:nth-child(3), .meta-info-table .value-col {
            width: 38%;
            word-wrap: break-word;
        }
        .meta-info-table tr td:nth-child(4), .meta-info-table .label-col-r {
            width: 16%;
            font-weight: bold;
            white-space: nowrap;
            padding-left: 10px;
        }
        .meta-info-table tr td:nth-child(5), .meta-info-table .colon-col-r {
            width: 2%;
            text-align: center;
        }
        .meta-info-table tr td:nth-child(6), .meta-info-table .value-col-r {
            width: 24%;
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
            padding: 7px 10px;
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
                <option value="tp" <?= $docType === 'tp' ? 'selected' : '' ?>>📄 2. Tujuan Pembelajaran (TP)</option>
                <option value="atp" <?= $docType === 'atp' ? 'selected' : '' ?>>📄 3. Alur Tujuan Pembelajaran (ATP 1 Tahun)</option>
                <option value="modul_sem1" <?= $docType === 'modul_sem1' ? 'selected' : '' ?>>📂 Modul Ajar Semester 1</option>
                <option value="modul_sem2" <?= $docType === 'modul_sem2' ? 'selected' : '' ?>>📂 Modul Ajar Semester 2</option>
            </select>
        </div>

        <?php if ($hasMpdf): ?>
            <a href="print.php?id=<?= $id ?>&doc_type=<?= urlencode($docType) ?>&sem=<?= $semParam ?>&bab_idx=<?= $babIdxParam ?>&download=pdf"
               class="btn-print">
                <i class="bi bi-file-earmark-arrow-down-fill"></i> <span>Download PDF</span>
            </a>
        <?php else: ?>
            <button type="button" id="btnDownloadPdf" onclick="downloadPdf()" class="btn-print">
                <i class="bi bi-file-earmark-arrow-down-fill"></i> <span id="btnDownloadLabel">Download PDF</span>
            </button>
        <?php endif; ?>
    </div>

    <div class="paper-wrapper">
        <?php foreach ($paperSections as $sec): ?>
            <div class="<?= htmlspecialchars($sec['class']) ?>">
                <?= $sec['html'] ?>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        const PDF_FILENAME = <?= json_encode($pdfFilename, JSON_UNESCAPED_UNICODE) ?>;

        async function waitForImages(container) {
            const imgs = Array.from(container.querySelectorAll('img'));
            await Promise.all(imgs.map((img) => {
                if (img.complete) return Promise.resolve();
                return new Promise((resolve) => {
                    img.addEventListener('load', resolve, { once: true });
                    img.addEventListener('error', resolve, { once: true });
                });
            }));
        }

        function paginatePaperElement(paperEl, stage, maxHeightPx) {
            const createdPages = [];

            const startNewPage = (isCover = false) => {
                const pageBox = document.createElement('div');
                pageBox.className = isCover ? 'pdf-page-box pdf-page-cover' : 'pdf-page-box';
                stage.appendChild(pageBox);
                createdPages.push(pageBox);
                return pageBox;
            };

            if (paperEl.classList.contains('paper-cover')) {
                const coverPage = startNewPage(true);
                Array.from(paperEl.children).forEach((child) => {
                    coverPage.appendChild(child.cloneNode(true));
                });
                return createdPages;
            }

            let currentPage = startNewPage(false);
            const children = Array.from(paperEl.children);

            for (let idx = 0; idx < children.length; idx++) {
                const child = children[idx];

                if (child.tagName === 'TABLE' && child.classList.contains('table-custom')) {
                    const thead = child.querySelector('thead');
                    let rows = Array.from(child.querySelectorAll(':scope > tbody > tr'));
                    if (!rows.length) {
                        rows = Array.from(child.querySelectorAll(':scope > tr'));
                    }
                    if (!rows.length) continue;

                    const makeTableShell = () => {
                        const tbl = child.cloneNode(false);
                        if (thead) tbl.appendChild(thead.cloneNode(true));
                        const tb = document.createElement('tbody');
                        tbl.appendChild(tb);
                        return { tbl, tb };
                    };

                    let { tbl, tb } = makeTableShell();
                    currentPage.appendChild(tbl);

                    for (const row of rows) {
                        const rowClone = row.cloneNode(true);
                        tb.appendChild(rowClone);

                        if (currentPage.scrollHeight > maxHeightPx) {
                            const hasEarlierContent = currentPage.children.length > 1 || tb.children.length > 1;
                            if (hasEarlierContent) {
                                rowClone.remove();
                                let orphanHeading = null;
                                if (tb.children.length === 0) {
                                    tbl.remove();
                                    const prev = currentPage.lastElementChild;
                                    if (prev && (prev.classList.contains('section-title') || prev.classList.contains('section-subtitle')) && currentPage.children.length > 1) {
                                        orphanHeading = prev;
                                        orphanHeading.remove();
                                    }
                                }
                                currentPage = startNewPage(false);
                                if (orphanHeading) currentPage.appendChild(orphanHeading);
                                ({ tbl, tb } = makeTableShell());
                                currentPage.appendChild(tbl);
                                tb.appendChild(rowClone);
                            }
                        }
                    }

                    if (tb.children.length === 0) {
                        tbl.remove();
                    }
                } else {
                    const clone = child.cloneNode(true);
                    const isHeading = clone.classList.contains('section-title') || clone.classList.contains('section-subtitle');
                    currentPage.appendChild(clone);

                    const overflowed = currentPage.scrollHeight > maxHeightPx;
                    const headingTooLow = isHeading && (currentPage.scrollHeight + 70 > maxHeightPx);

                    if ((overflowed || headingTooLow) && currentPage.children.length > 1) {
                        clone.remove();
                        currentPage = startNewPage(false);
                        currentPage.appendChild(clone);
                    }
                }
            }

            return createdPages.filter((p) => p.children.length > 0);
        }

        async function downloadPdf() {
            const btn = document.getElementById('btnDownloadPdf');
            const lbl = document.getElementById('btnDownloadLabel');
            const papers = Array.from(document.querySelectorAll('.paper-wrapper .paper'));

            if (!papers.length) {
                alert('Tidak ada halaman dokumen untuk diunduh.');
                return;
            }
            if (typeof html2canvas === 'undefined' || !window.jspdf || typeof window.jspdf.jsPDF !== 'function') {
                alert('Pustaka PDF gagal dimuat. Pastikan koneksi internet aktif lalu muat ulang halaman.');
                return;
            }

            btn.disabled = true;
            btn.style.opacity = '0.75';
            lbl.textContent = 'Menyiapkan tata letak A4...';

            const stage = document.createElement('div');
            stage.className = 'pdf-export-stage';
            document.body.appendChild(stage);
            const paperWrapper = document.querySelector('.paper-wrapper');

            try {
                await waitForImages(paperWrapper);

                // Rasio area cetak bersih A4: lebar 190mm (margin kiri/kanan 10mm), tinggi 273mm (margin atas/bawah 12mm)
                const stageWidthPx = stage.clientWidth || 718;
                const maxHeightPx = Math.floor(stageWidthPx * (271 / 190));

                const allPages = [];
                papers.forEach((paperEl) => {
                    const pagesForSection = paginatePaperElement(paperEl, stage, maxHeightPx);
                    pagesForSection.forEach((pg) => allPages.push(pg));
                });

                await waitForImages(stage);

                // Lepas semua halaman dulu agar tiap iterasi html2canvas hanya memproses 1 halaman (jauh lebih cepat)
                allPages.forEach((pg) => pg.remove());
                if (paperWrapper) paperWrapper.style.display = 'none';

                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait', compress: true });

                for (let i = 0; i < allPages.length; i++) {
                    lbl.textContent = `Menyiapkan PDF (${i + 1}/${allPages.length})...`;

                    const pageEl = allPages[i];
                    stage.innerHTML = '';
                    stage.appendChild(pageEl);

                    const canvas = await html2canvas(pageEl, {
                        scale: 1.35,
                        useCORS: true,
                        logging: false,
                        scrollY: 0,
                        backgroundColor: '#ffffff'
                    });

                    const cw = canvas.width;
                    const ch = canvas.height;
                    const imgData = canvas.toDataURL('image/jpeg', 0.65);
                    canvas.width = 0;
                    canvas.height = 0;
                    pageEl.remove();

                    if (i > 0) {
                        pdf.addPage();
                    }
                    const imgW = 190;
                    const imgH = Math.min(273, (ch / cw) * imgW);
                    pdf.addImage(imgData, 'JPEG', 10, 12, imgW, imgH, undefined, 'FAST');

                    if (i % 4 === 3) {
                        await new Promise((resolve) => setTimeout(resolve, 0));
                    }
                }

                lbl.textContent = 'Mengunduh PDF...';
                pdf.save(PDF_FILENAME);
            } catch (err) {
                console.error(err);
                alert('Terjadi kendala saat membuat PDF. Silakan muat ulang halaman dan coba lagi.');
            } finally {
                if (paperWrapper) paperWrapper.style.display = '';
                stage.remove();
                btn.disabled = false;
                btn.style.opacity = '';
                lbl.textContent = 'Download PDF';
            }
        }
    </script>

</body>
</html>
