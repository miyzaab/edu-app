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

    if ($action === 'delete') {
        // Hapus pembayaran & tagihan terkait agar tidak foreign key error
        $pdo->prepare("DELETE FROM pembayaran_spp WHERE siswa_id IN ($placeholders)")->execute($ids);
        $pdo->prepare("DELETE FROM pembayaran_uang_pangkal WHERE siswa_id IN ($placeholders)")->execute($ids);
        $pdo->prepare("DELETE FROM pembayaran_lain WHERE siswa_id IN ($placeholders)")->execute($ids);
        $pdo->prepare("DELETE FROM uang_pangkal_siswa WHERE siswa_id IN ($placeholders)")->execute($ids);

        // Hapus akun ortu terkait
        $pdo->prepare("DELETE FROM users WHERE role = 'ortu' AND siswa_id IN ($placeholders)")->execute($ids);

        // Hapus siswa
        $stmt = $pdo->prepare("DELETE FROM siswa WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        $pdo->commit();
        redirect('index.php', 'success', "🎉 Berhasil menghapus <b>$count</b> siswa secara massal.");
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
