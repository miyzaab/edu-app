<?php
/**
 * ============================================================
 * MIDDLEWARE AUTENTIKASI
 * ============================================================
 * Include file ini di setiap halaman yang membutuhkan login.
 * Jika user belum login, otomatis redirect ke halaman login.
 * ============================================================
 */

require_once __DIR__ . '/koneksi.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Simpan URL yang diminta agar bisa redirect setelah login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

require_once __DIR__ . '/auth_functions.php';

// Pastikan role-nya valid
$allowedRoles = ['bendahara', 'admin', 'operator', 'guru', 'kantin'];
if (isset($_SESSION['role']) && $_SESSION['role'] === 'ortu') {
    header('Location: ' . BASE_URL . '/portal-ortu.php');
    exit;
}

if (!in_array($_SESSION['role'] ?? '', $allowedRoles)) {
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Verifikasi status akun dan integritas password session secara realtime
$pdoAuth = getConnection();
$stmtSessionCheck = $pdoAuth->prepare("SELECT password, role, is_active FROM users WHERE id = :id LIMIT 1");
$stmtSessionCheck->execute([':id' => (int) $_SESSION['user_id']]);
$userSessionData = $stmtSessionCheck->fetch(PDO::FETCH_ASSOC);

if (!$userSessionData) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['login_error'] = 'Sesi Anda telah berakhir. Silakan login kembali.';
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// 1. Cek jika akun dinonaktifkan
if (isset($userSessionData['is_active']) && (int) $userSessionData['is_active'] === 0) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['login_error'] = 'Akun Anda telah dinonaktifkan oleh administrator.';
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// 2. Cek jika password telah diubah dari perangkat lain
if (isset($_SESSION['auth_password_hash'])) {
    if ($userSessionData['password'] !== $_SESSION['auth_password_hash']) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['login_error'] = 'Password akun Anda telah diperbarui dari perangkat lain. Silakan login kembali dengan password baru.';
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
} else {
    $_SESSION['auth_password_hash'] = $userSessionData['password'];
}

// Sinkronkan permissions user dari database (dengan caching per session)
if (!isset($_SESSION['permissions']) || !isset($_SESSION['perm_cached_time']) || (time() - $_SESSION['perm_cached_time'] > 180)) {
    $_SESSION['permissions'] = loadUserPermissions((int) $_SESSION['user_id']);
    $_SESSION['perm_cached_time'] = time();
}



