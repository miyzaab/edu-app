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

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div class="modern-filter-nav">
        <a class="modern-filter-btn <?= $filterStatus === 'pending' ? 'active' : '' ?>" href="?status=pending">
            <i class="bi bi-hourglass-split me-1 text-warning"></i> Pending
            <?php if ($countPending > 0): ?>
                <span class="modern-filter-badge bg-warning text-dark ms-1"><?= $countPending ?></span>
            <?php endif; ?>
        </a>
        <a class="modern-filter-btn <?= $filterStatus === 'disetujui' ? 'active' : '' ?>" href="?status=disetujui">
            <i class="bi bi-check-circle-fill me-1 text-success"></i> Disetujui
        </a>
        <a class="modern-filter-btn <?= $filterStatus === 'ditolak' ? 'active' : '' ?>" href="?status=ditolak">
            <i class="bi bi-x-circle-fill me-1 text-danger"></i> Ditolak
        </a>
    </div>
    <div class="text-muted small">
        Total Filtered: <strong class="text-dark fw-bold"><?= count($pendingList) ?></strong> data
    </div>
</div>

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
                    <td class="text-muted small font-monospace"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
                    <td>
                        <div class="table-avatar-item">
                            <div class="table-avatar-circle"><?= strtoupper(substr($p['nama'], 0, 1)) ?></div>
                            <div>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($p['nama']) ?></div>
                                <small class="text-muted font-monospace"><i class="bi bi-person-badge opacity-50 me-1"></i><?= htmlspecialchars($p['nama_kelas']) ?> • <?= htmlspecialchars($p['nis']) ?></small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php 
                        if ($p['jenis'] === 'spp') echo '<span class="badge rounded-pill bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2.5 py-1 font-monospace fw-bold"><i class="bi bi-calendar-check me-1"></i> SPP (' . namaBulan($p['bulan']) . ' ' . $p['tahun'] . ')</span>';
                        elseif ($p['jenis'] === 'uang_pangkal') echo '<span class="badge rounded-pill bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2.5 py-1 font-monospace fw-bold"><i class="bi bi-bank me-1"></i> Uang Pangkal</span>';
                        elseif ($p['jenis'] === 'lainnya') echo '<span class="badge rounded-pill bg-purple bg-opacity-10 text-purple border border-purple border-opacity-25 px-2.5 py-1 font-monospace fw-bold" style="background: rgba(168, 85, 247, 0.1); color: #9333ea; border: 1px solid rgba(168, 85, 247, 0.2);"><i class="bi bi-tags me-1"></i> ' . htmlspecialchars($p['nama_pembayaran']) . '</span>';
                        ?>
                    </td>
                    <td><span class="nominal-pill"><?= formatRupiah($p['nominal']) ?></span></td>
                    <td style="max-width:160px;white-space:normal;font-size:.82rem" class="text-muted"><?= htmlspecialchars($p['catatan']) ?: '<span class="opacity-50">-</span>' ?></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-light border rounded-pill px-3 fw-bold text-dark shadow-sm" onclick="lihatBukti('<?= htmlspecialchars($p['bukti_transfer']) ?>')">
                            <i class="bi bi-image text-primary me-1"></i> Lihat
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
                            <div class="d-flex flex-column gap-1 align-items-center">
                                <span class="badge <?= $p['status'] === 'disetujui' ? 'bg-success' : 'bg-danger' ?>">
                                    <?= ucfirst($p['status']) ?>
                                </span>
                                <?php if ($p['status'] === 'disetujui'): 
                                    $template = getSetting('wa_share_template', "Halo Bapak/Ibu Wali Murid {nama},\n\nBerikut adalah rincian pembayaran Anda di {sekolah}:\n\n*{judul}*\nNo: {no}\nTotal: *{nominal}*\nStatus: *LUNAS*\n\nLink Kwitansi Digital: {link}\n\nTerima kasih.");
                                    
                                    $waNama    = $p['nama'];
                                    $waSekolah = getSetting('nama_sekolah', SCHOOL_NAME);
                                    $waJudul   = ($p['jenis'] === 'spp' ? "SPP " . namaBulan($p['bulan']) . " " . $p['tahun'] : ($p['jenis'] === 'uang_pangkal' ? "Uang Pangkal" : $p['nama_pembayaran']));
                                    $waNominal = formatRupiah($p['nominal']);
                                    $waNo      = "PEND-" . $p['id']; // ID Pending sebagai referensi
                                    $waLink    = BASE_URL . "/portal-ortu.php";

                                    $waMsg = str_replace(
                                        ['{nama}', '{sekolah}', '{judul}', '{no}', '{nominal}', '{link}'],
                                        [$waNama, $waSekolah, $waJudul, $waNo, $waNominal, $waLink],
                                        $template
                                    );
                                ?>
                                    <a href="https://wa.me/?text=<?= urlencode($waMsg) ?>" target="_blank" class="btn btn-sm btn-outline-success" style="font-size:.7rem;padding:2px 5px">
                                        <i class="bi bi-whatsapp"></i> Notify WA
                                    </a>
                                <?php endif; ?>
                                <?php if ($p['status'] === 'ditolak'): ?>
                                    <div style="font-size:.7rem;margin-top:2px" class="text-danger"><?= htmlspecialchars($p['alasan_tolak']) ?></div>
                                <?php endif; ?>
                            </div>
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
