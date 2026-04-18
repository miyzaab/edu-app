<?php
/**
 * PEMBAYARAN SPP - Input SPP Massal (Banyak Bulan Sekaligus)
 */
$pageTitle  = 'Input SPP Massal';
$activePage = 'spp';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siswaId = (int)($_POST['siswa_id'] ?? 0);
    $tahun   = (int)($_POST['tahun'] ?? 0);
    $nominal = (float)str_replace(['.', ','], ['', '.'], $_POST['nominal'] ?? '0');
    $tanggal = $_POST['tanggal_bayar'] ?? date('Y-m-d');
    $metode  = $_POST['metode_bayar'] ?? 'tunai';
    $ket     = trim($_POST['keterangan'] ?? '');
    
    // Array bulan yang diceklis
    $bulanDipilih = $_POST['bulan'] ?? [];

    if ($siswaId && $tahun && $nominal > 0 && !empty($bulanDipilih)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO pembayaran_spp (siswa_id, bulan, tahun, nominal, tanggal_bayar, metode_bayar, keterangan, user_id) VALUES (:sid,:bln,:thn,:nom,:tgl,:met,:ket,:uid)");
            
            $berhasil = 0;
            $gagal = 0;
            
            foreach ($bulanDipilih as $bln) {
                $bulan = (int)$bln;
                // Cek apakah sudah lunas
                $cek = $pdo->prepare("SELECT id FROM pembayaran_spp WHERE siswa_id = :s AND bulan = :b AND tahun = :t");
                $cek->execute([':s' => $siswaId, ':b' => $bulan, ':t' => $tahun]);
                if ($cek->fetch()) {
                    $gagal++;
                    continue; // Skip jika sudah bayar
                }
                
                $stmt->execute([
                    ':sid' => $siswaId,
                    ':bln' => $bulan,
                    ':thn' => $tahun,
                    ':nom' => $nominal,
                    ':tgl' => $tanggal,
                    ':met' => $metode,
                    ':ket' => $ket,
                    ':uid' => $_SESSION['user_id']
                ]);
                $berhasil++;
            }
            
            $pdo->commit();
            
            if ($berhasil > 0) {
                $msg = "$berhasil bulan SPP berhasil disimpan.";
                if ($gagal > 0) $msg .= " ($gagal bulan dilewati karena sudah lunas sebelumnya).";
                redirect("index.php", 'success', $msg);
            } else {
                redirect("create-massal.php", 'warning', 'Tidak ada data yang disimpan. Kemungkinan bulan yang dipilih sudah lunas semua.');
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Gagal menyimpan: ' . $e->getMessage();
        }
    } else {
        $error = 'Siswa, Tahun, Nominal, dan minimal 1 Bulan wajib diisi.';
    }
}

// Pre-fill dari URL (Misal dipanggil dari history siswa)
$preSiswa = (int)($_GET['siswa_id'] ?? 0);
$preTahun = (int)($_GET['tahun'] ?? date('Y'));

$siswaList = $pdo->query("SELECT s.id, s.nis, s.nama, k.nama_kelas FROM siswa s JOIN kelas k ON s.kelas_id=k.id WHERE s.status='aktif' ORDER BY s.nama")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-md-8">
<div class="form-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0"><i class="bi bi-ui-checks-grid text-primary"></i> Input SPP Massal</h5>
        <a href="index.php" class="btn btn-sm btn-light">Kembali</a>
    </div>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Gunakan fitur ini untuk melunasi tunggakan SPP siswa pada beberapa bulan sekaligus dalam satu tahun yang sama.
    </div>

    <form method="POST">
        <div class="row">
            <div class="col-md-8 mb-3">
                <label class="form-label">Siswa <span class="text-danger">*</span></label>
                <select name="siswa_id" class="form-select form-select-lg" required>
                    <option value="">-- Pilih Siswa --</option>
                    <?php foreach ($siswaList as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $preSiswa==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['nis'].' - '.$s['nama'].' ('.$s['nama_kelas'].')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Tahun SPP <span class="text-danger">*</span></label>
                <input type="number" name="tahun" class="form-control form-control-lg" value="<?= $preTahun ?>" min="2020" max="2099" required>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label d-block fw-bold border-bottom pb-2">Pilih Bulan (Bisa lebih dari 1) <span class="text-danger">*</span></label>
            <div class="row g-2">
                <?php for ($m=1; $m<=12; $m++): ?>
                    <div class="col-4 col-md-3">
                        <div class="form-check border rounded p-2 d-flex align-items-center justify-content-center position-relative m-0" style="cursor:pointer;">
                            <input class="form-check-input m-0 me-2" type="checkbox" name="bulan[]" value="<?= $m ?>" id="b_<?= $m ?>">
                            <label class="form-check-label stretched-link m-0 fw-medium" for="b_<?= $m ?>" style="cursor:pointer; font-size: .9rem;">
                                <?= namaBulan($m) ?>
                            </label>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
            <div class="mt-2 text-end">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="checkAll(true)">Pilih Semua</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="checkAll(false)">Batal Pilih</button>
            </div>
        </div>

        <div class="row border-top pt-3 mt-3">
            <div class="col-md-6 mb-3">
                <label class="form-label">Nominal Per Bulan (Rp) <span class="text-danger">*</span></label>
                <input type="text" name="nominal" class="form-control" value="350000" required>
                <small class="text-muted">Nominal ini akan disamakan untuk setiap bulan yang diceklis.</small>
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
            <div class="col-md-6 mb-4">
                <label class="form-label">Keterangan</label>
                <input type="text" name="keterangan" class="form-control" placeholder="Opsional">
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn-primary-custom w-100 py-2" style="font-size: 1rem;"><i class="bi bi-save"></i> Simpan Pembayaran Massal</button>
        </div>
    </form>
</div>
</div>
</div>

<script>
function checkAll(check) {
    const checkboxes = document.querySelectorAll('input[name="bulan[]"]');
    checkboxes.forEach(cb => cb.checked = check);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
