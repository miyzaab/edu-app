<?php
/**
 * Hapus logo sekolah
 */
require_once __DIR__ . '/../../config/auth.php';
$pdo = getConnection();

$logoPath = getSetting('logo_path', '');
if ($logoPath) {
    $filePath = $_SERVER['DOCUMENT_ROOT'] . $logoPath;
    if (file_exists($filePath)) @unlink($filePath);
    updateSetting('logo_path', '');
}
redirect('index.php', 'success', 'Logo berhasil dihapus.');
