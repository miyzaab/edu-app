<?php
/**
 * BULK ACTION GURU - Hapus massal, Ubah status aktif/nonaktif massal
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('guru');

$pdo = getConnection();
$action = $_POST['bulk_action_type'] ?? '';
$ids = $_POST['ids'] ?? [];

// Sanitize IDs
$ids = array_filter(array_map('intval', (array) $ids));

if (empty($ids)) {
    redirect('index.php', 'warning', 'Tidak ada data guru yang dipilih.');
}

$count = count($ids);
$placeholders = implode(',', array_fill(0, $count, '?'));

try {
    $pdo->beginTransaction();

    if ($action === 'delete') {
        // Hapus penugasan wali kelas
        $pdo->prepare("UPDATE kelas SET wali_kelas_id = NULL WHERE wali_kelas_id IN ($placeholders)")->execute($ids);

        // Hapus penugasan musyrif halaqah jika ada
        try {
            $pdo->prepare("UPDATE halaqah_kelompok SET musyrif_user_id = NULL WHERE musyrif_user_id IN ($placeholders)")->execute($ids);
        } catch (Exception $e) {}

        // Hapus plotting guru mengajar
        $pdo->prepare("DELETE FROM guru_mapel_kelas WHERE user_id IN ($placeholders)")->execute($ids);

        // Hapus file foto guru jika ada
        $stmtFotos = $pdo->prepare("SELECT foto FROM guru_detail WHERE user_id IN ($placeholders)");
        $stmtFotos->execute($ids);
        foreach ($stmtFotos->fetchAll(PDO::FETCH_COLUMN) as $fPath) {
            if (!empty($fPath)) {
                $fullPath = __DIR__ . '/../../' . ltrim($fPath, '/');
                if (file_exists($fullPath) && is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }
        }

        // Hapus detail guru & permissions
        $pdo->prepare("DELETE FROM guru_detail WHERE user_id IN ($placeholders)")->execute($ids);
        $pdo->prepare("DELETE FROM user_permissions WHERE user_id IN ($placeholders)")->execute($ids);

        // Hapus users
        $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders) AND role = 'guru'");
        $stmt->execute($ids);

        $pdo->commit();
        redirect('index.php', 'success', "🎉 Berhasil menghapus <b>$count</b> data guru secara massal.");
    } elseif ($action === 'activate') {
        $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id IN ($placeholders) AND role = 'guru'");
        $stmt->execute($ids);

        $pdo->commit();
        redirect('index.php', 'success', "🎉 Berhasil MENGAKTIFKAN <b>$count</b> guru.");
    } elseif ($action === 'deactivate') {
        $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id IN ($placeholders) AND role = 'guru'");
        $stmt->execute($ids);

        $pdo->commit();
        redirect('index.php', 'success', "🎉 Berhasil MENONAKTIFKAN <b>$count</b> guru.");
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
