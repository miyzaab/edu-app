<?php
/**
 * ============================================================
 * AUTH FUNCTIONS - Helper functions untuk auth
 * ============================================================
 * File ini berisi fungsi-fungsi auth yang bisa dipanggil
 * tanpa menjalankan guard (cek login). 
 * Digunakan oleh login_process.php dan auth.php.
 * ============================================================
 */

require_once __DIR__ . '/koneksi.php';

// Pastikan fungsi tidak didefinisikan ulang jika sudah di-include via auth.php
if (!function_exists('loadUserPermissions')) {
    /**
     * Load permissions dari database ke session
     */
    function loadUserPermissions(int $userId): array
    {
        try {
            $pdo = getConnection();
            $stmt = $pdo->prepare("SELECT permission_key, is_allowed FROM user_permissions WHERE user_id = :uid");
            $stmt->execute([':uid' => $userId]);
            $rows = $stmt->fetchAll();

            $permissions = [];
            foreach ($rows as $row) {
                $permissions[$row['permission_key']] = (bool)$row['is_allowed'];
            }
            return $permissions;
        } catch (PDOException $e) {
            return [];
        }
    }
}

if (!function_exists('getAllPermissions')) {
    /**
     * Daftar semua permission yang tersedia dalam sistem
     */
    function getAllPermissions(): array
    {
        return [
            'dashboard'          => ['label' => 'Portal Utama (App Launcher)', 'icon' => 'bi-grid-fill',        'group' => 'Menu Utama'],
            'dashboard_utama'    => ['label' => 'Dashboard Utama (Keuangan)',  'icon' => 'bi-speedometer2',     'group' => 'Transaksi'],
            'verifikasi'         => ['label' => 'Verifikasi Pembayaran',       'icon' => 'bi-shield-check',     'group' => 'Transaksi'],
            'riwayat'            => ['label' => 'Riwayat Pembayaran',          'icon' => 'bi-clock-history',    'group' => 'Transaksi'],
            'rekap_spp'          => ['label' => 'Rekap SPP Kelas',             'icon' => 'bi-grid-3x3',        'group' => 'Transaksi'],
            'rekap_uang_pangkal' => ['label' => 'Rekap Uang Pangkal',          'icon' => 'bi-wallet2',          'group' => 'Transaksi'],
            'rekap_daftar_ulang' => ['label' => 'Rekap Daftar Ulang',          'icon' => 'bi-clipboard-check', 'group' => 'Transaksi'],
            'spp'                => ['label' => 'Pembayaran SPP',              'icon' => 'bi-cash-stack',       'group' => 'Transaksi'],
            'uang_pangkal'       => ['label' => 'Uang Pangkal',                'icon' => 'bi-wallet2',          'group' => 'Transaksi'],
            'pembayaran_lain'    => ['label' => 'Pembayaran Lain',             'icon' => 'bi-receipt-cutoff',   'group' => 'Transaksi'],
            'kantin'             => ['label' => 'Kantin Sekolah',              'icon' => 'bi-shop',             'group' => 'Kantin Sekolah'],
            'kelas'              => ['label' => 'Data Kelas',                  'icon' => 'bi-building',         'group' => 'Master Data'],
            'siswa'              => ['label' => 'Data Siswa',                  'icon' => 'bi-people-fill',      'group' => 'Master Data'],
            'guru'               => ['label' => 'Data Guru',                   'icon' => 'bi-person-badge',     'group' => 'Master Data'],
            'jenis_pembayaran'   => ['label' => 'Jenis Pembayaran',            'icon' => 'bi-tags-fill',        'group' => 'Master Data'],
            'perangkat_ajar'     => ['label' => 'Perangkat Ajar (CP/TP/Modul)', 'icon' => 'bi-journal-text',       'group' => 'Akademik & Guru'],
            'input_nilai'        => ['label' => 'Input Nilai Siswa',           'icon' => 'bi-calculator-fill',    'group' => 'Akademik & Guru'],
            'mapel'              => ['label' => 'Data Mata Pelajaran',         'icon' => 'bi-book-half',          'group' => 'Akademik & Guru'],
            'plotting_guru'      => ['label' => 'Guru Pengampu (Plotting)',     'icon' => 'bi-person-workspace',   'group' => 'Akademik & Guru'],
            'halaqah'            => ['label' => 'Halaqah & Tahfidz',           'icon' => 'bi-book-fill',          'group' => 'Akademik & Guru'],
            'kesantrian'         => ['label' => 'Poin Kesantrian & Disiplin',  'icon' => 'bi-trophy-fill',        'group' => 'Akademik & Guru'],
            'petty_cash'         => ['label' => 'Petty Cash (Buku Kas)',        'icon' => 'bi-wallet-fill',     'group' => 'Laporan'],
            'laporan'            => ['label' => 'Laporan Keuangan',            'icon' => 'bi-file-earmark-spreadsheet', 'group' => 'Laporan'],
            'users'              => ['label' => 'Kelola User',                 'icon' => 'bi-person-gear',      'group' => 'Pengaturan'],
            'pengaturan'         => ['label' => 'Pengaturan',                  'icon' => 'bi-gear-fill',        'group' => 'Pengaturan'],
        ];
    }
}

if (!function_exists('getDefaultPermissions')) {
    /**
     * Default permissions per role
     */
    function getDefaultPermissions(string $role): array
    {
        $all = array_keys(getAllPermissions());

        return match ($role) {
            'admin' => $all,
            'bendahara' => array_values(array_diff($all, ['users', 'pengaturan'])),
            'operator' => ['dashboard', 'dashboard_utama', 'verifikasi', 'riwayat', 'rekap_spp', 'rekap_uang_pangkal', 'rekap_daftar_ulang', 'spp', 'uang_pangkal', 'pembayaran_lain', 'kantin', 'kelas', 'siswa', 'guru', 'jenis_pembayaran', 'perangkat_ajar', 'input_nilai', 'mapel', 'plotting_guru', 'halaqah', 'kesantrian'],
            'guru' => ['dashboard', 'riwayat', 'rekap_spp', 'rekap_uang_pangkal', 'rekap_daftar_ulang', 'kelas', 'siswa', 'guru', 'perangkat_ajar', 'input_nilai', 'mapel', 'halaqah', 'kesantrian'],
            default => ['dashboard'],
        };
    }
}

if (!function_exists('hasPermission')) {
    /**
     * Cek apakah user saat ini punya permission tertentu
     * Admin selalu punya semua permission (bypass check)
     */
    function hasPermission(string $key): bool
    {
        $role = strtolower($_SESSION['role'] ?? '');
        if ($role === 'admin') {
            return true;
        }
        
        $permissions = $_SESSION['permissions'] ?? [];
        
        if (isset($permissions[$key])) {
            return (bool)$permissions[$key];
        }
        
        // Fallback ke default permission role jika belum ada di database/session
        $defaults = getDefaultPermissions($role);
        return in_array($key, $defaults);
    }
}

if (!function_exists('requirePermission')) {
    /**
     * Require permission — redirect jika tidak punya akses (bebas infinite loop)
     */
    function requirePermission(string $key): void
    {
        if (!hasPermission($key)) {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Anda tidak memiliki akses ke halaman tersebut. Hubungi admin untuk mendapatkan izin.'
            ];
            
            // Map permission_key ke URL
            $permUrlMap = [
                'dashboard'          => '/pages/dashboard.php',
                'perangkat_ajar'     => '/pages/perangkat-ajar/index.php',
                'riwayat'            => '/pages/riwayat/index.php',
                'rekap_spp'          => '/pages/riwayat/rekap-spp.php',
                'rekap_uang_pangkal' => '/pages/riwayat/rekap-uang-pangkal.php',
                'rekap_daftar_ulang' => '/pages/riwayat/rekap-daftar-ulang.php',
                'spp'                => '/pages/spp/index.php',
                'uang_pangkal'       => '/pages/uang-pangkal/index.php',
                'pembayaran_lain'    => '/pages/pembayaran-lain/index.php',
                'verifikasi'         => '/pages/verifikasi/index.php',
                'kelas'              => '/pages/kelas/index.php',
                'siswa'              => '/pages/siswa/index.php',
                'jenis_pembayaran'   => '/pages/jenis-pembayaran/index.php',
                'petty_cash'         => '/pages/petty-cash/index.php',
                'laporan'            => '/pages/laporan/index.php',
                'kesantrian'         => '/pages/halaqah/poin_dashboard.php',
            ];

            $targetUrl = null;
            foreach ($permUrlMap as $pkey => $url) {
                if ($pkey !== $key && hasPermission($pkey)) {
                    $targetUrl = BASE_URL . $url;
                    break;
                }
            }

            if (!$targetUrl) {
                $targetUrl = BASE_URL . '/index.php';
            }

            header('Location: ' . $targetUrl);
            exit;
        }
    }
}
