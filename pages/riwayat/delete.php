<?php
/**
 * DELETE TRANSAKSI - Hapus riwayat pembayaran
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('riwayat');

$pdo  = getConnection();
$id   = (int)($_GET['id'] ?? 0);
$tipe = $_GET['tipe'] ?? '';

if ($id > 0 && $tipe) {
    try {
        $table = match($tipe) {
            'spp' => 'pembayaran_spp',
            'uang_pangkal' => 'pembayaran_uang_pangkal',
            'lainnya' => 'pembayaran_lain',
            default => ''
        };

        if ($table) {
            $stmt = $pdo->prepare("DELETE FROM $table WHERE id = :id");
            $stmt->execute([':id' => $id]);
            
            $redir = $_GET['redirect'] ?? 'index.php';
            redirect($redir, 'success', 'Transaksi berhasil dihapus.');
        } else {
            redirect('index.php', 'danger', 'Jenis transaksi tidak valid.');
        }
    } catch (PDOException $e) {
        redirect('index.php', 'danger', 'Gagal menghapus transaksi: ' . $e->getMessage());
    }
} else {
    redirect('index.php', 'danger', 'ID atau Tipe tidak valid.');
}
