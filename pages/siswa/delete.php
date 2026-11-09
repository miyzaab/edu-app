<?php
/**
 * DATA SISWA - Nonaktifkan / Aktifkan Siswa (Soft Status Toggle)
 * SMP Islamic School of Minhaj Al-Ilmi
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('siswa');
$pdo = getConnection();

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    try {
        $pdo->beginTransaction();

        $stmtS = $pdo->prepare("SELECT id, nama, status FROM siswa WHERE id = :id LIMIT 1");
        $stmtS->execute([':id' => $id]);
        $siswa = $stmtS->fetch();

        if (!$siswa) {
            $pdo->rollBack();
            redirect('index.php', 'danger', 'Data santri tidak ditemukan.');
        }

        $newStatus = ($siswa['status'] === 'aktif') ? 'nonaktif' : 'aktif';

        $stmt = $pdo->prepare("UPDATE siswa SET status = :st WHERE id = :id");
        $stmt->execute([':st' => $newStatus, ':id' => $id]);

        // Sesuaikan juga status aktif akun ortu terkait jika ada
        $pdo->prepare("UPDATE users SET is_active = :act WHERE role = 'ortu' AND siswa_id = :sid")
            ->execute([':act' => ($newStatus === 'aktif' ? 1 : 0), ':sid' => $id]);

        $pdo->commit();

        $label = ($newStatus === 'aktif') ? 'DIAKTIFKAN kembali' : 'DINONAKTIFKAN';
        redirect('index.php', 'success', "Status santri '{$siswa['nama']}' berhasil {$label}. Seluruh riwayat pembayaran dan data tetap aman.");
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirect('index.php', 'danger', 'Gagal mengubah status santri: ' . $e->getMessage());
    }
} else {
    redirect('index.php', 'danger', 'ID santri tidak valid.');
}
