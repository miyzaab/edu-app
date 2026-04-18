<?php
/**
 * DATA SISWA - Edit data siswa
 */
$pageTitle  = 'Edit Siswa';
$activePage = 'siswa';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();
$id = (int)($_GET['id'] ?? 0);

// Ambil data siswa
$stmt = $pdo->prepare("SELECT * FROM siswa WHERE id = :id");
$stmt->execute([':id' => $id]);
$siswa = $stmt->fetch();

if (!$siswa) { redirect('index.php', 'danger', 'Siswa tidak ditemukan.'); }

// Proses form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nis    = trim($_POST['nis'] ?? '');
    $nama   = trim($_POST['nama'] ?? '');
    $kelasId = (int)($_POST['kelas_id'] ?? 0);
    $jk     = $_POST['jenis_kelamin'] ?? '';
    $tahun  = (int)($_POST['tahun_masuk'] ?? date('Y'));
    $status = $_POST['status'] ?? 'aktif';

    if ($nis && $nama && $kelasId && $jk) {
        try {
            $stmt = $pdo->prepare("UPDATE siswa SET nis=:nis, nama=:nama, kelas_id=:kelas, jenis_kelamin=:jk, tahun_masuk=:tahun, status=:status WHERE id=:id");
            $stmt->execute([':nis'=>$nis,':nama'=>$nama,':kelas'=>$kelasId,':jk'=>$jk,':tahun'=>$tahun,':status'=>$status,':id'=>$id]);
            redirect('index.php', 'success', 'Data siswa berhasil diperbarui.');
        } catch (PDOException $e) {
            $error = ($e->getCode() == 23000) ? 'NIS sudah digunakan.' : 'Gagal memperbarui data.';
        }
    } else {
        $error = 'Semua field wajib diisi.';
    }
}

$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY nama_kelas")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-md-7">
<div class="form-card">
    <h5 class="mb-3"><i class="bi bi-pencil-square"></i> Edit Data Siswa</h5>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">NIS <span class="text-danger">*</span></label>
            <input type="text" name="nis" class="form-control" value="<?= htmlspecialchars($siswa['nis']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
            <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($siswa['nama']) ?>" required>
        </div>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Kelas <span class="text-danger">*</span></label>
                <select name="kelas_id" class="form-select" required>
                    <?php foreach ($kelasList as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= $siswa['kelas_id']==$k['id']?'selected':'' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                <select name="jenis_kelamin" class="form-select" required>
                    <option value="L" <?= $siswa['jenis_kelamin']==='L'?'selected':'' ?>>Laki-laki</option>
                    <option value="P" <?= $siswa['jenis_kelamin']==='P'?'selected':'' ?>>Perempuan</option>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="aktif" <?= $siswa['status']==='aktif'?'selected':'' ?>>Aktif</option>
                    <option value="lulus" <?= $siswa['status']==='lulus'?'selected':'' ?>>Lulus</option>
                    <option value="keluar" <?= $siswa['status']==='keluar'?'selected':'' ?>>Keluar</option>
                </select>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Tahun Masuk</label>
            <input type="number" name="tahun_masuk" class="form-control" value="<?= $siswa['tahun_masuk'] ?>" min="2000" max="2099">
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn-primary-custom"><i class="bi bi-save"></i> Simpan Perubahan</button>
            <a href="index.php" class="btn btn-light">Batal</a>
        </div>
    </form>
</div>
</div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
