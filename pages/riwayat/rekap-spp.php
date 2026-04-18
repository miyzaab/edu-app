<?php
/**
 * REKAP SPP KELAS - Tampilan Matriks 12 Bulan
 */
$pageTitle  = 'Rekap SPP Kelas';
$activePage = 'rekap-spp';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

// Filter
$kelasId = (int)($_GET['kelas_id'] ?? 0);
$tahun   = (int)($_GET['tahun'] ?? date('Y'));

// Ambil list kelas untuk dropdown
$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();

$siswaData = [];
$sppData   = [];

if ($kelasId > 0) {
    // Ambil data siswa di kelas tersebut (Aktif)
    $stmtSiswa = $pdo->prepare("SELECT id, nis, nama FROM siswa WHERE kelas_id = :k_id AND status = 'aktif' ORDER BY nama");
    $stmtSiswa->execute([':k_id' => $kelasId]);
    $siswaData = $stmtSiswa->fetchAll();

    if (!empty($siswaData)) {
        // Ambil data SPP untuk tahun ini dari siswa-siswa tersebut
        $siswaIds = array_column($siswaData, 'id');
        $inQuery = implode(',', array_fill(0, count($siswaIds), '?'));
        
        $sqlSpp = "SELECT siswa_id, bulan FROM pembayaran_spp WHERE tahun = ? AND siswa_id IN ($inQuery)";
        $stmtSpp = $pdo->prepare($sqlSpp);
        
        $params = [$tahun];
        foreach ($siswaIds as $sid) {
            $params[] = $sid;
        }
        $stmtSpp->execute($params);
        
        // Bentuk array helper: $sppData[siswa_id][bulan] = true
        while ($row = $stmtSpp->fetch()) {
            $sppData[$row['siswa_id']][$row['bulan']] = true;
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="form-card mb-4">
    <h5 class="mb-3"><i class="bi bi-grid-3x3"></i> Matriks Rekap SPP Kelas</h5>
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Kelas</label>
            <select name="kelas_id" class="form-select form-select-sm" required>
                <option value="">-- Pilih Kelas --</option>
                <?php foreach ($kelasList as $k): ?>
                    <option value="<?= $k['id'] ?>" <?= $kelasId == $k['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($k['nama_kelas']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Tahun</label>
            <input type="number" name="tahun" class="form-control form-control-sm" value="<?= $tahun ?>" min="2020" max="2099" required>
        </div>
        <div class="col-md-2">
            <button class="btn btn-sm btn-primary-custom w-100"><i class="bi bi-search"></i> Tampilkan Rekap</button>
        </div>
    </form>
</div>

<?php if ($kelasId > 0): ?>
    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle text-center mb-0" style="font-size: 0.85rem; white-space: nowrap;">
                <thead class="table-light">
                    <tr>
                        <th class="text-start" style="width: 30px;">No</th>
                        <th class="text-start" style="width: 250px;">Nama Siswa</th>
                        <?php for ($m=1; $m<=12; $m++): ?>
                            <th style="width: 50px;"><?= substr(namaBulan($m), 0, 3) ?></th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($siswaData)): ?>
                    <tr><td colspan="14" class="text-muted py-4">Belum ada siswa aktif di kelas ini.</td></tr>
                <?php else: ?>
                    <?php foreach ($siswaData as $i => $s): ?>
                        <tr>
                            <td class="text-start"><?= $i+1 ?></td>
                            <td class="text-start fw-medium">
                                <?= htmlspecialchars($s['nama']) ?>
                                <br><small class="text-muted"><?= htmlspecialchars($s['nis']) ?></small>
                            </td>
                            <?php for ($m=1; $m<=12; $m++): ?>
                                <td>
                                    <?php if (isset($sppData[$s['id']][$m])): ?>
                                        <i class="bi bi-check-circle-fill text-success fs-5" title="Lunas"></i>
                                    <?php else: ?>
                                        <i class="bi bi-x-circle text-danger opacity-50 fs-5" title="Belum"></i>
                                    <?php endif; ?>
                                </td>
                            <?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-table" style="font-size: 3rem;"></i>
        <p class="mt-2">Silakan pilih kelas dan tahun di atas untuk melihat matriks SPP.</p>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
