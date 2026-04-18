<?php
/**
 * UANG PANGKAL - List pembayaran
 */
$pageTitle  = 'Uang Pangkal';
$activePage = 'uang-pangkal';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();
$data = $pdo->query("
    SELECT up.*, s.nis, s.nama, k.nama_kelas
    FROM pembayaran_uang_pangkal up
    JOIN siswa s ON up.siswa_id = s.id
    JOIN kelas k ON s.kelas_id = k.id
    ORDER BY up.tanggal_bayar DESC
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
            <thead><tr><th>No</th><th>Tanggal</th><th>NIS</th><th>Nama</th><th>Kelas</th><th>Nominal</th><th>Metode</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($data as $i => $d): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= formatTanggal($d['tanggal_bayar']) ?></td>
                    <td><code><?= htmlspecialchars($d['nis']) ?></code></td>
                    <td><?= htmlspecialchars($d['nama']) ?></td>
                    <td><?= htmlspecialchars($d['nama_kelas']) ?></td>
                    <td><strong><?= formatRupiah($d['nominal']) ?></strong></td>
                    <td><?= ucfirst($d['metode_bayar']) ?></td>
                    <td><a href="kwitansi.php?id=<?= $d['id'] ?>" class="btn-sm-action btn-print" target="_blank"><i class="bi bi-printer"></i></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($data)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Belum ada data.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
