<?php
/**
 * DATA SISWA - Hapus siswa
 */
require_once __DIR__ . '/../../config/auth.php';
$pdo = getConnection();

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    try {
        // Gunakan transaksi agar aman (hapus data pembayaran terkait dulu)
        $pdo->beginTransaction();
        
        $pdo->prepare("DELETE FROM pembayaran_pending WHERE siswa_id = :id")->execute([':id' => $id]);
        $pdo->prepare("DELETE FROM pembayaran_spp WHERE siswa_id = :id")->execute([':id' => $id]);
        $pdo->prepare("DELETE FROM pembayaran_uang_pangkal WHERE siswa_id = :id")->execute([':id' => $id]);
        $pdo->prepare("DELETE FROM pembayaran_lain WHERE siswa_id = :id")->execute([':id' => $id]);
        
        $stmt = $pdo->prepare("DELETE FROM siswa WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        $pdo->commit();
        redirect('index.php', 'success', 'Data siswa beserta riwayat pembayarannya berhasil dihapus.');
    } catch (PDOException $e) {
        $pdo->rollBack();
        redirect('index.php', 'danger', 'Gagal menghapus: ' . $e->getMessage());
    }
} else {
    redirect('index.php', 'danger', 'ID tidak valid.');
}
