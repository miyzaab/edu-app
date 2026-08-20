<?php
/**
 * DASHBOARD PERANGKAT AJAR KURIKULUM MERDEKA
 * Ringkasan Perangkat Ajar & Akses Cepat ke CP, TP, ATP, & Modul Ajar
 */
$pageTitle  = 'Dashboard Perangkat Ajar';
$activePage = 'perangkat-ajar';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_ajar');

$pdo = getConnection();
$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'guru';

// Load list perangkat ajar
if ($userRole === 'admin') {
    $stmt = $pdo->query("
        SELECT p.*, u.nama_lengkap AS nama_guru 
        FROM perangkat_ajar p 
        LEFT JOIN users u ON p.user_id = u.id 
        ORDER BY p.updated_at DESC
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT p.*, u.nama_lengkap AS nama_guru 
        FROM perangkat_ajar p 
        LEFT JOIN users u ON p.user_id = u.id 
        WHERE p.user_id = :uid OR p.user_id IS NOT NULL
        ORDER BY (p.user_id = :uid2) DESC, p.updated_at DESC
    ");
    $stmt->execute([':uid' => $userId, ':uid2' => $userId]);
}
$listPerangkat = $stmt->fetchAll();

// Statistics
$totalDocs = count($listPerangkat);
$myDocs = 0;
$mapelList = [];
foreach ($listPerangkat as $p) {
    if ($p['user_id'] == $userId) $myDocs++;
    if (!in_array($p['mapel'], $mapelList)) $mapelList[] = $p['mapel'];
}
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/subnav.php';
?>

<!-- HEADER DASHBOARD PERANGKAT AJAR -->
<div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white;">
    <div class="card-body p-4 p-md-5 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2" style="background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.4);">
                <i class="bi bi-journal-bookmark-fill text-success fs-6"></i>
                <span class="small fw-bold text-success" style="letter-spacing: 0.8px;">KURIKULUM MERDEKA DIGITAL</span>
            </div>
            <h3 class="fw-extrabold mb-1">Dashboard Perangkat Ajar AI</h3>
            <p class="opacity-75 small mb-0 fs-6">Kelola Capaian Pembelajaran (CP), Tujuan Pembelajaran (TP), Alur (ATP), dan Modul Ajar secara terpisah & otomatis.</p>
        </div>
        <a href="cp.php" class="btn btn-emerald px-4 py-2.5 fw-bold rounded-3 text-white shadow-sm" style="background: #10b981; border: none;">
            <i class="bi bi-plus-circle-fill me-1.5"></i> Buat CP Pembelajaran Baru
        </a>
    </div>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
    <div class="col-md-4 col-6">
        <div class="stat-card p-3 d-flex align-items-center gap-3">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.3rem;">
                <i class="bi bi-journal-check"></i>
            </div>
            <div>
                <div class="text-muted small fw-bold">Total Perangkat Ajar</div>
                <div class="h4 fw-bold mb-0"><?= $totalDocs ?> <small class="text-muted fs-6">dokumen</small></div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="stat-card p-3 d-flex align-items-center gap-3">
            <div class="stat-icon" style="background: linear-gradient(135deg, #6366f1, #4f46e5); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.3rem;">
                <i class="bi bi-person-workspace"></i>
            </div>
            <div>
                <div class="text-muted small fw-bold">Dokumen Saya</div>
                <div class="h4 fw-bold mb-0"><?= $myDocs ?> <small class="text-muted fs-6">dokumen</small></div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-12">
        <div class="stat-card p-3 d-flex align-items-center gap-3">
            <div class="stat-icon" style="background: linear-gradient(135deg, #06b6d4, #0891b2); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.3rem;">
                <i class="bi bi-book-half"></i>
            </div>
            <div>
                <div class="text-muted small fw-bold">Mata Pelajaran</div>
                <div class="h4 fw-bold mb-0"><?= count($mapelList) ?> <small class="text-muted fs-6">mapel terdaftar</small></div>
            </div>
        </div>
    </div>
</div>

<!-- 4 KARTU MENU TERPISAH -->
<div class="row g-4 mb-4">
    <!-- 1. CP -->
    <div class="col-lg-3 col-sm-6">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3 text-center">
            <div class="p-3 rounded-4 mx-auto mb-3" style="width: 64px; height: 64px; background: rgba(6, 182, 212, 0.12); color: #0891b2; font-size: 2rem; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-journal-text"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1">Capaian Pembelajaran (CP)</h5>
            <p class="text-muted small mb-3">Entri simpel poin-poin utama CP & cetak dokumen PDF khusus CP.</p>
            <a href="cp.php" class="btn btn-outline-info btn-sm w-100 fw-bold rounded-pill">
                <i class="bi bi-arrow-right-circle me-1"></i> Buka Menu CP
            </a>
        </div>
    </div>
    <!-- 2. TP -->
    <div class="col-lg-3 col-sm-6">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3 text-center">
            <div class="p-3 rounded-4 mx-auto mb-3" style="width: 64px; height: 64px; background: rgba(59, 130, 246, 0.12); color: #2563eb; font-size: 2rem; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-bullseye"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1">Tujuan Pembelajaran (TP)</h5>
            <p class="text-muted small mb-3">Auto-generate poin TP dari CP & cetak dokumen PDF khusus TP.</p>
            <a href="tp.php" class="btn btn-outline-primary btn-sm w-100 fw-bold rounded-pill">
                <i class="bi bi-arrow-right-circle me-1"></i> Buka Menu TP
            </a>
        </div>
    </div>
    <!-- 3. ATP -->
    <div class="col-lg-3 col-sm-6">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3 text-center">
            <div class="p-3 rounded-4 mx-auto mb-3" style="width: 64px; height: 64px; background: rgba(139, 92, 246, 0.12); color: #7c3aed; font-size: 2rem; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-diagram-3-fill"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1">Alur Tujuan Pembelajaran</h5>
            <p class="text-muted small mb-3">Auto-generate alur & JP, cetak dokumen PDF khusus ATP.</p>
            <a href="atp.php" class="btn btn-outline-purple btn-sm w-100 fw-bold rounded-pill" style="color: #7c3aed; border-color: #7c3aed;">
                <i class="bi bi-arrow-right-circle me-1"></i> Buka Menu ATP
            </a>
        </div>
    </div>
    <!-- 4. Modul Ajar -->
    <div class="col-lg-3 col-sm-6">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3 text-center">
            <div class="p-3 rounded-4 mx-auto mb-3" style="width: 64px; height: 64px; background: rgba(236, 72, 153, 0.12); color: #db2777; font-size: 2rem; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-box-seam-fill"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1">Modul Ajar Lengkap</h5>
            <p class="text-muted small mb-3">Auto-generate Modul Ajar & cetak PDF Modul Ajar terpisah.</p>
            <a href="modul.php" class="btn btn-outline-danger btn-sm w-100 fw-bold rounded-pill">
                <i class="bi bi-arrow-right-circle me-1"></i> Buka Modul Ajar
            </a>
        </div>
    </div>
</div>

<!-- TABLE DOKUMEN -->
<div class="table-card">
    <div class="table-header d-flex flex-wrap align-items-center justify-content-between gap-2 p-3.5">
        <h5 class="m-0 fw-bold text-dark"><i class="bi bi-folder-symlink text-primary me-2"></i> Daftar Perangkat Ajar Kurikulum Merdeka</h5>
        <a href="cp.php" class="btn btn-sm btn-primary-custom fw-bold"><i class="bi bi-plus-lg me-1"></i> Entri CP Baru</a>
    </div>
    <div class="table-responsive">
        <table class="data-table" id="tablePerangkat">
            <thead>
                <tr>
                    <th width="50">No</th>
                    <th>Mata Pelajaran</th>
                    <th>Kelas / Fase</th>
                    <th>Topik Pembelajaran</th>
                    <th>Guru Pengampu</th>
                    <th>Diperbarui</th>
                    <th width="340" class="text-end">Cetak PDF Terpisah</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($listPerangkat)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">Belum ada perangkat ajar. Klik tombol "Entri CP Baru" untuk memulai.</td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($listPerangkat as $row): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><span class="fw-bold text-dark"><?= htmlspecialchars($row['mapel']) ?></span></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($row['kelas']) ?> (Fase <?= htmlspecialchars($row['fase']) ?>)</span></td>
                            <td>
                                <div class="fw-bold text-primary"><?= htmlspecialchars($row['topik']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($row['elemen'] ?: '-') ?></small>
                            </td>
                            <td><small class="fw-semibold text-dark"><?= htmlspecialchars($row['nama_guru'] ?? '-') ?></small></td>
                            <td><small class="text-muted"><?= date('d/m/Y', strtotime($row['updated_at'])) ?></small></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm me-2">
                                    <a href="print.php?doc_type=cp&id=<?= $row['id'] ?>" target="_blank" class="btn btn-outline-info" title="Cetak PDF CP">CP</a>
                                    <a href="print.php?doc_type=tp&id=<?= $row['id'] ?>" target="_blank" class="btn btn-outline-primary" title="Cetak PDF TP">TP</a>
                                    <a href="print.php?doc_type=atp&id=<?= $row['id'] ?>" target="_blank" class="btn btn-outline-purple" style="color: #7c3aed; border-color: #7c3aed;" title="Cetak PDF ATP">ATP</a>
                                    <a href="print.php?doc_type=modul&id=<?= $row['id'] ?>" target="_blank" class="btn btn-outline-danger" title="Cetak PDF Modul Ajar">Modul</a>
                                    <a href="print.php?doc_type=all&id=<?= $row['id'] ?>" target="_blank" class="btn btn-danger text-white fw-bold" title="Cetak Paket Lengkap (CP+TP+ATP+Modul)"><i class="bi bi-file-earmark-pdf-fill"></i> Paket</a>
                                    <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-outline-warning text-dark" title="Edit Perangkat Ajar"><i class="bi bi-pencil"></i></a>
                                </div>
                                <a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus dokumen Perangkat Ajar ini secara permanen? Seluruh data CP, TP, ATP, dan Modul Ajar terkait akan terhapus.');" title="Hapus Perangkat Ajar">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function confirmDelete(id) {
    if (confirm('Apakah Anda yakin ingin menghapus dokumen Perangkat Ajar ini secara permanen? Seluruh data CP, TP, ATP, dan Modul Ajar terkait akan hilang.')) {
        window.location.href = 'delete.php?id=' + id;
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
