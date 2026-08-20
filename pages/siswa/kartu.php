<?php
/**
 * KARTU SISWA — Digital & Print ID Card Modern & QR Code
 */
$pageTitle  = 'Cetak Kartu Siswa';
$activePage = 'siswa';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('siswa');

$pdo = getConnection();

$id      = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$kelasId = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
$theme   = $_GET['theme'] ?? 'biru'; // biru, hijau, emas

$namaSekolah = getSetting('nama_sekolah', SCHOOL_NAME);
$appName     = getSetting('app_name', APP_NAME);

// Query Siswa
if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT s.*, k.nama_kelas, k.tingkat
        FROM siswa s
        JOIN kelas k ON s.kelas_id = k.id
        WHERE s.id = :id
    ");
    $stmt->execute([':id' => $id]);
    $siswaList = $stmt->fetchAll();
} elseif ($kelasId > 0) {
    $stmt = $pdo->prepare("
        SELECT s.*, k.nama_kelas, k.tingkat
        FROM siswa s
        JOIN kelas k ON s.kelas_id = k.id
        WHERE s.kelas_id = :kelas AND s.status = 'aktif'
        ORDER BY s.nama ASC
    ");
    $stmt->execute([':kelas' => $kelasId]);
    $siswaList = $stmt->fetchAll();
} else {
    // Semua siswa aktif
    $stmt = $pdo->query("
        SELECT s.*, k.nama_kelas, k.tingkat
        FROM siswa s
        JOIN kelas k ON s.kelas_id = k.id
        WHERE s.status = 'aktif'
        ORDER BY k.nama_kelas ASC, s.nama ASC
        LIMIT 50
    ");
    $siswaList = $stmt->fetchAll();
}

$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY nama_kelas ASC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
/* STYLE KARTU SISWA MODERN MINIMALIS */
.no-print {
    margin-bottom: 1.5rem;
}

.cards-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
    justify-content: center;
    padding: 1rem 0;
}

.student-card {
    width: 295px;
    background: #ffffff;
    border-radius: 22px;
    overflow: hidden;
    box-shadow: 0 16px 36px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(0, 0, 0, 0.04);
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    page-break-inside: avoid;
    font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.student-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.12);
}

/* TOP HEADER MODERN GRADIENT */
.card-header-bg {
    width: 100%;
    height: 105px;
    position: relative;
    padding: 1rem 0.8rem 0;
    text-align: center;
    color: #ffffff;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* COLOR THEMES FOR HEADER */
.theme-biru .card-header-bg {
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 45%, #06b6d4 100%);
}
.theme-hijau .card-header-bg {
    background: linear-gradient(135deg, #0d9488 0%, #0f766e 45%, #047857 100%);
}
.theme-emas .card-header-bg {
    background: linear-gradient(135deg, #d97706 0%, #b45309 45%, #78350f 100%);
}

/* SUBTLE RADIAL GLOW */
.card-header-bg::before {
    content: '';
    position: absolute;
    width: 200px;
    height: 200px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
    top: -60px;
    right: -40px;
    pointer-events: none;
}

.school-header-title {
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #ffffff;
    position: relative;
    z-index: 2;
    text-shadow: 0 1px 3px rgba(0,0,0,0.2);
    max-width: 260px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 6px;
}

/* PHOTO BOX OVERLAPPING HEADER */
.card-photo-box {
    width: 86px;
    height: 100px;
    margin-top: -46px;
    position: relative;
    z-index: 3;
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    border: 3.5px solid #ffffff;
    border-radius: 18px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.card-photo-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.card-photo-box .placeholder-icon {
    color: #ffffff;
    font-size: 2.3rem;
}

/* STUDENT INFO SECTION */
.card-body-info {
    text-align: center;
    padding: 0.9rem 1.25rem 1.25rem;
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 2;
}

.student-name {
    font-size: 1.05rem;
    font-weight: 800;
    color: #0f172a;
    text-transform: uppercase;
    letter-spacing: -0.2px;
    margin-bottom: 0.15rem;
    line-height: 1.3;
    max-width: 260px;
    word-wrap: break-word;
}

.student-nis {
    font-size: 0.78rem;
    color: #64748b;
    font-weight: 600;
    margin-bottom: 0.75rem;
}

.student-nis strong {
    color: #1e293b;
    font-weight: 800;
    letter-spacing: 0.5px;
}

/* QR CODE CONTAINER */
.card-qr-box {
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    border-radius: 20px;
    padding: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.75rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    position: relative;
}

.card-qr-box img {
    width: 130px;
    height: 130px;
    display: block;
}

.card-footer-text {
    font-size: 0.72rem;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* PRINT MEDIA SETTINGS */
@media print {
    .no-print, nav, sidebar, .sidebar-wrapper, header, .header-container, footer {
        display: none !important;
    }
    body, .main-content {
        background: #fff !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .cards-grid {
        gap: 1.2cm;
        justify-content: flex-start;
        padding: 0;
    }
    .student-card {
        box-shadow: none !important;
        border: 1px solid #cbd5e1 !important;
        transform: none !important;
        page-break-inside: avoid;
    }
}
</style>

<div class="container-fluid px-0">
    <!-- ACTION BAR -->
    <div class="card border-0 shadow-sm rounded-4 p-3 mb-4 no-print bg-white">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h5 class="fw-extrabold text-dark mb-1"><i class="bi bi-card-heading text-primary me-2"></i> Cetak Kartu Siswa & QR Code</h5>
                <p class="text-muted small mb-0">Kartu identitas resmi siswa dengan QR Code terverifikasi</p>
            </div>
            
            <div class="d-flex flex-wrap align-items-center gap-2">
                <!-- Theme Switcher -->
                <div class="btn-group btn-group-sm me-2" role="group" aria-label="Tema Warna">
                    <a href="?kelas_id=<?= $kelasId ?>&id=<?= $id ?>&theme=biru" class="btn btn-outline-primary <?= $theme==='biru'?'active':'' ?>">🔵 Biru Ocean</a>
                    <a href="?kelas_id=<?= $kelasId ?>&id=<?= $id ?>&theme=hijau" class="btn btn-outline-success <?= $theme==='hijau'?'active':'' ?>">🟢 Emerald Teal</a>
                    <a href="?kelas_id=<?= $kelasId ?>&id=<?= $id ?>&theme=emas" class="btn btn-outline-warning <?= $theme==='emas'?'active':'' ?>">🟡 Amber Gold</a>
                </div>

                <!-- Filter Kelas -->
                <form method="GET" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="theme" value="<?= htmlspecialchars($theme) ?>">
                    <select name="kelas_id" class="form-select form-select-sm rounded-3" onchange="this.form.submit()">
                        <option value="0">-- Semua Kelas --</option>
                        <?php foreach ($kelasList as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= $kelasId == $k['id'] ? 'selected' : '' ?>>
                                Kelas <?= htmlspecialchars($k['nama_kelas']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                
                <button onclick="window.print()" class="btn btn-primary-custom px-3 py-2 rounded-3 fw-bold small d-inline-flex align-items-center gap-2">
                    <i class="bi bi-printer-fill"></i> Cetak Kartu (Print)
                </button>
                <a href="index.php" class="btn btn-light px-3 py-2 rounded-3 fw-bold small border">
                    <i class="bi bi-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    <!-- CARDS GRID CONTAINER -->
    <div class="cards-grid">
        <?php if (empty($siswaList)): ?>
            <div class="text-center py-5 text-muted w-100">
                <i class="bi bi-card-checklist fs-1 d-block mb-2 text-secondary"></i>
                Tidak ada data siswa ditemukan untuk dicetak kartunya.
            </div>
        <?php else: ?>
            <?php foreach ($siswaList as $s): ?>
                <?php
                $nisStr = htmlspecialchars($s['nis']);
                $namaStr = htmlspecialchars($s['nama']);
                $kelasStr = htmlspecialchars($s['nama_kelas']);
                $tingkatStr = htmlspecialchars($s['tingkat'] ?? 'SISWA');
                
                // Content encoded by QR: NIS | Nama | Sekolah
                $qrData = "NIS:{$s['nis']}|NAMA:{$s['nama']}|SEKOLAH:{$namaSekolah}";
                $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($qrData);
                
                $hasFoto = !empty($s['foto']) && file_exists(__DIR__ . '/../../uploads/siswa/' . $s['foto']);
                $fotoUrl = $hasFoto ? BASE_URL . '/uploads/siswa/' . $s['foto'] : '';
                ?>
                <div class="student-card theme-<?= htmlspecialchars($theme) ?>">
                    <!-- TOP HEADER -->
                    <div class="card-header-bg">
                        <div class="school-header-title"><?= htmlspecialchars($namaSekolah) ?></div>
                    </div>

                    <!-- PHOTO BOX -->
                    <div class="card-photo-box">
                        <?php if ($hasFoto): ?>
                            <img src="<?= $fotoUrl ?>" alt="<?= $namaStr ?>">
                        <?php else: ?>
                            <i class="bi bi-person-fill placeholder-icon"></i>
                        <?php endif; ?>
                    </div>

                    <!-- STUDENT INFO -->
                    <div class="card-body-info">
                        <h4 class="student-name"><?= $namaStr ?></h4>
                        <div class="student-nis">NIS: <strong><?= $nisStr ?></strong></div>

                        <!-- QR CODE -->
                        <div class="card-qr-box">
                            <img src="<?= $qrUrl ?>" alt="QR Code <?= $nisStr ?>" loading="lazy">
                        </div>

                        <!-- FOOTER LEVEL / KELAS -->
                        <div class="card-footer-text">
                            <?= strtoupper($tingkatStr) ?> — KELAS <?= strtoupper($kelasStr) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
