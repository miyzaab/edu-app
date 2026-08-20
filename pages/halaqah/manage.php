<?php
/**
 * MODUL HALAQAH & TAHFIDZ - PENGATURAN KATEGORI & KELOMPOK HALAQAH
 */
$pageTitle  = 'Pengaturan Halaqah & Kelompok';
$activePage = 'halaqah';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('halaqah');

$pdo    = getConnection();
$userId = $_SESSION['user_id'];
$flash  = getFlash();

// Fetch Kategori
$kategoriList = $pdo->query("SELECT * FROM halaqah_kategori ORDER BY nama_kategori ASC")->fetchAll();

// Fetch Musyrif Users (Guru & Admin)
$musyrifList = $pdo->query("SELECT id, nama_lengkap, username FROM users WHERE role IN ('admin', 'guru', 'operator') ORDER BY nama_lengkap ASC")->fetchAll();

// Fetch Siswa Aktif untuk Anggota
$siswaList = $pdo->query("
    SELECT s.id, s.nis, s.nama, k.nama_kelas
    FROM siswa s
    JOIN kelas k ON s.kelas_id = k.id
    WHERE s.status = 'aktif'
    ORDER BY s.nama ASC
")->fetchAll();

// Fetch Kelompok Halaqah + Total Anggota
$kelompokList = $pdo->query("
    SELECT hk.*, hkat.nama_kategori, u.nama_lengkap AS nama_musyrif,
           (SELECT COUNT(*) FROM halaqah_anggota ha WHERE ha.kelompok_id = hk.id) AS total_anggota
    FROM halaqah_kelompok hk
    LEFT JOIN halaqah_kategori hkat ON hk.kategori_id = hkat.id
    LEFT JOIN users u ON hk.musyrif_user_id = u.id
    ORDER BY hk.nama_halaqah ASC
")->fetchAll();

// PROSES POST ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        // 1. TAMBAH KATEGORI
        if ($action === 'add_kategori') {
            $namaKat = trim($_POST['nama_kategori'] ?? '');
            $desk    = trim($_POST['deskripsi'] ?? '');
            if (empty($namaKat)) throw new Exception("Nama kategori wajib diisi.");

            $stmtIns = $pdo->prepare("INSERT INTO halaqah_kategori (nama_kategori, deskripsi) VALUES (:n, :d)");
            $stmtIns->execute([':n' => $namaKat, ':d' => $desk]);
            redirect('manage.php', 'success', '✨ Kategori halaqah baru berhasil ditambahkan!');
        }

        // 2. TAMBAH KELOMPOK HALAQAH
        elseif ($action === 'add_kelompok') {
            $namaHalaqah = trim($_POST['nama_halaqah'] ?? '');
            $kategoriId  = (int)($_POST['kategori_id'] ?? 0);
            $musyrifId   = !empty($_POST['musyrif_user_id']) ? (int)$_POST['musyrif_user_id'] : null;
            $ket         = trim($_POST['keterangan'] ?? '');

            if (empty($namaHalaqah)) throw new Exception("Nama kelompok halaqah wajib diisi.");
            if ($kategoriId <= 0) throw new Exception("Pilih kategori halaqah terlebih dahulu.");

            $stmtIns = $pdo->prepare("INSERT INTO halaqah_kelompok (kategori_id, nama_halaqah, musyrif_user_id, keterangan) VALUES (:kat, :n, :m, :k)");
            $stmtIns->execute([':kat' => $kategoriId, ':n' => $namaHalaqah, ':m' => $musyrifId, ':k' => $ket]);
            redirect('manage.php', 'success', '✨ Kelompok halaqah baru berhasil dibuat!');
        }

        // 3. TAMBAH ANGGOTA SISWA KE KELOMPOK
        elseif ($action === 'add_anggota') {
            $kelompokId = (int)($_POST['kelompok_id'] ?? 0);
            $siswaId    = (int)($_POST['siswa_id'] ?? 0);

            if ($kelompokId <= 0 || $siswaId <= 0) throw new Exception("Pilih kelompok dan siswa dengan benar.");

            $stmtIns = $pdo->prepare("INSERT INTO halaqah_anggota (kelompok_id, siswa_id) VALUES (:k, :s) ON DUPLICATE KEY UPDATE id=id");
            $stmtIns->execute([':k' => $kelompokId, ':s' => $siswaId]);
            redirect('manage.php?kelompok_id=' . $kelompokId, 'success', '✨ Siswa berhasil ditambahkan ke anggota halaqah!');
        }

        // 4. SIMPAN PENGATURAN TARGET DISPLAY ORTU
        elseif ($action === 'save_display_settings') {
            $tampilkan  = isset($_POST['tampilkan_target_ortu']) ? '1' : '0';
            $targetText = trim($_POST['target_hafalan_text'] ?? 'Juz 30 (Juz Amma) & Hadits Arba\'in');
            
            $stmtSet = $pdo->prepare("INSERT INTO halaqah_settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = :v2");
            $stmtSet->execute([':k' => 'tampilkan_target_ortu', ':v' => $tampilkan, ':v2' => $tampilkan]);
            $stmtSet->execute([':k' => 'target_hafalan_text', ':v' => $targetText, ':v2' => $targetText]);
            
            redirect('manage.php', 'success', '✨ Pengaturan tampilan target hafalan ke Orang Tua berhasil disimpan!');
        }
    } catch (Exception $e) {
        redirect('manage.php', 'danger', $e->getMessage());
    }
}

// Fetch Setting Target Ortu
$tampilkanTargetOrtu = (int)($pdo->query("SELECT setting_value FROM halaqah_settings WHERE setting_key = 'tampilkan_target_ortu'")->fetchColumn() ?? 1);
$targetHafalanText   = $pdo->query("SELECT setting_value FROM halaqah_settings WHERE setting_key = 'target_hafalan_text'")->fetchColumn() ?: "Juz 30 (Juz 'Amma) & Hadits Arba'in";

// PROSES DELETE ACTIONS
if (isset($_GET['action'])) {
    $act = $_GET['action'];
    $id  = (int)($_GET['id'] ?? 0);

    try {
        if ($act === 'del_kategori' && $id > 0) {
            $pdo->prepare("DELETE FROM halaqah_kategori WHERE id = :id")->execute([':id' => $id]);
            redirect('manage.php', 'success', 'Kategori halaqah berhasil dihapus.');
        } elseif ($act === 'del_kelompok' && $id > 0) {
            $pdo->prepare("DELETE FROM halaqah_kelompok WHERE id = :id")->execute([':id' => $id]);
            $pdo->prepare("DELETE FROM halaqah_anggota WHERE kelompok_id = :id")->execute([':id' => $id]);
            redirect('manage.php', 'success', 'Kelompok halaqah berhasil dihapus.');
        } elseif ($act === 'del_anggota' && $id > 0) {
            $kid = (int)($_GET['kelompok_id'] ?? 0);
            $pdo->prepare("DELETE FROM halaqah_anggota WHERE id = :id")->execute([':id' => $id]);
            redirect('manage.php?kelompok_id=' . $kid, 'success', 'Anggota siswa berhasil dikeluarkan dari kelompok.');
        }
    } catch (Exception $e) {
        redirect('manage.php', 'danger', 'Gagal menghapus: ' . $e->getMessage());
    }
}

// Detail Kelompok Terpilih untuk Kelola Anggota
$selectedKelompok = null;
$anggotaList = [];
$selectedKid = (int)($_GET['kelompok_id'] ?? 0);
if ($selectedKid > 0) {
    $stmtK = $pdo->prepare("SELECT hk.*, hkat.nama_kategori, u.nama_lengkap AS nama_musyrif FROM halaqah_kelompok hk LEFT JOIN halaqah_kategori hkat ON hk.kategori_id = hkat.id LEFT JOIN users u ON hk.musyrif_user_id = u.id WHERE hk.id = :id");
    $stmtK->execute([':id' => $selectedKid]);
    $selectedKelompok = $stmtK->fetch();

    if ($selectedKelompok) {
        $stmtA = $pdo->prepare("
            SELECT ha.id AS anggota_id, s.id AS siswa_id, s.nis, s.nama, k.nama_kelas
            FROM halaqah_anggota ha
            JOIN siswa s ON ha.siswa_id = s.id
            JOIN kelas k ON s.kelas_id = k.id
            WHERE ha.kelompok_id = :kid
            ORDER BY s.nama ASC
        ");
        $stmtA->execute([':kid' => $selectedKid]);
        $anggotaList = $stmtA->fetchAll();
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ACTION TABS HALAQAH -->
<div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle p-3 bg-emerald-subtle text-emerald d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: #ecfdf5; color: #059669;">
                <i class="bi bi-gear-fill fs-3"></i>
            </div>
            <div>
                <h5 class="fw-extrabold text-dark mb-0">Pengaturan Halaqah & Kelompok</h5>
                <p class="text-muted small mb-0">Kelola kategori halaqah, nama kelompok, musyrif pengampu, & anggota siswa</p>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="index.php" class="btn btn-outline-success px-3 py-2 rounded-3 fw-bold small">
                <i class="bi bi-journal-plus me-1"></i> Pencatatan Setoran
            </a>
            <a href="manage.php" class="btn btn-emerald px-3 py-2 rounded-3 fw-bold small text-white" style="background: #059669;">
                <i class="bi bi-gear-fill me-1"></i> Pengaturan Halaqah
            </a>
            <a href="laporan.php" class="btn btn-outline-secondary px-3 py-2 rounded-3 fw-bold small">
                <i class="bi bi-file-earmark-bar-graph me-1"></i> Laporan Progress
            </a>
        </div>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?= $flash['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- LEFT COLUMN: KELOLA KATEGORI & FORM KELOMPOK BARU -->
    <div class="col-lg-5">
        <!-- 1. KATEGORI HALAQAH -->
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-tags-fill me-2" style="color: #059669;"></i>Kategori Halaqah</h6>
                <button type="button" class="btn btn-sm btn-outline-success rounded-pill extra-small fw-bold" data-bs-toggle="modal" data-bs-target="#modalTambahKategori">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Kategori
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle extra-small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kategori</th>
                            <th>Deskripsi</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kategoriList as $kat): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($kat['nama_kategori']) ?></strong></td>
                                <td class="text-muted extra-small"><?= htmlspecialchars($kat['deskripsi'] ?: '-') ?></td>
                                <td class="text-end">
                                    <a href="manage.php?action=del_kategori&id=<?= $kat['id'] ?>" class="btn btn-sm btn-outline-danger py-0 px-2 rounded-2" onclick="return confirm('Hapus kategori halaqah ini?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. FORM BUAT KELOMPOK HALAQAH BARU -->
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-people-fill me-2" style="color: #059669;"></i>Buat Kelompok Halaqah Baru</h6>

            <form method="POST" action="manage.php">
                <input type="hidden" name="action" value="add_kelompok">

                <div class="mb-3">
                    <label class="form-label extra-small fw-bold text-muted">Kategori Halaqah <span class="text-danger">*</span></label>
                    <select name="kategori_id" class="form-select bg-light border-0 fw-bold" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach ($kategoriList as $kat): ?>
                            <option value="<?= $kat['id'] ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label extra-small fw-bold text-muted">Nama Kelompok Halaqah <span class="text-danger">*</span></label>
                    <input type="text" name="nama_halaqah" class="form-control bg-light border-0 fw-bold" placeholder="Contoh: Halaqah Abu Bakar / Al-Baqarah A" required>
                </div>

                <div class="mb-3">
                    <label class="form-label extra-small fw-bold text-muted">Musyrif / Pengampu</label>
                    <select name="musyrif_user_id" class="form-select bg-light border-0 fw-bold">
                        <option value="">-- Pilih Guru/Musyrif --</option>
                        <?php foreach ($musyrifList as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_lengkap']) ?> (<?= htmlspecialchars($m['username']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label extra-small fw-bold text-muted">Keterangan / Jadwal</label>
                    <textarea name="keterangan" class="form-control bg-light border-0 extra-small" rows="2" placeholder="Catatan tempat / jam pelaksanaan halaqah..."></textarea>
                </div>

                <button type="submit" class="btn w-100 rounded-3 py-2.5 fw-bold shadow-sm text-white" style="background: linear-gradient(135deg, #059669 0%, #047857 100%); border: none;">
                    <i class="bi bi-plus-circle-fill me-2"></i> Buat Kelompok Halaqah
                </button>
            </form>
        </div>

        <!-- 3. PENGATURAN DISPLAY TARGET HAFALAN KE ORANG TUA -->
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mt-4">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-eye-fill me-2 text-primary"></i>Pengaturan Display Target Ortu</h6>
            
            <form method="POST" action="manage.php">
                <input type="hidden" name="action" value="save_display_settings">

                <div class="form-check form-switch mb-3 p-3 rounded-3 bg-light border ms-0 d-flex align-items-center justify-content-between">
                    <div>
                        <label class="form-check-label fw-bold extra-small text-dark d-block" for="switchTargetOrtu">Tampilkan Target Hafalan ke Ortu</label>
                        <span class="text-muted extra-small">Aktifkan untuk menampilkan target di portal orang tua</span>
                    </div>
                    <input class="form-check-input ms-auto" type="checkbox" role="switch" id="switchTargetOrtu" name="tampilkan_target_ortu" value="1" <?= $tampilkanTargetOrtu ? 'checked' : '' ?> style="width: 2.5em; height: 1.25em;">
                </div>

                <div class="mb-3">
                    <label class="form-label extra-small fw-bold text-muted">Deskripsi Target Hafalan</label>
                    <input type="text" name="target_hafalan_text" class="form-control bg-light border-0 fw-bold extra-small" value="<?= htmlspecialchars($targetHafalanText) ?>" placeholder="Contoh: Juz 30 (Juz 'Amma) & Hadits Arba'in">
                </div>

                <button type="submit" class="btn btn-primary w-100 rounded-3 py-2 fw-bold extra-small">
                    <i class="bi bi-save2 me-1"></i> Simpan Pengaturan Display
                </button>
            </form>
        </div>
    </div>

    <!-- RIGHT COLUMN: DAFTAR KELOMPOK & DEDICATED MANAGEMENT ANGGOTA -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-grid-fill me-2" style="color: #059669;"></i>Daftar Kelompok Halaqah</h6>

            <div class="table-responsive">
                <table class="table table-hover align-middle extra-small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nama Kelompok</th>
                            <th>Kategori</th>
                            <th>Musyrif</th>
                            <th>Anggota</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kelompokList as $hk): ?>
                            <tr class="<?= $selectedKid == $hk['id'] ? 'table-warning' : '' ?>">
                                <td><strong><?= htmlspecialchars($hk['nama_halaqah']) ?></strong></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($hk['nama_kategori'] ?: 'Umum') ?></span></td>
                                <td><small class="text-muted"><?= htmlspecialchars($hk['nama_musyrif'] ?: 'Belum diplot') ?></small></td>
                                <td><span class="badge bg-emerald-subtle text-emerald border px-2"><?= $hk['total_anggota'] ?> Siswa</span></td>
                                <td class="text-end">
                                    <a href="manage.php?kelompok_id=<?= $hk['id'] ?>" class="btn btn-sm btn-outline-primary py-1 px-2 rounded-2 extra-small fw-bold">
                                        <i class="bi bi-person-plus me-1"></i> Kelola Siswa
                                    </a>
                                    <a href="manage.php?action=del_kelompok&id=<?= $hk['id'] ?>" class="btn btn-sm btn-outline-danger py-1 px-2 rounded-2" onclick="return confirm('Hapus kelompok halaqah ini?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($selectedKelompok): ?>
            <!-- CARD DETIL ANGGOTA KELOMPOK TERPILIH -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white border-start border-4 border-primary">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h6 class="fw-extrabold text-dark mb-0">Anggota Siswa: <?= htmlspecialchars($selectedKelompok['nama_halaqah']) ?></h6>
                        <small class="text-muted">Kategori: <?= htmlspecialchars($selectedKelompok['nama_kategori']) ?> &bull; Musyrif: <?= htmlspecialchars($selectedKelompok['nama_musyrif'] ?: '-') ?></small>
                    </div>
                    <span class="badge bg-primary rounded-pill px-3 py-2"><?= count($anggotaList) ?> Siswa</span>
                </div>

                <!-- FORM TAMBAH ANGGOTA SISWA -->
                <form method="POST" action="manage.php?kelompok_id=<?= $selectedKid ?>" class="row g-2 mb-4">
                    <input type="hidden" name="action" value="add_anggota">
                    <input type="hidden" name="kelompok_id" value="<?= $selectedKid ?>">
                    <div class="col-8">
                        <select name="siswa_id" class="form-select bg-light border-0 fw-bold extra-small" required>
                            <option value="">-- Pilih Siswa yang akan Ditambahkan --</option>
                            <?php foreach ($siswaList as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nama']) ?> (Kelas <?= htmlspecialchars($s['nama_kelas']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-4">
                        <button type="submit" class="btn btn-primary w-100 rounded-3 extra-small fw-bold">
                            <i class="bi bi-plus-lg me-1"></i> Tambahkan
                        </button>
                    </div>
                </form>

                <!-- TABEL DAFTAR ANGGOTA -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle extra-small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th>Kelas</th>
                                <th class="text-end">Keluarkan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($anggotaList)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">Belum ada anggota siswa di kelompok ini.</td></tr>
                            <?php else: ?>
                                <?php $no = 1; foreach ($anggotaList as $a): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><code><?= htmlspecialchars($a['nis']) ?></code></td>
                                        <td><strong><?= htmlspecialchars($a['nama']) ?></strong></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($a['nama_kelas']) ?></span></td>
                                        <td class="text-end">
                                            <a href="manage.php?action=del_anggota&id=<?= $a['anggota_id'] ?>&kelompok_id=<?= $selectedKid ?>" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="return confirm('Keluarkan siswa dari kelompok ini?')">
                                                <i class="bi bi-x-lg"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL TAMBAH KATEGORI -->
<div class="modal fade" id="modalTambahKategori" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 p-3">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-tags-fill me-1" style="color: #059669;"></i> Tambah Kategori Halaqah</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="manage.php">
                <input type="hidden" name="action" value="add_kategori">
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label extra-small fw-bold text-muted">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="nama_kategori" class="form-control bg-light border-0 fw-bold" placeholder="Contoh: Al-Qur'an / Hadits / Fiqih" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label extra-small fw-bold text-muted">Deskripsi</label>
                        <textarea name="deskripsi" class="form-control bg-light border-0 extra-small" rows="2" placeholder="Penjelasan cakupan materi kategori ini..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 extra-small fw-bold me-auto" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success rounded-3 extra-small fw-bold px-3">Simpan Kategori</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
