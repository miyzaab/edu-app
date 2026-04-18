<?php
/**
 * PETTY CASH - Input Transaksi Baru
 */
$pageTitle  = 'Input Petty Cash';
$activePage = 'petty-cash';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jenis   = $_POST['jenis'] ?? '';
    $nominal = (float)str_replace(['.', ','], ['', '.'], $_POST['nominal'] ?? '0');
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $ket     = trim($_POST['keterangan'] ?? '');

    if (in_array($jenis, ['masuk', 'keluar']) && $nominal > 0 && $tanggal) {
        try {
            $stmt = $pdo->prepare("INSERT INTO petty_cash (tanggal, jenis, nominal, keterangan, user_id) VALUES (:tgl, :jns, :nom, :ket, :uid)");
            $stmt->execute([
                ':tgl' => $tanggal,
                ':jns' => $jenis,
                ':nom' => $nominal,
                ':ket' => $ket,
                ':uid' => $_SESSION['user_id']
            ]);
            redirect('index.php', 'success', 'Transaksi berhasil disimpan.');
        } catch (PDOException $e) {
            $error = 'Gagal menyimpan transaksi: ' . $e->getMessage();
        }
    } else {
        $error = 'Semua field wajib diisi dengan benar.';
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-md-6">
<div class="form-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0"><i class="bi bi-wallet2"></i> Input Transaksi Buku Kas</h5>
        <a href="index.php" class="btn btn-sm btn-light">Kembali</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Jenis Transaksi <span class="text-danger">*</span></label>
            <select name="jenis" class="form-select form-select-lg" required>
                <option value="">-- Pilih Jenis --</option>
                <option value="masuk">Uang Masuk (+)</option>
                <option value="keluar">Uang Keluar (-)</option>
            </select>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Nominal (Rp) <span class="text-danger">*</span></label>
                <input type="text" name="nominal" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label">Keterangan / Uraian <span class="text-danger">*</span></label>
            <textarea name="keterangan" class="form-control" rows="3" placeholder="Contoh: Beli ATK Kantor, Terima Dana BOS..." required></textarea>
        </div>

        <button type="submit" class="btn-primary-custom w-100 py-2"><i class="bi bi-save"></i> Simpan Transaksi</button>
    </form>
</div>
</div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
