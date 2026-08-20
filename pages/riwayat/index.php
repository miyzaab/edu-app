<?php
/**
 * RIWAYAT PEMBAYARAN - List semua transaksi terbaru
 */
$pageTitle  = 'Riwayat Pembayaran';
$activePage = 'riwayat';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

// Filter & Pagination
$limit = 50;
$page  = (int)($_GET['page'] ?? 1);
$offset = ($page - 1) * $limit;

// Query gabungan (UNION) untuk semua jenis pembayaran
$query = "
    SELECT t.*, s.nama, s.nis, k.nama_kelas 
    FROM (
        SELECT 'spp' as tipe, id, siswa_id, nominal, tanggal_bayar, metode_bayar, keterangan FROM pembayaran_spp
        UNION ALL
        SELECT 'uang_pangkal', id, siswa_id, nominal, tanggal_bayar, metode_bayar, keterangan FROM pembayaran_uang_pangkal
        UNION ALL
        SELECT 'lainnya', id, siswa_id, nominal, tanggal_bayar, metode_bayar, keterangan FROM pembayaran_lain
    ) t
    JOIN siswa s ON t.siswa_id = s.id
    JOIN kelas k ON s.kelas_id = k.id
    ORDER BY t.tanggal_bayar DESC, t.id DESC
    LIMIT $limit OFFSET $offset
";

$riwayat = $pdo->query($query)->fetchAll();

// Hitung total untuk pagination simple
$total = $pdo->query("
    SELECT (SELECT COUNT(*) FROM pembayaran_spp) + 
           (SELECT COUNT(*) FROM pembayaran_uang_pangkal) + 
           (SELECT COUNT(*) FROM pembayaran_lain)
")->fetchColumn();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Menampilkan <strong><?= count($riwayat) ?></strong> transaksi terakhir dari total <strong><?= number_format($total) ?></strong></p>
    <div class="btn-group">
        <a href="../spp/index.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i> Bayar SPP</a>
    </div>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Nama Siswa</th>
                    <th>Kelas</th>
                    <th>Jenis</th>
                    <th>Nominal</th>
                    <th>Metode</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($riwayat as $i => $r): ?>
                <tr>
                    <td class="text-muted font-monospace"><?= $offset + $i + 1 ?></td>
                    <td class="text-muted small font-monospace"><?= formatTanggal($r['tanggal_bayar']) ?></td>
                    <td>
                        <div class="table-avatar-item">
                            <div class="table-avatar-circle"><?= strtoupper(substr($r['nama'], 0, 1)) ?></div>
                            <div>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($r['nama']) ?></div>
                                <small class="text-muted font-monospace"><i class="bi bi-hash opacity-50"></i><?= htmlspecialchars($r['nis']) ?></small>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge bg-light text-dark border px-2.5 py-1 rounded-pill fw-semibold"><?= htmlspecialchars($r['nama_kelas']) ?></span></td>
                    <td>
                        <?php if ($r['tipe'] === 'spp'): ?>
                            <span class="badge rounded-pill bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2.5 py-1 font-monospace fw-bold" style="font-size: 0.73rem;"><i class="bi bi-calendar-check me-1"></i> SPP</span>
                        <?php elseif ($r['tipe'] === 'uang_pangkal'): ?>
                            <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2.5 py-1 font-monospace fw-bold" style="font-size: 0.73rem;"><i class="bi bi-bank me-1"></i> Uang Pangkal</span>
                        <?php else: ?>
                            <span class="badge rounded-pill bg-purple bg-opacity-10 text-purple border border-purple border-opacity-25 px-2.5 py-1 font-monospace fw-bold" style="font-size: 0.73rem; background: rgba(168, 85, 247, 0.1); color: #9333ea; border: 1px solid rgba(168, 85, 247, 0.2);"><i class="bi bi-tags me-1"></i> Lainnya</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="nominal-pill"><?= formatRupiah($r['nominal']) ?></span></td>
                    <td><span class="badge bg-light text-secondary border rounded-pill px-2.5 py-1 fw-bold"><?= ucfirst($r['metode_bayar']) ?></span></td>
                    <td class="text-center">
                        <?php 
                        $printPath = match($r['tipe']) {
                            'spp' => '../spp/kwitansi.php',
                            'uang_pangkal' => '../uang-pangkal/kwitansi.php',
                            default => '../pembayaran-lain/kwitansi.php'
                        };
                        ?>
                        <a href="<?= $printPath ?>?id=<?= $r['id'] ?>" class="btn-sm-action btn-print" target="_blank" title="Cetak Kwitansi">
                            <i class="bi bi-printer"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($riwayat)): ?>
                <tr><td colspan="8" class="text-center py-5 text-muted">Belum ada transaksi pembayaran.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($total > $limit): ?>
<div class="d-flex justify-content-center mt-4">
    <nav>
        <ul class="pagination pagination-sm">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?>">Prev</a>
            </li>
            <li class="page-item disabled"><span class="page-link">Hal <?= $page ?></span></li>
            <li class="page-item <?= ($page * $limit) >= $total ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
            </li>
        </ul>
    </nav>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
