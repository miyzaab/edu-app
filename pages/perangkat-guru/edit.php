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

            <a href="print.php?id=<?= $naskahId ?>" target="_blank" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold">
                <i class="bi bi-printer-fill me-1"></i> Cetak / PDF
            </a>
            <a href="export_word.php?id=<?= $naskahId ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                <i class="bi bi-file-earmark-word-fill me-1"></i> Unduh Word (.doc)
            </a>
            <a href="print_kunci.php?id=<?= $naskahId ?>" target="_blank" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold">
                <i class="bi bi-key-fill me-1"></i> Kunci Jawaban
            </a>
            <button type="button" onclick="document.getElementById('formSoalEditor').submit();" class="btn btn-sm btn-primary rounded-pill px-4 fw-bold shadow-sm">
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
                    <div class="col-md-4">
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
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                    <span class="badge bg-primary rounded-circle" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;">A</span>
                    <span>BAGIAN I: PILIHAN GANDA (A – D)</span>
                </h5>
                <p class="text-muted small mb-0 mt-1">Pilih satu jawaban yang paling tepat (A, B, C, atau D)</p>
            </div>
            <button type="button" onclick="submitTambahSoal('pg')" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                <i class="bi bi-plus-circle me-1"></i> Tambah Soal PG
            </button>
        </div>
        <div class="card-body p-4">
            <?php if (empty($soalPg)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-ui-checks display-6 d-block mb-2 text-muted"></i>
                    Belum ada butir soal pilihan ganda. Klik <strong>Tambah Soal PG</strong> untuk memulai.
                </div>
            <?php else: ?>
                <?php foreach ($soalPg as $idx => $s):
                    $sId = (int) $s['id'];
                ?>
                    <div class="card border rounded-3 mb-4 p-3 bg-light-subtle shadow-xs position-relative" id="soal_item_<?= $sId ?>">
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-dark rounded-pill px-2.5 py-1">Soal No. <?= $idx + 1 ?></span>
                                <input type="hidden" name="soal[<?= $sId ?>][nomor_urut]" value="<?= $idx + 1 ?>">
                                <span class="badge bg-info-subtle text-info-emphasis border">Pilihan Ganda</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <div class="d-flex align-items-center gap-1.5">
                                    <label class="small fw-bold text-muted mb-0">Bobot Poin:</label>
                                    <input type="number" step="0.5" min="0.5" max="50" name="soal[<?= $sId ?>][poin]" 
                                        class="form-control form-control-sm font-monospace text-center poin-input" 
                                        style="width: 70px;" value="<?= (float)$s['poin'] ?>" onchange="hitungLiveTotal()">
                                </div>
                                <a href="delete_soal.php?id=<?= $sId ?>&naskah_id=<?= $naskahId ?>" 
                                    class="btn btn-sm btn-outline-danger rounded-circle p-1" title="Hapus Butir Soal"
                                    onclick="return confirm('Hapus butir soal nomor <?= $idx + 1 ?> ini?')">
                                    <i class="bi bi-trash" style="font-size: 0.9rem;"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Pertanyaan -->
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Teks Pertanyaan Soal:</label>
                            <textarea name="soal[<?= $sId ?>][pertanyaan]" class="form-control" rows="3" required placeholder="Tuliskan pertanyaan soal..."><?= htmlspecialchars($s['pertanyaan']) ?></textarea>
                        </div>

                        <!-- Gambar Soal (Jika ada) -->
                        <div class="mb-3 p-2.5 rounded-3 bg-white border">
                            <label class="form-label fw-bold small text-muted d-block mb-1">
                                <i class="bi bi-image me-1"></i> Gambar / Ilustrasi Pendukung Soal (Opsional):
                            </label>
                            <?php if (!empty($s['gambar']) && file_exists($uploadDir . '/' . $s['gambar'])): ?>
                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <img src="<?= BASE_URL ?>/uploads/soal/<?= htmlspecialchars($s['gambar']) ?>" alt="Ilustrasi" class="rounded border" style="max-height: 90px; max-width: 160px; object-fit: contain;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="delete_gambar[<?= $sId ?>]" value="1" id="del_img_<?= $sId ?>">
                                        <label class="form-check-label text-danger small fw-semibold" for="del_img_<?= $sId ?>">
                                            Hapus gambar ini
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="gambar_soal[<?= $sId ?>]" class="form-control form-control-sm" accept="image/*">
                        </div>

                        <!-- Pilihan Jawaban A, B, C, D & Kunci -->
                        <div class="row g-2">
                            <?php 
                            $opsiCols = [
                                'A' => $s['pilihan_a'],
                                'B' => $s['pilihan_b'],
                                'C' => $s['pilihan_c'],
                                'D' => $s['pilihan_d'],
                            ];
                            foreach ($opsiCols as $huruf => $val):
                                $isKey = (strtoupper(trim($s['kunci_jawaban'])) === $huruf);
                            ?>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white fw-bold <?= $isKey ? 'text-success border-success' : 'text-muted' ?>" style="width: 42px; justify-content: center;">
                                            <?= $huruf ?>.
                                        </span>
                                        <input type="text" name="soal[<?= $sId ?>][pilihan_<?= strtolower($huruf) ?>]" 
                                            class="form-control <?= $isKey ? 'border-success bg-success-subtle' : '' ?>" 
                                            value="<?= htmlspecialchars($val ?? '') ?>" placeholder="Pilihan Jawaban <?= $huruf ?>" required>
                                        <div class="input-group-text bg-white">
                                            <input class="form-check-input mt-0" type="radio" 
                                                name="soal[<?= $sId ?>][kunci_jawaban]" value="<?= $huruf ?>" 
                                                <?= $isKey ? 'checked' : '' ?> title="Tandai sebagai Kunci Jawaban Benar">
                                            <span class="small text-muted ms-1" style="font-size: 0.72rem;">Kunci</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="text-end mt-2">
                    <button type="button" onclick="submitTambahSoal('pg')" class="btn btn-outline-primary rounded-pill px-4 fw-bold">
                        <i class="bi bi-plus-circle me-1"></i> + Tambah Butir Soal PG
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BAGIAN B: ISIAN SINGKAT -->
    <!-- ================================================================ -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden" id="section_isian">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                    <span class="badge bg-teal rounded-circle text-white" style="background: #0d9488; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;">B</span>
                    <span>BAGIAN II: ISIAN SINGKAT</span>
                </h5>
                <p class="text-muted small mb-0 mt-1">Isilah titik-titik di bawah ini dengan jawaban yang benar dan singkat</p>
            </div>
            <button type="button" onclick="submitTambahSoal('isian')" class="btn btn-sm btn-outline-teal rounded-pill px-3 fw-bold" style="border-color: #0d9488; color: #0d9488;">
                <i class="bi bi-plus-circle me-1"></i> Tambah Soal Isian
            </button>
        </div>
        <div class="card-body p-4">
            <?php if (empty($soalIsian)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-input-cursor-text display-6 d-block mb-2 text-muted"></i>
                    Belum ada butir soal isian singkat. Klik <strong>Tambah Soal Isian</strong> untuk menambahkan.
                </div>
            <?php else: ?>
                <?php foreach ($soalIsian as $idx => $s):
                    $sId = (int) $s['id'];
                ?>
                    <div class="card border rounded-3 mb-4 p-3 bg-light-subtle shadow-xs" id="soal_item_<?= $sId ?>">
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge rounded-pill px-2.5 py-1 text-white" style="background: #0d9488;">Isian No. <?= $idx + 1 ?></span>
                                <input type="hidden" name="soal[<?= $sId ?>][nomor_urut]" value="<?= count($soalPg) + $idx + 1 ?>">
                                <span class="badge bg-teal-subtle text-teal-emphasis border" style="background: #ccfbf1; color: #0f766e;">Isian Singkat</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <div class="d-flex align-items-center gap-1.5">
                                    <label class="small fw-bold text-muted mb-0">Bobot Poin:</label>
                                    <input type="number" step="0.5" min="0.5" max="50" name="soal[<?= $sId ?>][poin]" 
                                        class="form-control form-control-sm font-monospace text-center poin-input" 
                                        style="width: 70px;" value="<?= (float)$s['poin'] ?>" onchange="hitungLiveTotal()">
                                </div>
                                <a href="delete_soal.php?id=<?= $sId ?>&naskah_id=<?= $naskahId ?>" 
                                    class="btn btn-sm btn-outline-danger rounded-circle p-1" title="Hapus Butir Soal"
                                    onclick="return confirm('Hapus butir isian nomor <?= $idx + 1 ?> ini?')">
                                    <i class="bi bi-trash" style="font-size: 0.9rem;"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Pertanyaan -->
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Pertanyaan / Kalimat Rumpang:</label>
                            <textarea name="soal[<?= $sId ?>][pertanyaan]" class="form-control" rows="2" required placeholder="Contoh: Ibukota negara Indonesia adalah ..."><?= htmlspecialchars($s['pertanyaan']) ?></textarea>
                        </div>

                        <!-- Kunci Jawaban Isian -->
                        <div class="mb-1">
                            <label class="form-label fw-bold small text-muted">Kunci Jawaban Singkat (Untuk Pedoman Guru):</label>
                            <input type="text" name="soal[<?= $sId ?>][kunci_jawaban]" class="form-control bg-white" 
                                value="<?= htmlspecialchars($s['kunci_jawaban'] ?? '') ?>" placeholder="Jawaban singkat yang benar...">
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="text-end mt-2">
                    <button type="button" onclick="submitTambahSoal('isian')" class="btn rounded-pill px-4 fw-bold text-white shadow-sm" style="background: #0d9488;">
                        <i class="bi bi-plus-circle me-1"></i> + Tambah Butir Soal Isian
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BAGIAN C: ESSAY / URAIAN -->
    <!-- ================================================================ -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden" id="section_essay">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                    <span class="badge bg-warning rounded-circle text-dark" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;">C</span>
                    <span>BAGIAN III: URAIAN / ESSAY</span>
                </h5>
                <p class="text-muted small mb-0 mt-1">Jawablah pertanyaan-pertanyaan di bawah ini secara jelas, lengkap, dan sistematis</p>
            </div>
            <button type="button" onclick="submitTambahSoal('essay')" class="btn btn-sm btn-outline-warning text-dark rounded-pill px-3 fw-bold">
                <i class="bi bi-plus-circle me-1"></i> Tambah Soal Essay
            </button>
        </div>
        <div class="card-body p-4">
            <?php if (empty($soalEssay)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-card-text display-6 d-block mb-2 text-muted"></i>
                    Belum ada butir soal uraian/essay. Klik <strong>Tambah Soal Essay</strong> untuk menambahkan.
                </div>
            <?php else: ?>
                <?php foreach ($soalEssay as $idx => $s):
                    $sId = (int) $s['id'];
                ?>
                    <div class="card border rounded-3 mb-4 p-3 bg-light-subtle shadow-xs" id="soal_item_<?= $sId ?>">
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1">Essay No. <?= $idx + 1 ?></span>
                                <input type="hidden" name="soal[<?= $sId ?>][nomor_urut]" value="<?= count($soalPg) + count($soalIsian) + $idx + 1 ?>">
                                <span class="badge bg-warning-subtle text-warning-emphasis border" style="background: #fef3c7; color: #b45309;">Uraian / Essay</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <div class="d-flex align-items-center gap-1.5">
                                    <label class="small fw-bold text-muted mb-0">Bobot Poin:</label>
                                    <input type="number" step="0.5" min="0.5" max="50" name="soal[<?= $sId ?>][poin]" 
                                        class="form-control form-control-sm font-monospace text-center poin-input" 
                                        style="width: 70px;" value="<?= (float)$s['poin'] ?>" onchange="hitungLiveTotal()">
                                </div>
                                <a href="delete_soal.php?id=<?= $sId ?>&naskah_id=<?= $naskahId ?>" 
                                    class="btn btn-sm btn-outline-danger rounded-circle p-1" title="Hapus Butir Soal"
                                    onclick="return confirm('Hapus butir essay nomor <?= $idx + 1 ?> ini?')">
                                    <i class="bi bi-trash" style="font-size: 0.9rem;"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Pertanyaan -->
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Teks Pertanyaan Uraian / Kasus:</label>
                            <textarea name="soal[<?= $sId ?>][pertanyaan]" class="form-control" rows="3" required placeholder="Jelaskan secara mendalam tentang..."><?= htmlspecialchars($s['pertanyaan']) ?></textarea>
                        </div>

                        <!-- Pedoman Penskoran / Jawaban Model -->
                        <div class="mb-1">
                            <label class="form-label fw-bold small text-muted">Pedoman Penskoran / Jawaban Model (Untuk Kunci Guru):</label>
                            <textarea name="soal[<?= $sId ?>][kunci_jawaban]" class="form-control bg-white" rows="2" 
                                placeholder="Rubrik penilaian atau rincian poin jawaban lengkap..."><?= htmlspecialchars($s['kunci_jawaban'] ?? '') ?></textarea>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="text-end mt-2">
                    <button type="button" onclick="submitTambahSoal('essay')" class="btn btn-warning rounded-pill px-4 fw-bold text-dark shadow-sm">
                        <i class="bi bi-plus-circle me-1"></i> + Tambah Butir Soal Essay
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- BOTTOM FLOATING ACTION -->
    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-5 d-flex flex-row justify-content-between align-items-center">
        <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="bi bi-arrow-left me-1"></i> Selesai &amp; Kembali
        </a>
        <button type="submit" class="btn btn-primary rounded-pill px-5 py-2.5 fw-bold shadow-sm">
            <i class="bi bi-floppy-fill me-1.5"></i> Simpan Seluruh Naskah Soal
        </button>
    </div>
</form>

<script>
function submitTambahSoal(tipe) {
    document.getElementById('addTipeInput').value = tipe;
    document.getElementById('formSoalEditor').submit();
}

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
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
