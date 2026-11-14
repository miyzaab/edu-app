<?php
/**
 * EDITOR BUTIR SOAL UJIAN (PILIHAN GANDA, ISIAN, ESSAY)
 * Terintegrasi Pembobotan Poin & Unggah Gambar
 */
$pageTitle = 'Susun Butir Soal - Perangkat Guru';
$activePage = 'perangkat-guru';
$activeModule = 'akademik';

require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_guru');

$pdo = getConnection();
require_once __DIR__ . '/init_soal.php';
require_once __DIR__ . '/card_template.php';

$naskahId = (int) ($_GET['id'] ?? 0);
if ($naskahId <= 0) {
    header('Location: index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$userRole = strtolower($_SESSION['role'] ?? 'guru');

// Ambil Data Naskah
$stmt = $pdo->prepare("SELECT * FROM soal_naskah WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $naskahId]);
$naskah = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$naskah) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Naskah soal tidak ditemukan.'];
    header('Location: index.php');
    exit;
}

// Cek hak akses
if ($userRole !== 'admin' && $naskah['guru_id'] != $userId) {
    $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Anda hanya dapat mengedit naskah soal yang Anda buat.'];
    header('Location: index.php');
    exit;
}

$uploadDir = __DIR__ . '/../../uploads/soal';

// PROSES SIMPAN DATA (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Update Header Naskah
    $judulUjian = trim($_POST['judul_ujian'] ?? $naskah['judul_ujian']);
    $tipeUjian = trim($_POST['tipe_ujian'] ?? $naskah['tipe_ujian']);
    $tingkatKelas = trim($_POST['tingkat_kelas'] ?? $naskah['tingkat_kelas']);
    $semester = trim($_POST['semester'] ?? $naskah['semester']);
    $tahunAjaran = trim($_POST['tahun_ajaran'] ?? $naskah['tahun_ajaran']);
    $alokasiWaktu = (int) ($_POST['alokasi_waktu'] ?? 90);
    $tanggalUjian = !empty($_POST['tanggal_ujian']) ? $_POST['tanggal_ujian'] : null;
    $petunjukUmum = trim($_POST['petunjuk_umum'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['draft', 'siap_cetak']) ? $_POST['status'] : 'draft';

    $stmtUpdH = $pdo->prepare("
        UPDATE soal_naskah SET 
            judul_ujian = :judul,
            tipe_ujian = :tipe,
            tingkat_kelas = :tingkat,
            semester = :semester,
            tahun_ajaran = :tahun,
            alokasi_waktu = :waktu,
            tanggal_ujian = :tgl,
            petunjuk_umum = :petunjuk,
            status = :status,
            updated_at = NOW()
        WHERE id = :id
    ");
    $stmtUpdH->execute([
        ':judul' => $judulUjian,
        ':tipe' => $tipeUjian,
        ':tingkat' => $tingkatKelas,
        ':semester' => $semester,
        ':tahun' => $tahunAjaran,
        ':waktu' => $alokasiWaktu,
        ':tgl' => $tanggalUjian,
        ':petunjuk' => $petunjukUmum,
        ':status' => $status,
        ':id' => $naskahId
    ]);

    // 2. Simpan / Perbarui Butir Soal yang Ada
    $soalData = $_POST['soal'] ?? [];
    $delImages = $_POST['delete_gambar'] ?? [];

    foreach ($soalData as $soalId => $s) {
        $soalId = (int) $soalId;
        if ($soalId <= 0) continue;

        $pertanyaan = trim($s['pertanyaan'] ?? '');
        $poin = (float) ($s['poin'] ?? 1.0);
        $kunci = trim($s['kunci_jawaban'] ?? '');
        $pilA = isset($s['pilihan_a']) ? trim($s['pilihan_a']) : null;
        $pilB = isset($s['pilihan_b']) ? trim($s['pilihan_b']) : null;
        $pilC = isset($s['pilihan_c']) ? trim($s['pilihan_c']) : null;
        $pilD = isset($s['pilihan_d']) ? trim($s['pilihan_d']) : null;
        $nomorUrut = (int) ($s['nomor_urut'] ?? 1);

        // Cek hapus gambar jika dicentang
        $gambarParam = null;
        $updateGambar = false;

        if (!empty($delImages[$soalId])) {
            $updateGambar = true;
            $gambarParam = null;
        }

        // Cek upload gambar baru
        if (isset($_FILES['gambar_soal']['name'][$soalId]) && $_FILES['gambar_soal']['error'][$soalId] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['gambar_soal']['tmp_name'][$soalId];
            $ext = strtolower(pathinfo($_FILES['gambar_soal']['name'][$soalId], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $newFilename = 'soal_' . $naskahId . '_' . $soalId . '_' . time() . '.' . $ext;
                if (move_uploaded_file($tmpName, $uploadDir . '/' . $newFilename)) {
                    $gambarParam = $newFilename;
                    $updateGambar = true;
                }
            }
        }

        if ($updateGambar) {
            $stmtUpdSoal = $pdo->prepare("
                UPDATE soal_butir SET 
                    nomor_urut = :no,
                    pertanyaan = :tanya,
                    gambar = :gambar,
                    pilihan_a = :pa,
                    pilihan_b = :pb,
                    pilihan_c = :pc,
                    pilihan_d = :pd,
                    kunci_jawaban = :kunci,
                    poin = :poin,
                    updated_at = NOW()
                WHERE id = :sid AND naskah_id = :nid
            ");
            $stmtUpdSoal->execute([
                ':no' => $nomorUrut,
                ':tanya' => $pertanyaan,
                ':gambar' => $gambarParam,
                ':pa' => $pilA,
                ':pb' => $pilB,
                ':pc' => $pilC,
                ':pd' => $pilD,
                ':kunci' => $kunci,
                ':poin' => $poin,
                ':sid' => $soalId,
                ':nid' => $naskahId
            ]);
        } else {
            $stmtUpdSoal = $pdo->prepare("
                UPDATE soal_butir SET 
                    nomor_urut = :no,
                    pertanyaan = :tanya,
                    pilihan_a = :pa,
                    pilihan_b = :pb,
                    pilihan_c = :pc,
                    pilihan_d = :pd,
                    kunci_jawaban = :kunci,
                    poin = :poin,
                    updated_at = NOW()
                WHERE id = :sid AND naskah_id = :nid
            ");
            $stmtUpdSoal->execute([
                ':no' => $nomorUrut,
                ':tanya' => $pertanyaan,
                ':pa' => $pilA,
                ':pb' => $pilB,
                ':pc' => $pilC,
                ':pd' => $pilD,
                ':kunci' => $kunci,
                ':poin' => $poin,
                ':sid' => $soalId,
                ':nid' => $naskahId
            ]);
        }
    }

    // 3. Tambah Butir Soal Baru jika diminta
    $newTipe = trim($_POST['add_tipe'] ?? '');
    if (in_array($newTipe, ['pg', 'isian', 'essay'])) {
        $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(nomor_urut), 0) + 1 FROM soal_butir WHERE naskah_id = :nid");
        $stmtMax->execute([':nid' => $naskahId]);
        $nextNo = (int) $stmtMax->fetchColumn();

        $defaultPoin = ($newTipe === 'pg') ? 2.0 : (($newTipe === 'isian') ? 3.0 : 5.0);
        $stmtIns = $pdo->prepare("
            INSERT INTO soal_butir (naskah_id, nomor_urut, tipe_soal, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, kunci_jawaban, poin)
            VALUES (:nid, :no, :tipe, :tanya, :pa, :pb, :pc, :pd, :kunci, :poin)
        ");
        $stmtIns->execute([
            ':nid' => $naskahId,
            ':no' => $nextNo,
            ':tipe' => $newTipe,
            ':tanya' => 'Tuliskan teks pertanyaan di sini...',
            ':pa' => ($newTipe === 'pg') ? 'Pilihan A' : null,
            ':pb' => ($newTipe === 'pg') ? 'Pilihan B' : null,
            ':pc' => ($newTipe === 'pg') ? 'Pilihan C' : null,
            ':pd' => ($newTipe === 'pg') ? 'Pilihan D' : null,
            ':kunci' => ($newTipe === 'pg') ? 'A' : '',
            ':poin' => $defaultPoin
        ]);
    }

    // 4. Hitung Ulang Total Poin & Soal
    rehitungPoinNaskah($naskahId, $pdo);

    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => 'Naskah soal berhasil disimpan!'
    ];

    header('Location: edit.php?id=' . $naskahId . (!empty($newTipe) ? '#section_' . $newTipe : ''));
    exit;
}

// Ambil Butir Soal per Kategori
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

// Re-fetch naskah info terbaru
$stmt->execute([':id' => $naskahId]);
$naskah = $stmt->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/subnav.php';
?>

<!-- FLASH NOTIFICATION -->
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

<!-- TOP ACTION & MONITOR BAR -->
<div class="card border-0 shadow-sm rounded-4 mb-4 bg-white sticky-top" style="top: 15px; z-index: 1020; border: 1px solid #e2e8f0 !important;">
    <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <div>
                <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($naskah['nama_mapel']) ?> (Kelas <?= htmlspecialchars($naskah['tingkat_kelas']) ?>)</h6>
                <div class="text-muted small" style="font-size: 0.8rem;">
                    <?= htmlspecialchars($naskah['judul_ujian']) ?> • <?= htmlspecialchars($naskah['semester']) ?> <?= htmlspecialchars($naskah['tahun_ajaran']) ?>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center flex-wrap gap-2">
            <!-- LIVE COUNTER TOTAL POIN -->
            <div class="px-3 py-1.5 rounded-pill d-flex align-items-center gap-2 shadow-xs" 
                style="background: #eef2ff; border: 1px solid #c7d2fe;">
                <i class="bi bi-award-fill text-primary"></i>
                <span class="small fw-bold text-muted">Total Poin:</span>
                <span class="fw-extrabold text-primary font-monospace fs-6" id="liveTotalPoinDisplay"><?= (float)$naskah['total_poin'] ?></span>
                <span class="small text-muted">/ 100</span>
            </div>

            <button type="button" onclick="openPasteModal('pg')" class="btn btn-sm btn-outline-dark rounded-pill px-3 fw-bold shadow-xs">
                <i class="bi bi-lightning-charge-fill text-warning me-1"></i> Paste Soal Cepat
            </button>
            <a href="koreksi.php?id=<?= $naskahId ?>" class="btn btn-sm btn-warning text-dark rounded-pill px-3 fw-bold">
                <i class="bi bi-lightning-charge-fill text-danger me-1"></i> Koreksi Cepat
            </a>
            <a href="print.php?id=<?= $naskahId ?>" target="_blank" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold">
                <i class="bi bi-printer-fill me-1"></i> Cetak / PDF
            </a>
            <a href="export_word.php?id=<?= $naskahId ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                <i class="bi bi-file-earmark-word-fill me-1"></i> Unduh Word (.doc)
            </a>
            <a href="print_kunci.php?id=<?= $naskahId ?>" target="_blank" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold">
                <i class="bi bi-key-fill me-1"></i> Kunci Jawaban
            </a>
            <button type="button" onclick="simpanNaskahForm()" class="btn btn-sm btn-primary rounded-pill px-4 fw-bold shadow-sm" id="btnSimpanTop">
                <i class="bi bi-floppy-fill me-1"></i> Simpan Perubahan
            </button>
        </div>
    </div>
</div>

<form id="formSoalEditor" action="edit.php?id=<?= $naskahId ?>" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="add_tipe" id="addTipeInput" value="">

    <!-- INFORMASI UMUM & IDENTITAS NASKAH -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center"
            data-bs-toggle="collapse" data-bs-target="#collapseIdentitas" style="cursor: pointer;">
            <h6 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-sliders text-primary"></i> Identitas &amp; Pengaturan Dokumen Soal
            </h6>
            <i class="bi bi-chevron-down text-muted"></i>
        </div>
        <div class="collapse show" id="collapseIdentitas">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">Target Kolom Penilaian <span class="text-danger">*</span></label>
                        <select name="tipe_ujian" class="form-select fw-bold">
                            <option value="sumatif_1" <?= $naskah['tipe_ujian'] === 'sumatif_1' ? 'selected' : '' ?>>Sumatif 1 (Lingkup Materi 1)</option>
                            <option value="sumatif_2" <?= $naskah['tipe_ujian'] === 'sumatif_2' ? 'selected' : '' ?>>Sumatif 2 (Lingkup Materi 2)</option>
                            <option value="sumatif_3" <?= $naskah['tipe_ujian'] === 'sumatif_3' ? 'selected' : '' ?>>Sumatif 3 (Lingkup Materi 3)</option>
                            <option value="sumatif_4" <?= $naskah['tipe_ujian'] === 'sumatif_4' ? 'selected' : '' ?>>Sumatif 4 (Lingkup Materi 4)</option>
                            <option value="ats" <?= strtolower($naskah['tipe_ujian']) === 'ats' ? 'selected' : '' ?>>ATS (Tengah Semester / PTS)</option>
                            <option value="aas" <?= strtolower($naskah['tipe_ujian']) === 'aas' ? 'selected' : '' ?>>AAS (Akhir Semester / PAS)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">Judul Dokumen Ujian</label>
                        <input type="text" name="judul_ujian" class="form-control" value="<?= htmlspecialchars($naskah['judul_ujian']) ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small text-muted">Tingkat Kelas</label>
                        <select name="tingkat_kelas" class="form-select">
                            <option value="VII" <?= $naskah['tingkat_kelas'] === 'VII' ? 'selected' : '' ?>>Kelas VII</option>
                            <option value="VIII" <?= $naskah['tingkat_kelas'] === 'VIII' ? 'selected' : '' ?>>Kelas VIII</option>
                            <option value="IX" <?= $naskah['tingkat_kelas'] === 'IX' ? 'selected' : '' ?>>Kelas IX</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small text-muted">Semester</label>
                        <select name="semester" class="form-select">
                            <option value="Ganjil" <?= $naskah['semester'] === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                            <option value="Genap" <?= $naskah['semester'] === 'Genap' ? 'selected' : '' ?>>Genap</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small text-muted">Tahun Ajaran</label>
                        <input type="text" name="tahun_ajaran" class="form-control font-monospace" value="<?= htmlspecialchars($naskah['tahun_ajaran']) ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small text-muted">Status Naskah</label>
                        <select name="status" class="form-select fw-bold <?= $naskah['status'] === 'siap_cetak' ? 'text-success' : 'text-secondary' ?>">
                            <option value="draft" <?= $naskah['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="siap_cetak" <?= $naskah['status'] === 'siap_cetak' ? 'selected' : '' ?>>Siap Cetak</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">Alokasi Waktu (Menit)</label>
                        <div class="input-group">
                            <input type="number" name="alokasi_waktu" class="form-control" value="<?= (int)$naskah['alokasi_waktu'] ?>" required>
                            <span class="input-group-text">Menit</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">Tanggal Pelaksanaan (Opsional)</label>
                        <input type="date" name="tanggal_ujian" class="form-control" value="<?= htmlspecialchars($naskah['tanggal_ujian'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BAGIAN A: PILIHAN GANDA (A - D) -->
    <!-- ================================================================ -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden" id="section_pg">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                    <span class="badge bg-primary rounded-circle" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;">A</span>
                    <span>BAGIAN I: PILIHAN GANDA (A – D)</span>
                </h5>
                <p class="text-muted small mb-0 mt-1">Pilih satu jawaban yang paling tepat (A, B, C, atau D)</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" onclick="openPasteModal('pg')" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold">
                    <i class="bi bi-clipboard2-plus me-1 text-primary"></i> Paste Soal Word
                </button>
                <div class="btn-group">
                    <button type="button" onclick="tambahSoalAjax('pg', 1)" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold" id="btn_add_pg">
                        <i class="bi bi-plus-circle me-1"></i> + Tambah Soal PG
                    </button>
                    <button type="button" class="btn btn-sm btn-primary dropdown-toggle dropdown-toggle-split rounded-pill rounded-start-0" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="visually-hidden">Pilihan Batch</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm rounded-3">
                        <li><h6 class="dropdown-header">Tambah Banyak Sekaligus</h6></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="tambahSoalAjax('pg', 5)"><i class="bi bi-plus-square text-primary me-2"></i> + 5 Soal PG</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="tambahSoalAjax('pg', 10)"><i class="bi bi-plus-square-fill text-primary me-2"></i> + 10 Soal PG</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="promptBatchAdd('pg')"><i class="bi bi-input-cursor text-muted me-2"></i> Jumlah Kustom...</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="card-body p-4">
            <div id="empty_pg" class="text-center py-4 text-muted <?= empty($soalPg) ? '' : 'd-none' ?>">
                <i class="bi bi-ui-checks display-6 d-block mb-2 text-muted"></i>
                Belum ada butir soal pilihan ganda. Klik <strong>+ Tambah Soal PG</strong> atau <strong>Paste Soal Word</strong> untuk memulai.
            </div>

            <div id="container_pg">
                <?php foreach ($soalPg as $idx => $s): ?>
                    <?= renderSoalCard($s, $idx + 1, $naskahId) ?>
                <?php endforeach; ?>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 pt-2 border-top">
                <button type="button" onclick="openPasteModal('pg')" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                    <i class="bi bi-clipboard2-plus me-1"></i> Paste Soal PG dari Teks/Word
                </button>
                <div class="btn-group">
                    <button type="button" onclick="tambahSoalAjax('pg', 1)" class="btn btn-outline-primary rounded-pill px-4 fw-bold">
                        <i class="bi bi-plus-circle me-1"></i> + Tambah Butir Soal PG
                    </button>
                    <button type="button" class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split rounded-pill rounded-start-0" data-bs-toggle="dropdown">
                        <span class="visually-hidden">Dropdown</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm rounded-3">
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="tambahSoalAjax('pg', 5)">+ 5 Butir Sekaligus</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="tambahSoalAjax('pg', 10)">+ 10 Butir Sekaligus</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="promptBatchAdd('pg')">Jumlah Kustom...</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BAGIAN B: ISIAN SINGKAT -->
    <!-- ================================================================ -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden" id="section_isian">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                    <span class="badge bg-teal rounded-circle text-white" style="background: #0d9488; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;">B</span>
                    <span>BAGIAN II: ISIAN SINGKAT</span>
                </h5>
                <p class="text-muted small mb-0 mt-1">Isilah titik-titik di bawah ini dengan jawaban yang benar dan singkat</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" onclick="openPasteModal('isian')" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold">
                    <i class="bi bi-clipboard2-plus me-1 text-teal" style="color: #0d9488;"></i> Paste Soal Isian
                </button>
                <div class="btn-group">
                    <button type="button" onclick="tambahSoalAjax('isian', 1)" class="btn btn-sm rounded-pill px-3 fw-bold text-white shadow-xs" style="background: #0d9488;" id="btn_add_isian">
                        <i class="bi bi-plus-circle me-1"></i> + Tambah Soal Isian
                    </button>
                    <button type="button" class="btn btn-sm dropdown-toggle dropdown-toggle-split rounded-pill rounded-start-0 text-white" style="background: #0d9488;" data-bs-toggle="dropdown">
                        <span class="visually-hidden">Pilihan Batch</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm rounded-3">
                        <li><h6 class="dropdown-header">Tambah Banyak Sekaligus</h6></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="tambahSoalAjax('isian', 5)"><i class="bi bi-plus-square text-teal me-2" style="color: #0d9488;"></i> + 5 Soal Isian</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="tambahSoalAjax('isian', 10)"><i class="bi bi-plus-square-fill text-teal me-2" style="color: #0d9488;"></i> + 10 Soal Isian</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="promptBatchAdd('isian')"><i class="bi bi-input-cursor text-muted me-2"></i> Jumlah Kustom...</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="card-body p-4">
            <div id="empty_isian" class="text-center py-4 text-muted <?= empty($soalIsian) ? '' : 'd-none' ?>">
                <i class="bi bi-input-cursor-text display-6 d-block mb-2 text-muted"></i>
                Belum ada butir soal isian singkat. Klik <strong>+ Tambah Soal Isian</strong> untuk menambahkan.
            </div>

            <div id="container_isian">
                <?php foreach ($soalIsian as $idx => $s): ?>
                    <?= renderSoalCard($s, $idx + 1, $naskahId) ?>
                <?php endforeach; ?>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 pt-2 border-top">
                <button type="button" onclick="openPasteModal('isian')" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                    <i class="bi bi-clipboard2-plus me-1"></i> Paste Soal Isian dari Teks
                </button>
                <div class="btn-group">
                    <button type="button" onclick="tambahSoalAjax('isian', 1)" class="btn rounded-pill px-4 fw-bold text-white shadow-sm" style="background: #0d9488;">
                        <i class="bi bi-plus-circle me-1"></i> + Tambah Butir Soal Isian
                    </button>
                    <button type="button" class="btn dropdown-toggle dropdown-toggle-split rounded-pill rounded-start-0 text-white" style="background: #0d9488;" data-bs-toggle="dropdown">
                        <span class="visually-hidden">Dropdown</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm rounded-3">
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="tambahSoalAjax('isian', 5)">+ 5 Butir Sekaligus</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="tambahSoalAjax('isian', 10)">+ 10 Butir Sekaligus</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="promptBatchAdd('isian')">Jumlah Kustom...</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BAGIAN C: ESSAY / URAIAN -->
    <!-- ================================================================ -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden" id="section_essay">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                    <span class="badge bg-warning rounded-circle text-dark" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;">C</span>
                    <span>BAGIAN III: URAIAN / ESSAY</span>
                </h5>
                <p class="text-muted small mb-0 mt-1">Jawablah pertanyaan-pertanyaan di bawah ini secara jelas, lengkap, dan sistematis</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" onclick="openPasteModal('essay')" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold">
                    <i class="bi bi-clipboard2-plus me-1 text-warning"></i> Paste Soal Essay
                </button>
                <div class="btn-group">
                    <button type="button" onclick="tambahSoalAjax('essay', 1)" class="btn btn-sm btn-warning text-dark rounded-pill px-3 fw-bold shadow-xs" id="btn_add_essay">
                        <i class="bi bi-plus-circle me-1"></i> + Tambah Soal Essay
                    </button>
                    <button type="button" class="btn btn-sm btn-warning text-dark dropdown-toggle dropdown-toggle-split rounded-pill rounded-start-0" data-bs-toggle="dropdown">
                        <span class="visually-hidden">Pilihan Batch</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm rounded-3">
                        <li><h6 class="dropdown-header">Tambah Banyak Sekaligus</h6></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="tambahSoalAjax('essay', 5)"><i class="bi bi-plus-square text-warning me-2"></i> + 5 Soal Essay</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="tambahSoalAjax('essay', 10)"><i class="bi bi-plus-square-fill text-warning me-2"></i> + 10 Soal Essay</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="promptBatchAdd('essay')"><i class="bi bi-input-cursor text-muted me-2"></i> Jumlah Kustom...</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="card-body p-4">
            <div id="empty_essay" class="text-center py-4 text-muted <?= empty($soalEssay) ? '' : 'd-none' ?>">
                <i class="bi bi-card-text display-6 d-block mb-2 text-muted"></i>
                Belum ada butir soal uraian/essay. Klik <strong>+ Tambah Soal Essay</strong> untuk menambahkan.
            </div>

            <div id="container_essay">
                <?php foreach ($soalEssay as $idx => $s): ?>
                    <?= renderSoalCard($s, $idx + 1, $naskahId) ?>
                <?php endforeach; ?>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 pt-2 border-top">
                <button type="button" onclick="openPasteModal('essay')" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                    <i class="bi bi-clipboard2-plus me-1"></i> Paste Soal Essay dari Teks
                </button>
                <div class="btn-group">
                    <button type="button" onclick="tambahSoalAjax('essay', 1)" class="btn btn-warning rounded-pill px-4 fw-bold text-dark shadow-sm">
                        <i class="bi bi-plus-circle me-1"></i> + Tambah Butir Soal Essay
                    </button>
                    <button type="button" class="btn btn-warning text-dark dropdown-toggle dropdown-toggle-split rounded-pill rounded-start-0" data-bs-toggle="dropdown">
                        <span class="visually-hidden">Dropdown</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm rounded-3">
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="tambahSoalAjax('essay', 5)">+ 5 Butir Sekaligus</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="tambahSoalAjax('essay', 10)">+ 10 Butir Sekaligus</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="promptBatchAdd('essay')">Jumlah Kustom...</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- BOTTOM FLOATING ACTION -->
    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-5 d-flex flex-row justify-content-between align-items-center">
        <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="bi bi-arrow-left me-1"></i> Selesai &amp; Kembali
        </a>
        <button type="button" onclick="simpanNaskahForm()" class="btn btn-primary rounded-pill px-5 py-2.5 fw-bold shadow-sm" id="btnSimpanBottom">
            <i class="bi bi-floppy-fill me-1.5"></i> Simpan Seluruh Naskah Soal
        </button>
    </div>
</form>

<!-- MODAL PASTE SOAL CEPAT (DARI WORD / CATATAN) -->
<div class="modal fade" id="modalPasteSoal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom py-3 px-4 bg-light">
                <div class="d-flex align-items-center gap-2.5">
                    <span class="badge bg-warning text-dark rounded-circle p-2 shadow-xs" style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="bi bi-lightning-charge-fill"></i>
                    </span>
                    <div>
                        <h6 class="modal-title fw-bold mb-0 text-dark">Paste Soal Cepat (Dari Word / Catatan Teks)</h6>
                        <small class="text-muted">Ekstrak otomatis teks pertanyaan, opsi pilihan A-D, dan kunci jawaban tanpa refresh</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Kategori Bagian Target:</label>
                        <select class="form-select fw-bold" id="pasteTipeSelect">
                            <option value="pg" selected>Bagian I: Pilihan Ganda (A – D)</option>
                            <option value="isian">Bagian II: Isian Singkat</option>
                            <option value="essay">Bagian III: Uraian / Essay</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Contoh Format yang Didukung:</label>
                        <div class="alert alert-info py-2 px-3 small mb-0 font-monospace" style="font-size: 0.74rem;">
                            1. Pertanyaan soal...<br>
                            A. Pilihan A &nbsp; B. Pilihan B<br>
                            C. Pilihan C &nbsp; D. Pilihan D<br>
                            Kunci: A
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-dark">Paste Teks Soal di Bawah Ini:</label>
                    <textarea class="form-control font-monospace" id="pasteRawText" rows="11" 
                        placeholder="Tempel (paste) kumpulan butir soal dari file Microsoft Word atau Notepad Anda di sini..."></textarea>
                </div>
                <div class="text-muted small">
                    <i class="bi bi-info-circle me-1 text-primary"></i> Sistem otomatis mendeteksi nomor soal, opsi A-D, dan baris kunci jawaban (misal: <code>Kunci: C</code>).
                </div>
            </div>
            <div class="modal-footer border-top py-3 px-4 bg-light">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" id="btnDoPaste" onclick="prosesPasteSoal()">
                    <i class="bi bi-cloud-arrow-down-fill me-1.5"></i> Ekstrak &amp; Tambahkan Soal Sekarang
                </button>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(15px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.soal-card {
    transition: all 0.25s ease;
}
.soal-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.06) !important;
}
</style>

<script>
// =========================================================================
// 1. TAMBAH BUTIR SOAL SECARA INSTAN VIA AJAX (ZERO PAGE RELOAD)
// =========================================================================
function tambahSoalAjax(tipe, count = 1) {
    const btn = document.getElementById('btn_add_' + tipe);
    let origHtml = '';
    if (btn) {
        origHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menambahkan...';
        btn.disabled = true;
    }

    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('naskah_id', '<?= $naskahId ?>');
    formData.append('tipe', tipe);
    formData.append('count', count);

    fetch('api_soal.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (btn) {
            btn.innerHTML = origHtml;
            btn.disabled = false;
        }

        if (!data.success) {
            alert(data.message || 'Gagal menambahkan butir soal.');
            return;
        }

        const container = document.getElementById('container_' + tipe);
        const emptyState = document.getElementById('empty_' + tipe);
        if (emptyState) emptyState.classList.add('d-none');

        let firstNewEl = null;
        data.items.forEach(item => {
            const temp = document.createElement('div');
            temp.innerHTML = item.html.trim();
            const cardEl = temp.firstElementChild;
            cardEl.style.animation = 'fadeInUp 0.35s ease-out';
            container.appendChild(cardEl);
            if (!firstNewEl) firstNewEl = cardEl;
        });

        renumberSection(tipe);
        updateStatsDisplay(data.stats);

        if (firstNewEl) {
            firstNewEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            const textarea = firstNewEl.querySelector('textarea.question-textarea');
            if (textarea) {
                setTimeout(() => {
                    textarea.focus();
                    textarea.select();
                }, 350);
            }
        }

        showToast('✓ ' + (data.items.length > 1 ? data.items.length + ' butir soal' : '1 butir soal') + ' berhasil ditambahkan!');
    })
    .catch(err => {
        if (btn) {
            btn.innerHTML = origHtml;
            btn.disabled = false;
        }
        alert('Terjadi kesalahan jaringan: ' + err.message);
    });
}

// =========================================================================
// 2. BATCH ADD PROMPT
// =========================================================================
function promptBatchAdd(tipe) {
    const defaultVal = 5;
    const label = (tipe === 'pg') ? 'Pilihan Ganda' : ((tipe === 'isian') ? 'Isian Singkat' : 'Essay');
    const countStr = prompt('Berapa butir soal ' + label + ' yang ingin Anda tambahkan sekaligus? (Contoh: 5, 10, 20):', defaultVal);
    if (!countStr) return;
    const count = parseInt(countStr);
    if (isNaN(count) || count < 1) {
        alert('Masukkan angka jumlah soal yang valid (minimal 1).');
        return;
    }
    if (count > 50) {
        alert('Maksimal penambahan dalam satu klik adalah 50 butir.');
        return;
    }
    tambahSoalAjax(tipe, count);
}

// =========================================================================
// 3. HAPUS BUTIR SOAL SECARA INSTAN VIA AJAX (ZERO PAGE RELOAD)
// =========================================================================
function hapusSoalAjax(soalId) {
    if (!confirm('Hapus butir soal ini? Tindakan ini tidak dapat dibatalkan.')) {
        return;
    }

    const card = document.getElementById('soal_item_' + soalId);
    if (!card) return;

    const tipe = card.getAttribute('data-tipe') || 'pg';

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('naskah_id', '<?= $naskahId ?>');
    formData.append('id', soalId);

    card.style.opacity = '0.35';
    card.style.pointerEvents = 'none';

    fetch('api_soal.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            card.style.opacity = '1';
            card.style.pointerEvents = 'auto';
            alert(data.message || 'Gagal menghapus butir soal.');
            return;
        }

        card.style.transition = 'all 0.3s ease-out';
        card.style.transform = 'scale(0.95)';
        card.style.opacity = '0';
        setTimeout(() => {
            card.remove();
            renumberSection(tipe);

            const container = document.getElementById('container_' + tipe);
            if (container && container.querySelectorAll('.soal-card').length === 0) {
                const emptyState = document.getElementById('empty_' + tipe);
                if (emptyState) emptyState.classList.remove('d-none');
            }
        }, 300);

        updateStatsDisplay(data.stats);
        showToast('Butir soal berhasil dihapus.');
    })
    .catch(err => {
        card.style.opacity = '1';
        card.style.pointerEvents = 'auto';
        alert('Terjadi kesalahan jaringan: ' + err.message);
    });
}

// =========================================================================
// 4. PENOMORAN ULANG OTOMATIS DI LAYAR
// =========================================================================
function renumberSection(tipe) {
    const container = document.getElementById('container_' + tipe);
    if (!container) return;

    const cards = container.querySelectorAll('.soal-card');
    cards.forEach((c, idx) => {
        const no = idx + 1;
        const badge = c.querySelector('.soal-nomor-badge');
        if (badge) {
            if (tipe === 'pg') badge.textContent = 'Soal No. ' + no;
            else if (tipe === 'isian') badge.textContent = 'Isian No. ' + no;
            else badge.textContent = 'Essay No. ' + no;
        }
        const inputNo = c.querySelector('.input-nomor-urut');
        if (inputNo) {
            inputNo.value = no;
        }
    });
}

// =========================================================================
// 5. UPDATE REALTIME TAMPILAN KUNCI JAWABAN (RADIO PG)
// =========================================================================
function updateKeyVisual(radio) {
    const card = radio.closest('.soal-card');
    if (!card) return;

    card.querySelectorAll('.opsi-text-input').forEach(input => {
        input.classList.remove('border-success', 'bg-success-subtle');
    });
    card.querySelectorAll('.opsi-letter-label').forEach(lbl => {
        lbl.classList.remove('text-success', 'border-success');
        lbl.classList.add('text-muted');
    });

    const inputGroup = radio.closest('.input-group');
    if (inputGroup) {
        const textInput = inputGroup.querySelector('.opsi-text-input');
        const letterLabel = inputGroup.querySelector('.opsi-letter-label');
        if (textInput) textInput.classList.add('border-success', 'bg-success-subtle');
        if (letterLabel) {
            letterLabel.classList.remove('text-muted');
            letterLabel.classList.add('text-success', 'border-success');
        }
    }
}

// =========================================================================
// 6. MODAL PASTE SOAL DARI WORD / CATATAN
// =========================================================================
function openPasteModal(tipe = 'pg') {
    document.getElementById('pasteTipeSelect').value = tipe;
    document.getElementById('pasteRawText').value = '';
    const modalEl = document.getElementById('modalPasteSoal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
    setTimeout(() => {
        document.getElementById('pasteRawText').focus();
    }, 450);
}

function prosesPasteSoal() {
    const tipe = document.getElementById('pasteTipeSelect').value;
    const rawText = document.getElementById('pasteRawText').value.trim();
    const btn = document.getElementById('btnDoPaste');

    if (!rawText) {
        alert('Teks soal masih kosong. Silakan paste teks soal dari dokumen Word atau catatan Anda.');
        return;
    }

    const origHtml = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memproses &amp; Menyimpan...';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'parse_paste');
    formData.append('naskah_id', '<?= $naskahId ?>');
    formData.append('tipe', tipe);
    formData.append('raw_text', rawText);

    fetch('api_soal.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.innerHTML = origHtml;
        btn.disabled = false;

        if (!data.success) {
            alert(data.message || 'Gagal memproses teks soal.');
            return;
        }

        const modalEl = document.getElementById('modalPasteSoal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();

        const container = document.getElementById('container_' + tipe);
        const emptyState = document.getElementById('empty_' + tipe);
        if (emptyState) emptyState.classList.add('d-none');

        let firstNewEl = null;
        data.items.forEach(item => {
            const temp = document.createElement('div');
            temp.innerHTML = item.html.trim();
            const cardEl = temp.firstElementChild;
            cardEl.style.animation = 'fadeInUp 0.35s ease-out';
            container.appendChild(cardEl);
            if (!firstNewEl) firstNewEl = cardEl;
        });

        renumberSection(tipe);
        updateStatsDisplay(data.stats);

        if (firstNewEl) {
            firstNewEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        showToast('⚡ Hebat! ' + data.items.length + ' butir soal berhasil diekstrak dan ditambahkan secara instan!');
    })
    .catch(err => {
        btn.innerHTML = origHtml;
        btn.disabled = false;
        alert('Terjadi kesalahan jaringan: ' + err.message);
    });
}

// =========================================================================
// 7. HITUNG TOTAL POIN & UPDATE STATS
// =========================================================================
function hitungLiveTotal() {
    var inputs = document.querySelectorAll('.poin-input');
    var total = 0;
    inputs.forEach(function(inp) {
        var val = parseFloat(inp.value) || 0;
        total += val;
    });
    var disp = document.getElementById('liveTotalPoinDisplay');
    if (disp) {
        disp.textContent = total.toFixed(1).replace('.0', '');
    }
}

function updateStatsDisplay(stats) {
    if (!stats) return;
    const disp = document.getElementById('liveTotalPoinDisplay');
    if (disp && stats.total_poin !== undefined) {
        disp.textContent = parseFloat(stats.total_poin).toFixed(1).replace('.0', '');
    }
}

// =========================================================================
// 8. SIMPAN FORM SECARA AMAN
// =========================================================================
function simpanNaskahForm() {
    const form = document.getElementById('formSoalEditor');
    document.getElementById('addTipeInput').value = '';
    
    // Indikator visual tombol simpan
    const btnTop = document.getElementById('btnSimpanTop');
    const btnBtm = document.getElementById('btnSimpanBottom');
    if (btnTop) btnTop.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';
    if (btnBtm) btnBtm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';
    
    form.submit();
}

// SHORTCUT KEYBOARD CTRL + S
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        simpanNaskahForm();
    }
});

// =========================================================================
// 9. TOAST NOTIFIKASI ELEGAN
// =========================================================================
function showToast(msg) {
    let toast = document.getElementById('quick_action_toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'quick_action_toast';
        toast.className = 'position-fixed bottom-0 end-0 p-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <div class="toast align-items-center text-white bg-dark border-0 shadow-lg rounded-pill px-3 py-1" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex align-items-center">
                    <div class="toast-body fw-bold" id="quick_toast_body" style="font-size: 0.9rem;"></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        document.body.appendChild(toast);
    }
    document.getElementById('quick_toast_body').innerHTML = msg;
    const bsToast = new bootstrap.Toast(toast.querySelector('.toast'), { delay: 3500 });
    bsToast.show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
