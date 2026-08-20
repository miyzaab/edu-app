<?php
/**
 * ============================================================
 * MIGRATION: Roles & Permissions System
 * ============================================================
 * Jalankan file ini SATU KALI untuk mengupdate database.
 * Setelah selesai, hapus file ini.
 * ============================================================
 */
require_once __DIR__ . '/config/koneksi.php';

$pdo = getConnection();
$results = [];

try {
    // ===== 1. ALTER tabel users: Ubah ENUM role =====
    try {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin','bendahara','guru','ortu') DEFAULT 'bendahara'");
        $results[] = ['success', 'Kolom role berhasil diperbarui (admin, bendahara, guru, ortu).'];
    } catch (PDOException $e) {
        $results[] = ['info', 'Kolom role sudah sesuai atau error: ' . $e->getMessage()];
    }

    // ===== 2. Tambah kolom siswa_id (untuk role ortu) =====
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN siswa_id INT NULL AFTER role");
        $pdo->exec("ALTER TABLE users ADD CONSTRAINT fk_users_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE SET NULL ON UPDATE CASCADE");
        $results[] = ['success', 'Kolom siswa_id berhasil ditambahkan ke tabel users.'];
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $results[] = ['info', 'Kolom siswa_id sudah ada.'];
        } else {
            $results[] = ['warning', 'siswa_id: ' . $e->getMessage()];
        }
    }

    // ===== 3. Tambah kolom is_active =====
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER siswa_id");
        $results[] = ['success', 'Kolom is_active berhasil ditambahkan ke tabel users.'];
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $results[] = ['info', 'Kolom is_active sudah ada.'];
        } else {
            $results[] = ['warning', 'is_active: ' . $e->getMessage()];
        }
    }

    // ===== 4. CREATE tabel user_permissions =====
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_permissions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            permission_key VARCHAR(50) NOT NULL,
            is_allowed TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
            UNIQUE KEY unique_user_perm (user_id, permission_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $results[] = ['success', 'Tabel user_permissions berhasil dibuat.'];

    // ===== 5. Insert default permissions untuk user existing =====
    $allPermissions = [
        'dashboard', 'verifikasi', 'riwayat', 'rekap_spp',
        'spp', 'uang_pangkal', 'pembayaran_lain',
        'kelas', 'siswa', 'jenis_pembayaran',
        'petty_cash', 'laporan', 'users', 'pengaturan'
    ];

    // Default permissions per role
    $roleDefaults = [
        'admin' => $allPermissions, // semua
        'bendahara' => [
            'dashboard', 'verifikasi', 'riwayat', 'rekap_spp',
            'spp', 'uang_pangkal', 'pembayaran_lain',
            'kelas', 'siswa', 'jenis_pembayaran',
            'petty_cash', 'laporan'
        ],
        'guru' => [
            'dashboard', 'riwayat', 'rekap_spp', 'kelas', 'siswa'
        ],
        'operator' => [
            'dashboard', 'verifikasi', 'riwayat', 'rekap_spp',
            'spp', 'uang_pangkal', 'pembayaran_lain',
            'kelas', 'siswa', 'jenis_pembayaran'
        ]
    ];

    // Ambil semua user yang belum punya permissions
    $users = $pdo->query("SELECT id, role FROM users")->fetchAll();
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM user_permissions WHERE user_id = :uid");
    $stmtInsert = $pdo->prepare("INSERT IGNORE INTO user_permissions (user_id, permission_key, is_allowed) VALUES (:uid, :pkey, :allowed)");

    $insertedCount = 0;
    foreach ($users as $user) {
        $stmtCheck->execute([':uid' => $user['id']]);
        $existingCount = $stmtCheck->fetchColumn();

        if ($existingCount == 0) {
            $role = $user['role'] ?: 'bendahara';
            $defaults = $roleDefaults[$role] ?? $roleDefaults['bendahara'];

            foreach ($allPermissions as $perm) {
                $allowed = in_array($perm, $defaults) ? 1 : 0;
                $stmtInsert->execute([
                    ':uid' => $user['id'],
                    ':pkey' => $perm,
                    ':allowed' => $allowed
                ]);
            }
            $insertedCount++;
        }
    }
    $results[] = ['success', "Default permissions ditambahkan untuk {$insertedCount} user."];

    // ===== 6. Update user 'bendahara' existing yang role-nya masih enum lama =====
    // Jika ada user dengan role 'operator', biarkan saja — mereka sudah punya permissions
    // Pastikan minimal ada satu admin
    $adminCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($adminCount == 0) {
        // Jadikan user pertama sebagai admin
        $firstUser = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetch();
        if ($firstUser) {
            $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = :id")->execute([':id' => $firstUser['id']]);
            // Update permissions juga
            foreach ($allPermissions as $perm) {
                $pdo->prepare("UPDATE user_permissions SET is_allowed = 1 WHERE user_id = :uid AND permission_key = :pkey")
                     ->execute([':uid' => $firstUser['id'], ':pkey' => $perm]);
            }
            $results[] = ['warning', "User pertama (ID: {$firstUser['id']}) dijadikan admin karena belum ada admin."];
        }
    }

    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
    $results[] = ['success', '✅ Migrasi selesai! Silakan hapus file ini.'];

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $results[] = ['danger', '❌ Error fatal: ' . $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration — Roles & Permissions</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background: #f1f5f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); max-width: 600px; width: 100%; padding: 2rem; }
        h2 { font-size: 1.3rem; font-weight: 700; color: #1e293b; margin-bottom: 1.5rem; }
        .result { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 0.5rem; font-size: 0.85rem; }
        .result-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .result-info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        .result-warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .result-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .back-link { display: inline-block; margin-top: 1.5rem; color: #6366f1; text-decoration: none; font-weight: 600; font-size: 0.85rem; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card">
        <h2>🔄 Migration: Roles & Permissions</h2>
        <?php foreach ($results as $r): ?>
            <div class="result result-<?= $r[0] ?>"><?= htmlspecialchars($r[1]) ?></div>
        <?php endforeach; ?>
        <a href="index.php" class="back-link">← Kembali ke Login</a>
    </div>
</body>
</html>
