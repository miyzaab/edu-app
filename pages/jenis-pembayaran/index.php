<?php
/**
 * JENIS PEMBAYARAN - CRUD kategori pembayaran dinamis
 */
$pageTitle  = 'Jenis Pembayaran';
$activePage = 'jenis-pembayaran';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

// Proses tambah/edit via modal (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $nama   = trim($_POST['nama_pembayaran'] ?? '');
    $nominal = (float)str_replace(['.', ','], ['', '.'], $_POST['nominal_default'] ?? '0');
    $ket    = trim($_POST['keterangan'] ?? '');

    if ($action === 'create' && $nama) {
        try {
            $stmt = $pdo->prepare("INSERT INTO jenis_pembayaran (nama_pembayaran, nominal_default, keterangan) VALUES (:nama,:nom,:ket)");
            $stmt->execute([':nama'=>$nama,':nom'=>$nominal,':ket'=>$ket]);
            redirect('index.php', 'success', 'Jenis pembayaran berhasil ditambahkan.');
        } catch (PDOException $e) {
            redirect('index.php', 'danger', 'Nama jenis pembayaran sudah ada.');
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'aktif';
        $stmt = $pdo->prepare("UPDATE jenis_pembayaran SET nama_pembayaran=:nama, nominal_default=:nom, keterangan=:ket, status=:st WHERE id=:id");
        $stmt->execute([':nama'=>$nama,':nom'=>$nominal,':ket'=>$ket,':st'=>$status,':id'=>$id]);
        redirect('index.php', 'success', 'Data berhasil diperbarui.');
    }
}

// Hapus
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();
        
        $pdo->prepare("DELETE FROM pembayaran_pending WHERE jenis_pembayaran_id = :id")->execute([':id'=>$id]);
        $pdo->prepare("DELETE FROM pembayaran_lain WHERE jenis_pembayaran_id = :id")->execute([':id'=>$id]);
        
        $pdo->prepare("DELETE FROM jenis_pembayaran WHERE id=:id")->execute([':id'=>$id]);
        
        $pdo->commit();
        redirect('index.php', 'success', 'Jenis pembayaran beserta riwayat pembayarannya berhasil dihapus.');
    } catch (PDOException $e) {
        $pdo->rollBack();
        redirect('index.php', 'danger', 'Gagal menghapus: ' . $e->getMessage());
    }
}

$jenisList = $pdo->query("SELECT * FROM jenis_pembayaran ORDER BY nama_pembayaran")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Total: <strong><?= count($jenisList) ?></strong> jenis</p>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalTambah"><i class="bi bi-plus-lg"></i> Tambah Jenis</button>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>No</th><th>Nama Pembayaran</th><th>Nominal Default</th><th>Keterangan</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                <!-- Row Statis SPP (Sistem) -->
                <tr style="background-color: #f8f9fa;">
                    <td>-</td>
                    <td><strong>📅 Pembayaran SPP</strong></td>
                    <td>-</td>
                    <td><small class="text-muted">Dikelola otomatis oleh sistem (bulanan)</small></td>
                    <td><span class="badge-status badge-aktif">Sistem</span></td>
                    <td><span class="text-muted">Fixed</span></td>
                </tr>
                <tr style="background-color: #f8f9fa;">
                    <td>-</td>
                    <td><strong>🏦 Uang Pangkal (Masuk)</strong></td>
                    <td>-</td>
                    <td><small class="text-muted">Target per siswa, mendukung cicilan & persentase</small></td>
                    <td><span class="badge-status badge-aktif">Sistem</span></td>
                    <td><a href="../uang-pangkal/index.php" class="btn btn-sm btn-outline-primary py-0" style="font-size: .7rem">Kelola</a></td>
                </tr>
            <?php foreach ($jenisList as $i => $j): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($j['nama_pembayaran']) ?></strong></td>
                    <td><?= formatRupiah($j['nominal_default']) ?></td>
                    <td><?= htmlspecialchars($j['keterangan'] ?? '-') ?></td>
                    <td><span class="badge-status <?= $j['status']==='aktif'?'badge-aktif':'badge-belum' ?>"><?= ucfirst($j['status']) ?></span></td>
                    <td>
                        <button class="btn-sm-action btn-edit" onclick="editJenis(<?= htmlspecialchars(json_encode($j)) ?>)"><i class="bi bi-pencil"></i></button>
                        <button onclick="confirmDelete('index.php?delete=<?= $j['id'] ?>','<?= htmlspecialchars($j['nama_pembayaran']) ?>')" class="btn-sm-action btn-delete"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($jenisList)): ?><tr><td colspan="6" class="text-center text-muted py-4">Belum ada data.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
    <form method="POST">
        <input type="hidden" name="action" value="create">
        <div class="modal-header"><h5 class="modal-title">Tambah Jenis Pembayaran</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Nama Pembayaran *</label><input type="text" name="nama_pembayaran" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Nominal Default (Rp)</label><input type="text" name="nominal_default" class="form-control" value="0"></div>
            <div class="mb-3"><label class="form-label">Keterangan</label><input type="text" name="keterangan" class="form-control"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn-primary-custom">Simpan</button></div>
    </form>
</div></div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
    <form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="editId">
        <div class="modal-header"><h5 class="modal-title">Edit Jenis Pembayaran</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Nama Pembayaran *</label><input type="text" name="nama_pembayaran" id="editNama" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Nominal Default (Rp)</label><input type="text" name="nominal_default" id="editNominal" class="form-control"></div>
            <div class="mb-3"><label class="form-label">Keterangan</label><input type="text" name="keterangan" id="editKet" class="form-control"></div>
            <div class="mb-3"><label class="form-label">Status</label><select name="status" id="editStatus" class="form-select"><option value="aktif">Aktif</option><option value="nonaktif">Nonaktif</option></select></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn-primary-custom">Simpan</button></div>
    </form>
</div></div>
</div>

<script>
function editJenis(data) {
    document.getElementById('editId').value = data.id;
    document.getElementById('editNama').value = data.nama_pembayaran;
    document.getElementById('editNominal').value = data.nominal_default;
    document.getElementById('editKet').value = data.keterangan || '';
    document.getElementById('editStatus').value = data.status;
    new bootstrap.Modal(document.getElementById('modalEdit')).show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
