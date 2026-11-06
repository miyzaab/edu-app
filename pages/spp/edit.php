<?php
/**
 * PEMBAYARAN SPP - Edit Pembayaran
 */
$pageTitle = 'Edit Pembayaran SPP';
$activePage = 'spp';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('spp');

$pdo = getConnection();
$id = (int) ($_GET['id'] ?? 0);

if (!$id) {
    redirect('index.php', 'danger', 'ID Pembayaran tidak valid.');
}

// Ambil data pembayaran SPP
$stmt = $pdo->prepare("
    SELECT spp.*, s.nis, s.nama, k.nama_kelas 
    FROM pembayaran_spp spp
    JOIN siswa s ON spp.siswa_id = s.id
    JOIN kelas k ON s.kelas_id = k.id
    WHERE spp.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$data = $stmt->fetch();

if (!$data) {
    redirect('index.php', 'danger', 'Data pembayaran SPP tidak ditemukan.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nominal = (float) str_replace(['.', ','], ['', '.'], $_POST['nominal'] ?? '0');
    $tanggal = $_POST['tanggal_bayar'] ?? date('Y-m-d');
    $metode = $_POST['metode_bayar'] ?? 'tunai';
    $ket = trim($_POST['keterangan'] ?? '');

    if ($nominal <= 0) {
        $error = 'Nominal pembayaran harus lebih dari 0.';
    } else {
        try {
            $stmtUpdate = $pdo->prepare("
                UPDATE pembayaran_spp 
                SET nominal = :nom, tanggal_bayar = :tgl, metode_bayar = :met, keterangan = :ket 
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                ':nom' => $nominal,
                ':tgl' => $tanggal,
                ':met' => $metode,
                ':ket' => $ket,
                ':id' => $id
            ]);

            redirect("index.php?bulan={$data['bulan']}&tahun={$data['tahun']}", 'success', "Data pembayaran SPP {$data['nama']} berhasil diperbarui.");
        } catch (PDOException $e) {
            $error = 'Gagal menyimpan perubahan: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="form-card">
            <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                <h5 class="mb-0"><i class="bi bi-pencil-square text-primary"></i> Edit Pembayaran SPP</h5>
                <a href="index.php?bulan=<?= $data['bulan'] ?>&tahun=<?= $data['tahun'] ?>"
                    class="btn btn-sm btn-light">
                    <i class="bi bi-arrow-left"></i> Kembali
                </a>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card bg-light border-0 mb-4 p-3 rounded-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small">Siswa & Kelas</div>
                        <div class="fw-bold text-dark fs-6">
                            <?= htmlspecialchars($data['nis'] . ' - ' . $data['nama']) ?></div>
                        <span
                            class="badge bg-white text-dark border mt-1"><?= htmlspecialchars($data['nama_kelas']) ?></span>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Periode SPP</div>
                        <span class="badge bg-primary px-2.5 py-1.5 fs-7">
                            <i class="bi bi-calendar-check"></i> <?= namaBulan($data['bulan']) ?> <?= $data['tahun'] ?>
                        </span>
                    </div>
                </div>
            </div>

            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Nominal SPP (Rp) <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white fw-bold">Rp</span>
                            <input type="text" name="nominal" id="input_nominal"
                                class="form-control nominal-input fw-bold fs-6 text-success"
                                value="<?= number_format($data['nominal'], 0, ',', '.') ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Tanggal Bayar <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_bayar" class="form-control"
                            value="<?= htmlspecialchars($data['tanggal_bayar']) ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Metode Pembayaran</label>
                        <select name="metode_bayar" class="form-select">
                            <option value="tunai" <?= ($data['metode_bayar'] ?? '') === 'tunai' ? 'selected' : '' ?>>Tunai
                            </option>
                            <option value="transfer" <?= ($data['metode_bayar'] ?? '') === 'transfer' ? 'selected' : '' ?>>
                                Transfer</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Keterangan / Catatan</label>
                        <input type="text" name="keterangan" class="form-control" placeholder="Opsional"
                            value="<?= htmlspecialchars($data['keterangan'] ?? '') ?>">
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4 pt-2 border-top">
                    <button type="submit" class="btn-primary-custom">
                        <i class="bi bi-save"></i> Simpan Perubahan
                    </button>
                    <a href="index.php?bulan=<?= $data['bulan'] ?>&tahun=<?= $data['tahun'] ?>"
                        class="btn btn-light">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const nomInput = document.getElementById('input_nominal');
        if (nomInput) {
            nomInput.addEventListener('input', function (e) {
                let val = this.value.replace(/\D/g, '');
                if (val) {
                    this.value = parseInt(val, 10).toLocaleString('id-ID');
                } else {
                    this.value = '';
                }
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>