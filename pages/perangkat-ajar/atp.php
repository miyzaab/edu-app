<?php
/**
 * ALUR TUJUAN PEMBELAJARAN (ATP) - Visual Flow & Learning Timeline
 * Modul Perangkat Ajar Kurikulum Merdeka
 */
$pageTitle  = 'Alur Tujuan Pembelajaran (ATP)';
$activePage = 'perangkat-ajar';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_ajar');

$pdo = getConnection();
$userId = (int)$_SESSION['user_id'];
$userRole = strtolower($_SESSION['role'] ?? '');

$selectedCpId = (int)($_GET['cp_id'] ?? 0);

// Auto-generate / Simpan perbaikan ATP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_atp') {
    $id  = (int)($_POST['id'] ?? 0);
    $atp = trim($_POST['alur_tujuan_pembelajaran'] ?? '');

    if ($id > 0 && $atp) {
        $stmtUp = $pdo->prepare("UPDATE perangkat_ajar SET alur_tujuan_pembelajaran = :atp, updated_at = NOW() WHERE id = :id");
        $stmtUp->execute([':atp' => $atp, ':id' => $id]);
        redirect('atp.php?cp_id=' . $id, 'success', '✨ Alur Tujuan Pembelajaran (ATP) berhasil diperbarui!');
    }
}

// Fetch list Perangkat Ajar untuk ATP
if ($userRole === 'admin') {
    $stmtATP = $pdo->query("
        SELECT p.*, u.nama_lengkap AS nama_guru 
        FROM perangkat_ajar p 
        LEFT JOIN users u ON p.user_id = u.id 
        ORDER BY p.updated_at DESC
    ");
} else {
    $stmtATP = $pdo->prepare("
        SELECT p.*, u.nama_lengkap AS nama_guru 
        FROM perangkat_ajar p 
        LEFT JOIN users u ON p.user_id = u.id 
        WHERE p.user_id = :uid OR p.user_id IS NOT NULL
        ORDER BY (p.user_id = :uid2) DESC, p.updated_at DESC
    ");
    $stmtATP->execute([':uid' => $userId, ':uid2' => $userId]);
}
$listATP = $stmtATP->fetchAll();

// Target CP aktif jika dipilih
$activeItem = null;
if ($selectedCpId > 0) {
    foreach ($listATP as $item) {
        if ($item['id'] == $selectedCpId) {
            $activeItem = $item;
            break;
        }
    }
}
if (!$activeItem && !empty($listATP)) {
    $activeItem = $listATP[0];
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/subnav.php';
?>

<style>
    .atp-hero {
        background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 50%, #5b21b6 100%);
        border-radius: 1.25rem;
        color: #fff;
        position: relative;
        overflow: hidden;
    }
    .atp-hero::before {
        content: '';
        position: absolute;
        top: -40%; right: -20%;
        width: 350px; height: 350px;
        background: rgba(255,255,255,0.12);
        border-radius: 50%;
        filter: blur(40px);
        pointer-events: none;
    }
    .atp-step-card {
        border-left: 4px solid #7c3aed;
        background: #fff;
        border-radius: 0.85rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .atp-step-card:hover {
        transform: translateX(4px);
        box-shadow: 0 8px 20px rgba(124, 58, 237, 0.12)!important;
    }
    .atp-step-badge {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        color: white;
        font-weight: 800;
        font-size: 0.75rem;
        padding: 4px 12px;
        border-radius: 20px;
    }
</style>

<!-- HERO HEADER -->
<div class="atp-hero p-4 p-md-5 mb-4 shadow-sm">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 position-relative" style="z-index: 2;">
        <div>
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px);">
                <i class="bi bi-diagram-3-fill fs-6"></i>
                <span class="small fw-bold text-uppercase" style="letter-spacing: 1px;">ALUR TUJUAN PEMBELAJARAN (ATP)</span>
            </div>
            <h3 class="fw-extrabold mb-1">Visualisasi Alur & Alokasi Pembelajaran (ATP)</h3>
            <p class="opacity-90 small mb-0 fs-6">Menyusun urutan alur pembelajaran secara kontekstual dan alokasi JP per tahap.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <?php if (count($listATP) > 1): ?>
                <select class="form-select fw-bold border-0 shadow-sm rounded-3 py-2 px-3 text-dark" style="min-width: 200px;" onchange="location = this.value;">
                    <?php foreach ($listATP as $atpOpt): ?>
                        <option value="atp.php?cp_id=<?= $atpOpt['id'] ?>" <?= ($activeItem && $activeItem['id'] == $atpOpt['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($atpOpt['mapel']) ?> — <?= htmlspecialchars($atpOpt['topik']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <a href="tp.php" class="btn btn-light fw-bold text-dark px-3 py-2.5 rounded-3 shadow-sm">
                <i class="bi bi-bullseye me-1"></i> Modul TP
            </a>
            <?php if ($activeItem): ?>
                <a href="print.php?doc_type=atp&id=<?= $activeItem['id'] ?>" target="_blank" class="btn btn-danger fw-bold px-3 py-2.5 rounded-3 shadow-sm">
                    <i class="bi bi-file-earmark-pdf-fill me-1"></i> Cetak PDF ATP
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($activeItem): ?>
<div class="row g-4">
    <!-- INFO TOPIC & TP ACUAN -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-light p-3.5 border-bottom">
                <h6 class="fw-bold m-0 text-dark"><i class="bi bi-journal-bookmark-fill text-purple me-2" style="color: #7c3aed;"></i> Topik & TP Acuan</h6>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <span class="badge bg-purple text-white fw-bold px-3 py-1.5 rounded-pill mb-1" style="background: #7c3aed;">
                        <?= htmlspecialchars($activeItem['mapel']) ?>
                    </span>
                    <span class="badge bg-secondary bg-opacity-10 text-dark fw-bold px-2.5 py-1.5 rounded-pill mb-1">
                        Kelas <?= htmlspecialchars($activeItem['kelas']) ?> (Fase <?= htmlspecialchars($activeItem['fase']) ?>)
                    </span>
                    <h4 class="fw-extrabold text-dark mt-2 mb-1"><?= htmlspecialchars($activeItem['topik']) ?></h4>
                    <small class="text-muted">Semester <?= htmlspecialchars($activeItem['semester']) ?> | Alokasi: <?= htmlspecialchars($activeItem['alokasi_waktu']) ?></small>
                </div>

                <div class="p-3.5 bg-light rounded-4 border mb-3">
                    <div class="small fw-bold text-muted mb-1"><i class="bi bi-quote me-1"></i>Tujuan Pembelajaran (TP) Acuan:</div>
                    <div class="small text-dark italic" style="line-height: 1.6;">
                        <?= nl2br(htmlspecialchars($activeItem['tujuan_pembelajaran'])) ?>
                    </div>
                </div>

                <div class="alert alert-purple border-0 rounded-3 mb-0 small d-flex gap-2 align-items-center" style="background: rgba(139, 92, 246, 0.12); color: #6d28d9;">
                    <i class="bi bi-clock-history fs-5"></i>
                    <div><strong>Alokasi Waktu Rasionil</strong>: Alur pembelajaran di samping disusun per tahap alokasi jam tatap muka (JP).</div>
                </div>
            </div>
        </div>
    </div>

    <!-- VISUAL TIMELINE FLOW ATP & EDIT FORM -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white p-3.5 border-bottom d-flex align-items-center justify-content-between">
                <h6 class="fw-bold m-0 text-dark"><i class="bi bi-diagram-3-fill text-purple me-2" style="color: #7c3aed;"></i> Visual Alur Pembelajaran (ATP)</h6>
                <a href="print.php?doc_type=atp&id=<?= $activeItem['id'] ?>" target="_blank" class="btn btn-sm btn-outline-danger fw-bold">
                    <i class="bi bi-file-pdf-fill me-1"></i> Download PDF ATP
                </a>
            </div>
            <div class="card-body p-4">
                
                <!-- VISUAL TIMELINE CARDS -->
                <?php
                $atpLines = array_filter(array_map('trim', explode("\n", $activeItem['alur_tujuan_pembelajaran'])));
                ?>
                <div class="d-flex flex-column gap-3 mb-4 position-relative">
                    <?php $atpStep = 1; foreach ($atpLines as $atpSingle): ?>
                        <div class="atp-step-card p-3 shadow-xs border border-start-0">
                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                <span class="atp-step-badge">TAHAP <?= $atpStep++ ?></span>
                                <span class="badge bg-light text-muted border fw-semibold fs-7"><i class="bi bi-clock me-1"></i>Alokasi JP Tatap Muka</span>
                            </div>
                            <div class="text-dark small fw-semibold" style="line-height: 1.5;"><?= htmlspecialchars($atpSingle) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- EDIT FORM ATP -->
                <form method="POST" action="atp.php?cp_id=<?= $activeItem['id'] ?>">
                    <input type="hidden" name="action" value="save_atp">
                    <input type="hidden" name="id" value="<?= $activeItem['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-7"><i class="bi bi-pencil-fill text-purple me-1" style="color: #7c3aed;"></i> Edit Teks Alur Tujuan Pembelajaran & Alokasi JP (ATP)</label>
                        <textarea name="alur_tujuan_pembelajaran" class="form-control border-2 font-monospace fs-7" rows="6" required><?= htmlspecialchars($activeItem['alur_tujuan_pembelajaran']) ?></textarea>
                    </div>

                    <div class="d-flex align-items-center justify-content-between">
                        <small class="text-muted"><i class="bi bi-clock-history me-1"></i>Diperbarui: <?= date('d M Y H:i', strtotime($activeItem['updated_at'])) ?></small>
                        <button type="submit" class="btn btn-purple px-4 py-2.5 fw-bold text-white shadow-sm" style="background: #7c3aed; border: none;">
                            <i class="bi bi-save-fill me-1.5"></i> Simpan Perubahan ATP
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- DAFTAR SEMUA DOKUMEN ATP -->
<div class="card border-0 shadow-sm rounded-4 mt-4">
    <div class="card-header bg-white p-3.5 border-bottom">
        <h6 class="fw-bold m-0 text-dark"><i class="bi bi-table text-primary me-2"></i> Daftar Seluruh Alur Tujuan Pembelajaran (ATP)</h6>
    </div>
    <div class="card-body p-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light fs-7">
                    <tr>
                        <th>Mata Pelajaran & Topik</th>
                        <th>Rangkaian Alur Pembelajaran (ATP)</th>
                        <th>Guru Pengampu</th>
                        <th class="text-end">Aksi Dokumen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listATP as $atpRow): ?>
                        <tr class="<?= ($activeItem && $atpRow['id'] == $activeItem['id']) ? 'table-active' : '' ?>">
                            <td>
                                <span class="fw-bold text-dark d-block"><?= htmlspecialchars($atpRow['mapel']) ?> (<?= htmlspecialchars($atpRow['kelas']) ?>)</span>
                                <small class="text-purple fw-semibold" style="color: #7c3aed;"><?= htmlspecialchars($atpRow['topik']) ?></small>
                            </td>
                            <td>
                                <small class="text-muted d-block text-truncate" style="max-width: 340px;">
                                    <?= htmlspecialchars(mb_strimwidth(str_replace("\n", " • ", $atpRow['alur_tujuan_pembelajaran']), 0, 110, '...')) ?>
                                </small>
                            </td>
                            <td><small class="fw-bold text-dark"><?= htmlspecialchars($atpRow['nama_guru'] ?? '-') ?></small></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="atp.php?cp_id=<?= $atpRow['id'] ?>" class="btn btn-outline-primary px-2.5" title="Pilih & Edit ATP">
                                        <i class="bi bi-pencil-square me-1"></i> Pilih
                                    </a>
                                    <a href="print.php?doc_type=atp&id=<?= $atpRow['id'] ?>" target="_blank" class="btn btn-outline-danger px-2.5" title="Cetak PDF ATP">
                                        <i class="bi bi-file-earmark-pdf-fill me-1"></i> PDF ATP
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
