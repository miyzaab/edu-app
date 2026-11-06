<?php
require_once __DIR__ . '/config/koneksi.php';

try {
    $pdo = getConnection();

    echo "Mulai memperbaiki struktur tabel users...<br>";

    // 1. Update role ke VARCHAR(50)
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'bendahara'");
    echo "✅ Kolom role berhasil diperbarui menjadi VARCHAR(50) (mendukung admin, bendahara, guru, ortu, operator).<br>";

    // 2. Pastikan user 'bendahara' tetap ada dan role-nya benar
    $pdo->exec("UPDATE users SET role = 'bendahara' WHERE username = 'bendahara' AND role = ''");

    echo "<br><b>Selesai!</b> Silakan hapus file ini dan coba login kembali sebagai admin.";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
