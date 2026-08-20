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

<div class="row g-4 mb-4">
    <!-- Saldo Awal -->
    <div class="col-md-3">
        <div class="stat-card border-0 shadow-sm" style="background: #ffffff;">
            <div class="d-flex align-items-center mb-3">
                <div class="stat-icon mb-0 me-3" style="width: 48px; height: 48px; border-radius: 14px; background: linear-gradient(135deg, #64748b 0%, #334155 100%); color: #fff; box-shadow: 0 4px 12px rgba(100, 116, 139, 0.3);">
                    <i class="bi bi-wallet2" style="font-size: 1.3rem;"></i>
                </div>
                <div class="stat-label mb-0" style="font-size: 0.75rem; letter-spacing: 0.05em; font-weight: 700; color: #64748b;">SALDO AWAL</div>
            </div>
            <div class="stat-value" style="font-size: 1.5rem; color: #1e293b; font-weight: 800;"><?= formatRupiah($saldoAwal) ?></div>
        </div>
    </div>
    <!-- Total Masuk -->
    <div class="col-md-3">
        <div class="stat-card border-0 shadow-sm" style="background: #ffffff;">
            <div class="d-flex align-items-center mb-3">
                <div class="stat-icon mb-0 me-3" style="width: 48px; height: 48px; border-radius: 14px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
                    <i class="bi bi-arrow-down-left-circle" style="font-size: 1.3rem;"></i>
                </div>
                <div class="stat-label mb-0" style="font-size: 0.75rem; letter-spacing: 0.05em; font-weight: 700; color: #059669;">TOTAL MASUK</div>
            </div>
            <div class="stat-value" style="font-size: 1.5rem; color: #059669; font-weight: 800;">+ <?= formatRupiah($totalMasuk) ?></div>
        </div>
    </div>
    <!-- Total Keluar -->
    <div class="col-md-3">
        <div class="stat-card border-0 shadow-sm" style="background: #ffffff;">
            <div class="d-flex align-items-center mb-3">
                <div class="stat-icon mb-0 me-3" style="width: 48px; height: 48px; border-radius: 14px; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: #fff; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);">
                    <i class="bi bi-arrow-up-right-circle" style="font-size: 1.3rem;"></i>
                </div>
                <div class="stat-label mb-0" style="font-size: 0.75rem; letter-spacing: 0.05em; font-weight: 700; color: #dc2626;">TOTAL KELUAR</div>
            </div>
            <div class="stat-value" style="font-size: 1.5rem; color: #dc2626; font-weight: 800;">- <?= formatRupiah($totalKeluar) ?></div>
        </div>
    </div>
    <!-- Saldo Akhir -->
    <div class="col-md-3">
        <div class="stat-card border-0 shadow-sm" style="background: #ffffff;">
            <div class="d-flex align-items-center mb-3">
                <div class="stat-icon mb-0 me-3" style="width: 48px; height: 48px; border-radius: 14px; background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); color: #fff; box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);">
                    <i class="bi bi-cash-stack" style="font-size: 1.3rem;"></i>
                </div>
                <div class="stat-label mb-0" style="font-size: 0.75rem; letter-spacing: 0.05em; font-weight: 700; color: #0891b2;">SALDO AKHIR</div>
            </div>
            <div class="stat-value" style="font-size: 1.5rem; color: #0891b2; font-weight: 800;"><?= formatRupiah($saldoAkhir) ?></div>
        </div>
    </div>
</div>

<div class="table-card p-3 px-4 mb-4">
    <div class="row align-items-center">
        <div class="col-lg-4 d-flex align-items-center gap-3 mb-3 mb-lg-0">
            <h5 class="mb-0 fw-800 text-dark" style="font-size: 1.1rem;">Laporan Kas</h5>
            <span class="badge bg-light text-muted border-0 px-3 py-2 rounded-pill font-monospace" style="font-size: 0.7rem;">
                <?= namaBulan($filterBulan) ?> <?= $filterTahun ?>
            </span>
        </div>
        
        <div class="col-lg-8">
            <div class="d-flex flex-wrap justify-content-lg-end align-items-center gap-2">
                <form method="GET" class="d-flex gap-2">
                    <select name="bulan" class="form-select form-select-sm border-0 bg-light px-3" style="width: 120px; border-radius: 20px; height: 38px;">
                        <?php for ($m=1; $m<=12; $m++): ?>
                            <option value="<?= $m ?>" <?= $filterBulan==$m?'selected':'' ?>><?= namaBulan($m) ?></option>
                        <?php endfor; ?>
                    </select>
                    <input type="number" name="tahun" class="form-control form-control-sm border-0 bg-light px-3" value="<?= $filterTahun ?>" style="width: 80px; border-radius: 20px; height: 38px;" min="2020" max="2099">
                    <button class="btn btn-sm btn-dark px-4 rounded-pill fw-600" style="height: 38px;">Filter</button>
                </form>
                
                <div class="vr mx-2 opacity-10 d-none d-md-block" style="height: 24px;"></div>
                
                <div class="d-flex gap-1">
                    <button onclick="exportToCSV('tablePettyCash', 'Buku_Kas_<?= namaBulan($filterBulan) ?>_<?= $filterTahun ?>')" class="btn btn-sm btn-link text-success text-decoration-none px-3 fw-600" style="font-size: 0.8rem;"><i class="bi bi-file-earmark-excel"></i> Export</button>
                    <a href="print.php?bulan=<?= $filterBulan ?>&tahun=<?= $filterTahun ?>" target="_blank" class="btn btn-sm btn-link text-danger text-decoration-none px-3 fw-600" style="font-size: 0.8rem;"><i class="bi bi-file-earmark-pdf"></i> Print</a>
                </div>
                
                <a href="create.php" class="btn btn-sm btn-primary-custom rounded-pill px-4 shadow-sm fw-700" style="height: 38px; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="bi bi-plus-lg"></i> Tambah Transaksi
                </a>
            </div>
        </div>
    </div>
</div>

<div class="table-card overflow-hidden border-0 shadow-sm">
    <div class="table-responsive">
        <table class="data-table mb-0" id="tablePettyCash">
            <thead>
                <tr>
                    <th class="ps-4">TANGGAL</th>
                    <th>KETERANGAN</th>
                    <th>USER</th>
                    <th class="text-end">MASUK (RP)</th>
                    <th class="text-end">KELUAR (RP)</th>
                    <th class="text-end pe-4">SALDO (RP)</th>
                    <?php if ($_SESSION['role'] === 'bendahara'): ?>
                        <th class="text-center">AKSI</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <!-- Saldo Awal Row -->
                <tr class="bg-white border-bottom">
                    <td colspan="3" class="ps-4 text-muted py-3" style="font-size: 0.8rem;">
                        <i class="bi bi-info-circle me-2 opacity-50"></i> SALDO AWAL
                    </td>
                    <td class="text-end text-light opacity-50">-</td>
                    <td class="text-end text-light opacity-50">-</td>
                    <td class="text-end fw-700 text-dark pe-4 py-3" style="font-size: 0.9rem;"><?= formatRupiah($saldoAwal) ?></td>
                    <?php if ($_SESSION['role'] === 'bendahara'): ?><td></td><?php endif; ?>
                </tr>
                
                <?php 
                $saldoBerjalan = $saldoAwal;
                foreach ($transaksi as $t): 
                    if ($t['jenis'] === 'masuk') {
                        $masuk = $t['nominal'];
                        $keluar = 0;
                        $saldoBerjalan += $masuk;
                        $colorClass = 'text-success fw-700';
                        $icon = 'bi-arrow-down-left';
                    } else {
                        $masuk = 0;
                        $keluar = $t['nominal'];
                        $saldoBerjalan -= $keluar;
                        $colorClass = 'text-danger fw-700';
                        $icon = 'bi-arrow-up-right';
                    }
                ?>
                    <tr>
                        <td class="ps-4 font-monospace small"><?= formatTanggal($t['tanggal']) ?></td>
                        <td>
                            <div class="fw-600 text-dark"><?= htmlspecialchars($t['keterangan']) ?></div>
                            <small class="text-muted text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.02em;">#<?= $t['id'] ?></small>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; font-size: 0.6rem; font-weight: 800;">
                                    <?= strtoupper(substr($t['nama_lengkap'], 0, 1)) ?>
                                </div>
                                <small class="text-muted fw-500"><?= htmlspecialchars($t['nama_lengkap']) ?></small>
                            </div>
                        </td>
                        <td class="text-end <?= $masuk > 0 ? $colorClass : 'text-muted opacity-25' ?>">
                            <?= $masuk > 0 ? '<i class="bi '.$icon.' small me-1"></i> '.formatRupiah($masuk) : '-' ?>
                        </td>
                        <td class="text-end <?= $keluar > 0 ? $colorClass : 'text-muted opacity-25' ?>">
                            <?= $keluar > 0 ? '<i class="bi '.$icon.' small me-1"></i> '.formatRupiah($keluar) : '-' ?>
                        </td>
                        <td class="text-end fw-700 text-dark pe-4"><?= formatRupiah($saldoBerjalan) ?></td>
                        <?php if ($_SESSION['role'] === 'bendahara'): ?>
                            <td class="text-center">
                                <button onclick="confirmDelete('delete.php?id=<?= $t['id'] ?>', 'Transaksi <?= htmlspecialchars($t['keterangan']) ?>')" class="btn-sm-action btn-delete" title="Hapus"><i class="bi bi-trash"></i></button>
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
