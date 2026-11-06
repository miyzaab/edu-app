<?php
/**
 * ============================================================
 * PROSES LOGIN - Edu-App
 * ============================================================
 * File ini memproses form login dengan keamanan:
 * - Prepared statements (PDO) untuk anti SQL Injection
 * - password_verify() untuk verifikasi hash password
 * - Session regeneration untuk anti session fixation
 * ============================================================
 */

require_once __DIR__ . '/config/koneksi.php';

// Hanya terima POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Ambil input dan sanitasi
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Validasi input tidak kosong
if (empty($username) || empty($password)) {
    $_SESSION['login_error'] = 'Username dan password wajib diisi.';
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

try {
    $pdo = getConnection();

    // Auto-sync database jika ada kolom baru
    require_once __DIR__ . '/config/db_auto_sync.php';
    try {
        runDatabaseAutoSync($pdo);
    } catch (Exception $eSync) {
        // Jangan gagalkan login jika auto sync mengalami timeout / notice
    }

    // 1. CARI USER LANGSUNG BERDASARKAN USERNAME
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.password, u.nama_lengkap, u.role, u.siswa_id, COALESCE(u.is_active, 1) AS is_active 
        FROM users u 
        WHERE u.username = :uname
    ");
    $stmt->execute([':uname' => $username]);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. JIKA BELUM KETEMU, CARI SEBAGAI LOGIN AKUN ORTU (VIA NIS ATAU NO HP WALI)
    if (empty($candidates)) {
        // Normalisasi input jika berupa nomor HP (08..., 628..., +628...)
        $cleanPhone = preg_replace('/[^0-9]/', '', $username);
        $phoneVariants = array_values(array_unique(array_filter([$username, $cleanPhone])));
        if (!empty($cleanPhone)) {
            if (substr($cleanPhone, 0, 2) === '62') {
                $phoneVariants[] = '0' . substr($cleanPhone, 2);
            } elseif (substr($cleanPhone, 0, 1) === '0') {
                $phoneVariants[] = '62' . substr($cleanPhone, 1);
                $phoneVariants[] = '+62' . substr($cleanPhone, 1);
            }
        }
        $phoneVariants = array_values(array_unique($phoneVariants));

        // Cek kolom no_hp_ortu di tabel siswa
        $hasNoHpOrtu = false;
        try {
            $colCheck = $pdo->query("SHOW COLUMNS FROM siswa LIKE 'no_hp_ortu'")->fetch();
            if ($colCheck) {
                $hasNoHpOrtu = true;
            }
        } catch (Exception $eCol) {
        }

        // Bangun pencarian siswa via NIS atau No HP
        $whereClauses = ["nis = :p_nis"];
        $searchParams = [':p_nis' => $username];

        if ($hasNoHpOrtu && !empty($phoneVariants)) {
            foreach ($phoneVariants as $idx => $pv) {
                $phKey = ':p_hp_' . $idx;
                $whereClauses[] = "no_hp_ortu = $phKey";
                $searchParams[$phKey] = $pv;
            }
        }

        try {
            $siswaSql = "SELECT id, nis FROM siswa WHERE " . implode(" OR ", $whereClauses);
            $stmtSiswa = $pdo->prepare($siswaSql);
            $stmtSiswa->execute($searchParams);
            $matchedSiswa = $stmtSiswa->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($matchedSiswa)) {
                $matchedIds = array_column($matchedSiswa, 'id');
                $matchedNis = array_column($matchedSiswa, 'nis');

                $inIds = implode(',', array_fill(0, count($matchedIds), '?'));
                $inNis = implode(',', array_fill(0, count($matchedNis), '?'));

                $ortuStmt = $pdo->prepare("
                    SELECT u.id, u.username, u.password, u.nama_lengkap, u.role, u.siswa_id, COALESCE(u.is_active, 1) AS is_active 
                    FROM users u 
                    WHERE u.role = 'ortu' AND (u.siswa_id IN ($inIds) OR u.username IN ($inNis))
                ");
                $ortuStmt->execute(array_merge($matchedIds, $matchedNis));
                $candidates = $ortuStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $eSiswaSearch) {
            // Abaikan error pencarian siswa jika terjadi kendala query
        }
    }

    // 3. VERIFIKASI PASSWORD TERHADAP KANDIDAT USER
    $user = null;
    foreach ($candidates as $cand) {
        if (password_verify($password, $cand['password'])) {
            $user = $cand;
            break;
        }
    }

    // 4. HASIL VERIFIKASI
    if ($user) {
        // Cek status aktif akun
        if (isset($user['is_active']) && (int) $user['is_active'] === 0) {
            $_SESSION['login_error'] = 'Akun Anda sedang dinonaktifkan. Silakan hubungi pihak sekolah.';
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }

        // --- LOGIN BERHASIL ---
        session_regenerate_id(true);

        require_once __DIR__ . '/config/auth_functions.php';

        // Simpan data user ke session
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['auth_password_hash'] = $user['password']; // Track password hash untuk auto-logout jika ganti password
        $_SESSION['siswa_id'] = !empty($user['siswa_id']) ? (int) $user['siswa_id'] : 0;
        $_SESSION['permissions'] = loadUserPermissions((int) $user['id']);

        // Update last_login di database secara aman
        try {
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
            $updateStmt->execute([':id' => $user['id']]);
        } catch (Exception $eLast) {
            // Abaikan jika kolom last_login belum ada di database
        }

        // Redirect sesuai role
        if ($user['role'] === 'ortu') {
            $defaultRedirect = BASE_URL . '/portal-ortu.php';
        } else {
            $defaultRedirect = BASE_URL . '/pages/dashboard.php';
        }

        $redirectTo = $_SESSION['redirect_after_login'] ?? $defaultRedirect;
        unset($_SESSION['redirect_after_login']);
        header("Location: $redirectTo");
        exit;
    } else {
        // --- LOGIN GAGAL ---
        $_SESSION['login_error'] = 'Username, NIS, No. HP, atau password salah.';
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
} catch (Throwable $e) {
    $_SESSION['login_error'] = 'Terjadi kesalahan sistem. Silakan coba lagi.';
    error_log('Login Error: ' . $e->getMessage());
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
