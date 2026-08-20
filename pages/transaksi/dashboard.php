<?php
/**
 * DASHBOARD UTAMA (TRANSAKSI) - Ringkasan Keuangan & Transaksi Sekolah
 */
$pageTitle  = 'Dashboard Utama';
$activePage = 'dashboard-utama';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('dashboard_utama');

$pdo = getConnection();
$today = date('Y-m-d');
$bulanIni = (int)date('m');
$tahunIni = (int)date('Y');

// --- Total pemasukan HARI INI ---
$stmtToday = $pdo->prepare("
    SELECT COALESCE(SUM(nominal),0) AS total FROM (
        SELECT nominal FROM pembayaran_spp WHERE tanggal_bayar = :t1
        UNION ALL SELECT nominal FROM pembayaran_uang_pangkal WHERE tanggal_bayar = :t2
        UNION ALL SELECT nominal FROM pembayaran_lain WHERE tanggal_bayar = :t3
    ) AS combined
");
$stmtToday->execute([':t1'=>$today,':t2'=>$today,':t3'=>$today]);
$totalHariIni = $stmtToday->fetchColumn();

// --- Total pemasukan BULAN INI ---
$stmtMonth = $pdo->prepare("
    SELECT COALESCE(SUM(nominal),0) AS total FROM (
        SELECT nominal FROM pembayaran_spp WHERE MONTH(tanggal_bayar)=:m1 AND YEAR(tanggal_bayar)=:y1
        UNION ALL SELECT nominal FROM pembayaran_uang_pangkal WHERE MONTH(tanggal_bayar)=:m2 AND YEAR(tanggal_bayar)=:y2
        UNION ALL SELECT nominal FROM pembayaran_lain WHERE MONTH(tanggal_bayar)=:m3 AND YEAR(tanggal_bayar)=:y3
    ) AS combined
");
$stmtMonth->execute([':m1'=>$bulanIni,':y1'=>$tahunIni,':m2'=>$bulanIni,':y2'=>$tahunIni,':m3'=>$bulanIni,':y3'=>$tahunIni]);
$totalBulanIni = $stmtMonth->fetchColumn();

// --- Jumlah siswa aktif ---
$totalSiswa = $pdo->query("SELECT COUNT(*) FROM siswa WHERE status='aktif'")->fetchColumn();

// --- Sudah bayar SPP bulan ini ---
$stmtSudah = $pdo->prepare("SELECT COUNT(DISTINCT siswa_id) FROM pembayaran_spp WHERE bulan=:b AND tahun=:y");
$stmtSudah->execute([':b'=>$bulanIni,':y'=>$tahunIni]);
$sudahBayar = $stmtSudah->fetchColumn();
$belumBayar = $totalSiswa - $sudahBayar;

// --- Progres Uang Pangkal ---
$totalTargetUP = 0;
$totalReceivedUP = 0;
$upPercent = 0;

try {
    $stmtUP = $pdo->query("
        SELECT 
            SUM(target_uang_pangkal) as total_target,
            (SELECT SUM(nominal) FROM pembayaran_uang_pangkal) as total_received
        FROM siswa WHERE status = 'aktif'
    ");
    $upStats = $stmtUP->fetch();
    $totalTargetUP = $upStats['total_target'] ?? 0;
    $totalReceivedUP = $upStats['total_received'] ?? 0;
    $upPercent = ($totalTargetUP > 0) ? ($totalReceivedUP / $totalTargetUP) * 100 : 0;
} catch (PDOException $e) {
    $dbError = true;
}

// --- Transaksi terakhir (5 terbaru) ---
$recentTx = $pdo->query("
    SELECT s.nama, s.nis, spp.nominal, spp.tanggal_bayar, 'SPP' AS jenis,
           CONCAT('" . "Bulan ', spp.bulan, '/', spp.tahun) AS detail
    FROM pembayaran_spp spp JOIN siswa s ON spp.siswa_id=s.id
    UNION ALL
    SELECT s.nama, s.nis, up.nominal, up.tanggal_bayar, 'Uang Pangkal' AS jenis, '' AS detail
    FROM pembayaran_uang_pangkal up JOIN siswa s ON up.siswa_id=s.id
    UNION ALL
    SELECT s.nama, s.nis, pl.nominal, pl.tanggal_bayar, jp.nama_pembayaran AS jenis, '' AS detail
    FROM pembayaran_lain pl JOIN siswa s ON pl.siswa_id=s.id JOIN jenis_pembayaran jp ON pl.jenis_pembayaran_id=jp.id
    ORDER BY tanggal_bayar DESC LIMIT 5
")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--gradient-primary)"><i class="bi bi-cash"></i></div>
            <div class="stat-label">Pemasukan Hari Ini</div>
            <div class="stat-value"><?= formatRupiah($totalHariIni) ?></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--gradient-green)"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="stat-label">Pemasukan Bulan Ini</div>
            <div class="stat-value"><?= formatRupiah($totalBulanIni) ?></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--gradient-blue)"><i class="bi bi-check-circle"></i></div>
            <div class="stat-label">Sudah Bayar SPP</div>
            <div class="stat-value"><?= $sudahBayar ?> <small class="text-muted" style="font-size:.7rem">/ <?= $totalSiswa ?></small></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--gradient-orange)"><i class="bi bi-exclamation-circle"></i></div>
            <div class="stat-label">Belum Bayar SPP</div>
            <div class="stat-value"><?= $belumBayar ?></div>
        </div>
    </div>
</div>

<?php if (isset($dbError) && $dbError): ?>
<div class="alert alert-danger shadow-sm border-0 mb-4 d-flex align-items-center justify-content-between">
    <div>
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Database belum diperbarui!</strong> Fitur Uang Pangkal memerlukan kolom tambahan di tabel siswa.
    </div>
    <a href="<?= BASE_URL ?>/update_db_feature.php" class="btn btn-danger btn-sm fw-bold px-3">Update Database Sekarang</a>
</div>
<?php endif; ?>

<!-- Uang Pangkal Progress Card -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold m-0"><i class="bi bi-display text-primary"></i> Progres Penerimaan Uang Pangkal</h6>
                    <a href="<?= BASE_URL ?>/pages/uang-pangkal/monitoring.php" class="btn btn-sm btn-light">Detail Monitoring</a>
                </div>
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <div class="mb-1 text-muted small">Sudah Diterima</div>
                        <div class="h4 fw-bold text-success mb-0"><?= formatRupiah($totalReceivedUP) ?></div>
                        <div class="text-muted small">dari target <?= formatRupiah($totalTargetUP) ?></div>
                    </div>
                    <div class="col-md-8">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small fw-bold"><?= round($upPercent, 1) ?>%</span>
                            <span class="small text-muted">Sisa <?= formatRupiah($totalTargetUP - $totalReceivedUP) ?></span>
                        </div>
                        <div class="progress" style="height: 15px; border-radius: 10px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: <?= $upPercent ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transaksi Terakhir -->
<div class="table-card">
    <div class="card-header">
        <h5><i class="bi bi-clock-history"></i> Transaksi Terakhir</h5>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tanggal</th><th>NIS</th><th>Nama Siswa</th><th>Jenis</th><th>Detail</th><th>Nominal</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentTx)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada transaksi.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentTx as $tx): ?>
                    <tr>
                        <td class="text-muted small font-monospace"><?= formatTanggal($tx['tanggal_bayar']) ?></td>
                        <td><code><?= htmlspecialchars($tx['nis']) ?></code></td>
                        <td>
                            <div class="table-avatar-item">
                                <div class="table-avatar-circle"><?= strtoupper(substr($tx['nama'], 0, 1)) ?></div>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($tx['nama']) ?></span>
                            </div>
                        </td>
                        <td><span class="badge-status badge-lunas"><?= htmlspecialchars($tx['jenis']) ?></span></td>
                        <td class="text-muted small"><?= htmlspecialchars($tx['detail']) ?></td>
                        <td><span class="nominal-pill"><?= formatRupiah($tx['nominal']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
