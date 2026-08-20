<?php
/**
 * DATA GURU PENGAMPU (PLOTTING MAPEL & KELAS GURU)
 * Modul Akademik & Guru
 */
$pageTitle  = 'Guru Pengampu';
$activePage = 'plotting_guru';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('plotting_guru');

$pdo = getConnection();

// Year default
$selectedTahun = $_GET['tahun_ajaran'] ?? (date('Y') . '/' . (date('Y') + 1));

// 1. TAMBAH PENUGASAN (PLOTTING)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_plotting') {
    $userId      = (int)($_POST['user_id'] ?? 0);
    $mapelIds    = $_POST['mapel_id'] ?? [];
    $kelasIds    = $_POST['kelas_id'] ?? [];
    $tahunAjaran = trim($_POST['tahun_ajaran'] ?? $selectedTahun);

    if ($userId > 0 && !empty($mapelIds) && !empty($kelasIds)) {
        $countSuccess = 0;
        $stmtInsert = $pdo->prepare("
            INSERT IGNORE INTO guru_mapel_kelas (user_id, mapel_id, kelas_id, tahun_ajaran) 
            VALUES (:uid, :mid, :kid, :th)
        ");

        foreach ($mapelIds as $mid) {
            $mid = (int)$mid;
            if ($mid <= 0) continue;

            foreach ($kelasIds as $kid) {
                $kid = (int)$kid;
                if ($kid > 0) {
                    $stmtInsert->execute([
                        ':uid' => $userId,
                        ':mid' => $mid,
                        ':kid' => $kid,
                        ':th'  => $tahunAjaran
                    ]);
                    if ($stmtInsert->rowCount() > 0) {
                        $countSuccess++;
                    }
                }
            }
        }
        redirect("plotting.php?tahun_ajaran=" . urlencode($tahunAjaran), 'success', "Berhasil menambahkan $countSuccess alokasi penugasan (mapel & kelas) untuk guru.");
    } else {
        redirect("plotting.php?tahun_ajaran=" . urlencode($tahunAjaran), 'warning', "Mohon pilih Guru, minimal 1 Mata Pelajaran, dan minimal 1 Kelas.");
    }
}

// 2. HAPUS PENUGASAN
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM guru_mapel_kelas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        redirect("plotting.php?tahun_ajaran=" . urlencode($selectedTahun), 'success', "Penugasan guru pengampu berhasil dihapus.");
    }
}

// LOAD LIST GURU, MAPEL, KELAS UNTUK FORM
$gurus = $pdo->query("SELECT id, nama_lengkap, username, role FROM users WHERE role IN ('guru', 'admin', 'operator') AND is_active = 1 ORDER BY nama_lengkap ASC")->fetchAll();
$mapels = $pdo->query("SELECT id, kode_mapel, nama_mapel, kelompok FROM mata_pelajaran WHERE status = 'aktif' ORDER BY kelompok ASC, kode_mapel ASC")->fetchAll();
$kelases = $pdo->query("SELECT id, nama_kelas, tingkat FROM kelas ORDER BY tingkat ASC, nama_kelas ASC")->fetchAll();

// LOAD DATA PENUGASAN
$search = trim($_GET['q'] ?? '');
$filterGuru = (int)($_GET['guru_id'] ?? 0);

$sql = "
    SELECT gmk.id, gmk.tahun_ajaran, gmk.created_at,
           u.nama_lengkap AS nama_guru, u.username, u.role,
           m.kode_mapel, m.nama_mapel, m.kelompok,
           k.nama_kelas, k.tingkat
    FROM guru_mapel_kelas gmk
    JOIN users u ON gmk.user_id = u.id
    JOIN mata_pelajaran m ON gmk.mapel_id = m.id
    JOIN kelas k ON gmk.kelas_id = k.id
    WHERE gmk.tahun_ajaran = :th
";
$params = [':th' => $selectedTahun];

if ($search !== '') {
    $sql .= " AND (u.nama_lengkap LIKE :q OR m.nama_mapel LIKE :q OR k.nama_kelas LIKE :q)";
    $params[':q'] = "%$search%";
}

if ($filterGuru > 0) {
    $sql .= " AND gmk.user_id = :gid";
    $params[':gid'] = $filterGuru;
}

$sql .= " ORDER BY u.nama_lengkap ASC, m.nama_mapel ASC, k.nama_kelas ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$plottingList = $stmt->fetchAll();

// STATISTIK RINGKASAN
$totalPenugasan = count($plottingList);
$uniqueGuruCount = count(array_unique(array_column($plottingList, 'nama_guru')));
$uniqueMapelCount = count(array_unique(array_column($plottingList, 'nama_mapel')));

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- HEADER & ACTION -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-md-6">
        <h4 class="fw-bold m-0 text-dark"><i class="bi bi-person-workspace text-primary me-2"></i> Plotting Guru Pengampu</h4>
        <p class="text-muted mb-0 small">Atur penugasan Mata Pelajaran dan Kelas untuk masing-masing Guru Pengampu.</p>
    </div>
    <div class="col-md-6 text-md-end d-flex justify-content-md-end gap-2">
        <a href="index.php" class="btn btn-outline-secondary px-3 py-2 fw-semibold">
            <i class="bi bi-book-half me-1"></i> Data Mapel
        </a>
        <button type="button" class="btn btn-primary-custom px-4 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#modalTambahPlotting">
            <i class="bi bi-plus-lg me-1"></i> Plotting Guru Pengampu
        </button>
    </div>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
    <div class="col-md-4 col-12">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white d-flex flex-row align-items-center gap-3">
            <div class="rounded-3 p-3 bg-primary bg-opacity-10 text-primary fs-3">
                <i class="bi bi-diagram-3-fill"></i>
            </div>
            <div>
                <small class="text-muted d-block fw-semibold">Total Alokasi Penugasan</small>
                <h4 class="fw-bold m-0 text-dark"><?= $totalPenugasan ?> <span class="fs-6 fw-normal text-muted">Kelas-Mapel</span></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white d-flex flex-row align-items-center gap-3">
            <div class="rounded-3 p-3 bg-success bg-opacity-10 text-success fs-3">
                <i class="bi bi-person-badge-fill"></i>
            </div>
            <div>
                <small class="text-muted d-block fw-semibold">Guru Pengampu Aktif</small>
                <h4 class="fw-bold m-0 text-dark"><?= $uniqueGuruCount ?> <span class="fs-6 fw-normal text-muted">Guru</span></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white d-flex flex-row align-items-center gap-3">
            <div class="rounded-3 p-3 bg-info bg-opacity-10 text-info fs-3">
                <i class="bi bi-journal-check"></i>
            </div>
            <div>
                <small class="text-muted d-block fw-semibold">Mapel Terpenuhi</small>
                <h4 class="fw-bold m-0 text-dark"><?= $uniqueMapelCount ?> <span class="fs-6 fw-normal text-muted">Mata Pelajaran</span></h4>
            </div>
        </div>
    </div>
</div>

<!-- FILTER & SEARCH -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-3">
        <form method="GET" class="row g-2">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Cari nama guru, mapel, atau kelas..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="guru_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">-- Semua Guru Pengampu --</option>
                    <?php foreach ($gurus as $g): ?>
                        <option value="<?= $g['id'] ?>" <?= $filterGuru == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_lengkap']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" name="tahun_ajaran" class="form-control text-center fw-bold" value="<?= htmlspecialchars($selectedTahun) ?>" placeholder="2026/2027">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-secondary fw-semibold"><i class="bi bi-filter me-1"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- TABLE PENUGASAN -->
<div class="table-card mb-4">
    <div class="table-responsive">
        <table class="data-table align-middle">
            <thead>
                <tr>
                    <th style="width: 50px;" class="text-center">No</th>
                    <th>Nama Guru Pengampu</th>
                    <th>Mata Pelajaran</th>
                    <th style="width: 130px;" class="text-center">Kelas</th>
                    <th style="width: 140px;" class="text-center">Tahun Ajaran</th>
                    <th style="width: 80px;" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($plottingList)): ?>
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        <i class="bi bi-diagram-3 display-4 d-block mb-2 opacity-50"></i>
                        Belum ada penugasan guru pengampu untuk tahun ajaran ini.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($plottingList as $idx => $row): ?>
                <tr>
                    <td class="text-center text-muted fw-semibold"><?= $idx + 1 ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-sm rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <?= strtoupper(substr($row['nama_guru'], 0, 1)) ?>
                            </div>
                            <div>
                                <strong class="text-dark d-block"><?= htmlspecialchars($row['nama_guru']) ?></strong>
                                <small class="text-muted">@<?= htmlspecialchars($row['username']) ?></small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-light text-primary border px-2 py-1 me-1 font-monospace"><?= htmlspecialchars($row['kode_mapel']) ?></span>
                        <strong class="text-dark"><?= htmlspecialchars($row['nama_mapel']) ?></strong>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-20 px-3 py-1 fs-6 fw-bold">
                            <i class="bi bi-building me-1"></i> Kelas <?= htmlspecialchars($row['nama_kelas']) ?>
                        </span>
                    </td>
                    <td class="text-center font-monospace text-muted fw-bold"><?= htmlspecialchars($row['tahun_ajaran']) ?></td>
                    <td class="text-center">
                        <a href="plotting.php?action=delete&id=<?= $row['id'] ?>&tahun_ajaran=<?= urlencode($selectedTahun) ?>" 
                           class="btn btn-sm btn-outline-danger" title="Hapus Penugasan" 
                           onclick="return confirm('Apakah Anda yakin ingin menghapus penugasan <?= htmlspecialchars($row['nama_guru']) ?> untuk <?= htmlspecialchars($row['nama_mapel']) ?> (Kelas <?= htmlspecialchars($row['nama_kelas']) ?>)?')">
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

<!-- MODAL TAMBAH PLOTTING -->
<div class="modal fade" id="modalTambahPlotting" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white p-4 rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i> Tambah Penugasan Guru Pengampu</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_plotting">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Pilih Guru Pengampu *</label>
                            <select name="user_id" class="form-select form-select-lg" required>
                                <option value="">-- Pilih Guru --</option>
                                <?php foreach ($gurus as $g): ?>
                                    <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nama_lengkap']) ?> (<?= ucfirst($g['role']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tahun Ajaran *</label>
                            <input type="text" name="tahun_ajaran" class="form-control form-select-lg fw-bold" value="<?= htmlspecialchars($selectedTahun) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold d-block mb-2">Pilih Mata Pelajaran (Bisa Lebih dari 1) *</label>
                        <div class="p-3 bg-light border rounded-3" style="max-height: 200px; overflow-y: auto;">
                            <div class="row g-2">
                                <?php foreach ($mapels as $m): ?>
                                    <div class="col-md-6 col-12">
                                        <div class="form-check p-2 bg-white rounded border">
                                            <input class="form-check-input ms-0 me-2" type="checkbox" name="mapel_id[]" value="<?= $m['id'] ?>" id="mapel_<?= $m['id'] ?>">
                                            <label class="form-check-label text-dark cursor-pointer fw-semibold small" for="mapel_<?= $m['id'] ?>">
                                                <strong class="text-primary">[<?= $m['kode_mapel'] ?>]</strong> <?= htmlspecialchars($m['nama_mapel']) ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <small class="text-muted mt-1 d-block"><i class="bi bi-info-circle me-1"></i> Centang semua mata pelajaran yang diampu oleh guru tersebut.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold d-block mb-2">Pilih Kelas yang Diajar (Bisa Lebih dari 1) *</label>
                        <div class="p-3 bg-light border rounded-3" style="max-height: 200px; overflow-y: auto;">
                            <div class="row g-2">
                                <?php foreach ($kelases as $k): ?>
                                    <div class="col-md-4 col-6">
                                        <div class="form-check p-2 bg-white rounded border">
                                            <input class="form-check-input ms-0 me-2" type="checkbox" name="kelas_id[]" value="<?= $k['id'] ?>" id="kelas_<?= $k['id'] ?>">
                                            <label class="form-check-label fw-bold text-dark cursor-pointer" for="kelas_<?= $k['id'] ?>">
                                                Kelas <?= htmlspecialchars($k['nama_kelas']) ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <small class="text-muted mt-1 d-block"><i class="bi bi-info-circle me-1"></i> Centang semua kelas yang diampu oleh guru tersebut untuk mata pelajaran ini.</small>
                    </div>
                </div>
                <div class="modal-footer p-3 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-custom px-4 fw-bold"><i class="bi bi-save me-1"></i> Simpan Penugasan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
