<?php
/**
 * BULK EDIT - Update banyak transaksi sekaligus
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('riwayat');

$pdo = getConnection();
$ids = $_POST['ids'] ?? [];

if (!empty($ids)) {
    $updateFields = [];
    $updateParams = [];

    if (isset($_POST['update_tanggal']) && !empty($_POST['tanggal_bayar'])) {
        $updateFields[] = "tanggal_bayar = :tgl";
        $updateParams[':tgl'] = $_POST['tanggal_bayar'];
    }
    if (isset($_POST['update_metode']) && !empty($_POST['metode_bayar'])) {
        $updateFields[] = "metode_bayar = :met";
        $updateParams[':met'] = $_POST['metode_bayar'];
    }
    if (isset($_POST['update_keterangan'])) {
        $updateFields[] = "keterangan = :ket";
        $updateParams[':ket'] = trim($_POST['keterangan']);
    }

    if (empty($updateFields)) {
        redirect('index.php', 'warning', 'Pilih minimal satu kolom untuk diperbarui.');
    }

    try {
        $pdo->beginTransaction();
        
        $count = 0;
        $fieldSql = implode(", ", $updateFields);

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
                    $stmt = $pdo->prepare("UPDATE $table SET $fieldSql WHERE id = :id");
                    $stmt->execute(array_merge($updateParams, [':id' => $id]));
                    $count++;
                }
            }
        }
        
        $pdo->commit();
        redirect('index.php', 'success', "$count transaksi berhasil diperbarui secara massal.");
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redirect('index.php', 'danger', 'Gagal memperbarui massal: ' . $e->getMessage());
    }
} else {
    redirect('index.php', 'warning', 'Tidak ada transaksi yang dipilih.');
}
