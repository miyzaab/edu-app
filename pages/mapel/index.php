<?php
/**
 * DATA MATA PELAJARAN - CRUD Master Mata Pelajaran (Akademik & Guru)
 */
$pageTitle  = 'Data Mata Pelajaran';
$activePage = 'mapel';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('mapel');

$pdo = getConnection();

// 1. TAMBAH MAPEL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $kodeMapel = strtoupper(trim($_POST['kode_mapel'] ?? ''));
    $namaMapel = trim($_POST['nama_mapel'] ?? '');
    $kelompok  = trim($_POST['kelompok'] ?? 'Wajib');
    $kkm       = (float)($_POST['kkm'] ?? 75);
    $status    = trim($_POST['status'] ?? 'aktif');

    if ($kodeMapel && $namaMapel) {
        try {
            $stmt = $pdo->prepare("INSERT INTO mata_pelajaran (kode_mapel, nama_mapel, kelompok, kkm, status) VALUES (:kode, :nama, :kel, :kkm, :st)");
            $stmt->execute([
                ':kode' => $kodeMapel,
                ':nama' => $namaMapel,
                ':kel'  => $kelompok,
                ':kkm'   => $kkm,
                ':st'   => $status
            ]);
            redirect('index.php', 'success', "Mata Pelajaran '$namaMapel' ($kodeMapel) berhasil ditambahkan.");
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                redirect('index.php', 'danger', "Kode Mata Pelajaran '$kodeMapel' sudah digunakan.");
            } else {
                redirect('index.php', 'danger', "Gagal menambah mata pelajaran: " . $e->getMessage());
            }
        }
    } else {
        redirect('index.php', 'warning', "Kode & Nama Mata Pelajaran wajib diisi.");
    }
}

// 2. EDIT MAPEL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id        = (int)($_POST['id'] ?? 0);
    $kodeMapel = strtoupper(trim($_POST['kode_mapel'] ?? ''));
    $namaMapel = trim($_POST['nama_mapel'] ?? '');
    $kelompok  = trim($_POST['kelompok'] ?? 'Wajib');
    $kkm       = (float)($_POST['kkm'] ?? 75);
    $status    = trim($_POST['status'] ?? 'aktif');

    if ($id > 0 && $kodeMapel && $namaMapel) {
        try {
            $stmt = $pdo->prepare("UPDATE mata_pelajaran SET kode_mapel = :kode, nama_mapel = :nama, kelompok = :kel, kkm = :kkm, status = :st WHERE id = :id");
            $stmt->execute([
                ':kode' => $kodeMapel,
                ':nama' => $namaMapel,
                ':kel'  => $kelompok,
                ':kkm'  => $kkm,
                ':st'   => $status,
                ':id'   => $id
            ]);
            redirect('index.php', 'success', "Mata Pelajaran '$namaMapel' berhasil diperbarui.");
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                redirect('index.php', 'danger', "Kode Mata Pelajaran '$kodeMapel' sudah digunakan oleh mapel lain.");
            } else {
                redirect('index.php', 'danger', "Gagal memperbarui mata pelajaran: " . $e->getMessage());
            }
        }
    } else {
        redirect('index.php', 'warning', "Data mapel tidak valid.");
    }
}

// 3. HAPUS MAPEL
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM mata_pelajaran WHERE id = :id");
            $stmt->execute([':id' => $id]);
            redirect('index.php', 'success', "Mata Pelajaran berhasil dihapus.");
        } catch (PDOException $e) {
            redirect('index.php', 'danger', "Gagal menghapus mata pelajaran: " . $e->getMessage());
        }
    }
}

// AMBIL DATA & STATISTIK
$search = trim($_GET['q'] ?? '');
$filterKelompok = trim($_GET['kelompok'] ?? '');

$sql = "SELECT * FROM mata_pelajaran WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (kode_mapel LIKE :q OR nama_mapel LIKE :q)";
    $params[':q'] = "%$search%";
}

if ($filterKelompok !== '') {
    $sql .= " AND kelompok = :kel";
    $params[':kel'] = $filterKelompok;
}

$sql .= " ORDER BY kelompok ASC, kode_mapel ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$mapelList = $stmt->fetchAll();

// Stat Ringkasan
$totalMapel = count($mapelList);
$wajibCount = 0;
$mulokCount = 0;
$pilihanCount = 0;
foreach ($mapelList as $m) {
    if ($m['kelompok'] === 'Wajib') $wajibCount++;
    elseif ($m['kelompok'] === 'Muatan Lokal') $mulokCount++;
    elseif ($m['kelompok'] === 'Pilihan') $pilihanCount++;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- HEADER & ACTION -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-md-6">
        <h4 class="fw-bold m-0 text-dark"><i class="bi bi-book-half text-primary me-2"></i> Data Mata Pelajaran</h4>
        <p class="text-muted mb-0 small">Kelola kurikulum, kode mata pelajaran, dan standar kelulusan (KKM).</p>
    </div>
    <div class="col-md-6 text-md-end d-flex justify-content-md-end gap-2">
        <a href="plotting.php" class="btn btn-outline-primary px-3 py-2 fw-bold">
            <i class="bi bi-person-workspace me-1"></i> Plotting Guru Pengampu
        </a>
        <button type="button" class="btn btn-primary-custom px-4 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#modalTambahMapel">
            <i class="bi bi-plus-lg me-1"></i> Tambah Mata Pelajaran
        </button>
    </div>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white d-flex flex-row align-items-center gap-3">
            <div class="rounded-3 p-3 bg-primary bg-opacity-10 text-primary fs-3">
                <i class="bi bi-journals"></i>
            </div>
            <div>
                <small class="text-muted d-block fw-semibold">Total Mapel</small>
                <h4 class="fw-bold m-0 text-dark"><?= $totalMapel ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white d-flex flex-row align-items-center gap-3">
            <div class="rounded-3 p-3 bg-success bg-opacity-10 text-success fs-3">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div>
                <small class="text-muted d-block fw-semibold">Kelompok Wajib</small>
                <h4 class="fw-bold m-0 text-dark"><?= $wajibCount ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white d-flex flex-row align-items-center gap-3">
            <div class="rounded-3 p-3 bg-info bg-opacity-10 text-info fs-3">
                <i class="bi bi-star-fill"></i>
            </div>
            <div>
                <small class="text-muted d-block fw-semibold">Kelompok Pilihan</small>
                <h4 class="fw-bold m-0 text-dark"><?= $pilihanCount ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white d-flex flex-row align-items-center gap-3">
            <div class="rounded-3 p-3 bg-warning bg-opacity-10 text-warning-emphasis fs-3">
                <i class="bi bi-geo-alt-fill"></i>
            </div>
            <div>
                <small class="text-muted d-block fw-semibold">Muatan Lokal</small>
                <h4 class="fw-bold m-0 text-dark"><?= $mulokCount ?></h4>
            </div>
        </div>
    </div>
</div>

<!-- FILTER & SEARCH -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-3">
        <form method="GET" class="row g-2">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Cari nama atau kode mata pelajaran..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="kelompok" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Semua Kelompok --</option>
                    <option value="Wajib" <?= $filterKelompok === 'Wajib' ? 'selected' : '' ?>>Wajib</option>
                    <option value="Pilihan" <?= $filterKelompok === 'Pilihan' ? 'selected' : '' ?>>Pilihan</option>
                    <option value="Muatan Lokal" <?= $filterKelompok === 'Muatan Lokal' ? 'selected' : '' ?>>Muatan Lokal</option>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-secondary fw-semibold"><i class="bi bi-filter me-1"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- TABLE MAPEL -->
<div class="table-card mb-4">
    <div class="table-responsive">
        <table class="data-table align-middle">
            <thead>
                <tr>
                    <th style="width: 50px;" class="text-center">No</th>
                    <th style="width: 120px;">Kode</th>
                    <th>Nama Mata Pelajaran</th>
                    <th style="width: 150px;">Kelompok</th>
                    <th style="width: 100px;" class="text-center">KKM</th>
                    <th style="width: 110px;" class="text-center">Status</th>
                    <th style="width: 120px;" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($mapelList)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-book display-4 d-block mb-2 opacity-50"></i>
                        Belum ada data mata pelajaran.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($mapelList as $idx => $row): ?>
                <tr>
                    <td class="text-center text-muted fw-semibold"><?= $idx + 1 ?></td>
                    <td><span class="badge bg-light text-primary border px-2 py-1 font-monospace fs-6"><?= htmlspecialchars($row['kode_mapel']) ?></span></td>
                    <td class="fw-bold text-dark"><?= htmlspecialchars($row['nama_mapel']) ?></td>
                    <td>
                        <?php if ($row['kelompok'] === 'Wajib'): ?>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20 px-2 py-1"><i class="bi bi-check-circle me-1"></i> Wajib</span>
                        <?php elseif ($row['kelompok'] === 'Pilihan'): ?>
                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-20 px-2 py-1"><i class="bi bi-star me-1"></i> Pilihan</span>
                        <?php else: ?>
                            <span class="badge bg-warning bg-opacity-10 text-warning-emphasis border border-warning border-opacity-20 px-2 py-1"><i class="bi bi-geo-alt me-1"></i> Muatan Lokal</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center fw-bold text-primary"><?= number_format($row['kkm'], 0) ?></td>
                    <td class="text-center">
                        <?php if ($row['status'] === 'aktif'): ?>
                            <span class="badge bg-success px-2 py-1">Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-secondary px-2 py-1">Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-primary me-1" title="Edit Mapel" 
                                onclick='openModalEdit(<?= json_encode($row) ?>)'>
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <a href="index.php?action=delete&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" title="Hapus Mapel" 
                           onclick="return confirm('Apakah Anda yakin ingin menghapus mata pelajaran <?= htmlspecialchars($row['nama_mapel']) ?>?')">
                            <i class="bi bi-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL TAMBAH MAPEL -->
<div class="modal fade" id="modalTambahMapel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white p-4 rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-journal-plus me-2"></i> Tambah Mata Pelajaran</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Kode Mata Pelajaran *</label>
                        <input type="text" name="kode_mapel" class="form-control text-uppercase" placeholder="Contoh: MP012 atau MTK" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Mata Pelajaran *</label>
                        <input type="text" name="nama_mapel" class="form-control" placeholder="Contoh: Seni Rupa" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Kelompok *</label>
                            <select name="kelompok" class="form-select" required>
                                <option value="Wajib">Wajib</option>
                                <option value="Pilihan">Pilihan</option>
                                <option value="Muatan Lokal">Muatan Lokal</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nilai KKM *</label>
                            <input type="number" step="0.5" name="kkm" class="form-control" value="75" min="0" max="100" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Status *</label>
                        <select name="status" class="form-select" required>
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer p-3 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-custom px-4 fw-bold"><i class="bi bi-save me-1"></i> Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDIT MAPEL -->
<div class="modal fade" id="modalEditMapel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-warning text-dark p-4 rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i> Edit Mata Pelajaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Kode Mata Pelajaran *</label>
                        <input type="text" name="kode_mapel" id="edit_kode_mapel" class="form-control text-uppercase" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Mata Pelajaran *</label>
                        <input type="text" name="nama_mapel" id="edit_nama_mapel" class="form-control" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Kelompok *</label>
                            <select name="kelompok" id="edit_kelompok" class="form-select" required>
                                <option value="Wajib">Wajib</option>
                                <option value="Pilihan">Pilihan</option>
                                <option value="Muatan Lokal">Muatan Lokal</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nilai KKM *</label>
                            <input type="number" step="0.5" name="kkm" id="edit_kkm" class="form-control" min="0" max="100" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Status *</label>
                        <select name="status" id="edit_status" class="form-select" required>
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer p-3 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning px-4 fw-bold text-dark"><i class="bi bi-check-lg me-1"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openModalEdit(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_kode_mapel').value = data.kode_mapel;
    document.getElementById('edit_nama_mapel').value = data.nama_mapel;
    document.getElementById('edit_kelompok').value = data.kelompok;
    document.getElementById('edit_kkm').value = data.kkm;
    document.getElementById('edit_status').value = data.status;

    var modal = new bootstrap.Modal(document.getElementById('modalEditMapel'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
