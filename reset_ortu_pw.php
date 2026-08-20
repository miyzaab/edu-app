<?php
require_once __DIR__ . '/config/koneksi.php';
$pdo = getConnection();
$hash = password_hash('123456', PASSWORD_DEFAULT);
$count = $pdo->exec("UPDATE users SET password = '$hash' WHERE role = 'ortu'");
echo "✓ Berhasil mereset $count akun Orang Tua dengan password default: 123456\n";

$users = $pdo->query("SELECT u.username, u.nama_lengkap, s.nis FROM users u LEFT JOIN siswa s ON u.siswa_id = s.id WHERE u.role = 'ortu'")->fetchAll();
foreach ($users as $u) {
    echo "• Username: " . $u['username'] . " | NIS: " . ($u['nis'] ?: '-') . " | Nama: " . $u['nama_lengkap'] . "\n";
}
