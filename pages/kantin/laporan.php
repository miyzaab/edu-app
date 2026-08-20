<?php
/**
 * LAPORAN PENJUALAN KANTIN
 */
$pageTitle  = 'Laporan Penjualan Kantin';
$activePage = 'kantin';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('kantin');

$pdo = getConnection();

// Filter Tanggal
$tglMulai   = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tglSelesai = $_GET['tgl_selesai'] ?? date('Y-m-d');

// Summary statistics
$stmtSum = $pdo->prepare("
    SELECT 
        COUNT(id) AS total_transaksi,
        COALESCE(SUM(total_harga), 0) AS total_omset,
        SUM(CASE WHEN metode_bayar = 'saldo' THEN total_harga ELSE 0 END) AS omset_saldo,
        SUM(CASE WHEN metode_bayar = 'tunai' THEN total_harga ELSE 0 END) AS omset_tunai
    FROM kantin_transaksi
    WHERE DATE(created_at) BETWEEN :t1 AND :t2
");
$stmtSum->execute([':t1' => $tglMulai, ':t2' => $tglSelesai]);
$summary = $stmtSum->fetch();

// Best-seller items ranking
$stmtBest = $pdo->prepare("
    SELECT m.nama_item, m.kategori, SUM(d.jumlah) AS total_terjual, SUM(d.subtotal) AS total_pendapatan
    FROM kantin_transaksi_detail d
    JOIN kantin_transaksi t ON d.transaksi_id = t.id
    JOIN kantin_menu m ON d.menu_id = m.id
    WHERE DATE(t.created_at) BETWEEN :t1 AND :t2
    GROUP BY d.menu_id
    ORDER BY total_terjual DESC
    LIMIT 5
");
$stmtBest->execute([':t1' => $tglMulai, ':t2' => $tglSelesai]);
$bestSellers = $stmtBest->fetchAll();

// Transaction logs list
$stmtLog = $pdo->prepare("
    SELECT t.*, s.nama AS nama_siswa, s.nis, k.nama_kelas, u.nama_lengkap AS nama_kasir
    FROM kantin_transaksi t
    LEFT JOIN siswa s ON t.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN users u ON t.kasir_user_id = u.id
    WHERE DATE(t.created_at) BETWEEN :t1 AND :t2
    ORDER BY t.created_at DESC
");
$stmtLog->execute([':t1' => $tglMulai, ':t2' => $tglSelesai]);
$trxLogs = $stmtLog->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .kantin-hero-card {
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 45%, #2563eb 100%);
        border-radius: 20px;
        border: none;
        box-shadow: 0 10px 30px rgba(30, 27, 75, 0.15);
    }
    .pos-nav-container {
        display: inline-flex;
        align-items: center;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 14px;
        padding: 4px;
    }
    .pos-nav-link {
        padding: 8px 18px;
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 700;
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
    }
    .pos-nav-link:hover {
        color: #ffffff;
        background: rgba(255, 255, 255, 0.15);
    }
    .pos-nav-link.active {
        background: #ffffff;
        color: #0f172a;
        font-weight: 800;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
    }
</style>

<!-- HERO HEADER BANNER -->
<div class="card kantin-hero-card text-white p-4 p-md-4.5 mb-4" style="border-radius: 22px;">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-4 p-2.5 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px; background: rgba(255, 255, 255, 0.18); border: 1px solid rgba(255, 255, 255, 0.25); backdrop-filter: blur(10px);">
                <i class="bi bi-graph-up-arrow fs-3 text-white"></i>
            </div>
            <div>
                <h4 class="fw-extrabold text-white mb-0" style="font-family: 'Outfit', sans-serif; letter-spacing: -0.02em;">Laporan Penjualan Kantin</h4>
                <p class="text-white-50 small mb-0">Rekap omset harian, bulanan, item paling laku, dan log transaksi kasir kantin</p>
            </div>
        </div>

        <!-- SEGMENTED NAVIGATION CONTROLLER -->
        <div class="pos-nav-container">
            <a href="index.php" class="pos-nav-link"><i class="bi bi-calculator-fill"></i> Kasir POS</a>
            <a href="topup.php" class="pos-nav-link"><i class="bi bi-wallet2"></i> Top-Up Saldo</a>
            <a href="menu.php" class="pos-nav-link"><i class="bi bi-egg-fried"></i> Kelola Menu</a>
            <a href="laporan.php" class="pos-nav-link active"><i class="bi bi-graph-up-arrow"></i> Laporan</a>
        </div>
    </div>
</div>

<!-- FILTER PERIODE TANGGAL -->
<div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
    <form method="GET" action="laporan.php" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label extra-small fw-bold text-muted">Tanggal Mulai</label>
            <input type="date" name="tgl_mulai" class="form-control bg-light border-0 fw-bold" value="<?= htmlspecialchars($tglMulai) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label extra-small fw-bold text-muted">Tanggal Selesai</label>
            <input type="date" name="tgl_selesai" class="form-control bg-light border-0 fw-bold" value="<?= htmlspecialchars($tglSelesai) ?>">
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-teal w-100 rounded-3 fw-bold"><i class="bi bi-filter me-1"></i> Terapkan Filter</button>
        </div>
    </form>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="text-muted extra-small fw-bold">TOTAL OMSET KANTIN</div>
            <h4 class="fw-extrabold text-teal mb-0" style="color: #0d9488;">Rp <?= number_format($summary['total_omset'], 0, ',', '.') ?></h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="text-muted extra-small fw-bold">TOTAL TRANSAKSI</div>
            <h4 class="fw-extrabold text-dark mb-0"><?= number_format($summary['total_transaksi'], 0, ',', '.') ?> Nota</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="text-muted extra-small fw-bold">NONTUNAI (SALDO SISWA)</div>
            <h4 class="fw-extrabold text-success mb-0">Rp <?= number_format($summary['omset_saldo'], 0, ',', '.') ?></h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="text-muted extra-small fw-bold">TUNAI (CASH)</div>
            <h4 class="fw-extrabold text-warning mb-0">Rp <?= number_format($summary['omset_tunai'], 0, ',', '.') ?></h4>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- LEFT COLUMN: BEST SELLER ranking -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-trophy-fill text-warning me-2"></i>Produk Terlaris (Best Seller)</h6>

            <?php if (empty($bestSellers)): ?>
                <div class="text-center py-4 text-muted small">Belum ada data penjualan pada periode ini.</div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($bestSellers as $idx => $b): ?>
                        <div class="d-flex align-items-center justify-content-between p-2 rounded-3 bg-light border">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-warning text-dark rounded-circle p-2 fw-bold" style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;"><?= $idx + 1 ?></span>
                                <div>
                                    <div class="fw-bold small text-dark"><?= htmlspecialchars($b['nama_item']) ?></div>
                                    <div class="text-muted extra-small">Terjual: <strong><?= $b['total_terjual'] ?> Porsi</strong></div>
                                </div>
                            </div>
                            <div class="text-end fw-extrabold text-teal" style="color: #0d9488;">
                                Rp <?= number_format($b['total_pendapatan'], 0, ',', '.') ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT COLUMN: TRANSACTION LOG TABLE -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-receipt-cutoff text-teal me-2"></i>Log Riwayat Transaksi Kasir</h6>

            <?php if (empty($trxLogs)): ?>
                <div class="text-center py-4 text-muted small">Tidak ada transaksi kasir pada periode ini.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle extra-small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No Transaksi / Waktu</th>
                                <th>Pembeli / Siswa</th>
                                <th>Total</th>
                                <th>Metode</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trxLogs as $t): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($t['no_transaksi']) ?></strong><br>
                                        <span class="text-muted"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($t['nama_siswa'])): ?>
                                            <strong><?= htmlspecialchars($t['nama_siswa']) ?></strong><br>
                                            <span class="text-muted"><?= htmlspecialchars($t['nama_kelas']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted italic">Umum / Tunai</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-extrabold text-dark">Rp <?= number_format($t['total_harga'], 0, ',', '.') ?></td>
                                    <td>
                                        <?php if ($t['metode_bayar'] === 'saldo'): ?>
                                            <span class="badge bg-success-subtle text-success fw-bold px-2 py-1"><i class="bi bi-wallet2 me-1"></i>Saldo</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning fw-bold px-2 py-1"><i class="bi bi-cash me-1"></i>Tunai</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="index.php?print_id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Cetak Struk">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
