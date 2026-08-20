<?php
require_once __DIR__ . '/config/koneksi.php';
$logo = getSetting('logo_path', '');
$cleanLogo = ltrim($logo, '/');
echo "Logo setting: " . var_export($logo, true) . "\n";
echo "Clean logo: " . var_export($cleanLogo, true) . "\n";
echo "File exists? " . (file_exists(__DIR__ . '/' . $cleanLogo) ? 'YES' : 'NO') . "\n";
