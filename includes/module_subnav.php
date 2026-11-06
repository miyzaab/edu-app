<?php
/**
 * UNIVERSAL MODULE SUBNAV CONTROLLER
 * Menampilkan Segmented Capsule Navigation di bagian atas halaman untuk setiap modul aktif
 */

if (!function_exists('renderModuleSubnav')) {
    function renderModuleSubnav(): string
    {
        global $activeModule, $activePage, $hideModuleSubnav;

        if ($activePage === 'dashboard' || !empty($hideModuleSubnav)) {
            return '';
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $pdo = getConnection();

        // 1. Definisi Tab Navigasi per Modul
        $moduleConfigs = [
            'transaksi' => [
                'gradient' => 'linear-gradient(135deg, #6366f1 0%, #4f46e5 100%)',
                'color' => '#4f46e5',
                'shadow' => 'rgba(99, 102, 241, 0.35)',
                'label' => 'Transaksi & Keuangan',
                'icon' => 'bi-cash-coin',
                'items' => [
                    [
                        'url' => BASE_URL . '/pages/transaksi/dashboard.php',
                        'icon' => 'bi-speedometer2',
                        'label' => 'Dashboard',
                        'permission' => 'dashboard_utama',
                        'match' => ['/pages/transaksi/dashboard.php', '/pages/transaksi/']
                    ],
                    [
                        'url' => BASE_URL . '/pages/verifikasi/index.php',
                        'icon' => 'bi-shield-check',
                        'label' => 'Verifikasi',
                        'permissions' => ['verifikasi', 'riwayat'],
                        'match' => ['/pages/verifikasi/', '/pages/riwayat/index.php', '/pages/riwayat/edit.php'],
                        'badge_query' => "SELECT COUNT(id) FROM pembayaran_pending WHERE status='pending' AND jenis IN ('spp', 'uang_pangkal', 'lainnya')"
                    ],
                    [
                        'url' => BASE_URL . '/pages/riwayat/rekap-spp.php',
                        'icon' => 'bi-grid-3x3',
                        'label' => 'Rekap Pembayaran',
                        'permissions' => ['rekap_spp', 'rekap_uang_pangkal', 'rekap_daftar_ulang', 'tunggakan'],
                        'match' => [
                            '/pages/riwayat/rekap-spp.php',
                            '/pages/riwayat/rekap-daftar-ulang.php',
                            '/pages/riwayat/rekap-uang-pangkal.php',
                            '/pages/tunggakan/'
                        ]
                    ],
                    [
                        'url' => BASE_URL . '/pages/spp/index.php',
                        'icon' => 'bi-credit-card-2-front-fill',
                        'label' => 'Pembayaran',
                        'permissions' => ['spp', 'uang_pangkal', 'pembayaran_lain', 'jenis_pembayaran'],
                        'match' => [
                            '/pages/spp/',
                            '/pages/uang-pangkal/',
                            '/pages/pembayaran-lain/',
                            '/pages/jenis-pembayaran/'
                        ]
                    ],
                    [
                        'url' => BASE_URL . '/pages/petty-cash/index.php',
                        'icon' => 'bi-wallet-fill',
                        'label' => 'Petty Cash',
                        'permission' => 'petty_cash',
                        'match' => ['/pages/petty-cash/']
                    ],
                    [
                        'url' => BASE_URL . '/pages/laporan/index.php',
                        'icon' => 'bi-file-earmark-spreadsheet',
                        'label' => 'Laporan Keuangan',
                        'permission' => 'laporan',
                        'match' => ['/pages/laporan/']
                    ],
                ]
            ],
            'master_data' => [
                'gradient' => 'linear-gradient(135deg, #06b6d4 0%, #0891b2 100%)',
                'color' => '#0891b2',
                'shadow' => 'rgba(6, 182, 212, 0.35)',
                'label' => 'Master Data',
                'icon' => 'bi-building-gear',
                'items' => [
                    [
                        'url' => BASE_URL . '/pages/kelas/index.php',
                        'icon' => 'bi-building',
                        'label' => 'Data Kelas',
                        'permission' => 'kelas',
                        'match' => ['/pages/kelas/']
                    ],
                    [
                        'url' => BASE_URL . '/pages/siswa/index.php',
                        'icon' => 'bi-people-fill',
                        'label' => 'Data Siswa',
                        'permission' => 'siswa',
                        'match' => ['/pages/siswa/']
                    ],
                    [
                        'url' => BASE_URL . '/pages/guru/index.php',
                        'icon' => 'bi-person-badge-fill',
                        'label' => 'Data Guru',
                        'permission' => 'guru',
                        'match' => ['/pages/guru/']
                    ],
                ]
            ],
            'akademik' => [
                'gradient' => 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
                'color' => '#059669',
                'shadow' => 'rgba(16, 185, 129, 0.35)',
                'label' => 'Akademik & Guru',
                'icon' => 'bi-journal-bookmark-fill',
                'items' => [
                    [
                        'url' => BASE_URL . '/pages/perangkat-ajar/index.php',
                        'icon' => 'bi-journal-bookmark-fill',
                        'label' => 'Perangkat Ajar',
                        'permission' => 'perangkat_ajar',
                        'match' => ['/pages/perangkat-ajar/']
                    ],
                    [
                        'url' => BASE_URL . '/pages/nilai/index.php',
                        'icon' => 'bi-calculator-fill',
                        'label' => 'Input Nilai (Guru)',
                        'permission' => 'input_nilai',
                        'match' => ['/pages/nilai/']
                    ],
                    [
                        'url' => BASE_URL . '/pages/wali-kelas/index.php',
                        'icon' => 'bi-person-badge-fill',
                        'label' => 'Wali Kelas (Nilai & Rapor)',
                        'permission' => 'wali_kelas',
                        'match' => ['/pages/wali-kelas/', '/pages/rapor/']
                    ],
                    [
                        'url' => BASE_URL . '/pages/mapel/index.php',
                        'icon' => 'bi-book-half',
                        'label' => 'Mata Pelajaran',
                        'permission' => 'mapel',
                        'match' => ['/pages/mapel/index.php']
                    ],
                    [
                        'url' => BASE_URL . '/pages/mapel/plotting.php',
                        'icon' => 'bi-person-workspace',
                        'label' => 'Plotting Guru Pengampu',
                        'permission' => 'plotting_guru',
                        'match' => ['/pages/mapel/plotting.php']
                    ],
                ]
            ],
            'kesantrian' => [
                'gradient' => 'linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%)',
                'color' => '#6d28d9',
                'shadow' => 'rgba(139, 92, 246, 0.35)',
                'label' => 'Kesantrian & Halaqah',
                'icon' => 'bi-moon-stars-fill',
                'items' => [
                    [
                        'url' => BASE_URL . '/pages/halaqah/index.php',
                        'icon' => 'bi-moon-stars-fill',
                        'label' => 'Halaqah & Tahfidz',
                        'permission' => 'halaqah',
                        'match' => ['/pages/halaqah/index.php']
                    ],
                    [
                        'url' => BASE_URL . '/pages/halaqah/manage.php',
                        'icon' => 'bi-diagram-3-fill',
                        'label' => 'Kelompok & Musyrif',
                        'permission' => 'halaqah',
                        'match' => ['/pages/halaqah/manage.php']
                    ],
                    [
                        'url' => BASE_URL . '/pages/halaqah/laporan.php',
                        'icon' => 'bi-file-earmark-text',
                        'label' => 'Rekap Setoran',
                        'permission' => 'halaqah',
                        'match' => ['/pages/halaqah/laporan.php']
                    ],
                    [
                        'url' => BASE_URL . '/pages/halaqah/poin_dashboard.php',
                        'icon' => 'bi-star-fill',
                        'label' => 'Poin Kesantrian',
                        'permission' => 'kesantrian',
                        'match' => ['/pages/halaqah/poin_dashboard.php']
                    ],
                    [
                        'url' => BASE_URL . '/pages/halaqah/poin_history.php',
                        'icon' => 'bi-clock-history',
                        'label' => 'Riwayat Poin',
                        'permission' => 'kesantrian',
                        'match' => ['/pages/halaqah/poin_history.php']
                    ],
                    [
                        'url' => BASE_URL . '/pages/halaqah/poin_leaderboard.php',
                        'icon' => 'bi-trophy-fill',
                        'label' => 'Leaderboard Poin',
                        'permission' => 'kesantrian',
                        'match' => ['/pages/halaqah/poin_leaderboard.php']
                    ],
                ]
            ],
            'kantin' => [
                'gradient' => 'linear-gradient(135deg, #f97316 0%, #ea580c 100%)',
                'color' => '#ea580c',
                'shadow' => 'rgba(249, 115, 22, 0.35)',
                'label' => 'Kantin Sekolah',
                'icon' => 'bi-shop',
                'items' => [
                    [
                        'url' => BASE_URL . '/pages/kantin/index.php',
                        'icon' => 'bi-calculator-fill',
                        'label' => 'Kasir POS',
                        'permission' => 'kantin',
                        'match' => ['/pages/kantin/index.php']
                    ],
                    [
                        'url' => BASE_URL . '/pages/kantin/topup.php',
                        'icon' => 'bi-wallet2',
                        'label' => 'Top-Up Saldo',
                        'permission' => 'kantin',
                        'match' => ['/pages/kantin/topup.php', '/pages/kantin/manage.php', '/pages/kantin/saldo.php'],
                        'badge_query' => "SELECT COUNT(id) FROM pembayaran_pending WHERE status='pending' AND (jenis = 'topup_kantin' OR jenis = 'topup' OR jenis = '' OR jenis IS NULL OR jenis NOT IN ('spp', 'uang_pangkal', 'lainnya'))"
                    ],
                    [
                        'url' => BASE_URL . '/pages/kantin/menu.php',
                        'icon' => 'bi-egg-fried',
                        'label' => 'Kelola Menu',
                        'permission' => 'kantin',
                        'match' => ['/pages/kantin/menu.php']
                    ],
                    [
                        'url' => BASE_URL . '/pages/kantin/laporan.php',
                        'icon' => 'bi-graph-up-arrow',
                        'label' => 'Laporan',
                        'permission' => 'kantin',
                        'match' => ['/pages/kantin/laporan.php']
                    ],
                ]
            ],
            'pengaturan' => [
                'gradient' => 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
                'color' => '#dc2626',
                'shadow' => 'rgba(239, 68, 68, 0.35)',
                'label' => 'Pengaturan Sistem',
                'icon' => 'bi-gear-wide-connected',
                'items' => [
                    [
                        'url' => BASE_URL . '/pages/users/index.php',
                        'icon' => 'bi-person-gear',
                        'label' => 'Kelola User',
                        'permission' => 'users',
                        'match' => ['/pages/users/']
                    ],
                    [
                        'url' => BASE_URL . '/pages/pengaturan/index.php',
                        'icon' => 'bi-gear-fill',
                        'label' => 'Pengaturan Aplikasi',
                        'permission' => 'pengaturan',
                        'match' => ['/pages/pengaturan/index.php']
                    ],
                    [
                        'url' => BASE_URL . '/pages/pengaturan/backup.php',
                        'icon' => 'bi-database-down',
                        'label' => 'Backup & Restore DB',
                        'permission' => 'pengaturan',
                        'match' => ['/pages/pengaturan/backup.php']
                    ],
                ]
            ]
        ];

        if (!isset($moduleConfigs[$activeModule])) {
            return '';
        }

        $config = $moduleConfigs[$activeModule];
        $visibleItems = [];

        foreach ($config['items'] as $it) {
            $hasAccess = false;
            if (!empty($it['permissions']) && is_array($it['permissions'])) {
                foreach ($it['permissions'] as $pKey) {
                    if (hasPermission($pKey)) {
                        $hasAccess = true;
                        break;
                    }
                }
            } elseif (!empty($it['permission'])) {
                $hasAccess = hasPermission($it['permission']);
            }

            if ($hasAccess) {
                // Deteksi active state
                $isActive = false;
                $scriptPath = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '');
                foreach ($it['match'] as $pattern) {
                    if (strpos($requestUri, $pattern) !== false || strpos($scriptPath, $pattern) !== false) {
                        $isActive = true;
                        break;
                    }
                }

                // Cek badge jika ada query
                $badgeHtml = '';
                if (!empty($it['badge_query'])) {
                    try {
                        $bCount = (int) $pdo->query($it['badge_query'])->fetchColumn();
                        if ($bCount > 0) {
                            $badgeHtml = '<span class="badge bg-danger ms-1.5 px-2 py-0.5 rounded-pill" style="font-size:0.7rem;">' . $bCount . '</span>';
                        }
                    } catch (Exception $e) {
                    }
                }

                $visibleItems[] = [
                    'url' => $it['url'],
                    'icon' => $it['icon'],
                    'label' => $it['label'],
                    'isActive' => $isActive,
                    'badgeHtml' => $badgeHtml
                ];
            }
        }

        if (count($visibleItems) <= 1) {
            return ''; // Tidak perlu navigasi tab jika hanya ada 1 item yang diizinkan
        }

        ob_start();
        ?>
        <div class="module-subnav-wrapper mb-4">
            <div class="module-subnav-container"
                style="--subnav-color: <?= $config['color'] ?>; --subnav-grad: <?= $config['gradient'] ?>; --subnav-shadow: <?= $config['shadow'] ?>;">
                <?php foreach ($visibleItems as $v): ?>
                    <a href="<?= $v['url'] ?>" class="module-subnav-link <?= $v['isActive'] ? 'active' : '' ?>">
                        <i class="bi <?= $v['icon'] ?>"></i>
                        <span><?= htmlspecialchars($v['label']) ?></span>
                        <?= $v['badgeHtml'] ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <style>
            .module-subnav-wrapper {
                width: 100%;
                overflow-x: auto;
                padding-top: 2px;
                padding-bottom: 6px;
                padding-left: 2px;
                padding-right: 20px;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }

            .module-subnav-wrapper::-webkit-scrollbar {
                display: none;
            }

            .module-subnav-container {
                display: inline-flex;
                align-items: center;
                background: #f8fafc;
                border: 1px solid #cbd5e1;
                border-radius: 16px;
                padding: 5px;
                gap: 4px;
                width: max-content;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            }

            .module-subnav-link {
                padding: 8px 16px;
                border-radius: 12px;
                font-size: 0.85rem;
                font-weight: 700;
                color: #334155;
                text-decoration: none !important;
                display: inline-flex;
                align-items: center;
                gap: 7px;
                white-space: nowrap;
                flex-shrink: 0;
                cursor: pointer;
                border: 1px solid transparent;
                -webkit-tap-highlight-color: transparent;
                user-select: none;
                transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
            }

            .module-subnav-link:hover,
            .module-subnav-link:focus {
                color: #0f172a;
                background: #ffffff;
                border-color: #cbd5e1;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
                outline: none !important;
            }

            .module-subnav-link.active,
            .module-subnav-link.active:hover,
            .module-subnav-link.active:focus,
            .module-subnav-link.active:active {
                background: var(--subnav-grad, linear-gradient(135deg, #4f46e5, #4338ca)) !important;
                color: #ffffff !important;
                font-weight: 800;
                border-color: rgba(255, 255, 255, 0.2) !important;
                box-shadow: 0 4px 14px var(--subnav-shadow, rgba(79, 70, 229, 0.4)) !important;
                transform: none !important;
            }

            .module-subnav-link.active i,
            .module-subnav-link.active:hover i,
            .module-subnav-link.active:focus i,
            .module-subnav-link.active:active i {
                color: #ffffff !important;
            }

            .module-subnav-link i {
                font-size: 1rem;
                color: var(--subnav-color, #4f46e5);
                transition: color 0.2s ease;
            }
        </style>
        <?php
        return ob_get_clean();
    }
}
