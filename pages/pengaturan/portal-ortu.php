<?php
/**
 * PENGATURAN PORTAL ORANG TUA - Edu-App
 * Kelola visibilitas menu/fitur portal, nomor WhatsApp Wali Kelas, dan preferensi portal ortu.
 */
$pageTitle = 'Pengaturan Portal Orang Tua';
$activePage = 'pengaturan-portal';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('pengaturan');

$pdo = getConnection();

// Daftar seluruh toggle visibilitas portal
$toggleKeys = [
    // Tab Navigasi Bawah
    'portal_tab_kesantrian',
    'portal_tab_kantin',
    'portal_tab_akademik',
    'portal_tab_profil',

    // Fitur Beranda
    'portal_hero_saldo',
    'portal_btn_topup',
    'portal_akses_cepat',
    'portal_menu_tagihan',
    'portal_menu_nilai',
    'portal_menu_kantin',
    'portal_menu_kesantrian',
    'portal_tagihan_aktif',
    'portal_kantin_terakhir',
    'portal_tahfidz_beranda',

    // Fitur Kesantrian
    'portal_section_tahfidz',
    'portal_section_pelanggaran',
    'portal_section_perizinan',
    'portal_section_ibadah',

    // Fitur Kantin
    'portal_kantin_pengeluaran',
    'portal_kantin_riwayat',
    'portal_kantin_mutasi',

    // Fitur Akademik
    'portal_akademik_rapor',
    'portal_akademik_presensi',
    'portal_akademik_jadwal',
    'portal_hubungi_wali_kelas',
    'portal_tampilkan_predikat_mapel',
    'portal_show_sumatif_1',
    'portal_show_sumatif_2',
    'portal_show_sumatif_3',
    'portal_show_sumatif_4',
    'portal_show_materi_sumatif',
    'portal_show_ats',
    'portal_show_aas',
    'portal_show_nilai_akhir',
    'portal_show_predikat_mapel',
    'portal_show_kkm',
    'portal_show_deskripsi_catatan',
    'portal_show_hero_akademik',

    // Fitur Profil
    'portal_profil_barcode',
    'portal_profil_ubah_foto',
    'portal_profil_password',
];

// Proses Simpan Pengaturan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_portal_settings') {
    // 1. Simpan Toggle Visibilitas Menu & Fitur (1 jika dicentang, 0 jika tidak)
    foreach ($toggleKeys as $key) {
        $val = isset($_POST[$key]) ? '1' : '0';
        updateSetting($key, $val);
    }

    // Sinkronkan sub-opsi terkait nilai
    if (isset($_POST['portal_show_sumatif_1'])) {
        updateSetting('portal_show_sumatif_2', '1');
        updateSetting('portal_show_sumatif_3', '1');
        updateSetting('portal_show_sumatif_4', '1');
        updateSetting('portal_show_materi_sumatif', '1');
        updateSetting('portal_show_nilai_akhir', '1');
    } else {
        updateSetting('portal_show_sumatif_2', '0');
        updateSetting('portal_show_sumatif_3', '0');
        updateSetting('portal_show_sumatif_4', '0');
        updateSetting('portal_show_materi_sumatif', '0');
    }
    if (isset($_POST['portal_show_ats'])) {
        updateSetting('portal_show_aas', '1');
    } else {
        updateSetting('portal_show_aas', '0');
    }
    if (isset($_POST['portal_show_deskripsi_catatan'])) {
        updateSetting('portal_show_kkm', '1');
    } else {
        updateSetting('portal_show_kkm', '0');
    }

    // 2. Simpan Pengaturan WhatsApp & Rekening Transfer
    $textFields = [
        'portal_wa_wali_default',
        'portal_wa_template',
        'portal_greeting_text',
        'portal_banner_info',
        'kantin_rekening_info',
        'tunggakan_rekening_info',
    ];

    foreach ($textFields as $tf) {
        if (isset($_POST[$tf])) {
            updateSetting($tf, trim($_POST[$tf]));
        }
    }

    // 3. Simpan Nomor WhatsApp Khusus per Kelas (jika ada input khusus)
    if (isset($_POST['wa_kelas']) && is_array($_POST['wa_kelas'])) {
        foreach ($_POST['wa_kelas'] as $kelasId => $noWa) {
            $kId = (int) $kelasId;
            $cleanWa = trim($noWa);
            updateSetting("portal_wa_kelas_{$kId}", $cleanWa);
        }
    }

    // 4. Simpan Pengaturan Pop-up Pengumuman Portal Ortu
    $popupActive = isset($_POST['portal_popup_active']) ? '1' : '0';
    updateSetting('portal_popup_active', $popupActive);

    $popupFields = [
        'portal_popup_type' => $_POST['portal_popup_type'] ?? 'text',
        'portal_popup_title' => trim($_POST['portal_popup_title'] ?? ''),
        'portal_popup_content' => trim($_POST['portal_popup_content'] ?? ''),
        'portal_popup_file_name' => trim($_POST['portal_popup_file_name'] ?? ''),
        'portal_popup_btn_text' => trim($_POST['portal_popup_btn_text'] ?? ''),
        'portal_popup_btn_link' => trim($_POST['portal_popup_btn_link'] ?? ''),
        'portal_popup_frequency' => $_POST['portal_popup_frequency'] ?? 'session',
    ];

    foreach ($popupFields as $k => $val) {
        updateSetting($k, $val);
    }

    // Hapus file pop-up jika dipilih
    if (isset($_POST['delete_popup_file']) && $_POST['delete_popup_file'] === '1') {
        $oldFile = getSetting('portal_popup_file', '');
        if (!empty($oldFile)) {
            $absOld = __DIR__ . '/../../' . ltrim($oldFile, '/');
            if (file_exists($absOld)) {
                @unlink($absOld);
            }
        }
        updateSetting('portal_popup_file', '');
        updateSetting('portal_popup_file_name', '');
    }

    // Unggah file pop-up baru (Gambar / Dokumen PDF)
    if (isset($_FILES['portal_popup_file']) && $_FILES['portal_popup_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['portal_popup_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

        if (in_array($ext, $allowedExts)) {
            $uploadDir = __DIR__ . '/../../assets/uploads/portal/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Hapus file lama jika ada
            $oldFile = getSetting('portal_popup_file', '');
            if (!empty($oldFile)) {
                $absOld = __DIR__ . '/../../' . ltrim($oldFile, '/');
                if (file_exists($absOld)) {
                    @unlink($absOld);
                }
            }

            $newFileName = 'popup_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $destination = $uploadDir . $newFileName;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                updateSetting('portal_popup_file', 'assets/uploads/portal/' . $newFileName);
                $originalClientName = trim(htmlspecialchars_decode($file['name'], ENT_QUOTES));
                updateSetting('portal_popup_file_name', $originalClientName);
            }
        }
    }

    // Perbarui timestamp versi pop-up
    updateSetting('portal_popup_updated_at', (string) time());

    redirect('portal-ortu.php', 'success', 'Pengaturan Portal Orang Tua berhasil diperbarui.');
}

// Ambil Seluruh Data Pengaturan Portal Saat Ini
$settings = [];
foreach ($toggleKeys as $tk) {
    $settings[$tk] = getSetting($tk, '1');
}

// Pengaturan WhatsApp & Rekening
$settings['portal_wa_wali_default'] = getSetting('portal_wa_wali_default', getSetting('telepon_sekolah', ''));
$settings['portal_wa_template'] = getSetting('portal_wa_template', "Assalamu'alaikum Bapak/Ibu {nama_wali},\n\nSaya orang tua dari *{nama_siswa}* (Kelas *{nama_kelas}*). Ingin berkonsultasi mengenai perkembangan akademik anak kami di sekolah.\n\nTerima kasih.");
$settings['portal_greeting_text'] = getSetting('portal_greeting_text', 'Ahlan wa Sahlan');
$settings['portal_banner_info'] = getSetting('portal_banner_info', '');
$settings['kantin_rekening_info'] = getSetting('kantin_rekening_info', getSetting('tunggakan_rekening_info', "BSI (Bank Syariah Indonesia) No. 7148123456\na.n. SMP Islamic School Minhaj Al-Ilmi"));
$settings['tunggakan_rekening_info'] = getSetting('tunggakan_rekening_info', "BSI (Bank Syariah Indonesia) No. 7148123456\na.n. SMP Islamic School Minhaj Al-Ilmi");

// Pengaturan Pop-up Pengumuman Portal
$settings['portal_popup_active'] = getSetting('portal_popup_active', '0');
$settings['portal_popup_type'] = getSetting('portal_popup_type', 'text');
$settings['portal_popup_title'] = getSetting('portal_popup_title', 'Maklumat / Pengumuman Penting');
$settings['portal_popup_content'] = getSetting('portal_popup_content', '');
$settings['portal_popup_file'] = getSetting('portal_popup_file', '');
$settings['portal_popup_file_name'] = getSetting('portal_popup_file_name', '');
$settings['portal_popup_btn_text'] = getSetting('portal_popup_btn_text', 'Buka Dokumen');
$settings['portal_popup_btn_link'] = getSetting('portal_popup_btn_link', '');
$settings['portal_popup_frequency'] = getSetting('portal_popup_frequency', 'session');
$settings['portal_popup_updated_at'] = getSetting('portal_popup_updated_at', '0');

// Ambil Daftar Kelas beserta Wali Kelas & Nomor HPnya untuk Tabel WhatsApp
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
    ORDER BY k.tingkat ASC, k.nama_kelas ASC
")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- HERO HEADER -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2"
                style="background: rgba(2, 132, 199, 0.1); border: 1px solid rgba(2, 132, 199, 0.25); color: #0284c7;">
                <i class="bi bi-phone-fill fs-6"></i>
                <span class="small fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;">MODUL PORTAL ORTU</span>
            </div>
            <h3 class="fw-bold text-dark m-0" style="font-family: 'Outfit', sans-serif;">Pengaturan Portal Orang Tua
            </h3>
            <p class="text-muted small m-0">Kontrol penuh visibilitas menu santri, WhatsApp wali kelas, dan preferensi
                portal ortu.</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="<?= BASE_URL ?>/pages/pengaturan/index.php"
                class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Pengaturan Umum
            </a>
            <a href="<?= BASE_URL ?>/portal-ortu.php" target="_blank"
                class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                <i class="bi bi-box-arrow-up-right me-1"></i> Buka Portal Ortu
            </a>
        </div>
    </div>

    <!-- MAIN SETTINGS FORM -->
    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_portal_settings">

        <!-- NAV TABS -->
        <ul class="nav nav-pills mb-4 gap-2 bg-white p-2 rounded-4 shadow-sm border" id="portalSettingsTab"
            role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active rounded-pill fw-semibold px-4 py-2" id="tab-visibilitas-btn"
                    data-bs-toggle="pill" data-bs-target="#tab-visibilitas" type="button" role="tab">
                    <i class="bi bi-toggle2-on me-2"></i> Visibilitas Menu & Fitur
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill fw-semibold px-4 py-2" id="tab-whatsapp-btn" data-bs-toggle="pill"
                    data-bs-target="#tab-whatsapp" type="button" role="tab">
                    <i class="bi bi-whatsapp me-2 text-success"></i> WhatsApp Wali Kelas
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill fw-semibold px-4 py-2" id="tab-tampilan-btn" data-bs-toggle="pill"
                    data-bs-target="#tab-tampilan" type="button" role="tab">
                    <i class="bi bi-palette2 me-2 text-info"></i> Teks & Preferensi
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill fw-semibold px-4 py-2" id="tab-popup-btn" data-bs-toggle="pill"
                    data-bs-target="#tab-popup" type="button" role="tab">
                    <i class="bi bi-megaphone-fill me-2 text-warning"></i> Pop-up Pengumuman
                    <?php if (($settings['portal_popup_active'] ?? '0') === '1'): ?>
                        <span class="badge bg-success ms-1 px-2 py-0.5 rounded-pill"
                            style="font-size: 0.68rem;">Aktif</span>
                    <?php endif; ?>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="portalSettingsTabContent">
            <!-- ============================================================== -->
            <!-- TAB 1: VISIBILITAS MENU & FITUR PORTAL ORTU -->
            <!-- ============================================================== -->
            <div class="tab-pane fade show active" id="tab-visibilitas" role="tabpanel">
                <div class="row g-4">
                    <!-- 1. TAB BAR NAVIGASI BAWAH -->
                    <div class="col-lg-6">
                        <div class="card border-0 rounded-4 shadow-sm h-100 p-4 bg-white">
                            <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                                <div class="bg-primary-subtle text-primary p-2 rounded-3">
                                    <i class="bi bi-segmented-nav fs-5"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-0 text-dark">Tab Navigasi Utama Bawah</h5>
                                    <small class="text-muted">Aktifkan/nonaktifkan tab utama di bagian bawah layar
                                        HP</small>
                                </div>
                            </div>

                            <div class="d-flex flex-column gap-3">
                                <!-- Beranda (Always active) -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-house-door-fill text-primary fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Tab Beranda</div>
                                            <small class="text-muted">Halaman utama ringkasan profil & tagihan (Wajib
                                                aktif)</small>
                                        </div>
                                    </div>
                                    <span class="badge bg-primary rounded-pill px-3 py-2">Selalu Aktif</span>
                                </div>

                                <!-- Tab Kesantrian -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-moon-stars-fill text-info fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Tab Kesantrian</div>
                                            <small class="text-muted">Menu hafalan tahfidz, pelanggaran, perizinan, dan
                                                ibadah</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_tab_kesantrian"
                                            value="1" <?= $settings['portal_tab_kesantrian'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Tab Kantin -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-cup-hot-fill text-warning fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Tab Kantin</div>
                                            <small class="text-muted">Menu saldo kantin, riwayat jajan, & topup
                                                online</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_tab_kantin"
                                            value="1" <?= $settings['portal_tab_kantin'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Tab Akademik -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-journal-bookmark-fill text-success fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Tab Akademik</div>
                                            <small class="text-muted">Menu nilai rapor PTS/PAS, presensi & kontak wali
                                                kelas</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_tab_akademik"
                                            value="1" <?= $settings['portal_tab_akademik'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Tab Profil -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-person-fill text-secondary fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Tab Profil Santri</div>
                                            <small class="text-muted">Biodata santri, kartu barcode, dan ubah password
                                                akun</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_tab_profil"
                                            value="1" <?= $settings['portal_tab_profil'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. FITUR & MODUL BERANDA UTAMA -->
                    <div class="col-lg-6">
                        <div class="card border-0 rounded-4 shadow-sm h-100 p-4 bg-white">
                            <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                                <div class="bg-primary-subtle text-primary p-2 rounded-3">
                                    <i class="bi bi-grid-fill fs-5"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-0 text-dark">Fitur &amp; Modul Beranda</h5>
                                    <small class="text-muted">Kelola shortcut akses cepat & daftar ringkasan di
                                        beranda</small>
                                </div>
                            </div>

                            <div class="d-flex flex-column gap-3">
                                <!-- Menu Pintar Akses Cepat (Parent Grid) -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-grid-3x3-gap-fill text-primary fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Menu Grid Akses Cepat</div>
                                            <small class="text-muted">Tampilkan tombol-tombol shortcut di bawah kartu
                                                profil</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_akses_cepat"
                                            value="1" <?= $settings['portal_akses_cepat'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Shortcut Bayar Tagihan -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-2.5 px-3 rounded-3 bg-light-subtle border">
                                    <div class="d-flex align-items-center gap-2.5">
                                        <i class="bi bi-wallet2 text-success fs-5"></i>
                                        <div>
                                            <div class="fw-bold text-dark small">Shortcut Bayar Tagihan</div>
                                            <small class="text-muted extra-small">Tombol cepat ke form
                                                pembayaran</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-5 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_menu_tagihan"
                                            value="1" <?= $settings['portal_menu_tagihan'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Shortcut Lihat Nilai -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-2.5 px-3 rounded-3 bg-light-subtle border">
                                    <div class="d-flex align-items-center gap-2.5">
                                        <i class="bi bi-mortarboard-fill text-info fs-5"></i>
                                        <div>
                                            <div class="fw-bold text-dark small">Shortcut Lihat Nilai</div>
                                            <small class="text-muted extra-small">Tombol cepat ke nilai &
                                                akademik</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-5 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_menu_nilai"
                                            value="1" <?= $settings['portal_menu_nilai'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Shortcut Riwayat Kantin -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-2.5 px-3 rounded-3 bg-light-subtle border">
                                    <div class="d-flex align-items-center gap-2.5">
                                        <i class="bi bi-cup-hot-fill text-warning fs-5"></i>
                                        <div>
                                            <div class="fw-bold text-dark small">Shortcut Riwayat Kantin</div>
                                            <small class="text-muted extra-small">Tombol cepat ke dompet kantin</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-5 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_menu_kantin"
                                            value="1" <?= $settings['portal_menu_kantin'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Shortcut Kesantrian -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-2.5 px-3 rounded-3 bg-light-subtle border">
                                    <div class="d-flex align-items-center gap-2.5">
                                        <i class="bi bi-moon-stars-fill text-primary fs-5"></i>
                                        <div>
                                            <div class="fw-bold text-dark small">Shortcut Kesantrian</div>
                                            <small class="text-muted extra-small">Tombol cepat ke tahfidz &
                                                kedisiplinan</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-5 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_menu_kesantrian"
                                            value="1" <?= $settings['portal_menu_kesantrian'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Widget Tagihan Aktif -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-cash-coin text-warning fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Daftar Tagihan Aktif di Beranda</div>
                                            <small class="text-muted">Tampilkan tagihan SPP & kewajiban yang belum
                                                lunas</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_tagihan_aktif"
                                            value="1" <?= $settings['portal_tagihan_aktif'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Widget Transaksi Kantin Terakhir -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-receipt-cutoff text-secondary fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Transaksi Kantin Terakhir</div>
                                            <small class="text-muted">Tampilkan 3 struk jajan terakhir di
                                                beranda</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_kantin_terakhir"
                                            value="1" <?= $settings['portal_kantin_terakhir'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. FITUR KESANTRIAN & TAHFIDZ -->
                    <div class="col-lg-6">
                        <div class="card border-0 rounded-4 shadow-sm h-100 p-4 bg-white">
                            <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                                <div class="bg-info-subtle text-info p-2 rounded-3">
                                    <i class="bi bi-book-half fs-5"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-0 text-dark">Fitur Kesantrian &amp; Tahfidz</h5>
                                    <small class="text-muted">Pilih bagian yang ingin dimunculkan atau
                                        disembunyikan</small>
                                </div>
                            </div>

                            <div class="d-flex flex-column gap-3">
                                <!-- Tahfidz & Quran -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-book-half text-success fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Setoran Tahfidz &amp; Qur'an</div>
                                            <small class="text-muted">Progres juz, ziyadah, murojaah, dan ujian
                                                tahfidz</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_section_tahfidz"
                                            value="1" <?= $settings['portal_section_tahfidz'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Ringkasan Tahfidz di Beranda -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-stars text-primary fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Widget Tahfidz di Beranda</div>
                                            <small class="text-muted">Menampilkan cuplikan setoran tahfidz terakhir di
                                                beranda</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_tahfidz_beranda"
                                            value="1" <?= $settings['portal_tahfidz_beranda'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Pelanggaran & Kedisiplinan -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-shield-exclamation text-danger fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Catatan Pelanggaran &amp; Poin</div>
                                            <small class="text-muted">Rekap pelanggaran tata tertib dan poin
                                                santri</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox"
                                            name="portal_section_pelanggaran" value="1"
                                            <?= $settings['portal_section_pelanggaran'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Perizinan Santri -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-clipboard2-check text-info fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Riwayat Perizinan &amp; Pulang</div>
                                            <small class="text-muted">Status izin keluar / pulang santri</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_section_perizinan"
                                            value="1" <?= $settings['portal_section_perizinan'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Ibadah & Sholat Yaumiyah -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-check2-circle text-success fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Mutaba'ah Ibadah &amp; Sholat</div>
                                            <small class="text-muted">Laporan presensi sholat berjamaah & ibadah
                                                harian</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_section_ibadah"
                                            value="1" <?= $settings['portal_section_ibadah'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. KANTIN & SALDO SANTRI -->
                    <div class="col-lg-6">
                        <div class="card border-0 rounded-4 shadow-sm h-100 p-4 bg-white">
                            <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                                <div class="bg-warning-subtle text-warning p-2 rounded-3">
                                    <i class="bi bi-wallet2 fs-5"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-0 text-dark">Fitur Kantin &amp; Saldo Digital</h5>
                                    <small class="text-muted">Kelola fitur kartu saldo & riwayat jajan</small>
                                </div>
                            </div>

                            <div class="d-flex flex-column gap-3">
                                <!-- Card Saldo di Hero Card -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-credit-card-2-front-fill text-primary fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Card Saldo Kantin di Beranda</div>
                                            <small class="text-muted">Kotak saldo santri pada header profil
                                                beranda</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_hero_saldo"
                                            value="1" <?= $settings['portal_hero_saldo'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Tombol Top Up Saldo -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-plus-circle-fill text-success fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Tombol Isi Saldo Online</div>
                                            <small class="text-muted">Mengizinkan orang tua mengisi saldo kantin via
                                                transfer</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_btn_topup"
                                            value="1" <?= $settings['portal_btn_topup'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Kartu Hero Pengeluaran Minggu Ini -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-graph-up text-primary fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Kartu Hero Pengeluaran Minggu Ini</div>
                                            <small class="text-muted">Kartu biru grafik pengeluaran mingguan di tab
                                                kantin</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_kantin_pengeluaran"
                                            value="1" <?= ($settings['portal_kantin_pengeluaran'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Riwayat Belanja / Jajan Santri -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-receipt text-secondary fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Riwayat Belanja &amp; Jajan</div>
                                            <small class="text-muted">Rincian menu dan struk barang yang dibeli
                                                santri</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_kantin_riwayat"
                                            value="1" <?= $settings['portal_kantin_riwayat'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Mutasi Saldo Kantin -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-arrow-left-right text-info fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Mutasi Saldo (Top-Up &amp; Refund)</div>
                                            <small class="text-muted">Catatan keluar masuk saldo santri</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_kantin_mutasi"
                                            value="1" <?= $settings['portal_kantin_mutasi'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. AKADEMIK, NILAI & PROFIL -->
                    <div class="col-lg-6">
                        <div class="card border-0 rounded-4 shadow-sm h-100 p-4 bg-white">
                            <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                                <div class="bg-success-subtle text-success p-2 rounded-3">
                                    <i class="bi bi-mortarboard-fill fs-5"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-0 text-dark">Fitur Akademik &amp; Profil</h5>
                                    <small class="text-muted">Pengaturan nilai rapor, wali kelas & barcode</small>
                                </div>
                            </div>

                            <div class="d-flex flex-column gap-3">
                                <!-- Kartu Hero Ringkasan Akademik -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-award-fill text-primary fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Kartu Hero Ringkasan Akademik</div>
                                            <small class="text-muted">Kartu biru rata-rata & evaluasi belajar di tab
                                                akademik</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_show_hero_akademik"
                                            value="1" <?= ($settings['portal_show_hero_akademik'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Nilai Rapor PTS/PAS -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-file-earmark-text-fill text-primary fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Laporan Nilai Rapor &amp; Capaian</div>
                                            <small class="text-muted">Tampilkan ringkasan nilai sumatif dan
                                                PTS/PAS</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_akademik_rapor"
                                            value="1" <?= $settings['portal_akademik_rapor'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Nilai Formatif & Sumatif 1-4 -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-ui-checks text-info fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Nilai Sumatif / Formatif (1 - 4)</div>
                                            <small class="text-muted">Tampilkan kotak nilai sumatif 1 s/d 4 di dropdown
                                                mapel</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_show_sumatif_1"
                                            value="1" <?= ($settings['portal_show_sumatif_1'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Nilai Ujian ATS (UTS) & AAS (UAS) -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-journal-check text-warning fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Nilai Ujian Semester (ATS &amp; AAS)</div>
                                            <small class="text-muted">Tampilkan nilai asesmen tengah & akhir
                                                semester</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_show_ats" value="1"
                                            <?= ($settings['portal_show_ats'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Predikat Nilai Mapel (A / B / C / D) -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-award text-warning fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Predikat Nilai Mapel (A / B / C / D)</div>
                                            <small class="text-muted">Tampilkan badge predikat huruf pada rincian nilai
                                                tiap mata pelajaran</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox"
                                            name="portal_tampilkan_predikat_mapel" value="1"
                                            <?= $settings['portal_tampilkan_predikat_mapel'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- KKM & Catatan Guru -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-chat-quote-fill text-secondary fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Catatan Guru &amp; KKM Mapel</div>
                                            <small class="text-muted">Tampilkan keterangan KKM dan catatan guru
                                                pembimbing</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox"
                                            name="portal_show_deskripsi_catatan" value="1"
                                            <?= ($settings['portal_show_deskripsi_catatan'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Hubungi Wali Kelas (WhatsApp Direct) -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-whatsapp text-success fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Tombol Hubungi Wali Kelas</div>
                                            <small class="text-muted">Tautan langsung chat WhatsApp dengan Wali
                                                Kelas</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_hubungi_wali_kelas"
                                            value="1" <?= $settings['portal_hubungi_wali_kelas'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Barcode NIS Digital Siswa -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-upc-scan text-dark fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Barcode / Kartu Siswa Digital</div>
                                            <small class="text-muted">Tampilkan barcode NIS santri untuk scan kasir
                                                kantin</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_profil_barcode"
                                            value="1" <?= $settings['portal_profil_barcode'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>

                                <!-- Ubah Foto Profil Siswa -->
                                <div
                                    class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-camera-fill text-info fs-4"></i>
                                        <div>
                                            <div class="fw-bold text-dark">Izin Ubah Foto Profil Siswa</div>
                                            <small class="text-muted">Izinkan orang tua mengunggah foto profil
                                                santri</small>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="portal_profil_ubah_foto"
                                            value="1" <?= $settings['portal_profil_ubah_foto'] === '1' ? 'checked' : '' ?>>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- TAB 2: PENGATURAN WHATSAPP WALI KELAS -->
            <!-- ============================================================== -->
            <div class="tab-pane fade" id="tab-whatsapp" role="tabpanel">
                <div class="row g-4">
                    <!-- KOLOM KIRI: NOMOR DEFAULT & TEMPLATE PESAN -->
                    <div class="col-lg-5">
                        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
                            <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                                <div class="bg-success-subtle text-success p-2 rounded-3">
                                    <i class="bi bi-whatsapp fs-5"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-0 text-dark">Konfigurasi Chat WhatsApp</h5>
                                    <small class="text-muted">Atur nomor fallback dan isi pesan otomatis</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark small">Nomor WhatsApp Default /
                                    Sekolah</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i
                                            class="bi bi-telephone-fill text-success"></i></span>
                                    <input type="text" name="portal_wa_wali_default" class="form-control"
                                        placeholder="Contoh: 081234567890"
                                        value="<?= htmlspecialchars($settings['portal_wa_wali_default']) ?>">
                                </div>
                                <small class="text-muted" style="font-size: 0.76rem;">Digunakan jika kelas belum
                                    memiliki nomor Wali Kelas terdaftar.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark small">Template Pesan WhatsApp
                                    Otomatis</label>
                                <textarea name="portal_wa_template" class="form-control" rows="5"
                                    placeholder="Isi draft pesan awal orang tua..."><?= htmlspecialchars($settings['portal_wa_template']) ?></textarea>
                                <div class="mt-2 p-2 bg-light rounded-3 border" style="font-size: 0.74rem;">
                                    <span class="fw-bold text-dark d-block mb-1">Tag Variabel yang Tersedia:</span>
                                    <code>{nama_wali}</code>, <code>{nama_siswa}</code>, <code>{nama_kelas}</code>,
                                    <code>{nis}</code>, <code>{nama_sekolah}</code>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- KOLOM KANAN: DAFTAR NOMOR WA PER KELAS -->
                    <div class="col-lg-7">
                        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
                            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bg-primary-subtle text-primary p-2 rounded-3">
                                        <i class="bi bi-person-lines-fill fs-5"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold mb-0 text-dark">Nomor WhatsApp Wali Kelas per Kelas</h5>
                                        <small class="text-muted">Nomor kontak khusus yang langsung dituju orang tua
                                            tiap kelas</small>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="py-2.5">Kelas</th>
                                            <th class="py-2.5">Wali Kelas</th>
                                            <th class="py-2.5" style="width: 220px;">Nomor WhatsApp</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($kelasList)): ?>
                                            <tr>
                                                <td colspan="3" class="text-center py-4 text-muted">Belum ada data kelas
                                                    yang terdaftar.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($kelasList as $k): ?>
                                                <?php
                                                $customWa = getSetting("portal_wa_kelas_{$k['id']}", '');
                                                $autoWa = $k['wali_no_hp'] ?? '';
                                                $effectiveWa = !empty($customWa) ? $customWa : $autoWa;
                                                ?>
                                                <tr>
                                                    <td>
                                                        <span
                                                            class="badge bg-primary-subtle text-primary fw-bold px-2.5 py-1.5 rounded-3">
                                                            Kelas <?= htmlspecialchars($k['nama_kelas']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold text-dark">
                                                            <?= htmlspecialchars($k['wali_nama'] ?? 'Belum Ditugaskan') ?>
                                                        </div>
                                                        <?php if (!empty($autoWa)): ?>
                                                            <small class="text-muted d-block" style="font-size: 0.72rem;">No. HP
                                                                Profil: <?= htmlspecialchars($autoWa) ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text bg-light"><i
                                                                    class="bi bi-whatsapp text-success"></i></span>
                                                            <input type="text" name="wa_kelas[<?= $k['id'] ?>]"
                                                                class="form-control"
                                                                placeholder="<?= !empty($autoWa) ? htmlspecialchars($autoWa) : 'Ikuti default' ?>"
                                                                value="<?= htmlspecialchars($customWa) ?>">
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- TAB 3: TEKS & PREFERENSI TAMPILAN -->
            <!-- ============================================================== -->
            <div class="tab-pane fade" id="tab-tampilan" role="tabpanel">
                <div class="card border-0 rounded-4 shadow-sm p-4 bg-white col-lg-8 mx-auto">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <div class="bg-info-subtle text-info p-2 rounded-3">
                            <i class="bi bi-card-heading fs-5"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">Teks Sapaan &amp; Banner Informasi</h5>
                            <small class="text-muted">Kustomisasi ucapan dan banner pengumuman untuk orang tua</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Teks Badge Sapaan (Hero Header)</label>
                        <input type="text" name="portal_greeting_text" class="form-control"
                            placeholder="Contoh: Ahlan wa Sahlan"
                            value="<?= htmlspecialchars($settings['portal_greeting_text']) ?>">
                        <small class="text-muted" style="font-size: 0.76rem;">Teks kecil yang muncul di atas nama santri
                            pada beranda portal.</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark small">Banner Pengumuman / Info Penting Ortu
                            (Opsional)</label>
                        <textarea name="portal_banner_info" class="form-control" rows="3"
                            placeholder="Tulis informasi atau maklumat singkat yang akan tampil di bagian atas beranda portal ortu..."><?= htmlspecialchars($settings['portal_banner_info']) ?></textarea>
                        <small class="text-muted" style="font-size: 0.76rem;">Kosongkan jika tidak ingin menampilkan
                            banner informasi pengumuman.</small>
                    </div>

                    <div class="d-flex align-items-center gap-2 mb-3 pt-3 pb-2 border-top border-bottom">
                        <div class="bg-success-subtle text-success p-2 rounded-3">
                            <i class="bi bi-bank fs-5"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">Informasi Rekening Transfer</h5>
                            <small class="text-muted">Nomor rekening tujuan transfer yang muncul di form wali
                                murid</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">
                            <i class="bi bi-shop text-warning me-1"></i> Rekening Transfer E-Kantin (Top-Up Saldo Siswa)
                        </label>
                        <textarea name="kantin_rekening_info" class="form-control font-monospace" rows="3"
                            placeholder="Contoh: BSI (Bank Syariah Indonesia) No. 7148123456 a.n. E-Kantin SMP"><?= htmlspecialchars($settings['kantin_rekening_info']) ?></textarea>
                        <small class="text-muted" style="font-size: 0.76rem;">Informasi rekening bank tujuan yang
                            ditampilkan saat orang tua mengisi saldo e-kantin.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">
                            <i class="bi bi-cash-stack text-primary me-1"></i> Rekening Transfer SPP &amp; Tagihan
                            Sekolah
                        </label>
                        <textarea name="tunggakan_rekening_info" class="form-control font-monospace" rows="3"
                            placeholder="Contoh: BSI (Bank Syariah Indonesia) No. 7148123456 a.n. SMP Islamic School"><?= htmlspecialchars($settings['tunggakan_rekening_info']) ?></textarea>
                        <small class="text-muted" style="font-size: 0.76rem;">Informasi rekening bank tujuan yang
                            ditampilkan pada rincian tagihan / bayar SPP online.</small>
                    </div>
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- TAB 4: POP-UP PENGUMUMAN PORTAL ORTU (TEKS / GAMBAR / PDF)     -->
            <!-- ============================================================== -->
            <div class="tab-pane fade" id="tab-popup" role="tabpanel">
                <div class="row g-4">
                    <!-- KOLOM KIRI: FORM PENGATURAN POP-UP -->
                    <div class="col-lg-7">
                        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
                            <!-- STATUS TOGGLE -->
                            <div
                                class="d-flex align-items-center justify-content-between p-3.5 rounded-4 bg-primary-subtle border border-primary-subtle mb-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-primary text-white p-2.5 rounded-3 d-flex align-items-center justify-content-center shadow-xs"
                                        style="width: 46px; height: 46px;">
                                        <i class="bi bi-megaphone-fill fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark fs-6">Aktifkan Pop-up Pemberitahuan</div>
                                        <small class="text-muted">Tampilkan modal pengumuman saat orang tua membuka
                                            portal santri</small>
                                    </div>
                                </div>
                                <div class="form-check form-switch fs-3 mb-0">
                                    <input class="form-check-input" type="checkbox" name="portal_popup_active"
                                        id="portal_popup_active" value="1" <?= ($settings['portal_popup_active'] ?? '0') === '1' ? 'checked' : '' ?> onchange="updateLivePreview()">
                                </div>
                            </div>

                            <!-- TIPE POP-UP (PILIHAN TEKS / GAMBAR / PDF) -->
                            <div class="mb-4">
                                <label class="form-label fw-bold text-dark small mb-2"><i
                                        class="bi bi-grid-fill text-primary me-1"></i> Tipe Konten Pop-up</label>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label
                                            class="popup-type-card p-3 rounded-3 border d-flex flex-column align-items-center text-center cursor-pointer w-100 <?= ($settings['portal_popup_type'] === 'text') ? 'active-type' : '' ?>"
                                            id="label_type_text" for="type_text">
                                            <input type="radio" name="portal_popup_type" id="type_text" value="text"
                                                class="d-none" <?= ($settings['portal_popup_type'] === 'text') ? 'checked' : '' ?> onchange="changePopupType('text')">
                                            <i class="bi bi-card-text fs-3 text-primary mb-1"></i>
                                            <span class="fw-bold small text-dark">Teks &amp; Pesan</span>
                                            <small class="text-muted" style="font-size: 0.72rem;">Maklumat tulisan
                                                rapi</small>
                                        </label>
                                    </div>
                                    <div class="col-md-4">
                                        <label
                                            class="popup-type-card p-3 rounded-3 border d-flex flex-column align-items-center text-center cursor-pointer w-100 <?= ($settings['portal_popup_type'] === 'image') ? 'active-type' : '' ?>"
                                            id="label_type_image" for="type_image">
                                            <input type="radio" name="portal_popup_type" id="type_image" value="image"
                                                class="d-none" <?= ($settings['portal_popup_type'] === 'image') ? 'checked' : '' ?> onchange="changePopupType('image')">
                                            <i class="bi bi-image-fill fs-3 text-success mb-1"></i>
                                            <span class="fw-bold small text-dark">Gambar / Flyer</span>
                                            <small class="text-muted" style="font-size: 0.72rem;">Banner / Infografis
                                                visual</small>
                                        </label>
                                    </div>
                                    <div class="col-md-4">
                                        <label
                                            class="popup-type-card p-3 rounded-3 border d-flex flex-column align-items-center text-center cursor-pointer w-100 <?= ($settings['portal_popup_type'] === 'pdf') ? 'active-type' : '' ?>"
                                            id="label_type_pdf" for="type_pdf">
                                            <input type="radio" name="portal_popup_type" id="type_pdf" value="pdf"
                                                class="d-none" <?= ($settings['portal_popup_type'] === 'pdf') ? 'checked' : '' ?> onchange="changePopupType('pdf')">
                                            <i class="bi bi-file-earmark-pdf-fill fs-3 text-danger mb-1"></i>
                                            <span class="fw-bold small text-dark">Dokumen PDF</span>
                                            <small class="text-muted" style="font-size: 0.72rem;">Surat edaran
                                                resmi</small>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- JUDUL PENGUMUMAN -->
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark small">Judul Pop-up / Pengumuman <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i
                                            class="bi bi-type-h1 text-primary"></i></span>
                                    <input type="text" name="portal_popup_title" id="input_popup_title"
                                        class="form-control"
                                        placeholder="Contoh: Pemberitahuan Kegiatan Ujian Akhir Semester & Libur Santri"
                                        value="<?= htmlspecialchars($settings['portal_popup_title']) ?>"
                                        oninput="updateLivePreview()">
                                </div>
                            </div>

                            <!-- ISI PESAN / KETERANGAN -->
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark small">Isi Pesan / Maklumat
                                    Pengumuman</label>
                                <textarea name="portal_popup_content" id="input_popup_content" class="form-control"
                                    rows="4"
                                    placeholder="Tuliskan isi pesan atau instruksi pengumuman untuk orang tua di sini..."
                                    oninput="updateLivePreview()"><?= htmlspecialchars($settings['portal_popup_content']) ?></textarea>
                                <small class="text-muted" style="font-size: 0.76rem;">Bisa berupa poin-poin penjelasan
                                    atau instruksi pembuka.</small>
                            </div>

                            <!-- UPLOAD FILE (GAMBAR / PDF) -->
                            <div class="mb-3" id="group_file_upload">
                                <label class="form-label fw-bold text-dark small d-flex justify-content-between">
                                    <span><i class="bi bi-paperclip text-info me-1"></i> Lampiran Berkas (Gambar Flyer /
                                        Dokumen PDF)</span>
                                    <span class="text-muted fw-normal" style="font-size: 0.75rem;">Maks. 10MB</span>
                                </label>

                                <?php if (!empty($settings['portal_popup_file'])):
                                    $filePath = $settings['portal_popup_file'];
                                    $isPdf = strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'pdf';
                                    $fullFileUrl = BASE_URL . '/' . ltrim($filePath, '/');
                                    $displayDocName = !empty($settings['portal_popup_file_name']) ? $settings['portal_popup_file_name'] : (!empty($settings['portal_popup_title']) ? $settings['portal_popup_title'] . '.pdf' : basename($filePath));
                                    ?>
                                    <div
                                        class="p-3 bg-light rounded-3 border mb-2 d-flex align-items-center justify-content-between gap-2">
                                        <div class="d-flex align-items-center gap-2.5 overflow-hidden">
                                            <?php if ($isPdf): ?>
                                                <div class="bg-danger text-white p-2 rounded-2 d-flex align-items-center justify-content-center flex-shrink-0"
                                                    style="width:40px;height:40px;"><i
                                                        class="bi bi-file-earmark-pdf-fill fs-5"></i>
                                                </div>
                                            <?php else: ?>
                                                <img src="<?= $fullFileUrl ?>" alt="Preview"
                                                    class="rounded border flex-shrink-0"
                                                    style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php endif; ?>
                                            <div class="overflow-hidden">
                                                <div class="fw-bold text-dark small text-truncate">
                                                    <?= htmlspecialchars($displayDocName) ?>
                                                </div>
                                                <a href="<?= $fullFileUrl ?>" target="_blank"
                                                    class="extra-small text-primary text-decoration-none fw-semibold">
                                                    <i class="bi bi-box-arrow-up-right me-1"></i> Lihat / Buka Berkas
                                                </a>
                                            </div>
                                        </div>
                                        <div class="form-check text-nowrap">
                                            <input class="form-check-input" type="checkbox" name="delete_popup_file"
                                                value="1" id="del_file">
                                            <label class="form-check-label text-danger small fw-semibold"
                                                for="del_file">Hapus</label>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <input type="file" name="portal_popup_file" id="input_popup_file" class="form-control"
                                    accept=".jpg,.jpeg,.png,.webp,.pdf" onchange="previewSelectedFile(this)">
                                <small class="text-muted" style="font-size: 0.76rem;">Pilih berkas gambar (JPG/PNG/WebP)
                                    atau file PDF resmi dari perangkat Anda.</small>
                            </div>

                            <!-- NAMA DOKUMEN / FILE CUSTOM -->
                            <div class="mb-3" id="group_doc_name">
                                <label class="form-label fw-bold text-dark small">Nama Berkas / Dokumen
                                    Ditampilkan</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i
                                            class="bi bi-file-earmark-text text-secondary"></i></span>
                                    <input type="text" name="portal_popup_file_name" id="input_popup_file_name"
                                        class="form-control"
                                        placeholder="Contoh: Jadwal ATS (Asesmen Tengah Semester Ganjil).pdf"
                                        value="<?= htmlspecialchars($settings['portal_popup_file_name']) ?>"
                                        oninput="updateLivePreview()">
                                </div>
                                <small class="text-muted" style="font-size: 0.74rem;">Nama dokumen yang dibaca wali
                                    murid pada kartu pop-up (otomatis dari nama file asli jika kosong).</small>
                            </div>

                            <div class="row g-3 mb-3">
                                <!-- TEKS TOMBOL AKSI -->
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark small">Teks Tombol Aksi (CTA)</label>
                                    <input type="text" name="portal_popup_btn_text" id="input_btn_text"
                                        class="form-control" placeholder="Contoh: Buka Dokumen PDF / Pelajari"
                                        value="<?= htmlspecialchars($settings['portal_popup_btn_text']) ?>"
                                        oninput="updateLivePreview()">
                                </div>
                                <!-- LINK TOMBOL AKSI -->
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark small">Tautan / Link Tombol
                                        (Opsional)</label>
                                    <input type="text" name="portal_popup_btn_link" id="input_btn_link"
                                        class="form-control" placeholder="Contoh: https://... (Kosongkan jika otomatis)"
                                        value="<?= htmlspecialchars($settings['portal_popup_btn_link']) ?>"
                                        oninput="updateLivePreview()">
                                    <small class="text-muted" style="font-size: 0.74rem;">Jika kosong, tombol otomatis
                                        mengarah ke file lampiran.</small>
                                </div>
                            </div>

                            <!-- FREKUENSI TAMPIL -->
                            <div class="mb-2">
                                <label class="form-label fw-bold text-dark small">Frekuensi Kemunculan Pop-up</label>
                                <select name="portal_popup_frequency" class="form-select">
                                    <option value="session" <?= $settings['portal_popup_frequency'] === 'session' ? 'selected' : '' ?>>
                                        1x Per Sesi Kunjungan (Rekomendasi - Tidak Mengganggu)
                                    </option>
                                    <option value="always" <?= $settings['portal_popup_frequency'] === 'always' ? 'selected' : '' ?>>
                                        Selalu Muncul (Setiap kali membuka/merefresh portal)
                                    </option>
                                    <option value="once" <?= $settings['portal_popup_frequency'] === 'once' ? 'selected' : '' ?>>
                                        1x Selamanya (Hanya tampil 1x hingga admin mengupdate pengumuman baru)
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- KOLOM KANAN: LIVE PREVIEW SIMULATOR -->
                    <div class="col-lg-5">
                        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white sticky-top" style="top: 20px;">
                            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bg-warning-subtle text-warning p-2 rounded-3">
                                        <i class="bi bi-eye-fill fs-5"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-0 text-dark">Live Pratinjau Pop-up</h6>
                                        <small class="text-muted">Simulasi tampilan di HP / Layar Wali Murid</small>
                                    </div>
                                </div>
                                <span id="preview_status_badge"
                                    class="badge <?= ($settings['portal_popup_active'] ?? '0') === '1' ? 'bg-success' : 'bg-secondary' ?> rounded-pill px-2.5 py-1">
                                    <?= ($settings['portal_popup_active'] ?? '0') === '1' ? 'Aktif' : 'Non-Aktif' ?>
                                </span>
                            </div>

                            <!-- SIMULATOR CONTAINER -->
                            <div class="preview-backdrop p-3 rounded-4 position-relative"
                                style="background: rgba(15, 23, 42, 0.78); backdrop-filter: blur(8px); min-height: 380px;">
                                <!-- MODAL CARD SIMULATION -->
                                <div class="preview-modal bg-white rounded-4 shadow-lg overflow-hidden border mx-auto"
                                    style="border-radius: 26px !important; max-width: 360px;">
                                    <!-- HEADER BAR -->
                                    <div
                                        class="px-3.5 py-3 bg-light border-bottom d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge rounded-pill px-2.5 py-1 shadow-2xs"
                                                style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: #fff; font-size: 0.68rem; font-weight: 700; letter-spacing: 0.4px;"
                                                id="preview_badge_type">DOKUMEN RESMI</span>
                                        </div>
                                        <button type="button"
                                            class="btn btn-sm btn-light rounded-circle p-0 border d-flex align-items-center justify-content-center text-secondary shadow-2xs"
                                            style="width: 26px; height: 26px; line-height: 1;"><i
                                                class="bi bi-x fs-6"></i></button>
                                    </div>

                                    <!-- BODY -->
                                    <div class="p-3.5 text-start">
                                        <h6 class="fw-bold text-dark mb-3 text-start" id="preview_title"
                                            style="font-family: 'Outfit', sans-serif; font-size: 1.05rem; font-weight: 700; line-height: 1.38; letter-spacing: -0.01em;">
                                            <?= htmlspecialchars($settings['portal_popup_title'] ?: 'Judul Pengumuman') ?>
                                        </h6>

                                        <!-- MEDIA PREVIEW CONTAINER -->
                                        <div id="preview_media_box" class="mb-3">
                                            <?php
                                            $currFile = $settings['portal_popup_file'];
                                            $currIsPdf = !empty($currFile) && strtolower(pathinfo($currFile, PATHINFO_EXTENSION)) === 'pdf';
                                            $currFileUrl = !empty($currFile) ? (BASE_URL . '/' . ltrim($currFile, '/')) : '';
                                            $currDisplayName = !empty($settings['portal_popup_file_name']) ? $settings['portal_popup_file_name'] : (!empty($settings['portal_popup_title']) ? $settings['portal_popup_title'] . '.pdf' : basename($currFile));
                                            ?>
                                            <img src="<?= $currFileUrl ?>"
                                                class="img-fluid rounded-3 border w-100 mb-2 <?= ($settings['portal_popup_type'] === 'image' && !empty($currFileUrl) && !$currIsPdf) ? '' : 'd-none' ?>"
                                                style="max-height: 170px; object-fit: cover;" id="preview_img_elem">

                                            <!-- HERO PDF PREVIEW CARD -->
                                            <div class="p-2.5 rounded-3 border d-flex align-items-center gap-3 mb-2 <?= ($settings['portal_popup_type'] === 'pdf' || $currIsPdf) ? '' : 'd-none' ?>"
                                                id="preview_pdf_elem"
                                                style="background: #ffffff; border: 1.5px solid #fee2e2 !important; box-shadow: 0 4px 14px rgba(239, 68, 68, 0.06);">
                                                <div class="text-white d-flex align-items-center justify-content-center flex-shrink-0"
                                                    style="width: 44px; height: 44px; min-width: 44px; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important; border-radius: 12px !important; box-shadow: 0 6px 14px rgba(239, 68, 68, 0.25);">
                                                    <i class="bi bi-file-earmark-pdf-fill fs-4"></i>
                                                </div>
                                                <div class="overflow-hidden flex-grow-1 text-start">
                                                    <div class="fw-bold text-dark small text-truncate mb-0.5"
                                                        id="preview_pdf_name"
                                                        style="font-size: 0.88rem; font-weight: 700; color: #1e293b;">
                                                        <?= !empty($currDisplayName) ? htmlspecialchars($currDisplayName) : 'Dokumen_Lampiran.pdf' ?>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-1">
                                                        <span
                                                            class="badge rounded-pill bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-0.5 d-inline-flex align-items-center gap-1"
                                                            style="font-size: 0.65rem; font-weight: 600;">
                                                            <i class="bi bi-check-circle-fill"></i> PDF
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="text-danger opacity-75 ps-1 flex-shrink-0">
                                                    <i class="bi bi-chevron-right fs-6"></i>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="text-secondary small mb-2 text-start px-0.5" id="preview_content"
                                            style="font-size: 0.88rem; line-height: 1.6; color: #475569; white-space: pre-wrap; max-height: 130px; overflow-y: auto;">
                                            <?= htmlspecialchars($settings['portal_popup_content'] ?: 'Isi teks pengumuman penting untuk wali murid akan tampil di area ini secara rapi...') ?>
                                        </div>
                                    </div>

                                    <!-- FOOTER -->
                                    <div
                                        class="px-3.5 py-3 bg-white border-top d-flex align-items-center justify-content-between gap-2">
                                        <button type="button"
                                            class="btn btn-light btn-sm rounded-pill px-3 py-1.5 border text-secondary shadow-2xs"
                                            style="font-size: 0.8rem; background: #f8fafc;">Tutup</button>
                                        <button type="button"
                                            class="btn btn-primary btn-sm rounded-pill px-3.5 py-1.5 shadow-sm fw-bold d-inline-flex align-items-center gap-1.5"
                                            id="preview_btn_action"
                                            style="font-size: 0.8rem; background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); border: none;">
                                            <i class="bi bi-file-earmark-arrow-down"></i>
                                            <?= htmlspecialchars($settings['portal_popup_btn_text'] ?: 'Buka Dokumen PDF') ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- STICKY BOTTOM ACTION BAR -->
        <div class="bg-white p-3 rounded-4 shadow-sm border mt-4 d-flex align-items-center justify-content-between">
            <span class="text-muted small"><i class="bi bi-shield-check text-success me-1"></i> Perubahan langsung aktif
                di Portal Orang Tua secara instan.</span>
            <div class="d-flex gap-2">
                <a href="<?= BASE_URL ?>/pages/pengaturan/index.php"
                    class="btn btn-outline-secondary rounded-pill px-4">Batal</a>
                <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold">
                    <i class="bi bi-check2-circle me-1"></i> Simpan Pengaturan Portal
                </button>
            </div>
        </div>
    </form>
</div>

<style>
    .popup-type-card {
        transition: all 0.2s ease-in-out;
        background: #ffffff;
        cursor: pointer;
    }

    .popup-type-card:hover {
        border-color: #3b82f6 !important;
        background: #f8fafc;
        transform: translateY(-2px);
    }

    .popup-type-card.active-type {
        border-color: #3b82f6 !important;
        background: rgba(59, 130, 246, 0.06) !important;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
    }

    .preview-modal {
        max-width: 320px;
        margin: 0 auto;
        animation: modalPopIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes modalPopIn {
        0% {
            transform: scale(0.92);
            opacity: 0;
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
    }
</style>

<script>
    let currentPopupType = '<?= $settings['portal_popup_type'] ?>';

    function changePopupType(type) {
        currentPopupType = type;
        document.querySelectorAll('.popup-type-card').forEach(c => c.classList.remove('active-type'));
        const activeLabel = document.getElementById('label_type_' + type);
        if (activeLabel) activeLabel.classList.add('active-type');

        const badge = document.getElementById('preview_badge_type');
        if (type === 'text') badge.innerText = 'PENGUMUMAN';
        else if (type === 'image') badge.innerText = 'INFO BERGAMBAR';
        else if (type === 'pdf') badge.innerText = 'DOKUMEN RESMI';

        updateLivePreview();
    }

    function updateLivePreview() {
        const isActive = document.getElementById('portal_popup_active').checked;
        const titleVal = document.getElementById('input_popup_title').value.trim() || 'Judul Pengumuman';
        const contentVal = document.getElementById('input_popup_content').value.trim() || 'Isi teks pengumuman penting untuk wali murid akan tampil di area ini secara rapi...';
        const btnTextVal = document.getElementById('input_btn_text').value.trim() || (currentPopupType === 'pdf' ? 'Buka Surat PDF' : 'Buka Dokumen');

        document.getElementById('preview_title').innerText = titleVal;
        document.getElementById('preview_content').innerText = contentVal;
        document.getElementById('preview_btn_action').innerText = btnTextVal;

        const statusBadge = document.getElementById('preview_status_badge');
        if (isActive) {
            statusBadge.className = 'badge bg-success rounded-pill px-2.5 py-1';
            statusBadge.innerText = 'Aktif';
        } else {
            statusBadge.className = 'badge bg-secondary rounded-pill px-2.5 py-1';
            statusBadge.innerText = 'Non-Aktif';
        }

        const imgElem = document.getElementById('preview_img_elem');
        const pdfElem = document.getElementById('preview_pdf_elem');

        if (currentPopupType === 'text') {
            if (imgElem) imgElem.classList.add('d-none');
            if (pdfElem) pdfElem.classList.add('d-none');
        } else if (currentPopupType === 'image') {
            if (imgElem && imgElem.src && !imgElem.src.endsWith('/')) imgElem.classList.remove('d-none');
            if (pdfElem) pdfElem.classList.add('d-none');
        } else if (currentPopupType === 'pdf') {
            if (imgElem) imgElem.classList.add('d-none');
            if (pdfElem) pdfElem.classList.remove('d-none');
        }
    }

    function previewSelectedFile(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const ext = file.name.split('.').pop().toLowerCase();

            if (['jpg', 'jpeg', 'png', 'webp'].includes(ext)) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const imgElem = document.getElementById('preview_img_elem');
                    if (imgElem) {
                        imgElem.src = e.target.result;
                        if (currentPopupType === 'image') imgElem.classList.remove('d-none');
                    }
                };
                reader.readAsDataURL(file);
            } else if (ext === 'pdf') {
                const pdfNameElem = document.getElementById('preview_pdf_name');
                if (pdfNameElem) pdfNameElem.innerText = file.name;
                const pdfElem = document.getElementById('preview_pdf_elem');
                if (pdfElem && currentPopupType === 'pdf') pdfElem.classList.remove('d-none');
            }
        }
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>