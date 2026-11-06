<?php
/**
 * LAPORAN PENJUALAN KANTIN
 */
$pageTitle = 'Laporan Penjualan Kantin';
$activePage = 'kantin';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('kantin');

$pdo = getConnection();
$flash = getFlash();

// Filter Tanggal
$tglMulai = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tglSelesai = $_GET['tgl_selesai'] ?? date('Y-m-d');

// PROSES HAPUS TRANSAKSI KASIR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_transaksi') {
    $trxId = (int) ($_POST['transaksi_id'] ?? 0);
    try {
        if ($trxId <= 0) {
            throw new Exception("ID transaksi tidak valid.");
        }

        $stmtTrx = $pdo->prepare("SELECT * FROM kantin_transaksi WHERE id = :id");
        $stmtTrx->execute([':id' => $trxId]);
        $trx = $stmtTrx->fetch();

        if (!$trx) {
            throw new Exception("Transaksi tidak ditemukan atau sudah dihapus.");
        }

        $pdo->beginTransaction();

        // 1. Kembalikan saldo siswa jika dibayar menggunakan saldo
        if ($trx['metode_bayar'] === 'saldo' && !empty($trx['siswa_id']) && $trx['total_harga'] > 0) {
            $stmtRef = $pdo->prepare("UPDATE saldo_siswa SET saldo = saldo + :amount WHERE siswa_id = :sid");
            $stmtRef->execute([':amount' => $trx['total_harga'], ':sid' => $trx['siswa_id']]);

            // Hapus mutasi saldo yang terkait
            $pdo->prepare("DELETE FROM mutasi_saldo WHERE ref_transaksi_id = :tid")->execute([':tid' => $trxId]);
        }

        // 2. Kembalikan stok item produk kantin
        $stmtDetails = $pdo->prepare("SELECT menu_id, jumlah FROM kantin_transaksi_detail WHERE transaksi_id = :tid");
        $stmtDetails->execute([':tid' => $trxId]);
        $details = $stmtDetails->fetchAll();

        $stmtRestock = $pdo->prepare("UPDATE kantin_menu SET stok = stok + :qty WHERE id = :mid");
        foreach ($details as $d) {
            $stmtRestock->execute([':qty' => $d['jumlah'], ':mid' => $d['menu_id']]);
        }

        // 3. Hapus detail transaksi
        $pdo->prepare("DELETE FROM kantin_transaksi_detail WHERE transaksi_id = :tid")->execute([':tid' => $trxId]);

        // 4. Hapus header transaksi
        $pdo->prepare("DELETE FROM kantin_transaksi WHERE id = :id")->execute([':id' => $trxId]);

        $pdo->commit();

        $redirectUrl = 'laporan.php?tgl_mulai=' . urlencode($tglMulai) . '&tgl_selesai=' . urlencode($tglSelesai);
        redirect($redirectUrl, 'success', '🗑️ Transaksi ' . htmlspecialchars($trx['no_transaksi']) . ' berhasil dihapus. Data di portal orang tua otomatis terhapus dan saldo/stok telah dikembalikan!');

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $redirectUrl = 'laporan.php?tgl_mulai=' . urlencode($tglMulai) . '&tgl_selesai=' . urlencode($tglSelesai);
        redirect($redirectUrl, 'danger', 'Gagal menghapus transaksi: ' . $e->getMessage());
    }
}

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
        display: flex;
        align-items: center;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 14px;
        padding: 4px;
        overflow-x: auto;
        white-space: nowrap;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        max-width: 100%;
    }

    .pos-nav-container::-webkit-scrollbar {
        display: none;
    }

    .pos-nav-link {
        padding: 8px 16px;
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
        flex-shrink: 0;
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

    @media (max-width: 767.98px) {
        .kantin-hero-card {
            border-radius: 16px !important;
            padding: 1.25rem !important;
        }

        .pos-nav-container {
            width: 100%;
        }

        .pos-nav-link {
            padding: 6px 12px;
            font-size: 0.76rem;
        }
    }
</style>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4"
        role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?= $flash['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- FILTER PERIODE TANGGAL -->
<div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
    <form method="GET" action="laporan.php" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label extra-small fw-bold text-muted">Tanggal Mulai</label>
            <input type="date" name="tgl_mulai" class="form-control bg-light border-0 fw-bold"
                value="<?= htmlspecialchars($tglMulai) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label extra-small fw-bold text-muted">Tanggal Selesai</label>
            <input type="date" name="tgl_selesai" class="form-control bg-light border-0 fw-bold"
                value="<?= htmlspecialchars($tglSelesai) ?>">
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-teal w-100 rounded-3 fw-bold"><i class="bi bi-filter me-1"></i>
                Terapkan Filter</button>
        </div>
    </form>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="text-muted extra-small fw-bold">TOTAL OMSET KANTIN</div>
            <h4 class="fw-extrabold text-teal mb-0" style="color: #0d9488;">Rp
                <?= number_format($summary['total_omset'], 0, ',', '.') ?>
            </h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="text-muted extra-small fw-bold">TOTAL TRANSAKSI</div>
            <h4 class="fw-extrabold text-dark mb-0"><?= number_format($summary['total_transaksi'], 0, ',', '.') ?> Nota
            </h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="text-muted extra-small fw-bold">NONTUNAI (SALDO SISWA)</div>
            <h4 class="fw-extrabold text-success mb-0">Rp <?= number_format($summary['omset_saldo'], 0, ',', '.') ?>
            </h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="text-muted extra-small fw-bold">TUNAI (CASH)</div>
            <h4 class="fw-extrabold text-warning mb-0">Rp <?= number_format($summary['omset_tunai'], 0, ',', '.') ?>
            </h4>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- LEFT COLUMN: BEST SELLER ranking -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-trophy-fill text-warning me-2"></i>Produk Terlaris (Best
                Seller)</h6>

            <?php if (empty($bestSellers)): ?>
                <div class="text-center py-4 text-muted small">Belum ada data penjualan pada periode ini.</div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($bestSellers as $idx => $b): ?>
                        <div class="d-flex align-items-center justify-content-between p-2 rounded-3 bg-light border">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-warning text-dark rounded-circle p-2 fw-bold"
                                    style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;"><?= $idx + 1 ?></span>
                                <div>
                                    <div class="fw-bold small text-dark"><?= htmlspecialchars($b['nama_item']) ?></div>
                                    <div class="text-muted extra-small">Terjual: <strong><?= $b['total_terjual'] ?>
                                            Porsi</strong></div>
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
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-receipt-cutoff text-teal me-2"></i>Log Riwayat Transaksi
                Kasir</h6>

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
                                    <td class="fw-extrabold text-dark">Rp <?= number_format($t['total_harga'], 0, ',', '.') ?>
                                    </td>
                                    <td>
                                        <?php if ($t['metode_bayar'] === 'saldo'): ?>
                                            <span class="badge bg-success-subtle text-success fw-bold px-2 py-1"><i
                                                    class="bi bi-wallet2 me-1"></i>Saldo</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning fw-bold px-2 py-1"><i
                                                    class="bi bi-cash me-1"></i>Tunai</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex gap-1 align-items-center">
                                            <a href="index.php?print_id=<?= $t['id'] ?>"
                                                class="btn btn-sm btn-outline-secondary rounded-3 px-2 py-1"
                                                title="Cetak Struk">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                            <form method="POST" action="laporan.php"
                                                onsubmit="return confirm('Apakah Anda yakin ingin menghapus transaksi <?= htmlspecialchars($t['no_transaksi']) ?>?\n\n- Transaksi akan otomatis terhapus dari Portal Orang Tua.\n- Jika menggunakan Saldo Siswa, saldo sebesar Rp <?= number_format($t['total_harga'], 0, ',', '.') ?> akan otomatis dikembalikan ke siswa.\n- Stok barang akan otomatis dikembalikan.');"
                                                class="d-inline">
                                                <input type="hidden" name="action" value="delete_transaksi">
                                                <input type="hidden" name="transaksi_id" value="<?= $t['id'] ?>">
                                                <input type="hidden" name="tgl_mulai"
                                                    value="<?= htmlspecialchars($tglMulai) ?>">
                                                <input type="hidden" name="tgl_selesai"
                                                    value="<?= htmlspecialchars($tglSelesai) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-3 px-2 py-1"
                                                    title="Hapus Transaksi">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                        </div>
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