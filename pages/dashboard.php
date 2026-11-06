<?php
/**
 * PORTAL UTAMA (APP LAUNCHER) - 5 Modul Utama (Glassmorphism & Large Icon Cards)
 * Halaman depan serbaguna tanpa sidebar ΓÇö Glassmorphism, Responsive & Dynamic Theme
 */
$pageTitle = 'Portal Utama';
$activePage = 'dashboard';
require_once __DIR__ . '/../config/auth.php';

$pdo = getConnection();
$namaSekolah = getSetting('nama_sekolah', SCHOOL_NAME);
$appName = getSetting('app_name', APP_NAME);
$userName = $_SESSION['nama_lengkap'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'guru';
$themeColor = getSetting('theme_color', '#4f46e5');

// Daftar 5 Modul Utama
$modules = [
    [
        'key' => 'transaksi',
        'title' => 'Transaksi & Keuangan',
        'icon' => 'bi-cash-coin',
        'gradient' => 'linear-gradient(135deg, #6366f1 0%, #4f46e5 100%)',
        'shadow' => 'rgba(99, 102, 241, 0.4)',
        'color' => '#4f46e5',
        'target' => '/pages/transaksi/dashboard.php',
        'permissions' => ['dashboard_utama', 'verifikasi', 'riwayat', 'rekap_spp', 'rekap_uang_pangkal', 'rekap_daftar_ulang', 'spp', 'uang_pangkal', 'pembayaran_lain', 'jenis_pembayaran', 'petty_cash', 'laporan']
    ],
    [
        'key' => 'master_data',
        'title' => 'Master Data',
        'icon' => 'bi-building-gear',
        'gradient' => 'linear-gradient(135deg, #06b6d4 0%, #0891b2 100%)',
        'shadow' => 'rgba(6, 182, 212, 0.4)',
        'color' => '#0891b2',
        'target' => '/pages/kelas/index.php',
        'permissions' => ['kelas', 'siswa', 'guru']
    ],
    [
        'key' => 'akademik',
        'title' => 'Akademik & Guru',
        'icon' => 'bi-journal-bookmark-fill',
        'gradient' => 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
        'shadow' => 'rgba(16, 185, 129, 0.4)',
        'color' => '#059669',
        'target' => '/pages/perangkat-ajar/index.php',
        'permissions' => ['perangkat_ajar', 'input_nilai', 'rapor', 'mapel', 'plotting_guru']
    ],
    [
        'key' => 'kesantrian',
        'title' => 'Kesantrian',
        'icon' => 'bi-moon-stars-fill',
        'gradient' => 'linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%)',
        'shadow' => 'rgba(139, 92, 246, 0.4)',
        'color' => '#8b5cf6',
        'target' => '/pages/halaqah/index.php',
        'permissions' => ['halaqah', 'kesantrian']
    ],
    [
        'key' => 'kantin',
        'title' => 'Kantin Sekolah',
        'icon' => 'bi-shop',
        'gradient' => 'linear-gradient(135deg, #f97316 0%, #ea580c 100%)',
        'shadow' => 'rgba(249, 115, 22, 0.4)',
        'color' => '#ea580c',
        'target' => '/pages/kantin/index.php',
        'permissions' => ['kantin']
    ],
    [
        'key' => 'pengaturan',
        'title' => 'Pengaturan Sistem',
        'icon' => 'bi-gear-wide-connected',
        'gradient' => 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
        'shadow' => 'rgba(239, 68, 68, 0.4)',
        'color' => '#dc2626',
        'target' => '/pages/users/index.php',
        'permissions' => ['users', 'pengaturan']
    ]
];

// Map URL default per permission jika target pertama tidak diizinkan
$firstAllowedUrl = [
    'dashboard_utama' => '/pages/transaksi/dashboard.php',
    'verifikasi' => '/pages/verifikasi/index.php',
    'riwayat' => '/pages/riwayat/index.php',
    'rekap_spp' => '/pages/riwayat/rekap-spp.php',
    'rekap_uang_pangkal' => '/pages/riwayat/rekap-uang-pangkal.php',
    'rekap_daftar_ulang' => '/pages/riwayat/rekap-daftar-ulang.php',
    'spp' => '/pages/spp/index.php',
    'uang_pangkal' => '/pages/uang-pangkal/index.php',
    'pembayaran_lain' => '/pages/pembayaran-lain/index.php',
    'kantin' => '/pages/kantin/index.php',
    'kelas' => '/pages/kelas/index.php',
    'siswa' => '/pages/siswa/index.php',
    'guru' => '/pages/guru/index.php',
    'jenis_pembayaran' => '/pages/jenis-pembayaran/index.php',
    'perangkat_ajar' => '/pages/perangkat-ajar/index.php',
    'input_nilai' => '/pages/nilai/index.php',
    'rapor' => '/pages/rapor/index.php',
    'mapel' => '/pages/mapel/index.php',
    'plotting_guru' => '/pages/mapel/plotting.php',
    'halaqah' => '/pages/halaqah/index.php',
    'petty_cash' => '/pages/petty-cash/index.php',
    'laporan' => '/pages/laporan/index.php',
    'users' => '/pages/users/index.php',
    'pengaturan' => '/pages/pengaturan/index.php',
];

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* ===== HIGH-PERFORMANCE MODERN APP GRID ===== */
    .app-grid-container {
        padding: 1rem 0 3rem;
        display: flex;
        justify-content: center;
        width: 100%;
        contain: content;
    }

    .app-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
        width: 100%;
        max-width: 1100px;
        margin: 0 auto;
    }

    @media (min-width: 1400px) {
        .app-grid {
            grid-template-columns: repeat(6, 1fr);
            max-width: 1400px;
            gap: 1.25rem;
        }
    }

    /* --- MODULE CARD (LIGHTWEIGHT & SOLID FOR FAST MOBILE RENDERING) --- */
    .app-module-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-decoration: none !important;
        cursor: pointer;
        position: relative;
        padding: 1.5rem 1rem;
        border-radius: 22px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        min-height: 195px;
        transform: translateZ(0);
        -webkit-tap-highlight-color: transparent;
    }

    .app-module-card:hover {
        transform: translateY(-5px);
        border-color: #cbd5e1;
        box-shadow: 0 12px 24px -6px var(--mod-shadow, rgba(0, 0, 0, 0.12));
    }

    .app-module-card:active {
        transform: scale(0.97);
    }

    /* --- ICON BOX --- */
    .app-icon-box {
        position: relative;
        margin-bottom: 1rem;
        transform: translateZ(0);
    }

    .app-icon-inner {
        width: 92px;
        height: 92px;
        border-radius: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 2.6rem;
        position: relative;
        border: 2px solid rgba(255, 255, 255, 0.4);
        box-shadow: 0 10px 20px -3px var(--mod-shadow, rgba(0, 0, 0, 0.2));
        transition: transform 0.2s ease;
        transform: translateZ(0);
    }

    .app-module-card:hover .app-icon-inner {
        transform: scale(1.05);
    }

    /* --- TITLE --- */
    .app-module-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: #1e293b !important;
        text-align: center;
        line-height: 1.3;
        letter-spacing: -0.01em;
        min-height: 2.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        user-select: none;
    }

    .app-module-card:hover .app-module-title {
        color: var(--mod-color) !important;
    }

    /* ===== RESPONSIVE MOBILE OPTIMIZATION ===== */
    @media (max-width: 992px) {
        .app-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }
    }

    @media (max-width: 640px) {
        .app-grid-container {
            padding: 0.5rem 0 2rem;
        }

        .app-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .app-icon-inner {
            width: 76px;
            height: 76px;
            font-size: 2.1rem;
            border-radius: 20px;
        }

        .app-module-card {
            padding: 1rem 0.5rem;
            min-height: 155px;
            border-radius: 18px;
        }

        .app-module-title {
            font-size: 0.82rem;
            min-height: 2.2rem;
            font-weight: 600;
        }
    }

    /* ===== SUB MODULE CARDS IN POPUP MODAL (OPTIMIZED) ===== */
    .sub-mod-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 1.4rem 1rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 22px;
        text-decoration: none !important;
        transition: transform 0.2s ease, background 0.2s ease;
        height: 100%;
        transform: translateZ(0);
    }

    .sub-mod-card:hover {
        transform: translateY(-4px);
        background: rgba(255, 255, 255, 0.12);
        border-color: rgba(255, 255, 255, 0.3);
    }

    .sub-icon-wrapper {
        position: relative;
        margin-bottom: 1rem;
    }

    .sub-icon-box {
        position: relative;
        width: 76px;
        height: 76px;
        border-radius: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 2.2rem;
        border: 2px solid rgba(255, 255, 255, 0.35);
        box-shadow: 0 8px 20px -3px var(--icon-shadow, rgba(0, 0, 0, 0.3));
        transition: transform 0.2s ease;
        transform: translateZ(0);
    }

    .sub-mod-card:hover .sub-icon-box {
        transform: scale(1.05);
    }

    .sub-card-title {
        font-size: 0.98rem;
        font-weight: 700;
        color: #ffffff !important;
        margin-bottom: 0.3rem;
        line-height: 1.3;
    }

    .sub-card-desc {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.85) !important;
        line-height: 1.4;
        max-width: 230px;
    }
</style>

<!-- BANNER GREETING PORTAL -->
<div class="card border-0 rounded-4 shadow-sm mb-4 overflow-hidden text-white w-100 position-relative"
    style="background: linear-gradient(135deg, <?= htmlspecialchars($themeColor) ?> 0%, #0f172a 100%);">

    <div class="card-body p-4 position-relative" style="z-index: 2;">
        <div class="row align-items-center">
            <div class="col-lg-8 col-12 mb-3 mb-lg-0">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2"
                    style="background-color: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255, 255, 255, 0.25);">
                    <i class="bi bi-stars text-warning"></i>
                    <span class="small fw-semibold text-white" style="letter-spacing: 0.5px;">Selamat Datang di Portal
                        Utama</span>
                </div>
                <h2 class="fw-extrabold mb-1 display-6 text-white">Ahlan wa Sahlan, <?= htmlspecialchars($userName) ?>
                    👋</h2>
                <p class="text-white opacity-75 mb-0" style="font-size: 0.95rem;">
                    <?= htmlspecialchars($appName) ?> — <?= htmlspecialchars($namaSekolah) ?>.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end text-start d-none d-md-block">
                <div class="p-3 rounded-4 d-inline-block text-center"
                    style="min-width: 170px; background-color: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2);">
                    <div class="display-6 text-warning mb-1"><i class="bi bi-grid-3x3-gap-fill"></i></div>
                    <div class="fw-bold small text-uppercase text-white" style="letter-spacing: 1px;">Status Sistem
                    </div>
                    <span class="badge bg-success rounded-pill px-3 py-1 mt-1">Aktif & Terhubung</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- APP GRID LAYOUT: HIGH PERFORMANCE MODERN ICONS -->
<div class="app-grid-container">
    <div class="app-grid">
        <?php
        $visibleModuleCount = 0;
        foreach ($modules as $mod):
            $allowedPerms = array_filter($mod['permissions'], fn($p) => hasPermission($p));
            if (empty($allowedPerms))
                continue;

            $visibleModuleCount++;

            $targetUrl = BASE_URL . $mod['target'];
            if (!hasPermission(str_replace(['/pages/', '/index.php', '.php'], '', $mod['target']))) {
                foreach ($allowedPerms as $ap) {
                    if (isset($firstAllowedUrl[$ap])) {
                        $targetUrl = BASE_URL . $firstAllowedUrl[$ap];
                        break;
                    }
                }
            }
            ?>
            <?php
            $modalMap = [
                'transaksi' => 'modalSubTransaksi',
                'master_data' => 'modalSubMasterData',
                'akademik' => 'modalSubAkademik',
                'kesantrian' => 'modalSubKesantrian',
                'kantin' => 'modalSubKantin',
                'pengaturan' => 'modalSubPengaturan',
            ];
            ?>
            <a href="#<?= $modalMap[$mod['key']] ?? 'modalSubTransaksi' ?>" class="app-module-card" data-bs-toggle="modal"
                data-bs-target="#<?= $modalMap[$mod['key']] ?? 'modalSubTransaksi' ?>"
                style="--mod-gradient: <?= $mod['gradient'] ?>; --mod-shadow: <?= $mod['shadow'] ?>; --mod-color: <?= $mod['color'] ?>;">
                <!-- Icon container -->
                <div class="app-icon-box">
                    <div class="app-icon-inner" style="background: <?= $mod['gradient'] ?>;">
                        <i class="bi <?= $mod['icon'] ?>"></i>
                    </div>
                </div>

                <!-- Title -->
                <span class="app-module-title"><?= htmlspecialchars($mod['title']) ?></span>
            </a>
        <?php endforeach; ?>

        <?php if ($visibleModuleCount === 0): ?>
            <div class="col-12 text-center py-5 text-muted">
                <i class="bi bi-lock-fill display-3 d-block mb-3 opacity-50"></i>
                <h5>Anda belum memiliki hak akses modul.</h5>
                <p>Silakan hubungi Administrator Sekolah untuk mengaktifkan izin modul Anda.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL SUB-MODUL TRANSAKSI & KEUANGAN (ICON POP-UP MODERN) -->
<div class="modal fade" id="modalSubTransaksi" tabindex="-1" aria-labelledby="modalSubTransaksiLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden"
            style="background: rgba(11, 15, 25, 0.97); backdrop-filter: blur(28px); border: 1px solid rgba(255,255,255,0.18)!important;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 px-md-5">
                <div>
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill mb-2"
                        style="background: rgba(99, 102, 241, 0.25); border: 1px solid rgba(99, 102, 241, 0.45);">
                        <i class="bi bi-cash-coin text-info fs-6"></i>
                        <span class="small fw-bold text-info" style="font-size: 0.75rem; letter-spacing: 0.8px;">MODUL
                            KEUANGAN & TRANSAKSI</span>
                    </div>
                    <h3 class="fw-extrabold text-white mb-1 display-7" id="modalSubTransaksiLabel">Sub-Modul Transaksi &
                        Keuangan</h3>
                    <p class="text-white opacity-75 small mb-0 fs-6">Pilih fitur transaksi, rekapitulasi, atau laporan
                        keuangan yang ingin Anda buka</p>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto mt-2 me-2" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 p-md-5">
                <div class="row g-4">
                    <!-- 1. Dashboard Keuangan -->
                    <?php if (hasPermission('dashboard_utama')): ?>
                        <div class="col-lg-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/transaksi/dashboard.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #6366f1, #3b82f6);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #6366f1, #3b82f6); --icon-shadow: rgba(99, 102, 241, 0.5);">
                                        <i class="bi bi-speedometer2"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Dashboard Keuangan</div>
                                <div class="sub-card-desc">Ringkasan & statistik lengkap transaksi keuangan sekolah</div>
                            </a>
                        </div>
                    <?php endif; ?>
                    <!-- 2. Verifikasi Pembayaran -->
                    <?php if (hasPermission('verifikasi')): ?>
                        <div class="col-lg-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/verifikasi/index.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #10b981, #059669);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #10b981, #059669); --icon-shadow: rgba(16, 185, 129, 0.5);">
                                        <i class="bi bi-shield-check"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Verifikasi Pembayaran</div>
                                <div class="sub-card-desc">Verifikasi bukti transfer wali & status approval</div>
                            </a>
                        </div>
                    <?php endif; ?>
                    <!-- 3. Riwayat Pembayaran -->
                    <?php if (hasPermission('riwayat')): ?>
                        <div class="col-lg-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/riwayat/index.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #0284c7, #06b6d4);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #0284c7, #06b6d4); --icon-shadow: rgba(2, 132, 199, 0.5);">
                                        <i class="bi bi-clock-history"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Riwayat Pembayaran</div>
                                <div class="sub-card-desc">Log riwayat transaksi dan pencarian mutasi</div>
                            </a>
                        </div>
                    <?php endif; ?>
                    <!-- 4. Rekapitulasi Pembayaran -->
                    <?php if (hasPermission('rekap_spp') || hasPermission('rekap_uang_pangkal') || hasPermission('rekap_daftar_ulang')): ?>
                        <div class="col-lg-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/riwayat/rekap-spp.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #0284c7, #06b6d4);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #0284c7, #06b6d4); --icon-shadow: rgba(2, 132, 199, 0.5);">
                                        <i class="bi bi-grid-3x3-gap-fill"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Tampilan Rekap</div>
                                <div class="sub-card-desc">Matriks rekap SPP Kelas, Uang Pangkal & Daftar Ulang</div>
                            </a>
                        </div>
                    <?php endif; ?>
                    <!-- 5. Pembayaran SPP & Lainnya -->
                    <?php if (hasPermission('spp') || hasPermission('uang_pangkal') || hasPermission('pembayaran_lain')): ?>
                        <div class="col-lg-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/spp/index.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #f59e0b, #d97706);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #f59e0b, #d97706); --icon-shadow: rgba(245, 158, 11, 0.5);">
                                        <i class="bi bi-cash-stack"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Pembayaran</div>
                                <div class="sub-card-desc">Loket entri bayar SPP, Uang Pangkal & Tagihan Lain</div>
                            </a>
                        </div>
                    <?php endif; ?>
                    <!-- 6. Laporan Keuangan -->
                    <?php if (hasPermission('laporan')): ?>
                        <div class="col-lg-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/laporan/index.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); --icon-shadow: rgba(139, 92, 246, 0.5);">
                                        <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Laporan Keuangan</div>
                                <div class="sub-card-desc">Cetak laporan kas, rekap bulanan & ekspor data</div>
                            </a>
                        </div>
                    <?php endif; ?>
                    <!-- 7. Petty Cash -->
                    <?php if (hasPermission('petty_cash')): ?>
                        <div class="col-lg-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/petty-cash/index.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #ec4899, #db2777);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #ec4899, #db2777); --icon-shadow: rgba(236, 72, 153, 0.5);">
                                        <i class="bi bi-wallet-fill"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Petty Cash</div>
                                <div class="sub-card-desc">Buku kas kecil & pengelolaan operasional harian</div>
                            </a>
                        </div>
                    <?php endif; ?>
                    <!-- 8. Jenis Pembayaran -->
                    <?php if (hasPermission('jenis_pembayaran')): ?>
                        <div class="col-lg-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/jenis-pembayaran/index.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #0d9488, #14b8a6);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #0d9488, #14b8a6); --icon-shadow: rgba(139, 92, 246, 0.5);">
                                        <i class="bi bi-tags-fill"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Jenis Pembayaran</div>
                                <div class="sub-card-desc">Kelola jenis pos pembayaran & penetapan tarif</div>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL SUB-MODUL MASTER DATA -->
<div class="modal fade" id="modalSubMasterData" tabindex="-1" aria-labelledby="modalSubMasterDataLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden"
            style="background: rgba(11, 15, 25, 0.97); backdrop-filter: blur(28px); border: 1px solid rgba(255,255,255,0.18)!important;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 px-md-5">
                <div>
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill mb-2"
                        style="background: rgba(6, 182, 212, 0.25); border: 1px solid rgba(6, 182, 212, 0.45);">
                        <i class="bi bi-building-gear text-info fs-6"></i>
                        <span class="small fw-bold text-info" style="font-size: 0.75rem; letter-spacing: 0.8px;">MASTER
                            DATA SEKOLAH</span>
                    </div>
                    <h3 class="fw-extrabold text-white mb-1 display-7" id="modalSubMasterDataLabel">Sub-Modul Master
                        Data</h3>
                    <p class="text-white opacity-75 small mb-0 fs-6">Pilih data utama sekolah yang ingin Anda kelola</p>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto mt-2 me-2" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 p-md-5">
                <div class="row g-4">
                    <!-- 1. Data Kelas -->
                    <?php if (hasPermission('kelas')): ?>
                        <div class="col-lg-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/kelas/index.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #06b6d4, #0891b2);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #06b6d4, #0891b2); --icon-shadow: rgba(6, 182, 212, 0.5);">
                                        <i class="bi bi-building"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Data Kelas</div>
                                <div class="sub-card-desc">Kelola tingkat kelas, nama kelas & wali kelas</div>
                            </a>
                        </div>
                    <?php endif; ?>
                    <!-- 2. Data Siswa -->
                    <?php if (hasPermission('siswa')): ?>
                        <div class="col-lg-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/siswa/index.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #3b82f6, #2563eb);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #3b82f6, #2563eb); --icon-shadow: rgba(59, 130, 246, 0.5);">
                                        <i class="bi bi-people-fill"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Data Siswa</div>
                                <div class="sub-card-desc">Kelola biodata siswa, NIS, cetak kartu & status</div>
                            </a>
                        </div>
                    <?php endif; ?>
                    <!-- 3. Data Guru -->
                    <?php if (hasPermission('guru')): ?>
                        <div class="col-lg-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/guru/index.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #10b981, #059669);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #10b981, #059669); --icon-shadow: rgba(16, 185, 129, 0.5);">
                                        <i class="bi bi-person-badge"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Data Guru</div>
                                <div class="sub-card-desc">Kelola profil guru, NIP/NUPTK & kontak pengajar</div>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL SUB-MODUL AKADEMIK & GURU -->
<div class="modal fade" id="modalSubAkademik" tabindex="-1" aria-labelledby="modalSubAkademikLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden"
            style="background: rgba(11, 15, 25, 0.97); backdrop-filter: blur(28px); border: 1px solid rgba(255,255,255,0.18)!important;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 px-md-5">
                <div>
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill mb-2"
                        style="background: rgba(16, 185, 129, 0.25); border: 1px solid rgba(16, 185, 129, 0.45);">
                        <i class="bi bi-journal-bookmark-fill text-success fs-6"></i>
                        <span class="small fw-bold text-success"
                            style="font-size: 0.75rem; letter-spacing: 0.8px;">AKADEMIK & KURIKULUM</span>
                    </div>
                    <h3 class="fw-extrabold text-white mb-1 display-7" id="modalSubAkademikLabel">Sub-Modul Akademik &
                        Guru</h3>
                    <p class="text-white opacity-75 small mb-0 fs-6">Pilih layanan pembelajaran, perangkat ajar, atau
                        nilai siswa</p>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto mt-2 me-2" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 p-md-5">
                <div class="row g-4">
                    <!-- 1. Perangkat Ajar -->
                    <?php if (hasPermission('perangkat_ajar')): ?>
                        <div class="col-lg-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/perangkat-ajar/index.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #10b981, #059669);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #10b981, #059669); --icon-shadow: rgba(16, 185, 129, 0.5);">
                                        <i class="bi bi-journal-check"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Perangkat Ajar</div>
                                <div class="sub-card-desc">Kelola CP, TP, ATP & modul Kurikulum Merdeka</div>
                            </a>
                        </div>
                    <?php endif; ?>
                    <!-- 2. Input Nilai Siswa -->
                    <?php if (hasPermission('input_nilai')): ?>
                        <div class="col-lg-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/nilai/index.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #0284c7, #06b6d4);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #0284c7, #06b6d4); --icon-shadow: rgba(2, 132, 199, 0.5);">
                                        <i class="bi bi-calculator-fill"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Input Nilai Siswa</div>
                                <div class="sub-card-desc">Penginputan nilai sumatif, formatif & leger siswa</div>
                            </a>
                        </div>
                    <?php endif; ?>
                    <!-- 3. Wali Kelas (Rekap Nilai & Catatan) -->
                    <?php if (hasPermission('wali_kelas')): ?>
                        <div class="col-lg-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/wali-kelas/index.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #1e3a8a, #3b82f6);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #1e3a8a, #3b82f6); --icon-shadow: rgba(30, 58, 138, 0.5);">
                                        <i class="bi bi-person-badge-fill"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Wali Kelas (Rekap Nilai)</div>
                                <div class="sub-card-desc">Pantau nilai & catatan santri binaan semua mapel</div>
                            </a>
                        </div>
                    <?php endif; ?>
                    <!-- 4. Rapor Siswa (PTS / PAS) -->
                    <?php if (hasPermission('rapor')): ?>
                        <div class="col-lg-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/rapor/index.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #059669, #10b981);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #059669, #10b981); --icon-shadow: rgba(16, 185, 129, 0.5);">
                                        <i class="bi bi-file-earmark-person-fill"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Rapor Siswa (PTS & PAS)</div>
                                <div class="sub-card-desc">Cetak & generate rapor Kurikulum Merdeka</div>
                            </a>
                        </div>
                    <?php endif; ?>
                    <!-- 4. Mata Pelajaran -->
                    <?php if (hasPermission('mapel')): ?>
                        <div class="col-lg-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/mapel/index.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #14b8a6, #0d9488);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #14b8a6, #0d9488); --icon-shadow: rgba(20, 184, 166, 0.5);">
                                        <i class="bi bi-book-half"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Mata Pelajaran</div>
                                <div class="sub-card-desc">Kelola kurikulum & daftar mata pelajaran</div>
                            </a>
                        </div>
                    <?php endif; ?>
                    <!-- 5. Plotting Guru Pengampu -->
                    <?php if (hasPermission('plotting_guru')): ?>
                        <div class="col-lg-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/mapel/plotting.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #f59e0b, #d97706);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #f59e0b, #d97706); --icon-shadow: rgba(245, 158, 11, 0.5);">
                                        <i class="bi bi-person-workspace"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Guru Pengampu</div>
                                <div class="sub-card-desc">Plotting penugasan guru pengajar per kelas</div>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
</div>
</div>

<!-- MODAL SUB-MODUL KESANTRIAN -->
<div class="modal fade" id="modalSubKesantrian" tabindex="-1" aria-labelledby="modalSubKesantrianLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden"
            style="background: rgba(11, 15, 25, 0.97); backdrop-filter: blur(28px); border: 1px solid rgba(255,255,255,0.18)!important;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 px-md-5">
                <div>
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill mb-2"
                        style="background: rgba(139, 92, 246, 0.25); border: 1px solid rgba(139, 92, 246, 0.45);">
                        <i class="bi bi-moon-stars-fill fs-6" style="color: #a78bfa;"></i>
                        <span class="small fw-bold"
                            style="font-size: 0.75rem; letter-spacing: 0.8px; color: #a78bfa;">KESANTRIAN &
                            BOARDING</span>
                    </div>
                    <h3 class="fw-extrabold text-white mb-1 display-7" id="modalSubKesantrianLabel">Sub-Modul Kesantrian
                    </h3>
                    <p class="text-white opacity-75 small mb-0 fs-6">Pilih layanan halaqah setoran hafalan, pengaturan
                        kelompok, atau laporan progress siswa</p>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto mt-2 me-2" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 p-md-5">
                <div class="row g-4">
                    <!-- 1. Pencatatan Setoran -->
                    <?php if (hasPermission('halaqah')): ?>
                        <div class="col-lg-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/halaqah/index.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #8b5cf6, #6d28d9); --icon-shadow: rgba(139, 92, 246, 0.5);">
                                        <i class="bi bi-journal-plus"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Halaqah & Tahfidz (Setoran)</div>
                                <div class="sub-card-desc">Input setoran ziyadah, muroja'ah, tahsin & ujian tahfidz</div>
                            </a>
                        </div>
                        <!-- 2. Pengaturan Halaqah & Kelompok -->
                        <div class="col-lg-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/halaqah/manage.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #0284c7, #0369a1);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #0284c7, #0369a1); --icon-shadow: rgba(2, 132, 199, 0.5);">
                                        <i class="bi bi-gear-fill"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Pengaturan Halaqah & Kelompok</div>
                                <div class="sub-card-desc">Kelola kategori halaqah, nama kelompok & anggota siswa</div>
                            </a>
                        </div>
                        <!-- 3. Laporan Progress Tahfidz -->
                        <div class="col-lg-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/halaqah/laporan.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #ec4899, #be185d);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #ec4899, #be185d); --icon-shadow: rgba(236, 72, 153, 0.5);">
                                        <i class="bi bi-file-earmark-bar-graph-fill"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Laporan & Progress Tahfidz</div>
                                <div class="sub-card-desc">Rekapitulasi pencapaian hafalan & ekspor laporan PDF</div>
                            </a>
                        </div>
                    <?php endif; ?>
                    <!-- 4. Poin Kesantrian & Disiplin -->
                    <?php if (hasPermission('kesantrian')): ?>
                        <div class="col-lg-4 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/halaqah/poin_dashboard.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #f59e0b, #d97706);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #f59e0b, #d97706); --icon-shadow: rgba(245, 158, 11, 0.5);">
                                        <i class="bi bi-trophy-fill"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Poin Kesantrian & Disiplin</div>
                                <div class="sub-card-desc">Kelola poin pelanggaran & penghargaan siswa dengan leaderboard
                                </div>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL SUB-MODUL KANTIN SEKOLAH -->
<div class="modal fade" id="modalSubKantin" tabindex="-1" aria-labelledby="modalSubKantinLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden"
            style="background: rgba(11, 15, 25, 0.97); backdrop-filter: blur(28px); border: 1px solid rgba(255,255,255,0.18)!important;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 px-md-5">
                <div>
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill mb-2"
                        style="background: rgba(249, 115, 22, 0.25); border: 1px solid rgba(249, 115, 22, 0.45);">
                        <i class="bi bi-shop text-warning fs-6"></i>
                        <span class="small fw-bold text-warning"
                            style="font-size: 0.75rem; letter-spacing: 0.8px;">KANTIN & KASIR E-MONEY</span>
                    </div>
                    <h3 class="fw-extrabold text-white mb-1 display-7" id="modalSubKantinLabel">Sub-Modul Kantin Sekolah
                    </h3>
                    <p class="text-white opacity-75 small mb-0 fs-6">Pilih layanan kasir POS, top-up saldo, atau
                        manajemen menu kantin</p>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto mt-2 me-2" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 p-md-5">
                <div class="row g-4">
                    <!-- 1. Kasir POS Kantin -->
                    <?php if (hasPermission('kantin')): ?>
                        <div class="col-lg-3 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/kantin/index.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #f97316, #ea580c);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #f97316, #ea580c); --icon-shadow: rgba(249, 115, 22, 0.5);">
                                        <i class="bi bi-calculator-fill"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Kasir POS Kantin</div>
                                <div class="sub-card-desc">Mesin kasir transaksi non-tunai siswa</div>
                            </a>
                        </div>
                        <!-- 2. Top-Up Saldo Siswa -->
                        <div class="col-lg-3 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/kantin/topup.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #10b981, #059669);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #10b981, #059669); --icon-shadow: rgba(16, 185, 129, 0.5);">
                                        <i class="bi bi-wallet2"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Top-Up Saldo</div>
                                <div class="sub-card-desc">Isi ulang saldo kartu e-money jajan siswa</div>
                            </a>
                        </div>
                        <!-- 3. Kelola Menu Kantin -->
                        <div class="col-lg-3 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/kantin/menu.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #eab308, #ca8a04);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #eab308, #ca8a04); --icon-shadow: rgba(234, 179, 8, 0.5);">
                                        <i class="bi bi-egg-fried"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Kelola Menu</div>
                                <div class="sub-card-desc">Atur katalog makanan, minuman & stok kantin</div>
                            </a>
                        </div>
                        <!-- 4. Laporan Penjualan -->
                        <div class="col-lg-3 col-sm-6">
                            <a href="<?= BASE_URL ?>/pages/kantin/laporan.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #6366f1, #4f46e5);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #6366f1, #4f46e5); --icon-shadow: rgba(99, 102, 241, 0.5);">
                                        <i class="bi bi-graph-up"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Laporan Penjualan</div>
                                <div class="sub-card-desc">Rekap omzet harian & laporan transaksi kantin</div>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL SUB-MODUL PENGATURAN SISTEM -->
<div class="modal fade" id="modalSubPengaturan" tabindex="-1" aria-labelledby="modalSubPengaturanLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden"
            style="background: rgba(11, 15, 25, 0.97); backdrop-filter: blur(28px); border: 1px solid rgba(255,255,255,0.18)!important;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 px-md-5">
                <div>
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill mb-2"
                        style="background: rgba(239, 68, 68, 0.25); border: 1px solid rgba(239, 68, 68, 0.45);">
                        <i class="bi bi-gear-wide-connected text-danger fs-6"></i>
                        <span class="small fw-bold text-danger"
                            style="font-size: 0.75rem; letter-spacing: 0.8px;">PENGATURAN & KEAMANAN</span>
                    </div>
                    <h3 class="fw-extrabold text-white mb-1 display-7" id="modalSubPengaturanLabel">Sub-Modul Pengaturan
                        Sistem</h3>
                    <p class="text-white opacity-75 small mb-0 fs-6">Pilih opsi manajemen pengguna atau konfigurasi
                        aplikasi</p>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto mt-2 me-2" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 p-md-5">
                <div class="row g-4">
                    <!-- 1. Kelola User -->
                    <?php if (hasPermission('users')): ?>
                        <div class="col-lg-4 col-md-6 col-sm-12">
                            <a href="<?= BASE_URL ?>/pages/users/index.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #ef4444, #dc2626);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #ef4444, #dc2626); --icon-shadow: rgba(239, 68, 68, 0.5);">
                                        <i class="bi bi-person-gear"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Kelola User</div>
                                <div class="sub-card-desc">Manajemen akun staf, admin, role & hak akses pengguna</div>
                            </a>
                        </div>
                    <?php endif; ?>
                    <!-- 2. Pengaturan Aplikasi -->
                    <?php if (hasPermission('pengaturan')): ?>
                        <div class="col-lg-4 col-md-6 col-sm-12">
                            <a href="<?= BASE_URL ?>/pages/pengaturan/index.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #64748b, #475569);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #64748b, #475569); --icon-shadow: rgba(100, 116, 139, 0.5);">
                                        <i class="bi bi-gear-fill"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Pengaturan Aplikasi</div>
                                <div class="sub-card-desc">Konfigurasi nama sekolah, logo, tema & parameter sistem</div>
                            </a>
                        </div>
                        <!-- 3. Pengaturan Portal Ortu -->
                        <div class="col-lg-4 col-md-6 col-sm-12">
                            <a href="<?= BASE_URL ?>/pages/pengaturan/portal-ortu.php" class="sub-mod-card">
                                <div class="sub-icon-wrapper">
                                    <div class="sub-icon-aura"
                                        style="background: linear-gradient(135deg, #0284c7, #0369a1);"></div>
                                    <div class="sub-icon-box"
                                        style="background: linear-gradient(135deg, #0284c7, #0369a1); --icon-shadow: rgba(2, 132, 199, 0.5);">
                                        <i class="bi bi-phone-fill"></i>
                                        <div class="sub-icon-shine"></div>
                                    </div>
                                </div>
                                <div class="sub-card-title">Pengaturan Portal Ortu</div>
                                <div class="sub-card-desc">Kontrol menu/fitur portal, nomor WhatsApp wali kelas & preferensi
                                </div>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>