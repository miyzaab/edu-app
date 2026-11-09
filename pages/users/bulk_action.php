<?php
/**
 * BULK ACTION USERS - Hapus massal, Toggle Status Aktif/Nonaktif, Reset Password Massal
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('users');

$pdo = getConnection();
$action = $_POST['bulk_action_type'] ?? '';
$ids = $_POST['ids'] ?? [];

// Sanitize IDs
$ids = array_filter(array_map('intval', (array) $ids));

// Cegah menghapus atau menonaktifkan akun sendiri yang sedang login
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$ids = array_diff($ids, [$currentUserId]);

if (empty($ids)) {
    redirect('index.php', 'warning', 'Tidak ada user yang valid untuk diproses (akun Anda sendiri tidak dapat diubah massal).');
}

$count = count($ids);
$placeholders = implode(',', array_fill(0, $count, '?'));

try {
    $pdo->beginTransaction();

    if ($action === 'delete' || $action === 'deactivate') {
        // Soft deactivate: nonaktifkan akun tanpa menghapus data
        $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        $pdo->commit();
        redirect('index.php', 'success', "🎉 Berhasil MENONAKTIFKAN <b>$count</b> user. Riwayat transaksi dan data akun tetap aman.");
    } elseif ($action === 'activate') {
        $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        $pdo->commit();
        redirect('index.php', 'success', "🎉 Berhasil MENGAKTIFKAN <b>$count</b> user.");
    } elseif ($action === 'deactivate') {
        $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        $pdo->commit();
        redirect('index.php', 'success', "🎉 Berhasil MENONAKTIFKAN <b>$count</b> user.");
    } elseif ($action === 'reset_password') {
        // Reset password ke username masing-masing
        $users = $pdo->query("SELECT id, username FROM users WHERE id IN (" . implode(',', $ids) . ")")->fetchAll();
        $stmtUpd = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id");

        foreach ($users as $u) {
            $hash = password_hash($u['username'], PASSWORD_DEFAULT);
            $stmtUpd->execute([':p' => $hash, ':id' => $u['id']]);
        }

        $pdo->commit();
        redirect('index.php', 'success', "🎉 Berhasil me-reset password <b>$count</b> user ke username masing-masing.");
    } else {
        $pdo->rollBack();
        redirect('index.php', 'warning', 'Aksi massal tidak dikenali.');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirect('index.php', 'danger', 'Terjadi kesalahan: ' . $e->getMessage());
}
