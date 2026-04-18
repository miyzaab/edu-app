<?php
/**
 * VERIFIKASI PEMBAYARAN - Halaman untuk Bendahara memverifikasi bukti transfer
 */
$pageTitle  = 'Verifikasi Pembayaran';
$activePage = 'verifikasi';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

// Ambil status filter (default: pending)
$filterStatus = $_GET['status'] ?? 'pending';
$statusCondition = "";
if (in_array($filterStatus, ['pending', 'disetujui', 'ditolak'])) {
    $statusCondition = " AND p.status = " . $pdo->quote($filterStatus);
}

// Query data pending
$query = "
    SELECT p.*, s.nis, s.nama, k.nama_kelas, jp.nama_pembayaran 
    FROM pembayaran_pending p
    JOIN siswa s ON p.siswa_id = s.id
    JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN jenis_pembayaran jp ON p.jenis_pembayaran_id = jp.id
    WHERE 1=1 $statusCondition
    ORDER BY p.created_at DESC
";
$pendingList = $pdo->query($query)->fetchAll();

// Hitung badge
$countPending = $pdo->query("SELECT COUNT(id) FROM pembayaran_pending WHERE status='pending'")->fetchColumn();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Total: <strong><?= count($pendingList) ?></strong> data <?= $filterStatus ?></p>
</div>

<!-- Navigasi Status -->
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item">
        <a class="nav-link <?= $filterStatus === 'pending' ? 'active' : '' ?>" href="?status=pending">
            <i class="bi bi-hourglass-split"></i> Pending
            <?php if ($countPending > 0): ?>
                <span class="badge bg-danger rounded-pill ms-1"><?= $countPending ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filterStatus === 'disetujui' ? 'active' : '' ?>" href="?status=disetujui">
            <i class="bi bi-check-circle text-success"></i> Disetujui
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filterStatus === 'ditolak' ? 'active' : '' ?>" href="?status=ditolak">
            <i class="bi bi-x-circle text-danger"></i> Ditolak
        </a>
    </li>
</ul>

<div class="table-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Waktu Submit</th>
                    <th>Siswa</th>
                    <th>Jenis</th>
                    <th>Nominal</th>
                    <th>Catatan</th>
                    <th class="text-center">Bukti</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pendingList as $p): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
                    <td>
                        <strong><?= htmlspecialchars($p['nama']) ?></strong><br>
                        <small class="text-muted"><?= htmlspecialchars($p['nama_kelas']) ?> - <?= htmlspecialchars($p['nis']) ?></small>
                    </td>
                    <td>
                        <?php 
                        if ($p['jenis'] === 'spp') echo "SPP (" . namaBulan($p['bulan']) . " " . $p['tahun'] . ")";
                        elseif ($p['jenis'] === 'uang_pangkal') echo "Uang Pangkal";
                        elseif ($p['jenis'] === 'lainnya') echo htmlspecialchars($p['nama_pembayaran']);
                        ?>
                    </td>
                    <td class="fw-bold text-success"><?= formatRupiah($p['nominal']) ?></td>
                    <td style="max-width:150px;white-space:normal;font-size:.8rem"><?= htmlspecialchars($p['catatan']) ?: '-' ?></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-secondary" onclick="lihatBukti('<?= htmlspecialchars($p['bukti_transfer']) ?>')">
                            <i class="bi bi-image"></i> Lihat
                        </button>
                    </td>
                    <td class="text-center">
                        <?php if ($p['status'] === 'pending'): ?>
                            <div class="d-flex gap-1 justify-content-center">
                                <form method="POST" action="proses.php" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Setujui pembayaran ini? Data akan otomatis masuk ke laporan.')" title="Setujui"><i class="bi bi-check-lg"></i></button>
                                </form>
                                <button class="btn btn-sm btn-danger" onclick="tolakPembayaran(<?= $p['id'] ?>)" title="Tolak"><i class="bi bi-x-lg"></i></button>
                            </div>
                        <?php else: ?>
                            <span class="badge <?= $p['status'] === 'disetujui' ? 'bg-success' : 'bg-danger' ?>">
                                <?= ucfirst($p['status']) ?>
                            </span>
                            <?php if ($p['status'] === 'ditolak'): ?>
                                <div style="font-size:.7rem;margin-top:2px" class="text-danger"><?= htmlspecialchars($p['alasan_tolak']) ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($pendingList)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted">Tidak ada data pembayaran <?= $filterStatus ?>.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Lihat Bukti -->
<div class="modal fade" id="modalBukti" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bukti Transfer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center bg-light">
                <img src="" id="imgBukti" style="max-width:100%;max-height:70vh;object-fit:contain;">
            </div>
        </div>
    </div>
</div>

<!-- Modal Tolak -->
<div class="modal fade" id="modalTolak" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="proses.php" class="modal-content">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="id" id="tolak_id">
            
            <div class="modal-header">
                <h5 class="modal-title">Tolak Pembayaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Alasan Penolakan</label>
                    <textarea name="alasan" class="form-control" rows="3" required placeholder="Misal: Bukti transfer tidak valid/buram..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger">Tolak Pembayaran</button>
            </div>
        </form>
    </div>
</div>

<script>
function lihatBukti(url) {
    document.getElementById('imgBukti').src = url;
    new bootstrap.Modal(document.getElementById('modalBukti')).show();
}

function tolakPembayaran(id) {
    document.getElementById('tolak_id').value = id;
    new bootstrap.Modal(document.getElementById('modalTolak')).show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
