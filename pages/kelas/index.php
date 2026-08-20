<?php
/**
 * DATA KELAS - CRUD Master Kelas
 */
$pageTitle  = 'Data Kelas';
$activePage = 'kelas';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

// Helper format nama guru dengan gelar
function formatNamaGuruGelar($nama, $gDepan = '', $gBelakang = '') {
    $depan = !empty($gDepan) ? trim($gDepan) . ' ' : '';
    $belakang = !empty($gBelakang) ? ', ' . trim($gBelakang) : '';
    return htmlspecialchars($depan . $nama . $belakang);
}

// Proses Tambah Kelas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $namaKelas   = trim($_POST['nama_kelas']);
    $tingkat     = $_POST['tingkat'];
    $waliKelasId = !empty($_POST['wali_kelas_id']) ? (int)$_POST['wali_kelas_id'] : null;
    
    if ($namaKelas && $tingkat) {
        try {
            $stmt = $pdo->prepare("INSERT INTO kelas (nama_kelas, tingkat, wali_kelas_id) VALUES (:nama, :tingkat, :wali)");
            $stmt->execute([':nama' => $namaKelas, ':tingkat' => $tingkat, ':wali' => $waliKelasId]);
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

// Ambil Daftar Guru Aktif untuk Dropdown Wali Kelas
$guruList = $pdo->query("
    SELECT u.id, u.nama_lengkap, gd.gelar_depan, gd.gelar_belakang, gd.nip
    FROM users u 
    LEFT JOIN guru_detail gd ON u.id = gd.user_id 
    WHERE u.role = 'guru' AND u.is_active = 1 
    ORDER BY u.nama_lengkap ASC
")->fetchAll();

// Ambil Data Kelas beserta Data Wali Kelas
$kelasList = $pdo->query("
    SELECT k.*, 
           u.nama_lengkap AS wali_nama,
           gd.gelar_depan AS wali_gelar_depan,
           gd.gelar_belakang AS wali_gelar_belakang,
           gd.no_hp AS wali_no_hp,
           (SELECT COUNT(id) FROM siswa WHERE kelas_id = k.id AND status='aktif') as jumlah_siswa 
    FROM kelas k 
    LEFT JOIN users u ON k.wali_kelas_id = u.id
    LEFT JOIN guru_detail gd ON u.id = gd.user_id
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
                            <th style="width: 50px;">No</th>
                            <th style="width: 90px;">Tingkat</th>
                            <th>Nama Kelas</th>
                            <th>Wali Kelas</th>
                            <th>Jumlah Siswa</th>
                            <th style="width: 90px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($kelasList as $i => $k): ?>
                        <tr>
                            <td class="text-muted font-monospace"><?= $i+1 ?></td>
                            <td><span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle px-2.5 py-1 rounded-pill fw-bold"><?= $k['tingkat'] ?></span></td>
                            <td><strong class="text-dark fs-6"><?= htmlspecialchars($k['nama_kelas']) ?></strong></td>
                            <td>
                                <?php if (!empty($k['wali_nama'])): 
                                    $namaWali = formatNamaGuruGelar($k['wali_nama'], $k['wali_gelar_depan'], $k['wali_gelar_belakang']);
                                ?>
                                    <div class="table-avatar-item">
                                        <div class="table-avatar-circle" style="background: #eff6ff; color: #2563eb; border-color: #bfdbfe;">
                                            <i class="bi bi-person-check-fill"></i>
                                        </div>
                                        <div>
                                            <span class="fw-bold text-dark d-block" style="font-size: 0.84rem;"><?= $namaWali ?></span>
                                            <?php if (!empty($k['wali_no_hp'])): ?>
                                                <small class="text-muted d-inline-flex align-items-center gap-1 font-monospace" style="font-size: 0.72rem;">
                                                    <i class="bi bi-whatsapp text-success"></i> <?= htmlspecialchars($k['wali_no_hp']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border px-2.5 py-1 rounded-pill fw-normal" style="font-size: 0.72rem;">
                                        <i class="bi bi-dash-circle me-1 opacity-50"></i> Belum Ditentukan
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="nominal-pill text-dark border-0" style="background-color: #f1f5f9; color: #334155;">
                                    <i class="bi bi-people me-1 text-primary"></i> <?= $k['jumlah_siswa'] ?> Siswa
                                </span>
                            </td>
                            <td>
                                <a href="edit.php?id=<?= $k['id'] ?>" class="btn-sm-action btn-edit" title="Edit Kelas"><i class="bi bi-pencil"></i></a>
                                <?php if ($k['jumlah_siswa'] == 0): ?>
                                    <button onclick="confirmDelete('delete.php?id=<?= $k['id'] ?>', 'Kelas <?= htmlspecialchars($k['nama_kelas']) ?>')" class="btn-sm-action btn-delete" title="Hapus Kelas"><i class="bi bi-trash"></i></button>
                                <?php else: ?>
                                    <button onclick="alert('Tidak dapat menghapus kelas yang masih memiliki siswa aktif.')" class="btn-sm-action btn-delete" style="opacity:0.4; cursor:not-allowed;" title="Ada siswa aktif"><i class="bi bi-trash"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($kelasList)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data kelas.</td></tr>
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
                    <label class="form-label fw-bold">Tingkat <span class="text-danger">*</span></label>
                    <select name="tingkat" class="form-select" required>
                        <option value="">-- Pilih Tingkat --</option>
                        <option value="VII">VII (Tujuh)</option>
                        <option value="VIII">VIII (Delapan)</option>
                        <option value="IX">IX (Sembilan)</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Nama Kelas <span class="text-danger">*</span></label>
                    <input type="text" name="nama_kelas" class="form-control" placeholder="Contoh: VII-A, VIII-B" required>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Wali Kelas <small class="text-muted fw-normal">(Opsional)</small></label>
                    <select name="wali_kelas_id" class="form-select">
                        <option value="">-- Pilih Wali Kelas (Opsional) --</option>
                        <?php foreach ($guruList as $g): 
                            $nGuru = formatNamaGuruGelar($g['nama_lengkap'], $g['gelar_depan'], $g['gelar_belakang']);
                        ?>
                            <option value="<?= $g['id'] ?>"><?= $nGuru ?> <?= !empty($g['nip']) ? '('.$g['nip'].')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted" style="font-size: 0.75rem;">Guru yang bertanggung jawab sebagai wali kelas ini.</small>
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
