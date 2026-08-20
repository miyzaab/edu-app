<?php
/**
 * PORTAL UTAMA (APP LAUNCHER) - 5 Modul Utama (Glassmorphism & Large Icon Cards)
 * Halaman depan serbaguna tanpa sidebar ΓÇö Glassmorphism, Responsive & Dynamic Theme
 */
$pageTitle  = 'Portal Utama';
$activePage = 'dashboard';
require_once __DIR__ . '/../config/auth.php';

$pdo = getConnection();
$namaSekolah = getSetting('nama_sekolah', SCHOOL_NAME);
$appName     = getSetting('app_name', APP_NAME);
$userName    = $_SESSION['nama_lengkap'] ?? 'User';
$userRole    = $_SESSION['role'] ?? 'guru';
$themeColor  = getSetting('theme_color', '#4f46e5');

// Daftar 5 Modul Utama
$modules = [
    [
        'key'         => 'transaksi',
        'title'       => 'Transaksi & Keuangan',
        'icon'        => 'bi-cash-coin',
        'gradient'    => 'linear-gradient(135deg, #6366f1 0%, #4f46e5 100%)',
        'shadow'      => 'rgba(99, 102, 241, 0.4)',
        'color'       => '#4f46e5',
        'target'      => '/pages/transaksi/dashboard.php',
        'permissions' => ['dashboard_utama', 'verifikasi', 'riwayat', 'rekap_spp', 'rekap_uang_pangkal', 'rekap_daftar_ulang', 'spp', 'uang_pangkal', 'pembayaran_lain', 'jenis_pembayaran', 'petty_cash', 'laporan']
    ],
    [
        'key'         => 'master_data',
        'title'       => 'Master Data',
        'icon'        => 'bi-building-gear',
        'gradient'    => 'linear-gradient(135deg, #06b6d4 0%, #0891b2 100%)',
        'shadow'      => 'rgba(6, 182, 212, 0.4)',
        'color'       => '#0891b2',
        'target'      => '/pages/kelas/index.php',
        'permissions' => ['kelas', 'siswa', 'guru']
    ],
    [
        'key'         => 'akademik',
        'title'       => 'Akademik & Guru',
        'icon'        => 'bi-journal-bookmark-fill',
        'gradient'    => 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
        'shadow'      => 'rgba(16, 185, 129, 0.4)',
        'color'       => '#059669',
        'target'      => '/pages/perangkat-ajar/index.php',
        'permissions' => ['perangkat_ajar', 'input_nilai', 'mapel', 'plotting_guru']
    ],
    [
        'key'         => 'kesantrian',
        'title'       => 'Kesantrian',
        'icon'        => 'bi-moon-stars-fill',
        'gradient'    => 'linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%)',
        'shadow'      => 'rgba(139, 92, 246, 0.4)',
        'color'       => '#8b5cf6',
        'target'      => '/pages/halaqah/index.php',
        'permissions' => ['halaqah', 'kesantrian']
    ],
    [
        'key'         => 'kantin',
        'title'       => 'Kantin Sekolah',
        'icon'        => 'bi-shop',
        'gradient'    => 'linear-gradient(135deg, #f97316 0%, #ea580c 100%)',
        'shadow'      => 'rgba(249, 115, 22, 0.4)',
        'color'       => '#ea580c',
        'target'      => '/pages/kantin/index.php',
        'permissions' => ['kantin']
    ],
    [
        'key'         => 'pengaturan',
        'title'       => 'Pengaturan Sistem',
        'icon'        => 'bi-gear-wide-connected',
        'gradient'    => 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
        'shadow'      => 'rgba(239, 68, 68, 0.4)',
        'color'       => '#dc2626',
        'target'      => '/pages/users/index.php',
        'permissions' => ['users', 'pengaturan']
    ]
];

// Map URL default per permission jika target pertama tidak diizinkan
$firstAllowedUrl = [
    'dashboard_utama'    => '/pages/transaksi/dashboard.php',
    'verifikasi'         => '/pages/verifikasi/index.php',
    'riwayat'            => '/pages/riwayat/index.php',
    'rekap_spp'          => '/pages/riwayat/rekap-spp.php',
    'rekap_uang_pangkal' => '/pages/riwayat/rekap-uang-pangkal.php',
    'rekap_daftar_ulang' => '/pages/riwayat/rekap-daftar-ulang.php',
    'spp'                => '/pages/spp/index.php',
    'uang_pangkal'       => '/pages/uang-pangkal/index.php',
    'pembayaran_lain'    => '/pages/pembayaran-lain/index.php',
    'kantin'             => '/pages/kantin/index.php',
    'kelas'              => '/pages/kelas/index.php',
    'siswa'              => '/pages/siswa/index.php',
    'guru'               => '/pages/guru/index.php',
    'jenis_pembayaran'   => '/pages/jenis-pembayaran/index.php',
    'perangkat_ajar'     => '/pages/perangkat-ajar/index.php',
    'input_nilai'        => '/pages/nilai/index.php',
    'mapel'              => '/pages/mapel/index.php',
    'plotting_guru'      => '/pages/mapel/plotting.php',
    'halaqah'            => '/pages/halaqah/index.php',
    'petty_cash'         => '/pages/petty-cash/index.php',
    'laporan'            => '/pages/laporan/index.php',
    'users'              => '/pages/users/index.php',
    'pengaturan'         => '/pages/pengaturan/index.php',
];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- BANNER GREETING PORTAL -->
<div class="card border-0 rounded-4 shadow-sm mb-5 overflow-hidden text-white w-100 position-relative" 
     style="background: linear-gradient(135deg, <?= htmlspecialchars($themeColor) ?> 0%, #0f172a 100%);">
    <!-- Subtle Background Pattern Overlay -->
    <div class="position-absolute top-0 end-0 bottom-0 start-0 opacity-10" 
         style="background-image: radial-gradient(circle at 20% 20%, rgba(255,255,255,0.4) 0%, transparent 40%), radial-gradient(circle at 80% 80%, rgba(255,255,255,0.3) 0%, transparent 40%); pointer-events: none;"></div>
    
    <div class="card-body p-4 p-md-5 position-relative" style="z-index: 2;">
        <div class="row align-items-center">
            <div class="col-lg-8 col-12 mb-3 mb-lg-0">
                <!-- FIX BUG: Tampilan background diset manual transparan agar teks tidak hilang -->
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" 
                     style="background-color: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255, 255, 255, 0.25); backdrop-filter: blur(8px);">
                    <i class="bi bi-stars text-warning"></i>
                    <span class="small fw-semibold text-white" style="letter-spacing: 0.5px;">Selamat Datang di Portal Utama</span>
                </div>
                <h2 class="fw-extrabold mb-2 display-6 text-white">Ahlan wa Sahlan, <?= htmlspecialchars($userName) ?> 👋</h2>
                <p class="text-white opacity-75 mb-0" style="font-size: 1.05rem;">
                    <?= htmlspecialchars($appName) ?> — <?= htmlspecialchars($namaSekolah) ?>.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end text-start d-none d-md-block">
                <div class="p-3 rounded-4 d-inline-block text-center" 
                     style="min-width: 170px; background-color: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); backdrop-filter: blur(8px);">
                    <div class="display-6 text-warning mb-1"><i class="bi bi-grid-3x3-gap-fill"></i></div>
                    <div class="fw-bold small text-uppercase text-white" style="letter-spacing: 1px;">Status Sistem</div>
                    <span class="badge bg-success rounded-pill px-3 py-1 mt-1">Aktif & Terhubung</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- APP GRID LAYOUT: PREMIUM MODERN ICONS -->
<div class="app-grid-container">
    <div class="app-grid">
        <?php
        $visibleModuleCount = 0;
        foreach ($modules as $mod):
            $allowedPerms = array_filter($mod['permissions'], fn($p) => hasPermission($p));
            if (empty($allowedPerms)) continue;

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
            'transaksi'   => 'modalSubTransaksi',
            'master_data' => 'modalSubMasterData',
            'akademik'    => 'modalSubAkademik',
            'kesantrian'  => 'modalSubKesantrian',
            'kantin'      => 'modalSubKantin',
            'pengaturan'  => 'modalSubPengaturan',
        ];
        ?>
        <a href="#<?= $modalMap[$mod['key']] ?? 'modalSubTransaksi' ?>" 
           class="app-module-card" 
           data-bs-toggle="modal" 
           data-bs-target="#<?= $modalMap[$mod['key']] ?? 'modalSubTransaksi' ?>"
           style="--mod-gradient: <?= $mod['gradient'] ?>; --mod-shadow: <?= $mod['shadow'] ?>; --mod-color: <?= $mod['color'] ?>;">
            <!-- Animated glow ring -->
            <div class="app-glow-ring"></div>
            
            <!-- Icon container -->
            <div class="app-icon-box">
                <div class="app-icon-inner" style="background: <?= $mod['gradient'] ?>;">
                    <i class="bi <?= $mod['icon'] ?>"></i>
                </div>
                <!-- Shine sweep effect -->
                <div class="app-icon-shine"></div>
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
<div class="modal fade" id="modalSubTransaksi" tabindex="-1" aria-labelledby="modalSubTransaksiLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden" style="background: rgba(11, 15, 25, 0.97); backdrop-filter: blur(28px); border: 1px solid rgba(255,255,255,0.18)!important;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 px-md-5">
                <div>
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill mb-2" style="background: rgba(99, 102, 241, 0.25); border: 1px solid rgba(99, 102, 241, 0.45);">
                        <i class="bi bi-cash-coin text-info fs-6"></i>
                        <span class="small fw-bold text-info" style="font-size: 0.75rem; letter-spacing: 0.8px;">MODUL KEUANGAN & TRANSAKSI</span>
                    </div>
                    <h3 class="fw-extrabold text-white mb-1 display-7" id="modalSubTransaksiLabel">Sub-Modul Transaksi & Keuangan</h3>
                    <p class="text-white opacity-75 small mb-0 fs-6">Pilih fitur transaksi, rekapitulasi, atau laporan keuangan yang ingin Anda buka</p>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 p-md-5">
                <div class="row g-4">
                    <!-- 1. Dashboard Keuangan -->
                    <div class="col-lg-4 col-sm-6">
                        <a href="<?= BASE_URL ?>/pages/transaksi/dashboard.php" class="sub-mod-card">
                            <div class="sub-icon-wrapper">
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #6366f1, #3b82f6);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #6366f1, #3b82f6); --icon-shadow: rgba(99, 102, 241, 0.5);">
                                    <i class="bi bi-speedometer2"></i>
                                    <div class="sub-icon-shine"></div>
                                </div>
                            </div>
                            <div class="sub-card-title">Dashboard Keuangan</div>
                            <div class="sub-card-desc">Ringkasan & statistik lengkap transaksi keuangan sekolah</div>
                        </a>
                    </div>
                    <!-- 2. Verifikasi & Riwayat Pembayaran -->
                    <div class="col-lg-4 col-sm-6">
                        <a href="<?= BASE_URL ?>/pages/verifikasi/index.php" class="sub-mod-card">
                            <div class="sub-icon-wrapper">
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #10b981, #059669);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #10b981, #059669); --icon-shadow: rgba(16, 185, 129, 0.5);">
                                    <i class="bi bi-shield-check"></i>
                                    <div class="sub-icon-shine"></div>
                                </div>
                            </div>
                            <div class="sub-card-title">Verifikasi & Riwayat</div>
                            <div class="sub-card-desc">Verifikasi bukti transfer wali & cek riwayat bayar</div>
                        </a>
                    </div>
                    <!-- 3. Tampilan Rekap -->
                    <div class="col-lg-4 col-sm-6">
                        <a href="<?= BASE_URL ?>/pages/riwayat/rekap-spp.php" class="sub-mod-card">
                            <div class="sub-icon-wrapper">
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #0284c7, #06b6d4);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #0284c7, #06b6d4); --icon-shadow: rgba(2, 132, 199, 0.5);">
                                    <i class="bi bi-grid-3x3-gap-fill"></i>
                                    <div class="sub-icon-shine"></div>
                                </div>
                            </div>
                            <div class="sub-card-title">Tampilan Rekap</div>
                            <div class="sub-card-desc">Matriks rekap SPP Kelas, Uang Pangkal & Daftar Ulang</div>
                        </a>
                    </div>
                    <!-- 4. Pembayaran -->
                    <div class="col-lg-4 col-sm-6">
                        <a href="<?= BASE_URL ?>/pages/spp/index.php" class="sub-mod-card">
                            <div class="sub-icon-wrapper">
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #f59e0b, #d97706);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #f59e0b, #d97706); --icon-shadow: rgba(245, 158, 11, 0.5);">
                                    <i class="bi bi-cash-stack"></i>
                                    <div class="sub-icon-shine"></div>
                                </div>
                            </div>
                            <div class="sub-card-title">Pembayaran</div>
                            <div class="sub-card-desc">Loket entri bayar SPP, Uang Pangkal & Tagihan Lain</div>
                        </a>
                    </div>
                    <!-- 5. Laporan Keuangan -->
                    <div class="col-lg-4 col-sm-6">
                        <a href="<?= BASE_URL ?>/pages/laporan/index.php" class="sub-mod-card">
                            <div class="sub-icon-wrapper">
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); --icon-shadow: rgba(139, 92, 246, 0.5);">
                                    <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                                    <div class="sub-icon-shine"></div>
                                </div>
                            </div>
                            <div class="sub-card-title">Laporan Keuangan</div>
                            <div class="sub-card-desc">Cetak laporan kas, rekap bulanan & ekspor data</div>
                        </a>
                    </div>
                    <!-- 6. Petty Cash -->
                    <div class="col-lg-4 col-sm-6">
                        <a href="<?= BASE_URL ?>/pages/petty-cash/index.php" class="sub-mod-card">
                            <div class="sub-icon-wrapper">
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #ec4899, #db2777);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #ec4899, #db2777); --icon-shadow: rgba(236, 72, 153, 0.5);">
                                    <i class="bi bi-wallet-fill"></i>
                                    <div class="sub-icon-shine"></div>
                                </div>
                            </div>
                            <div class="sub-card-title">Petty Cash</div>
                            <div class="sub-card-desc">Buku kas kecil & pengelolaan operasional harian</div>
                        </a>
                    </div>
                    <!-- 7. Jenis Pembayaran -->
                    <div class="col-lg-4 col-sm-6">
                        <a href="<?= BASE_URL ?>/pages/jenis-pembayaran/index.php" class="sub-mod-card">
                            <div class="sub-icon-wrapper">
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #0d9488, #14b8a6);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #0d9488, #14b8a6); --icon-shadow: rgba(13, 148, 136, 0.5);">
                                    <i class="bi bi-tags-fill"></i>
                                    <div class="sub-icon-shine"></div>
                                </div>
                            </div>
                            <div class="sub-card-title">Jenis Pembayaran</div>
                            <div class="sub-card-desc">Kelola jenis pos pembayaran & penetapan tarif</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL SUB-MODUL MASTER DATA -->
<div class="modal fade" id="modalSubMasterData" tabindex="-1" aria-labelledby="modalSubMasterDataLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden" style="background: rgba(11, 15, 25, 0.97); backdrop-filter: blur(28px); border: 1px solid rgba(255,255,255,0.18)!important;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 px-md-5">
                <div>
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill mb-2" style="background: rgba(6, 182, 212, 0.25); border: 1px solid rgba(6, 182, 212, 0.45);">
                        <i class="bi bi-building-gear text-info fs-6"></i>
                        <span class="small fw-bold text-info" style="font-size: 0.75rem; letter-spacing: 0.8px;">MASTER DATA SEKOLAH</span>
                    </div>
                    <h3 class="fw-extrabold text-white mb-1 display-7" id="modalSubMasterDataLabel">Sub-Modul Master Data</h3>
                    <p class="text-white opacity-75 small mb-0 fs-6">Pilih data utama sekolah yang ingin Anda kelola</p>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 p-md-5">
                <div class="row g-4">
                    <!-- 1. Data Kelas -->
                    <div class="col-lg-4 col-sm-6">
                        <a href="<?= BASE_URL ?>/pages/kelas/index.php" class="sub-mod-card">
                            <div class="sub-icon-wrapper">
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #06b6d4, #0891b2);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #06b6d4, #0891b2); --icon-shadow: rgba(6, 182, 212, 0.5);">
                                    <i class="bi bi-building"></i>
                                    <div class="sub-icon-shine"></div>
                                </div>
                            </div>
                            <div class="sub-card-title">Data Kelas</div>
                            <div class="sub-card-desc">Kelola tingkat kelas, nama kelas & wali kelas</div>
                        </a>
                    </div>
                    <!-- 2. Data Siswa -->
                    <div class="col-lg-4 col-sm-6">
                        <a href="<?= BASE_URL ?>/pages/siswa/index.php" class="sub-mod-card">
                            <div class="sub-icon-wrapper">
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #3b82f6, #2563eb);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #3b82f6, #2563eb); --icon-shadow: rgba(59, 130, 246, 0.5);">
                                    <i class="bi bi-people-fill"></i>
                                    <div class="sub-icon-shine"></div>
                                </div>
                            </div>
                            <div class="sub-card-title">Data Siswa</div>
                            <div class="sub-card-desc">Kelola biodata siswa, NIS, cetak kartu & status</div>
                        </a>
                    </div>
                    <!-- 3. Data Guru -->
                    <div class="col-lg-4 col-sm-6">
                        <a href="<?= BASE_URL ?>/pages/guru/index.php" class="sub-mod-card">
                            <div class="sub-icon-wrapper">
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #10b981, #059669);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #10b981, #059669); --icon-shadow: rgba(16, 185, 129, 0.5);">
                                    <i class="bi bi-person-badge"></i>
                                    <div class="sub-icon-shine"></div>
                                </div>
                            </div>
                            <div class="sub-card-title">Data Guru</div>
                            <div class="sub-card-desc">Kelola profil guru, NIP/NUPTK & kontak pengajar</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL SUB-MODUL AKADEMIK & GURU -->
<div class="modal fade" id="modalSubAkademik" tabindex="-1" aria-labelledby="modalSubAkademikLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden" style="background: rgba(11, 15, 25, 0.97); backdrop-filter: blur(28px); border: 1px solid rgba(255,255,255,0.18)!important;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 px-md-5">
                <div>
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill mb-2" style="background: rgba(16, 185, 129, 0.25); border: 1px solid rgba(16, 185, 129, 0.45);">
                        <i class="bi bi-journal-bookmark-fill text-success fs-6"></i>
                        <span class="small fw-bold text-success" style="font-size: 0.75rem; letter-spacing: 0.8px;">AKADEMIK & KURIKULUM</span>
                    </div>
                    <h3 class="fw-extrabold text-white mb-1 display-7" id="modalSubAkademikLabel">Sub-Modul Akademik & Guru</h3>
                    <p class="text-white opacity-75 small mb-0 fs-6">Pilih layanan pembelajaran, perangkat ajar, atau nilai siswa</p>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 p-md-5">
                <div class="row g-4">
                    <!-- 1. Dashboard Perangkat Ajar -->
                    <div class="col-lg-4 col-sm-6">
                        <a href="<?= BASE_URL ?>/pages/perangkat-ajar/index.php" class="sub-mod-card">
                            <div class="sub-icon-wrapper">
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #10b981, #059669);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #10b981, #059669); --icon-shadow: rgba(16, 185, 129, 0.5);">
                                    <i class="bi bi-journal-check"></i>
                                    <div class="sub-icon-shine"></div>
                                </div>
                            </div>
                            <div class="sub-card-title">Dashboard Perangkat Ajar</div>
                            <div class="sub-card-desc">Kelola & cetak dokumen Kurikulum Merdeka</div>
                        </a>
                    </div>
                    <!-- 6. Input Nilai Siswa -->
                    <div class="col-lg-4 col-sm-6">
                        <a href="<?= BASE_URL ?>/pages/nilai/index.php" class="sub-mod-card">
                            <div class="sub-icon-wrapper">
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #0284c7, #06b6d4);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #0284c7, #06b6d4); --icon-shadow: rgba(2, 132, 199, 0.5);">
                                    <i class="bi bi-calculator-fill"></i>
                                    <div class="sub-icon-shine"></div>
                                </div>
                            </div>
                            <div class="sub-card-title">Input Nilai Siswa</div>
                            <div class="sub-card-desc">Penginputan nilai harian, ujian & leger siswa</div>
                        </a>
                    </div>
                    <!-- 7. Mata Pelajaran -->
                    <div class="col-lg-4 col-sm-6">
                        <a href="<?= BASE_URL ?>/pages/mapel/index.php" class="sub-mod-card">
                            <div class="sub-icon-wrapper">
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #14b8a6, #0d9488);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #14b8a6, #0d9488); --icon-shadow: rgba(20, 184, 166, 0.5);">
                                    <i class="bi bi-book-half"></i>
                                    <div class="sub-icon-shine"></div>
                                </div>
                            </div>
                            <div class="sub-card-title">Mata Pelajaran</div>
                            <div class="sub-card-desc">Kelola kurikulum & daftar mata pelajaran</div>
                        </a>
                    </div>
                    <!-- 8. Guru Pengampu -->
                    <div class="col-lg-4 col-sm-6">
                        <a href="<?= BASE_URL ?>/pages/mapel/plotting.php" class="sub-mod-card">
                            <div class="sub-icon-wrapper">
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #f59e0b, #d97706);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #f59e0b, #d97706); --icon-shadow: rgba(245, 158, 11, 0.5);">
                                    <i class="bi bi-person-workspace"></i>
                                    <div class="sub-icon-shine"></div>
                                </div>
                            </div>
                            <div class="sub-card-title">Guru Pengampu</div>
                            <div class="sub-card-desc">Plotting penugasan guru pengajar per kelas</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL SUB-MODUL KESANTRIAN -->
<div class="modal fade" id="modalSubKesantrian" tabindex="-1" aria-labelledby="modalSubKesantrianLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden" style="background: rgba(11, 15, 25, 0.97); backdrop-filter: blur(28px); border: 1px solid rgba(255,255,255,0.18)!important;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 px-md-5">
                <div>
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill mb-2" style="background: rgba(139, 92, 246, 0.25); border: 1px solid rgba(139, 92, 246, 0.45);">
                        <i class="bi bi-moon-stars-fill fs-6" style="color: #a78bfa;"></i>
                        <span class="small fw-bold" style="font-size: 0.75rem; letter-spacing: 0.8px; color: #a78bfa;">KESANTRIAN & BOARDING</span>
                    </div>
                    <h3 class="fw-extrabold text-white mb-1 display-7" id="modalSubKesantrianLabel">Sub-Modul Kesantrian</h3>
                    <p class="text-white opacity-75 small mb-0 fs-6">Pilih layanan halaqah setoran hafalan, pengaturan kelompok, atau laporan progress siswa</p>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 p-md-5">
                <div class="row g-4">
                    <!-- 1. Pencatatan Setoran -->
                    <?php if (hasPermission('halaqah')): ?>
                    <div class="col-lg-4 col-sm-6">
                        <a href="<?= BASE_URL ?>/pages/halaqah/index.php" class="sub-mod-card">
                            <div class="sub-icon-wrapper">
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9); --icon-shadow: rgba(139, 92, 246, 0.5);">
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
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #0284c7, #0369a1);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #0284c7, #0369a1); --icon-shadow: rgba(2, 132, 199, 0.5);">
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
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #ec4899, #be185d);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #ec4899, #be185d); --icon-shadow: rgba(236, 72, 153, 0.5);">
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
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #f59e0b, #d97706);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #f59e0b, #d97706); --icon-shadow: rgba(245, 158, 11, 0.5);">
                                    <i class="bi bi-trophy-fill"></i>
                                    <div class="sub-icon-shine"></div>
                                </div>
                            </div>
                            <div class="sub-card-title">Poin Kesantrian & Disiplin</div>
                            <div class="sub-card-desc">Kelola poin pelanggaran & penghargaan siswa dengan leaderboard</div>
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
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden" style="background: rgba(11, 15, 25, 0.97); backdrop-filter: blur(28px); border: 1px solid rgba(255,255,255,0.18)!important;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 px-md-5">
                <div>
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill mb-2" style="background: rgba(249, 115, 22, 0.25); border: 1px solid rgba(249, 115, 22, 0.45);">
                        <i class="bi bi-shop text-warning fs-6"></i>
                        <span class="small fw-bold text-warning" style="font-size: 0.75rem; letter-spacing: 0.8px;">KANTIN & KASIR E-MONEY</span>
                    </div>
                    <h3 class="fw-extrabold text-white mb-1 display-7" id="modalSubKantinLabel">Sub-Modul Kantin Sekolah</h3>
                    <p class="text-white opacity-75 small mb-0 fs-6">Pilih layanan kasir POS, top-up saldo, atau manajemen menu kantin</p>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 p-md-5">
                <div class="row g-4">
                    <!-- 1. Kasir POS Kantin -->
                    <div class="col-lg-3 col-sm-6">
                        <a href="<?= BASE_URL ?>/pages/kantin/index.php" class="sub-mod-card">
                            <div class="sub-icon-wrapper">
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #f97316, #ea580c);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #f97316, #ea580c); --icon-shadow: rgba(249, 115, 22, 0.5);">
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
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #10b981, #059669);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #10b981, #059669); --icon-shadow: rgba(16, 185, 129, 0.5);">
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
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #eab308, #ca8a04);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #eab308, #ca8a04); --icon-shadow: rgba(234, 179, 8, 0.5);">
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
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #6366f1, #4f46e5);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #6366f1, #4f46e5); --icon-shadow: rgba(99, 102, 241, 0.5);">
                                    <i class="bi bi-graph-up"></i>
                                    <div class="sub-icon-shine"></div>
                                </div>
                            </div>
                            <div class="sub-card-title">Laporan Penjualan</div>
                            <div class="sub-card-desc">Rekap omzet harian & laporan transaksi kantin</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL SUB-MODUL PENGATURAN SISTEM -->
<div class="modal fade" id="modalSubPengaturan" tabindex="-1" aria-labelledby="modalSubPengaturanLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden" style="background: rgba(11, 15, 25, 0.97); backdrop-filter: blur(28px); border: 1px solid rgba(255,255,255,0.18)!important;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 px-md-5">
                <div>
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill mb-2" style="background: rgba(239, 68, 68, 0.25); border: 1px solid rgba(239, 68, 68, 0.45);">
                        <i class="bi bi-gear-wide-connected text-danger fs-6"></i>
                        <span class="small fw-bold text-danger" style="font-size: 0.75rem; letter-spacing: 0.8px;">PENGATURAN & KEAMANAN</span>
                    </div>
                    <h3 class="fw-extrabold text-white mb-1 display-7" id="modalSubPengaturanLabel">Sub-Modul Pengaturan Sistem</h3>
                    <p class="text-white opacity-75 small mb-0 fs-6">Pilih opsi manajemen pengguna atau konfigurasi aplikasi</p>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 p-md-5">
                <div class="row g-4">
                    <!-- 1. Kelola User -->
                    <div class="col-lg-6 col-sm-6">
                        <a href="<?= BASE_URL ?>/pages/users/index.php" class="sub-mod-card">
                            <div class="sub-icon-wrapper">
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #ef4444, #dc2626);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #ef4444, #dc2626); --icon-shadow: rgba(239, 68, 68, 0.5);">
                                    <i class="bi bi-person-gear"></i>
                                    <div class="sub-icon-shine"></div>
                                </div>
                            </div>
                            <div class="sub-card-title">Kelola User</div>
                            <div class="sub-card-desc">Manajemen akun staf, admin, role & hak akses pengguna</div>
                        </a>
                    </div>
                    <!-- 2. Pengaturan Aplikasi -->
                    <div class="col-lg-6 col-sm-6">
                        <a href="<?= BASE_URL ?>/pages/pengaturan/index.php" class="sub-mod-card">
                            <div class="sub-icon-wrapper">
                                <div class="sub-icon-aura" style="background: linear-gradient(135deg, #64748b, #475569);"></div>
                                <div class="sub-icon-box" style="background: linear-gradient(135deg, #64748b, #475569); --icon-shadow: rgba(100, 116, 139, 0.5);">
                                    <i class="bi bi-gear-fill"></i>
                                    <div class="sub-icon-shine"></div>
                                </div>
                            </div>
                            <div class="sub-card-title">Pengaturan Aplikasi</div>
                            <div class="sub-card-desc">Konfigurasi nama sekolah, logo, tema & parameter sistem</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ===== PREMIUM APP GRID (ULTRA PRECISE ALIGNMENT) ===== */
.app-grid-container {
    padding: 1.5rem 0 3.5rem;
    display: flex;
    justify-content: center;
    width: 100%;
}

.app-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.75rem;
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

/* --- MODULE CARD --- */
.app-module-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-decoration: none !important;
    cursor: pointer;
    position: relative;
    padding: 1.5rem 1rem;
    border-radius: 24px;
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.85);
    box-shadow: 
        0 10px 30px -5px rgba(15, 23, 42, 0.05),
        0 0 0 1px rgba(0, 0, 0, 0.03);
    transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
    min-height: 200px;
    overflow: hidden;
}

.app-module-card:hover {
    transform: translateY(-8px) scale(1.03);
    background: #ffffff;
    border-color: rgba(255, 255, 255, 1);
    box-shadow: 
        0 20px 40px -10px var(--mod-shadow),
        0 0 0 1.5px var(--mod-color);
}

/* Glow ring on hover - controlled & clean */
.app-glow-ring {
    position: absolute;
    inset: 0;
    border-radius: 24px;
    background: var(--mod-gradient);
    opacity: 0;
    filter: blur(20px);
    transition: opacity 0.35s ease;
    z-index: 0;
    pointer-events: none;
}

.app-module-card:hover .app-glow-ring {
    opacity: 0.18;
}

/* --- ICON BOX --- */
.app-icon-box {
    position: relative;
    z-index: 1;
    margin-bottom: 1.1rem;
}

.app-icon-inner {
    width: 100px;
    height: 100px;
    border-radius: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 2.8rem;
    position: relative;
    overflow: hidden;
    border: 2px solid rgba(255, 255, 255, 0.4);
    box-shadow:
        0 14px 32px var(--mod-shadow),
        0 4px 10px rgba(0,0,0,0.06),
        inset 0 2px 4px rgba(255,255,255,0.6),
        inset 0 -3px 6px rgba(0,0,0,0.15);
    transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.app-module-card:hover .app-icon-inner {
    transform: scale(1.08) rotate(-2deg);
    box-shadow:
        0 20px 42px var(--mod-shadow),
        0 8px 18px rgba(0,0,0,0.1),
        inset 0 2px 4px rgba(255,255,255,0.7),
        inset 0 -3px 6px rgba(0,0,0,0.15);
}

/* Shine sweep animation */
.app-icon-shine {
    position: absolute;
    top: 0; left: -100%;
    width: 100%; height: 100%;
    background: linear-gradient(
        120deg,
        transparent 30%,
        rgba(255,255,255,0.45) 50%,
        transparent 70%
    );
    border-radius: 28px;
    transition: left 0.6s ease;
    pointer-events: none;
    z-index: 2;
}

.app-module-card:hover .app-icon-shine {
    left: 100%;
}

/* --- TITLE --- */
.app-module-title {
    font-size: 0.95rem;
    font-weight: 800;
    color: #1e293b;
    text-align: center;
    line-height: 1.3;
    letter-spacing: -0.01em;
    z-index: 1;
    transition: color 0.3s ease;
    min-height: 2.6rem;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
}

.app-module-card:hover .app-module-title,
.app-module-card:focus .app-module-title {
    color: var(--mod-color);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 992px) {
    .app-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 1.25rem;
    }
}

@media (max-width: 640px) {
    .app-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    .app-icon-inner {
        width: 86px;
        height: 86px;
        font-size: 2.4rem;
        border-radius: 24px;
    }
    .app-module-card {
        padding: 1.1rem 0.6rem;
        min-height: 170px;
        border-radius: 20px;
    }
    .app-module-title {
        font-size: 0.85rem;
        min-height: 2.4rem;
    }
}

/* ===== SUB MODULE CARDS IN POPUP MODAL (ULTRA MODERN & LARGE ICONS) ===== */
.sub-mod-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 1.6rem 1.25rem 1.4rem;
    background: linear-gradient(145deg, rgba(255, 255, 255, 0.06) 0%, rgba(255, 255, 255, 0.02) 100%);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 26px;
    text-decoration: none !important;
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    height: 100%;
    position: relative;
    overflow: hidden;
}

.sub-mod-card:hover {
    transform: translateY(-8px) scale(1.035);
    background: linear-gradient(145deg, rgba(255, 255, 255, 0.14) 0%, rgba(255, 255, 255, 0.05) 100%);
    border-color: rgba(255, 255, 255, 0.35);
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.5);
}

.sub-icon-wrapper {
    position: relative;
    margin-bottom: 1.15rem;
}

.sub-icon-aura {
    position: absolute;
    inset: -6px;
    border-radius: 30px;
    filter: blur(14px);
    opacity: 0.45;
    transition: opacity 0.4s ease, transform 0.4s ease;
}

.sub-mod-card:hover .sub-icon-aura {
    opacity: 0.85;
    transform: scale(1.12);
}

.sub-icon-box {
    position: relative;
    z-index: 2;
    width: 84px;
    height: 84px;
    border-radius: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 2.5rem;
    border: 2px solid rgba(255, 255, 255, 0.35);
    box-shadow: 
        0 14px 35px var(--icon-shadow, rgba(0,0,0,0.35)),
        inset 0 2px 4px rgba(255,255,255,0.5),
        inset 0 -3px 6px rgba(0,0,0,0.15);
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    overflow: hidden;
}

.sub-mod-card:hover .sub-icon-box {
    transform: scale(1.08) rotate(-4deg);
    box-shadow: 
        0 20px 45px var(--icon-shadow, rgba(0,0,0,0.45)),
        inset 0 2px 4px rgba(255,255,255,0.6),
        inset 0 -3px 6px rgba(0,0,0,0.15);
}

.sub-icon-shine {
    position: absolute;
    top: 0; left: -100%;
    width: 100%; height: 100%;
    background: linear-gradient(
        120deg,
        transparent 30%,
        rgba(255,255,255,0.45) 50%,
        transparent 70%
    );
    border-radius: 26px;
    transition: left 0.6s ease;
    pointer-events: none;
    z-index: 3;
}

.sub-mod-card:hover .sub-icon-shine {
    left: 100%;
}

.sub-card-title {
    font-size: 1rem;
    font-weight: 800;
    color: #ffffff;
    margin-bottom: 0.35rem;
    line-height: 1.3;
    letter-spacing: -0.01em;
}

.sub-card-desc {
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.7);
    line-height: 1.4;
    max-width: 220px;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
