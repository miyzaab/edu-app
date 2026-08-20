<?php
/**
 * DATA SISWA - Tambah siswa baru
 */
$pageTitle  = 'Tambah Siswa';
$activePage = 'siswa';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

// Proses form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nis    = trim($_POST['nis'] ?? '');
    $nama   = trim($_POST['nama'] ?? '');
    $kelasId = (int)($_POST['kelas_id'] ?? 0);
    $jk     = $_POST['jenis_kelamin'] ?? '';
    $tahun  = (int)($_POST['tahun_masuk'] ?? date('Y'));

    if ($nis && $nama && $kelasId && $jk) {
        try {
            $target_up = (float)str_replace(['.', ','], ['', '.'], $_POST['target_uang_pangkal'] ?? '0');
            $stmt = $pdo->prepare("INSERT INTO siswa (nis, nama, kelas_id, jenis_kelamin, tahun_masuk, target_uang_pangkal) VALUES (:nis, :nama, :kelas, :jk, :tahun, :up)");
            $stmt->execute([':nis'=>$nis, ':nama'=>$nama, ':kelas'=>$kelasId, ':jk'=>$jk, ':tahun'=>$tahun, ':up'=>$target_up]);
            redirect('index.php', 'success', 'Siswa baru berhasil ditambahkan.');
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'NIS sudah terdaftar.';
            } else {
                $error = 'Gagal menyimpan data.';
            }
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
    <h5 class="mb-3"><i class="bi bi-person-plus"></i> Form Tambah Siswa</h5>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">NIS <span class="text-danger">*</span></label>
            <input type="text" name="nis" class="form-control" value="<?= htmlspecialchars($nis ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
            <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($nama ?? '') ?>" required>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Kelas <span class="text-danger">*</span></label>
                <select name="kelas_id" class="form-select" required>
                    <option value="">-- Pilih --</option>
                    <?php foreach ($kelasList as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= ($kelasId ?? 0)==$k['id']?'selected':'' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                <select name="jenis_kelamin" class="form-select" required>
                    <option value="">-- Pilih --</option>
                    <option value="L" <?= ($jk ?? '')==='L'?'selected':'' ?>>Laki-laki</option>
                    <option value="P" <?= ($jk ?? '')==='P'?'selected':'' ?>>Perempuan</option>
                </select>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Tahun Masuk</label>
            <input type="number" name="tahun_masuk" class="form-control" value="<?= date('Y') ?>" min="2000" max="2099">
        </div>
        <div class="mb-3">
            <label class="form-label">Target Uang Pangkal (Total Kewajiban) <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text">Rp</span>
                <input type="text" name="target_uang_pangkal" class="form-control fw-bold text-primary" placeholder="Contoh: 10.000.000" onkeyup="formatRupiahInput(this)">
            </div>
            <div class="form-text">Atur total biaya uang pangkal yang harus dibayar santri ini.</div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn-primary-custom"><i class="bi bi-save"></i> Simpan</button>
            <a href="index.php" class="btn btn-light">Batal</a>
        </div>
    </form>
</div>
</div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
