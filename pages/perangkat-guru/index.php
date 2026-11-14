<?php
/**
 * PERANGKAT GURU - BANK SOAL & ASESMEN (AAS KURIKULUM MERDEKA)
 * SMP ISMI - Islamic School of Minhaj Al-Ilmi
 */
$pageTitle = 'Perangkat Guru - Pembuatan Soal AAS';
$activePage = 'perangkat-guru';
$activeModule = 'akademik';

require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_guru');

$pdo = getConnection();
require_once __DIR__ . '/init_soal.php';

$userId = (int) $_SESSION['user_id'];
$userRole = strtolower($_SESSION['role'] ?? 'guru');

// Handle Upload KOP Soal PNG
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_kop_soal') {
    if (isset($_FILES['kop_file']) && $_FILES['kop_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['kop_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
            $destFile = __DIR__ . '/../../assets/img/kop_soal_ismi.png';
            if (move_uploaded_file($_FILES['kop_file']['tmp_name'], $destFile)) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Gambar KOP Soal (PNG) berhasil diperbarui!'];
            }
        }
    }
    header('Location: index.php');
    exit;
}

// Handle Filter
$filterMapel = trim($_GET['mapel'] ?? '');
$filterTingkat = trim($_GET['tingkat'] ?? '');
$filterSemester = trim($_GET['semester'] ?? '');
$filterSearch = trim($_GET['q'] ?? '');

// Build Query
$sql = "
    SELECT sn.*, u.nama_lengkap AS nama_guru, m.kode_mapel
    FROM soal_naskah sn
    LEFT JOIN users u ON sn.guru_id = u.id
    LEFT JOIN mata_pelajaran m ON sn.mapel_id = m.id
    WHERE 1=1
";
$params = [];

if ($userRole !== 'admin') {
    $sql .= " AND (sn.guru_id = :uid OR sn.guru_id IS NOT NULL)";
    $params[':uid'] = $userId;
}

if ($filterMapel !== '') {
    $sql .= " AND (sn.nama_mapel LIKE :mapel OR sn.mapel_id = :mapel_id)";
    $params[':mapel'] = "%$filterMapel%";
    $params[':mapel_id'] = (int) $filterMapel;
}

if ($filterTingkat !== '') {
    $sql .= " AND sn.tingkat_kelas = :tingkat";
    $params[':tingkat'] = $filterTingkat;
}

if ($filterSemester !== '') {
    $sql .= " AND sn.semester = :semester";
    $params[':semester'] = $filterSemester;
}

if ($filterSearch !== '') {
    $sql .= " AND (sn.judul_ujian LIKE :search OR sn.nama_mapel LIKE :search2)";
    $params[':search'] = "%$filterSearch%";
    $params[':search2'] = "%$filterSearch%";
}

$sql .= " ORDER BY (sn.guru_id = :uid_sort) DESC, sn.updated_at DESC";
$params[':uid_sort'] = $userId;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listNaskah = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung Statistik
$statTotalNaskah = count($listNaskah);
$statTotalPg = 0;
$statTotalIsian = 0;
$statTotalEssay = 0;
$statSiapCetak = 0;

foreach ($listNaskah as $n) {
    $statTotalPg += (int) $n['total_pg'];
    $statTotalIsian += (int) $n['total_isian'];
    $statTotalEssay += (int) $n['total_essay'];
    if ($n['status'] === 'siap_cetak') {
        $statSiapCetak++;
    }
}

// Ambil daftar mapel aktif untuk dropdown
$stmtMapels = $pdo->query("SELECT id, nama_mapel, kelompok FROM mata_pelajaran WHERE status = 'aktif' ORDER BY kelompok ASC, nama_mapel ASC");
$allMapels = $stmtMapels->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/subnav.php';
?>

<!-- FLASH NOTIFICATION -->
<?php if (isset($_SESSION['flash'])): ?>
    <div class="alert alert-<?= htmlspecialchars($_SESSION['flash']['type']) ?> alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-info-circle-fill fs-5"></i>
            <div><?= htmlspecialchars($_SESSION['flash']['message']) ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<!-- BANNER HERO -->
<div class="card border-0 shadow-sm rounded-4 mb-4"
    style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 45%, #4f46e5 100%); color: white;">
    <div class="card-body p-4 p-md-5 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2"
                style="background: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255, 255, 255, 0.25); backdrop-filter: blur(10px);">
                <i class="bi bi-pencil-square text-white fs-6"></i>
                <span class="small fw-bold text-white" style="letter-spacing: 0.8px;">PERANGKAT GURU • BANK SOAL ASESMEN</span>
            </div>
            <h2 class="fw-extrabold mb-1" style="font-family: 'Outfit', sans-serif;">Penyusunan Soal Ujian (AAS)</h2>
            <p class="opacity-75 small mb-0 fs-6" style="max-width: 680px;">
                Fasilitas guru untuk menyusun butir soal AAS (Pilihan Ganda A-D, Isian Singkat, &amp; Essay) dengan pembobotan poin otomatis, ekspor Microsoft Word (.doc), serta cetak PDF presisi ber-KOP resmi.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-light px-3 py-3 fw-bold rounded-pill shadow-xs d-inline-flex align-items-center gap-2"
                data-bs-toggle="modal" data-bs-target="#modalKopSoal" style="font-size: 0.95rem; border-color: rgba(255,255,255,0.4);">
                <i class="bi bi-image fs-5"></i>
                <span>Upload KOP Soal (PNG)</span>
            </button>
            <button type="button" class="btn btn-light px-4 py-3 fw-bold rounded-pill shadow-lg d-inline-flex align-items-center gap-2"
                data-bs-toggle="modal" data-bs-target="#modalCreateNaskah" style="color: #312e81; font-size: 0.95rem;">
                <i class="bi bi-plus-circle-fill text-primary fs-5"></i>
                <span>Buat Naskah Soal Baru</span>
            </button>
        </div>
    </div>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
    <div class="col-lg-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white d-flex flex-row align-items-center gap-3">
            <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white"
                style="background: linear-gradient(135deg, #4f46e5, #6366f1); width: 52px; height: 52px;">
                <i class="bi bi-collection-fill fs-4"></i>
            </div>
            <div>
                <div class="text-muted small fw-bold">Total Naskah Soal</div>
                <div class="h4 fw-bold mb-0 text-dark"><?= $statTotalNaskah ?> <small class="text-muted fs-6">paket</small></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white d-flex flex-row align-items-center gap-3">
            <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white"
                style="background: linear-gradient(135deg, #0284c7, #06b6d4); width: 52px; height: 52px;">
                <i class="bi bi-ui-checks fs-4"></i>
            </div>
            <div>
                <div class="text-muted small fw-bold">Pilihan Ganda (PG)</div>
                <div class="h4 fw-bold mb-0 text-dark"><?= $statTotalPg ?> <small class="text-muted fs-6">butir</small></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white d-flex flex-row align-items-center gap-3">
            <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white"
                style="background: linear-gradient(135deg, #0d9488, #14b8a6); width: 52px; height: 52px;">
                <i class="bi bi-input-cursor-text fs-4"></i>
            </div>
            <div>
                <div class="text-muted small fw-bold">Isian Singkat</div>
                <div class="h4 fw-bold mb-0 text-dark"><?= $statTotalIsian ?> <small class="text-muted fs-6">butir</small></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white d-flex flex-row align-items-center gap-3">
            <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white"
                style="background: linear-gradient(135deg, #f59e0b, #d97706); width: 52px; height: 52px;">
                <i class="bi bi-card-text fs-4"></i>
            </div>
            <div>
                <div class="text-muted small fw-bold">Uraian / Essay</div>
                <div class="h4 fw-bold mb-0 text-dark"><?= $statTotalEssay ?> <small class="text-muted fs-6">butir</small></div>
            </div>
        </div>
    </div>
</div>

<!-- FILTER & SEARCH BAR -->
<div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control bg-light border-start-0" placeholder="Cari mapel atau judul..." value="<?= htmlspecialchars($filterSearch) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="mapel" class="form-select bg-light">
                    <option value="">Semua Mata Pelajaran</option>
                    <?php foreach ($allMapels as $m): ?>
                        <option value="<?= htmlspecialchars($m['nama_mapel']) ?>" <?= $filterMapel === $m['nama_mapel'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['nama_mapel']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="tingkat" class="form-select bg-light">
                    <option value="">Semua Tingkat</option>
                    <option value="VII" <?= $filterTingkat === 'VII' ? 'selected' : '' ?>>Kelas VII</option>
                    <option value="VIII" <?= $filterTingkat === 'VIII' ? 'selected' : '' ?>>Kelas VIII</option>
                    <option value="IX" <?= $filterTingkat === 'IX' ? 'selected' : '' ?>>Kelas IX</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="semester" class="form-select bg-light">
                    <option value="">Semua Semester</option>
                    <option value="Ganjil" <?= $filterSemester === 'Ganjil' ? 'selected' : '' ?>>Semester Ganjil</option>
                    <option value="Genap" <?= $filterSemester === 'Genap' ? 'selected' : '' ?>>Semester Genap</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 rounded-3 fw-bold">
                    <i class="bi bi-funnel-fill me-1"></i> Filter
                </button>
                <a href="index.php" class="btn btn-outline-secondary rounded-3" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- LIST NASKAH SOAL -->
<div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
    <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-text-fill text-primary"></i> Daftar Naskah Soal Ujian
        </h5>
        <span class="badge bg-light text-muted border px-3 py-1.5 rounded-pill font-monospace">
            <?= count($listNaskah) ?> Naskah Ditemukan
        </span>
    </div>

    <?php if (empty($listNaskah)): ?>
        <div class="text-center py-5 px-3">
            <div class="rounded-circle bg-light d-inline-flex p-4 mb-3 text-muted">
                <i class="bi bi-pencil-square display-5"></i>
            </div>
            <h5 class="fw-bold text-dark">Belum Ada Naskah Soal</h5>
            <p class="text-muted small" style="max-width: 480px; margin: 0 auto 20px auto;">
                Mulai susun naskah soal Ujian Akhir Semester (AAS) Anda dengan mudah. Lengkapi dengan butir pilihan ganda, isian, dan essay beserta bobot nilainya.
            </p>
            <button type="button" class="btn btn-primary rounded-pill px-4 py-2.5 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCreateNaskah">
                <i class="bi bi-plus-circle me-1"></i> Buat Naskah Soal Pertama
            </button>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width: 5%;">No</th>
                        <th style="width: 28%;">Informasi Mata Pelajaran &amp; Ujian</th>
                        <th style="width: 15%;">Tingkat &amp; Waktu</th>
                        <th style="width: 22%;">Komposisi Soal &amp; Poin</th>
                        <th style="width: 10%;">Status</th>
                        <th class="text-end px-4" style="width: 20%;">Aksi Pengelolaan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listNaskah as $idx => $n):
                        $totalSoal = (int)$n['total_pg'] + (int)$n['total_isian'] + (int)$n['total_essay'];
                        $isOwner = ($n['guru_id'] == $userId);
                    ?>
                        <tr>
                            <td class="text-center fw-bold text-muted"><?= $idx + 1 ?></td>
                            <td>
                                <div class="fw-bold text-dark fs-6 mb-1"><?= htmlspecialchars($n['nama_mapel']) ?></div>
                                <div class="text-muted small d-flex align-items-center gap-2">
                                    <span class="badge bg-indigo-subtle text-indigo px-2 py-0.5 rounded-pill" style="background: #e0e7ff; color: #4338ca;">
                                        <?= htmlspecialchars($n['judul_ujian']) ?>
                                    </span>
                                    <span>•</span>
                                    <span><?= htmlspecialchars($n['semester']) ?> <?= htmlspecialchars($n['tahun_ajaran']) ?></span>
                                </div>
                                <?php if ($userRole === 'admin' && !empty($n['nama_guru'])): ?>
                                    <div class="text-muted small mt-1" style="font-size: 0.78rem;">
                                        <i class="bi bi-person me-1"></i> Guru: <?= htmlspecialchars($n['nama_guru']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">Kelas <?= htmlspecialchars($n['tingkat_kelas']) ?></div>
                                <div class="text-muted small"><i class="bi bi-clock me-1"></i><?= (int)$n['alokasi_waktu'] ?> Menit</div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1 mb-1">
                                    <span class="badge bg-info-subtle text-info-emphasis border px-2 py-0.5 rounded-pill" style="font-size: 0.78rem;">
                                        PG: <strong><?= (int)$n['total_pg'] ?></strong>
                                    </span>
                                    <span class="badge bg-teal-subtle text-teal-emphasis border px-2 py-0.5 rounded-pill" style="font-size: 0.78rem; background: #ccfbf1; color: #0f766e;">
                                        Isian: <strong><?= (int)$n['total_isian'] ?></strong>
                                    </span>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border px-2 py-0.5 rounded-pill" style="font-size: 0.78rem; background: #fef3c7; color: #b45309;">
                                        Essay: <strong><?= (int)$n['total_essay'] ?></strong>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge <?= (float)$n['total_poin'] >= 100 ? 'bg-success' : 'bg-primary' ?> px-2.5 py-1 rounded-pill">
                                        <i class="bi bi-award-fill me-1"></i>Total: <?= (float)$n['total_poin'] ?> Poin
                                    </span>
                                    <span class="text-muted small font-monospace">(<?= $totalSoal ?> Butir)</span>
                                </div>
                            </td>
                            <td>
                                <?php if ($n['status'] === 'siap_cetak'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 rounded-pill d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-check-circle-fill"></i> Siap Cetak
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2.5 py-1.5 rounded-pill d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-pencil-fill"></i> Draft
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end px-4">
                                <div class="btn-group shadow-xs">
                                    <a href="edit.php?id=<?= $n['id'] ?>" class="btn btn-sm btn-primary px-2.5" title="Susun &amp; Edit Soal">
                                        <i class="bi bi-pencil-square me-1"></i> Susun
                                    </a>
                                    <a href="print.php?id=<?= $n['id'] ?>" target="_blank" class="btn btn-sm btn-outline-danger px-2" title="Cetak Naskah (PDF)">
                                        <i class="bi bi-printer-fill"></i>
                                    </a>
                                    <a href="export_word.php?id=<?= $n['id'] ?>" class="btn btn-sm btn-outline-primary px-2" title="Unduh File Word (.doc)">
                                        <i class="bi bi-file-earmark-word-fill"></i>
                                    </a>
                                    <a href="print_kunci.php?id=<?= $n['id'] ?>" target="_blank" class="btn btn-sm btn-outline-success px-2" title="Cetak Kunci Jawaban &amp; Rubrik">
                                        <i class="bi bi-key-fill"></i>
                                    </a>
                                    <a href="duplicate.php?id=<?= $n['id'] ?>" class="btn btn-sm btn-outline-secondary px-2" title="Duplikasi Naskah Soal" onclick="return confirm('Duplikasi naskah soal ini untuk membuat paket baru?')">
                                        <i class="bi bi-copy"></i>
                                    </a>
                                    <?php if ($isOwner || $userRole === 'admin'): ?>
                                        <a href="delete.php?id=<?= $n['id'] ?>" class="btn btn-sm btn-outline-danger px-2" title="Hapus Naskah" onclick="return confirm('Apakah Anda yakin ingin menghapus naskah soal ini beserta butir-butir soal di dalamnya?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL BUAT NASKAH SOAL BARU -->
<div class="modal fade" id="modalCreateNaskah" tabindex="-1" aria-labelledby="modalCreateNaskahLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header text-white px-4 py-3" style="background: linear-gradient(135deg, #1e1b4b 0%, #3730a3 100%);">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="modalCreateNaskahLabel">
                    <i class="bi bi-magic fs-5"></i> Buat Naskah Soal Ujian Baru
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="create_action.php" method="POST">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Mata Pelajaran <span class="text-danger">*</span></label>
                            <select name="mapel_id" class="form-select" required onchange="syncMapelName(this)">
                                <option value="">-- Pilih Mata Pelajaran --</option>
                                <?php foreach ($allMapels as $m): ?>
                                    <option value="<?= $m['id'] ?>" data-name="<?= htmlspecialchars($m['nama_mapel']) ?>">
                                        <?= htmlspecialchars($m['nama_mapel']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="nama_mapel" id="namaMapelInput" value="">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Tingkat Kelas <span class="text-danger">*</span></label>
                            <select name="tingkat_kelas" class="form-select" required>
                                <option value="VII">Kelas VII (Tujuh)</option>
                                <option value="VIII">Kelas VIII (Delapan)</option>
                                <option value="IX">Kelas IX (Sembilan)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Tipe &amp; Judul Ujian <span class="text-danger">*</span></label>
                            <input type="text" name="judul_ujian" class="form-control" value="UJIAN AKHIR SEMESTER" required>
                            <input type="hidden" name="tipe_ujian" value="AAS">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted">Semester <span class="text-danger">*</span></label>
                            <select name="semester" class="form-select" required>
                                <option value="Ganjil" selected>Ganjil</option>
                                <option value="Genap">Genap</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted">Tahun Ajaran <span class="text-danger">*</span></label>
                            <input type="text" name="tahun_ajaran" class="form-control font-monospace" value="2025 – 2026" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Alokasi Waktu (Menit) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="alokasi_waktu" class="form-control" value="90" min="15" max="300" required>
                                <span class="input-group-text">Menit</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Tanggal Ujian (Opsional)</label>
                            <input type="date" name="tanggal_ujian" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                        <i class="bi bi-arrow-right-circle me-1"></i> Simpan &amp; Lanjut Susun Soal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL UPLOAD KOP SOAL PNG -->
<div class="modal fade" id="modalKopSoal" tabindex="-1" aria-labelledby="modalKopSoalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header text-white px-4 py-3" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="modalKopSoalLabel">
                    <i class="bi bi-image fs-5 text-warning"></i> Upload &amp; Pengaturan KOP Soal (PNG)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_kop_soal">
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        KOP naskah soal dicetak dalam bentuk gambar <strong>PNG</strong> agar presisi, rapi, dan sesuai standar sekolah. Anda dapat mengunggah file KOP PNG baru di sini.
                    </p>

                    <!-- Preview KOP Saat Ini -->
                    <div class="mb-4 p-3 bg-light rounded-3 border text-center">
                        <label class="form-label fw-bold small text-muted d-block text-start mb-2">Pratinjau KOP Soal Aktif:</label>
                        <?php 
                        $kopPath = __DIR__ . '/../../assets/img/kop_soal_ismi.png';
                        if (file_exists($kopPath)):
                        ?>
                            <img src="<?= BASE_URL ?>/assets/img/kop_soal_ismi.png?v=<?= filemtime($kopPath) ?>" alt="KOP Soal" class="img-fluid border rounded shadow-xs" style="max-height: 120px;">
                        <?php else: ?>
                            <div class="text-muted py-3">Belum ada file KOP PNG.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Pilih File KOP Baru (.PNG) <span class="text-danger">*</span></label>
                        <input type="file" name="kop_file" class="form-control" accept="image/png, image/jpeg, image/webp" required>
                        <small class="text-muted d-block mt-1">Rekomendasi format: PNG transparan atau putih, rasio lebar ~700 x 120 px.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                        <i class="bi bi-upload me-1"></i> Simpan Gambar KOP
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function syncMapelName(select) {
    var opt = select.options[select.selectedIndex];
    var name = opt.getAttribute('data-name') || '';
    document.getElementById('namaMapelInput').value = name;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
