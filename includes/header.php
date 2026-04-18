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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= APP_NAME ?></title>
    <meta name="description" content="Sistem E-Pembayaran <?= SCHOOL_NAME ?>">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- Overlay untuk menutup sidebar di mobile -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="mainSidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><?= getLogoHtml(28) ?></div>
        <div>
            <h2><?= APP_NAME ?></h2>
            <small>Minhaj Al-Ilmi</small>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Menu Utama</div>
        <a href="<?= BASE_URL ?>/pages/dashboard.php" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2-fill"></i> Dashboard
        </a>

        <div class="nav-section">Transaksi</div>
        <?php
        $pendingBadgeCount = getConnection()->query("SELECT COUNT(id) FROM pembayaran_pending WHERE status='pending'")->fetchColumn();
        ?>
        <a href="<?= BASE_URL ?>/pages/verifikasi/index.php" class="<?= $activePage === 'verifikasi' ? 'active' : '' ?>">
            <i class="bi bi-shield-check"></i> Verifikasi Pembayaran
            <?php if ($pendingBadgeCount > 0): ?>
                <span class="badge bg-danger ms-auto" style="border-radius:12px"><?= $pendingBadgeCount ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>/pages/riwayat/index.php" class="<?= $activePage === 'riwayat' ? 'active' : '' ?>">
            <i class="bi bi-clock-history"></i> Riwayat Pembayaran
        </a>
        <a href="<?= BASE_URL ?>/pages/riwayat/rekap-spp.php" class="<?= $activePage === 'rekap-spp' ? 'active' : '' ?>">
            <i class="bi bi-grid-3x3"></i> Rekap SPP Kelas
        </a>
        <a href="<?= BASE_URL ?>/pages/spp/index.php" class="<?= $activePage === 'spp' ? 'active' : '' ?>">
            <i class="bi bi-cash-stack"></i> Pembayaran SPP
        </a>
        <a href="<?= BASE_URL ?>/pages/uang-pangkal/index.php" class="<?= $activePage === 'uang-pangkal' ? 'active' : '' ?>">
            <i class="bi bi-wallet2"></i> Uang Pangkal
        </a>
        <a href="<?= BASE_URL ?>/pages/pembayaran-lain/index.php" class="<?= $activePage === 'pembayaran-lain' ? 'active' : '' ?>">
            <i class="bi bi-receipt-cutoff"></i> Pembayaran Lain
        </a>

        <div class="nav-section">Master Data</div>
        <a href="<?= BASE_URL ?>/pages/kelas/index.php" class="<?= $activePage === 'kelas' ? 'active' : '' ?>">
            <i class="bi bi-building"></i> Data Kelas
        </a>
        <a href="<?= BASE_URL ?>/pages/siswa/index.php" class="<?= $activePage === 'siswa' ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i> Data Siswa
        </a>
        <a href="<?= BASE_URL ?>/pages/jenis-pembayaran/index.php" class="<?= $activePage === 'jenis-pembayaran' ? 'active' : '' ?>">
            <i class="bi bi-tags-fill"></i> Jenis Pembayaran
        </a>

        <div class="nav-section">Laporan</div>
        <a href="<?= BASE_URL ?>/pages/petty-cash/index.php" class="<?= $activePage === 'petty-cash' ? 'active' : '' ?>">
            <i class="bi bi-wallet-fill"></i> Petty Cash (Buku Kas)
        </a>
        <a href="<?= BASE_URL ?>/pages/laporan/index.php" class="<?= $activePage === 'laporan' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-spreadsheet"></i> Laporan Keuangan
        </a>

        <div class="nav-section">Pengaturan</div>
        <a href="<?= BASE_URL ?>/pages/users/index.php" class="<?= $activePage === 'users' ? 'active' : '' ?>">
            <i class="bi bi-person-gear"></i> Kelola User
        </a>
        <a href="<?= BASE_URL ?>/pages/pengaturan/index.php" class="<?= $activePage === 'pengaturan' ? 'active' : '' ?>">
            <i class="bi bi-gear-fill"></i> Pengaturan
        </a>
    </nav>
</aside>

<!-- ===== MAIN CONTENT ===== -->
<div class="main-content">
    <!-- Top Header -->
    <header class="top-header">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm d-md-none" onclick="toggleSidebar()" style="background:none;border:1px solid var(--border);border-radius:8px;padding:6px 10px;">
                <i class="bi bi-list fs-5" style="color:var(--dark)"></i>
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

<!-- ===== BOTTOM NAV (MOBILE) ===== -->
<nav class="bottom-nav">
    <a href="<?= BASE_URL ?>/pages/dashboard.php" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>">
        <i class="bi bi-grid-1x2-fill"></i>
        <span>Dashboard</span>
    </a>
    <a href="<?= BASE_URL ?>/pages/verifikasi/index.php" class="<?= $activePage === 'verifikasi' ? 'active' : '' ?>">
        <i class="bi bi-shield-check"></i>
        <span>Verifikasi</span>
    </a>
    <a href="<?= BASE_URL ?>/pages/riwayat/rekap-spp.php" class="<?= $activePage === 'rekap-spp' ? 'active' : '' ?>">
        <i class="bi bi-grid-3x3"></i>
        <span>Rekap SPP</span>
    </a>
    <a href="<?= BASE_URL ?>/pages/laporan/index.php" class="<?= $activePage === 'laporan' ? 'active' : '' ?>">
        <i class="bi bi-file-earmark-spreadsheet"></i>
        <span>Laporan</span>
    </a>
    <button class="bnav-menu" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
        <span>Menu</span>
    </button>
</nav>
