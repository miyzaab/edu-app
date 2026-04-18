<?php
/**
 * UANG PANGKAL - Input pembayaran baru
 */
$pageTitle  = 'Input Uang Pangkal';
$activePage = 'uang-pangkal';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siswaId = (int)($_POST['siswa_id'] ?? 0);
    $nominal = (float)str_replace(['.', ','], ['', '.'], $_POST['nominal'] ?? '0');
    $tanggal = $_POST['tanggal_bayar'] ?? date('Y-m-d');
    $metode  = $_POST['metode_bayar'] ?? 'tunai';
    $ket     = trim($_POST['keterangan'] ?? '');

    if ($siswaId && $nominal > 0) {
        $stmt = $pdo->prepare("INSERT INTO pembayaran_uang_pangkal (siswa_id, nominal, tanggal_bayar, metode_bayar, keterangan, user_id) VALUES (:sid,:nom,:tgl,:met,:ket,:uid)");
        $stmt->execute([':sid'=>$siswaId,':nom'=>$nominal,':tgl'=>$tanggal,':met'=>$metode,':ket'=>$ket,':uid'=>$_SESSION['user_id']]);
        $lastId = $pdo->lastInsertId();
        redirect("kwitansi.php?id=$lastId", 'success', 'Pembayaran uang pangkal berhasil disimpan.');
    } else {
        $error = 'Semua field wajib diisi.';
    }
}

$siswaList = $pdo->query("SELECT s.id, s.nis, s.nama, k.nama_kelas FROM siswa s JOIN kelas k ON s.kelas_id=k.id WHERE s.status='aktif' ORDER BY s.nama")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-md-7">
<div class="form-card">
    <h5 class="mb-3"><i class="bi bi-wallet2"></i> Form Pembayaran Uang Pangkal</h5>
    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Siswa <span class="text-danger">*</span></label>
            <select name="siswa_id" class="form-select" required>
                <option value="">-- Pilih Siswa --</option>
                <?php foreach ($siswaList as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nis'].' - '.$s['nama'].' ('.$s['nama_kelas'].')') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Nominal (Rp) <span class="text-danger">*</span></label>
                <input type="text" name="nominal" class="form-control" value="2500000" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Tanggal Bayar</label>
                <input type="date" name="tanggal_bayar" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Metode Bayar</label>
                <select name="metode_bayar" class="form-select">
                    <option value="tunai">Tunai</option>
                    <option value="transfer">Transfer</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Keterangan</label>
                <input type="text" name="keterangan" class="form-control" placeholder="Opsional">
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn-primary-custom"><i class="bi bi-save"></i> Simpan & Cetak Kwitansi</button>
            <a href="index.php" class="btn btn-light">Batal</a>
        </div>
    </form>
</div>
</div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
