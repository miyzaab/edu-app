<?php
/**
 * EDITOR RAPOR SISWA KURIKULUM MERDEKA
 * Input deskripsi capaian kompetensi, kokurikuler, ekskul, absensi, catatan wali kelas
 */
$pageTitle = 'Edit Rapor Siswa';
$activePage = 'rapor';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('rapor');

$pdo = getConnection();
require_once __DIR__ . '/init_rapor.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);

$siswaId = (int) ($_GET['siswa_id'] ?? 0);
$rawTipe = strtolower($_GET['tipe'] ?? 'ats');
$isAts = in_array($rawTipe, ['ats', 'pts', 'tengah']);
$tipeRapor = $isAts ? 'ats' : 'pas';
$semester = isset($_GET['semester']) && in_array($_GET['semester'], ['Ganjil', 'Genap']) ? $_GET['semester'] : 'Ganjil';
$tahunAjaran = isset($_GET['tahun_ajaran']) ? trim($_GET['tahun_ajaran']) : (date('Y') . '/' . (date('Y') + 1));

if ($siswaId <= 0) {
    redirect('index.php', 'danger', 'ID Siswa tidak valid.');
}

// Ambil Data Siswa & Kelas
$stmtS = $pdo->prepare("
    SELECT s.*, k.nama_kelas, k.tingkat, k.wali_kelas_id, 
           u.nama_lengkap AS nama_wali_kelas, 
           gd.nip AS nip_wali_kelas
    FROM siswa s
    JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN users u ON k.wali_kelas_id = u.id
    LEFT JOIN guru_detail gd ON (u.id = gd.user_id OR k.wali_kelas_id = gd.user_id)
    WHERE s.id = :id
");
$stmtS->execute([':id' => $siswaId]);
$siswa = $stmtS->fetch();

if (!$siswa) {
    redirect('index.php', 'danger', 'Data siswa tidak ditemukan.');
}

// Ambil Settings Default Rapor
$settingsRaw = $pdo->query("SELECT setting_key, setting_value FROM rapor_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$defaultKepsekNama = $settingsRaw['kepsek_nama'] ?? 'Ustadz Ibrahim, M.Pd.';
$defaultKepsekNip = $settingsRaw['kepsek_nip'] ?? '-';
$defaultTempat = $settingsRaw['tempat_terbit'] ?? 'Jakarta';
$defaultFase = $settingsRaw['fase_default'] ?? 'D';
$defaultKokurikuler = $settingsRaw['kokurikuler_default'] ?? 'Peserta didik aktif berpartisipasi dalam Projek Penguatan Profil Pelajar Pancasila (P5) dengan menunjukkan sikap mandiri, gotong royong, dan bernalar kritis.';

$defaultTanggalKey = 'tanggal_' . ($isAts ? 'pts' : 'pas') . '_' . strtolower($semester);
$defaultTanggal = $settingsRaw[$defaultTanggalKey] ?? date('Y-m-d');

// Ambil Data Rapor Siswa yang Sudah Tersimpan (Jika Ada)
$stmtR = $pdo->prepare("
    SELECT * FROM rapor_siswa 
    WHERE siswa_id = :sid AND (tipe_rapor = :tipe OR tipe_rapor = :tipe_alt) AND semester = :sem AND tahun_ajaran = :th 
    LIMIT 1
");
$stmtR->execute([
    ':sid' => $siswaId,
    ':tipe' => $tipeRapor,
    ':tipe_alt' => ($isAts ? 'pts' : 'aas'),
    ':sem' => $semester,
    ':th' => $tahunAjaran
]);
$rapor = $stmtR->fetch();

// Ambil Mata Pelajaran Aktif khusus untuk kelas santri ini (mendukung plotting per kelas)
$kid = (int) ($siswa['kelas_id'] ?? 0);
$stmtMapels = $pdo->prepare("
    SELECT DISTINCT m.id AS mapel_id, m.nama_mapel, m.kelompok, m.kode_mapel 
    FROM guru_mapel_kelas gmk
    JOIN mata_pelajaran m ON gmk.mapel_id = m.id 
    WHERE gmk.kelas_id = :kid AND m.status = 'aktif' 
    ORDER BY m.kelompok ASC, m.kode_mapel ASC, m.nama_mapel ASC
");
$stmtMapels->execute([':kid' => $kid]);
$allActiveMapels = $stmtMapels->fetchAll();

// Fallback jika kelas belum memiliki plotting guru pengampu khusus
if (empty($allActiveMapels)) {
    $stmtMapels = $pdo->query("SELECT id AS mapel_id, nama_mapel, kelompok, kode_mapel FROM mata_pelajaran WHERE status = 'aktif' ORDER BY kelompok ASC, kode_mapel ASC, nama_mapel ASC");
    $allActiveMapels = $stmtMapels->fetchAll();
}

// Ambil seluruh nilai siswa untuk semester & tahun ajaran ini
$stmtAllNilai = $pdo->prepare("SELECT * FROM nilai_siswa WHERE siswa_id = :sid AND semester = :sem AND tahun_ajaran = :th");
$stmtAllNilai->execute([
    ':sid' => $siswaId,
    ':sem' => $semester,
    ':th' => $tahunAjaran
]);
$rawStudentNilai = $stmtAllNilai->fetchAll();

$nilaiList = [];
foreach ($allActiveMapels as $m) {
    $matched = matchMapelNilai($rawStudentNilai, $m['nama_mapel']);
    $nilaiList[] = [
        'mapel_id' => $m['mapel_id'],
        'nama_mapel' => $m['nama_mapel'],
        'kelompok' => $m['kelompok'],
        'sumatif_1' => $matched['sumatif_1'] ?? null,
        'sumatif_2' => $matched['sumatif_2'] ?? null,
        'sumatif_3' => $matched['sumatif_3'] ?? null,
        'sumatif_4' => $matched['sumatif_4'] ?? null,
        'ats' => $matched['ats'] ?? null,
        'aas' => $matched['aas'] ?? null,
        'nilai_akhir' => $matched['nilai_akhir'] ?? null,
        'predikat' => $matched['predikat'] ?? '',
        'catatan_guru' => $matched['catatan'] ?? '',
        'deskripsi_ats' => $matched['deskripsi_ats'] ?? '',
        'deskripsi_aas' => $matched['deskripsi_aas'] ?? ''
    ];
}

// Ambil Capaian Pembelajaran dari Perangkat Ajar untuk bantuan rekomendasi deskripsi (jika ada)
$cpMap = [];
try {
    $cpRaw = $pdo->query("SELECT mapel, capaian_pembelajaran, tujuan_pembelajaran, topik FROM perangkat_ajar")->fetchAll();
    foreach ($cpRaw as $c) {
        $cpMap[$c['mapel']] = $c;
    }
} catch (Exception $eCp) {
    $cpMap = [];
}

// Decode JSON yang sudah ada atau siapkan array kosong
$savedDeskripsiMapel = !empty($rapor['deskripsi_mapel_json']) ? json_decode($rapor['deskripsi_mapel_json'], true) : [];
$savedEkskul = !empty($rapor['ekskul_json']) ? json_decode($rapor['ekskul_json'], true) : [
    ['nama' => 'Pramuka', 'predikat' => 'Sangat Baik', 'keterangan' => 'Aktif dan disiplin dalam kepramukaan'],
    ['nama' => 'Tahfidz Al-Qur\'an', 'predikat' => 'Baik', 'keterangan' => 'Menunjukkan hafalan yang mutqin']
];

// PROSES SIMPAN / UPDATE RAPOR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_rapor') {
    $sakit = (int) ($_POST['sakit'] ?? 0);
    $izin = (int) ($_POST['izin'] ?? 0);
    $alpa = (int) ($_POST['tanpa_keterangan'] ?? 0);
    $catatanWali = trim($_POST['catatan_wali_kelas'] ?? '');
    $tanggapanOrtu = trim($_POST['tanggapan_ortu'] ?? '');
    $kokurikuler = isset($_POST['kokurikuler_deskripsi']) ? trim($_POST['kokurikuler_deskripsi']) : ($rapor['kokurikuler_deskripsi'] ?? $defaultKokurikuler);
    $fase = trim($_POST['fase'] ?? $defaultFase);
    $tempatTerbit = trim($_POST['tempat_terbit'] ?? $defaultTempat);
    $tglRapor = !empty($_POST['tanggal_rapor']) ? $_POST['tanggal_rapor'] : $defaultTanggal;
    $waliNama = trim($_POST['wali_kelas_nama'] ?? ($siswa['nama_wali_kelas'] ?? ''));
    $waliNip = trim($_POST['wali_kelas_nip'] ?? ($siswa['nip_wali_kelas'] ?? '-'));
    $kepsekNama = trim($_POST['kepsek_nama'] ?? $defaultKepsekNama);
    $kepsekNip = trim($_POST['kepsek_nip'] ?? $defaultKepsekNip);

    // Deskripsi Mapel
    if (isset($_POST['deskripsi_mapel'])) {
        $postDeskripsiMapel = $_POST['deskripsi_mapel'];
        $deskripsiJson = json_encode($postDeskripsiMapel, JSON_UNESCAPED_UNICODE);
    } else {
        $deskripsiJson = $rapor['deskripsi_mapel_json'] ?? '[]';
    }

    // Ekskul
    if (isset($_POST['ekskul_nama'])) {
        $ekskulNama = $_POST['ekskul_nama'] ?? [];
        $ekskulPred = $_POST['ekskul_predikat'] ?? [];
        $ekskulKet = $_POST['ekskul_keterangan'] ?? [];
        $ekskulData = [];
        foreach ($ekskulNama as $idx => $eName) {
            $eName = trim($eName);
            if (!empty($eName)) {
                $ekskulData[] = [
                    'nama' => $eName,
                    'predikat' => trim($ekskulPred[$idx] ?? 'Baik'),
                    'keterangan' => trim($ekskulKet[$idx] ?? '')
                ];
            }
        }
        $ekskulJson = json_encode($ekskulData, JSON_UNESCAPED_UNICODE);
    } else {
        $ekskulJson = $rapor['ekskul_json'] ?? '[]';
    }

    // Simpan Perubahan Nilai ATS (khusus jika diubah oleh Wali Kelas pada mode ATS)
    if (isset($_POST['nilai_ats']) && is_array($_POST['nilai_ats'])) {
        $stmtCheckNilai = $pdo->prepare("SELECT * FROM nilai_siswa WHERE siswa_id = :sid AND mapel = :mapel AND semester = :sem AND tahun_ajaran = :th LIMIT 1");
        $stmtInsertNilai = $pdo->prepare("
            INSERT INTO nilai_siswa (siswa_id, kelas_id, mapel, semester, tahun_ajaran, ats, nilai_akhir, predikat, user_id)
            VALUES (:sid, :kid, :mapel, :sem, :th, :ats, :na, :pred, :uid)
        ");
        $stmtUpdateNilai = $pdo->prepare("
            UPDATE nilai_siswa 
            SET ats = :ats, nilai_akhir = :na, predikat = :pred, user_id = :uid
            WHERE id = :id
        ");

        foreach ($_POST['nilai_ats'] as $mapelName => $atsVal) {
            $atsVal = trim($atsVal);
            $atsNum = ($atsVal !== '' && is_numeric($atsVal)) ? (float) $atsVal : null;

            $stmtCheckNilai->execute([
                ':sid' => $siswaId,
                ':mapel' => $mapelName,
                ':sem' => $semester,
                ':th' => $tahunAjaran
            ]);
            $existingNilai = $stmtCheckNilai->fetch();

            if ($existingNilai) {
                $s1 = (float) ($existingNilai['sumatif_1'] ?? 0);
                $s2 = (float) ($existingNilai['sumatif_2'] ?? 0);
                $s3 = (float) ($existingNilai['sumatif_3'] ?? 0);
                $s4 = (float) ($existingNilai['sumatif_4'] ?? 0);
                $aas = (float) ($existingNilai['aas'] ?? 0);
                $ats = ($atsNum !== null) ? $atsNum : (float) ($existingNilai['ats'] ?? 0);

                $sumArr = array_filter([$s1, $s2, $s3, $s4], fn($v) => $v > 0);
                $rSumatif = !empty($sumArr) ? (array_sum($sumArr) / count($sumArr)) : 0.0;

                if ($rSumatif > 0 || $aas > 0) {
                    $na = ($rSumatif * 0.80) + ($ats * 0.10) + ($aas * 0.10);
                } else {
                    $na = $ats;
                }
                $na = round($na, 2);

                if ($na >= 90)
                    $pred = 'A';
                elseif ($na >= 80)
                    $pred = 'B';
                elseif ($na >= 70)
                    $pred = 'C';
                elseif ($na > 0)
                    $pred = 'D';
                else
                    $pred = '-';

                $stmtUpdateNilai->execute([
                    ':ats' => $atsNum,
                    ':na' => ($na > 0 ? $na : null),
                    ':pred' => ($pred !== '-' ? $pred : null),
                    ':uid' => $userId,
                    ':id' => $existingNilai['id']
                ]);
            } else {
                if ($atsNum !== null && $atsNum > 0) {
                    $na = $atsNum;
                    if ($na >= 90)
                        $pred = 'A';
                    elseif ($na >= 80)
                        $pred = 'B';
                    elseif ($na >= 70)
                        $pred = 'C';
                    else
                        $pred = 'D';

                    $stmtInsertNilai->execute([
                        ':sid' => $siswaId,
                        ':kid' => (int) $siswa['kelas_id'],
                        ':mapel' => $mapelName,
                        ':sem' => $semester,
                        ':th' => $tahunAjaran,
                        ':ats' => $atsNum,
                        ':na' => $na,
                        ':pred' => $pred,
                        ':uid' => $userId
                    ]);
                }
            }
        }
    }

    try {
        $stmtUpsert = $pdo->prepare("
            INSERT INTO rapor_siswa 
            (siswa_id, kelas_id, tipe_rapor, semester, tahun_ajaran, fase, sakit, izin, tanpa_keterangan, 
             catatan_wali_kelas, tanggapan_ortu, kokurikuler_deskripsi, ekskul_json, deskripsi_mapel_json, 
             wali_kelas_nama, wali_kelas_nip, kepsek_nama, kepsek_nip, tanggal_rapor, tempat_terbit)
            VALUES 
            (:sid, :kid, :tipe, :sem, :th, :fase, :sakit, :izin, :alpa, 
             :cat, :tang, :koku, :ekskul, :desk, 
             :wnama, :wnip, :knama, :knip, :tgl, :tempat)
            ON DUPLICATE KEY UPDATE
            fase = VALUES(fase),
            sakit = VALUES(sakit),
            izin = VALUES(izin),
            tanpa_keterangan = VALUES(tanpa_keterangan),
            catatan_wali_kelas = VALUES(catatan_wali_kelas),
            tanggapan_ortu = VALUES(tanggapan_ortu),
            kokurikuler_deskripsi = VALUES(kokurikuler_deskripsi),
            ekskul_json = VALUES(ekskul_json),
            deskripsi_mapel_json = VALUES(deskripsi_mapel_json),
            wali_kelas_nama = VALUES(wali_kelas_nama),
            wali_kelas_nip = VALUES(wali_kelas_nip),
            kepsek_nama = VALUES(kepsek_nama),
            kepsek_nip = VALUES(kepsek_nip),
            tanggal_rapor = VALUES(tanggal_rapor),
            tempat_terbit = VALUES(tempat_terbit)
        ");

        $stmtUpsert->execute([
            ':sid' => $siswaId,
            ':kid' => (int) $siswa['kelas_id'],
            ':tipe' => $tipeRapor,
            ':sem' => $semester,
            ':th' => $tahunAjaran,
            ':fase' => $fase,
            ':sakit' => $sakit,
            ':izin' => $izin,
            ':alpa' => $alpa,
            ':cat' => $catatanWali,
            ':tang' => $tanggapanOrtu,
            ':koku' => $kokurikuler,
            ':ekskul' => $ekskulJson,
            ':desk' => $deskripsiJson,
            ':wnama' => $waliNama,
            ':wnip' => $waliNip,
            ':knama' => $kepsekNama,
            ':knip' => $kepsekNip,
            ':tgl' => $tglRapor,
            ':tempat' => $tempatTerbit
        ]);

        redirect("edit.php?siswa_id={$siswaId}&tipe={$tipeRapor}&semester={$semester}&tahun_ajaran=" . urlencode($tahunAjaran), 'success', "Data Rapor {$siswa['nama']} ({$tipeRapor}) berhasil disimpan.");
    } catch (PDOException $e) {
        $errorMsg = 'Gagal menyimpan rapor: ' . $e->getMessage();
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid px-0 mb-5">
    <!-- NAVIGASI ATAS -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <a href="index.php?kelas_id=<?= $siswa['kelas_id'] ?>&tipe=<?= $tipeRapor ?>&semester=<?= $semester ?>&tahun_ajaran=<?= urlencode($tahunAjaran) ?>"
                class="btn btn-sm btn-outline-secondary rounded-pill px-3 mb-2">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Rapor
            </a>
            <h4 class="fw-extrabold text-dark mb-0">
                Pengisian Rapor: <span class="text-primary"><?= htmlspecialchars($siswa['nama']) ?></span>
            </h4>
            <p class="text-muted small mb-0">
                Kelas: <strong><?= htmlspecialchars($siswa['nama_kelas']) ?></strong> | Tipe: <span
                    class="badge bg-primary px-2.5 py-1 text-uppercase"><?= $tipeRapor ?></span> | Semester:
                <strong><?= $semester ?></strong> | Tahun: <strong><?= htmlspecialchars($tahunAjaran) ?></strong>
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="cetak.php?siswa_id=<?= $siswaId ?>&tipe=<?= $tipeRapor ?>&semester=<?= $semester ?>&tahun_ajaran=<?= urlencode($tahunAjaran) ?>"
                target="_blank"
                class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm d-inline-flex align-items-center gap-2">
                <i class="bi bi-printer-fill"></i> Cetak / Pratinjau Rapor PDF
            </a>
        </div>
    </div>

    <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($errorMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" id="formRapor">
        <input type="hidden" name="action" value="save_rapor">

        <!-- 1. TABEL NILAI & CAPAIAN KOMPETENSI -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-4">
            <div
                class="card-header bg-white p-3.5 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h5 class="fw-extrabold text-dark mb-0"><i class="bi bi-award text-primary me-2"></i> Capaian Hasil
                        Belajar <?= $isAts ? '(Nilai ATS)' : '(Nilai & Deskripsi)' ?></h5>
                    <p class="text-muted small mb-0">
                        <?= $isAts ? 'Nilai Asesmen Tengah Semester (ATS) dapat diubah langsung oleh Wali Kelas dan otomatis tersinkron ke cetak rapor serta rekap nilai.' : 'Nilai & deskripsi otomatis tersinkron dari modul Input Nilai Guru. Wali kelas dapat menyesuaikan jika diperlukan.' ?>
                    </p>
                </div>
                <?php if (!$isAts): ?>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold"
                            onclick="syncTeacherDescriptions()">
                            <i class="bi bi-arrow-repeat me-1"></i> Tarik Deskripsi Guru
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-3 fw-bold"
                            onclick="autoGenerateAllDescriptions()">
                            <i class="bi bi-magic me-1"></i> Auto-Generate Default
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0" style="border-color: #f1f5f9;">
                        <thead class="bg-light text-muted small fw-bold text-uppercase">
                            <tr>
                                <th style="width: 45px;" class="text-center">No</th>
                                <th style="width: <?= $isAts ? 'auto' : '260px' ?>;">Mata Pelajaran</th>
                                <th style="width: 140px;" class="text-center">
                                    <?= $isAts ? 'Nilai ATS (Edit)' : 'Nilai Akhir (Kompilasi)' ?>
                                </th>
                                <th style="width: 100px;" class="text-center">Predikat</th>
                                <?php if (!$isAts): ?>
                                    <th>Capaian Kompetensi (Deskripsi Rapor Kurikulum Merdeka)</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($nilaiList as $idx => $nl):
                                $mName = $nl['nama_mapel'];
                                if ($isAts) {
                                    // Rapor ATS: Murni nilai ATS saja (> 0 dianggap sudah dinilai)
                                    $na = ($nl['ats'] !== null && $nl['ats'] !== '' && (float) $nl['ats'] > 0) ? (float) $nl['ats'] : null;
                                    $predikat = ($na !== null && $na > 0) ? ($na >= 90 ? 'A' : ($na >= 80 ? 'B' : ($na >= 70 ? 'C' : 'D'))) : '-';
                                    $guruDesk = !empty($nl['deskripsi_ats']) ? trim($nl['deskripsi_ats']) : '';
                                } else {
                                    // Rapor AAS: Kompilasi semua nilai (> 0 dianggap sudah dinilai)
                                    $na = ($nl['nilai_akhir'] !== null && $nl['nilai_akhir'] !== '' && (float) $nl['nilai_akhir'] > 0) ? (float) $nl['nilai_akhir'] : null;
                                    $predikat = !empty($nl['predikat']) ? $nl['predikat'] : (($na !== null && $na > 0) ? ($na >= 90 ? 'A' : ($na >= 80 ? 'B' : ($na >= 70 ? 'C' : 'D'))) : '-');
                                    $guruDesk = !empty($nl['deskripsi_aas']) ? trim($nl['deskripsi_aas']) : '';
                                }

                                // Ambil deskripsi: Utamakan langsung dari deskripsi guru pengampu jika sudah diinput oleh guru
                                if (!empty($guruDesk)) {
                                    $currentDesk = $guruDesk;
                                } elseif (!empty($savedDeskripsiMapel[$mName])) {
                                    $currentDesk = trim($savedDeskripsiMapel[$mName]);
                                } elseif ($na !== null && $na > 0) {
                                    if ($na >= 85) {
                                        $currentDesk = "Menunjukkan penguasaan yang sangat baik dalam memahami materi pokok " . $mName . ", mampu menerapkan konsep secara mandiri dan bernalar kritis.";
                                    } elseif ($na >= 75) {
                                        $currentDesk = "Menunjukkan penguasaan yang baik dalam memahami konsep dasar " . $mName . ", perlu pemantapan pada beberapa indikator capaian.";
                                    } else {
                                        $currentDesk = "Cukup memahami materi pokok " . $mName . ", membutuhkan bimbingan dan pendampingan lebih lanjut untuk penguatan kompetensi.";
                                    }
                                } else {
                                    $currentDesk = "Telah mengikuti pembelajaran mata pelajaran " . $mName . " dengan baik.";
                                }
                                ?>
                                <tr>
                                    <td class="text-center fw-bold text-muted font-monospace"><?= $idx + 1 ?></td>
                                    <td>
                                        <strong class="text-dark d-block"><?= htmlspecialchars($mName) ?></strong>
                                        <small class="text-muted"><?= htmlspecialchars($nl['kelompok'] ?: 'Umum') ?></small>
                                        <?php if (!$isAts): ?>
                                            <?php if (!empty($guruDesk)): ?>
                                                <span
                                                    class="badge bg-success bg-opacity-10 text-success border border-success-subtle rounded-pill small mt-1"
                                                    style="font-size: 0.72rem;">
                                                    <i class="bi bi-check-circle-fill me-1"></i> Sinkron dari Guru Pengampu
                                                </span>
                                            <?php else: ?>
                                                <span
                                                    class="badge bg-secondary bg-opacity-10 text-muted border rounded-pill small mt-1"
                                                    style="font-size: 0.7rem;">
                                                    <i class="bi bi-robot me-1"></i> Template Standar
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center" style="<?= $isAts ? 'width: 140px;' : '' ?>">
                                        <?php if ($isAts): ?>
                                            <div class="input-group input-group-sm justify-content-center mx-auto"
                                                style="max-width: 110px;">
                                                <input type="number" step="0.1" min="0" max="100"
                                                    name="nilai_ats[<?= htmlspecialchars($mName) ?>]"
                                                    value="<?= $na !== null ? (float) $na : '' ?>"
                                                    class="form-control form-control-sm text-center font-monospace fw-bold bg-white text-primary border-primary border-opacity-50 ats-input-score shadow-none"
                                                    placeholder="0 - 100" data-idx="<?= $idx ?>"
                                                    oninput="updatePredikatAts(this, 'predikat_<?= $idx ?>')">
                                            </div>
                                        <?php else: ?>
                                            <?php if ($na !== null && $na > 0): ?>
                                                <span
                                                    class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle fs-6 font-monospace px-3 py-1">
                                                    <?= number_format($na, 1) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary bg-opacity-10 text-muted px-2 py-1">Belum ada</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center fw-bold font-monospace" id="predikat_<?= $idx ?>">
                                        <?= htmlspecialchars($predikat) ?>
                                    </td>
                                    <?php if (!$isAts): ?>
                                        <td>
                                            <textarea name="deskripsi_mapel[<?= htmlspecialchars($mName) ?>]"
                                                id="desk_<?= $idx ?>" class="form-control form-control-sm deskripsi-textarea"
                                                rows="2" data-mapel="<?= htmlspecialchars($mName) ?>"
                                                data-na="<?= $na !== null ? $na : 75 ?>"
                                                data-gurudesk="<?= htmlspecialchars($guruDesk) ?>"
                                                placeholder="Tuliskan deskripsi capaian kompetensi..."><?= htmlspecialchars($currentDesk) ?></textarea>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 2. KOKURIKULER & EKSTRAKURIKULER (HANYA UNTUK AAS / PAS) -->
        <?php if (!$isAts): ?>
            <div class="row g-4 mb-4">
                <!-- PROJEK KOKURIKULER P5 -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                        <div class="card-header bg-white p-3.5 border-bottom">
                            <h6 class="fw-extrabold text-dark mb-0"><i class="bi bi-stars text-warning me-2"></i> Projek
                                Kokurikuler (P5)</h6>
                            <small class="text-muted">Capaian penguatan karakter dan profil pelajar Pancasila</small>
                        </div>
                        <div class="card-body p-3.5">
                            <textarea name="kokurikuler_deskripsi" class="form-control" rows="5"
                                placeholder="Tuliskan deskripsi ketercapaian projek kokurikuler..."><?= htmlspecialchars($rapor['kokurikuler_deskripsi'] ?? $defaultKokurikuler) ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- EKSTRAKURIKULER -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                        <div
                            class="card-header bg-white p-3.5 border-bottom d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="fw-extrabold text-dark mb-0"><i class="bi bi-trophy text-success me-2"></i>
                                    Kegiatan Ekstrakurikuler</h6>
                                <small class="text-muted">Kegiatan minat bakat dan pengembangan diri santri</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-2.5"
                                onclick="addEkskulRow()">
                                <i class="bi bi-plus-lg me-1"></i> Tambah
                            </button>
                        </div>
                        <div class="card-body p-3.5">
                            <datalist id="listEkskulPreset">
                                <option value="Bela Diri">
                                <option value="Tahfidz Al-Qur'an">
                                <option value="Pramuka">
                                <option value="Panahan">
                                <option value="Futsal">
                                <option value="Bulu Tangkis">
                                <option value="Robotik">
                                <option value="Jurnalistik">
                                <option value="Desain & Multimedia">
                                <option value="Renang">
                                <option value="Kaligrafi">
                            </datalist>
                            <div id="ekskulContainer">
                                <?php if (empty($savedEkskul)): ?>
                                    <div class="row g-2 mb-2 ekskul-row">
                                        <div class="col-4">
                                            <input type="text" name="ekskul_nama[]" class="form-control form-control-sm"
                                                list="listEkskulPreset" placeholder="Nama Ekskul (Pilih/Ketik)">
                                        </div>
                                        <div class="col-3">
                                            <select name="ekskul_predikat[]" class="form-select form-select-sm">
                                                <option value="A">A (Sangat Baik)</option>
                                                <option value="B" selected>B (Baik)</option>
                                                <option value="C">C (Cukup)</option>
                                                <option value="D">D (Kurang)</option>
                                            </select>
                                        </div>
                                        <div class="col-4">
                                            <input type="text" name="ekskul_keterangan[]" class="form-control form-control-sm"
                                                placeholder="Keterangan pencapaian">
                                        </div>
                                        <div class="col-1 text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger p-1"
                                                onclick="this.closest('.ekskul-row').remove()"><i class="bi bi-x"></i></button>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($savedEkskul as $ek): 
                                        $curPred = trim($ek['predikat'] ?? 'B');
                                        $isA = ($curPred === 'A' || $curPred === 'Sangat Baik');
                                        $isB = ($curPred === 'B' || $curPred === 'Baik');
                                        $isC = ($curPred === 'C' || $curPred === 'Cukup');
                                        $isD = ($curPred === 'D' || $curPred === 'Kurang');
                                    ?>
                                        <div class="row g-2 mb-2 ekskul-row">
                                            <div class="col-4">
                                                <input type="text" name="ekskul_nama[]" class="form-control form-control-sm"
                                                    list="listEkskulPreset" value="<?= htmlspecialchars($ek['nama'] ?? '') ?>" placeholder="Nama Ekskul (Pilih/Ketik)">
                                            </div>
                                            <div class="col-3">
                                                <select name="ekskul_predikat[]" class="form-select form-select-sm">
                                                    <option value="A" <?= $isA ? 'selected' : '' ?>>A (Sangat Baik)</option>
                                                    <option value="B" <?= $isB ? 'selected' : '' ?>>B (Baik)</option>
                                                    <option value="C" <?= $isC ? 'selected' : '' ?>>C (Cukup)</option>
                                                    <option value="D" <?= $isD ? 'selected' : '' ?>>D (Kurang)</option>
                                                </select>
                                            </div>
                                            <div class="col-4">
                                                <input type="text" name="ekskul_keterangan[]" class="form-control form-control-sm"
                                                    value="<?= htmlspecialchars($ek['keterangan'] ?? '') ?>"
                                                    placeholder="Keterangan">
                                            </div>
                                            <div class="col-1 text-center">
                                                <button type="button" class="btn btn-sm btn-outline-danger p-1"
                                                    onclick="this.closest('.ekskul-row').remove()"><i class="bi bi-x"></i></button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($isAts): ?>
            <!-- LAYOUT ATS: PRESENSI & TITIMANGSA SAJA -->
            <div class="row g-4 mb-4">
                <!-- KETIDAKHADIRAN -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                        <div class="card-header bg-white p-3.5 border-bottom">
                            <h6 class="fw-extrabold text-dark mb-0"><i class="bi bi-calendar-check text-info me-2"></i>
                                Ketidakhadiran (Presensi)</h6>
                            <small class="text-muted">Rekapitulasi absensi santri selama tengah semester</small>
                        </div>
                        <div class="card-body p-3.5">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td style="width: 45%; vertical-align: middle;"><strong>1. Sakit (S)</strong></td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="sakit" class="form-control font-monospace" min="0"
                                                value="<?= (int) ($rapor['sakit'] ?? 0) ?>">
                                            <span class="input-group-text bg-light">Hari</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle;"><strong>2. Izin (I)</strong></td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="izin" class="form-control font-monospace" min="0"
                                                value="<?= (int) ($rapor['izin'] ?? 0) ?>">
                                            <span class="input-group-text bg-light">Hari</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle;"><strong>3. Tanpa Keterangan (A)</strong></td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="tanpa_keterangan" class="form-control font-monospace"
                                                min="0" value="<?= (int) ($rapor['tanpa_keterangan'] ?? 0) ?>">
                                            <span class="input-group-text bg-light">Hari</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TANGGAL & PENANDATANGAN -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                        <div class="card-header bg-white p-3.5 border-bottom">
                            <h6 class="fw-extrabold text-dark mb-0"><i class="bi bi-pen-fill text-secondary me-2"></i>
                                Titimangsa & Penandatangan</h6>
                            <small class="text-muted">Konfigurasi tanggal dan pejabat penandatangan rapor</small>
                        </div>
                        <div class="card-body p-3.5">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-bold text-muted mb-1">Tempat Titimangsa</label>
                                    <input type="text" name="tempat_terbit" class="form-control form-control-sm"
                                        value="<?= htmlspecialchars($rapor['tempat_terbit'] ?? $defaultTempat) ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold text-muted mb-1">Tanggal Terbit</label>
                                    <input type="date" name="tanggal_rapor" class="form-control form-control-sm"
                                        value="<?= htmlspecialchars($rapor['tanggal_rapor'] ?? $defaultTanggal) ?>">
                                </div>
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small fw-bold text-muted mb-1">Nama Wali Kelas</label>
                                    <?php
                                    $dispWaliNama = (!empty($rapor['wali_kelas_nama']) && $rapor['wali_kelas_nama'] !== 'Wali Kelas') ? $rapor['wali_kelas_nama'] : ($siswa['nama_wali_kelas'] ?? '');
                                    $dispWaliNip = (!empty($rapor['wali_kelas_nip']) && $rapor['wali_kelas_nip'] !== '-') ? $rapor['wali_kelas_nip'] : ($siswa['nip_wali_kelas'] ?? '');
                                    ?>
                                    <input type="text" name="wali_kelas_nama" class="form-control form-control-sm"
                                        value="<?= htmlspecialchars($dispWaliNama) ?>">
                                    <input type="text" name="wali_kelas_nip" class="form-control form-control-sm mt-1"
                                        placeholder="NIP Wali Kelas" value="<?= htmlspecialchars($dispWaliNip) ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold text-muted mb-1">Nama Kepala Sekolah</label>
                                    <input type="text" name="kepsek_nama" class="form-control form-control-sm"
                                        value="<?= htmlspecialchars($rapor['kepsek_nama'] ?? $defaultKepsekNama) ?>">
                                    <input type="text" name="kepsek_nip" class="form-control form-control-sm mt-1"
                                        placeholder="NIP Kepala Sekolah"
                                        value="<?= htmlspecialchars($rapor['kepsek_nip'] ?? $defaultKepsekNip) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- LAYOUT AAS / PAS: PRESENSI, CATATAN WALI KELAS, TANGGAPAN ORTU & TITIMANGSA -->
            <div class="row g-4 mb-4">
                <!-- KETIDAKHADIRAN -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                        <div class="card-header bg-white p-3.5 border-bottom">
                            <h6 class="fw-extrabold text-dark mb-0"><i class="bi bi-calendar-check text-info me-2"></i>
                                Ketidakhadiran (Presensi)</h6>
                            <small class="text-muted">Rekapitulasi absensi santri selama satu semester</small>
                        </div>
                        <div class="card-body p-3.5">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td style="width: 45%; vertical-align: middle;"><strong>1. Sakit (S)</strong></td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="sakit" class="form-control font-monospace" min="0"
                                                value="<?= (int) ($rapor['sakit'] ?? 0) ?>">
                                            <span class="input-group-text bg-light">Hari</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle;"><strong>2. Izin (I)</strong></td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="izin" class="form-control font-monospace" min="0"
                                                value="<?= (int) ($rapor['izin'] ?? 0) ?>">
                                            <span class="input-group-text bg-light">Hari</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align: middle;"><strong>3. Tanpa Keterangan (A)</strong></td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="tanpa_keterangan" class="form-control font-monospace"
                                                min="0" value="<?= (int) ($rapor['tanpa_keterangan'] ?? 0) ?>">
                                            <span class="input-group-text bg-light">Hari</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- CATATAN WALI KELAS -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                        <div
                            class="card-header bg-white p-3.5 border-bottom d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="fw-extrabold text-dark mb-0"><i
                                        class="bi bi-chat-quote-fill text-primary me-2"></i> Catatan Wali Kelas</h6>
                                <small class="text-muted">Apresiasi, motivasi, serta saran pengembangan untuk santri</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2.5"
                                onclick="setTemplateCatatan()">
                                <i class="bi bi-magic me-1"></i> Template
                            </button>
                        </div>
                        <div class="card-body p-3.5">
                            <textarea name="catatan_wali_kelas" id="catatanWaliText" class="form-control" rows="4"
                                placeholder="Tuliskan catatan dan motivasi wali kelas..."><?= htmlspecialchars($rapor['catatan_wali_kelas'] ?? "Alhamdulillah, ananda menunjukkan akhlak yang mulia, kedisiplinan belajar yang baik, serta keaktifan dalam program tahfidz. Pertahankan dan terus tingkatkan prestasimu!") ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. TANGGAPAN ORANG TUA / WALI & PENGATURAN TANDA TANGAN -->
            <div class="row g-4 mb-4">
                <!-- TANGGAPAN ORTU -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                        <div class="card-header bg-white p-3.5 border-bottom">
                            <h6 class="fw-extrabold text-dark mb-0"><i class="bi bi-person-heart text-danger me-2"></i>
                                Tanggapan Orang Tua / Wali Murid</h6>
                            <small class="text-muted">Kotak isian feedback dari orang tua santri (bisa diisi langsung / saat
                                penyerahan rapor)</small>
                        </div>
                        <div class="card-body p-3.5">
                            <textarea name="tanggapan_ortu" class="form-control" rows="3"
                                placeholder="Isian komentar orang tua (opsional / bisa dikosongkan untuk diisi manual)..."><?= htmlspecialchars($rapor['tanggapan_ortu'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- TANGGAL & PENANDATANGAN -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                        <div class="card-header bg-white p-3.5 border-bottom">
                            <h6 class="fw-extrabold text-dark mb-0"><i class="bi bi-pen-fill text-secondary me-2"></i>
                                Titimangsa & Penandatangan</h6>
                            <small class="text-muted">Konfigurasi tanggal dan pejabat penandatangan rapor</small>
                        </div>
                        <div class="card-body p-3.5">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-bold text-muted mb-1">Tempat Titimangsa</label>
                                    <input type="text" name="tempat_terbit" class="form-control form-control-sm"
                                        value="<?= htmlspecialchars($rapor['tempat_terbit'] ?? $defaultTempat) ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold text-muted mb-1">Tanggal Terbit</label>
                                    <input type="date" name="tanggal_rapor" class="form-control form-control-sm"
                                        value="<?= htmlspecialchars($rapor['tanggal_rapor'] ?? $defaultTanggal) ?>">
                                </div>
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small fw-bold text-muted mb-1">Nama Wali Kelas</label>
                                    <?php
                                    $dispWaliNama = (!empty($rapor['wali_kelas_nama']) && $rapor['wali_kelas_nama'] !== 'Wali Kelas') ? $rapor['wali_kelas_nama'] : ($siswa['nama_wali_kelas'] ?? '');
                                    $dispWaliNip = (!empty($rapor['wali_kelas_nip']) && $rapor['wali_kelas_nip'] !== '-') ? $rapor['wali_kelas_nip'] : ($siswa['nip_wali_kelas'] ?? '');
                                    ?>
                                    <input type="text" name="wali_kelas_nama" class="form-control form-control-sm"
                                        value="<?= htmlspecialchars($dispWaliNama) ?>">
                                    <input type="text" name="wali_kelas_nip" class="form-control form-control-sm mt-1"
                                        placeholder="NIP Wali Kelas" value="<?= htmlspecialchars($dispWaliNip) ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold text-muted mb-1">Nama Kepala Sekolah</label>
                                    <input type="text" name="kepsek_nama" class="form-control form-control-sm"
                                        value="<?= htmlspecialchars($rapor['kepsek_nama'] ?? $defaultKepsekNama) ?>">
                                    <input type="text" name="kepsek_nip" class="form-control form-control-sm mt-1"
                                        placeholder="NIP Kepala Sekolah"
                                        value="<?= htmlspecialchars($rapor['kepsek_nip'] ?? $defaultKepsekNip) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- STICKY ACTION FOOTER -->
        <div class="card border-0 shadow-lg rounded-4 bg-white p-3 d-flex flex-row align-items-center justify-content-between position-sticky bottom-0 z-3"
            style="border: 1px solid #e2e8f0!important;">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-dark border px-3 py-2">
                    <i class="bi bi-info-circle text-primary me-1"></i> Data rapor tersimpan per semester & tahun
                    ajaran.
                </span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="index.php?kelas_id=<?= $siswa['kelas_id'] ?>&tipe=<?= $tipeRapor ?>&semester=<?= $semester ?>&tahun_ajaran=<?= urlencode($tahunAjaran) ?>"
                    class="btn btn-light rounded-pill px-4 fw-bold">
                    Batal
                </a>
                <button type="submit"
                    class="btn btn-primary rounded-pill px-4.5 py-2 fw-bold shadow-sm d-inline-flex align-items-center gap-2">
                    <i class="bi bi-check2-circle fs-5"></i> Simpan Rapor Siswa
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    function updatePredikatAts(input, predId) {
        const val = parseFloat(input.value);
        const predEl = document.getElementById(predId);
        if (!predEl) return;
        if (isNaN(val) || val <= 0) {
            predEl.innerText = '-';
        } else if (val >= 90) {
            predEl.innerText = 'A';
        } else if (val >= 80) {
            predEl.innerText = 'B';
        } else if (val >= 70) {
            predEl.innerText = 'C';
        } else {
            predEl.innerText = 'D';
        }
    }

    function syncTeacherDescriptions() {
        let syncedCount = 0;
        document.querySelectorAll('.deskripsi-textarea').forEach(ta => {
            const guruDesk = (ta.dataset.gurudesk || '').trim();
            if (guruDesk !== '') {
                ta.value = guruDesk;
                syncedCount++;
            }
        });
        if (syncedCount > 0) {
            alert(`Berhasil menarik ${syncedCount} deskripsi capaian dari guru pengampu mata pelajaran.`);
        } else {
            alert('Belum ada deskripsi yang diinput oleh guru pengampu mata pelajaran untuk santri ini. Guru dapat mengisinya melalui menu Input Nilai Guru > Tab Deskripsi Rapor.');
        }
    }

    function autoGenerateAllDescriptions() {
        if (confirm('Apakah Anda ingin memperbarui seluruh deskripsi capaian kompetensi secara otomatis berdasarkan nilai masing-masing mata pelajaran?')) {
            document.querySelectorAll('.deskripsi-textarea').forEach(ta => {
                const mapel = ta.dataset.mapel;
                const na = parseFloat(ta.dataset.na) || 75;
                let desk = '';
                if (na >= 85) {
                    desk = `Menunjukkan penguasaan yang sangat baik dalam memahami materi pokok ${mapel}, mampu menerapkan konsep secara mandiri dan bernalar kritis.`;
                } else if (na >= 75) {
                    desk = `Menunjukkan penguasaan yang baik dalam memahami konsep dasar ${mapel}, perlu pemantapan pada beberapa indikator capaian.`;
                } else {
                    desk = `Cukup memahami materi pokok ${mapel}, membutuhkan bimbingan dan pendampingan lebih lanjut untuk penguatan kompetensi dasar.`;
                }
                ta.value = desk;
            });
        }
    }

    function setTemplateCatatan() {
        const templates = [
            "Alhamdulillah, ananda menunjukkan akhlak yang mulia, kedisiplinan belajar yang baik, serta keaktifan dalam program tahfidz. Pertahankan dan terus tingkatkan prestasimu!",
            "Ananda memiliki potensi akademik dan kepemimpinan yang sangat baik. Terus tingkatkan ketekunan belajar dan perluas wawasan ilmu pengetahuan.",
            "Ananda menunjukkan perkembangan karakter yang positif dan aktif dalam kegiatan santri. Perlu sedikit peningkatan fokus belajar untuk meraih prestasi yang lebih gemilang."
        ];
        const picked = templates[Math.floor(Math.random() * templates.length)];
        document.getElementById('catatanWaliText').value = picked;
    }

    function addEkskulRow() {
        const container = document.getElementById('ekskulContainer');
        const div = document.createElement('div');
        div.className = 'row g-2 mb-2 ekskul-row';
        div.innerHTML = `
        <div class="col-4">
            <input type="text" name="ekskul_nama[]" class="form-control form-control-sm" list="listEkskulPreset" placeholder="Nama Ekskul (Pilih/Ketik)">
        </div>
        <div class="col-3">
            <select name="ekskul_predikat[]" class="form-select form-select-sm">
                <option value="A">A (Sangat Baik)</option>
                <option value="B" selected>B (Baik)</option>
                <option value="C">C (Cukup)</option>
                <option value="D">D (Kurang)</option>
            </select>
        </div>
        <div class="col-4">
            <input type="text" name="ekskul_keterangan[]" class="form-control form-control-sm" placeholder="Keterangan">
        </div>
        <div class="col-1 text-center">
            <button type="button" class="btn btn-sm btn-outline-danger p-1" onclick="this.closest('.ekskul-row').remove()"><i class="bi bi-x"></i></button>
        </div>
    `;
        container.appendChild(div);
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>