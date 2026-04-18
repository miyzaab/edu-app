<?php
/**
 * PEMBAYARAN SPP - Input pembayaran baru
 */
$pageTitle  = 'Input Pembayaran SPP';
$activePage = 'spp';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

// Proses form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siswaId = (int)($_POST['siswa_id'] ?? 0);
    $bulan   = (int)($_POST['bulan'] ?? 0);
    $tahun   = (int)($_POST['tahun'] ?? 0);
    $nominal = (float)str_replace(['.', ','], ['', '.'], $_POST['nominal'] ?? '0');
    $tanggal = $_POST['tanggal_bayar'] ?? date('Y-m-d');
    $metode  = $_POST['metode_bayar'] ?? 'tunai';
    $ket     = trim($_POST['keterangan'] ?? '');

    if ($siswaId && $bulan && $tahun && $nominal > 0) {
        try {
            $stmt = $pdo->prepare("INSERT INTO pembayaran_spp (siswa_id, bulan, tahun, nominal, tanggal_bayar, metode_bayar, keterangan, user_id) VALUES (:sid,:bln,:thn,:nom,:tgl,:met,:ket,:uid)");
            $stmt->execute([':sid'=>$siswaId,':bln'=>$bulan,':thn'=>$tahun,':nom'=>$nominal,':tgl'=>$tanggal,':met'=>$metode,':ket'=>$ket,':uid'=>$_SESSION['user_id']]);
            $lastId = $pdo->lastInsertId();
            redirect("kwitansi.php?id=$lastId", 'success', 'Pembayaran SPP berhasil disimpan.');
        } catch (PDOException $e) {
            $error = ($e->getCode() == 23000) ? 'Siswa sudah membayar SPP bulan ini.' : 'Gagal menyimpan: ' . $e->getMessage();
        }
    } else {
        $error = 'Semua field wajib diisi dengan benar.';
    }
}

// Pre-fill dari URL
$preSiswa = (int)($_GET['siswa_id'] ?? 0);
$preBulan = (int)($_GET['bulan'] ?? date('m'));
$preTahun = (int)($_GET['tahun'] ?? date('Y'));

$siswaList = $pdo->query("SELECT s.id, s.nis, s.nama, k.nama_kelas FROM siswa s JOIN kelas k ON s.kelas_id=k.id WHERE s.status='aktif' ORDER BY s.nama")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-md-7">
<div class="form-card">
    <h5 class="mb-3"><i class="bi bi-cash-stack"></i> Form Pembayaran SPP</h5>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Siswa <span class="text-danger">*</span></label>
            <select name="siswa_id" class="form-select" required>
                <option value="">-- Pilih Siswa --</option>
                <?php foreach ($siswaList as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $preSiswa==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['nis'].' - '.$s['nama'].' ('.$s['nama_kelas'].')') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Bulan <span class="text-danger">*</span></label>
                <select name="bulan" class="form-select" required>
                    <?php for ($m=1; $m<=12; $m++): ?>
                        <option value="<?= $m ?>" <?= $preBulan==$m?'selected':'' ?>><?= namaBulan($m) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Tahun <span class="text-danger">*</span></label>
                <input type="number" name="tahun" class="form-control" value="<?= $preTahun ?>" min="2020" max="2099" required>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Nominal (Rp) <span class="text-danger">*</span></label>
                <input type="text" name="nominal" class="form-control" value="350000" required>
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
