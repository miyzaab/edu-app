<?php
/**
 * HEADER - Layout utama (bagian atas + sidebar)
 * Include file ini di semua halaman setelah login.
 * 
 * Variabel yang harus di-set sebelum include:
 * - $pageTitle : judul halaman
 * - $activePage : identifier menu aktif
 */

$pageTitle  = $pageTitle ?? 'Dashboard';
$activePage = $activePage ?? 'dashboard';

// Deteksi Modul Aktif berdasarkan URL halaman saat ini untuk pemfilteran navigasi
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$activeModule = 'dashboard';

if (
    strpos($requestUri, '/pages/transaksi/') !== false ||
    strpos($requestUri, '/pages/verifikasi/') !== false ||
    strpos($requestUri, '/pages/riwayat/') !== false ||
    strpos($requestUri, '/pages/spp/') !== false ||
    strpos($requestUri, '/pages/uang-pangkal/') !== false ||
    strpos($requestUri, '/pages/pembayaran-lain/') !== false ||
    strpos($requestUri, '/pages/jenis-pembayaran/') !== false ||
    strpos($requestUri, '/pages/petty-cash/') !== false ||
    strpos($requestUri, '/pages/laporan/') !== false
) {
    $activeModule = 'transaksi';
} elseif (
    strpos($requestUri, '/pages/kelas/') !== false ||
    strpos($requestUri, '/pages/siswa/') !== false ||
    strpos($requestUri, '/pages/guru/') !== false
) {
    $activeModule = 'master_data';
} elseif (
    strpos($requestUri, '/pages/perangkat-ajar/') !== false ||
    strpos($requestUri, '/pages/nilai/') !== false ||
    strpos($requestUri, '/pages/mapel/') !== false
) {
    $activeModule = 'akademik';
} elseif (strpos($requestUri, '/pages/halaqah/') !== false) {
    $activeModule = 'kesantrian';
} elseif (strpos($requestUri, '/pages/kantin/') !== false) {
    $activeModule = 'kantin';
} elseif (
    strpos($requestUri, '/pages/users/') !== false ||
    strpos($requestUri, '/pages/pengaturan/') !== false
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
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= BASE_URL ?>/assets/css/style.css?v=<?= time() ?>" rel="stylesheet">
    <?= getDynamicThemeCss() ?>
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
        <a href="<?= BASE_URL ?>/pages/transaksi/dashboard.php" class="<?= $activePage === 'dashboard-utama' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard Keuangan
        </a>
        <?php endif; ?>
        <?php if (hasPermission('verifikasi')): ?>
        <?php
        $pendingBadgeCount = getConnection()->query("SELECT COUNT(id) FROM pembayaran_pending WHERE status='pending'")->fetchColumn();
        ?>
        <a href="<?= BASE_URL ?>/pages/verifikasi/index.php" class="<?= $activePage === 'verifikasi' ? 'active' : '' ?>">
            <i class="bi bi-shield-check"></i> Verifikasi Pembayaran
            <?php if ($pendingBadgeCount > 0): ?>
                <span class="badge bg-danger ms-auto" style="border-radius:12px"><?= $pendingBadgeCount ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('riwayat')): ?>
        <a href="<?= BASE_URL ?>/pages/riwayat/index.php" class="<?= $activePage === 'riwayat' ? 'active' : '' ?>">
            <i class="bi bi-clock-history"></i> Riwayat Pembayaran
        </a>
        <?php endif; ?>
        <?php if (hasPermission('rekap_spp')): ?>
        <a href="<?= BASE_URL ?>/pages/riwayat/rekap-spp.php" class="<?= $activePage === 'rekap-spp' ? 'active' : '' ?>">
            <i class="bi bi-grid-3x3"></i> Rekap SPP Kelas
        </a>
        <?php endif; ?>
        <?php if (hasPermission('spp')): ?>
        <a href="<?= BASE_URL ?>/pages/spp/index.php" class="<?= $activePage === 'spp' ? 'active' : '' ?>">
            <i class="bi bi-cash-stack"></i> Pembayaran SPP
        </a>
        <?php endif; ?>
        <?php if (hasPermission('uang_pangkal')): ?>
        <a href="<?= BASE_URL ?>/pages/uang-pangkal/index.php" class="<?= $activePage === 'uang-pangkal' ? 'active' : '' ?>">
            <i class="bi bi-wallet2"></i> Uang Pangkal
        </a>
        <?php endif; ?>
        <?php if (hasPermission('pembayaran_lain')): ?>
        <a href="<?= BASE_URL ?>/pages/pembayaran-lain/index.php" class="<?= $activePage === 'pembayaran-lain' ? 'active' : '' ?>">
            <i class="bi bi-receipt-cutoff"></i> Pembayaran Lain
        </a>
        <?php endif; ?>
        <?php if (hasPermission('jenis_pembayaran')): ?>
        <a href="<?= BASE_URL ?>/pages/jenis-pembayaran/index.php" class="<?= $activePage === 'jenis-pembayaran' ? 'active' : '' ?>">
            <i class="bi bi-tags-fill"></i> Jenis Pembayaran
        </a>
        <?php endif; ?>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($activeModule === 'dashboard' || $activeModule === 'master_data'): ?>
        <?php if (hasPermission('kelas') || hasPermission('siswa') || hasPermission('guru')): ?>
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
        <?php if (hasPermission('perangkat_ajar') || hasPermission('input_nilai') || hasPermission('mapel') || hasPermission('plotting_guru')): ?>
        <div class="nav-section">Akademik & Guru</div>
        <?php if (hasPermission('perangkat_ajar')): ?>
        <a href="<?= BASE_URL ?>/pages/perangkat-ajar/index.php" class="<?= $activePage === 'perangkat-ajar' ? 'active' : '' ?>">
            <i class="bi bi-journal-bookmark-fill"></i> Perangkat Ajar
        </a>
        <?php endif; ?>
        <?php if (hasPermission('input_nilai')): ?>
        <a href="<?= BASE_URL ?>/pages/nilai/index.php" class="<?= $activePage === 'nilai' ? 'active' : '' ?>">
            <i class="bi bi-calculator-fill"></i> Input Nilai
        </a>
        <?php endif; ?>
        <?php if (hasPermission('mapel')): ?>
        <a href="<?= BASE_URL ?>/pages/mapel/index.php" class="<?= $activePage === 'mapel' ? 'active' : '' ?>">
            <i class="bi bi-book-half"></i> Mata Pelajaran
        </a>
        <?php endif; ?>
        <?php if (hasPermission('plotting_guru')): ?>
        <a href="<?= BASE_URL ?>/pages/mapel/plotting.php" class="<?= $activePage === 'plotting' ? 'active' : '' ?>">
            <i class="bi bi-person-workspace"></i> Plotting Guru
        </a>
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
        <a href="<?= BASE_URL ?>/pages/halaqah/poin_dashboard.php" class="<?= $activePage === 'poin' ? 'active' : '' ?>">
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
        <a href="<?= BASE_URL ?>/pages/kantin/topup.php" class="<?= $activePage === 'kantin-topup' ? 'active' : '' ?>">
            <i class="bi bi-credit-card-2-front-fill"></i> Top Up Saldo
        </a>
        <a href="<?= BASE_URL ?>/pages/kantin/menu.php" class="<?= $activePage === 'kantin-menu' ? 'active' : '' ?>">
            <i class="bi bi-egg-fried"></i> Kelola Menu & Stok
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($activeModule === 'dashboard' || $activeModule === 'transaksi'): ?>
        <?php if (hasPermission('petty_cash') || hasPermission('laporan')): ?>
        <div class="nav-section">Laporan</div>
        <?php if (hasPermission('petty_cash')): ?>
        <a href="<?= BASE_URL ?>/pages/petty-cash/index.php" class="<?= $activePage === 'petty-cash' ? 'active' : '' ?>">
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
        <a href="<?= BASE_URL ?>/pages/pengaturan/index.php" class="<?= $activePage === 'pengaturan' ? 'active' : '' ?>">
            <i class="bi bi-gear-fill"></i> Pengaturan
        </a>
        <a href="<?= BASE_URL ?>/pages/pengaturan/index.php#backup-section" class="<?= $activePage === 'backup' ? 'active' : '' ?>">
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
            <button class="btn btn-sm btn-sidebar-toggle" onclick="toggleSidebar()" style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:6px 12px;display:inline-flex;align-items:center;gap:6px;" title="Buka Menu Navigasi">
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
