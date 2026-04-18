<?php
/**
 * DATA KELAS - CRUD Master Kelas
 */
$pageTitle  = 'Data Kelas';
$activePage = 'kelas';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

// Proses Tambah Kelas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $namaKelas = trim($_POST['nama_kelas']);
    $tingkat   = $_POST['tingkat'];
    
    if ($namaKelas && $tingkat) {
        try {
            $stmt = $pdo->prepare("INSERT INTO kelas (nama_kelas, tingkat) VALUES (:nama, :tingkat)");
            $stmt->execute([':nama' => $namaKelas, ':tingkat' => $tingkat]);
            redirect('index.php', 'success', "Kelas $namaKelas berhasil ditambahkan.");
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                redirect('index.php', 'danger', "Nama kelas '$namaKelas' sudah ada.");
            } else {
                redirect('index.php', 'danger', "Gagal menambah kelas: " . $e->getMessage());
            }
        }
    } else {
        redirect('index.php', 'warning', "Nama kelas dan tingkat wajib diisi.");
    }
}

// Ambil Data Kelas
$kelasList = $pdo->query("
    SELECT k.*, (SELECT COUNT(id) FROM siswa WHERE kelas_id = k.id AND status='aktif') as jumlah_siswa 
    FROM kelas k 
    ORDER BY k.tingkat, k.nama_kelas
")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <!-- Tabel Data Kelas -->
    <div class="col-md-8 mb-4">
        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-building"></i> Daftar Kelas</h5>
            </div>
            <div class="table-responsive">
                <table class="data-table" id="tableKelas">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tingkat</th>
                            <th>Nama Kelas</th>
                            <th>Jumlah Siswa</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($kelasList as $i => $k): ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td><span class="badge bg-secondary text-white"><?= $k['tingkat'] ?></span></td>
                            <td><strong><?= htmlspecialchars($k['nama_kelas']) ?></strong></td>
                            <td><?= $k['jumlah_siswa'] ?> Siswa</td>
                            <td>
                                <a href="edit.php?id=<?= $k['id'] ?>" class="btn-sm-action btn-edit"><i class="bi bi-pencil"></i></a>
                                <?php if ($k['jumlah_siswa'] == 0): ?>
                                    <button onclick="confirmDelete('delete.php?id=<?= $k['id'] ?>', 'Kelas <?= htmlspecialchars($k['nama_kelas']) ?>')" class="btn-sm-action btn-delete"><i class="bi bi-trash"></i></button>
                                <?php else: ?>
                                    <button onclick="alert('Tidak dapat menghapus kelas yang masih memiliki siswa aktif.')" class="btn-sm-action btn-delete" style="opacity:0.5"><i class="bi bi-trash"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($kelasList)): ?>
                        <tr><td colspan="5" class="text-center text-muted">Belum ada data kelas.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Form Tambah Kelas -->
    <div class="col-md-4">
        <div class="form-card sticky-top" style="top: 80px;">
            <h5 class="mb-3"><i class="bi bi-plus-circle"></i> Tambah Kelas Baru</h5>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="mb-3">
                    <label class="form-label">Tingkat <span class="text-danger">*</span></label>
                    <select name="tingkat" class="form-select" required>
                        <option value="">-- Pilih Tingkat --</option>
                        <option value="VII">VII (Tujuh)</option>
                        <option value="VIII">VIII (Delapan)</option>
                        <option value="IX">IX (Sembilan)</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Nama Kelas <span class="text-danger">*</span></label>
                    <input type="text" name="nama_kelas" class="form-control" placeholder="Contoh: VII-A, VIII-B" required>
                </div>
                
                <button type="submit" class="btn-primary-custom w-100"><i class="bi bi-save"></i> Simpan Kelas</button>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    if($('#tableKelas').length) {
        $('#tableKelas').DataTable({
            "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json" },
            "pageLength": 25
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
