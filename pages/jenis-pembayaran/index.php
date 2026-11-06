<?php
/**
 * JENIS PEMBAYARAN - CRUD kategori pembayaran dinamis
 */
$pageTitle = 'Jenis Pembayaran';
$activePage = 'jenis-pembayaran';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('jenis_pembayaran');

$pdo = getConnection();

// Proses tambah/edit via modal (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $nama = trim($_POST['nama_pembayaran'] ?? '');
    $nominal = (float) str_replace(['.', ','], ['', '.'], $_POST['nominal_default'] ?? '0');
    $ket = trim($_POST['keterangan'] ?? '');
    $targetTingkat = trim($_POST['target_tingkat'] ?? 'all');

    if ($action === 'create' && $nama) {
        try {
            $stmt = $pdo->prepare("INSERT INTO jenis_pembayaran (nama_pembayaran, nominal_default, keterangan, target_tingkat) VALUES (:nama, :nom, :ket, :tt)");
            $stmt->execute([':nama' => $nama, ':nom' => $nominal, ':ket' => $ket, ':tt' => $targetTingkat]);
            redirect('index.php', 'success', 'Jenis pembayaran berhasil ditambahkan.');
        } catch (PDOException $e) {
            redirect('index.php', 'danger', 'Nama jenis pembayaran sudah ada.');
        }
    } elseif ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'aktif';
        $stmt = $pdo->prepare("UPDATE jenis_pembayaran SET nama_pembayaran = :nama, nominal_default = :nom, keterangan = :ket, status = :st, target_tingkat = :tt WHERE id = :id");
        $stmt->execute([':nama' => $nama, ':nom' => $nominal, ':ket' => $ket, ':st' => $status, ':tt' => $targetTingkat, ':id' => $id]);
        redirect('index.php', 'success', 'Data berhasil diperbarui.');
    }
}

// Hapus
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    try {
        $pdo->beginTransaction();

        $pdo->prepare("DELETE FROM pembayaran_pending WHERE jenis_pembayaran_id = :id")->execute([':id' => $id]);
        $pdo->prepare("DELETE FROM pembayaran_lain WHERE jenis_pembayaran_id = :id")->execute([':id' => $id]);

        $pdo->prepare("DELETE FROM jenis_pembayaran WHERE id=:id")->execute([':id' => $id]);

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

<!-- SUB-TAB PILL NAVIGATION (SPP, Uang Pangkal, Pembayaran Lain, Jenis Pembayaran) -->
<div class="rekap-tabs-bar mb-4 overflow-auto pb-1">
    <div class="rekap-tabs-pill-container">
        <?php if (hasPermission('spp')): ?>
            <a href="<?= BASE_URL ?>/pages/spp/index.php"
                class="rekap-tab-pill <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/pages/spp/') !== false ? 'active' : '' ?>">
                <i class="bi bi-cash-stack"></i> SPP Bulanan
            </a>
        <?php endif; ?>
        <?php if (hasPermission('uang_pangkal')): ?>
            <a href="<?= BASE_URL ?>/pages/uang-pangkal/index.php"
                class="rekap-tab-pill <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/pages/uang-pangkal/') !== false ? 'active' : '' ?>">
                <i class="bi bi-wallet2"></i> Uang Pangkal
            </a>
        <?php endif; ?>
        <?php if (hasPermission('pembayaran_lain')): ?>
            <a href="<?= BASE_URL ?>/pages/pembayaran-lain/index.php"
                class="rekap-tab-pill <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/pages/pembayaran-lain/') !== false ? 'active' : '' ?>">
                <i class="bi bi-receipt-cutoff"></i> Pembayaran Lain
            </a>
        <?php endif; ?>
        <?php if (hasPermission('jenis_pembayaran')): ?>
            <a href="<?= BASE_URL ?>/pages/jenis-pembayaran/index.php"
                class="rekap-tab-pill <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/pages/jenis-pembayaran/') !== false ? 'active' : '' ?>">
                <i class="bi bi-tags-fill"></i> Jenis Pembayaran
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Total: <strong><?= count($jenisList) ?></strong> jenis</p>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalTambah"><i
            class="bi bi-plus-lg"></i> Tambah Jenis</button>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Pembayaran</th>
                    <th>Nominal Default</th>
                    <th>Berlaku Untuk</th>
                    <th>Keterangan</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <!-- Row Statis SPP (Sistem) -->
                <tr style="background-color: #f8f9fa;">
                    <td>-</td>
                    <td><strong>📅 Pembayaran SPP</strong></td>
                    <td>-</td>
                    <td><span class="badge bg-secondary-subtle text-secondary fw-bold">Semua Kelas (Per Siswa)</span>
                    </td>
                    <td><small class="text-muted">Dikelola otomatis oleh sistem (bulanan)</small></td>
                    <td><span class="badge-status badge-aktif">Sistem</span></td>
                    <td><span class="text-muted">Fixed</span></td>
                </tr>
                <tr style="background-color: #f8f9fa;">
                    <td>-</td>
                    <td><strong>🏦 Uang Pangkal (Masuk)</strong></td>
                    <td>-</td>
                    <td><span class="badge bg-primary-subtle text-primary fw-bold">Khusus Kelas 7 (Siswa Baru)</span>
                    </td>
                    <td><small class="text-muted">Target per siswa, mendukung cicilan & persentase</small></td>
                    <td><span class="badge-status badge-aktif">Sistem</span></td>
                    <td><a href="../uang-pangkal/index.php" class="btn btn-sm btn-outline-primary py-0"
                            style="font-size: .7rem">Kelola</a></td>
                </tr>
                <?php foreach ($jenisList as $i => $j): ?>
                    <?php
                    $tt = $j['target_tingkat'] ?? 'all';
                    $badgeTingkat = match ($tt) {
                        '7' => '<span class="badge bg-info-subtle text-info fw-bold">Khusus Kelas 7</span>',
                        '8' => '<span class="badge bg-primary-subtle text-primary fw-bold">Khusus Kelas 8</span>',
                        '9' => '<span class="badge bg-warning-subtle text-dark fw-bold">Khusus Kelas 9</span>',
                        '8,9' => '<span class="badge bg-primary text-white fw-bold">Kelas 8 & 9 (Daftar Ulang)</span>',
                        '7,8' => '<span class="badge bg-info text-dark fw-bold">Kelas 7 & 8</span>',
                        '7,9' => '<span class="badge bg-secondary text-white fw-bold">Kelas 7 & 9</span>',
                        default => '<span class="badge bg-secondary-subtle text-secondary fw-bold">Semua Kelas</span>',
                    };
                    ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= htmlspecialchars($j['nama_pembayaran']) ?></strong></td>
                        <td><?= formatRupiah($j['nominal_default']) ?></td>
                        <td><?= $badgeTingkat ?></td>
                        <td><?= htmlspecialchars($j['keterangan'] ?? '-') ?></td>
                        <td><span
                                class="badge-status <?= $j['status'] === 'aktif' ? 'badge-aktif' : 'badge-belum' ?>"><?= ucfirst($j['status']) ?></span>
                        </td>
                        <td>
                            <button class="btn-sm-action btn-edit"
                                onclick="editJenis(<?= htmlspecialchars(json_encode($j)) ?>)"><i
                                    class="bi bi-pencil"></i></button>
                            <button
                                onclick="confirmDelete('index.php?delete=<?= $j['id'] ?>','<?= htmlspecialchars($j['nama_pembayaran']) ?>')"
                                class="btn-sm-action btn-delete"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($jenisList)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Belum ada data.</td>
                    </tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Jenis Pembayaran</h5><button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nama Pembayaran *</label><input type="text"
                            name="nama_pembayaran" class="form-control" required
                            placeholder="Contoh: Daftar Ulang TA 2026/2027"></div>
                    <div class="mb-3"><label class="form-label">Nominal Default (Rp)</label><input type="text"
                            name="nominal_default" class="form-control" value="0"></div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Berlaku Untuk Kelas / Tingkat *</label>
                        <select name="target_tingkat" class="form-select" required>
                            <option value="all">Semua Tingkat (Semua Kelas)</option>
                            <option value="8,9">Kelas 8 &amp; 9 (Siswa Lama / Daftar Ulang)</option>
                            <option value="7">Khusus Kelas 7 (Siswa Baru)</option>
                            <option value="8">Khusus Kelas 8 Saja</option>
                            <option value="9">Khusus Kelas 9 Saja</option>
                            <option value="7,8">Kelas 7 &amp; 8</option>
                            <option value="7,9">Kelas 7 &amp; 9</option>
                        </select>
                        <small class="text-muted" style="font-size: 0.75rem;">Contoh: Jika memilih <strong>Kelas 8 &amp;
                                9</strong>, maka siswa Kelas 7 tidak akan dibebani tagihan ini.</small>
                    </div>
                    <div class="mb-3"><label class="form-label">Keterangan</label><input type="text" name="keterangan"
                            class="form-control"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light"
                        data-bs-dismiss="modal">Batal</button><button type="submit"
                        class="btn-primary-custom">Simpan</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Jenis Pembayaran</h5><button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nama Pembayaran *</label><input type="text"
                            name="nama_pembayaran" id="editNama" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Nominal Default (Rp)</label><input type="text"
                            name="nominal_default" id="editNominal" class="form-control"></div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Berlaku Untuk Kelas / Tingkat *</label>
                        <select name="target_tingkat" id="editTargetTingkat" class="form-select" required>
                            <option value="all">Semua Tingkat (Semua Kelas)</option>
                            <option value="8,9">Kelas 8 &amp; 9 (Siswa Lama / Daftar Ulang)</option>
                            <option value="7">Khusus Kelas 7 (Siswa Baru)</option>
                            <option value="8">Khusus Kelas 8 Saja</option>
                            <option value="9">Khusus Kelas 9 Saja</option>
                            <option value="7,8">Kelas 7 &amp; 8</option>
                            <option value="7,9">Kelas 7 &amp; 9</option>
                        </select>
                        <small class="text-muted" style="font-size: 0.75rem;">Contoh: Jika memilih <strong>Kelas 8 &amp;
                                9</strong>, maka siswa Kelas 7 tidak akan dibebani tagihan ini.</small>
                    </div>
                    <div class="mb-3"><label class="form-label">Keterangan</label><input type="text" name="keterangan"
                            id="editKet" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Status</label><select name="status" id="editStatus"
                            class="form-select">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light"
                        data-bs-dismiss="modal">Batal</button><button type="submit"
                        class="btn-primary-custom">Simpan</button></div>
            </form>
        </div>
    </div>
</div>

<script>
    function editJenis(data) {
        document.getElementById('editId').value = data.id;
        document.getElementById('editNama').value = data.nama_pembayaran;
        document.getElementById('editNominal').value = data.nominal_default;
        document.getElementById('editKet').value = data.keterangan || '';
        document.getElementById('editStatus').value = data.status;
        document.getElementById('editTargetTingkat').value = data.target_tingkat || 'all';
        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>