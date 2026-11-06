<?php
/**
 * DATA SISWA - Tambah siswa baru
 */
$pageTitle = 'Tambah Siswa';
$activePage = 'siswa';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

// Auto-sync database jika ada kolom baru
require_once __DIR__ . '/../../config/db_auto_sync.php';
runDatabaseAutoSync($pdo);

// Proses form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nis = trim($_POST['nis'] ?? '');
    $nisn = trim($_POST['nisn'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $noHpOrtu = trim($_POST['no_hp_ortu'] ?? '');
    $kelasId = (int) ($_POST['kelas_id'] ?? 0);
    $jk = $_POST['jenis_kelamin'] ?? '';
    $tahun = (int) ($_POST['tahun_masuk'] ?? date('Y'));

    if ($nis && $nama && $kelasId && $jk) {
        try {
            $target_up = (float) str_replace(['.', ','], ['', '.'], $_POST['target_uang_pangkal'] ?? '0');
            $nominal_spp = (float) str_replace(['.', ','], ['', '.'], $_POST['nominal_spp'] ?? '350000');
            $stmt = $pdo->prepare("INSERT INTO siswa (nis, nisn, nama, no_hp_ortu, kelas_id, jenis_kelamin, tahun_masuk, target_uang_pangkal, nominal_spp) VALUES (:nis, :nisn, :nama, :hp, :kelas, :jk, :tahun, :up, :spp)");
            $stmt->execute([
                ':nis' => $nis,
                ':nisn' => $nisn ?: null,
                ':nama' => $nama,
                ':hp' => $noHpOrtu ?: null,
                ':kelas' => $kelasId,
                ':jk' => $jk,
                ':tahun' => $tahun,
                ':up' => $target_up,
                ':spp' => $nominal_spp
            ]);
            redirect('index.php', 'success', 'Siswa baru berhasil ditambahkan.');
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'NIS sudah terdaftar.';
            } else {
                $error = 'Gagal menyimpan data: ' . $e->getMessage();
            }
        }
    } else {
        $error = 'Semua field bertanda * wajib diisi.';
    }
}

$kelasList = $pdo->query("
    SELECT * FROM kelas 
    WHERE (status IS NULL OR status = 'aktif') AND (is_active IS NULL OR is_active = 1) 
    ORDER BY tingkat, nama_kelas
")->fetchAll();

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
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">NIS <span class="text-danger">*</span></label>
                        <input type="text" name="nis" class="form-control" value="<?= htmlspecialchars($nis ?? '') ?>"
                            placeholder="Contoh: 2026001" required>
                        <div class="form-text">NIS digunakan untuk identitas santri & login.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">NISN (Opsional)</label>
                        <input type="text" name="nisn" class="form-control" value="<?= htmlspecialchars($nisn ?? '') ?>"
                            placeholder="Contoh: 0081234567">
                        <div class="form-text">Nomor Induk Siswa Nasional untuk Rapor.</div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Lengkap Santri <span class="text-danger">*</span></label>
                    <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($nama ?? '') ?>"
                        placeholder="Nama lengkap santri" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nomor HP / WhatsApp Orang Tua (Untuk Login)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-success"><i class="bi bi-whatsapp"></i></span>
                        <input type="text" name="no_hp_ortu" class="form-control"
                            value="<?= htmlspecialchars($noHpOrtu ?? '') ?>" placeholder="Contoh: 081234567890">
                    </div>
                    <div class="form-text">Dapat digunakan orang tua/wali untuk login ke aplikasi selain menggunakan
                        NIS.</div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kelas <span class="text-danger">*</span></label>
                        <select name="kelas_id" class="form-select" required>
                            <option value="">-- Pilih --</option>
                            <?php foreach ($kelasList as $k): ?>
                                <option value="<?= $k['id'] ?>" <?= ($kelasId ?? 0) == $k['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($k['nama_kelas']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                        <select name="jenis_kelamin" class="form-select" required>
                            <option value="">-- Pilih --</option>
                            <option value="L" <?= ($jk ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                            <option value="P" <?= ($jk ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tahun Masuk</label>
                    <input type="number" name="tahun_masuk" class="form-control" value="<?= $tahun ?? date('Y') ?>"
                        min="2000" max="2099">
                </div>
                <div class="mb-3">
                    <label class="form-label">Target Uang Pangkal (Total Kewajiban) <span
                            class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="text" name="target_uang_pangkal" class="form-control fw-bold text-primary"
                            placeholder="Contoh: 10.000.000" onkeyup="formatRupiahInput(this)">
                    </div>
                    <div class="form-text">Atur total biaya uang pangkal yang harus dibayar santri ini.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nominal SPP Per Bulan <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="text" name="nominal_spp" class="form-control fw-bold text-primary" value="350.000"
                            placeholder="Contoh: 350.000">
                    </div>
                    <div class="form-text">Nominal SPP bulanan yang harus dibayar santri ini.</div>
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