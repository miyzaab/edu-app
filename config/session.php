<?php
/**
 * ============================================================
 * KONFIGURASI SESSION - Edu-App
 * ============================================================
 * File ini mengatur durasi login agar user (admin) tidak 
 * logout otomatis dalam waktu singkat.
 * ============================================================
 */

// Durasi session: 30 hari (30 * 24 * 60 * 60 detik)
$session_lifetime = 2592000;

if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.gc_maxlifetime', $session_lifetime);
    session_set_cookie_params([
        'lifetime' => $session_lifetime,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
