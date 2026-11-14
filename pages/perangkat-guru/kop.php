<?php
/**
 * PENGATURAN & UPLOAD KOP SOAL UJIAN
 * Fitur Anti-Pecah (Mode Teks Vektor / Gambar PNG HD)
 * SMP ISMI - Islamic School of Minhaj Al-Ilmi
 */
$pageTitle = 'Pengaturan KOP Soal (Anti-Pecah) - Perangkat Guru';
$activePage = 'perangkat-guru';
$activeModule = 'akademik';

require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_guru');

$pdo = getConnection();
require_once __DIR__ . '/init_soal.php';

$kopDestFile = __DIR__ . '/../../assets/img/kop_soal_ismi.png';

// Ambil Pengaturan KOP dari rapor_settings
$settingsRaw = $pdo->query("SELECT setting_key, setting_value FROM rapor_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$currentMode = $settingsRaw['kop_soal_mode'] ?? 'vector'; // default vector agar anti-pecah!

// Handle Update Mode / Upload Gambar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'set_mode') {
        $mode = $_POST['kop_mode'] ?? 'vector';
        if (!in_array($mode, ['vector', 'image'])) {
            $mode = 'vector';
        }
        $stmtUpsert = $pdo->prepare("
            INSERT INTO rapor_settings (setting_key, setting_value) 
            VALUES ('kop_soal_mode', :val)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmtUpsert->execute([':val' => $mode]);

        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => ($mode === 'vector') 
                ? 'Berhasil! Mode KOP Teks Vektor (Anti-Pecah & 100% Tajam) sekarang aktif untuk semua naskah ujian dan file Word!'
                : 'Mode Gambar KOP PNG sekarang aktif.'
        ];
    } elseif ($action === 'sync_from_rapor') {
        $raporKopName = $settingsRaw['kop_image'] ?? '';
        $raporKopPath = __DIR__ . '/../../uploads/rapor/' . $raporKopName;
        if (!empty($raporKopName) && file_exists($raporKopPath)) {
            copy($raporKopPath, $kopDestFile);
            $stmtUpsert = $pdo->prepare("
                INSERT INTO rapor_settings (setting_key, setting_value) 
                VALUES ('kop_soal_mode', 'image')
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmtUpsert->execute();
            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Berhasil menyinkronkan KOP resmi dari Pengaturan Rapor (' . htmlspecialchars($raporKopName) . ') ke Naskah Soal!'
            ];
        } else {
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'File KOP resmi di modul Rapor tidak ditemukan di server.'];
        }
    } elseif ($action === 'upload') {
        if (isset($_FILES['kop_image']) && $_FILES['kop_image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['kop_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
                if (move_uploaded_file($_FILES['kop_image']['tmp_name'], $kopDestFile)) {
                    // Set mode otomatis ke image jika baru upload
                    $stmtUpsert = $pdo->prepare("
                        INSERT INTO rapor_settings (setting_key, setting_value) 
                        VALUES ('kop_soal_mode', 'image')
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ");
                    $stmtUpsert->execute();

                    $_SESSION['flash'] = [
                        'type' => 'success',
                        'message' => 'Gambar KOP baru berhasil diunggah dan diaktifkan!'
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
$cubeLogo = __DIR__ . '/../../assets/img/logo_ismi_cube.png';

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
    <div class="col-lg-9">
        
        <!-- CARD PENGATURAN KOP ANTI-PECAH -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-4">
            <div class="card-header text-white px-4 py-3" style="background: linear-gradient(135deg, #1e1b4b 0%, #3730a3 100%);">
                <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-patch-check-fill fs-5 text-warning"></i> Pengaturan Format KOP Soal (Anti-Pecah)
                </h5>
            </div>
            <div class="card-body p-4">
                
                <!-- PENJELASAN MENGAPA GAMBAR PECAH & SOLUSINYA -->
                <div class="alert alert-info border-0 rounded-4 p-3 mb-4 shadow-xs" style="background: #f0fdf4; border-left: 4px solid #16a34a !important;">
                    <div class="d-flex gap-3">
                        <i class="bi bi-shield-check fs-2 text-success"></i>
                        <div>
                            <h6 class="fw-bold text-success mb-1">Mengapa KOP Gambar Bisa Terlihat Pecah di Lembar Soal?</h6>
                            <p class="small text-muted mb-2">
                                Lembar soal dicetak dalam ukuran kertas <strong>A4</strong>. Pada saat dicetak atau diubah ke PDF/Word, kertas A4 membutuhkan kerapatan minimal <strong>2480 pixel (300 DPI)</strong>. Gambar hasil screenshot layar yang hanya berukuran ~690 pixel akan otomatis membesar 3-4 kali lipat sehingga huruf-huruf kecilnya terlihat buram/pecah.
                            </p>
                            <p class="small text-success fw-bold mb-0">
                                <i class="bi bi-check-circle-fill me-1"></i> Solusi Terbaik: Gunakan <strong>Mode Teks Vektor</strong> di bawah ini. Teks sekolah &amp; kontak dicetak menggunakan font asli resolusi tinggi sehingga <strong>100% tajam, tidak akan pecah sama sekali</strong> pada zoom berapapun maupun di semua printer!
                            </p>
                        </div>
                    </div>
                </div>

                <!-- FORM PEMILIHAN MODE KOP -->
                <form action="kop.php" method="POST" class="mb-4">
                    <input type="hidden" name="action" value="set_mode">
                    <label class="form-label fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-sliders text-primary"></i> Pilih Mode Tampilan KOP yang Digunakan:
                    </label>

                    <div class="row g-3">
                        <!-- OPSI 1: VEKTOR ANTI-PECAH (RECOMMENDED) -->
                        <div class="col-md-6">
                            <label class="card h-100 p-3 rounded-4 border-2 cursor-pointer transition-all <?= ($currentMode === 'vector') ? 'border-primary bg-primary bg-opacity-10 shadow-sm' : 'border-light bg-light' ?>" style="cursor: pointer;">
                                <div class="d-flex align-items-start gap-2">
                                    <input type="radio" name="kop_mode" value="vector" class="form-check-input mt-1" <?= ($currentMode === 'vector') ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <div>
                                        <div class="fw-bold text-primary d-flex align-items-center gap-1">
                                            <span>KOP Teks Vektor</span>
                                            <span class="badge bg-success small">100% Anti-Pecah</span>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            Teks nama sekolah, yayasan, dan kontak dirender dengan font vektor tajam asli. <strong>Sangat jernih di printer fisik, PDF, dan Word.</strong>
                                        </small>
                                    </div>
                                </div>
                            </label>
                        </div>

                        <!-- OPSI 2: GAMBAR PNG -->
                        <div class="col-md-6">
                            <label class="card h-100 p-3 rounded-4 border-2 cursor-pointer transition-all <?= ($currentMode === 'image') ? 'border-primary bg-primary bg-opacity-10 shadow-sm' : 'border-light bg-light' ?>" style="cursor: pointer;">
                                <div class="d-flex align-items-start gap-2">
                                    <input type="radio" name="kop_mode" value="image" class="form-check-input mt-1" <?= ($currentMode === 'image') ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <div>
                                        <div class="fw-bold text-dark">
                                            <span>Gambar KOP PNG</span>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            Menggunakan file gambar PNG yang diunggah. Pastikan file gambar beresolusi tinggi (~2400px, 300 DPI) agar tidak pecah.
                                        </small>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                </form>

                <hr class="my-4">

                <!-- PRATINJAU KOP AKTIF SAAT INI -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-eye-fill text-primary"></i> Pratinjau KOP yang Sedang Aktif:
                        </label>
                        <span class="badge <?= ($currentMode === 'vector') ? 'bg-success' : 'bg-warning text-dark' ?> px-3 py-1.5 rounded-pill font-monospace">
                            Mode Aktif: <?= ($currentMode === 'vector') ? 'Teks Vektor (Anti-Pecah)' : 'Gambar PNG' ?>
                        </span>
                    </div>

                    <div class="p-3 bg-white rounded-3 border shadow-xs" style="background: #ffffff;">
                        <?php if ($currentMode === 'vector'): ?>
                            <!-- PRATINJAU VEKTOR -->
                            <table style="width: 100%; border-collapse: collapse; margin-bottom: 2px;">
                                <tr>
                                    <td style="width: 78px; vertical-align: middle; text-align: left;">
                                        <?php if (file_exists($cubeLogo)): ?>
                                            <img src="<?= BASE_URL ?>/assets/img/logo_ismi_cube.png?v=<?= filemtime($cubeLogo) ?>" alt="Logo SMP ISMI" style="width: 70px; height: 70px; object-fit: contain; display: block;">
                                        <?php else: ?>
                                            <img src="<?= BASE_URL ?>/assets/img/logo_1776435927.png" alt="Logo SMP ISMI" style="width: 70px; height: 70px; object-fit: contain; display: block;">
                                        <?php endif; ?>
                                    </td>
                                    <td style="vertical-align: middle; padding-left: 10px;">
                                        <div style="font-family: Arial, Helvetica, sans-serif; font-size: 17pt; font-weight: 800; line-height: 1.1; color: #000; letter-spacing: -0.3px;">SMP ISMI</div>
                                        <div style="font-family: Arial, Helvetica, sans-serif; font-size: 11pt; font-weight: 800; line-height: 1.15; color: #000;">ISLAMIC SCHOOL OF</div>
                                        <div style="font-family: Arial, Helvetica, sans-serif; font-size: 11pt; font-weight: 800; line-height: 1.15; color: #0d9488;">MINHAJ AL-ILMI</div>
                                        <div style="font-family: 'Segoe Print', 'Caveat', cursive, sans-serif; font-size: 8.5pt; font-style: italic; color: #222; margin-top: 2px;">Berakhlak Mulia, Berilmu, Mahir IT</div>
                                    </td>
                                    <td style="vertical-align: middle; text-align: right; font-family: Arial, Helvetica, sans-serif; font-size: 8.5pt; line-height: 1.35; color: #111;">
                                        <div style="font-weight: 700;">Jl. Nakula No.1 Harjamukti Cimanggis Depok</div>
                                        <div style="font-weight: 700;">Telp. (021) 4867 8793</div>
                                        <div>Email : <span style="color: #0284c7;">smp.minhaj.al.ilmi@gmail.com</span></div>
                                        <div>Website : <span style="color: #0284c7;">sekolahismi.sch.id &amp; ismi.sch.id</span></div>
                                        <div style="font-weight: 700;">Admin WA 0899-2244-111</div>
                                    </td>
                                </tr>
                            </table>
                            <div style="border-bottom: 2.5px solid #000000; margin-top: 6px;"></div>
                        <?php else: ?>
                            <!-- PRATINJAU GAMBAR PNG -->
                            <div class="text-center">
                                <?php if ($hasKop): ?>
                                    <img src="<?= BASE_URL ?>/assets/img/kop_soal_ismi.png?v=<?= filemtime($kopDestFile) ?>" alt="KOP Soal Aktif" class="img-fluid border rounded" style="max-height: 130px; background: white;">
                                <?php else: ?>
                                    <div class="text-muted py-4">Belum ada file KOP PNG yang diunggah.</div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- INFORMASI & DOWNLOAD KOP RESMI DARI PENGATURAN RAPOR -->
                <?php 
                $raporKopName = $settingsRaw['kop_image'] ?? '';
                if (!empty($raporKopName)):
                ?>
                    <div class="card border border-primary border-opacity-25 rounded-4 p-3 bg-white mb-4 shadow-xs">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div>
                                <div class="fw-bold text-dark d-flex align-items-center gap-2">
                                    <i class="bi bi-file-earmark-image-fill text-primary fs-5"></i>
                                    <span>File KOP Resmi Sekolah (Pengaturan Rapor):</span>
                                    <span class="badge bg-primary-subtle text-primary border"><?= htmlspecialchars($raporKopName) ?></span>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    File KOP resmi ini beresolusi HD (1600 &times; 252 px). Anda dapat langsung mengunduhnya ke laptop/HP Anda.
                                </small>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?= BASE_URL ?>/uploads/rapor/<?= htmlspecialchars($raporKopName) ?>" download="<?= htmlspecialchars($raporKopName) ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold">
                                    <i class="bi bi-download me-1"></i> Download KOP Ini (.PNG)
                                </a>
                                <form action="kop.php" method="POST" class="m-0">
                                    <input type="hidden" name="action" value="sync_from_rapor">
                                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold shadow-xs">
                                        <i class="bi bi-check2-circle me-1"></i> Pasang ke Naskah Soal
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- FORM UPLOAD GAMBAR BARU -->
                <div class="card border rounded-4 p-3 bg-light mt-4">
                    <h6 class="fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-cloud-arrow-up-fill text-primary"></i> Upload Gambar KOP Baru (Opsional)
                    </h6>
                    <p class="small text-muted mb-3">
                        Jika Anda ingin memakai gambar kreasi sendiri, gunakan format <strong>PNG dengan resolusi tinggi (lebar minimal 2400 px / 300 DPI)</strong> agar teks alamat dan nomor telepon tidak pecah saat dicetak di lembar soal A4.
                    </p>

                    <form action="kop.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload">
                        <div class="mb-3">
                            <input type="file" name="kop_image" class="form-control" accept="image/png, image/jpeg, image/webp" required onchange="previewNewKop(this)">
                        </div>

                        <!-- Live Preview Upload -->
                        <div id="newKopPreviewBox" class="mb-3 d-none">
                            <label class="form-label fw-bold small text-muted">Pratinjau File yang Baru Dipilih:</label>
                            <div class="p-3 bg-white rounded-3 border text-center">
                                <img id="newKopImg" src="#" alt="Pratinjau Baru" class="img-fluid border rounded" style="max-height: 130px; background: white;">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">
                                <i class="bi bi-arrow-left me-1"></i> Kembali ke Bank Soal
                            </a>
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                                <i class="bi bi-upload me-1.5"></i> Unggah &amp; Pasang Gambar
                            </button>
                        </div>
                    </form>
                </div>

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
