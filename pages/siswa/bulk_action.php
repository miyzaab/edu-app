<?php
/**
 * BULK ACTION SISWA - Hapus massal, Ubah kelas massal, Ubah status massal
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('siswa');

$pdo = getConnection();
$action = $_POST['bulk_action_type'] ?? '';
$ids = $_POST['ids'] ?? [];

// Sanitize IDs
$ids = array_filter(array_map('intval', (array) $ids));

if (empty($ids)) {
    redirect('index.php', 'warning', 'Tidak ada siswa yang dipilih.');
}

$count = count($ids);
$placeholders = implode(',', array_fill(0, $count, '?'));

try {
    $pdo->beginTransaction();

    if ($action === 'delete' || $action === 'deactivate') {
        // Soft deactivate: ubah status siswa menjadi nonaktif tanpa menghapus data pembayaran
        $pdo->prepare("UPDATE siswa SET status = 'nonaktif' WHERE id IN ($placeholders)")->execute($ids);
        $pdo->prepare("UPDATE users SET is_active = 0 WHERE role = 'ortu' AND siswa_id IN ($placeholders)")->execute($ids);

        $pdo->commit();
        redirect('index.php', 'success', "🎉 Berhasil MENONAKTIFKAN <b>$count</b> data siswa. Seluruh data tagihan dan riwayat pembayaran tetap aman.");
    } elseif ($action === 'activate') {
        $pdo->prepare("UPDATE siswa SET status = 'aktif' WHERE id IN ($placeholders)")->execute($ids);
        $pdo->prepare("UPDATE users SET is_active = 1 WHERE role = 'ortu' AND siswa_id IN ($placeholders)")->execute($ids);

        $pdo->commit();
        redirect('index.php', 'success', "🎉 Berhasil MENGAKTIFKAN kembali <b>$count</b> data siswa.");
    } elseif ($action === 'edit_kelas') {
        $kelasId = (int) ($_POST['target_kelas_id'] ?? 0);
        if ($kelasId <= 0) {
            redirect('index.php', 'danger', 'Kelas tujuan belum dipilih.');
        }

        // Cek nama kelas tujuan
        $stmtK = $pdo->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
        $stmtK->execute([$kelasId]);
        $namaKelas = $stmtK->fetchColumn() ?: 'Kelas Terpilih';

        $params = array_merge([$kelasId], $ids);
        $stmt = $pdo->prepare("UPDATE siswa SET kelas_id = ? WHERE id IN ($placeholders)");
        $stmt->execute($params);

        $pdo->commit();
        redirect('index.php', 'success', "🎉 Berhasil memindahkan <b>$count</b> siswa ke kelas <b>$namaKelas</b>.");
    } elseif ($action === 'edit_status') {
        $newStatus = trim($_POST['target_status'] ?? '');
        if (!in_array($newStatus, ['aktif', 'nonaktif', 'lulus', 'pindah'])) {
            redirect('index.php', 'danger', 'Status tidak valid.');
        }

        $params = array_merge([$newStatus], $ids);
        $stmt = $pdo->prepare("UPDATE siswa SET status = ? WHERE id IN ($placeholders)");
        $stmt->execute($params);

        $pdo->commit();
        redirect('index.php', 'success', "🎉 Berhasil mengubah status <b>$count</b> siswa menjadi <b>" . ucfirst($newStatus) . "</b>.");
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
