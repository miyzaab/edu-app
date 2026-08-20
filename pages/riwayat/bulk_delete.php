<?php
/**
 * BULK DELETE - Hapus banyak transaksi sekaligus
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('riwayat');

$pdo = getConnection();
$ids = $_POST['ids'] ?? [];

if (!empty($ids)) {
    try {
        $pdo->beginTransaction();
        
        $count = 0;
        foreach ($ids as $val) {
            // format value: "tipe|id"
            $parts = explode('|', $val);
            if (count($parts) === 2) {
                $tipe = strtolower($parts[0]);
                $id   = (int)$parts[1];
                
                $table = match($tipe) {
                    'spp' => 'pembayaran_spp',
                    'uang_pangkal' => 'pembayaran_uang_pangkal',
                    'lainnya' => 'pembayaran_lain',
                    default => ''
                };
                
                if ($table) {
                    $stmt = $pdo->prepare("DELETE FROM $table WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                    $count++;
                }
            }
        }
        
        $pdo->commit();
        redirect('index.php', 'success', "$count transaksi berhasil dihapus secara massal.");
    } catch (PDOException $e) {
        $pdo->rollBack();
        redirect('index.php', 'danger', 'Gagal menghapus massal: ' . $e->getMessage());
    }
} else {
    redirect('index.php', 'warning', 'Tidak ada transaksi yang dipilih.');
}
