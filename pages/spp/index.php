<?php
/**
 * PEMBAYARAN SPP - List & status pembayaran
 */
$pageTitle  = 'Pembayaran SPP';
$activePage = 'spp';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();
$bulanFilter = (int)($_GET['bulan'] ?? date('m'));
$tahunFilter = (int)($_GET['tahun'] ?? date('Y'));

// Ambil semua siswa aktif + status SPP
$stmt = $pdo->prepare("
    SELECT s.id, s.nis, s.nama, k.nama_kelas,
           spp.id AS spp_id, spp.nominal, spp.tanggal_bayar
    FROM siswa s
    JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN pembayaran_spp spp ON spp.siswa_id = s.id AND spp.bulan = :bulan AND spp.tahun = :tahun
    WHERE s.status = 'aktif'
    ORDER BY k.nama_kelas, s.nama
");
$stmt->execute([':bulan' => $bulanFilter, ':tahun' => $tahunFilter]);
$data = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <!-- Filter -->
    <form class="d-flex gap-2" method="GET">
        <select name="bulan" class="form-select form-select-sm" style="width:140px">
            <?php for ($m=1; $m<=12; $m++): ?>
                <option value="<?= $m ?>" <?= $bulanFilter==$m?'selected':'' ?>><?= namaBulan($m) ?></option>
            <?php endfor; ?>
        </select>
        <input type="number" name="tahun" class="form-control form-control-sm" value="<?= $tahunFilter ?>" style="width:90px" min="2020" max="2099">
        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i> Filter</button>
    </form>
    <div class="d-flex gap-2">
        <a href="create-massal.php" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1"><i class="bi bi-ui-checks-grid"></i> Input Massal</a>
        <a href="create.php" class="btn-primary-custom"><i class="bi bi-plus-lg"></i> Input Pembayaran</a>
    </div>
</div>

<div class="table-card">
    <div class="card-header">
        <h5><i class="bi bi-cash-stack"></i> SPP <?= namaBulan($bulanFilter) ?> <?= $tahunFilter ?></h5>
    </div>
    <div class="table-responsive">
        <table class="data-table" id="tableSpp">
            <thead>
                <tr><th>No</th><th>NIS</th><th>Nama</th><th>Kelas</th><th>Status</th><th>Nominal</th><th>Tanggal</th><th>Aksi</th></tr>
            </thead>
            <tbody>
            <?php foreach ($data as $i => $d): ?>
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
                    <td>
                        <?php if ($d['spp_id']): ?>
                            <span class="badge-status badge-lunas">Lunas</span>
                        <?php else: ?>
                            <span class="badge-status badge-belum">Belum</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $d['nominal'] ? '<span class="nominal-pill">' . formatRupiah($d['nominal']) . '</span>' : '<span class="text-muted opacity-50">-</span>' ?></td>
                    <td><?= $d['tanggal_bayar'] ? formatTanggal($d['tanggal_bayar']) : '<span class="text-muted opacity-50">-</span>' ?></td>
                    <td>
                        <?php if ($d['spp_id']): ?>
                            <a href="kwitansi.php?id=<?= $d['spp_id'] ?>" class="btn-sm-action btn-print" target="_blank" title="Cetak Kwitansi"><i class="bi bi-printer"></i></a>
                        <?php else: ?>
                            <a href="create.php?siswa_id=<?= $d['id'] ?>&bulan=<?= $bulanFilter ?>&tahun=<?= $tahunFilter ?>" class="btn-sm-action btn-edit" title="Bayar SPP"><i class="bi bi-plus-lg"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
