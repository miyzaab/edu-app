<?php
/**
 * DATA GURU - Nonaktifkan / Aktifkan Guru (Soft Toggle Status)
 * SMP Islamic School of Minhaj Al-Ilmi
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('guru');

$pdo = getConnection();
$id = (int) ($_GET['id'] ?? 0);

if ($id > 0) {
    try {
        $pdo->beginTransaction();

        $stmtGuru = $pdo->prepare("SELECT id, nama_lengkap, COALESCE(is_active, 1) AS is_active FROM users WHERE id = :id AND role = 'guru'");
        $stmtGuru->execute([':id' => $id]);
        $guru = $stmtGuru->fetch();

        if (!$guru) {
            $pdo->rollBack();
            redirect('index.php', 'danger', 'Data guru tidak ditemukan atau bukan berstatus guru.');
        }

        // Jangan nonaktifkan akun sendiri jika sedang login
        if ($id === (int) ($_SESSION['user_id'] ?? 0)) {
            $pdo->rollBack();
            redirect('index.php', 'danger', 'Tidak bisa menonaktifkan akun sendiri.');
        }

        $newStatus = ((int) $guru['is_active'] === 1) ? 0 : 1;

        $stmtUpd = $pdo->prepare("UPDATE users SET is_active = :st WHERE id = :id AND role = 'guru'");
        $stmtUpd->execute([':st' => $newStatus, ':id' => $id]);

        $pdo->commit();

        $label = ($newStatus === 1) ? 'DIAKTIFKAN kembali' : 'DINONAKTIFKAN';
        redirect('index.php', 'success', "Status akun Guru '{$guru['nama_lengkap']}' berhasil {$label}. Seluruh plotting mengajar dan riwayat data tetap aman.");
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirect('index.php', 'danger', 'Gagal mengubah status guru: ' . $e->getMessage());
    }
} else {
    redirect('index.php', 'danger', 'ID Guru tidak valid.');
}
