<?php
/**
 * PETTY CASH - Hapus Transaksi
 */
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();
$id = (int)($_GET['id'] ?? 0);

if ($id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM petty_cash WHERE id = :id");
        $stmt->execute([':id' => $id]);
        redirect('index.php', 'success', 'Transaksi berhasil dihapus.');
    } catch (PDOException $e) {
        redirect('index.php', 'danger', 'Gagal menghapus transaksi: ' . $e->getMessage());
    }
} else {
    redirect('index.php', 'danger', 'ID Transaksi tidak valid.');
}
