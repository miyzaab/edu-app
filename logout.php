<?php
/**
 * LOGOUT - Hapus semua session dan redirect ke login
 * Tidak memerlukan koneksi.php agar tidak ada kemungkinan error dependency
 */
session_start();

// Hapus semua data session
$_SESSION = [];

// Hapus cookie session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hancurkan session
session_destroy();

// Redirect ke halaman login
header('Location: index.php');
exit;
