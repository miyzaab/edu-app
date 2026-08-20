<?php
/**
 * LAPORAN KEUANGAN - Filter & Export
 */
$pageTitle  = 'Laporan Keuangan';
$activePage = 'laporan';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

// Filter
$filterBulan = $_GET['bulan'] ?? '';
$filterTahun = $_GET['tahun'] ?? date('Y');
$filterJenis = $_GET['jenis'] ?? 'semua';

// Build query berdasarkan filter
$results = [];
$whereMonth = $filterBulan ? " AND MONTH(tanggal_bayar) = " . (int)$filterBulan : "";
$whereYear  = " AND YEAR(tanggal_bayar) = " . (int)$filterTahun;

// SPP
if ($filterJenis === 'semua' || $filterJenis === 'spp') {
    $rows = $pdo->query("
        SELECT spp.tanggal_bayar, s.nis, s.nama, k.nama_kelas, 'SPP' AS jenis,
               CONCAT('Bulan ', spp.bulan, '/', spp.tahun) AS detail,
               spp.nominal, spp.metode_bayar
        FROM pembayaran_spp spp
        JOIN siswa s ON spp.siswa_id = s.id
        JOIN kelas k ON s.kelas_id = k.id
        WHERE 1=1 $whereYear $whereMonth
    ")->fetchAll();
    $results = array_merge($results, $rows);
}

// Uang Pangkal
if ($filterJenis === 'semua' || $filterJenis === 'uang_pangkal') {
    $rows = $pdo->query("
        SELECT up.tanggal_bayar, s.nis, s.nama, k.nama_kelas, 'Uang Pangkal' AS jenis,
               '-' AS detail, up.nominal, up.metode_bayar
        FROM pembayaran_uang_pangkal up
        JOIN siswa s ON up.siswa_id = s.id
        JOIN kelas k ON s.kelas_id = k.id
        WHERE 1=1 $whereYear $whereMonth
    ")->fetchAll();
    $results = array_merge($results, $rows);
}

// Pembayaran Lain
if ($filterJenis === 'semua' || $filterJenis === 'lainnya') {
    $rows = $pdo->query("
        SELECT pl.tanggal_bayar, s.nis, s.nama, k.nama_kelas, jp.nama_pembayaran AS jenis,
               '-' AS detail, pl.nominal, pl.metode_bayar
        FROM pembayaran_lain pl
        JOIN siswa s ON pl.siswa_id = s.id
        JOIN kelas k ON s.kelas_id = k.id
        JOIN jenis_pembayaran jp ON pl.jenis_pembayaran_id = jp.id
        WHERE 1=1 $whereYear $whereMonth
    ")->fetchAll();
    $results = array_merge($results, $rows);
}

// Sort by tanggal DESC
usort($results, fn($a, $b) => strcmp($b['tanggal_bayar'], $a['tanggal_bayar']));

$totalNominal = array_sum(array_column($results, 'nominal'));

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Filter Bar -->
<div class="form-card mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label">Bulan</label>
            <select name="bulan" class="form-select form-select-sm">
                <option value="">Semua</option>
                <?php for ($m=1; $m<=12; $m++): ?>
                    <option value="<?= $m ?>" <?= $filterBulan==$m?'selected':'' ?>><?= namaBulan($m) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Tahun</label>
            <input type="number" name="tahun" class="form-control form-control-sm" value="<?= (int)$filterTahun ?>" min="2020" max="2099">
        </div>
        <div class="col-md-3">
            <label class="form-label">Jenis Pembayaran</label>
            <select name="jenis" class="form-select form-select-sm">
                <option value="semua" <?= $filterJenis==='semua'?'selected':'' ?>>Semua Jenis</option>
                <option value="spp" <?= $filterJenis==='spp'?'selected':'' ?>>SPP</option>
                <option value="uang_pangkal" <?= $filterJenis==='uang_pangkal'?'selected':'' ?>>Uang Pangkal</option>
                <option value="lainnya" <?= $filterJenis==='lainnya'?'selected':'' ?>>Pembayaran Lain</option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
        </div>
        <div class="col-md-5 text-end">
            <div class="d-flex gap-2 justify-content-end flex-wrap">
                <button type="button" onclick="exportToCSV('tableLaporan','laporan_keuangan_<?= $filterTahun ?>')" class="btn btn-sm btn-outline-secondary"><i class="bi bi-filetype-csv"></i> CSV</button>
                <a href="export-excel.php?bulan=<?= $filterBulan ?>&tahun=<?= $filterTahun ?>&jenis=<?= $filterJenis ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                <a href="export-pdf.php?bulan=<?= $filterBulan ?>&tahun=<?= $filterTahun ?>&jenis=<?= $filterJenis ?>" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
            </div>
        </div>
    </form>
</div>

<!-- Summary -->
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="stat-card">
            <div class="stat-label">Total Transaksi</div>
            <div class="stat-value"><?= count($results) ?></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card">
            <div class="stat-label">Total Pemasukan</div>
            <div class="stat-value" style="color:#198754"><?= formatRupiah($totalNominal) ?></div>
        </div>
    </div>
</div>

<!-- Table -->
<div class="table-card">
    <div class="card-header">
        <h5><i class="bi bi-file-earmark-spreadsheet"></i> Riwayat Pembayaran</h5>
    </div>
    <div class="table-responsive">
        <table class="data-table" id="tableLaporan">
            <thead>
                <tr><th>No</th><th>Tanggal</th><th>NIS</th><th>Nama</th><th>Kelas</th><th>Jenis</th><th>Detail</th><th>Metode</th><th>Nominal</th></tr>
            </thead>
            <tbody>
            <?php foreach ($results as $i => $r): ?>
                <tr>
                    <td class="text-muted font-monospace"><?= $i+1 ?></td>
                    <td class="text-muted small font-monospace"><?= formatTanggal($r['tanggal_bayar']) ?></td>
                    <td><code><?= htmlspecialchars($r['nis']) ?></code></td>
                    <td>
                        <div class="table-avatar-item">
                            <div class="table-avatar-circle"><?= strtoupper(substr($r['nama'], 0, 1)) ?></div>
                            <span class="fw-bold text-dark"><?= htmlspecialchars($r['nama']) ?></span>
                        </div>
                    </td>
                    <td><span class="badge bg-light text-dark border px-2.5 py-1 rounded-pill fw-semibold"><?= htmlspecialchars($r['nama_kelas']) ?></span></td>
                    <td><span class="badge-status badge-aktif"><?= htmlspecialchars($r['jenis']) ?></span></td>
                    <td class="text-muted small"><?= htmlspecialchars($r['detail']) ?></td>
                    <td><span class="badge bg-light text-secondary border rounded-pill px-2.5 py-1 fw-bold"><?= ucfirst($r['metode_bayar']) ?></span></td>
                    <td><span class="nominal-pill"><?= formatRupiah($r['nominal']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($results)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada data untuk filter yang dipilih.</td></tr>
            <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f8fafc; border-top: 2px solid #e2e8f0; font-weight: 700; color: #334155;">
                    <td colspan="8" class="text-end py-3.5 ps-4" style="color: #64748b; letter-spacing: 0.05em; font-size: 0.8rem; text-transform: uppercase;">Total Pemasukan:</td>
                    <td class="py-3.5 pe-4"><span class="nominal-pill" style="font-size: 0.95rem; padding: 0.35rem 0.85rem;"><?= formatRupiah($totalNominal) ?></span></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
