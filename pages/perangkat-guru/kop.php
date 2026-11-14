<?php
/**
 * PENGATURAN & UPLOAD KOP SOAL UJIAN (PNG)
 * SMP ISMI - Islamic School of Minhaj Al-Ilmi
 */
$pageTitle = 'Upload KOP Soal (PNG) - Perangkat Guru';
$activePage = 'perangkat-guru';
$activeModule = 'akademik';

require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_guru');

$pdo = getConnection();
require_once __DIR__ . '/init_soal.php';

$kopDestFile = __DIR__ . '/../../assets/img/kop_soal_ismi.png';

// Handle Upload / Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        if (isset($_FILES['kop_image']) && $_FILES['kop_image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['kop_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
                if (move_uploaded_file($_FILES['kop_image']['tmp_name'], $kopDestFile)) {
                    $_SESSION['flash'] = [
                        'type' => 'success',
                        'message' => 'Gambar KOP Soal (PNG) berhasil diperbarui dan aktif untuk semua naskah ujian!'
                    ];
                } else {
                    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Gagal menyimpan file gambar ke server.'];
                }
            } else {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Format file tidak didukung. Harap gunakan file PNG atau JPG.'];
            }
        } else {
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Silakan pilih file gambar KOP terlebih dahulu.'];
        }
    }

    header('Location: kop.php');
    exit;
}

$hasKop = file_exists($kopDestFile);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/subnav.php';
?>

<!-- FLASH NOTIFIKASI -->
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

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-4">
            <div class="card-header text-white px-4 py-3" style="background: linear-gradient(135deg, #1e1b4b 0%, #3730a3 100%);">
                <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-image fs-5 text-warning"></i> Upload &amp; Pengaturan KOP Soal (PNG)
                </h5>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-4">
                    KOP pada lembar naskah soal AAS dicetak langsung menggunakan file gambar <strong>PNG</strong> agar tata letak logo, nama sekolah, yayasan, dan kontak sekolah selalu presisi dan tidak bergeser saat dicetak maupun diunduh ke Microsoft Word.
                </p>

                <!-- PREVIEW KOP AKTIF -->
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-eye-fill text-primary"></i> Pratinjau KOP Soal Aktif Saat Ini:
                    </label>
                    <div class="p-3 bg-light rounded-3 border text-center">
                        <?php if ($hasKop): ?>
                            <img src="<?= BASE_URL ?>/assets/img/kop_soal_ismi.png?v=<?= filemtime($kopDestFile) ?>" alt="KOP Soal Aktif" class="img-fluid border rounded shadow-xs" style="max-height: 140px; background: white;">
                        <?php else: ?>
                            <div class="text-muted py-4">Belum ada file KOP PNG yang diunggah.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- FORM UPLOAD KOP BARU -->
                <form action="kop.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark">Pilih File Gambar KOP Baru (.PNG) <span class="text-danger">*</span></label>
                        <input type="file" name="kop_image" class="form-control" accept="image/png, image/jpeg, image/webp" required onchange="previewNewKop(this)">
                        <div class="form-text text-muted">
                            <i class="bi bi-info-circle me-1"></i> Rekomendasi: Gunakan file PNG dengan latar belakang putih atau transparan, dengan rasio lebar penuh (~700 x 120 px atau 2100 x 360 px).
                        </div>
                    </div>

                    <!-- Live Preview Upload -->
                    <div id="newKopPreviewBox" class="mb-4 d-none">
                        <label class="form-label fw-bold small text-muted">Pratinjau File yang Baru Dipilih:</label>
                        <div class="p-3 bg-light rounded-3 border text-center">
                            <img id="newKopImg" src="#" alt="Pratinjau Baru" class="img-fluid border rounded" style="max-height: 140px; background: white;">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">
                            <i class="bi bi-arrow-left me-1"></i> Kembali ke Bank Soal
                        </a>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                            <i class="bi bi-upload me-1.5"></i> Simpan Gambar KOP
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function previewNewKop(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('newKopImg').src = e.target.result;
            document.getElementById('newKopPreviewBox').classList.remove('d-none');
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
