<?php
/**
 * RIWAYAT PEMBAYARAN - Cari dan lihat riwayat siswa
 */
$pageTitle  = 'Riwayat Pembayaran Siswa';
$activePage = 'riwayat';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

$siswaId = (int)($_GET['siswa_id'] ?? 0);

if ($siswaId > 0) {
    redirect("../siswa/history.php?id=$siswaId", '', '');
}

$siswaList = $pdo->query("SELECT s.id, s.nis, s.nama, k.nama_kelas FROM siswa s JOIN kelas k ON s.kelas_id=k.id WHERE s.status='aktif' ORDER BY s.nama")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="form-card">
            <h5 class="mb-3"><i class="bi bi-search"></i> Cari Riwayat Siswa</h5>
            <form method="GET" class="d-flex gap-2">
                <select name="siswa_id" class="form-select" required>
                    <option value="">-- Pilih Siswa --</option>
                    <?php foreach ($siswaList as $s): ?>
                        <option value="<?= $s['id'] ?>">
                            <?= htmlspecialchars($s['nis'] . ' - ' . $s['nama'] . ' (' . $s['nama_kelas'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-primary-custom"><i class="bi bi-search"></i> Cari</button>
            </form>
        </div>
    </div>
</div>

<div class="text-center py-5 text-muted">
    <i class="bi bi-person-lines-fill" style="font-size: 3rem;"></i>
    <p class="mt-2">Silakan pilih siswa di atas untuk melihat riwayat pembayarannya secara detail.</p>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
