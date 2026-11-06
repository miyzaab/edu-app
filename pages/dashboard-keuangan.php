<?php
/**
 * DASHBOARD KEUANGAN - Detail Statistik Keuangan & Transaksi Terakhir
 */
$pageTitle = 'Dashboard Keuangan';
$activePage = 'dashboard-keuangan';
require_once __DIR__ . '/../config/auth.php';

$pdo = getConnection();
$today = date('Y-m-d');
$bulanIni = (int) date('m');
$tahunIni = (int) date('Y');

// 1. Total pemasukan HARI INI (Gunakan DATE(tanggal_bayar) agar akurat)
$stmtToday = $pdo->prepare("
    SELECT COALESCE(SUM(nominal),0) AS total FROM (
        SELECT nominal, tanggal_bayar FROM pembayaran_spp WHERE DATE(tanggal_bayar) = :t1
        UNION ALL 
        SELECT nominal, tanggal_bayar FROM pembayaran_uang_pangkal WHERE DATE(tanggal_bayar) = :t2
        UNION ALL 
        SELECT nominal, tanggal_bayar FROM pembayaran_lain WHERE DATE(tanggal_bayar) = :t3
    ) AS combined
");
$stmtToday->execute([':t1' => $today, ':t2' => $today, ':t3' => $today]);
$totalHariIni = (float) $stmtToday->fetchColumn();

// 2. Total pemasukan BULAN INI
$stmtMonth = $pdo->prepare("
    SELECT COALESCE(SUM(nominal),0) AS total FROM (
        SELECT nominal, tanggal_bayar FROM pembayaran_spp WHERE MONTH(tanggal_bayar) = :m1 AND YEAR(tanggal_bayar) = :y1
        UNION ALL 
        SELECT nominal, tanggal_bayar FROM pembayaran_uang_pangkal WHERE MONTH(tanggal_bayar) = :m2 AND YEAR(tanggal_bayar) = :y2
        UNION ALL 
        SELECT nominal, tanggal_bayar FROM pembayaran_lain WHERE MONTH(tanggal_bayar) = :m3 AND YEAR(tanggal_bayar) = :y3
    ) AS combined
");
$stmtMonth->execute([':m1' => $bulanIni, ':y1' => $tahunIni, ':m2' => $bulanIni, ':y2' => $tahunIni, ':m3' => $bulanIni, ':y3' => $tahunIni]);
$totalBulanIni = (float) $stmtMonth->fetchColumn();

// 3. Jumlah siswa aktif
$totalSiswa = (int) $pdo->query("SELECT COUNT(*) FROM siswa WHERE status = 'aktif'")->fetchColumn();

// 4. Sudah & Belum bayar SPP bulan ini (Siswa Aktif)
$stmtSudah = $pdo->prepare("
    SELECT COUNT(DISTINCT ps.siswa_id) 
    FROM pembayaran_spp ps 
    JOIN siswa s ON ps.siswa_id = s.id 
    WHERE s.status = 'aktif' AND ps.bulan = :b AND ps.tahun = :y
");
$stmtSudah->execute([':b' => $bulanIni, ':y' => $tahunIni]);
$sudahBayar = (int) $stmtSudah->fetchColumn();
$belumBayar = max(0, $totalSiswa - $sudahBayar);

// 5. Progres Penerimaan Uang Pangkal Siswa Aktif
$totalTargetUP = 0;
$totalReceivedUP = 0;
$upPercent = 0;

try {
    $stmtUP = $pdo->query("
        SELECT 
            COALESCE(SUM(s.target_uang_pangkal), 0) AS total_target,
            COALESCE(SUM(up.total_bayar), 0) AS total_received
        FROM siswa s
        LEFT JOIN (
            SELECT siswa_id, SUM(nominal) AS total_bayar 
            FROM pembayaran_uang_pangkal 
            GROUP BY siswa_id
        ) up ON s.id = up.siswa_id
        WHERE s.status = 'aktif'
    ");
    $upStats = $stmtUP->fetch();
    $totalTargetUP = (float) ($upStats['total_target'] ?? 0);
    $totalReceivedUP = (float) ($upStats['total_received'] ?? 0);
    $upPercent = ($totalTargetUP > 0) ? ($totalReceivedUP / $totalTargetUP) * 100 : 0;
} catch (PDOException $e) {
    $dbError = true;
}

// 6. Transaksi Terakhir (Sinkron 100% dengan Riwayat Pembayaran)
$totalTxAll = (int) $pdo->query("
    SELECT (SELECT COUNT(*) FROM pembayaran_spp) + 
           (SELECT COUNT(*) FROM pembayaran_uang_pangkal) + 
           (SELECT COUNT(*) FROM pembayaran_lain)
")->fetchColumn();

$recentTx = $pdo->query("
    SELECT t.*, s.nama, s.nis, k.nama_kelas 
    FROM (
        SELECT 'spp' AS tipe, 'SPP' AS jenis_label, id, siswa_id, nominal, tanggal_bayar, metode_bayar, keterangan, 
               CONCAT('Bulan ', bulan, '/', tahun) AS detail
        FROM pembayaran_spp
        UNION ALL
        SELECT 'uang_pangkal' AS tipe, 'Uang Pangkal' AS jenis_label, id, siswa_id, nominal, tanggal_bayar, metode_bayar, keterangan,
               COALESCE(NULLIF(keterangan, ''), 'Uang Pangkal') AS detail
        FROM pembayaran_uang_pangkal
        UNION ALL
        SELECT 'lainnya' AS tipe, COALESCE(jp.nama_pembayaran, 'Pembayaran Lain') AS jenis_label, pl.id, pl.siswa_id, pl.nominal, pl.tanggal_bayar, pl.metode_bayar, pl.keterangan,
               COALESCE(NULLIF(pl.keterangan, ''), jp.nama_pembayaran, 'Lainnya') AS detail
        FROM pembayaran_lain pl
        LEFT JOIN jenis_pembayaran jp ON pl.jenis_pembayaran_id = jp.id
    ) t
    JOIN siswa s ON t.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    ORDER BY t.tanggal_bayar DESC, t.id DESC
    LIMIT 10
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-extrabold m-0" style="font-family: 'Outfit', sans-serif;"><i
                class="bi bi-speedometer2 text-primary me-2"></i> Dashboard Keuangan</h4>
        <small class="text-muted">Ringkasan &amp; statistik lengkap transaksi keuangan sekolah</small>
    </div>
    <a href="<?= BASE_URL ?>/pages/dashboard.php" class="btn btn-outline-secondary rounded-pill px-3 fw-bold btn-sm">
        <i class="bi bi-grid-fill me-1"></i> Ke Portal Utama
    </a>
</div>

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
            <div class="stat-value"><?= $sudahBayar ?> <small class="text-muted" style="font-size:.7rem">/
                    <?= $totalSiswa ?></small></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--gradient-orange)"><i class="bi bi-exclamation-circle"></i>
            </div>
            <div class="stat-label">Belum Bayar SPP</div>
            <div class="stat-value"><?= $belumBayar ?></div>
        </div>
    </div>
</div>

<!-- Uang Pangkal Progress Card -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold m-0"><i class="bi bi-display text-primary"></i> Progres Penerimaan Uang Pangkal
                    </h6>
                    <a href="<?= BASE_URL ?>/pages/uang-pangkal/monitoring.php" class="btn btn-sm btn-light">Detail
                        Monitoring</a>
                </div>
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <div class="mb-1 text-muted small">Sudah Diterima</div>
                        <div class="h4 fw-bold text-success mb-0"><?= formatRupiah($totalReceivedUP) ?></div>
                        <div class="text-muted small">dari target <?= formatRupiah($totalTargetUP) ?></div>
                    </div>
                    <div class="col-md-8">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small fw-bold"><?= number_format($upPercent, 1, ',', '.') ?>%</span>
                            <?php if ($totalReceivedUP >= $totalTargetUP && $totalTargetUP > 0): ?>
                                <span class="small text-success fw-bold"><i class="bi bi-check2-circle"></i> Target Tercapai
                                    (100%)</span>
                            <?php else: ?>
                                <span class="small text-muted">Sisa
                                    <?= formatRupiah(max(0, $totalTargetUP - $totalReceivedUP)) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="progress" style="height: 15px; border-radius: 10px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                                role="progressbar" style="width: <?= min(100, max(0, $upPercent)) ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transaksi Terakhir (Sinkron Penuh dengan Riwayat Pembayaran) -->
<div class="table-card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="m-0"><i class="bi bi-clock-history me-2"></i> Transaksi Terakhir</h5>
        <a href="<?= BASE_URL ?>/pages/riwayat/index.php"
            class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
            <i class="bi bi-arrow-right-circle me-1"></i> Lihat Semua (<?= number_format($totalTxAll) ?>)
        </a>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Nama Santri</th>
                    <th>Kelas</th>
                    <th>Jenis</th>
                    <th>Detail</th>
                    <th>Nominal</th>
                    <th>Metode</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentTx)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Belum ada transaksi pembayaran.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentTx as $tx): ?>
                        <tr>
                            <td class="text-muted small font-monospace"><?= formatTanggal($tx['tanggal_bayar']) ?></td>
                            <td>
                                <div class="table-avatar-item">
                                    <div class="table-avatar-circle"><?= strtoupper(substr($tx['nama'], 0, 1)) ?></div>
                                    <div>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($tx['nama']) ?></div>
                                        <small class="text-muted font-monospace"><i
                                                class="bi bi-hash opacity-50"></i><?= htmlspecialchars($tx['nis']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2.5 py-1 rounded-pill fw-semibold">
                                    <?= htmlspecialchars($tx['nama_kelas'] ?? '-') ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($tx['tipe'] === 'spp'): ?>
                                    <span
                                        class="badge rounded-pill bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2.5 py-1 font-monospace fw-bold"
                                        style="font-size: 0.73rem;">
                                        <i class="bi bi-calendar-check me-1"></i> SPP
                                    </span>
                                <?php elseif ($tx['tipe'] === 'uang_pangkal'): ?>
                                    <span
                                        class="badge rounded-pill bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2.5 py-1 font-monospace fw-bold"
                                        style="font-size: 0.73rem;">
                                        <i class="bi bi-bank me-1"></i> Uang Pangkal
                                    </span>
                                <?php else: ?>
                                    <span class="badge rounded-pill px-2.5 py-1 font-monospace fw-bold"
                                        style="font-size: 0.73rem; background: rgba(168, 85, 247, 0.1); color: #9333ea; border: 1px solid rgba(168, 85, 247, 0.2);">
                                        <i class="bi bi-tags me-1"></i> <?= htmlspecialchars($tx['jenis_label']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= htmlspecialchars($tx['detail']) ?></td>
                            <td><span class="nominal-pill"><?= formatRupiah($tx['nominal']) ?></span></td>
                            <td>
                                <span class="badge bg-light text-secondary border rounded-pill px-2.5 py-1 fw-bold">
                                    <?= ucfirst($tx['metode_bayar'] ?? 'Tunai') ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php
                                $printPath = match ($tx['tipe']) {
                                    'spp' => '../spp/kwitansi.php?id=' . $tx['id'],
                                    'uang_pangkal' => '../uang-pangkal/kwitansi.php?id=' . $tx['id'],
                                    default => '../pembayaran-lain/kwitansi.php?id=' . $tx['id']
                                };
                                ?>
                                <a href="<?= $printPath ?>" target="_blank" class="btn-sm-action btn-detail"
                                    title="Cetak Kwitansi">
                                    <i class="bi bi-printer"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>