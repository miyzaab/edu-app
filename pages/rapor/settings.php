<?php
/**
 * PENGATURAN RAPOR & TANDA TANGAN (Settings Rapor)
 * Konfigurasi KOP Surat (Upload Gambar / Teks Otomatis), Mudir / Kepala Sekolah, NIP, Stempel, Tanggal Terbit
 */
$pageTitle = 'Pengaturan Rapor & KOP Surat';
$activePage = 'rapor';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('rapor');

$pdo = getConnection();
require_once __DIR__ . '/init_rapor.php';

$uploadDir = __DIR__ . '/../../uploads/rapor';
if (!file_exists($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

// Load Existing Settings
$settingsRaw = $pdo->query("SELECT setting_key, setting_value FROM rapor_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

// Load Daftar Kelas beserta Wali Kelas
$kelasList = $pdo->query("
    SELECT k.*, u.nama_lengkap AS nama_wali_kelas, gd.nip AS nip_wali_kelas
    FROM kelas k
    LEFT JOIN users u ON k.wali_kelas_id = u.id
    LEFT JOIN guru_detail gd ON (u.id = gd.user_id OR k.wali_kelas_id = gd.user_id)
    ORDER BY k.tingkat ASC, k.nama_kelas ASC
")->fetchAll();

// PROSES SIMPAN SETTINGS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $ptsGanjil = trim($_POST['tanggal_pts_ganjil'] ?? date('Y') . '-10-11');
    $pasGanjil = trim($_POST['tanggal_pas_ganjil'] ?? date('Y') . '-12-20');
    $ptsGenap = trim($_POST['tanggal_pts_genap'] ?? (date('Y') + 1) . '-03-20');
    $pasGenap = trim($_POST['tanggal_pas_genap'] ?? (date('Y') + 1) . '-06-25');

    $keys = [
        'use_uploaded_kop' => isset($_POST['use_uploaded_kop']) ? '1' : '0',
        'kop_nama_sekolah' => trim($_POST['kop_nama_sekolah'] ?? 'SMP ISMI'),
        'kop_yayasan' => trim($_POST['kop_yayasan'] ?? 'ISLAMIC SCHOOL OF MINHAJ AL-ILMI'),
        'kop_slogan' => trim($_POST['kop_slogan'] ?? 'Berakhlak Mulia, Berilmu, Mahir IT'),
        'kop_alamat' => trim($_POST['kop_alamat'] ?? 'Jl. Nakula No.1 Harjamukti Cimanggis Depok'),
        'kop_telp' => trim($_POST['kop_telp'] ?? 'Telp. (021) 4867 8793'),
        'kop_email' => trim($_POST['kop_email'] ?? 'smp.minhaj.al.ilmi@gmail.com'),
        'kop_website' => trim($_POST['kop_website'] ?? 'sekolahismi.sch.id & ismi.sch.id'),
        'kop_wa' => trim($_POST['kop_wa'] ?? 'Admin WA 0899-2244-111'),
        'kepsek_jabatan' => trim($_POST['kepsek_jabatan'] ?? 'Mudir SMP'),
        'kepsek_lembaga' => trim($_POST['kepsek_lembaga'] ?? 'Islamic School of Minhaj Al Ilmi'),
        'kepsek_nama' => trim($_POST['kepsek_nama'] ?? 'Riki Kaptanto, Lc'),
        'kepsek_nip' => trim($_POST['kepsek_nip'] ?? '20240711001'),
        'tempat_terbit' => trim($_POST['tempat_terbit'] ?? 'Depok'),
        'tanggal_pts_ganjil' => $ptsGanjil,
        'tanggal_ats_ganjil' => $ptsGanjil,
        'tanggal_pas_ganjil' => $pasGanjil,
        'tanggal_aas_ganjil' => $pasGanjil,
        'tanggal_pts_genap' => $ptsGenap,
        'tanggal_ats_genap' => $ptsGenap,
        'tanggal_pas_genap' => $pasGenap,
        'tanggal_aas_genap' => $pasGenap,
        'fase_default' => trim($_POST['fase_default'] ?? 'D'),
        'kokurikuler_default' => trim($_POST['kokurikuler_default'] ?? ''),
        'watermark_enabled' => isset($_POST['watermark_enabled']) ? '1' : '0',
        'watermark_opacity' => max(1, min(100, (int) ($_POST['watermark_opacity'] ?? 12))),
        'watermark_size' => max(100, min(1000, (int) ($_POST['watermark_size'] ?? 450))),
    ];

    // Handle Upload KOP Image
    if (isset($_FILES['kop_image']) && $_FILES['kop_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['kop_image']['tmp_name'];
        $fileExt = strtolower(pathinfo($_FILES['kop_image']['name'], PATHINFO_EXTENSION));
        if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp'])) {
            $newFileName = 'kop_' . time() . '.' . $fileExt;
            $dest = $uploadDir . '/' . $newFileName;
            if (move_uploaded_file($fileTmp, $dest)) {
                $keys['kop_image'] = $newFileName;
                $keys['use_uploaded_kop'] = '1';
            }
        }
    } elseif (isset($_POST['remove_kop_image']) && $_POST['remove_kop_image'] === '1') {
        $keys['kop_image'] = '';
        $keys['use_uploaded_kop'] = '0';
    }

    // Handle Upload Logo Sekolah
    if (isset($_FILES['kop_logo']) && $_FILES['kop_logo']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['kop_logo']['tmp_name'];
        $fileExt = strtolower(pathinfo($_FILES['kop_logo']['name'], PATHINFO_EXTENSION));
        if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp', 'svg'])) {
            $newLogoName = 'logo_' . time() . '.' . $fileExt;
            $dest = $uploadDir . '/' . $newLogoName;
            if (move_uploaded_file($fileTmp, $dest)) {
                $keys['kop_logo'] = $newLogoName;
            }
        }
    } elseif (isset($_POST['remove_kop_logo']) && $_POST['remove_kop_logo'] === '1') {
        $keys['kop_logo'] = '';
    }

    // Handle Upload Watermark Image
    if (isset($_FILES['watermark_image']) && $_FILES['watermark_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['watermark_image']['tmp_name'];
        $fileExt = strtolower(pathinfo($_FILES['watermark_image']['name'], PATHINFO_EXTENSION));
        if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp', 'svg'])) {
            $newWmName = 'watermark_' . time() . '.' . $fileExt;
            $dest = $uploadDir . '/' . $newWmName;
            if (move_uploaded_file($fileTmp, $dest)) {
                $keys['watermark_image'] = $newWmName;
                $keys['watermark_enabled'] = '1';
            }
        }
    } elseif (isset($_POST['remove_watermark_image']) && $_POST['remove_watermark_image'] === '1') {
        $keys['watermark_image'] = '';
        $keys['watermark_enabled'] = '0';
    } elseif (isset($_POST['use_logo_as_watermark']) && $_POST['use_logo_as_watermark'] === '1') {
        $currentLogo = $keys['kop_logo'] ?? $settingsRaw['kop_logo'] ?? '';
        if (!empty($currentLogo)) {
            $keys['watermark_image'] = $currentLogo;
            $keys['watermark_enabled'] = '1';
        }
    }

    // Handle Upload Stempel / TTD Mudir
    if (isset($_FILES['stempel_image']) && $_FILES['stempel_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['stempel_image']['tmp_name'];
        $fileExt = strtolower(pathinfo($_FILES['stempel_image']['name'], PATHINFO_EXTENSION));
        if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp'])) {
            $newStempelName = 'stempel_' . time() . '.' . $fileExt;
            $dest = $uploadDir . '/' . $newStempelName;
            if (move_uploaded_file($fileTmp, $dest)) {
                $keys['stempel_image'] = $newStempelName;
            }
        }
    } elseif (isset($_POST['remove_stempel_image']) && $_POST['remove_stempel_image'] === '1') {
        $keys['stempel_image'] = '';
    }

    try {
        $pdo->beginTransaction();
        $stmtUpsert = $pdo->prepare("
            INSERT INTO rapor_settings (setting_key, setting_value) 
            VALUES (:k, :v)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");

        foreach ($keys as $k => $v) {
            $stmtUpsert->execute([':k' => $k, ':v' => $v]);
        }

        // Sinkronisasi pembaruan ke rapor_siswa
        $tempat = $keys['tempat_terbit'];
        $kepsekNama = $keys['kepsek_nama'];
        $kepsekNip = $keys['kepsek_nip'];

        $stmtSync = $pdo->prepare("
            UPDATE rapor_siswa 
            SET tanggal_rapor = :tgl,
                tempat_terbit = :tempat,
                kepsek_nama = :knama,
                kepsek_nip = :knip
            WHERE (tipe_rapor = :tipe OR tipe_rapor = :tipe_alt) AND semester = :sem
        ");
        $stmtSync->execute([':tgl' => $ptsGanjil, ':tempat' => $tempat, ':knama' => $kepsekNama, ':knip' => $kepsekNip, ':tipe' => 'ats', ':tipe_alt' => 'pts', ':sem' => 'Ganjil']);
        $stmtSync->execute([':tgl' => $ptsGenap, ':tempat' => $tempat, ':knama' => $kepsekNama, ':knip' => $kepsekNip, ':tipe' => 'ats', ':tipe_alt' => 'pts', ':sem' => 'Genap']);
        $stmtSync->execute([':tgl' => $pasGanjil, ':tempat' => $tempat, ':knama' => $kepsekNama, ':knip' => $kepsekNip, ':tipe' => 'pas', ':tipe_alt' => 'aas', ':sem' => 'Ganjil']);
        $stmtSync->execute([':tgl' => $pasGenap, ':tempat' => $tempat, ':knama' => $kepsekNama, ':knip' => $kepsekNip, ':tipe' => 'pas', ':tipe_alt' => 'aas', ':sem' => 'Genap']);

        $pdo->commit();
        redirect('settings.php', 'success', '🎉 Pengaturan Rapor & KOP Surat berhasil diperbarui!');
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = 'Gagal menyimpan pengaturan: ' . $e->getMessage();
    }
}

// Refresh settings after POST
$settingsRaw = $pdo->query("SELECT setting_key, setting_value FROM rapor_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$kopImage = $settingsRaw['kop_image'] ?? '';
$kopLogo = $settingsRaw['kop_logo'] ?? '';
$stempelImage = $settingsRaw['stempel_image'] ?? '';
$useUploadedKop = ($settingsRaw['use_uploaded_kop'] ?? '0') === '1';

$watermarkImage = $settingsRaw['watermark_image'] ?? '';
$watermarkEnabled = ($settingsRaw['watermark_enabled'] ?? '0') === '1';
$watermarkOpacity = (int) ($settingsRaw['watermark_opacity'] ?? 12);
$watermarkSize = (int) ($settingsRaw['watermark_size'] ?? 360);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid px-0 mb-5">
    <!-- SUB TABS NAVIGATION WALI KELAS -->
    <ul class="nav nav-tabs mb-4" style="border-bottom: 2px solid #e2e8f0; gap: 8px;">
        <li class="nav-item">
            <a class="nav-link text-secondary fw-bold"
                style="border:none; border-bottom: 3px solid transparent; padding: 0.75rem 1.25rem;"
                href="<?= BASE_URL ?>/pages/wali-kelas/index.php">
                <i class="bi bi-journal-check fs-5 text-primary"></i> Rekap Nilai & Catatan Guru (Pertemuan Ortu)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-secondary fw-bold"
                style="border:none; border-bottom: 3px solid transparent; padding: 0.75rem 1.25rem;"
                href="<?= BASE_URL ?>/pages/wali-kelas/ranking.php">
                <i class="bi bi-trophy-fill fs-5 text-warning"></i> Ranking
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-secondary fw-bold"
                style="border:none; border-bottom: 3px solid transparent; padding: 0.75rem 1.25rem;"
                href="<?= BASE_URL ?>/pages/rapor/index.php?tipe=ats">
                <i class="bi bi-file-earmark-text-fill fs-5 text-success"></i> Rapor ATS (Tengah Sem)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-secondary fw-bold"
                style="border:none; border-bottom: 3px solid transparent; padding: 0.75rem 1.25rem;"
                href="<?= BASE_URL ?>/pages/rapor/index.php?tipe=pas">
                <i class="bi bi-journal-bookmark-fill fs-5" style="color: #6366f1;"></i> Rapor AAS (Akhir Sem)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active fw-bold text-primary"
                style="border:none; border-bottom: 3px solid #1e3a8a; padding: 0.75rem 1.25rem; background:transparent;"
                href="<?= BASE_URL ?>/pages/rapor/settings.php">
                <i class="bi bi-sliders fs-5 text-secondary"></i> Pengaturan Rapor, KOP & TTD
            </a>
        </li>
    </ul>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h4 class="fw-extrabold text-dark mb-0"><i class="bi bi-gear-wide-connected text-primary me-2"></i>
                Pengaturan Rapor & KOP Surat</h4>
            <p class="text-muted small mb-0">Atur KOP Surat resmi (unggah gambar atau desain teks otomatis), Kepala
                Sekolah/Mudir, NIP, Cap/Stempel, dan Tanggal Pembagian Rapor</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-primary rounded-pill px-3.5 fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Rapor
            </a>
        </div>
    </div>

    <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($errorMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_settings">

        <div class="row g-4">
            <!-- 1. KOP SURAT RAPOR (UPLOAD / TEKS OTOMATIS) -->
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                    <div
                        class="card-header bg-white p-3.5 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <h6 class="fw-extrabold text-dark mb-0">
                                <i class="bi bi-card-heading text-primary me-2"></i> KOP Surat Rapor (Header Atas)
                            </h6>
                            <small class="text-muted">KOP akan ditampilkan di bagian paling atas dokumen cetak Rapor ATS
                                & AAS</small>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="use_uploaded_kop"
                                name="use_uploaded_kop" value="1" <?= $useUploadedKop ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold small text-dark" for="use_uploaded_kop">Gunakan
                                Gambar KOP Surat Unggahan</label>
                        </div>
                    </div>
                    <div class="card-body p-3.5 p-md-4">
                        <div class="row g-4">
                            <!-- UPLOAD GAMBAR KOP -->
                            <div class="col-lg-5">
                                <div class="p-3 bg-light rounded-3 border h-100">
                                    <label
                                        class="form-label small fw-bold text-dark d-flex align-items-center justify-content-between mb-2">
                                        <span><i class="bi bi-image me-1 text-primary"></i> Upload File Gambar KOP
                                            Surat</span>
                                        <span class="badge bg-secondary font-monospace">JPG, PNG, WebP</span>
                                    </label>
                                    <input type="file" name="kop_image"
                                        class="form-control form-control-sm bg-white mb-2" accept="image/*"
                                        onchange="previewKopImage(this)">
                                    <small class="text-muted d-block mb-3" style="font-size: 0.78rem;">
                                        Rekomendasi ukuran: <strong>2100 x 380 px</strong> (rasio lebar penuh A4).
                                    </small>

                                    <?php if (!empty($kopImage) && file_exists($uploadDir . '/' . $kopImage)): ?>
                                        <div
                                            class="border rounded-3 p-2 bg-white text-center position-relative mb-2 shadow-xs">
                                            <img id="kopImgPreview"
                                                src="<?= BASE_URL ?>/uploads/rapor/<?= htmlspecialchars($kopImage) ?>"
                                                alt="KOP Surat" class="img-fluid rounded"
                                                style="max-height: 110px; width: 100%; object-fit: contain;">
                                            <div class="mt-2 text-start d-flex justify-content-between align-items-center">
                                                <small class="text-success fw-bold"><i
                                                        class="bi bi-check-circle-fill me-1"></i> File aktif:
                                                    <?= htmlspecialchars($kopImage) ?></small>
                                                <div class="form-check form-check-inline m-0">
                                                    <input class="form-check-input" type="checkbox" name="remove_kop_image"
                                                        value="1" id="removeKop">
                                                    <label class="form-check-label small text-danger fw-semibold"
                                                        for="removeKop">Hapus Gambar</label>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div id="kopImgPlaceholder"
                                            class="border border-dashed rounded-3 p-4 bg-white text-center text-muted">
                                            <i class="bi bi-cloud-arrow-up display-6 d-block mb-1 opacity-50"></i>
                                            <small class="fw-semibold">Belum ada gambar KOP surat yang diunggah.</small>
                                            <br><small class="text-secondary" style="font-size: 0.72rem;">Sistem akan
                                                menggunakan format teks otomatis di samping.</small>
                                        </div>
                                        <div id="kopImgPreviewBox" style="display:none;"
                                            class="border rounded-3 p-2 bg-white text-center mb-2">
                                            <img id="kopImgPreview" src="" class="img-fluid rounded"
                                                style="max-height: 110px; width: 100%; object-fit: contain;">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- FORM TEKS KOP OTOMATIS -->
                            <div class="col-lg-7">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted mb-1">Nama Sekolah
                                            Utama</label>
                                        <input type="text" name="kop_nama_sekolah"
                                            class="form-control fw-bold text-dark"
                                            value="<?= htmlspecialchars($settingsRaw['kop_nama_sekolah'] ?? 'SMP ISMI') ?>"
                                            placeholder="SMP ISMI">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted mb-1">Nama Lembaga /
                                            Yayasan</label>
                                        <input type="text" name="kop_yayasan" class="form-control fw-bold text-primary"
                                            value="<?= htmlspecialchars($settingsRaw['kop_yayasan'] ?? 'ISLAMIC SCHOOL OF MINHAJ AL-ILMI') ?>"
                                            placeholder="ISLAMIC SCHOOL OF MINHAJ AL-ILMI">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-muted mb-1">Slogan / Visi
                                            Sekolah</label>
                                        <input type="text" name="kop_slogan" class="form-control fst-italic"
                                            value="<?= htmlspecialchars($settingsRaw['kop_slogan'] ?? 'Berakhlak Mulia, Berilmu, Mahir IT') ?>"
                                            placeholder="Berakhlak Mulia, Berilmu, Mahir IT">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-muted mb-1">Alamat Sekolah</label>
                                        <input type="text" name="kop_alamat" class="form-control"
                                            value="<?= htmlspecialchars($settingsRaw['kop_alamat'] ?? 'Jl. Nakula No.1 Harjamukti Cimanggis Depok') ?>"
                                            placeholder="Jl. Nakula No.1 Harjamukti Cimanggis Depok">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted mb-1">No. Telepon</label>
                                        <input type="text" name="kop_telp" class="form-control"
                                            value="<?= htmlspecialchars($settingsRaw['kop_telp'] ?? 'Telp. (021) 4867 8793') ?>"
                                            placeholder="Telp. (021) 4867 8793">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted mb-1">Email Resmi</label>
                                        <input type="text" name="kop_email" class="form-control"
                                            value="<?= htmlspecialchars($settingsRaw['kop_email'] ?? 'smp.minhaj.al.ilmi@gmail.com') ?>"
                                            placeholder="smp.minhaj.al.ilmi@gmail.com">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted mb-1">Website Sekolah</label>
                                        <input type="text" name="kop_website" class="form-control"
                                            value="<?= htmlspecialchars($settingsRaw['kop_website'] ?? 'sekolahismi.sch.id & ismi.sch.id') ?>"
                                            placeholder="sekolahismi.sch.id & ismi.sch.id">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted mb-1">Kontak Admin WA</label>
                                        <input type="text" name="kop_wa" class="form-control"
                                            value="<?= htmlspecialchars($settingsRaw['kop_wa'] ?? 'Admin WA 0899-2244-111') ?>"
                                            placeholder="Admin WA 0899-2244-111">
                                    </div>
                                    <div class="col-12 mt-2">
                                        <label class="form-label small fw-bold text-muted mb-1">Logo Sekolah (Kiri
                                            KOP)</label>
                                        <div class="d-flex align-items-center gap-3">
                                            <?php if (!empty($kopLogo) && file_exists($uploadDir . '/' . $kopLogo)): ?>
                                                <img src="<?= BASE_URL ?>/uploads/rapor/<?= htmlspecialchars($kopLogo) ?>"
                                                    alt="Logo" class="rounded border p-1"
                                                    style="height: 42px; width: 42px; object-fit: contain;">
                                                <div class="form-check form-check-inline m-0">
                                                    <input class="form-check-input" type="checkbox" name="remove_kop_logo"
                                                        value="1" id="removeLogo">
                                                    <label class="form-check-label small text-danger"
                                                        for="removeLogo">Hapus</label>
                                                </div>
                                            <?php endif; ?>
                                            <input type="file" name="kop_logo" class="form-control form-control-sm"
                                                accept="image/*">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. WATERMARK LOGO / LATAR BELAKANG RAPOR -->
            <?php
            $simWmSrc = '';
            if (!empty($watermarkImage) && file_exists($uploadDir . '/' . $watermarkImage)) {
                $simWmSrc = BASE_URL . '/uploads/rapor/' . htmlspecialchars($watermarkImage);
            } elseif (!empty($kopLogo) && file_exists($uploadDir . '/' . $kopLogo)) {
                $simWmSrc = BASE_URL . '/uploads/rapor/' . htmlspecialchars($kopLogo);
            }
            $liveOpacity = $watermarkEnabled ? ($watermarkOpacity / 100) : 0;
            $hasSimWm = !empty($simWmSrc);
            ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                    <div
                        class="card-header bg-white p-3.5 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <h6 class="fw-extrabold text-dark mb-0">
                                <i class="bi bi-shield-shaded text-primary me-2"></i> Watermark Logo / Lambang Latar
                                Belakang Rapor
                            </h6>
                            <small class="text-muted">Sisipkan lambang atau logo transparan di tengah lembar cetak rapor
                                (ATS & AAS) dengan transparansi yang dapat diatur</small>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="watermark_enabled"
                                name="watermark_enabled" value="1" <?= $watermarkEnabled ? 'checked' : '' ?>
                                onchange="toggleWatermarkUi(this.checked)">
                            <label class="form-check-label fw-bold small text-dark" for="watermark_enabled">
                                Aktifkan Watermark Rapor
                            </label>
                        </div>
                    </div>
                    <div class="card-body p-3.5 p-md-4">
                        <div class="row g-4">
                            <!-- UPLOAD / KELOLA GAMBAR WATERMARK -->
                            <div class="col-lg-5">
                                <div class="p-3 bg-light rounded-3 border h-100">
                                    <label
                                        class="form-label small fw-bold text-dark d-flex align-items-center justify-content-between mb-2">
                                        <span><i class="bi bi-image me-1 text-primary"></i> File Gambar Watermark</span>
                                        <span class="badge bg-secondary font-monospace">PNG, WebP, JPG, SVG</span>
                                    </label>
                                    <input type="file" name="watermark_image" id="wmFileInput"
                                        class="form-control form-control-sm bg-white mb-2" accept="image/*"
                                        onchange="previewWatermarkImage(this)">
                                    <small class="text-muted d-block mb-3" style="font-size: 0.78rem;">
                                        Disarankan menggunakan file <strong>PNG Transparan</strong> atau lambang sekolah
                                        resolusi tinggi.
                                    </small>

                                    <?php if (!empty($watermarkImage) && file_exists($uploadDir . '/' . $watermarkImage)): ?>
                                        <div
                                            class="border rounded-3 p-2 bg-white text-center position-relative mb-2 shadow-xs">
                                            <div class="p-3 rounded bg-light border d-inline-block position-relative"
                                                style="background-image: repeating-linear-gradient(45deg, #f1f5f9 25%, transparent 25%, transparent 75%, #f1f5f9 75%, #f1f5f9), repeating-linear-gradient(45deg, #f1f5f9 25%, #ffffff 25%, #ffffff 75%, #f1f5f9 75%, #f1f5f9); background-position: 0 0, 10px 10px; background-size: 20px 20px;">
                                                <img id="wmImgPreview"
                                                    src="<?= BASE_URL ?>/uploads/rapor/<?= htmlspecialchars($watermarkImage) ?>"
                                                    alt="Watermark Rapor" class="img-fluid rounded"
                                                    style="max-height: 100px; max-width: 160px; object-fit: contain;">
                                            </div>
                                            <div class="mt-2 text-start d-flex justify-content-between align-items-center">
                                                <small class="text-success fw-bold"><i
                                                        class="bi bi-check-circle-fill me-1"></i> File aktif:
                                                    <?= htmlspecialchars($watermarkImage) ?></small>
                                                <div class="form-check form-check-inline m-0">
                                                    <input class="form-check-input" type="checkbox"
                                                        name="remove_watermark_image" value="1" id="removeWm"
                                                        onchange="toggleRemoveWm(this.checked)">
                                                    <label class="form-check-label small text-danger fw-semibold"
                                                        for="removeWm">Hapus Watermark</label>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div id="wmImgPlaceholder"
                                            class="border border-dashed rounded-3 p-4 bg-white text-center text-muted">
                                            <i class="bi bi-shield-fill-check display-6 d-block mb-1 opacity-50"></i>
                                            <small class="fw-semibold">Belum ada file watermark khusus yang
                                                diunggah.</small>
                                            <br><small class="text-secondary" style="font-size: 0.72rem;">Sistem otomatis
                                                dapat memakai Logo KOP Sekolah.</small>
                                        </div>
                                        <div id="wmImgPreviewBox" style="display:none;"
                                            class="border rounded-3 p-2 bg-white text-center mb-2">
                                            <img id="wmImgPreview" src="" alt="Preview" class="img-fluid rounded"
                                                style="max-height: 100px; max-width: 160px; object-fit: contain;">
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($kopLogo) && file_exists($uploadDir . '/' . $kopLogo)): ?>
                                        <div class="mt-3 pt-2 border-top">
                                            <button type="button" class="btn btn-sm btn-outline-primary w-100"
                                                onclick="useLogoAsWatermarkClick()">
                                                <i class="bi bi-arrow-repeat me-1"></i> Gunakan Logo KOP Sekolah sebagai
                                                Watermark
                                            </button>
                                            <input type="hidden" name="use_logo_as_watermark" id="useLogoAsWatermarkInput"
                                                value="0">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- PENGATURAN OPACITY & UKURAN + LIVE PREVIEW -->
                            <div class="col-lg-7">
                                <div class="row g-3">
                                    <!-- SLIDER TRANSPARANSI (OPACITY) -->
                                    <div class="col-12">
                                        <div class="p-3 bg-light rounded-3 border">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label class="form-label small fw-bold text-dark mb-0">
                                                    <i class="bi bi-droplet-half text-primary me-1"></i> Transparansi /
                                                    Opacity Watermark
                                                </label>
                                                <span class="badge bg-primary fs-6 px-2.5 py-1"
                                                    id="wmOpacityBadge"><?= $watermarkOpacity ?>%</span>
                                            </div>
                                            <input type="range" class="form-range mt-2" min="2" max="60" step="1"
                                                name="watermark_opacity" id="watermark_opacity"
                                                value="<?= $watermarkOpacity ?>" oninput="syncWmOpacity(this.value)">
                                            <div class="d-flex justify-content-between text-muted"
                                                style="font-size: 0.72rem;">
                                                <span>2% (Sangat Tipis)</span>
                                                <span class="text-primary fw-bold">10% - 15% (Ideal Standar
                                                    Cetak)</span>
                                                <span>60% (Sangat Jelas)</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- SLIDER UKURAN (SIZE) -->
                                    <div class="col-12">
                                        <div class="p-3 bg-light rounded-3 border">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label class="form-label small fw-bold text-dark mb-0">
                                                    <i class="bi bi-arrows-angle-expand text-primary me-1"></i> Ukuran
                                                    Logo (Diameter / Lebar)
                                                </label>
                                                <span class="badge bg-secondary fs-6 px-2.5 py-1"
                                                    id="wmSizeBadge"><?= $watermarkSize ?> px</span>
                                            </div>
                                            <input type="range" class="form-range mt-2" min="180" max="850" step="10"
                                                name="watermark_size" id="watermark_size" value="<?= $watermarkSize ?>"
                                                oninput="syncWmSize(this.value)">
                                            <div class="d-flex justify-content-between text-muted"
                                                style="font-size: 0.72rem;">
                                                <span>180 px (Kecil)</span>
                                                <span class="text-secondary fw-bold">450 - 550 px (Besar & Jelas)</span>
                                                <span>850 px (Ekstra Besar Penuh)</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- SIMULASI / LIVE PREVIEW LEMBAR RAPOR -->
                                    <div class="col-12">
                                        <div class="p-3 bg-white rounded-3 border shadow-2xs position-relative overflow-hidden"
                                            style="min-height: 140px;">
                                            <div class="small fw-bold text-muted mb-2 text-uppercase d-flex justify-content-between align-items-center"
                                                style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                                <span><i class="bi bi-eye-fill me-1 text-primary"></i> Simulasi Tampilan
                                                    Latar Rapor Cetak:</span>
                                                <span
                                                    class="badge <?= $watermarkEnabled ? 'bg-success' : 'bg-secondary' ?>"
                                                    id="wmStatusBadge">
                                                    <?= $watermarkEnabled ? 'Status: Aktif' : 'Status: Nonaktif' ?>
                                                </span>
                                            </div>

                                            <div class="position-relative p-2 border rounded-2 bg-white"
                                                style="overflow: hidden;">
                                                <!-- Live Watermark Overlay -->
                                                <div id="wmLiveContainer"
                                                    class="position-absolute d-flex align-items-center justify-content-center"
                                                    style="top: 50%; left: 50%; transform: translate(-50%, -50%); pointer-events: none; z-index: 0; width: 140px; height: 140px; opacity: <?= $liveOpacity ?>; transition: opacity 0.15s ease;">
                                                    <img id="wmLiveImg" src="<?= $simWmSrc ?>" alt="Watermark Preview"
                                                        style="max-width: 100%; max-height: 100%; object-fit: contain; <?= $hasSimWm ? '' : 'display:none;' ?>">
                                                    <div id="wmLiveFallbackText"
                                                        style="display: <?= $hasSimWm ? 'none' : 'block' ?>; font-size: 2.2rem; font-weight: 900; color: #1e3a8a;">
                                                        ISMI
                                                    </div>
                                                </div>

                                                <!-- Sample Text Content on Top -->
                                                <div class="position-relative"
                                                    style="z-index: 1; font-family: 'Cambria', Georgia, serif; font-size: 8.5pt;">
                                                    <div class="fw-bold text-center border-bottom pb-1 mb-2">LAPORAN
                                                        HASIL BELAJAR PESERTA DIDIK</div>
                                                    <table class="table table-sm table-bordered m-0"
                                                        style="font-size: 8pt; background: transparent;">
                                                        <tr class="table-light">
                                                            <th style="width: 35%;">Mata Pelajaran</th>
                                                            <th class="text-center" style="width: 15%;">Nilai</th>
                                                            <th class="text-center" style="width: 50%;">Capaian
                                                                Kompetensi</th>
                                                        </tr>
                                                        <tr>
                                                            <td>Pendidikan Agama Islam</td>
                                                            <td class="text-center fw-bold font-monospace">92</td>
                                                            <td class="text-muted" style="font-size:7.5pt;">Menunjukkan
                                                                penguasaan sangat baik...</td>
                                                        </tr>
                                                        <tr>
                                                            <td>Matematika</td>
                                                            <td class="text-center fw-bold font-monospace">88</td>
                                                            <td class="text-muted" style="font-size:7.5pt;">Mencapai
                                                                kompetensi dengan baik...</td>
                                                        </tr>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. PENANDATANGAN (MUDIR / KEPSEK & STEMPEL) -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                    <div class="card-header bg-white p-3.5 border-bottom">
                        <h6 class="fw-extrabold text-dark mb-0">
                            <i class="bi bi-person-badge-fill text-primary me-2"></i> Pimpinan / Mudir & Cap Stempel
                        </h6>
                        <small class="text-muted">Data penandatangan utama dan cap stempel resmi sekolah</small>
                    </div>
                    <div class="card-body p-3.5">
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted mb-1">Jabatan Pimpinan</label>
                                <input type="text" name="kepsek_jabatan" class="form-control fw-bold"
                                    value="<?= htmlspecialchars($settingsRaw['kepsek_jabatan'] ?? 'Mudir SMP') ?>"
                                    placeholder="Mudir SMP / Kepala Sekolah" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted mb-1">Nama Satuan Lembaga</label>
                                <input type="text" name="kepsek_lembaga" class="form-control"
                                    value="<?= htmlspecialchars($settingsRaw['kepsek_lembaga'] ?? 'Islamic School of Minhaj Al Ilmi') ?>"
                                    placeholder="Islamic School of Minhaj Al Ilmi">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted mb-1">Nama Lengkap & Gelar
                                Pimpinan</label>
                            <input type="text" name="kepsek_nama" class="form-control fw-bold"
                                value="<?= htmlspecialchars($settingsRaw['kepsek_nama'] ?? 'Riki Kaptanto, Lc') ?>"
                                required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted mb-1">NIP / ID Pimpinan</label>
                            <input type="text" name="kepsek_nip" class="form-control font-monospace"
                                value="<?= htmlspecialchars($settingsRaw['kepsek_nip'] ?? '20240711001') ?>"
                                placeholder="NIP (isi '-' jika tidak ada)">
                        </div>
                        <div class="p-3 bg-light rounded-3 border">
                            <label
                                class="form-label small fw-bold text-dark d-flex align-items-center justify-content-between mb-1">
                                <span><i class="bi bi-patch-check-fill text-success me-1"></i> Upload Gambar Cap Stempel
                                    & TTD Pimpinan (Transparan/PNG)</span>
                            </label>
                            <small class="text-muted d-block mb-2" style="font-size:0.75rem;">Cap stempel akan
                                disisipkan di atas tanda tangan Mudir/Kepala Sekolah.</small>
                            <div class="d-flex align-items-center gap-3">
                                <?php if (!empty($stempelImage) && file_exists($uploadDir . '/' . $stempelImage)): ?>
                                    <img src="<?= BASE_URL ?>/uploads/rapor/<?= htmlspecialchars($stempelImage) ?>"
                                        alt="Stempel" class="rounded border p-1 bg-white"
                                        style="height: 50px; width: 50px; object-fit: contain;">
                                    <div class="form-check form-check-inline m-0">
                                        <input class="form-check-input" type="checkbox" name="remove_stempel_image"
                                            value="1" id="removeStempel">
                                        <label class="form-check-label small text-danger" for="removeStempel">Hapus
                                            Cap</label>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="stempel_image" class="form-control form-control-sm bg-white"
                                    accept="image/*">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. TITIMANGSA & TANGGAL PEMBAGIAN RAPOR -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                    <div class="card-header bg-white p-3.5 border-bottom">
                        <h6 class="fw-extrabold text-dark mb-0">
                            <i class="bi bi-calendar-event-fill text-info me-2"></i> Titimangsa & Tanggal Penerbitan
                        </h6>
                        <small class="text-muted">Kota penerbitan dan tanggal resmi cetak rapor per semester</small>
                    </div>
                    <div class="card-body p-3.5">
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted mb-1">Kota / Titimangsa</label>
                                <input type="text" name="tempat_terbit" class="form-control fw-bold"
                                    value="<?= htmlspecialchars($settingsRaw['tempat_terbit'] ?? 'Depok') ?>"
                                    placeholder="Depok">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted mb-1">Fase Default</label>
                                <input type="text" name="fase_default" class="form-control"
                                    value="<?= htmlspecialchars($settingsRaw['fase_default'] ?? 'D') ?>"
                                    placeholder="D">
                            </div>
                        </div>
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <h6 class="fw-bold text-dark small mb-2"><i
                                    class="bi bi-file-earmark-text text-primary me-1"></i> Rapor ATS (Tengah Semester)
                            </h6>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small text-muted mb-1" style="font-size:0.75rem;">Semester
                                        Ganjil</label>
                                    <input type="date" name="tanggal_pts_ganjil" class="form-control form-control-sm"
                                        value="<?= htmlspecialchars($settingsRaw['tanggal_pts_ganjil'] ?? date('Y-10-11')) ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small text-muted mb-1" style="font-size:0.75rem;">Semester
                                        Genap</label>
                                    <input type="date" name="tanggal_pts_genap" class="form-control form-control-sm"
                                        value="<?= htmlspecialchars($settingsRaw['tanggal_pts_genap'] ?? (date('Y') + 1) . '-03-20') ?>">
                                </div>
                            </div>
                        </div>
                        <div class="p-3 bg-light rounded-3 border">
                            <h6 class="fw-bold text-dark small mb-2"><i
                                    class="bi bi-file-earmark-check text-success me-1"></i> Rapor AAS / Akhir Semester
                            </h6>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small text-muted mb-1" style="font-size:0.75rem;">Semester
                                        Ganjil</label>
                                    <input type="date" name="tanggal_pas_ganjil" class="form-control form-control-sm"
                                        value="<?= htmlspecialchars($settingsRaw['tanggal_pas_ganjil'] ?? date('Y-12-20')) ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small text-muted mb-1" style="font-size:0.75rem;">Semester
                                        Genap</label>
                                    <input type="date" name="tanggal_pas_genap" class="form-control form-control-sm"
                                        value="<?= htmlspecialchars($settingsRaw['tanggal_pas_genap'] ?? (date('Y') + 1) . '-06-25') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. DAFTAR WALI KELAS -->
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                    <div
                        class="card-header bg-white p-3.5 border-bottom d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="fw-extrabold text-dark mb-0"><i
                                    class="bi bi-person-workspace text-success me-2"></i> Daftar Wali Kelas Saat Ini
                            </h6>
                            <small class="text-muted">Untuk mengubah wali kelas, Anda dapat mengaturnya di menu <a
                                    href="../kelas/index.php" class="fw-bold">Data Kelas</a></small>
                        </div>
                        <a href="../kelas/index.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Kelola di Data Kelas
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light text-muted small fw-bold text-uppercase">
                                    <tr>
                                        <th style="width: 50px;" class="ps-3 text-center">No</th>
                                        <th>Nama Kelas</th>
                                        <th>Tingkat</th>
                                        <th>Wali Kelas Terdaftar</th>
                                        <th>NIP Wali Kelas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($kelasList as $idx => $k): ?>
                                        <tr>
                                            <td class="ps-3 text-center fw-bold text-muted"><?= $idx + 1 ?></td>
                                            <td><strong>Kelas <?= htmlspecialchars($k['nama_kelas']) ?></strong></td>
                                            <td><span class="badge bg-light text-dark border">Tingkat
                                                    <?= htmlspecialchars($k['tingkat']) ?></span></td>
                                            <td>
                                                <?php if (!empty($k['nama_wali_kelas'])): ?>
                                                    <span class="fw-bold text-dark"><i
                                                            class="bi bi-person-check-fill text-success me-1"></i><?= htmlspecialchars($k['nama_wali_kelas']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted font-italic">— Belum ditugaskan —</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span
                                                    class="font-monospace text-muted"><?= htmlspecialchars($k['nip_wali_kelas'] ?: '-') ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-end gap-2 mt-4">
            <a href="index.php" class="btn btn-light rounded-pill px-4 fw-bold">Batal</a>
            <button type="submit" class="btn btn-primary rounded-pill px-4.5 py-2 fw-bold shadow-sm"
                style="background: #1e3a8a; border-color: #1e3a8a;">
                <i class="bi bi-check2-circle me-1"></i> Simpan Pengaturan Rapor & KOP
            </button>
        </div>
    </form>
</div>

<script>
    function previewKopImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                const preview = document.getElementById('kopImgPreview');
                const previewBox = document.getElementById('kopImgPreviewBox');
                const placeholder = document.getElementById('kopImgPlaceholder');
                if (preview) preview.src = e.target.result;
                if (previewBox) previewBox.style.display = 'block';
                if (placeholder) placeholder.style.display = 'none';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function previewWatermarkImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                const preview = document.getElementById('wmImgPreview');
                const previewBox = document.getElementById('wmImgPreviewBox');
                const placeholder = document.getElementById('wmImgPlaceholder');
                const liveImg = document.getElementById('wmLiveImg');
                const liveFallback = document.getElementById('wmLiveFallbackText');

                if (preview) preview.src = e.target.result;
                if (previewBox) previewBox.style.display = 'block';
                if (placeholder) placeholder.style.display = 'none';

                if (liveImg) {
                    liveImg.src = e.target.result;
                    liveImg.style.display = 'block';
                }
                if (liveFallback) liveFallback.style.display = 'none';

                // Automatically check watermark checkbox
                const chk = document.getElementById('watermark_enabled');
                if (chk && !chk.checked) {
                    chk.checked = true;
                    toggleWatermarkUi(true);
                }
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function toggleWatermarkUi(enabled) {
        const liveContainer = document.getElementById('wmLiveContainer');
        const statusBadge = document.getElementById('wmStatusBadge');
        const opacityVal = parseInt(document.getElementById('watermark_opacity')?.value || '12', 10);

        if (liveContainer) {
            liveContainer.style.opacity = enabled ? (opacityVal / 100) : '0';
        }
        if (statusBadge) {
            if (enabled) {
                statusBadge.className = 'badge bg-success';
                statusBadge.textContent = 'Status: Aktif';
            } else {
                statusBadge.className = 'badge bg-secondary';
                statusBadge.textContent = 'Status: Nonaktif';
            }
        }
    }

    function syncWmOpacity(val) {
        const badge = document.getElementById('wmOpacityBadge');
        const liveContainer = document.getElementById('wmLiveContainer');
        const isEnabled = document.getElementById('watermark_enabled')?.checked ?? true;

        if (badge) badge.textContent = val + '%';
        if (liveContainer && isEnabled) {
            liveContainer.style.opacity = (val / 100);
        }
    }

    function syncWmSize(val) {
        const badge = document.getElementById('wmSizeBadge');
        if (badge) badge.textContent = val + ' px';
    }

    function toggleRemoveWm(checked) {
        if (checked) {
            const liveImg = document.getElementById('wmLiveImg');
            const liveFallback = document.getElementById('wmLiveFallbackText');
            if (liveImg) liveImg.style.display = 'none';
            if (liveFallback) liveFallback.style.display = 'block';
        }
    }

    function useLogoAsWatermarkClick() {
        const inputHidden = document.getElementById('useLogoAsWatermarkInput');
        if (inputHidden) inputHidden.value = '1';

        const chk = document.getElementById('watermark_enabled');
        if (chk) {
            chk.checked = true;
            toggleWatermarkUi(true);
        }

        // Visual feedback
        alert('Logo KOP Sekolah akan otomatis diset sebagai Watermark Rapor saat Anda menekan tombol "Simpan Pengaturan Rapor & KOP".');
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>