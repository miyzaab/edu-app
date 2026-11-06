<?php
/**
 * HEADER - Layout utama (bagian atas + sidebar)
 * Include file ini di semua halaman setelah login.
 * 
 * Variabel yang harus di-set sebelum include:
 * - $pageTitle : judul halaman
 * - $activePage : identifier menu aktif
 */

$pageTitle = $pageTitle ?? 'Dashboard';
$activePage = $activePage ?? 'dashboard';
if (file_exists(__DIR__ . '/module_subnav.php')) {
    require_once __DIR__ . '/module_subnav.php';
}

// Deteksi Modul Aktif berdasarkan URL dan variabel $activePage untuk pemfilteran navigasi
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$currentUrl = $requestUri . ' ' . $scriptName;
$activeModule = 'dashboard';

if (
    in_array($activePage, ['dashboard-utama', 'verifikasi', 'riwayat', 'rekap-spp', 'spp', 'uang-pangkal', 'pembayaran-lain', 'jenis-pembayaran', 'tunggakan', 'petty-cash', 'laporan']) ||
    strpos($currentUrl, '/pages/transaksi/') !== false ||
    strpos($currentUrl, '/pages/verifikasi/') !== false ||
    strpos($currentUrl, '/pages/riwayat/') !== false ||
    strpos($currentUrl, '/pages/spp/') !== false ||
    strpos($currentUrl, '/pages/uang-pangkal/') !== false ||
    strpos($currentUrl, '/pages/pembayaran-lain/') !== false ||
    strpos($currentUrl, '/pages/jenis-pembayaran/') !== false ||
    strpos($currentUrl, '/pages/tunggakan/') !== false ||
    strpos($currentUrl, '/pages/petty-cash/') !== false ||
    strpos($currentUrl, '/pages/laporan/') !== false
) {
    $activeModule = 'transaksi';
} elseif (
    in_array($activePage, ['kelas', 'siswa', 'guru']) ||
    strpos($currentUrl, '/pages/kelas/') !== false ||
    strpos($currentUrl, '/pages/siswa/') !== false ||
    strpos($currentUrl, '/pages/guru/') !== false
) {
    $activeModule = 'master_data';
} elseif (
    in_array($activePage, ['perangkat-ajar', 'input-nilai', 'wali-kelas', 'rapor', 'mapel', 'plotting_guru']) ||
    strpos($currentUrl, '/pages/perangkat-ajar/') !== false ||
    strpos($currentUrl, '/pages/nilai/') !== false ||
    strpos($currentUrl, '/pages/wali-kelas/') !== false ||
    strpos($currentUrl, '/pages/rapor/') !== false ||
    strpos($currentUrl, '/pages/mapel/') !== false
) {
    $activeModule = 'akademik';
} elseif (
    in_array($activePage, ['halaqah', 'poin', 'kesantrian']) ||
    strpos($currentUrl, '/pages/halaqah/') !== false
) {
    $activeModule = 'kesantrian';
} elseif (
    in_array($activePage, ['kantin', 'menu-kantin', 'topup-kantin', 'saldo-kantin', 'laporan-kantin']) ||
    strpos($currentUrl, '/pages/kantin/') !== false
) {
    $activeModule = 'kantin';
} elseif (
    in_array($activePage, ['users', 'pengaturan', 'backup']) ||
    strpos($currentUrl, '/pages/users/') !== false ||
    strpos($currentUrl, '/pages/pengaturan/') !== false
) {
    $activeModule = 'pengaturan';
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= getSetting('app_name', APP_NAME) ?></title>
    <meta name="description" content="Sistem Edu-App <?= getSetting('nama_sekolah', SCHOOL_NAME) ?>">
    <!-- DNS Prefetch & Preconnect for Lightning Speed -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet">
    <!-- Custom CSS (High Speed Browser Caching) -->
    <link href="<?= BASE_URL ?>/assets/css/style.css?v=1.0.6" rel="stylesheet">
    <?= getDynamicThemeCss() ?>
    <!-- Custom JS (High Speed Browser Caching) -->
    <script src="<?= BASE_URL ?>/assets/js/app.js?v=1.0.6" defer></script>
</head>

<body class="<?= $activePage === 'dashboard' ? 'page-dashboard' : '' ?>">

    <!-- Overlay untuk menutup sidebar di mobile & desktop drawer -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="mainSidebar">
        <div class="sidebar-brand">
            <div class="brand-icon"><?= getLogoHtml(40) ?></div>
            <div class="brand-text">
                <h2><?= htmlspecialchars(getSetting('app_name', APP_NAME)) ?></h2>
                <small><?= htmlspecialchars(getSetting('nama_sekolah', SCHOOL_NAME)) ?></small>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">Menu Utama</div>
            <a href="<?= BASE_URL ?>/pages/dashboard.php" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i> Portal Utama
            </a>

            <?php if ($activeModule === 'dashboard' || $activeModule === 'transaksi'): ?>
                <?php if (hasPermission('dashboard_utama') || hasPermission('verifikasi') || hasPermission('riwayat') || hasPermission('spp') || hasPermission('uang_pangkal') || hasPermission('pembayaran_lain')): ?>
                    <div class="nav-section">Transaksi</div>
                    <?php if (hasPermission('dashboard_utama')): ?>
                        <a href="<?= BASE_URL ?>/pages/transaksi/dashboard.php"
                            class="<?= $activePage === 'dashboard-utama' ? 'active' : '' ?>">
                            <i class="bi bi-speedometer2"></i> Dashboard Keuangan
                        </a>
                    <?php endif; ?>
                    <?php if (hasPermission('verifikasi')): ?>
                        <?php
                        $pendingBadgeCount = getConnection()->query("SELECT COUNT(id) FROM pembayaran_pending WHERE status='pending' AND jenis IN ('spp', 'uang_pangkal', 'lainnya')")->fetchColumn();
                        ?>
                        <a href="<?= BASE_URL ?>/pages/verifikasi/index.php"
                            class="<?= $activePage === 'verifikasi' ? 'active' : '' ?>">
                            <i class="bi bi-shield-check"></i> Verifikasi Pembayaran
                            <?php if ($pendingBadgeCount > 0): ?>
                                <span class="badge bg-danger ms-auto" style="border-radius:12px"><?= $pendingBadgeCount ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                    <?php if (hasPermission('spp')): ?>
                        <a href="<?= BASE_URL ?>/pages/spp/index.php" class="<?= $activePage === 'spp' ? 'active' : '' ?>">
                            <i class="bi bi-cash-stack"></i> Pembayaran SPP
                        </a>
                    <?php endif; ?>
                    <?php if (hasPermission('uang_pangkal')): ?>
                        <a href="<?= BASE_URL ?>/pages/uang-pangkal/index.php"
                            class="<?= $activePage === 'uang-pangkal' ? 'active' : '' ?>">
                            <i class="bi bi-wallet2"></i> Uang Pangkal
                        </a>
                    <?php endif; ?>
                    <?php if (hasPermission('pembayaran_lain')): ?>
                        <a href="<?= BASE_URL ?>/pages/pembayaran-lain/index.php"
                            class="<?= $activePage === 'pembayaran-lain' ? 'active' : '' ?>">
                            <i class="bi bi-receipt-cutoff"></i> Pembayaran Lain
                        </a>
                    <?php endif; ?>
                    <?php if (hasPermission('tunggakan')): ?>
                        <a href="<?= BASE_URL ?>/pages/tunggakan/index.php"
                            class="<?= $activePage === 'tunggakan' ? 'active' : '' ?>">
                            <i class="bi bi-exclamation-triangle-fill text-warning"></i> Rekap Tunggakan
                        </a>
                    <?php endif; ?>
                    <?php if (hasPermission('riwayat')): ?>
                        <a href="<?= BASE_URL ?>/pages/riwayat/index.php" class="<?= $activePage === 'riwayat' ? 'active' : '' ?>">
                            <i class="bi bi-clock-history"></i> Riwayat Pembayaran
                        </a>
                    <?php endif; ?>
                    <?php if (hasPermission('rekap_spp')): ?>
                        <a href="<?= BASE_URL ?>/pages/riwayat/rekap-spp.php"
                            class="<?= $activePage === 'rekap-spp' ? 'active' : '' ?>">
                            <i class="bi bi-grid-3x3"></i> Rekap SPP Kelas
                        </a>
                    <?php endif; ?>
                    <?php if (hasPermission('rekap_uang_pangkal')): ?>
                        <a href="<?= BASE_URL ?>/pages/riwayat/rekap-uang-pangkal.php"
                            class="<?= $activePage === 'rekap-uang-pangkal' ? 'active' : '' ?>">
                            <i class="bi bi-wallet2"></i> Rekap Uang Pangkal
                        </a>
                    <?php endif; ?>
                    <?php if (hasPermission('rekap_daftar_ulang')): ?>
                        <a href="<?= BASE_URL ?>/pages/riwayat/rekap-daftar-ulang.php"
                            class="<?= $activePage === 'rekap-daftar-ulang' ? 'active' : '' ?>">
                            <i class="bi bi-clipboard-check"></i> Rekap Daftar Ulang
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($activeModule === 'dashboard' || $activeModule === 'kantin'): ?>
                <?php if (hasPermission('kantin')): ?>
                    <div class="nav-section">Kantin Sekolah</div>
                    <a href="<?= BASE_URL ?>/pages/kantin/index.php" class="<?= $activePage === 'kantin' ? 'active' : '' ?>">
                        <i class="bi bi-cart3"></i> Kasir / POS Kantin
                    </a>
                    <?php
                    $pendingTopupKantinCount = getConnection()->query("SELECT COUNT(id) FROM pembayaran_pending WHERE status='pending' AND (jenis = 'topup_kantin' OR jenis = 'topup' OR jenis = '' OR jenis IS NULL OR jenis NOT IN ('spp', 'uang_pangkal', 'lainnya'))")->fetchColumn();
                    ?>
                    <a href="<?= BASE_URL ?>/pages/kantin/topup.php"
                        class="<?= $activePage === 'topup-kantin' ? 'active' : '' ?>">
                        <i class="bi bi-wallet2"></i> Top Up Saldo Santri
                        <?php if ($pendingTopupKantinCount > 0): ?>
                            <span class="badge bg-danger ms-auto" style="border-radius:12px"><?= $pendingTopupKantinCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="<?= BASE_URL ?>/pages/kantin/menu.php"
                        class="<?= $activePage === 'menu-kantin' ? 'active' : '' ?>">
                        <i class="bi bi-egg-fried"></i> Kelola Menu & Stok
                    </a>
                    <a href="<?= BASE_URL ?>/pages/kantin/laporan.php"
                        class="<?= $activePage === 'laporan-kantin' ? 'active' : '' ?>">
                        <i class="bi bi-receipt"></i> Laporan Transaksi
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($activeModule === 'dashboard' || $activeModule === 'master_data'): ?>
                <?php if (hasPermission('kelas') || hasPermission('siswa') || hasPermission('guru') || hasPermission('jenis_pembayaran')): ?>
                    <div class="nav-section">Master Data</div>
                    <?php if (hasPermission('kelas')): ?>
                        <a href="<?= BASE_URL ?>/pages/kelas/index.php" class="<?= $activePage === 'kelas' ? 'active' : '' ?>">
                            <i class="bi bi-building"></i> Data Kelas
                        </a>
                    <?php endif; ?>
                    <?php if (hasPermission('siswa')): ?>
                        <a href="<?= BASE_URL ?>/pages/siswa/index.php" class="<?= $activePage === 'siswa' ? 'active' : '' ?>">
                            <i class="bi bi-people-fill"></i> Data Siswa
                        </a>
                    <?php endif; ?>
                    <?php if (hasPermission('guru')): ?>
                        <a href="<?= BASE_URL ?>/pages/guru/index.php" class="<?= $activePage === 'guru' ? 'active' : '' ?>">
                            <i class="bi bi-person-badge-fill"></i> Data Guru
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($activeModule === 'dashboard' || $activeModule === 'akademik'): ?>
                <?php if (hasPermission('perangkat_ajar') || hasPermission('input_nilai') || hasPermission('wali_kelas') || hasPermission('rapor') || hasPermission('mapel') || hasPermission('plotting_guru')): ?>
                    <div class="nav-section">Akademik & Guru</div>
                    <?php if (hasPermission('perangkat_ajar')): ?>
                        <a href="<?= BASE_URL ?>/pages/perangkat-ajar/index.php"
                            class="<?= $activePage === 'perangkat-ajar' ? 'active' : '' ?>">
                            <i class="bi bi-journal-bookmark-fill"></i> Perangkat Ajar
                        </a>
                    <?php endif; ?>
                    <?php if (hasPermission('input_nilai')): ?>
                        <a href="<?= BASE_URL ?>/pages/nilai/index.php"
                            class="<?= $activePage === 'input-nilai' ? 'active' : '' ?>">
                            <i class="bi bi-calculator-fill"></i> Input Nilai (Guru)
                        </a>
                    <?php endif; ?>

                    <?php if (hasPermission('wali_kelas') || hasPermission('rapor')): ?>
                        <div class="nav-section mt-2"
                            style="font-size: 0.68rem; color: #94a3b8; text-transform: uppercase; padding: 0.25rem 0.75rem;">Menu
                            Wali Kelas</div>
                        <?php if (hasPermission('wali_kelas')): ?>
                            <a href="<?= BASE_URL ?>/pages/wali-kelas/index.php"
                                class="<?= $activePage === 'wali-kelas' ? 'active' : '' ?>">
                                <i class="bi bi-person-badge-fill text-info"></i> Rekap Nilai & Catatan
                            </a>
                        <?php endif; ?>
                        <?php if (hasPermission('rapor')): ?>
                            <a href="<?= BASE_URL ?>/pages/rapor/index.php" class="<?= $activePage === 'rapor' ? 'active' : '' ?>">
                                <i class="bi bi-file-earmark-person-fill text-success"></i> Rapor Siswa (PTS/PAS)
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (hasPermission('mapel') || hasPermission('plotting_guru')): ?>
                        <div class="nav-section mt-2"
                            style="font-size: 0.68rem; color: #94a3b8; text-transform: uppercase; padding: 0.25rem 0.75rem;">
                            Manajemen Mapel</div>
                        <?php if (hasPermission('mapel')): ?>
                            <a href="<?= BASE_URL ?>/pages/mapel/index.php" class="<?= $activePage === 'mapel' ? 'active' : '' ?>">
                                <i class="bi bi-book-half"></i> Data Mata Pelajaran
                            </a>
                        <?php endif; ?>
                        <?php if (hasPermission('plotting_guru')): ?>
                            <a href="<?= BASE_URL ?>/pages/mapel/plotting.php"
                                class="<?= $activePage === 'plotting_guru' ? 'active' : '' ?>">
                                <i class="bi bi-person-workspace"></i> Plotting Guru Pengampu
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($activeModule === 'dashboard' || $activeModule === 'kesantrian'): ?>
                <?php if (hasPermission('halaqah') || hasPermission('kesantrian')): ?>
                    <div class="nav-section">Kesantrian</div>
                    <?php if (hasPermission('halaqah')): ?>
                        <a href="<?= BASE_URL ?>/pages/halaqah/index.php" class="<?= $activePage === 'halaqah' ? 'active' : '' ?>">
                            <i class="bi bi-moon-stars-fill"></i> Halaqah & Tahfidz
                        </a>
                    <?php endif; ?>
                    <?php if (hasPermission('kesantrian')): ?>
                        <a href="<?= BASE_URL ?>/pages/halaqah/poin_dashboard.php"
                            class="<?= $activePage === 'poin' ? 'active' : '' ?>">
                            <i class="bi bi-star-fill"></i> Poin Kesantrian
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($activeModule === 'dashboard' || $activeModule === 'kantin'): ?>
                <?php if (hasPermission('kantin')): ?>
                    <div class="nav-section">Kantin Sekolah</div>
                    <a href="<?= BASE_URL ?>/pages/kantin/index.php" class="<?= $activePage === 'kantin' ? 'active' : '' ?>">
                        <i class="bi bi-shop"></i> Kasir Kantin
                    </a>
                    <a href="<?= BASE_URL ?>/pages/kantin/topup.php"
                        class="<?= $activePage === 'kantin-topup' ? 'active' : '' ?>">
                        <i class="bi bi-credit-card-2-front-fill"></i> Top Up Saldo
                    </a>
                    <a href="<?= BASE_URL ?>/pages/kantin/menu.php"
                        class="<?= $activePage === 'kantin-menu' ? 'active' : '' ?>">
                        <i class="bi bi-egg-fried"></i> Kelola Menu & Stok
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($activeModule === 'dashboard' || $activeModule === 'transaksi'): ?>
                <?php if (hasPermission('petty_cash') || hasPermission('laporan')): ?>
                    <div class="nav-section">Laporan</div>
                    <?php if (hasPermission('petty_cash')): ?>
                        <a href="<?= BASE_URL ?>/pages/petty-cash/index.php"
                            class="<?= $activePage === 'petty-cash' ? 'active' : '' ?>">
                            <i class="bi bi-wallet-fill"></i> Petty Cash (Buku Kas)
                        </a>
                    <?php endif; ?>
                    <?php if (hasPermission('laporan')): ?>
                        <a href="<?= BASE_URL ?>/pages/laporan/index.php" class="<?= $activePage === 'laporan' ? 'active' : '' ?>">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Laporan Keuangan
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($activeModule === 'dashboard' || $activeModule === 'pengaturan'): ?>
                <?php if (hasPermission('users') || hasPermission('pengaturan')): ?>
                    <div class="nav-section">Pengaturan</div>
                    <?php if (hasPermission('users')): ?>
                        <a href="<?= BASE_URL ?>/pages/users/index.php" class="<?= $activePage === 'users' ? 'active' : '' ?>">
                            <i class="bi bi-person-gear"></i> Kelola User
                        </a>
                    <?php endif; ?>
                    <?php if (hasPermission('pengaturan')): ?>
                        <a href="<?= BASE_URL ?>/pages/pengaturan/index.php"
                            class="<?= $activePage === 'pengaturan' ? 'active' : '' ?>">
                            <i class="bi bi-gear-fill"></i> Pengaturan
                        </a>
                        <a href="<?= BASE_URL ?>/pages/pengaturan/portal-ortu.php"
                            class="<?= $activePage === 'pengaturan-portal' ? 'active' : '' ?>">
                            <i class="bi bi-phone-fill text-info"></i> Pengaturan Portal Ortu
                        </a>
                        <a href="<?= BASE_URL ?>/pages/pengaturan/index.php#backup-section"
                            class="<?= $activePage === 'backup' ? 'active' : '' ?>">
                            <i class="bi bi-database-down"></i> Backup & Restore DB
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="copyright-one-line">
                &copy; 2026 Developed by <a href="https://miyzaab.com" target="_blank">miyzaab.com</a> | Zia Abdurrofi
            </div>
        </div>
    </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <div class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-sidebar-toggle" onclick="toggleSidebar()"
                    style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:6px 12px;display:inline-flex;align-items:center;gap:6px;"
                    title="Buka Menu Navigasi">
                    <i class="bi bi-list fs-5" style="color:var(--dark)"></i>
                    <span class="d-none d-sm-inline fw-bold small text-dark">Menu</span>
                </button>
                <span class="page-title"><?= htmlspecialchars($pageTitle) ?></span>
            </div>
            <div class="user-info">
                <div>
                    <div class="user-name"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'User') ?></div>
                    <div class="user-role"><?= ucfirst($_SESSION['role'] ?? 'Bendahara') ?></div>
                </div>
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['nama_lengkap'] ?? 'U', 0, 1)) ?>
                </div>
                <a href="<?= BASE_URL ?>/logout.php" class="btn btn-sm btn-outline-danger ms-2" title="Logout">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </header>

        <!-- Content Area -->
        <div class="content-wrapper">
            <?= showFlash() ?>
            <?= function_exists('renderModuleSubnav') ? renderModuleSubnav() : '' ?>

            <!-- ===== FLOATING ACTION DOCK ===== -->
            <div class="floating-action-dock">
                <a href="javascript:window.history.back()" class="floating-btn back-btn" title="Kembali">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <a href="<?= BASE_URL ?>/pages/dashboard.php" class="floating-btn home-btn" title="Portal Utama (Home)">
                    <i class="bi bi-house-door-fill"></i>
                </a>
                <a href="<?= BASE_URL ?>/logout.php" class="floating-btn logout-btn" title="Keluar / Logout">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>