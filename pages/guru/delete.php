<?php
/**
 * DATA GURU - Hapus Data Guru
 * SMP Islamic School of Minhaj Al-Ilmi
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('guru');

$pdo = getConnection();
$id = (int) ($_GET['id'] ?? 0);

if ($id > 0) {
    try {
        $pdo->beginTransaction();

        // Ambil nama guru sebelum dihapus
        $stmtName = $pdo->prepare("SELECT nama_lengkap FROM users WHERE id = :id AND role = 'guru'");
        $stmtName->execute([':id' => $id]);
        $guruName = $stmtName->fetchColumn();

        if (!$guruName) {
            $pdo->rollBack();
            redirect('index.php', 'danger', 'Data guru tidak ditemukan atau bukan berstatus guru.');
        }

        // 1. Reset penugasan wali kelas
        $pdo->prepare("UPDATE kelas SET wali_kelas_id = NULL WHERE wali_kelas_id = :id")->execute([':id' => $id]);

        // 2. Reset pembimbing / musyrif halaqah jika ada
        try {
            $pdo->prepare("UPDATE halaqah_kelompok SET musyrif_user_id = NULL WHERE musyrif_user_id = :id")->execute([':id' => $id]);
        } catch (Exception $e) {
            // Abaikan jika tabel belum ada
        }

        // 3. Hapus plotting guru mengajar
        $pdo->prepare("DELETE FROM guru_mapel_kelas WHERE user_id = :id")->execute([':id' => $id]);

        // 4. Hapus hak akses / user permissions
        $pdo->prepare("DELETE FROM user_permissions WHERE user_id = :id")->execute([':id' => $id]);

        // 5. Hapus detail guru & file foto jika ada
        $stmtFoto = $pdo->prepare("SELECT foto FROM guru_detail WHERE user_id = :id LIMIT 1");
        $stmtFoto->execute([':id' => $id]);
        $fotoPath = $stmtFoto->fetchColumn();
        if (!empty($fotoPath)) {
            $fullFotoPath = __DIR__ . '/../../' . ltrim($fotoPath, '/');
            if (file_exists($fullFotoPath) && is_file($fullFotoPath)) {
                @unlink($fullFotoPath);
            }
        }
        $pdo->prepare("DELETE FROM guru_detail WHERE user_id = :id")->execute([':id' => $id]);

        // 6. Hapus user guru
        $pdo->prepare("DELETE FROM users WHERE id = :id AND role = 'guru'")->execute([':id' => $id]);

        $pdo->commit();
        redirect('index.php', 'success', "Data Guru '{$guruName}' beserta seluruh penugasan mengajar dan hak aksesnya berhasil dihapus.");
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirect('index.php', 'danger', 'Gagal menghapus data guru: ' . $e->getMessage());
    }
} else {
    redirect('index.php', 'danger', 'ID Guru tidak valid.');
}
