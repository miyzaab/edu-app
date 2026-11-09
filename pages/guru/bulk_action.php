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

    if ($action === 'delete' || $action === 'deactivate') {
        // Soft deactivate: nonaktifkan akun guru tanpa menghapus plotting atau data
        $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id IN ($placeholders) AND role = 'guru'");
        $stmt->execute($ids);

        $pdo->commit();
        redirect('index.php', 'success', "🎉 Berhasil MENONAKTIFKAN <b>$count</b> data guru. Plotting mengajar dan riwayat tetap aman.");
    } elseif ($action === 'activate') {
        $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id IN ($placeholders) AND role = 'guru'");
        $stmt->execute($ids);

        $pdo->commit();
        redirect('index.php', 'success', "🎉 Berhasil MENGAKTIFKAN kembali <b>$count</b> data guru.");
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
