<?php
/**
 * UANG PANGKAL - Monitoring Progres Pembayaran Siswa
 */
$pageTitle  = 'Monitoring Uang Pangkal';
$activePage = 'uang-pangkal';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

try {
    // Ambil data progres per siswa
    $data = $pdo->query("
        SELECT 
            s.id, s.nis, s.nama, k.nama_kelas, s.target_uang_pangkal,
            COALESCE(SUM(up.nominal), 0) as total_bayar
        FROM siswa s
        JOIN kelas k ON s.kelas_id = k.id
        LEFT JOIN pembayaran_uang_pangkal up ON s.id = up.siswa_id
        WHERE s.status = 'aktif'
        GROUP BY s.id
        ORDER BY k.nama_kelas, s.nama
    ")->fetchAll();
} catch (PDOException $e) {
    redirect(BASE_URL . '/pages/dashboard.php', 'danger', 'Database belum diperbarui. Harap jalankan update_db_feature.php di root folder.');
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold m-0"><i class="bi bi-display text-primary"></i> Monitoring Pembayaran Uang Pangkal</h5>
    <div class="d-flex gap-2">
        <a href="../siswa/bulk_uang_pangkal.php" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1"><i class="bi bi-people-fill"></i> Update Massal / Lunas</a>
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Riwayat Transaksi</a>
        <a href="create.php" class="btn-primary-custom btn-sm"><i class="bi bi-plus-lg"></i> Input Pembayaran</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php
    $totalTarget = 0;
    $totalPaid = 0;
    $lunasCount = 0;
    foreach ($data as $d) {
        $totalTarget += $d['target_uang_pangkal'];
        $totalPaid += $d['total_bayar'];
        if ($d['target_uang_pangkal'] > 0 && $d['total_bayar'] >= $d['target_uang_pangkal']) $lunasCount++;
    }
    $overallPercent = ($totalTarget > 0) ? ($totalPaid / $totalTarget) * 100 : 0;
    ?>
    <div class="col-md-4">
        <div class="stat-card shadow-sm border-0">
            <div class="stat-label">Total Target Penerimaan</div>
            <div class="stat-value text-primary"><?= formatRupiah($totalTarget) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card shadow-sm border-0">
            <div class="stat-label">Total Sudah Diterima</div>
            <div class="stat-value text-success"><?= formatRupiah($totalPaid) ?> (<?= round($overallPercent, 1) ?>%)</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card shadow-sm border-0">
            <div class="stat-label">Siswa Lunas</div>
            <div class="stat-value text-info"><?= $lunasCount ?> <small class="text-muted" style="font-size: .8rem">/ <?= count($data) ?></small></div>
        </div>
    </div>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="data-table" id="tableMonitoring">
            <thead>
                <tr>
                    <th>No</th>
                    <th>NIS</th>
                    <th>Nama Siswa</th>
                    <th>Kelas</th>
                    <th>Target Tagihan</th>
                    <th>Total Bayar</th>
                    <th>Sisa</th>
                    <th width="150">Progres</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($data as $i => $d): 
                $sisa = $d['target_uang_pangkal'] - $d['total_bayar'];
                if ($sisa < 0) $sisa = 0;
                $percent = ($d['target_uang_pangkal'] > 0) ? ($d['total_bayar'] / $d['target_uang_pangkal']) * 100 : 0;
                if ($percent > 100) $percent = 100;
                $isLunas = ($d['target_uang_pangkal'] > 0 && $sisa <= 0);
            ?>
                <tr>
                    <td class="text-muted font-monospace"><?= $i+1 ?></td>
                    <td><code><?= htmlspecialchars($d['nis']) ?></code></td>
                    <td>
                        <div class="table-avatar-item">
                            <div class="table-avatar-circle"><?= strtoupper(substr($d['nama'], 0, 1)) ?></div>
                            <span class="fw-bold text-dark"><?= htmlspecialchars($d['nama']) ?></span>
                        </div>
                    </td>
                    <td><span class="badge bg-light text-dark border px-2.5 py-1 rounded-pill fw-semibold"><?= htmlspecialchars($d['nama_kelas']) ?></span></td>
                    <td class="text-muted"><?= formatRupiah($d['target_uang_pangkal']) ?></td>
                    <td><span class="nominal-pill"><?= formatRupiah($d['total_bayar']) ?></span></td>
                    <td><span class="text-danger fw-semibold"><?= formatRupiah($sisa) ?></span></td>
                    <td>
                        <div class="progress" style="height: 6px; border-radius: 999px; background-color: #f1f5f9;">
                            <div class="progress-bar <?= $isLunas ? 'bg-success' : 'bg-primary' ?>" role="progressbar" style="width: <?= $percent ?>%; border-radius: 999px;"></div>
                        </div>
                        <small class="text-muted fw-bold" style="font-size: .7rem"><?= round($percent, 1) ?>%</small>
                    </td>
                    <td>
                        <?php if ($isLunas): ?>
                            <span class="badge-status badge-lunas">Lunas</span>
                        <?php elseif ($d['total_bayar'] > 0): ?>
                            <span class="badge-status badge-pending">Mencicil</span>
                        <?php else: ?>
                            <span class="badge-status badge-belum">Belum Bayar</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
