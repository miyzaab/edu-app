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

    // Query user dengan prepared statement (anti SQL Injection)
    $stmt = $pdo->prepare("SELECT id, username, password, nama_lengkap, role FROM users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    // Verifikasi user dan password
    if ($user && password_verify($password, $user['password'])) {
        // --- LOGIN BERHASIL ---

        // Regenerasi session ID untuk keamanan (anti session fixation)
        session_regenerate_id(true);

        require_once __DIR__ . '/config/auth_functions.php';

        // Simpan data user ke session
        $_SESSION['user_id']      = $user['id'];
        $_SESSION['username']     = $user['username'];
        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
        $_SESSION['role']         = $user['role'];
        $_SESSION['permissions']  = loadUserPermissions((int)$user['id']);

        // Update last_login di database
        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
        $updateStmt->execute([':id' => $user['id']]);

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
        $_SESSION['login_error'] = 'Username atau password salah.';
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['login_error'] = 'Terjadi kesalahan sistem. Silakan coba lagi.';
    // Log error (jangan tampilkan detail ke user di production)
    error_log('Login Error: ' . $e->getMessage());
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
