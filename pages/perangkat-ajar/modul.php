<?php
/**
 * MODUL AJAR KURIKULUM MERDEKA - High Quality Executive Hub
 * Kegiatan Pembelajaran, Asesmen & LKPD
 */
$pageTitle  = 'Modul Ajar';
$activePage = 'perangkat-ajar';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_ajar');

$pdo = getConnection();
$userId = (int)$_SESSION['user_id'];
$userRole = strtolower($_SESSION['role'] ?? '');

$selectedCpId = (int)($_GET['cp_id'] ?? 0);

// Auto-generate / Simpan perbaikan Modul Ajar JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_modul') {
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        $stmtCurr = $pdo->prepare("SELECT modul_ajar_json FROM perangkat_ajar WHERE id = :id LIMIT 1");
        $stmtCurr->execute([':id' => $id]);
        $currRow = $stmtCurr->fetch();
        $mJson = json_decode($currRow['modul_ajar_json'] ?? '[]', true) ?: [];

        $mJson['pertanyaan_pemantik']  = trim($_POST['pertanyaan_pemantik'] ?? '');
        $mJson['kegiatan_pendahuluan'] = trim($_POST['kegiatan_pendahuluan'] ?? '');
        $mJson['kegiatan_inti']        = trim($_POST['kegiatan_inti'] ?? '');
        $mJson['kegiatan_penutup']     = trim($_POST['kegiatan_penutup'] ?? '');
        $mJson['asesmen_formatif']     = trim($_POST['asesmen_formatif'] ?? '');
        $mJson['asesmen_sumatif']      = trim($_POST['asesmen_sumatif'] ?? '');
        $mJson['lkpd_content']         = trim($_POST['lkpd_content'] ?? '');
        $mJson['glosarium']            = trim($_POST['glosarium'] ?? '');
        $mJson['daftar_pustaka']       = trim($_POST['daftar_pustaka'] ?? '');

        $stmtUp = $pdo->prepare("UPDATE perangkat_ajar SET modul_ajar_json = :json, updated_at = NOW() WHERE id = :id");
        $stmtUp->execute([':json' => json_encode($mJson, JSON_UNESCAPED_UNICODE), ':id' => $id]);
        redirect('modul.php?cp_id=' . $id, 'success', '✨ Modul Ajar berhasil diperbarui!');
    }
}

// Fetch list Perangkat Ajar untuk Modul
if ($userRole === 'admin') {
    $stmtMod = $pdo->query("
        SELECT p.*, u.nama_lengkap AS nama_guru 
        FROM perangkat_ajar p 
        LEFT JOIN users u ON p.user_id = u.id 
        ORDER BY p.updated_at DESC
    ");
} else {
    $stmtMod = $pdo->prepare("
        SELECT p.*, u.nama_lengkap AS nama_guru 
        FROM perangkat_ajar p 
        LEFT JOIN users u ON p.user_id = u.id 
        WHERE p.user_id = :uid OR p.user_id IS NOT NULL
        ORDER BY (p.user_id = :uid2) DESC, p.updated_at DESC
    ");
    $stmtMod->execute([':uid' => $userId, ':uid2' => $userId]);
}
$listMod = $stmtMod->fetchAll();

// Target CP aktif jika dipilih
$activeItem = null;
if ($selectedCpId > 0) {
    foreach ($listMod as $item) {
        if ($item['id'] == $selectedCpId) {
            $activeItem = $item;
            break;
        }
    }
}
if (!$activeItem && !empty($listMod)) {
    $activeItem = $listMod[0];
}

$mJson = [];
if ($activeItem && !empty($activeItem['modul_ajar_json'])) {
    $mJson = json_decode($activeItem['modul_ajar_json'], true) ?: [];
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/subnav.php';
?>

<style>
    .modul-hero {
        background: linear-gradient(135deg, #db2777 0%, #be185d 50%, #9d174d 100%);
        border-radius: 1.25rem;
        color: #fff;
        position: relative;
        overflow: hidden;
    }
    .modul-hero::before {
        content: '';
        position: absolute;
        top: -40%; right: -20%;
        width: 350px; height: 350px;
        background: rgba(255,255,255,0.12);
        border-radius: 50%;
        filter: blur(40px);
        pointer-events: none;
    }
</style>

<!-- HERO HEADER -->
<div class="modul-hero p-4 p-md-5 mb-4 shadow-sm">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 position-relative" style="z-index: 2;">
        <div>
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px);">
                <i class="bi bi-box-seam-fill fs-6"></i>
                <span class="small fw-bold text-uppercase" style="letter-spacing: 1px;">MODUL AJAR KURIKULUM MERDEKA</span>
            </div>
            <h3 class="fw-extrabold mb-1">Rancangan Modul Ajar Lengkap</h3>
            <p class="opacity-90 small mb-0 fs-6">Kelola komponen inti kegiatan pembelajaran, pertanyaan pemantik, asesmen, dan Lembar Kerja Peserta Didik (LKPD).</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <?php if (count($listMod) > 1): ?>
                <select class="form-select fw-bold border-0 shadow-sm rounded-3 py-2 px-3 text-dark" style="min-width: 200px;" onchange="location = this.value;">
                    <?php foreach ($listMod as $modOpt): ?>
                        <option value="modul.php?cp_id=<?= $modOpt['id'] ?>" <?= ($activeItem && $activeItem['id'] == $modOpt['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($modOpt['mapel']) ?> — <?= htmlspecialchars($modOpt['topik']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <a href="identitas.php" class="btn btn-light fw-bold text-dark px-3 py-2.5 rounded-3 shadow-sm">
                <i class="bi bi-person-badge me-1"></i> Identitas Modul
            </a>
            <?php if ($activeItem): ?>
                <a href="print.php?doc_type=modul&id=<?= $activeItem['id'] ?>" target="_blank" class="btn btn-light text-danger fw-bold px-3 py-2.5 rounded-3 shadow-sm">
                    <i class="bi bi-file-earmark-pdf-fill me-1"></i> Cetak PDF Modul Ajar
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($activeItem): ?>
<form method="POST" action="modul.php?cp_id=<?= $activeItem['id'] ?>">
    <input type="hidden" name="action" value="save_modul">
    <input type="hidden" name="id" value="<?= $activeItem['id'] ?>">

    <div class="row g-4 mb-4">
        <!-- KEGIATAN PEMBELAJARAN & PEMANTIK -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white p-3.5 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold m-0 text-dark"><i class="bi bi-journal-text text-pink me-2" style="color: #db2777;"></i> Kegiatan Pembelajaran Utama</h6>
                    <span class="badge bg-secondary"><?= htmlspecialchars($activeItem['mapel']) ?> (<?= htmlspecialchars($activeItem['kelas']) ?>)</span>
                </div>
                <div class="card-body p-4">
                    
                    <!-- INFO RINGKAS IDENTITAS TERHUBUNG -->
                    <div class="p-3 bg-light rounded-4 border mb-3 small text-dark">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-primary"><i class="bi bi-info-circle me-1"></i> Identitas Terhubung</span>
                            <a href="identitas.php" class="small text-decoration-none fw-bold">Edit Identitas</a>
                        </div>
                        <div>Sekolah: <strong><?= htmlspecialchars($mJson['nama_sekolah'] ?? SCHOOL_NAME) ?></strong></div>
                        <div>Model Pembelajaran: <strong><?= htmlspecialchars($mJson['model_pembelajaran'] ?? 'Problem-Based Learning (PBL)') ?></strong></div>
                        <div>Profil Pancasila: <strong><?= implode(', ', $mJson['profil_pancasila'] ?? ['Bernalar Kritis', 'Gotong Royong', 'Kreatif']) ?></strong></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-7"><i class="bi bi-lightbulb-fill text-warning me-1"></i> Pertanyaan Pemantik</label>
                        <textarea name="pertanyaan_pemantik" class="form-control border-2" rows="3" placeholder="Masukkan 1-3 pertanyaan pemicu diskusi siswa..."><?= htmlspecialchars($mJson['pertanyaan_pemantik'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-7"><i class="bi bi-diagram-2-fill text-pink me-1" style="color: #db2777;"></i> Kegiatan Inti Pembelajaran</label>
                        <textarea name="kegiatan_inti" class="form-control border-2" rows="6" placeholder="Orientasi masalah, penyelidikan mandiri/kelompok, menyajikan karya..."><?= htmlspecialchars($mJson['kegiatan_inti'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- ASESMEN, LKPD, GLOSARIUM -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white p-3.5 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold m-0 text-dark"><i class="bi bi-journal-check text-success me-2"></i> Asesmen & Lembar Kerja (LKPD)</h6>
                    <button type="submit" class="btn btn-primary-custom px-4 btn-sm fw-bold">
                        <i class="bi bi-save-fill me-1.5"></i> Simpan Modul
                    </button>
                </div>
                <div class="card-body p-4">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold text-dark fs-7">Asesmen Formatif</label>
                            <input type="text" name="asesmen_formatif" class="form-control border-2" value="<?= htmlspecialchars($mJson['asesmen_formatif'] ?? 'Penilaian keaktifan diskusi & LKPD') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold text-dark fs-7">Asesmen Sumatif</label>
                            <input type="text" name="asesmen_sumatif" class="form-control border-2" value="<?= htmlspecialchars($mJson['asesmen_sumatif'] ?? 'Tes pilihan ganda / esai evaluasi') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-7"><i class="bi bi-file-earmark-text-fill text-primary me-1"></i> Konten LKPD (Lembar Kerja Peserta Didik)</label>
                        <textarea name="lkpd_content" class="form-control border-2" rows="4" placeholder="Lembar tugas / instruksi kerja peserta didik..."><?= htmlspecialchars($mJson['lkpd_content'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-7">Glosarium & Kata Kunci</label>
                        <input type="text" name="glosarium" class="form-control border-2" value="<?= htmlspecialchars($mJson['glosarium'] ?? 'Istilah Penting: Definisi ringkas materi') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<?php endif; ?>

<!-- DAFTAR SEMUA DOKUMEN MODUL AJAR -->
<div class="card border-0 shadow-sm rounded-4 mt-4">
    <div class="card-header bg-white p-3.5 border-bottom">
        <h6 class="fw-bold m-0 text-dark"><i class="bi bi-table text-primary me-2"></i> Daftar Seluruh Modul Ajar Kurikulum Merdeka</h6>
    </div>
    <div class="card-body p-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light fs-7">
                    <tr>
                        <th>Mata Pelajaran & Topik</th>
                        <th>Model & Profil Pancasila</th>
                        <th>Guru Pengampu</th>
                        <th class="text-end">Aksi Dokumen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listMod as $modRow): 
                        $mj = json_decode($modRow['modul_ajar_json'] ?? '[]', true) ?: [];
                    ?>
                        <tr class="<?= ($activeItem && $modRow['id'] == $activeItem['id']) ? 'table-active' : '' ?>">
                            <td>
                                <span class="fw-bold text-dark d-block"><?= htmlspecialchars($modRow['mapel']) ?> (<?= htmlspecialchars($modRow['kelas']) ?>)</span>
                                <small class="text-pink fw-semibold" style="color: #db2777;"><?= htmlspecialchars($modRow['topik']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-secondary mb-1"><?= htmlspecialchars($mj['model_pembelajaran'] ?? 'PBL') ?></span>
                                <small class="text-muted d-block"><?= implode(', ', $mj['profil_pancasila'] ?? []) ?></small>
                            </td>
                            <td><small class="fw-bold text-dark"><?= htmlspecialchars($modRow['nama_guru'] ?? '-') ?></small></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="modul.php?cp_id=<?= $modRow['id'] ?>" class="btn btn-outline-primary px-2.5" title="Pilih & Edit Modul Ajar">
                                        <i class="bi bi-pencil-square me-1"></i> Pilih
                                    </a>
                                    <a href="print.php?doc_type=modul&id=<?= $modRow['id'] ?>" target="_blank" class="btn btn-outline-danger px-2.5" title="Cetak PDF Modul Ajar">
                                        <i class="bi bi-file-earmark-pdf-fill me-1"></i> PDF Modul Ajar
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
