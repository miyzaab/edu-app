<?php
/**
 * PETTY CASH - Buku Kas Umum
 */
$pageTitle  = 'Petty Cash (Buku Kas)';
$activePage = 'petty-cash';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

// Filter
$filterBulan = (int)($_GET['bulan'] ?? date('m'));
$filterTahun = (int)($_GET['tahun'] ?? date('Y'));

// Saldo Awal (Total Masuk - Total Keluar sebelum bulan yang dipilih)
// Kita hitung semua transaksi sebelum tanggal 1 pada bulan&tahun filter
$startDate = "$filterTahun-" . str_pad($filterBulan, 2, '0', STR_PAD_LEFT) . "-01";

$stmtSaldoAwal = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN jenis = 'masuk' THEN nominal ELSE 0 END), 0) -
        COALESCE(SUM(CASE WHEN jenis = 'keluar' THEN nominal ELSE 0 END), 0) AS saldo_awal
    FROM petty_cash 
    WHERE tanggal < :start_date
");
$stmtSaldoAwal->execute([':start_date' => $startDate]);
$saldoAwal = $stmtSaldoAwal->fetchColumn() ?: 0;

// Transaksi Bulan Ini
$stmtTransaksi = $pdo->prepare("
    SELECT pc.*, u.nama_lengkap 
    FROM petty_cash pc
    JOIN users u ON pc.user_id = u.id
    WHERE MONTH(pc.tanggal) = :bulan AND YEAR(pc.tanggal) = :tahun
    ORDER BY pc.tanggal ASC, pc.id ASC
");
$stmtTransaksi->execute([':bulan' => $filterBulan, ':tahun' => $filterTahun]);
$transaksi = $stmtTransaksi->fetchAll();

// Hitung Total Masuk & Keluar bulan ini
$totalMasuk = 0;
$totalKeluar = 0;
foreach ($transaksi as $t) {
    if ($t['jenis'] === 'masuk') $totalMasuk += $t['nominal'];
    if ($t['jenis'] === 'keluar') $totalKeluar += $t['nominal'];
}
$saldoAkhir = $saldoAwal + $totalMasuk - $totalKeluar;

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-3 mb-2">
        <div class="form-card text-center h-100 bg-light">
            <h6 class="text-muted mb-2">Saldo Awal</h6>
            <h4 class="text-dark fw-bold mb-0"><?= formatRupiah($saldoAwal) ?></h4>
        </div>
    </div>
    <div class="col-md-3 mb-2">
        <div class="form-card text-center h-100 bg-success text-white">
            <h6 class="mb-2 opacity-75">Total Masuk (Bulan Ini)</h6>
            <h4 class="fw-bold mb-0">+ <?= formatRupiah($totalMasuk) ?></h4>
        </div>
    </div>
    <div class="col-md-3 mb-2">
        <div class="form-card text-center h-100 bg-danger text-white">
            <h6 class="mb-2 opacity-75">Total Keluar (Bulan Ini)</h6>
            <h4 class="fw-bold mb-0">- <?= formatRupiah($totalKeluar) ?></h4>
        </div>
    </div>
    <div class="col-md-3 mb-2">
        <div class="form-card text-center h-100 bg-primary text-white">
            <h6 class="mb-2 opacity-75">Saldo Akhir</h6>
            <h4 class="fw-bold mb-0"><?= formatRupiah($saldoAkhir) ?></h4>
        </div>
    </div>
</div>

<div class="form-card mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <form method="GET" class="d-flex gap-2">
            <select name="bulan" class="form-select form-select-sm" style="width: 140px;">
                <?php for ($m=1; $m<=12; $m++): ?>
                    <option value="<?= $m ?>" <?= $filterBulan==$m?'selected':'' ?>><?= namaBulan($m) ?></option>
                <?php endfor; ?>
            </select>
            <input type="number" name="tahun" class="form-control form-control-sm" value="<?= $filterTahun ?>" style="width: 90px;" min="2020" max="2099">
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i> Filter</button>
        </form>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" onclick="exportToCSV('tablePettyCash', 'Buku_Kas_<?= namaBulan($filterBulan) ?>_<?= $filterTahun ?>')" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-excel"></i> Export Excel</button>
            <a href="print.php?bulan=<?= $filterBulan ?>&tahun=<?= $filterTahun ?>" target="_blank" class="btn btn-sm btn-danger"><i class="bi bi-file-earmark-pdf"></i> Cetak PDF</a>
            <a href="create.php" class="btn btn-sm btn-primary-custom"><i class="bi bi-plus-lg"></i> Tambah Transaksi</a>
        </div>
    </div>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="tablePettyCash">
            <thead class="table-light">
                <tr>
                    <th>Tanggal</th>
                    <th>Keterangan</th>
                    <th>User</th>
                    <th class="text-end">Masuk (Rp)</th>
                    <th class="text-end">Keluar (Rp)</th>
                    <th class="text-end">Saldo (Rp)</th>
                    <?php if ($_SESSION['role'] === 'bendahara'): ?>
                        <th class="text-center">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <!-- Saldo Awal Row -->
                <tr class="table-secondary">
                    <td colspan="3" class="text-end fw-bold">SALDO AWAL</td>
                    <td></td>
                    <td></td>
                    <td class="text-end fw-bold"><?= formatRupiah($saldoAwal) ?></td>
                    <?php if ($_SESSION['role'] === 'bendahara'): ?><td></td><?php endif; ?>
                </tr>
                
                <?php 
                $saldoBerjalan = $saldoAwal;
                foreach ($transaksi as $t): 
                    if ($t['jenis'] === 'masuk') {
                        $masuk = $t['nominal'];
                        $keluar = 0;
                        $saldoBerjalan += $masuk;
                    } else {
                        $masuk = 0;
                        $keluar = $t['nominal'];
                        $saldoBerjalan -= $keluar;
                    }
                ?>
                    <tr>
                        <td><?= formatTanggal($t['tanggal']) ?></td>
                        <td><?= htmlspecialchars($t['keterangan']) ?></td>
                        <td><small class="text-muted"><?= htmlspecialchars($t['nama_lengkap']) ?></small></td>
                        <td class="text-end text-success"><?= $masuk > 0 ? formatRupiah($masuk) : '-' ?></td>
                        <td class="text-end text-danger"><?= $keluar > 0 ? formatRupiah($keluar) : '-' ?></td>
                        <td class="text-end fw-medium"><?= formatRupiah($saldoBerjalan) ?></td>
                        <?php if ($_SESSION['role'] === 'bendahara'): ?>
                            <td class="text-center">
                                <button onclick="confirmDelete('delete.php?id=<?= $t['id'] ?>', 'Transaksi <?= htmlspecialchars($t['keterangan']) ?>')" class="btn-sm-action btn-delete"><i class="bi bi-trash"></i></button>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($transaksi)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">Belum ada transaksi bulan ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
