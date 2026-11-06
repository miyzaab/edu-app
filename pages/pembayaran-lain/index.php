<?php
/**
 * PEMBAYARAN LAIN - List transaksi
 */
$pageTitle = 'Pembayaran Lain';
$activePage = 'pembayaran-lain';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('pembayaran_lain');

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

<!-- SUB-TAB PILL NAVIGATION (SPP, Uang Pangkal, Pembayaran Lain, Jenis Pembayaran) -->
<div class="rekap-tabs-bar mb-4 overflow-auto pb-1">
    <div class="rekap-tabs-pill-container">
        <?php if (hasPermission('spp')): ?>
            <a href="<?= BASE_URL ?>/pages/spp/index.php"
                class="rekap-tab-pill <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/pages/spp/') !== false ? 'active' : '' ?>">
                <i class="bi bi-cash-stack"></i> SPP Bulanan
            </a>
        <?php endif; ?>
        <?php if (hasPermission('uang_pangkal')): ?>
            <a href="<?= BASE_URL ?>/pages/uang-pangkal/index.php"
                class="rekap-tab-pill <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/pages/uang-pangkal/') !== false ? 'active' : '' ?>">
                <i class="bi bi-wallet2"></i> Uang Pangkal
            </a>
        <?php endif; ?>
        <?php if (hasPermission('pembayaran_lain')): ?>
            <a href="<?= BASE_URL ?>/pages/pembayaran-lain/index.php"
                class="rekap-tab-pill <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/pages/pembayaran-lain/') !== false ? 'active' : '' ?>">
                <i class="bi bi-receipt-cutoff"></i> Pembayaran Lain
            </a>
        <?php endif; ?>
        <?php if (hasPermission('jenis_pembayaran')): ?>
            <a href="<?= BASE_URL ?>/pages/jenis-pembayaran/index.php"
                class="rekap-tab-pill <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/pages/jenis-pembayaran/') !== false ? 'active' : '' ?>">
                <i class="bi bi-tags-fill"></i> Jenis Pembayaran
            </a>
        <?php endif; ?>
    </div>
</div>

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
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <a href="../riwayat/edit.php?tipe=lainnya&id=<?= $d['id'] ?>&redirect=../pembayaran-lain/index.php"
                                    class="btn-sm-action btn-edit" title="Edit Transaksi">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <a href="kwitansi.php?id=<?= $d['id'] ?>" class="btn-sm-action btn-print" target="_blank"
                                    title="Cetak Kwitansi"><i class="bi bi-printer"></i></a>
                            </div>
                        </td>
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