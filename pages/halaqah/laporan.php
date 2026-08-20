<?php
/**
 * MODUL HALAQAH & TAHFIDZ - LAPORAN REKAP & PROGRESS SISWA
 */
$pageTitle  = 'Laporan Progress Halaqah & Tahfidz';
$activePage = 'halaqah';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('halaqah');

$pdo = getConnection();

// Filter State
$filterKelompok = (int)($_GET['kelompok_id'] ?? 0);
$filterKategori = (int)($_GET['kategori_id'] ?? 0);
$filterTipe     = $_GET['tipe'] ?? '';
$filterBulan    = $_GET['bulan'] ?? date('Y-m');

// Fetch Master Data for Filters
$kategoriList = $pdo->query("SELECT * FROM halaqah_kategori ORDER BY nama_kategori ASC")->fetchAll();
$kelompokList = $pdo->query("SELECT * FROM halaqah_kelompok ORDER BY nama_halaqah ASC")->fetchAll();

// Build Query Laporan
$queryStr = "
    SELECT hs.*, s.nama AS nama_siswa, s.nis, k.nama_kelas, hk.nama_halaqah, hkat.nama_kategori, u.nama_lengkap AS nama_musyrif
    FROM halaqah_setoran hs
    JOIN siswa s ON hs.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN halaqah_kelompok hk ON hs.kelompok_id = hk.id
    LEFT JOIN halaqah_kategori hkat ON hs.kategori_id = hkat.id
    LEFT JOIN users u ON hs.musyrif_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($filterBulan)) {
    $queryStr .= " AND DATE_FORMAT(hs.tanggal, '%Y-%m') = :bln";
    $params[':bln'] = $filterBulan;
}
if ($filterKelompok > 0) {
    $queryStr .= " AND hs.kelompok_id = :kid";
    $params[':kid'] = $filterKelompok;
}
if ($filterKategori > 0) {
    $queryStr .= " AND hs.kategori_id = :katid";
    $params[':katid'] = $filterKategori;
}
if (!empty($filterTipe)) {
    $queryStr .= " AND hs.tipe_setoran = :tipe";
    $params[':tipe'] = $filterTipe;
}

$queryStr .= " ORDER BY hs.tanggal DESC, hs.id DESC";

$stmtL = $pdo->prepare($queryStr);
$stmtL->execute($params);
$laporanRows = $stmtL->fetchAll();

// Agregasi Ringkasan Progress
$totalZiyadah  = 0;
$totalMurojaah = 0;
$totalTahsin   = 0;
$totalUjian    = 0;
$totalMumtaz   = 0;

foreach ($laporanRows as $r) {
    if ($r['tipe_setoran'] === 'ziyadah') $totalZiyadah++;
    if ($r['tipe_setoran'] === 'murojaah') $totalMurojaah++;
    if ($r['tipe_setoran'] === 'tahsin') $totalTahsin++;
    if ($r['tipe_setoran'] === 'ujian') $totalUjian++;
    if ($r['penilaian'] === 'mumtaz') $totalMumtaz++;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ACTION TABS HALAQAH -->
<div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white d-print-none">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle p-3 bg-emerald-subtle text-emerald d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: #ecfdf5; color: #059669;">
                <i class="bi bi-file-earmark-bar-graph fs-3"></i>
            </div>
            <div>
                <h5 class="fw-extrabold text-dark mb-0">Laporan Progress Halaqah & Tahfidz</h5>
                <p class="text-muted small mb-0">Rekapitulasi setoran hafalan, pencapaian mutu, & ekspor laporan</p>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="index.php" class="btn btn-outline-success px-3 py-2 rounded-3 fw-bold small">
                <i class="bi bi-journal-plus me-1"></i> Pencatatan Setoran
            </a>
            <a href="manage.php" class="btn btn-outline-primary px-3 py-2 rounded-3 fw-bold small">
                <i class="bi bi-gear-fill me-1"></i> Pengaturan Halaqah
            </a>
            <a href="laporan.php" class="btn btn-emerald px-3 py-2 rounded-3 fw-bold small text-white" style="background: #059669;">
                <i class="bi bi-file-earmark-bar-graph me-1"></i> Laporan Progress
            </a>
        </div>
    </div>
</div>

<!-- FILTER CARD -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4 d-print-none">
    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-funnel-fill text-teal me-2"></i>Filter Laporan Progress</h6>

    <form method="GET" action="laporan.php" class="row g-3">
        <div class="col-md-3">
            <label class="form-label extra-small fw-bold text-muted">Bulan Setoran</label>
            <input type="month" name="bulan" class="form-control bg-light border-0 fw-bold extra-small" value="<?= htmlspecialchars($filterBulan) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label extra-small fw-bold text-muted">Kelompok Halaqah</label>
            <select name="kelompok_id" class="form-select bg-light border-0 fw-bold extra-small">
                <option value="0">-- Semua Kelompok --</option>
                <?php foreach ($kelompokList as $hk): ?>
                    <option value="<?= $hk['id'] ?>" <?= $filterKelompok == $hk['id'] ? 'selected' : '' ?>><?= htmlspecialchars($hk['nama_halaqah']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label extra-small fw-bold text-muted">Kategori</label>
            <select name="kategori_id" class="form-select bg-light border-0 fw-bold extra-small">
                <option value="0">-- Semua Kategori --</option>
                <?php foreach ($kategoriList as $kat): ?>
                    <option value="<?= $kat['id'] ?>" <?= $filterKategori == $kat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label extra-small fw-bold text-muted">Tipe Setoran</label>
            <select name="tipe" class="form-select bg-light border-0 fw-bold extra-small">
                <option value="">-- Semua Tipe --</option>
                <option value="ziyadah" <?= $filterTipe === 'ziyadah' ? 'selected' : '' ?>>Nambah (Ziyadah)</option>
                <option value="murojaah" <?= $filterTipe === 'murojaah' ? 'selected' : '' ?>>Muroja'ah</option>
                <option value="tahsin" <?= $filterTipe === 'tahsin' ? 'selected' : '' ?>>Tahsin</option>
                <option value="ujian" <?= $filterTipe === 'ujian' ? 'selected' : '' ?>>Ujian Tahfidz</option>
            </select>
        </div>
        <div class="col-12 d-flex justify-content-end gap-2">
            <button type="submit" class="btn btn-teal px-4 fw-bold extra-small"><i class="bi bi-search me-1"></i> Tampilkan Laporan</button>
            <button type="button" class="btn btn-outline-dark px-3 fw-bold extra-small" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak Laporan</button>
        </div>
    </form>
</div>

<!-- AGREGASI RINGKASAN CARDS -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center">
            <span class="extra-small text-muted fw-bold d-block">TOTAL ZIYADAH</span>
            <h3 class="fw-extrabold text-success mb-0"><?= $totalZiyadah ?> Setoran</h3>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center">
            <span class="extra-small text-muted fw-bold d-block">TOTAL MUROJA'AH</span>
            <h3 class="fw-extrabold text-info mb-0"><?= $totalMurojaah ?> Setoran</h3>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center">
            <span class="extra-small text-muted fw-bold d-block">UJIAN TAHFIDZ</span>
            <h3 class="fw-extrabold text-warning mb-0"><?= $totalUjian ?> Setoran</h3>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center">
            <span class="extra-small text-muted fw-bold d-block">NILAI MUMTAZ</span>
            <h3 class="fw-extrabold text-primary mb-0"><?= $totalMumtaz ?> Setoran</h3>
        </div>
    </div>
</div>

<!-- TABEL REKAP LAPORAN -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-table me-2" style="color: #059669;"></i>Data Rekapitulasi Setoran Siswa (<?= count($laporanRows) ?> Data)</h6>

    <?php if (empty($laporanRows)): ?>
        <div class="text-center py-5 text-muted small">
            <i class="bi bi-inbox fs-1 d-block mb-1 text-secondary opacity-50"></i>
            Tidak ada data setoran halaqah sesuai filter yang dipilih.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle extra-small mb-0">
                <thead class="table-light">
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Siswa & Kelas</th>
                        <th>Kelompok & Musyrif</th>
                        <th>Tipe & Metode</th>
                        <th>Materi Setoran</th>
                        <th>Penilaian</th>
                        <th>Status</th>
                        <th>Catatan Ortu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($laporanRows as $lr): ?>
                        <?php 
                            $nilaiBadge = [
                                'mumtaz'        => 'bg-success-subtle text-success border-success',
                                'jayyid_jiddan' => 'bg-info-subtle text-info border-info',
                                'jayyid'        => 'bg-warning-subtle text-warning border-warning',
                                'rasib'         => 'bg-danger-subtle text-danger border-danger'
                            ][$lr['penilaian']] ?? 'bg-secondary-subtle';
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td class="text-nowrap"><?= date('d/m/Y', strtotime($lr['tanggal'])) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($lr['nama_siswa']) ?></strong><br>
                                <span class="text-muted extra-small">NIS: <?= htmlspecialchars($lr['nis']) ?> • Kelas <?= htmlspecialchars($lr['nama_kelas']) ?></span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($lr['nama_halaqah'] ?: 'Umum') ?></strong><br>
                                <span class="text-muted extra-small">Musyrif: <?= htmlspecialchars($lr['nama_musyrif'] ?: '-') ?></span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= ucfirst($lr['tipe_setoran']) ?></span><br>
                                <small class="text-muted">(<?= ucfirst($lr['metode_input']) ?>)</small>
                            </td>
                            <td><strong><?= htmlspecialchars($lr['materi_setoran']) ?></strong></td>
                            <td>
                                <span class="badge <?= $nilaiBadge ?> border">
                                    <?= strtoupper(str_replace('_', ' ', $lr['penilaian'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $lr['status_setoran'] === 'lulus' ? 'bg-success' : 'bg-danger' ?>">
                                    <?= ucfirst($lr['status_setoran']) ?>
                                </span>
                            </td>
                            <td class="text-muted extra-small"><?= htmlspecialchars($lr['catatan_ortu'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
