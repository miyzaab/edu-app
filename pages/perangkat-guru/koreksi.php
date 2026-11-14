<?php
/**
 * MEJA KOREKSI CEPAT & REKAP NILAI OTOMATIS
 * Islamic School of Minhaj Al-Ilmi
 * - Auto-Calculate Skor PG, Isian, Essay (0 - 100)
 * - Keyboard Fast-Navigation (Enter / ArrowDown)
 * - Checklist Kunci Soal PG
 * - 1-Klik Sinkronisasi ke Modul Nilai & Rapor Siswa
 */
$pageTitle = 'Meja Koreksi Cepat Ujian - Perangkat Guru';
$activePage = 'perangkat-guru';
$activeModule = 'akademik';

require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_guru');

$pdo = getConnection();
require_once __DIR__ . '/init_soal.php';

$naskahId = (int) ($_GET['id'] ?? 0);
if ($naskahId <= 0) {
    header('Location: index.php');
    exit;
}

// 1. Ambil Data Naskah Soal
$stmt = $pdo->prepare("SELECT * FROM soal_naskah WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $naskahId]);
$naskah = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$naskah) {
    die('Naskah soal tidak ditemukan.');
}

// 2. Ambil Butir Soal & Hitung Bobot Maksimal
$stmtSoal = $pdo->prepare("SELECT * FROM soal_butir WHERE naskah_id = :nid ORDER BY FIELD(tipe_soal, 'pg', 'isian', 'essay'), nomor_urut ASC");
$stmtSoal->execute([':nid' => $naskahId]);
$allSoal = $stmtSoal->fetchAll(PDO::FETCH_ASSOC);

$soalPg = [];
$soalIsian = [];
$soalEssay = [];

$sumPgPoin = 0.0;
$sumIsianPoin = 0.0;
$sumEssayPoin = 0.0;

foreach ($allSoal as $s) {
    $p = (float) $s['poin'];
    if ($s['tipe_soal'] === 'pg') {
        $soalPg[] = $s;
        $sumPgPoin += $p;
    } elseif ($s['tipe_soal'] === 'isian') {
        $soalIsian[] = $s;
        $sumIsianPoin += $p;
    } else {
        $soalEssay[] = $s;
        $sumEssayPoin += $p;
    }
}

$countPg = count($soalPg);
$countIsian = count($soalIsian);
$countEssay = count($soalEssay);

$totalPoinMaksimal = $sumPgPoin + $sumIsianPoin + $sumEssayPoin;
if ($totalPoinMaksimal <= 0) {
    $totalPoinMaksimal = (float) $naskah['total_poin'];
}
$poinPerPg = ($countPg > 0 && $sumPgPoin > 0) ? ($sumPgPoin / $countPg) : 0;

// 3. Ambil Daftar Kelas yang Tersedia
$tingkatFilter = trim($naskah['tingkat_kelas'] ?? '');
$cleanTingkat = preg_replace('/[^0-9]/', '', $tingkatFilter);
if ($tingkatFilter === 'VII') $cleanTingkat = '7';
elseif ($tingkatFilter === 'VIII') $cleanTingkat = '8';
elseif ($tingkatFilter === 'IX') $cleanTingkat = '9';

$kelases = $pdo->query("SELECT * FROM kelas ORDER BY tingkat ASC, nama_kelas ASC")->fetchAll(PDO::FETCH_ASSOC);

// Tentukan kelas yang terpilih (prioritaskan kelas yang cocok dengan tingkat kelas naskah)
$selectedKelasId = (int) ($_GET['kelas_id'] ?? 0);
if ($selectedKelasId <= 0) {
    foreach ($kelases as $k) {
        if ($cleanTingkat !== '' && ((string)$k['tingkat'] === $cleanTingkat || stripos($k['nama_kelas'], $tingkatFilter) !== false)) {
            $selectedKelasId = (int) $k['id'];
            break;
        }
    }
    if ($selectedKelasId <= 0 && !empty($kelases)) {
        $selectedKelasId = (int) $kelases[0]['id'];
    }
}

// 4. PROSES SIMPAN KOREKSI / SINKRONISASI KE RAPOR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    $postKelasId = (int) ($_POST['kelas_id'] ?? $selectedKelasId);
    $siswaIds = $_POST['siswa_id'] ?? [];
    $benarPgArr = $_POST['benar_pg'] ?? [];
    $skorPgArr = $_POST['skor_pg'] ?? [];
    $skorIsianArr = $_POST['skor_isian'] ?? [];
    $skorEssayArr = $_POST['skor_essay'] ?? [];
    $nilaiAkhirArr = $_POST['nilai_akhir'] ?? [];
    $catatanArr = $_POST['catatan'] ?? [];
    $detailPgArr = $_POST['detail_pg'] ?? [];

    $countSaved = 0;

    $stmtUpsertKoreksi = $pdo->prepare("
        INSERT INTO soal_hasil_koreksi 
        (naskah_id, siswa_id, kelas_id, benar_pg, skor_pg, skor_isian, skor_essay, nilai_akhir, catatan, detail_pg)
        VALUES 
        (:nid, :sid, :kid, :bpg, :spg, :sisian, :sessay, :na, :cat, :det)
        ON DUPLICATE KEY UPDATE
        kelas_id = VALUES(kelas_id),
        benar_pg = VALUES(benar_pg),
        skor_pg = VALUES(skor_pg),
        skor_isian = VALUES(skor_isian),
        skor_essay = VALUES(skor_essay),
        nilai_akhir = VALUES(nilai_akhir),
        catatan = VALUES(catatan),
        detail_pg = VALUES(detail_pg)
    ");

    foreach ($siswaIds as $idx => $sid) {
        $sid = (int) $sid;
        $bpg = (int) ($benarPgArr[$idx] ?? 0);
        $spg = (float) ($skorPgArr[$idx] ?? 0);
        $sisian = (float) ($skorIsianArr[$idx] ?? 0);
        $sessay = (float) ($skorEssayArr[$idx] ?? 0);
        $na = (float) ($nilaiAkhirArr[$idx] ?? 0);
        $cat = trim($catArr[$idx] ?? '');
        $det = trim($detailPgArr[$idx] ?? '');

        $stmtUpsertKoreksi->execute([
            ':nid' => $naskahId,
            ':sid' => $sid,
            ':kid' => $postKelasId,
            ':bpg' => $bpg,
            ':spg' => $spg,
            ':sisian' => $sisian,
            ':sessay' => $sessay,
            ':na' => $na,
            ':cat' => $cat,
            ':det' => $det
        ]);
        $countSaved++;
    }

    // JIKA USER MEMILIH "SIMPAN & SINKRONKAN KE NILAI RAPOR"
    if ($postAction === 'save_and_sync') {
        $tipeUjian = strtoupper($naskah['tipe_ujian'] ?? 'AAS');
        $isAts = ($tipeUjian === 'ATS' || $tipeUjian === 'PTS');
        $mapelName = trim($naskah['nama_mapel']);
        $sem = trim($naskah['semester']);
        $th = trim($naskah['tahun_ajaran']);
        $userId = (int) ($_SESSION['user_id'] ?? 1);

        $stmtUpsertNilai = $pdo->prepare("
            INSERT INTO nilai_siswa 
            (siswa_id, kelas_id, mapel, semester, tahun_ajaran, sumatif_1, sumatif_2, sumatif_3, sumatif_4, ats, aas, nilai_akhir, predikat, catatan, user_id)
            VALUES 
            (:sid, :kid, :mapel, :sem, :th, 0, 0, 0, 0, :ats, :aas, :na, :pred, :cat, :uid)
            ON DUPLICATE KEY UPDATE
            " . ($isAts ? "ats = VALUES(ats)" : "aas = VALUES(aas)") . ",
            nilai_akhir = VALUES(nilai_akhir),
            predikat = VALUES(predikat),
            user_id = VALUES(user_id)
        ");

        foreach ($siswaIds as $idx => $sid) {
            $sid = (int) $sid;
            $naUjian = (float) ($nilaiAkhirArr[$idx] ?? 0);

            // Ambil data nilai siswa eksisting untuk kalkulasi pembobotan nilai akhir rapor
            $stmtEx = $pdo->prepare("SELECT * FROM nilai_siswa WHERE siswa_id = :sid AND kelas_id = :kid AND mapel = :mapel AND semester = :sem AND tahun_ajaran = :th LIMIT 1");
            $stmtEx->execute([':sid' => $sid, ':kid' => $postKelasId, ':mapel' => $mapelName, ':sem' => $sem, ':th' => $th]);
            $existing = $stmtEx->fetch(PDO::FETCH_ASSOC);

            $s1 = (float) ($existing['sumatif_1'] ?? 0);
            $s2 = (float) ($existing['sumatif_2'] ?? 0);
            $s3 = (float) ($existing['sumatif_3'] ?? 0);
            $s4 = (float) ($existing['sumatif_4'] ?? 0);
            $currentAts = $isAts ? $naUjian : (float) ($existing['ats'] ?? 0);
            $currentAas = !$isAts ? $naUjian : (float) ($existing['aas'] ?? 0);

            // Formula Pembobotan Kurikulum Merdeka SMP ISMI: Sumatif 80%, ATS 10%, AAS 10%
            $sumArr = array_filter([$s1, $s2, $s3, $s4], fn($v) => $v > 0);
            $rSumatif = !empty($sumArr) ? (array_sum($sumArr) / count($sumArr)) : $naUjian;
            $naRapor = ($rSumatif * 0.80) + ($currentAts * 0.10) + ($currentAas * 0.10);
            $naRapor = round($naRapor, 2);

            if ($naRapor >= 90) $pred = 'A';
            elseif ($naRapor >= 80) $pred = 'B';
            elseif ($naRapor >= 70) $pred = 'C';
            else $pred = 'D';

            $stmtUpsertNilai->execute([
                ':sid' => $sid,
                ':kid' => $postKelasId,
                ':mapel' => $mapelName,
                ':sem' => $sem,
                ':th' => $th,
                ':ats' => $currentAts,
                ':aas' => $currentAas,
                ':na' => $naRapor,
                ':pred' => $pred,
                ':cat' => trim($catatanArr[$idx] ?? ''),
                ':uid' => $userId
            ]);
        }

        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => "Alhamdulillah! Nilai {$countSaved} santri berhasil disimpan dan otomatis disinkronkan ke Modul Nilai & Rapor Siswa!"
        ];
    } else {
        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => "Alhamdulillah! Data koreksi ujian {$countSaved} santri berhasil disimpan."
        ];
    }

    header("Location: koreksi.php?id={$naskahId}&kelas_id={$postKelasId}");
    exit;
}

// 5. Ambil Daftar Santri & Data Koreksi Tersimpan
$listSiswa = [];
if ($selectedKelasId > 0) {
    $stmtSiswa = $pdo->prepare("
        SELECT 
            s.id AS siswa_id, s.nis, s.nama,
            hk.benar_pg, hk.skor_pg, hk.skor_isian, hk.skor_essay, hk.nilai_akhir, hk.catatan, hk.detail_pg,
            ns.aas AS nilai_aas_rapor, ns.ats AS nilai_ats_rapor
        FROM siswa s
        LEFT JOIN soal_hasil_koreksi hk ON s.id = hk.siswa_id AND hk.naskah_id = :nid
        LEFT JOIN nilai_siswa ns ON s.id = ns.siswa_id 
            AND ns.kelas_id = s.kelas_id 
            AND ns.mapel = :mapel 
            AND ns.semester = :sem 
            AND ns.tahun_ajaran = :th
        WHERE s.kelas_id = :kid AND (s.status IS NULL OR s.status = 'aktif')
        ORDER BY s.nama ASC
    ");
    $stmtSiswa->execute([
        ':nid' => $naskahId,
        ':mapel' => $naskah['nama_mapel'],
        ':sem' => $naskah['semester'],
        ':th' => $naskah['tahun_ajaran'],
        ':kid' => $selectedKelasId
    ]);
    $listSiswa = $stmtSiswa->fetchAll(PDO::FETCH_ASSOC);
}

// 6. Hitung Statistik Ringkas Kelas (Live Summary)
$statTerkoreksi = 0;
$statTotalNilai = 0;
$statNilaiMax = 0;
$statNilaiMin = 100;
$statTuntas = 0;
$statRemedial = 0;

foreach ($listSiswa as $s) {
    if ($s['nilai_akhir'] !== null) {
        $val = (float) $s['nilai_akhir'];
        $statTerkoreksi++;
        $statTotalNilai += $val;
        if ($val > $statNilaiMax) $statNilaiMax = $val;
        if ($val < $statNilaiMin) $statNilaiMin = $val;
        if ($val >= 75) $statTuntas++;
        else $statRemedial++;
    }
}
$statRataRata = ($statTerkoreksi > 0) ? round($statTotalNilai / $statTerkoreksi, 1) : 0;
if ($statTerkoreksi === 0) $statNilaiMin = 0;

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/subnav.php';
?>

<!-- FLASH NOTIFIKASI -->
<?php if (isset($_SESSION['flash'])): ?>
    <div class="alert alert-<?= htmlspecialchars($_SESSION['flash']['type']) ?> alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div><?= htmlspecialchars($_SESSION['flash']['message']) ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<!-- HERO BANNER INFORMASI NASKAH -->
<div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 45%, #4338ca 100%); color: white;">
    <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2"
                style="background: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255, 255, 255, 0.25);">
                <i class="bi bi-lightning-charge-fill text-warning"></i>
                <span class="small fw-bold text-white font-monospace">MEJA KOREKSI CEPAT UJIAN (SPEED GRADING)</span>
            </div>
            <h3 class="fw-bold mb-1 text-white">
                <?= htmlspecialchars($naskah['nama_mapel']) ?> • Kelas <?= htmlspecialchars($naskah['tingkat_kelas']) ?>
            </h3>
            <p class="opacity-75 small mb-0">
                <?= htmlspecialchars($naskah['judul_ujian']) ?> | Semester <?= htmlspecialchars($naskah['semester']) ?> TP. <?= htmlspecialchars($naskah['tahun_ajaran']) ?>
            </p>
        </div>

        <!-- BOBOT POIN INFO PILLS -->
        <div class="d-flex flex-wrap gap-2">
            <div class="px-3 py-2 rounded-3 text-center" style="background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2);">
                <div class="text-white-50" style="font-size: 0.72rem; font-weight: bold;">PG (<?= $countPg ?> Soal)</div>
                <div class="fw-bold fs-6 text-warning"><?= (float)$sumPgPoin ?> Poin</div>
            </div>
            <div class="px-3 py-2 rounded-3 text-center" style="background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2);">
                <div class="text-white-50" style="font-size: 0.72rem; font-weight: bold;">ISIAN (<?= $countIsian ?> Soal)</div>
                <div class="fw-bold fs-6 text-info"><?= (float)$sumIsianPoin ?> Poin</div>
            </div>
            <div class="px-3 py-2 rounded-3 text-center" style="background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2);">
                <div class="text-white-50" style="font-size: 0.72rem; font-weight: bold;">ESSAY (<?= $countEssay ?> Soal)</div>
                <div class="fw-bold fs-6 text-success"><?= (float)$sumEssayPoin ?> Poin</div>
            </div>
            <div class="px-3 py-2 rounded-3 text-center bg-white text-dark shadow-sm">
                <div class="text-muted" style="font-size: 0.72rem; font-weight: bold;">TOTAL MAKSIMAL</div>
                <div class="fw-bold fs-6 text-primary"><?= (float)$totalPoinMaksimal ?> Poin</div>
            </div>
        </div>
    </div>
</div>

<!-- PILIHAN KELAS & STATISTIK REALTIME -->
<div class="row g-3 mb-4">
    <!-- FILTER KELAS -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <label class="form-label fw-bold small text-muted mb-2 d-flex align-items-center gap-1.5">
                <i class="bi bi-people-fill text-primary"></i> Pilih Rombongan Belajar (Kelas):
            </label>
            <form method="GET" action="koreksi.php">
                <input type="hidden" name="id" value="<?= $naskahId ?>">
                <div class="input-group">
                    <select name="kelas_id" class="form-select rounded-start-pill ps-3 fw-bold" onchange="this.form.submit()">
                        <?php foreach ($kelases as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= ($selectedKelasId == $k['id']) ? 'selected' : '' ?>>
                                Kelas <?= htmlspecialchars($k['nama_kelas']) ?> (Tingkat <?= htmlspecialchars($k['tingkat']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary rounded-end-pill px-3">
                        <i class="bi bi-arrow-right-short fs-5"></i>
                    </button>
                </div>
            </form>
            <div class="form-text small text-muted mt-2">
                <i class="bi bi-info-circle me-1"></i> Data santri akan otomatis disesuaikan dengan kelas terpilih.
            </div>
        </div>
    </div>

    <!-- STATISTIK LIVE KELAS -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small fw-bold text-muted text-uppercase">Statistik Hasil Koreksi Kelas Ini</span>
                <span class="badge bg-light text-dark border">Progress: <?= $statTerkoreksi ?> / <?= count($listSiswa) ?> Santri</span>
            </div>
            <div class="row g-2 text-center">
                <div class="col-3">
                    <div class="p-2 bg-light rounded-3">
                        <div class="text-muted small" style="font-size: 0.72rem;">RATA-RATA</div>
                        <div class="fs-5 fw-bold text-primary" id="statRataRata"><?= $statRataRata ?></div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 bg-light rounded-3">
                        <div class="text-muted small" style="font-size: 0.72rem;">TERTINGGI</div>
                        <div class="fs-5 fw-bold text-success" id="statTertinggi"><?= $statNilaiMax ?></div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 bg-light rounded-3">
                        <div class="text-muted small" style="font-size: 0.72rem;">TUNTAS (≥75)</div>
                        <div class="fs-5 fw-bold text-success" id="statTuntas"><?= $statTuntas ?></div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 bg-light rounded-3">
                        <div class="text-muted small" style="font-size: 0.72rem;">REMEDIAL (&lt;75)</div>
                        <div class="fs-5 fw-bold text-danger" id="statRemedial"><?= $statRemedial ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FORM MEJA KOREKSI (SPREADSHEET TABLE) -->
<form method="POST" action="koreksi.php?id=<?= $naskahId ?>&kelas_id=<?= $selectedKelasId ?>" id="formKoreksi">
    <input type="hidden" name="action" id="formAction" value="save_koreksi">
    <input type="hidden" name="kelas_id" value="<?= $selectedKelasId ?>">

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-4">
        <!-- TOOLBAR ATAS TABEL -->
        <div class="p-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2 bg-light">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold text-dark"><i class="bi bi-grid-3x3-gap-fill text-primary me-1"></i> Lembar Input Cepat</span>
                <span class="badge bg-secondary rounded-pill font-monospace"><?= count($listSiswa) ?> Santri</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="setSemuaPgPenuh()" title="Atur seluruh santri benar PG maksimal">
                    <i class="bi bi-check-all me-1 text-success"></i> Set Semua PG Penuh
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" onclick="exportTableToCSV()" title="Unduh rekap koreksi ke Excel">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Unduh CSV / Excel
                </button>
            </div>
        </div>

        <!-- PETUNJUK TOMBOL CEPAT KEYBOARD -->
        <div class="bg-primary bg-opacity-10 px-3 py-1.5 border-bottom small text-primary d-flex align-items-center justify-content-between">
            <div>
                <i class="bi bi-keyboard-fill me-1"></i> <strong>Tips Cepat Keyboard:</strong> Tekan <kbd>Enter</kbd> atau <kbd>↓</kbd> untuk langsung pindah ke santri di bawahnya. Tekan <kbd>Tab</kbd> untuk geser antar kolom.
            </div>
            <span class="small font-monospace text-muted">Auto-Calculate Nilai Aktif</span>
        </div>

        <!-- TABEL SPREADSHEET INPUT -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tableKoreksi" style="font-size: 0.9rem;">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width: 45px;">No.</th>
                        <th style="width: 100px;">NIS</th>
                        <th style="min-width: 200px;">Nama Santri</th>
                        <th class="text-center bg-warning bg-opacity-10" style="width: 150px;">
                            Benar PG
                            <span class="d-block text-muted" style="font-size: 0.72rem; font-weight: normal;">Maks: <?= $countPg ?> Soal</span>
                        </th>
                        <th class="text-center" style="width: 95px;">
                            Skor PG
                            <span class="d-block text-muted" style="font-size: 0.72rem; font-weight: normal;">Maks: <?= $sumPgPoin ?></span>
                        </th>
                        <th class="text-center bg-info bg-opacity-10" style="width: 115px;">
                            Skor Isian
                            <span class="d-block text-muted" style="font-size: 0.72rem; font-weight: normal;">Maks: <?= $sumIsianPoin ?></span>
                        </th>
                        <th class="text-center bg-success bg-opacity-10" style="width: 115px;">
                            Skor Essay
                            <span class="d-block text-muted" style="font-size: 0.72rem; font-weight: normal;">Maks: <?= $sumEssayPoin ?></span>
                        </th>
                        <th class="text-center bg-light" style="width: 130px;">
                            Nilai Akhir
                            <span class="d-block text-muted" style="font-size: 0.72rem; font-weight: normal;">Skala 100</span>
                        </th>
                        <th style="min-width: 160px;">Catatan / Evaluasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($listSiswa)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-people display-6 d-block mb-2 opacity-50"></i>
                                Tidak ada data santri aktif pada kelas yang dipilih.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($listSiswa as $idx => $s): 
                            $bPg = $s['benar_pg'] !== null ? (int)$s['benar_pg'] : 0;
                            $sPg = $s['skor_pg'] !== null ? (float)$s['skor_pg'] : 0.0;
                            $sIsian = $s['skor_isian'] !== null ? (float)$s['skor_isian'] : 0.0;
                            $sEssay = $s['skor_essay'] !== null ? (float)$s['skor_essay'] : 0.0;
                            $na = $s['nilai_akhir'] !== null ? (float)$s['nilai_akhir'] : 0.0;
                            $cat = $s['catatan'] ?? '';
                            $detPg = $s['detail_pg'] ?? '';
                            $isTuntas = ($na >= 75);
                        ?>
                            <tr id="row-<?= $idx ?>" class="koreksi-row">
                                <td class="text-center text-muted fw-bold"><?= $idx + 1 ?></td>
                                <td class="font-monospace small text-muted"><?= htmlspecialchars($s['nis'] ?: '-') ?></td>
                                <td>
                                    <div class="fw-bold text-dark student-name"><?= htmlspecialchars($s['nama']) ?></div>
                                    <input type="hidden" name="siswa_id[]" value="<?= $s['siswa_id'] ?>">
                                    <input type="hidden" name="detail_pg[]" id="detail_pg_<?= $idx ?>" value="<?= htmlspecialchars($detPg) ?>">
                                </td>

                                <!-- 1. INPUT BENAR PG -->
                                <td class="bg-warning bg-opacity-10 text-center">
                                    <div class="input-group input-group-sm">
                                        <input type="number" name="benar_pg[]" id="bpg_<?= $idx ?>" 
                                            class="form-control text-center fw-bold input-koreksi cell-bpg" 
                                            value="<?= $bPg ?>" min="0" max="<?= $countPg ?>" step="1"
                                            data-idx="<?= $idx ?>" oninput="recalcRow(<?= $idx ?>)" onkeydown="handleKeyNav(event, <?= $idx ?>, 'bpg')">
                                        <?php if ($countPg > 0): ?>
                                            <button type="button" class="btn btn-outline-warning text-dark px-2" title="Buka Checklist Kunci PG" onclick="openChecklistModal(<?= $idx ?>)">
                                                <i class="bi bi-ui-checks"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- 2. SKOR PG (AUTO) -->
                                <td class="text-center">
                                    <input type="number" name="skor_pg[]" id="spg_<?= $idx ?>" 
                                        class="form-control form-control-sm text-center fw-bold bg-light cell-spg" 
                                        value="<?= $sPg ?>" readonly style="border: none;">
                                </td>

                                <!-- 3. INPUT SKOR ISIAN -->
                                <td class="bg-info bg-opacity-10 text-center">
                                    <input type="number" name="skor_isian[]" id="sisian_<?= $idx ?>" 
                                        class="form-control form-control-sm text-center fw-bold input-koreksi cell-sisian" 
                                        value="<?= $sIsian ?>" min="0" max="<?= $sumIsianPoin ?>" step="0.5"
                                        data-idx="<?= $idx ?>" oninput="recalcRow(<?= $idx ?>)" onkeydown="handleKeyNav(event, <?= $idx ?>, 'sisian')">
                                </td>

                                <!-- 4. INPUT SKOR ESSAY -->
                                <td class="bg-success bg-opacity-10 text-center">
                                    <input type="number" name="skor_essay[]" id="sessay_<?= $idx ?>" 
                                        class="form-control form-control-sm text-center fw-bold input-koreksi cell-sessay" 
                                        value="<?= $sEssay ?>" min="0" max="<?= $sumEssayPoin ?>" step="0.5"
                                        data-idx="<?= $idx ?>" oninput="recalcRow(<?= $idx ?>)" onkeydown="handleKeyNav(event, <?= $idx ?>, 'sessay')">
                                </td>

                                <!-- 5. NILAI AKHIR (AUTO) -->
                                <td class="text-center bg-light">
                                    <div class="d-flex align-items-center justify-content-center gap-1.5">
                                        <input type="number" name="nilai_akhir[]" id="na_<?= $idx ?>" 
                                            class="form-control form-control-sm text-center fw-bold cell-na" 
                                            value="<?= $na ?>" readonly style="width: 65px; border: none; font-size: 1rem;">
                                        <span id="badge_status_<?= $idx ?>" class="badge <?= $isTuntas ? 'bg-success' : 'bg-warning text-dark' ?> small" style="font-size: 0.72rem;">
                                            <?= $isTuntas ? 'Tuntas' : 'Remedial' ?>
                                        </span>
                                    </div>
                                </td>

                                <!-- 6. CATATAN EVALUASI -->
                                <td>
                                    <input type="text" name="catatan[]" class="form-control form-control-sm" 
                                        placeholder="Catatan evaluasi..." value="<?= htmlspecialchars($cat) ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ACTION FOOTER BAR -->
        <div class="p-3 bg-light border-top d-flex flex-wrap justify-content-between align-items-center gap-3">
            <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Bank Soal
            </a>
            
            <div class="d-flex align-items-center gap-2">
                <button type="submit" onclick="submitFormAction('save_koreksi')" class="btn btn-outline-primary rounded-pill px-4 fw-bold">
                    <i class="bi bi-floppy-fill me-1.5"></i> Simpan Hasil Koreksi
                </button>
                <button type="submit" onclick="submitFormAction('save_and_sync')" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm"
                    style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none;">
                    <i class="bi bi-cloud-arrow-up-fill me-1.5"></i> Simpan &amp; Sinkronkan ke Rapor
                </button>
            </div>
        </div>
    </div>
</form>

<!-- MODAL INTERAKTIF: CHECKLIST KUNCI SOAL PG -->
<div class="modal fade" id="modalChecklistPg" tabindex="-1" aria-labelledby="modalChecklistPgLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header text-white px-4 py-3" style="background: linear-gradient(135deg, #1e1b4b 0%, #3730a3 100%);">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="modalChecklistPgLabel">
                        <i class="bi bi-ui-checks me-2 text-warning"></i> Checklist Jawaban PG Santri
                    </h5>
                    <small class="text-white-50" id="modalStudentName">Santri: -</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-light border rounded-3 p-2.5 small mb-3 d-flex align-items-center justify-content-between">
                    <div>
                        <i class="bi bi-info-circle-fill text-primary me-1"></i> Klik nomor yang <strong class="text-danger">SALAH</strong> untuk menonaktifkannya. Warna hijau = BENAR.
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-xs btn-outline-success rounded-pill px-2" onclick="setAllModalKeys(true)">Semua Benar</button>
                        <button type="button" class="btn btn-xs btn-outline-danger rounded-pill px-2" onclick="setAllModalKeys(false)">Semua Salah</button>
                    </div>
                </div>

                <!-- GRID BUTTONS NOMOR SOAL -->
                <div class="row g-2" id="gridModalSoal">
                    <!-- Populated by Javascript -->
                </div>

                <!-- COUNTER RINGKAS -->
                <div class="p-3 bg-light rounded-3 border mt-4 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small">Total Soal PG: <strong><?= $countPg ?></strong></span>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="text-success fw-bold">Benar: <span id="modalCounterBenar" class="fs-5">0</span></span>
                        <span class="text-danger fw-bold">Salah: <span id="modalCounterSalah" class="fs-5">0</span></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light px-4 py-3">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="applyModalChecklist()">
                    <i class="bi bi-check2-circle me-1"></i> Terapkan ke Santri
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Master Data Kunci PG
const masterPgSoal = <?= json_encode(array_map(function($s) {
    return [
        'no' => (int) $s['nomor_urut'],
        'kunci' => strtoupper($s['kunci_jawaban'] ?: '-'),
        'poin' => (float) $s['poin']
    ];
}, $soalPg)) ?>;

const totalPgCount = <?= $countPg ?>;
const sumPgPoin = <?= (float) $sumPgPoin ?>;
const poinPerPg = <?= (float) $poinPerPg ?>;
const totalPoinMaksimal = <?= (float) $totalPoinMaksimal ?>;

let activeModalRowIdx = null;
let currentModalState = {}; // no -> true/false

function submitFormAction(action) {
    document.getElementById('formAction').value = action;
}

// Recalculate single row
function recalcRow(idx) {
    const bpgInput = document.getElementById('bpg_' + idx);
    const spgInput = document.getElementById('spg_' + idx);
    const sisianInput = document.getElementById('sisian_' + idx);
    const sessayInput = document.getElementById('sessay_' + idx);
    const naInput = document.getElementById('na_' + idx);
    const badgeStatus = document.getElementById('badge_status_' + idx);

    let bpg = parseInt(bpgInput.value) || 0;
    if (bpg < 0) bpg = 0;
    if (bpg > totalPgCount) bpg = totalPgCount;

    // Hitung Skor PG
    let spg = (poinPerPg > 0) ? (bpg * poinPerPg) : 0;
    if (spg > sumPgPoin) spg = sumPgPoin;
    spgInput.value = spg.toFixed(1);

    let sisian = parseFloat(sisianInput.value) || 0;
    let sessay = parseFloat(sessayInput.value) || 0;

    let skorTotal = spg + sisian + sessay;

    // Nilai akhir skala 100
    let na = 0;
    if (totalPoinMaksimal > 0) {
        na = (skorTotal / totalPoinMaksimal) * 100;
    } else {
        na = skorTotal;
    }
    na = Math.min(100, Math.max(0, na));
    na = Math.round(na * 10) / 10;
    naInput.value = na.toFixed(1);

    if (na >= 75) {
        badgeStatus.className = 'badge bg-success small';
        badgeStatus.innerText = 'Tuntas';
    } else {
        badgeStatus.className = 'badge bg-warning text-dark small';
        badgeStatus.innerText = 'Remedial';
    }

    recalcClassStats();
}

// Recalculate class level statistics
function recalcClassStats() {
    const naInputs = document.querySelectorAll('.cell-na');
    let total = 0;
    let count = 0;
    let max = 0;
    let min = 100;
    let tuntas = 0;
    let remedial = 0;

    naInputs.forEach(input => {
        let val = parseFloat(input.value);
        if (!isNaN(val)) {
            total += val;
            count++;
            if (val > max) max = val;
            if (val < min) min = val;
            if (val >= 75) tuntas++;
            else remedial++;
        }
    });

    let avg = count > 0 ? (total / count).toFixed(1) : '0';
    if (count === 0) min = 0;

    document.getElementById('statRataRata').innerText = avg;
    document.getElementById('statTertinggi').innerText = max.toFixed(1);
    document.getElementById('statTuntas').innerText = tuntas;
    document.getElementById('statRemedial').innerText = remedial;
}

// Fast Keyboard Navigation: Up/Down arrow or Enter jumps to next row
function handleKeyNav(e, idx, fieldPrefix) {
    if (e.key === 'Enter' || e.key === 'ArrowDown') {
        e.preventDefault();
        const nextInput = document.getElementById(fieldPrefix + '_' + (idx + 1));
        if (nextInput) {
            nextInput.focus();
            nextInput.select();
        }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        const prevInput = document.getElementById(fieldPrefix + '_' + (idx - 1));
        if (prevInput) {
            prevInput.focus();
            prevInput.select();
        }
    }
}

// Quick action: set all PG correct
function setSemuaPgPenuh() {
    if (confirm('Atur seluruh santri mendapatkan nilai PG Benar Penuh (Maksimal)?')) {
        const rows = document.querySelectorAll('.cell-bpg');
        rows.forEach(input => {
            input.value = totalPgCount;
            let idx = input.getAttribute('data-idx');
            recalcRow(idx);
        });
    }
}

// CHECKLIST MODAL LOGIC
function openChecklistModal(idx) {
    activeModalRowIdx = idx;
    const studentName = document.querySelectorAll('.student-name')[idx].innerText;
    document.getElementById('modalStudentName').innerText = 'Santri: ' + studentName;

    // Load existing detail or default
    const detInput = document.getElementById('detail_pg_' + idx);
    let detailMap = {};
    if (detInput.value) {
        try {
            detailMap = JSON.parse(detInput.value);
        } catch(e) {}
    }

    currentModalState = {};
    const grid = document.getElementById('gridModalSoal');
    grid.innerHTML = '';

    masterPgSoal.forEach((s, sIdx) => {
        let isCorrect = true;
        if (detailMap && detailMap.hasOwnProperty(s.no)) {
            isCorrect = detailMap[s.no] === 1;
        } else {
            // Default check from bpg input if fresh
            let currentBpg = parseInt(document.getElementById('bpg_' + idx).value) || 0;
            isCorrect = (sIdx < currentBpg);
        }
        currentModalState[s.no] = isCorrect;

        const col = document.createElement('div');
        col.className = 'col-3 col-md-2 text-center';
        col.innerHTML = `
            <button type="button" id="btn_modal_soal_${s.no}" class="btn btn-sm w-100 py-2 rounded-3 fw-bold ${isCorrect ? 'btn-success' : 'btn-outline-danger'}" onclick="toggleModalKey(${s.no})">
                <div style="font-size:0.75rem;">No. ${s.no}</div>
                <div class="fs-6">[ ${s.kunci} ]</div>
            </button>
        `;
        grid.appendChild(col);
    });

    updateModalCounters();
    const modalEl = new bootstrap.Modal(document.getElementById('modalChecklistPg'));
    modalEl.show();
}

function toggleModalKey(no) {
    currentModalState[no] = !currentModalState[no];
    const btn = document.getElementById('btn_modal_soal_' + no);
    if (currentModalState[no]) {
        btn.className = 'btn btn-sm w-100 py-2 rounded-3 fw-bold btn-success';
    } else {
        btn.className = 'btn btn-sm w-100 py-2 rounded-3 fw-bold btn-outline-danger';
    }
    updateModalCounters();
}

function setAllModalKeys(val) {
    masterPgSoal.forEach(s => {
        currentModalState[s.no] = val;
        const btn = document.getElementById('btn_modal_soal_' + s.no);
        if (btn) {
            btn.className = 'btn btn-sm w-100 py-2 rounded-3 fw-bold ' + (val ? 'btn-success' : 'btn-outline-danger');
        }
    });
    updateModalCounters();
}

function updateModalCounters() {
    let benar = 0;
    let salah = 0;
    Object.keys(currentModalState).forEach(k => {
        if (currentModalState[k]) benar++;
        else salah++;
    });
    document.getElementById('modalCounterBenar').innerText = benar;
    document.getElementById('modalCounterSalah').innerText = salah;
}

function applyModalChecklist() {
    if (activeModalRowIdx === null) return;
    let benar = 0;
    const detailToSave = {};
    Object.keys(currentModalState).forEach(k => {
        if (currentModalState[k]) {
            benar++;
            detailToSave[k] = 1;
        } else {
            detailToSave[k] = 0;
        }
    });

    document.getElementById('bpg_' + activeModalRowIdx).value = benar;
    document.getElementById('detail_pg_' + activeModalRowIdx).value = JSON.stringify(detailToSave);
    recalcRow(activeModalRowIdx);

    const modalEl = bootstrap.Modal.getInstance(document.getElementById('modalChecklistPg'));
    if (modalEl) modalEl.hide();
}

// Export Table to CSV
function exportTableToCSV() {
    let csv = [];
    csv.push(['No', 'NIS', 'Nama Santri', 'Benar PG', 'Skor PG', 'Skor Isian', 'Skor Essay', 'Nilai Akhir', 'Status', 'Catatan']);

    const rows = document.querySelectorAll('#tableKoreksi tbody tr.koreksi-row');
    rows.forEach((row, i) => {
        const nis = row.cells[1].innerText.trim();
        const nama = row.querySelector('.student-name').innerText.trim();
        const bpg = row.querySelector('.cell-bpg').value;
        const spg = row.querySelector('.cell-spg').value;
        const sisian = row.querySelector('.cell-sisian').value;
        const sessay = row.querySelector('.cell-sessay').value;
        const na = row.querySelector('.cell-na').value;
        const status = parseFloat(na) >= 75 ? 'Tuntas' : 'Remedial';
        const cat = row.cells[8].querySelector('input').value;

        csv.push([i + 1, `"${nis}"`, `"${nama}"`, bpg, spg, sisian, sessay, na, status, `"${cat}"`]);
    });

    let csvContent = "\uFEFF" + csv.map(e => e.join(",")).join("\n");
    let blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    let link = document.createElement("a");
    let url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    link.setAttribute("download", "Rekap_Nilai_Ujian_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $naskah['nama_mapel']) ?>_<?= date('Ymd') ?>.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
