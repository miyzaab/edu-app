<?php
require_once __DIR__ . '/config/koneksi.php';

try {
    $pdo = getConnection();
    
    echo "Mulai memperbaiki struktur tabel users...<br>";
    
    // 1. Update ENUM role
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'bendahara', 'operator') DEFAULT 'bendahara'");
    echo "✅ Kolom role berhasil diperbarui (admin, bendahara, operator).<br>";
    
    // 2. Pastikan user 'bendahara' tetap ada dan role-nya benar
    $pdo->exec("UPDATE users SET role = 'bendahara' WHERE username = 'bendahara' AND role = ''");
    
    echo "<br><b>Selesai!</b> Silakan hapus file ini dan coba login kembali sebagai admin.";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
