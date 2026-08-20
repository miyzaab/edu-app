<?php
/**
 * EDIT TRANSAKSI - Edit riwayat pembayaran (SPP, Uang Pangkal, atau Lainnya)
 */
$pageTitle  = 'Edit Transaksi';
$activePage = 'riwayat';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('riwayat');

$pdo  = getConnection();
$id       = (int)($_GET['id'] ?? 0);
$tipe     = $_GET['tipe'] ?? '';
$redirect = $_GET['redirect'] ?? 'index.php';

if (!$id || !$tipe) {
    redirect($redirect, 'danger', 'Data tidak valid.');
}

// Tentukan tabel berdasarkan tipe
$table = match ($tipe) {
    'spp' => 'pembayaran_spp',
    'uang_pangkal' => 'pembayaran_uang_pangkal',
    'lainnya' => 'pembayaran_lain',
    default => null
};

if (!$table) {
    redirect($redirect, 'danger', 'Tipe transaksi tidak dikenal.');
}

// Ambil data lama
$stmt = $pdo->prepare("
    SELECT t.*, s.nama, s.nis 
    FROM $table t 
    JOIN siswa s ON t.siswa_id = s.id 
    WHERE t.id = :id
");
$stmt->execute([':id' => $id]);
$data = $stmt->fetch();

if (!$data) {
    redirect($redirect, 'danger', 'Data transaksi tidak ditemukan.');
}

// Proses Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nominal = str_replace(['.', ','], ['', '.'], $_POST['nominal']);
    $tanggal = $_POST['tanggal_bayar'];
    $metode  = $_POST['metode_bayar'];
    $ket     = trim($_POST['keterangan']);
    
    try {
        if ($tipe === 'spp') {
            $bulan = $_POST['bulan'];
            $tahun = $_POST['tahun'];
            $update = $pdo->prepare("UPDATE pembayaran_spp SET nominal = :nom, tanggal_bayar = :tgl, metode_bayar = :met, keterangan = :ket, bulan = :bln, tahun = :thn WHERE id = :id");
            $update->execute([
                ':nom' => $nominal, ':tgl' => $tanggal, ':met' => $metode, ':ket' => $ket,
                ':bln' => $bulan, ':thn' => $tahun, ':id' => $id
            ]);
        } elseif ($tipe === 'uang_pangkal') {
            $update = $pdo->prepare("UPDATE pembayaran_uang_pangkal SET nominal = :nom, tanggal_bayar = :tgl, metode_bayar = :met, keterangan = :ket WHERE id = :id");
            $update->execute([':nom' => $nominal, ':tgl' => $tanggal, ':met' => $metode, ':ket' => $ket, ':id' => $id]);
        } elseif ($tipe === 'lainnya') {
            $jenis_id = $_POST['jenis_pembayaran_id'];
            $update = $pdo->prepare("UPDATE pembayaran_lain SET nominal = :nom, tanggal_bayar = :tgl, metode_bayar = :met, keterangan = :ket, jenis_pembayaran_id = :jid WHERE id = :id");
            $update->execute([':nom' => $nominal, ':tgl' => $tanggal, ':met' => $metode, ':ket' => $ket, ':jid' => $jenis_id, ':id' => $id]);
        }
        
        redirect($redirect, 'success', 'Data transaksi berhasil diperbarui.');
    } catch (PDOException $e) {
        $error = "Gagal memperbarui data: " . $e->getMessage();
    }
}

// Jika tipe 'lainnya', ambil daftar jenis pembayaran
$jenisList = [];
if ($tipe === 'lainnya') {
    $jenisList = $pdo->query("SELECT * FROM jenis_pembayaran ORDER BY nama_pembayaran")->fetchAll();
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="form-card shadow-lg border-0" style="border-radius: 20px;">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="stat-icon bg-primary text-white" style="width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-pencil-square fs-4"></i>
                </div>
                <div>
                    <h4 class="mb-0 fw-bold">Edit Transaksi</h4>
                    <small class="text-muted"><?= strtoupper($tipe) ?> - <?= htmlspecialchars($data['nama']) ?> (<?= $data['nis'] ?>)</small>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger border-0 shadow-sm mb-4" style="border-radius: 12px;">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="row g-3">
                    <?php if ($tipe === 'spp'): ?>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Bulan</label>
                            <select name="bulan" class="form-select input-custom" required>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $data['bulan'] == $m ? 'selected' : '' ?>><?= namaBulan($m) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Tahun</label>
                            <input type="number" name="tahun" class="form-control input-custom" value="<?= $data['tahun'] ?>" required>
                        </div>
                    <?php endif; ?>

                    <?php if ($tipe === 'lainnya'): ?>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted">Jenis Pembayaran</label>
                            <select name="jenis_pembayaran_id" class="form-select input-custom" required>
                                <?php foreach ($jenisList as $j): ?>
                                    <option value="<?= $j['id'] ?>" <?= $data['jenis_pembayaran_id'] == $j['id'] ? 'selected' : '' ?>><?= htmlspecialchars($j['nama_pembayaran']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="col-12">
                        <label class="form-label fw-bold small text-muted">Nominal Pembayaran</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">Rp</span>
                            <input type="text" name="nominal" class="form-control border-start-0 fw-bold text-primary input-custom" value="<?= number_format($data['nominal'], 0, ',', '.') ?>" required onkeyup="formatRupiahInput(this)">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Tanggal Bayar</label>
                        <input type="date" name="tanggal_bayar" class="form-control input-custom" value="<?= $data['tanggal_bayar'] ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Metode</label>
                        <select name="metode_bayar" class="form-select input-custom" required>
                            <option value="tunai" <?= $data['metode_bayar'] === 'tunai' ? 'selected' : '' ?>>Tunai</option>
                            <option value="transfer" <?= $data['metode_bayar'] === 'transfer' ? 'selected' : '' ?>>Transfer</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold small text-muted">Keterangan</label>
                        <textarea name="keterangan" class="form-control input-custom" rows="3"><?= htmlspecialchars($data['keterangan']) ?></textarea>
                    </div>

                    <div class="col-12 mt-4">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary-custom flex-grow-1 justify-content-center py-2">
                                <i class="bi bi-save2 me-2"></i> Simpan Perubahan
                            </button>
                            <a href="<?= htmlspecialchars($redirect) ?>" class="btn btn-light px-4" style="border-radius: 12px; font-weight: 600;">Batal</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .input-custom {
        border-radius: 12px;
        padding: 0.75rem 1rem;
        border: 1.5px solid #f1f5f9;
        background: #f8fafc;
        font-size: 0.9rem;
    }
    .input-custom:focus {
        background: #fff;
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(13, 202, 240, 0.1);
    }
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
