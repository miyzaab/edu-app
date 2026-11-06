<?php
/**
 * DATA SISWA - Edit data siswa
 */
$pageTitle = 'Edit Siswa';
$activePage = 'siswa';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('siswa');

$pdo = getConnection();

// Auto-sync database jika ada kolom baru
require_once __DIR__ . '/../../config/db_auto_sync.php';
runDatabaseAutoSync($pdo);

$id = (int) ($_GET['id'] ?? 0);

// Ambil data siswa
$stmt = $pdo->prepare("SELECT * FROM siswa WHERE id = :id");
$stmt->execute([':id' => $id]);
$siswa = $stmt->fetch();

if (!$siswa) {
    redirect('index.php', 'danger', 'Siswa tidak ditemukan.');
}

// Proses form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nis = trim($_POST['nis'] ?? '');
    $nisn = trim($_POST['nisn'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $noHpOrtu = trim($_POST['no_hp_ortu'] ?? '');
    $kelasId = (int) ($_POST['kelas_id'] ?? 0);
    $jk = $_POST['jenis_kelamin'] ?? '';
    $tahun = (int) ($_POST['tahun_masuk'] ?? date('Y'));
    $status = $_POST['status'] ?? 'aktif';

    if ($nis && $nama && $kelasId && $jk) {
        try {
            $target_up = (float) str_replace(['.', ','], ['', '.'], $_POST['target_uang_pangkal'] ?? '0');
            $nominal_spp = (float) str_replace(['.', ','], ['', '.'], $_POST['nominal_spp'] ?? '350000');
            $stmt = $pdo->prepare("UPDATE siswa SET nis=:nis, nisn=:nisn, nama=:nama, no_hp_ortu=:hp, kelas_id=:kelas, jenis_kelamin=:jk, tahun_masuk=:tahun, status=:status, target_uang_pangkal=:up, nominal_spp=:spp WHERE id=:id");
            $stmt->execute([
                ':nis' => $nis,
                ':nisn' => $nisn ?: null,
                ':nama' => $nama,
                ':hp' => $noHpOrtu ?: null,
                ':kelas' => $kelasId,
                ':jk' => $jk,
                ':tahun' => $tahun,
                ':status' => $status,
                ':up' => $target_up,
                ':spp' => $nominal_spp,
                ':id' => $id
            ]);
            redirect('index.php', 'success', 'Data siswa berhasil diperbarui.');
        } catch (PDOException $e) {
            $error = ($e->getCode() == 23000) ? 'NIS sudah digunakan.' : 'Gagal memperbarui data: ' . $e->getMessage();
        }
    } else {
        $error = 'Semua field bertanda * wajib diisi.';
    }
}

$currentKelasId = (int) ($siswa['kelas_id'] ?? 0);
$kelasList = $pdo->query("
    SELECT * FROM kelas 
    WHERE (status IS NULL OR status = 'aktif') AND (is_active IS NULL OR is_active = 1) OR id = $currentKelasId
    ORDER BY tingkat, nama_kelas
")->fetchAll();

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
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">NIS <span class="text-danger">*</span></label>
                        <input type="text" name="nis" class="form-control"
                            value="<?= htmlspecialchars($siswa['nis']) ?>" required>
                        <div class="form-text">NIS digunakan untuk identitas santri & login.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">NISN (Opsional)</label>
                        <input type="text" name="nisn" class="form-control"
                            value="<?= htmlspecialchars($siswa['nisn'] ?? '') ?>" placeholder="Contoh: 0081234567">
                        <div class="form-text">Nomor Induk Siswa Nasional untuk Rapor.</div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Lengkap Santri <span class="text-danger">*</span></label>
                    <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($siswa['nama']) ?>"
                        required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nomor HP / WhatsApp Orang Tua (Untuk Login)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-success"><i class="bi bi-whatsapp"></i></span>
                        <input type="text" name="no_hp_ortu" class="form-control"
                            value="<?= htmlspecialchars($siswa['no_hp_ortu'] ?? '') ?>"
                            placeholder="Contoh: 081234567890">
                    </div>
                    <div class="form-text">Dapat digunakan orang tua/wali untuk login ke aplikasi selain menggunakan
                        NIS.</div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Kelas <span class="text-danger">*</span></label>
                        <select name="kelas_id" class="form-select" required>
                            <?php foreach ($kelasList as $k):
                                $isKlsAktif = ($k['status'] ?? 'aktif') === 'aktif' && ($k['is_active'] ?? 1) == 1;
                                ?>
                                <option value="<?= $k['id'] ?>" <?= $siswa['kelas_id'] == $k['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($k['nama_kelas']) ?>     <?= !$isKlsAktif ? ' (Nonaktif)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                        <select name="jenis_kelamin" class="form-select" required>
                            <option value="L" <?= $siswa['jenis_kelamin'] === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                            <option value="P" <?= $siswa['jenis_kelamin'] === 'P' ? 'selected' : '' ?>>Perempuan</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="aktif" <?= $siswa['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                            <option value="lulus" <?= $siswa['status'] === 'lulus' ? 'selected' : '' ?>>Lulus</option>
                            <option value="keluar" <?= $siswa['status'] === 'keluar' ? 'selected' : '' ?>>Keluar</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tahun Masuk</label>
                    <input type="number" name="tahun_masuk" class="form-control" value="<?= $siswa['tahun_masuk'] ?>"
                        min="2000" max="2099">
                </div>
                <div class="mb-3">
                    <label class="form-label">Target Uang Pangkal (Total Kewajiban) <span
                            class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="text" name="target_uang_pangkal" class="form-control fw-bold text-primary"
                            value="<?= number_format($siswa['target_uang_pangkal'] ?? 0, 0, ',', '.') ?>"
                            onkeyup="formatRupiahInput(this)">
                    </div>
                    <div class="form-text">Atur total biaya uang pangkal yang harus dibayar santri ini.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nominal SPP Per Bulan <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="text" name="nominal_spp" class="form-control fw-bold text-primary"
                            value="<?= number_format($siswa['nominal_spp'] ?? 350000, 0, ',', '.') ?>">
                    </div>
                    <div class="form-text">Nominal SPP bulanan yang harus dibayar santri ini.</div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn-primary-custom"><i class="bi bi-save"></i> Simpan
                        Perubahan</button>
                    <a href="index.php" class="btn btn-light">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>