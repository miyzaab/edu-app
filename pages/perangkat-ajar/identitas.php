<?php
/**
 * PERANGKAT AJAR KURIKULUM MERDEKA - EDIT IDENTITAS DOKUMEN & KOP SEKOLAH
 * Khusus Administrator: Mengatur Logo, Nama Sekolah, Alamat, Kontak, dan Kepala Sekolah
 * Dilengkapi Live Interactive Preview Kop Surat & Lembar Tanda Tangan Resmi.
 */
$pageTitle = 'Edit Identitas Dokumen & Kop Sekolah';
$activePage = 'perangkat-ajar';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_ajar');

// Proteksi Hak Akses: Khusus Admin
$userRole = strtolower($_SESSION['role'] ?? 'guru');
if ($userRole !== 'admin') {
    redirect('index.php', 'warning', 'Akses ditolak: Hanya Administrator yang berhak mengubah identitas dokumen dan kop sekolah.');
}

$pdo = getConnection();

// Handle Form Submission
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_identitas') {
    $namaSekolah = trim($_POST['nama_sekolah'] ?? '');
    $alamatSekolah = trim($_POST['alamat_sekolah'] ?? '');
    $kotaSekolah = trim($_POST['kota_sekolah'] ?? '');
    $teleponSekolah = trim($_POST['telepon_sekolah'] ?? '');
    $emailSekolah = trim($_POST['email_sekolah'] ?? '');
    $namaKepsek = trim($_POST['nama_kepsek'] ?? '');
    $nipKepsek = trim($_POST['nip_kepsek'] ?? '');
    $titimangsa = trim($_POST['titimangsa_dokumen'] ?? '');

    // 1. Simpan Settings Teks
    updateSetting('nama_sekolah', $namaSekolah);
    updateSetting('identitas_nama_sekolah', $namaSekolah);
    updateSetting('alamat_sekolah', $alamatSekolah);
    updateSetting('kota_sekolah', $kotaSekolah);
    updateSetting('telepon_sekolah', $teleponSekolah);
    updateSetting('email_sekolah', $emailSekolah);
    updateSetting('nama_kepsek', $namaKepsek);
    updateSetting('nama_kepala_sekolah', $namaKepsek);
    updateSetting('nip_kepsek', $nipKepsek);
    updateSetting('titimangsa_dokumen', $titimangsa);

    // 2. Upload Logo Baru (Opsional)
    if (isset($_FILES['logo_sekolah']) && $_FILES['logo_sekolah']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo_sekolah'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
        if (in_array($ext, $allowed) && $file['size'] <= 3 * 1024 * 1024) {
            $uploadDir = __DIR__ . '/../../assets/img/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            $filename = 'logo_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                updateSetting('logo_path', '/assets/img/' . $filename);
            }
        }
    }

    // 3. Upload Stempel / TTD Digital Baru (Opsional)
    if (isset($_FILES['stempel_kepsek']) && $_FILES['stempel_kepsek']['error'] === UPLOAD_ERR_OK) {
        $fileStempel = $_FILES['stempel_kepsek'];
        $extStempel = strtolower(pathinfo($fileStempel['name'], PATHINFO_EXTENSION));
        $allowedStempel = ['png', 'jpg', 'jpeg', 'webp'];
        if (in_array($extStempel, $allowedStempel) && $fileStempel['size'] <= 3 * 1024 * 1024) {
            $uploadDir = __DIR__ . '/../../assets/img/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            $filenameStempel = 'stempel_' . time() . '.' . $extStempel;
            if (move_uploaded_file($fileStempel['tmp_name'], $uploadDir . $filenameStempel)) {
                updateSetting('stempel_path', '/assets/img/' . $filenameStempel);
            }
        }
    }

    // 4. Sinkronisasi Otomatis ke Seluruh Data Dokumen Tersimpan di Database
    try {
        $stmtAll = $pdo->query("SELECT id, modul_ajar_json FROM perangkat_ajar");
        $updateStmt = $pdo->prepare("UPDATE perangkat_ajar SET modul_ajar_json = :json WHERE id = :id");
        while ($row = $stmtAll->fetch(PDO::FETCH_ASSOC)) {
            $j = json_decode($row['modul_ajar_json'] ?? '{}', true);
            if (is_array($j)) {
                $j['nama_sekolah'] = $namaSekolah;
                $j['nama_kepsek'] = $namaKepsek;
                $j['nip_kepsek'] = $nipKepsek;
                $updateStmt->execute([':json' => json_encode($j, JSON_UNESCAPED_UNICODE), ':id' => $row['id']]);
            }
        }
    } catch (Exception $e) {
        // Lewati jika belum ada dokumen
    }

    redirect('identitas.php', 'success', 'Alhamdulillah! Identitas Dokumen & Kop Sekolah berhasil diperbarui dan disinkronkan ke seluruh dokumen cetak PDF!');
}

// Ambil data settings aktif
$namaSekolah = getSetting('nama_sekolah', SCHOOL_NAME);
$alamatSekolah = getSetting('alamat_sekolah', 'Jl. Nakula No. 1');
$kotaSekolah = getSetting('kota_sekolah', 'Kota / Kabupaten');
$teleponSekolah = getSetting('telepon_sekolah', '0812-3456-7890');
$emailSekolah = getSetting('email_sekolah', 'info@sekolah.sch.id');
$namaKepsek = getSetting('nama_kepsek', 'Kepala Sekolah, M.Pd');
$nipKepsek = getSetting('nip_kepsek', '—');
$titimangsa = getSetting('titimangsa_dokumen', '');
$logoPath = getSetting('logo_path', '');
$stempelPath = getSetting('stempel_path', '');

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/subnav.php';
?>

<div class="row justify-content-center">
    <div class="col-xl-11 col-12">

        <!-- HEADER BANNER -->
        <div class="card border-0 rounded-4 shadow-sm mb-4 overflow-hidden"
            style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #1e40af 100%); color: #ffffff;">
            <div class="card-body p-4 p-md-5 position-relative">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3"
                    style="background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.22); backdrop-filter: blur(10px);">
                    <i class="bi bi-shield-lock-fill text-warning fs-6"></i>
                    <span class="small fw-bold text-white tracking-wide">PENGATURAN KHUSUS ADMINISTRATOR</span>
                </div>
                <h2 class="fw-extrabold mb-2" style="font-family: 'Outfit', sans-serif;">
                    Edit Identitas Dokumen &amp; Kop Sekolah
                </h2>
                <p class="text-white-50 mb-0 fs-6" style="max-width: 780px; line-height: 1.6;">
                    Atur logo, nama sekolah, kontak, dan tanda tangan kepala sekolah. Perubahan di sini otomatis
                    tersinkronisasi ke seluruh dokumen cetak PDF (CP, TP, ATP, dan Modul Ajar) dengan pratinjau
                    real-time.
                </p>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <!-- KOLOM KIRI: FORM PENGATURAN (7 COL) -->
            <div class="col-lg-7 col-12">
                <form method="POST" action="identitas.php" enctype="multipart/form-data" id="formIdentitasDokumen">
                    <input type="hidden" name="action" value="save_identitas">

                    <!-- KARTU 1: KOP SURAT SEKOLAH -->
                    <div class="card border-0 rounded-4 shadow-sm mb-4" style="background: #ffffff;">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                                <div class="rounded-circle d-flex align-items-center justify-content-center"
                                    style="width: 32px; height: 32px; background: #e0f2fe; color: #0284c7;">
                                    <i class="bi bi-bank2 fs-6"></i>
                                </div>
                                <h5 class="fw-bold mb-0 text-dark" style="font-family: 'Outfit', sans-serif;">
                                    1. Identitas Sekolah &amp; Kop Dokumen
                                </h5>
                            </div>

                            <!-- Upload Logo Sekolah -->
                            <div class="mb-4">
                                <label class="form-label extra-small fw-bold text-muted mb-1">LOGO RESMI SEKOLAH</label>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-3 border d-flex align-items-center justify-content-center p-2 bg-light flex-shrink-0"
                                        style="width: 70px; height: 70px;">
                                        <img src="<?= !empty($logoPath) ? BASE_URL . $logoPath : '../../assets/img/logo.png' ?>"
                                            alt="Logo" id="logoPreviewImg"
                                            style="max-width: 100%; max-height: 100%; object-fit: contain;"
                                            onerror="this.src='../../assets/img/logo.png'">
                                    </div>
                                    <div>
                                        <input type="file" name="logo_sekolah" id="inputLogoSekolah"
                                            class="form-control form-control-sm rounded-3"
                                            accept="image/png, image/jpeg, image/webp, image/svg+xml"
                                            onchange="previewLogo(this)">
                                        <div class="form-text extra-small text-muted">
                                            Format: PNG transparan, JPG, atau WebP (Maksimal 3MB).
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Nama Sekolah -->
                            <div class="mb-3">
                                <label class="form-label extra-small fw-bold text-muted mb-1">
                                    NAMA RESMI SEKOLAH / MADRASAH <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="nama_sekolah" id="inputNamaSekolah"
                                    class="form-control rounded-3 py-2 fw-bold"
                                    value="<?= htmlspecialchars($namaSekolah) ?>"
                                    placeholder="Contoh: SMP ISLAM TERPADU ..." required>
                            </div>

                            <!-- Alamat Lengkap -->
                            <div class="mb-3">
                                <label class="form-label extra-small fw-bold text-muted mb-1">
                                    ALAMAT LENGKAP SEKOLAH <span class="text-danger">*</span>
                                </label>
                                <textarea name="alamat_sekolah" id="inputAlamatSekolah" rows="2"
                                    class="form-control rounded-3 small"
                                    placeholder="Nama jalan, nomor, RT/RW, kelurahan, kecamatan..."
                                    required><?= htmlspecialchars($alamatSekolah) ?></textarea>
                            </div>

                            <!-- Kota / Kabupaten & Kontak -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-6 col-12">
                                    <label class="form-label extra-small fw-bold text-muted mb-1">
                                        KOTA / KABUPATEN <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="kota_sekolah" id="inputKotaSekolah"
                                        class="form-control rounded-3 py-2"
                                        value="<?= htmlspecialchars($kotaSekolah) ?>"
                                        placeholder="Contoh: Cilacap / Jakarta" required>
                                    <div class="form-text extra-small text-muted">Digunakan untuk titimangsa kota tanda
                                        tangan.</div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label extra-small fw-bold text-muted mb-1">TELEPON /
                                        WHATSAPP</label>
                                    <input type="text" name="telepon_sekolah" id="inputTelpSekolah"
                                        class="form-control rounded-3 py-2"
                                        value="<?= htmlspecialchars($teleponSekolah) ?>"
                                        placeholder="Contoh: 0812-3456-7890">
                                </div>
                            </div>

                            <!-- Email Sekolah -->
                            <div class="mb-2">
                                <label class="form-label extra-small fw-bold text-muted mb-1">EMAIL RESMI
                                    SEKOLAH</label>
                                <input type="email" name="email_sekolah" id="inputEmailSekolah"
                                    class="form-control rounded-3 py-2" value="<?= htmlspecialchars($emailSekolah) ?>"
                                    placeholder="Contoh: info@sekolah.sch.id">
                            </div>
                        </div>
                    </div>

                    <!-- KARTU 2: KEPALA SEKOLAH & TANDA TANGAN -->
                    <div class="card border-0 rounded-4 shadow-sm mb-4" style="background: #ffffff;">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                                <div class="rounded-circle d-flex align-items-center justify-content-center"
                                    style="width: 32px; height: 32px; background: #fef3c7; color: #d97706;">
                                    <i class="bi bi-pen-fill fs-6"></i>
                                </div>
                                <h5 class="fw-bold mb-0 text-dark" style="font-family: 'Outfit', sans-serif;">
                                    2. Pimpinan &amp; Pengesahan Dokumen
                                </h5>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-7 col-12">
                                    <label class="form-label extra-small fw-bold text-muted mb-1">
                                        NAMA KEPALA SEKOLAH (DENGAN GELAR) <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="nama_kepsek" id="inputNamaKepsek"
                                        class="form-control rounded-3 py-2 fw-semibold"
                                        value="<?= htmlspecialchars($namaKepsek) ?>"
                                        placeholder="Contoh: Drs. H. Ahmad Dahlan, M.Pd" required>
                                </div>
                                <div class="col-md-5 col-12">
                                    <label class="form-label extra-small fw-bold text-muted mb-1">NIP / NPK / NIY
                                        KEPSEK</label>
                                    <input type="text" name="nip_kepsek" id="inputNipKepsek"
                                        class="form-control rounded-3 py-2" value="<?= htmlspecialchars($nipKepsek) ?>"
                                        placeholder="Contoh: 197501012000031001">
                                </div>
                            </div>

                            <!-- Titimangsa Tanggal -->
                            <div class="mb-3">
                                <label class="form-label extra-small fw-bold text-muted mb-1">TITIMANGSA TANGGAL
                                    PENGESAHAN DOKUMEN</label>
                                <input type="text" name="titimangsa_dokumen" id="inputTitimangsa"
                                    class="form-control rounded-3 py-2" value="<?= htmlspecialchars($titimangsa) ?>"
                                    placeholder="Kosongkan untuk otomatis tanggal cetak hari ini (atau isi: 15 Juli 2026)">
                                <div class="form-text extra-small text-muted">
                                    Misal diisi <em>15 Juli 2026</em> agar tanggal pengesahan tertulis tetap pada awal
                                    tahun pelajaran.
                                </div>
                            </div>

                            <!-- Upload Stempel / TTD Digital (Opsional) -->
                            <div class="mb-2">
                                <label class="form-label extra-small fw-bold text-muted mb-1">STEMPEL / TTD DIGITAL
                                    KEPALA SEKOLAH (OPSIONAL)</label>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-3 border d-flex align-items-center justify-content-center p-2 bg-light flex-shrink-0"
                                        style="width: 70px; height: 70px;">
                                        <img src="<?= !empty($stempelPath) ? BASE_URL . $stempelPath : '' ?>"
                                            alt="Stempel" id="stempelPreviewImg"
                                            style="max-width: 100%; max-height: 100%; object-fit: contain; <?= empty($stempelPath) ? 'display:none;' : '' ?>">
                                        <span id="stempelPlaceholder" class="extra-small text-muted text-center"
                                            style="<?= !empty($stempelPath) ? 'display:none;' : '' ?>">
                                            Belum Ada
                                        </span>
                                    </div>
                                    <div>
                                        <input type="file" name="stempel_kepsek" id="inputStempelKepsek"
                                            class="form-control form-control-sm rounded-3"
                                            accept="image/png, image/jpeg, image/webp" onchange="previewStempel(this)">
                                        <div class="form-text extra-small text-muted">
                                            Format: PNG transparan (Maksimal 3MB). Muncul di atas tanda tangan kepala
                                            sekolah.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TOMBOL SIMPAN -->
                    <div class="card border-0 rounded-4 shadow-sm mb-4 p-3" style="background: #ffffff;">
                        <div class="d-flex align-items-center justify-content-between">
                            <a href="index.php" class="btn btn-light rounded-pill px-4 fw-bold text-secondary">
                                Batal
                            </a>
                            <button type="submit"
                                class="btn btn-primary rounded-pill px-5 py-2.5 fw-bold shadow-sm d-inline-flex align-items-center gap-2">
                                <i class="bi bi-save-fill"></i>
                                <span>Simpan Identitas Dokumen</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- KOLOM KANAN: LIVE INTERACTIVE PREVIEW KOP & TTD (5 COL) -->
            <div class="col-lg-5 col-12">
                <div class="sticky-top" style="top: 20px; z-index: 10;">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span
                            class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill extra-small fw-bold">
                            <i class="bi bi-eye-fill me-1"></i> LIVE INTERACTIVE PREVIEW
                        </span>
                        <small class="text-muted extra-small">Tampilan nyata di lembar A4</small>
                    </div>

                    <!-- KERTAS PREVIEW PUTIH -->
                    <div class="card border rounded-4 shadow-sm p-4 bg-white"
                        style="border-color: #cbd5e1 !important; font-family: 'Times New Roman', Times, serif; color: #000; font-size: 10.5pt; line-height: 1.35;">

                        <!-- PREVIEW KOP SURAT SIMETRIS -->
                        <div>
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="width: 60px; vertical-align: middle; text-align: left; padding: 0;">
                                        <img src="<?= !empty($logoPath) ? BASE_URL . $logoPath : '../../assets/img/logo.png' ?>"
                                            alt="Logo" id="livePrevLogo"
                                            style="width: 55px; height: 55px; object-fit: contain; display: block;"
                                            onerror="this.src='../../assets/img/logo.png'">
                                    </td>
                                    <td style="vertical-align: middle; text-align: center; padding: 0 8px;">
                                        <div style="font-size: 11pt; font-weight: bold; text-transform: uppercase; margin-bottom: 2px; letter-spacing: 0.5px; line-height: 1.25;"
                                            id="livePrevNamaSekolah">
                                            <?= htmlspecialchars($namaSekolah) ?>
                                        </div>
                                        <div style="font-size: 7.5pt; color: #111; line-height: 1.3;"
                                            id="livePrevAlamat">
                                            <?= htmlspecialchars($alamatSekolah) ?>
                                        </div>
                                        <div style="font-size: 7.5pt; color: #333; line-height: 1.3; margin-top: 2px;"
                                            id="livePrevKontak">
                                            Telp: <?= htmlspecialchars($teleponSekolah) ?> | Email:
                                            <?= htmlspecialchars($emailSekolah) ?>
                                        </div>
                                    </td>
                                    <td style="width: 60px; padding: 0;">
                                        <!-- Penyeimbang visual -->
                                    </td>
                                </tr>
                            </table>
                            <div style="margin-top: 8px; margin-bottom: 12px;">
                                <div style="border-top: 2.5px solid #000; margin-bottom: 1.5px;"></div>
                                <div style="border-top: 0.8px solid #000;"></div>
                            </div>
                        </div>

                        <!-- BODY CONTOH PREVIEW -->
                        <div
                            style="text-align: center; font-weight: bold; font-size: 10pt; text-transform: uppercase; margin: 8px 0 10px 0;">
                            MODUL AJAR KURIKULUM MERDEKA
                        </div>
                        <div
                            style="font-size: 8pt; color: #555; text-align: justify; margin-bottom: 15px; font-style: italic;">
                            [... Isi Capaian Pembelajaran, Alur Tujuan (ATP), Sintaks Pembelajaran, dan Asesmen ...]
                        </div>

                        <!-- PREVIEW LEMBAR TANDA TANGAN -->
                        <div style="margin-top: 20px; padding-top: 10px; border-top: 1px dashed #e2e8f0;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 8.5pt;">
                                <tr>
                                    <td style="width: 50%; vertical-align: top;">
                                        Mengetahui,<br>
                                        <span id="livePrevJabatanKepsek">Kepala Sekolah</span>
                                        <div style="height: 40px; position: relative; margin: 4px 0;">
                                            <img src="<?= !empty($stempelPath) ? BASE_URL . $stempelPath : '' ?>"
                                                alt="Stempel" id="livePrevStempel"
                                                style="max-height: 40px; position: absolute; left: 10px; top: -5px; opacity: 0.85; <?= empty($stempelPath) ? 'display:none;' : '' ?>">
                                        </div>
                                        <strong><u
                                                id="livePrevNamaKepsek"><?= htmlspecialchars($namaKepsek) ?></u></strong><br>
                                        NIP. <span id="livePrevNipKepsek"><?= htmlspecialchars($nipKepsek) ?></span>
                                    </td>
                                    <td style="width: 50%; vertical-align: top; text-align: right;">
                                        <span id="livePrevKota"><?= htmlspecialchars($kotaSekolah) ?></span>,
                                        <span
                                            id="livePrevTanggal"><?= !empty($titimangsa) ? htmlspecialchars($titimangsa) : date('j F Y') ?></span><br>
                                        Guru Mata Pelajaran
                                        <div style="height: 40px; margin: 4px 0;"></div>
                                        <strong><u>[Nama Guru Pengampu]</u></strong><br>
                                        NIP. [NIP Guru]
                                    </td>
                                </tr>
                            </table>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    // Live Image Preview Logo
    function previewLogo(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                document.getElementById('logoPreviewImg').src = e.target.result;
                document.getElementById('livePrevLogo').src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Live Image Preview Stempel
    function previewStempel(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                const img = document.getElementById('stempelPreviewImg');
                const liveImg = document.getElementById('livePrevStempel');
                const placeholder = document.getElementById('stempelPlaceholder');
                img.src = e.target.result;
                img.style.display = 'block';
                if (placeholder) placeholder.style.display = 'none';
                if (liveImg) {
                    liveImg.src = e.target.result;
                    liveImg.style.display = 'block';
                }
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Live Text Sync ke Preview Kertas A4
    document.addEventListener('DOMContentLoaded', function () {
        const syncText = (inputId, prevId) => {
            const el = document.getElementById(inputId);
            const prev = document.getElementById(prevId);
            if (el && prev) {
                el.addEventListener('input', () => {
                    prev.textContent = el.value || '—';
                });
            }
        };

        syncText('inputNamaSekolah', 'livePrevNamaSekolah');
        syncText('inputAlamatSekolah', 'livePrevAlamat');
        syncText('inputKotaSekolah', 'livePrevKota');
        syncText('inputNamaKepsek', 'livePrevNamaKepsek');
        syncText('inputNipKepsek', 'livePrevNipKepsek');

        // Update jabatan kepsek preview
        const elNamaSekolah = document.getElementById('inputNamaSekolah');
        if (elNamaSekolah) {
            elNamaSekolah.addEventListener('input', () => {
                const val = elNamaSekolah.value.trim();
                const jabPrev = document.getElementById('livePrevJabatanKepsek');
                if (jabPrev) {
                    jabPrev.textContent = 'Kepala Sekolah';
                }
            });
        }

        // Update kontak bar
        const updateKontak = () => {
            const telp = document.getElementById('inputTelpSekolah')?.value || '';
            const email = document.getElementById('inputEmailSekolah')?.value || '';
            document.getElementById('livePrevKontak').textContent = `Telp: ${telp} | Email: ${email}`;
        };
        document.getElementById('inputTelpSekolah')?.addEventListener('input', updateKontak);
        document.getElementById('inputEmailSekolah')?.addEventListener('input', updateKontak);

        // Update titimangsa tanggal
        const elTgl = document.getElementById('inputTitimangsa');
        if (elTgl) {
            elTgl.addEventListener('input', () => {
                document.getElementById('livePrevTanggal').textContent = elTgl.value.trim() !== '' ? elTgl.value : '<?= date('j F Y') ?>';
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>