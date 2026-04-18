<?php
/**
 * DATA SISWA - Riwayat Pembayaran Siswa
 */
$pageTitle  = 'Riwayat Pembayaran Siswa';
$activePage = 'siswa';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    redirect('index.php', 'danger', 'Siswa tidak ditemukan.');
}

// Data Siswa
$stmt = $pdo->prepare("SELECT s.*, k.nama_kelas FROM siswa s JOIN kelas k ON s.kelas_id = k.id WHERE s.id = :id");
$stmt->execute([':id' => $id]);
$siswa = $stmt->fetch();

if (!$siswa) {
    redirect('index.php', 'danger', 'Data siswa tidak ditemukan.');
}

// Riwayat SPP
$spp = $pdo->prepare("SELECT * FROM pembayaran_spp WHERE siswa_id = :id ORDER BY tahun DESC, bulan DESC");
$spp->execute([':id' => $id]);
$sppList = $spp->fetchAll();

// Riwayat Uang Pangkal
$up = $pdo->prepare("SELECT * FROM pembayaran_uang_pangkal WHERE siswa_id = :id ORDER BY tanggal_bayar DESC");
$up->execute([':id' => $id]);
$upList = $up->fetchAll();

// Riwayat Pembayaran Lain
$lain = $pdo->prepare("
    SELECT pl.*, jp.nama_pembayaran 
    FROM pembayaran_lain pl 
    JOIN jenis_pembayaran jp ON pl.jenis_pembayaran_id = jp.id 
    WHERE pl.siswa_id = :id 
    ORDER BY pl.tanggal_bayar DESC
");
$lain->execute([':id' => $id]);
$lainList = $lain->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="index.php" class="btn btn-light"><i class="bi bi-arrow-left"></i> Kembali</a>
    <a href="../spp/create-massal.php?siswa_id=<?= $id ?>" class="btn-primary-custom"><i class="bi bi-plus-lg"></i> Input SPP Massal</a>
</div>

<div class="row">
    <!-- Profil Siswa -->
    <div class="col-md-4 mb-4">
        <div class="form-card text-center">
            <div class="mb-3">
                <div class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle" style="width: 80px; height: 80px; font-size: 2rem;">
                    <i class="bi bi-person-fill"></i>
                </div>
            </div>
            <h4 class="mb-1"><?= htmlspecialchars($siswa['nama']) ?></h4>
            <p class="text-muted mb-3">NIS: <?= htmlspecialchars($siswa['nis']) ?> | Kelas: <?= htmlspecialchars($siswa['nama_kelas']) ?></p>
            <div class="d-flex justify-content-center gap-2">
                <span class="badge bg-light text-dark border"><?= $siswa['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></span>
                <span class="badge bg-light text-dark border">Masuk: <?= $siswa['tahun_masuk'] ?></span>
                <span class="badge <?= $siswa['status'] === 'aktif' ? 'bg-success' : 'bg-danger' ?>"><?= ucfirst($siswa['status']) ?></span>
            </div>
        </div>
    </div>

    <!-- Tabel Riwayat -->
    <div class="col-md-8">
        <!-- Tab Navigation -->
        <ul class="nav nav-tabs mb-3" id="historyTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold" id="spp-tab" data-bs-toggle="tab" data-bs-target="#spp" type="button" role="tab"><i class="bi bi-cash-stack"></i> SPP</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold" id="up-tab" data-bs-toggle="tab" data-bs-target="#up" type="button" role="tab"><i class="bi bi-wallet2"></i> Uang Pangkal</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold" id="lain-tab" data-bs-toggle="tab" data-bs-target="#lain" type="button" role="tab"><i class="bi bi-receipt-cutoff"></i> Lainnya</button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content bg-white border border-top-0 rounded-bottom p-3" id="historyTabContent">
            
            <!-- SPP -->
            <div class="tab-pane fade show active" id="spp" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr><th>Bulan</th><th>Tahun</th><th>Tgl Bayar</th><th>Nominal</th><th>Kwitansi</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sppList as $d): ?>
                                <tr>
                                    <td><strong><?= namaBulan($d['bulan']) ?></strong></td>
                                    <td><?= $d['tahun'] ?></td>
                                    <td><?= formatTanggal($d['tanggal_bayar']) ?></td>
                                    <td><?= formatRupiah($d['nominal']) ?></td>
                                    <td><a href="../spp/kwitansi.php?id=<?= $d['id'] ?>" class="btn-sm-action btn-print" target="_blank"><i class="bi bi-printer"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($sppList)): ?><tr><td colspan="5" class="text-center text-muted">Belum ada data pembayaran SPP.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Uang Pangkal -->
            <div class="tab-pane fade" id="up" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr><th>Tgl Bayar</th><th>Metode</th><th>Keterangan</th><th>Nominal</th><th>Kwitansi</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upList as $d): ?>
                                <tr>
                                    <td><?= formatTanggal($d['tanggal_bayar']) ?></td>
                                    <td><?= ucfirst($d['metode_bayar']) ?></td>
                                    <td><?= htmlspecialchars($d['keterangan'] ?: '-') ?></td>
                                    <td><?= formatRupiah($d['nominal']) ?></td>
                                    <td><a href="../uang-pangkal/kwitansi.php?id=<?= $d['id'] ?>" class="btn-sm-action btn-print" target="_blank"><i class="bi bi-printer"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($upList)): ?><tr><td colspan="5" class="text-center text-muted">Belum ada data pembayaran Uang Pangkal.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pembayaran Lainnya -->
            <div class="tab-pane fade" id="lain" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr><th>Jenis</th><th>Tgl Bayar</th><th>Metode</th><th>Nominal</th><th>Kwitansi</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lainList as $d): ?>
                                <tr>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($d['nama_pembayaran']) ?></span></td>
                                    <td><?= formatTanggal($d['tanggal_bayar']) ?></td>
                                    <td><?= ucfirst($d['metode_bayar']) ?></td>
                                    <td><?= formatRupiah($d['nominal']) ?></td>
                                    <td><a href="../pembayaran-lain/kwitansi.php?id=<?= $d['id'] ?>" class="btn-sm-action btn-print" target="_blank"><i class="bi bi-printer"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($lainList)): ?><tr><td colspan="5" class="text-center text-muted">Belum ada data pembayaran Lainnya.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
