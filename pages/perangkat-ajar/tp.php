<?php
/**
 * TUJUAN PEMBELAJARAN (TP) - High Quality Learning Objective Hub
 * Modul Perangkat Ajar Kurikulum Merdeka
 */
$pageTitle  = 'Tujuan Pembelajaran (TP)';
$activePage = 'perangkat-ajar';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_ajar');

$pdo = getConnection();
$userId = (int)$_SESSION['user_id'];
$userRole = strtolower($_SESSION['role'] ?? '');

$selectedCpId = (int)($_GET['cp_id'] ?? 0);

// Auto-generate / Simpan perbaikan TP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_tp') {
    $id = (int)($_POST['id'] ?? 0);
    $tp = trim($_POST['tujuan_pembelajaran'] ?? '');

    if ($id > 0 && $tp) {
        $stmtUp = $pdo->prepare("UPDATE perangkat_ajar SET tujuan_pembelajaran = :tp, updated_at = NOW() WHERE id = :id");
        $stmtUp->execute([':tp' => $tp, ':id' => $id]);
        redirect('tp.php?cp_id=' . $id, 'success', '✨ Tujuan Pembelajaran (TP) berhasil diperbarui!');
    }
}

// Fetch list Perangkat Ajar untuk TP
if ($userRole === 'admin') {
    $stmtTP = $pdo->query("
        SELECT p.*, u.nama_lengkap AS nama_guru 
        FROM perangkat_ajar p 
        LEFT JOIN users u ON p.user_id = u.id 
        ORDER BY p.updated_at DESC
    ");
} else {
    $stmtTP = $pdo->prepare("
        SELECT p.*, u.nama_lengkap AS nama_guru 
        FROM perangkat_ajar p 
        LEFT JOIN users u ON p.user_id = u.id 
        WHERE p.user_id = :uid OR p.user_id IS NOT NULL
        ORDER BY (p.user_id = :uid2) DESC, p.updated_at DESC
    ");
    $stmtTP->execute([':uid' => $userId, ':uid2' => $userId]);
}
$listTP = $stmtTP->fetchAll();

// Target CP aktif jika dipilih
$activeItem = null;
if ($selectedCpId > 0) {
    foreach ($listTP as $item) {
        if ($item['id'] == $selectedCpId) {
            $activeItem = $item;
            break;
        }
    }
}
if (!$activeItem && !empty($listTP)) {
    $activeItem = $listTP[0];
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/subnav.php';
?>

<style>
    .tp-hero {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 50%, #1e40af 100%);
        border-radius: 1.25rem;
        color: #fff;
        position: relative;
        overflow: hidden;
    }
    .tp-hero::before {
        content: '';
        position: absolute;
        top: -40%; right: -20%;
        width: 350px; height: 350px;
        background: rgba(255,255,255,0.12);
        border-radius: 50%;
        filter: blur(40px);
        pointer-events: none;
    }
    .tp-pill-badge {
        width: 38px; height: 38px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        color: white;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800;
        font-size: 0.9rem;
        box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3);
    }
</style>

<!-- HERO HEADER -->
<div class="tp-hero p-4 p-md-5 mb-4 shadow-sm">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 position-relative" style="z-index: 2;">
        <div>
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px);">
                <i class="bi bi-bullseye fs-6"></i>
                <span class="small fw-bold text-uppercase" style="letter-spacing: 1px;">MODUL TUJUAN PEMBELAJARAN (TP)</span>
            </div>
            <h3 class="fw-extrabold mb-1">Rincian Tujuan Pembelajaran (TP)</h3>
            <p class="opacity-90 small mb-0 fs-6">Tujuan Pembelajaran secara rasional diturunkan dari poin Capaian Pembelajaran (CP).</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <?php if (count($listTP) > 1): ?>
                <select class="form-select fw-bold border-0 shadow-sm rounded-3 py-2 px-3 text-dark" style="min-width: 200px;" onchange="location = this.value;">
                    <?php foreach ($listTP as $tpOpt): ?>
                        <option value="tp.php?cp_id=<?= $tpOpt['id'] ?>" <?= ($activeItem && $activeItem['id'] == $tpOpt['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tpOpt['mapel']) ?> — <?= htmlspecialchars($tpOpt['topik']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <a href="cp.php" class="btn btn-light fw-bold text-dark px-3 py-2.5 rounded-3 shadow-sm">
                <i class="bi bi-journal-text me-1"></i> Kelola CP
            </a>
            <?php if ($activeItem): ?>
                <a href="print.php?doc_type=tp&id=<?= $activeItem['id'] ?>" target="_blank" class="btn btn-danger fw-bold px-3 py-2.5 rounded-3 shadow-sm">
                    <i class="bi bi-file-earmark-pdf-fill me-1"></i> Cetak PDF TP
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($activeItem): ?>
<div class="row g-4">
    <!-- CP ACUAN -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-light p-3.5 border-bottom">
                <h6 class="fw-bold m-0 text-dark"><i class="bi bi-info-circle-fill text-info me-2"></i> Induk Capaian Pembelajaran (CP)</h6>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <span class="badge bg-primary bg-opacity-10 text-primary fw-bold px-3 py-1.5 rounded-pill mb-1">
                        <?= htmlspecialchars($activeItem['mapel']) ?>
                    </span>
                    <span class="badge bg-secondary bg-opacity-10 text-dark fw-bold px-2.5 py-1.5 rounded-pill mb-1">
                        Kelas <?= htmlspecialchars($activeItem['kelas']) ?> (Fase <?= htmlspecialchars($activeItem['fase']) ?>)
                    </span>
                    <h4 class="fw-extrabold text-dark mt-2 mb-1"><?= htmlspecialchars($activeItem['topik']) ?></h4>
                    <small class="text-muted">Elemen: <?= htmlspecialchars($activeItem['elemen'] ?: '-') ?> | Alokasi Waktu: <?= htmlspecialchars($activeItem['alokasi_waktu']) ?></small>
                </div>

                <div class="p-3.5 bg-light rounded-4 border mb-3">
                    <div class="small fw-bold text-muted mb-1"><i class="bi bi-quote me-1"></i>Teks Capaian Pembelajaran (CP):</div>
                    <div class="small text-dark italic" style="line-height: 1.6;">
                        <?= nl2br(htmlspecialchars($activeItem['capaian_pembelajaran'])) ?>
                    </div>
                </div>

                <div class="alert alert-primary border-0 rounded-3 mb-0 small d-flex gap-2 align-items-center">
                    <i class="bi bi-magic fs-5"></i>
                    <div><strong>Auto-Generated TP</strong>: Poin Tujuan Pembelajaran (TP) disusun secara terstruktur dari indikator CP di atas.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- POIN TP CARD HUB -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white p-3.5 border-bottom d-flex align-items-center justify-content-between">
                <h6 class="fw-bold m-0 text-dark"><i class="bi bi-check2-circle text-primary me-2"></i> Rincian Poin Tujuan Pembelajaran (TP)</h6>
                <a href="print.php?doc_type=tp&id=<?= $activeItem['id'] ?>" target="_blank" class="btn btn-sm btn-outline-danger fw-bold">
                    <i class="bi bi-file-pdf-fill me-1"></i> Download PDF TP
                </a>
            </div>
            <div class="card-body p-4">
                
                <!-- VISUAL BREAKDOWN LIST TP -->
                <?php
                $tpLines = array_filter(array_map('trim', explode("\n", $activeItem['tujuan_pembelajaran'])));
                ?>
                <div class="d-flex flex-column gap-3 mb-4">
                    <?php $tpIdx = 1; foreach ($tpLines as $tpSingle): ?>
                        <div class="p-3 rounded-4 border bg-white shadow-xs d-flex align-items-start gap-3">
                            <div class="tp-pill-badge flex-shrink-0"><?= $tpIdx++ ?></div>
                            <div class="flex-grow-1">
                                <div class="fw-bold text-dark fs-7 mb-0.5">Indikator Tujuan Pembelajaran</div>
                                <div class="text-secondary small" style="line-height: 1.5;"><?= htmlspecialchars(preg_replace('/^\d+\.\s*/', '', $tpSingle)) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- EDIT FORM -->
                <form method="POST" action="tp.php?cp_id=<?= $activeItem['id'] ?>">
                    <input type="hidden" name="action" value="save_tp">
                    <input type="hidden" name="id" value="<?= $activeItem['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-7"><i class="bi bi-pencil-fill text-primary me-1"></i> Edit Teks Tujuan Pembelajaran (TP)</label>
                        <textarea name="tujuan_pembelajaran" class="form-control border-2 font-monospace fs-7" rows="6" required><?= htmlspecialchars($activeItem['tujuan_pembelajaran']) ?></textarea>
                    </div>

                    <div class="d-flex align-items-center justify-content-between">
                        <small class="text-muted"><i class="bi bi-clock-history me-1"></i>Diperbarui: <?= date('d M Y H:i', strtotime($activeItem['updated_at'])) ?></small>
                        <button type="submit" class="btn btn-primary-custom px-4 py-2.5 fw-bold shadow-sm">
                            <i class="bi bi-save-fill me-1.5"></i> Simpan Perubahan TP
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- TABEL SEMUA DOKUMEN TP -->
<div class="card border-0 shadow-sm rounded-4 mt-4">
    <div class="card-header bg-white p-3.5 border-bottom">
        <h6 class="fw-bold m-0 text-dark"><i class="bi bi-table text-primary me-2"></i> Daftar Seluruh Dokumen Tujuan Pembelajaran (TP)</h6>
    </div>
    <div class="card-body p-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light fs-7">
                    <tr>
                        <th>Mata Pelajaran & Topik</th>
                        <th>Rincian Tujuan Pembelajaran (TP)</th>
                        <th>Guru Pengampu</th>
                        <th class="text-end">Aksi Dokumen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listTP as $tpRow): ?>
                        <tr class="<?= ($activeItem && $tpRow['id'] == $activeItem['id']) ? 'table-active' : '' ?>">
                            <td>
                                <span class="fw-bold text-dark d-block"><?= htmlspecialchars($tpRow['mapel']) ?> (<?= htmlspecialchars($tpRow['kelas']) ?>)</span>
                                <small class="text-primary fw-semibold"><?= htmlspecialchars($tpRow['topik']) ?></small>
                            </td>
                            <td>
                                <small class="text-muted d-block text-truncate" style="max-width: 340px;">
                                    <?= htmlspecialchars(mb_strimwidth(str_replace("\n", " • ", $tpRow['tujuan_pembelajaran']), 0, 110, '...')) ?>
                                </small>
                            </td>
                            <td><small class="fw-bold text-dark"><?= htmlspecialchars($tpRow['nama_guru'] ?? '-') ?></small></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="tp.php?cp_id=<?= $tpRow['id'] ?>" class="btn btn-outline-primary px-2.5" title="Pilih & Edit TP">
                                        <i class="bi bi-pencil-square me-1"></i> Pilih
                                    </a>
                                    <a href="print.php?doc_type=tp&id=<?= $tpRow['id'] ?>" target="_blank" class="btn btn-outline-danger px-2.5" title="Cetak PDF TP">
                                        <i class="bi bi-file-earmark-pdf-fill me-1"></i> PDF TP
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
