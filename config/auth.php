<?php
/**
 * ============================================================
 * MIDDLEWARE AUTENTIKASI
 * ============================================================
 * Include file ini di setiap halaman yang membutuhkan login.
 * Jika user belum login, otomatis redirect ke halaman login.
 * ============================================================
 */

session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Simpan URL yang diminta agar bisa redirect setelah login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Pastikan role-nya valid
$allowedRoles = ['bendahara', 'admin', 'operator'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Load koneksi database
require_once __DIR__ . '/koneksi.php';
