<?php
/**
 * PENGATURAN - Logo & Identitas Sekolah
 */
$pageTitle = 'Pengaturan';
$activePage = 'pengaturan';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

// Proses simpan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update text settings
    $fields = ['app_name', 'nama_sekolah', 'alamat_sekolah', 'telepon_sekolah', 'kota_sekolah', 'nama_kepala_sekolah', 'kwitansi_header', 'kwitansi_footer', 'pdf_judul', 'pdf_footer', 'wa_share_template', 'theme_color', 'login_bg_color'];
    foreach ($fields as $f) {
        if (isset($_POST[$f])) {
            updateSetting($f, trim($_POST[$f]));
        }
    }

    // Upload logo
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo'];
        $allowedTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        if (!in_array($file['type'], $allowedTypes)) {
            redirect('index.php', 'danger', 'Format file harus PNG, JPG, GIF, WebP, atau SVG.');
        }
        if ($file['size'] > $maxSize) {
            redirect('index.php', 'danger', 'Ukuran file maksimal 2MB.');
        }

        // Simpan file logo
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'logo_' . time() . '.' . $ext;
        $uploadDir = __DIR__ . '/../../assets/img/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0755, true);
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // Hapus logo lama jika ada
            $oldLogo = getSetting('logo_path', '');
            if ($oldLogo) {
                $oldFile = $_SERVER['DOCUMENT_ROOT'] . $oldLogo;
                if (file_exists($oldFile))
                    @unlink($oldFile);
            }
            updateSetting('logo_path', BASE_URL . '/assets/img/' . $filename);
        } else {
            redirect('index.php', 'danger', 'Gagal mengupload logo.');
        }
    }

    redirect('index.php', 'success', 'Pengaturan berhasil disimpan.');
}

// Ambil nilai settings saat ini
$appName = getSetting('app_name', APP_NAME);
$namaSekolah = getSetting('nama_sekolah', SCHOOL_NAME);
$alamatSekolah = getSetting('alamat_sekolah', '');
$teleponSekolah = getSetting('telepon_sekolah', '');
$logoPath = getSetting('logo_path', '');
$kwitansiHeader = getSetting('kwitansi_header', 'KWITANSI PEMBAYARAN');
$kwitansiFooter = getSetting('kwitansi_footer', 'Terima kasih atas pembayarannya');
$kotaSekolah = getSetting('kota_sekolah', '');
$namaKepala = getSetting('nama_kepala_sekolah', '');
$pdfJudul = getSetting('pdf_judul', 'BUKU KAS UMUM (PETTY CASH)');
$pdfFooter = getSetting('pdf_footer', '');
$waShareTemplate = getSetting('wa_share_template', "Halo Bapak/Ibu Wali Murid {nama},\n\nBerikut adalah rincian pembayaran Anda di {sekolah}:\n\n*{judul}*\nNo: {no}\nTotal: *{nominal}*\nStatus: *LUNAS*\n\nLink Kwitansi Digital: {link}\n\nTerima kasih.");
$themeColor = getSetting('theme_color', '#0dcaf0');
$loginBgColor = getSetting('login_bg_color', '#0f172a');

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row g-4">
    <!-- Kolom Kiri: Identitas & Logo -->
    <div class="col-md-6">
        <div class="form-card">
            <h5 class="mb-3"><i class="bi bi-building"></i> Identitas Sekolah</h5>
            <form method="POST" enctype="multipart/form-data">
                <!-- Logo Preview -->
                <div class="text-center mb-3">
                    <div
                        style="width:120px;height:120px;margin:0 auto;border:2px dashed var(--border);border-radius:16px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:var(--light)">
                        <?php if ($logoPath): ?>
                            <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo"
                                style="max-width:100%;max-height:100%;object-fit:contain" id="logoPreview">
                        <?php else: ?>
                            <span style="font-size:3rem" id="logoPreview">🏫</span>
                        <?php endif; ?>
                    </div>
                    <div class="mt-2">
                        <label class="btn btn-sm btn-outline-primary" style="cursor:pointer">
                            <i class="bi bi-camera"></i> Ganti Logo
                            <input type="file" name="logo" accept="image/*" hidden onchange="previewLogo(this)">
                        </label>
                        <?php if ($logoPath): ?>
                            <a href="hapus-logo.php" class="btn btn-sm btn-outline-danger"
                                onclick="return confirm('Hapus logo?')"><i class="bi bi-trash"></i></a>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted d-block mt-1">PNG/JPG/SVG, maks 2MB</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nama Aplikasi</label>
                    <input type="text" name="app_name" class="form-control" value="<?= htmlspecialchars($appName) ?>"
                        placeholder="Contoh: Edu-App">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label d-block">Warna Tema Utama</label>
                        <div class="d-flex align-items-center gap-3 p-2 border rounded bg-white">
                            <input type="color" name="theme_color" class="form-control form-control-color"
                                value="<?= htmlspecialchars($themeColor) ?>"
                                style="width: 60px; height: 40px; cursor: pointer;">
                            <div>
                                <small class="text-muted d-block">Tombol & Ikon</small>
                                <code class="small"><?= htmlspecialchars($themeColor) ?></code>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label d-block">Warna Latar Login</label>
                        <div class="d-flex align-items-center gap-3 p-2 border rounded bg-white">
                            <input type="color" name="login_bg_color" class="form-control form-control-color"
                                value="<?= htmlspecialchars($loginBgColor) ?>"
                                style="width: 60px; height: 40px; cursor: pointer;">
                            <div>
                                <small class="text-muted d-block">Background sisi kiri</small>
                                <code class="small"><?= htmlspecialchars($loginBgColor) ?></code>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Sekolah / Instansi</label>
                    <input type="text" name="nama_sekolah" class="form-control"
                        value="<?= htmlspecialchars($namaSekolah) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Alamat Sekolah</label>
                    <input type="text" name="alamat_sekolah" class="form-control"
                        value="<?= htmlspecialchars($alamatSekolah) ?>">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Telepon</label>
                        <input type="text" name="telepon_sekolah" class="form-control"
                            value="<?= htmlspecialchars($teleponSekolah) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kota</label>
                        <input type="text" name="kota_sekolah" class="form-control"
                            value="<?= htmlspecialchars($kotaSekolah) ?>" placeholder="Contoh: Kota Bekasi">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Kepala Sekolah</label>
                    <input type="text" name="nama_kepala_sekolah" class="form-control"
                        value="<?= htmlspecialchars($namaKepala) ?>" placeholder="Untuk tanda tangan laporan">
                </div>

                <hr>
                <h6 class="mb-3"><i class="bi bi-receipt"></i> Pengaturan Kwitansi</h6>
                <div class="mb-3">
                    <label class="form-label">Judul Kwitansi</label>
                    <input type="text" name="kwitansi_header" class="form-control"
                        value="<?= htmlspecialchars($kwitansiHeader) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Teks Footer Kwitansi</label>
                    <input type="text" name="kwitansi_footer" class="form-control"
                        value="<?= htmlspecialchars($kwitansiFooter) ?>">
                </div>

                <hr>
                <h6 class="mb-3"><i class="bi bi-file-earmark-pdf"></i> Pengaturan Cetak PDF (Buku Kas)</h6>
                <div class="mb-3">
                    <label class="form-label">Judul Laporan PDF</label>
                    <input type="text" name="pdf_judul" class="form-control" value="<?= htmlspecialchars($pdfJudul) ?>"
                        placeholder="BUKU KAS UMUM (PETTY CASH)">
                </div>
                <div class="mb-3">
                    <label class="form-label">Catatan Kaki PDF (Opsional)</label>
                    <input type="text" name="pdf_footer" class="form-control"
                        value="<?= htmlspecialchars($pdfFooter) ?>"
                        placeholder="Contoh: Laporan ini digenerate oleh sistem...">
                </div>

                <hr>
                <h6 class="mb-3"><i class="bi bi-whatsapp"></i> Template WhatsApp Share</h6>
                <div class="mb-3">
                    <label class="form-label">Format Pesan WA</label>
                    <textarea name="wa_share_template" class="form-control"
                        rows="6"><?= htmlspecialchars($waShareTemplate) ?></textarea>
                    <div class="form-text mt-2" style="font-size: 0.75rem;">
                        Gunakan placeholder berikut:<br>
                        <code>{nama}</code> : Nama Siswa<br>
                        <code>{sekolah}</code> : Nama Sekolah<br>
                        <code>{judul}</code> : Jenis Pembayaran<br>
                        <code>{no}</code> : Nomor Kwitansi<br>
                        <code>{nominal}</code> : Jumlah Bayar<br>
                        <code>{link}</code> : Link Kwitansi Online
                    </div>
                </div>

                <button type="submit" class="btn-primary-custom w-100 mb-3"><i class="bi bi-save"></i> Simpan
                    Pengaturan</button>
            </form>

            <hr class="my-4">
            <h6 class="mb-2"><i class="bi bi-arrow-repeat"></i> Sinkronisasi Sistem</h6>
            <p class="text-muted small">Jika terdapat ketidakcocokan data siswa, e-money kantin, atau akun Portal Orang
                Tua, lakukan penyelarasan database di bawah ini.</p>
            <a href="<?= BASE_URL ?>/sync_all_data.php" class="btn btn-outline-warning w-100 btn-sm"><i
                    class="bi bi-database-gear"></i> Jalankan Sinkronisasi Database</a>
        </div>
    </div>

    <!-- Kolom Kanan: Preview Kwitansi -->
    <div class="col-md-6">
        <div class="form-card">
            <h5 class="mb-3"><i class="bi bi-eye"></i> Preview Kwitansi</h5>
            <div class="receipt" style="border:1px solid #ddd;border-radius:8px;font-size:.8rem">
                <div class="receipt-header"
                    style="display:flex;align-items:center;gap:12px;justify-content:center;text-align:left">
                    <div style="flex-shrink:0">
                        <?= getLogoHtml(50) ?>
                    </div>
                    <div>
                        <h6 style="margin:0;font-weight:800;font-size:.85rem" id="prevNamaSekolah">
                            <?= htmlspecialchars($namaSekolah) ?>
                        </h6>
                        <p style="font-size:.65rem;color:#666;margin:0" id="prevAlamat">
                            <?= htmlspecialchars($alamatSekolah) ?>
                        </p>
                        <p style="font-size:.65rem;color:#666;margin:0" id="prevTelp">
                            <?= htmlspecialchars($teleponSekolah) ?>
                        </p>
                    </div>
                </div>
                <p style="text-align:center;font-size:.75rem;font-weight:700;margin:.75rem 0;letter-spacing:.05em"
                    id="prevHeader"><?= htmlspecialchars($kwitansiHeader) ?></p>
                <div class="receipt-row"><span>Tanggal</span><strong><?= formatTanggal(date('Y-m-d')) ?></strong></div>
                <div class="receipt-row"><span>NIS</span><strong>2024001</strong></div>
                <div class="receipt-row"><span>Nama</span><strong>Ahmad Fauzan</strong></div>
                <div class="receipt-row"><span>Kelas</span><strong>VII-A</strong></div>
                <div class="receipt-row"><span>Pembayaran</span><strong>SPP Januari 2026</strong></div>
                <div class="receipt-row receipt-total"><span>TOTAL</span><strong style="color:#198754">Rp
                        350.000</strong></div>
                <div style="text-align:center;font-size:.7rem;color:#999;margin-top:1rem">
                    <p>Diterima oleh: <strong>Bendahara Sekolah</strong></p>
                    <p id="prevFooter"><?= htmlspecialchars($kwitansiFooter) ?></p>
                </div>
            </div>
        </div>

            </div>
        </div>
    </div>
</div>

<!-- SECTION BACKUP & RESTORE DATABASE (FULL WIDTH) -->
<div class="row mt-4" id="backup-section">
    <div class="col-12">
        <div class="form-card" style="border: 2px solid #3b82f6; background: #ffffff;">
            <h5 class="mb-3 text-primary fw-bold"><i class="bi bi-database-down"></i> Backup & Restore Database</h5>
            <p class="text-muted small mb-4">Gunakan fitur ini untuk membuat cadangan data seluruh aplikasi (.sql) atau mengembalikan data dari file backup sebelumnya.</p>
            
            <div class="row g-3">
                <!-- 1. BACKUP -->
                <div class="col-md-6">
                    <div class="p-3 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                        <div>
                            <strong class="d-block text-dark mb-1 fs-6"><i class="bi bi-download text-success"></i> Backup Data (Export SQL)</strong>
                            <small class="text-muted d-block mb-3">Unduh seluruh isi database aplikasi saat ini (seluruh tabel & data) ke file `.sql`.</small>
                        </div>
                        <a href="backup.php" class="btn btn-success fw-bold w-100 py-2">
                            <i class="bi bi-cloud-arrow-down-fill me-1"></i> Download Backup Database (.sql)
                        </a>
                    </div>
                </div>

                <!-- 2. RESTORE -->
                <div class="col-md-6">
                    <div class="p-3 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                        <div>
                            <strong class="d-block text-dark mb-1 fs-6"><i class="bi bi-upload text-primary"></i> Restore Data (Import SQL)</strong>
                            <small class="text-muted d-block mb-2">Upload file `.sql` hasil backup untuk mengembalikan seluruh data ke sistem.</small>
                            <div class="alert alert-warning py-1.5 px-2.5 small mb-2" style="font-size: 0.78rem;">
                                <i class="bi bi-exclamation-triangle-fill"></i> <strong>Perhatian:</strong> Data lama di database akan diperbarui dengan isi file SQL ini.
                            </div>
                        </div>
                        <form action="restore.php" method="POST" enctype="multipart/form-data" onsubmit="return confirm('⚠️ APAKAH ANDA YAKIN?\n\nProses restore akan mengganti data saat ini dengan data dari file backup SQL yang dipilih. Lanjutkan?')">
                            <div class="input-group input-group-sm">
                                <input type="file" name="backup_file" class="form-control" accept=".sql" required>
                                <button class="btn btn-primary fw-bold px-3" type="submit">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Restore
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Preview logo sebelum upload
    function previewLogo(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                const container = document.getElementById('logoPreview').parentElement;
                container.innerHTML = '<img src="' + e.target.result + '" alt="Preview" style="max-width:100%;max-height:100%;object-fit:contain" id="logoPreview">';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Live preview saat mengetik
    document.querySelector('[name=nama_sekolah]').addEventListener('input', e => document.getElementById('prevNamaSekolah').textContent = e.target.value);
    document.querySelector('[name=alamat_sekolah]').addEventListener('input', e => document.getElementById('prevAlamat').textContent = e.target.value);
    document.querySelector('[name=telepon_sekolah]').addEventListener('input', e => document.getElementById('prevTelp').textContent = e.target.value);
    document.querySelector('[name=kwitansi_header]').addEventListener('input', e => document.getElementById('prevHeader').textContent = e.target.value);
    document.querySelector('[name=kwitansi_footer]').addEventListener('input', e => document.getElementById('prevFooter').textContent = e.target.value);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>