<?php
/**
 * PEMBAYARAN LAIN - List transaksi
 */
$pageTitle = 'Pembayaran Lain';
$activePage = 'pembayaran-lain';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();
$data = $pdo->query("
    SELECT pl.*, s.nis, s.nama, k.nama_kelas, jp.nama_pembayaran
    FROM pembayaran_lain pl
    JOIN siswa s ON pl.siswa_id = s.id
    JOIN kelas k ON s.kelas_id = k.id
    JOIN jenis_pembayaran jp ON pl.jenis_pembayaran_id = jp.id
    ORDER BY pl.tanggal_bayar DESC
")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Total: <strong><?= count($data) ?></strong> transaksi</p>
    <a href="create.php" class="btn-primary-custom"><i class="bi bi-plus-lg"></i> Input Pembayaran</a>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>NIS</th>
                    <th>Nama</th>
                    <th>Kelas</th>
                    <th>Jenis</th>
                    <th>Nominal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $i => $d): ?>
                    <tr>
                        <td class="text-muted font-monospace"><?= $i + 1 ?></td>
                        <td class="text-muted small font-monospace"><?= formatTanggal($d['tanggal_bayar']) ?></td>
                        <td><code><?= htmlspecialchars($d['nis']) ?></code></td>
                        <td>
                            <div class="table-avatar-item">
                                <div class="table-avatar-circle"><?= strtoupper(substr($d['nama'], 0, 1)) ?></div>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($d['nama']) ?></span>
                            </div>
                        </td>
                        <td><span
                                class="badge bg-light text-dark border px-2.5 py-1 rounded-pill fw-semibold"><?= htmlspecialchars($d['nama_kelas']) ?></span>
                        </td>
                        <td><span class="badge-status badge-aktif"><?= htmlspecialchars($d['nama_pembayaran']) ?></span>
                        </td>
                        <td><span class="nominal-pill"><?= formatRupiah($d['nominal']) ?></span></td>
                        <td><a href="kwitansi.php?id=<?= $d['id'] ?>" class="btn-sm-action btn-print" target="_blank"
                                title="Cetak Kwitansi"><i class="bi bi-printer"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($data)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Belum ada data.</td>
                    </tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>