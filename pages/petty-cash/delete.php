<?php
/**
 * PETTY CASH - Hapus Transaksi
 */
require_once __DIR__ . '/../../config/auth.php';

// Cek permission atau role yang diperbolehkan
if (!hasPermission('petty_cash') && !in_array($_SESSION['role'] ?? '', ['admin', 'bendahara', 'operator'])) {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Anda tidak memiliki hak akses untuk menghapus data transaksi Buku Kas.'
    ];
    header('Location: ' . BASE_URL . '/pages/petty-cash/index.php');
    exit;
}

$pdo = getConnection();
$id = (int) ($_GET['id'] ?? 0);

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM petty_cash WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => 'Transaksi Buku Kas berhasil dihapus.'
        ];
    } catch (PDOException $e) {
        $_SESSION['flash'] = [
            'type' => 'danger',
            'message' => 'Gagal menghapus transaksi: ' . $e->getMessage()
        ];
    }
} else {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'ID Transaksi tidak valid.'
    ];
}

header('Location: ' . BASE_URL . '/pages/petty-cash/index.php');
exit;

