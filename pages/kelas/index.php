<?php
/**
 * DATA KELAS - CRUD Master Kelas
 */
$pageTitle = 'Data Kelas';
$activePage = 'kelas';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('kelas');

$pdo = getConnection();

// Auto-check & self heal columns
try {
    $checkStatusCol = $pdo->query("SHOW COLUMNS FROM kelas LIKE 'status'")->fetch();
    if (!$checkStatusCol) {
        $pdo->exec("ALTER TABLE kelas ADD COLUMN status ENUM('aktif','nonaktif') DEFAULT 'aktif' AFTER tingkat");
    }
    $checkActiveCol = $pdo->query("SHOW COLUMNS FROM kelas LIKE 'is_active'")->fetch();
    if (!$checkActiveCol) {
        $pdo->exec("ALTER TABLE kelas ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER status");
    }
} catch (Exception $e) {
    // Ignore if already exists or permission issue
}

// Helper format nama guru dengan gelar
function formatNamaGuruGelar($nama, $gDepan = '', $gBelakang = '')
{
    $depan = !empty($gDepan) ? trim($gDepan) . ' ' : '';
    $belakang = !empty($gBelakang) ? ', ' . trim($gBelakang) : '';
    return htmlspecialchars($depan . $nama . $belakang);
}

// Proses Tambah Kelas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $namaKelas = trim($_POST['nama_kelas']);
    $tingkat = $_POST['tingkat'];
    $statusKelas = ($_POST['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
    $isActive = ($statusKelas === 'aktif') ? 1 : 0;
    $waliKelasId = !empty($_POST['wali_kelas_id']) ? (int) $_POST['wali_kelas_id'] : null;

    if ($namaKelas && $tingkat) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO kelas (nama_kelas, tingkat, wali_kelas_id, status, is_active) 
                VALUES (:nama, :tingkat, :wali, :status, :is_act)
            ");
            $stmt->execute([
                ':nama' => $namaKelas,
                ':tingkat' => $tingkat,
                ':wali' => $waliKelasId,
                ':status' => $statusKelas,
                ':is_act' => $isActive
            ]);
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
    WHERE u.role IN ('guru', 'bendahara', 'admin', 'operator') AND u.is_active = 1 
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
    ORDER BY (CASE WHEN (k.status = 'nonaktif' OR k.is_active = 0) THEN 1 ELSE 0 END), k.tingkat, k.nama_kelas
")->fetchAll();

// Hitung total seluruh siswa dan status kelas
$totalSiswaSemuaKelas = 0;
$totalKelasAktif = 0;
$totalKelasNonaktif = 0;
foreach ($kelasList as $k) {
    $totalSiswaSemuaKelas += (int) $k['jumlah_siswa'];
    $isKlsAktif = ($k['status'] ?? 'aktif') === 'aktif' && ($k['is_active'] ?? 1) == 1;
    if ($isKlsAktif) {
        $totalKelasAktif++;
    } else {
        $totalKelasNonaktif++;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- STATISTIK RINGKAS KELAS -->
<div class="row g-3 mb-4">
    <div class="col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-3 d-flex flex-row align-items-center gap-3"
            style="border: 1px solid #e2e8f0 !important;">
            <div class="rounded-4 text-white d-flex align-items-center justify-content-center shadow-sm flex-shrink-0"
                style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); width: 48px; height: 48px;">
                <i class="bi bi-building-fill fs-4"></i>
            </div>
            <div>
                <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.4px;">
                    Total Rombel Kelas</div>
                <h4 class="fw-black mb-0 text-dark" style="font-size: 1.45rem;">
                    <?= count($kelasList) ?> <span class="fs-7 fw-normal text-muted">Kelas</span>
                    <span class="fs-7 fw-semibold text-success ms-1">(<?= $totalKelasAktif ?> Aktif</span>
                    <?php if ($totalKelasNonaktif > 0): ?>
                        <span class="fs-7 fw-semibold text-danger">, <?= $totalKelasNonaktif ?> Nonaktif</span>
                    <?php endif; ?>
                    <span class="fs-7 fw-semibold text-success">)</span>
                </h4>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-3 d-flex flex-row align-items-center gap-3"
            style="border: 1px solid #e2e8f0 !important;">
            <div class="rounded-4 text-white d-flex align-items-center justify-content-center shadow-sm flex-shrink-0"
                style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); width: 48px; height: 48px;">
                <i class="bi bi-people-fill fs-4"></i>
            </div>
            <div>
                <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.4px;">
                    Total Seluruh Santri</div>
                <h4 class="fw-black mb-0 text-success" style="font-size: 1.45rem;">
                    <?= number_format($totalSiswaSemuaKelas) ?> <span class="fs-7 fw-normal text-muted">Santri</span>
                </h4>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-12">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-3 d-flex flex-row align-items-center gap-3"
            style="border: 1px solid #e2e8f0 !important;">
            <div class="rounded-4 text-white d-flex align-items-center justify-content-center shadow-sm flex-shrink-0"
                style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); width: 48px; height: 48px;">
                <i class="bi bi-mortarboard-fill fs-4"></i>
            </div>
            <div>
                <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.4px;">
                    Jenjang Pendidikan</div>
                <h4 class="fw-black mb-0 text-dark" style="font-size: 1.25rem;">SMP <span
                        class="fs-7 fw-semibold text-primary">(VII, VIII, IX)</span></h4>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Tabel Data Kelas -->
    <div class="col-md-8 mb-4">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white"
            style="border: 1px solid #e2e8f0 !important;">
            <div class="card-header bg-white p-3.5 border-bottom d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 text-white d-flex align-items-center justify-content-center shadow-xs flex-shrink-0"
                        style="background: linear-gradient(135deg, #1e3a8a 0%, #1e1b4b 100%); width: 42px; height: 42px;">
                        <i class="bi bi-grid-fill fs-5"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">Data Kelas & Rombongan Belajar</h5>
                        <small class="text-muted" style="font-size: 0.76rem;">Kelola rombel, status keaktifan, dan wali
                            kelas</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span
                        class="badge bg-success bg-opacity-10 text-success border border-success-subtle rounded-pill px-2.5 py-1 fw-bold"
                        style="font-size: 0.75rem;">
                        <?= $totalKelasAktif ?> AKTIF
                    </span>
                    <?php if ($totalKelasNonaktif > 0): ?>
                        <span
                            class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1 fw-bold"
                            style="font-size: 0.75rem;">
                            <?= $totalKelasNonaktif ?> NONAKTIF
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="table-responsive p-0">
                <table class="data-table mb-0" id="tableKelas">
                    <thead>
                        <tr>
                            <th style="width: 45px;">No</th>
                            <th style="width: 80px;">Tingkat</th>
                            <th>Nama Kelas</th>
                            <th style="width: 110px;">Status</th>
                            <th>Wali Kelas</th>
                            <th>Jumlah Siswa</th>
                            <th style="width: 120px;" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kelasList as $i => $k):
                            $isAktif = ($k['status'] ?? 'aktif') === 'aktif' && ($k['is_active'] ?? 1) == 1;
                            ?>
                            <tr class="<?= !$isAktif ? 'bg-light text-muted opacity-75' : '' ?>">
                                <td class="text-muted font-monospace"><?= $i + 1 ?></td>
                                <td>
                                    <span
                                        class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle px-2.5 py-1 rounded-pill fw-bold font-monospace">
                                        <?= $k['tingkat'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <strong
                                            class="<?= $isAktif ? 'text-dark' : 'text-secondary text-decoration-line-through' ?> fs-6">
                                            <?= htmlspecialchars($k['nama_kelas']) ?>
                                        </strong>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($isAktif): ?>
                                        <span
                                            class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-2.5 py-1 rounded-pill fw-semibold"
                                            style="font-size: 0.72rem;">
                                            <i class="bi bi-check-circle-fill me-1"></i> Aktif
                                        </span>
                                    <?php else: ?>
                                        <span
                                            class="badge bg-secondary bg-opacity-15 text-secondary border border-secondary-subtle px-2.5 py-1 rounded-pill fw-semibold"
                                            style="font-size: 0.72rem;">
                                            <i class="bi bi-slash-circle-fill me-1"></i> Nonaktif
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($k['wali_nama'])):
                                        $namaWali = formatNamaGuruGelar($k['wali_nama'], $k['wali_gelar_depan'], $k['wali_gelar_belakang']);
                                        ?>
                                        <div class="table-avatar-item">
                                            <div class="table-avatar-circle"
                                                style="background: #eff6ff; color: #2563eb; border-color: #bfdbfe;">
                                                <i class="bi bi-person-check-fill"></i>
                                            </div>
                                            <div>
                                                <span class="fw-bold text-dark d-block"
                                                    style="font-size: 0.84rem;"><?= $namaWali ?></span>
                                                <?php if (!empty($k['wali_no_hp'])): ?>
                                                    <small class="text-muted d-inline-flex align-items-center gap-1 font-monospace"
                                                        style="font-size: 0.72rem;">
                                                        <i class="bi bi-whatsapp text-success"></i>
                                                        <?= htmlspecialchars($k['wali_no_hp']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border px-2.5 py-1 rounded-pill fw-normal"
                                            style="font-size: 0.72rem;">
                                            <i class="bi bi-dash-circle me-1 opacity-50"></i> Belum Ditentukan
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="nominal-pill text-dark border-0"
                                        style="background-color: #f1f5f9; color: #334155;">
                                        <i class="bi bi-people me-1 text-primary"></i> <?= $k['jumlah_siswa'] ?> Siswa
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <!-- Toggle Status Aktif / Nonaktif -->
                                        <?php if ($isAktif): ?>
                                            <a href="toggle-status.php?id=<?= $k['id'] ?>&status=nonaktif"
                                                class="btn-sm-action btn-delete" title="Nonaktifkan Kelas"
                                                onclick="return confirm('Apakah Anda yakin ingin menonaktifkan Kelas <?= htmlspecialchars(addslashes($k['nama_kelas'])) ?>?')">
                                                <i class="bi bi-toggle-on text-success fs-5"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="toggle-status.php?id=<?= $k['id'] ?>&status=aktif"
                                                class="btn-sm-action btn-edit" title="Aktifkan Kelas"
                                                onclick="return confirm('Aktifkan kembali Kelas <?= htmlspecialchars(addslashes($k['nama_kelas'])) ?>?')">
                                                <i class="bi bi-toggle-off text-muted fs-5"></i>
                                            </a>
                                        <?php endif; ?>

                                        <!-- Edit -->
                                        <a href="edit.php?id=<?= $k['id'] ?>" class="btn-sm-action btn-edit"
                                            title="Edit Kelas">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <!-- Delete -->
                                        <?php if ($k['jumlah_siswa'] == 0): ?>
                                            <button
                                                onclick="confirmDelete('delete.php?id=<?= $k['id'] ?>', 'Kelas <?= htmlspecialchars($k['nama_kelas']) ?>')"
                                                class="btn-sm-action btn-delete" title="Hapus Kelas"><i
                                                    class="bi bi-trash"></i></button>
                                        <?php else: ?>
                                            <button
                                                onclick="alert('Tidak dapat menghapus kelas yang masih memiliki siswa aktif. Anda bisa menonaktifkan status kelas ini.')"
                                                class="btn-sm-action btn-delete" style="opacity:0.3; cursor:not-allowed;"
                                                title="Ada siswa aktif (Bisa dinonaktifkan)"><i
                                                    class="bi bi-trash"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($kelasList)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Belum ada data kelas.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Form Tambah Kelas -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3.5 p-md-4 sticky-top bg-white"
            style="top: 80px; border: 1px solid #e2e8f0 !important;">
            <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                <div class="rounded-3 text-white d-flex align-items-center justify-content-center shadow-xs flex-shrink-0"
                    style="background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%); width: 40px; height: 40px;">
                    <i class="bi bi-plus-square-fill fs-5"></i>
                </div>
                <div>
                    <h5 class="mb-0 fw-bold text-dark">Tambah Kelas Baru</h5>
                    <small class="text-muted" style="font-size: 0.76rem;">Rombel tingkat VII, VIII, atau IX</small>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">

                <div class="mb-3">
                    <label class="form-label fw-bold small text-secondary">Tingkat <span
                            class="text-danger">*</span></label>
                    <select name="tingkat" class="form-select rounded-3 fw-semibold" required>
                        <option value="">-- Pilih Tingkat --</option>
                        <option value="VII">VII (Tujuh)</option>
                        <option value="VIII">VIII (Delapan)</option>
                        <option value="IX">IX (Sembilan)</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-secondary">Nama Kelas <span
                            class="text-danger">*</span></label>
                    <input type="text" name="nama_kelas" class="form-control rounded-3"
                        placeholder="Contoh: VII-A, VIII-B, IX" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-secondary">Status Kelas <span
                            class="text-danger">*</span></label>
                    <select name="status" class="form-select rounded-3 fw-semibold" required>
                        <option value="aktif" selected>🟢 Aktif (Buka Pendaftaran & Rombel)</option>
                        <option value="nonaktif">🔴 Tidak Aktif / Nonaktif (Arsip)</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold small text-secondary">Wali Kelas <small
                            class="text-muted fw-normal">(Opsional)</small></label>
                    <select name="wali_kelas_id" class="form-select rounded-3">
                        <option value="">-- Pilih Wali Kelas (Opsional) --</option>
                        <?php foreach ($guruList as $g):
                            $nGuru = formatNamaGuruGelar($g['nama_lengkap'], $g['gelar_depan'], $g['gelar_belakang']);
                            ?>
                            <option value="<?= $g['id'] ?>"><?= $nGuru ?>
                                <?= !empty($g['nip']) ? '(' . $g['nip'] . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted" style="font-size: 0.72rem;">Guru yang bertanggung jawab sebagai wali kelas
                        ini.</small>
                </div>

                <button type="submit"
                    class="btn btn-primary w-100 rounded-3 py-2.5 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2"
                    style="background: #1e1b4b; border-color: #1e1b4b;">
                    <i class="bi bi-box-arrow-in-down"></i> Simpan Kelas
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>