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

// Auto-detect path tanpa perlu koneksi.php
$base = rtrim(str_replace(
    str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']),
    '',
    str_replace('\\', '/', dirname(__FILE__))
), '/');

// Redirect ke halaman login
header('Location: ' . $base . '/index.php');
exit;
