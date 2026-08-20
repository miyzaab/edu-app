<?php
/**
 * TOGGLE LOCK - Ubah visibilitas kelas untuk orang tua
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('kelas');

$pdo    = getConnection();
$id     = (int)($_GET['id'] ?? 0);
$status = (int)($_GET['status'] ?? 0);

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("UPDATE kelas SET is_locked = :status WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $id]);
        
        $msg = $status ? "Kelas berhasil dikunci (tidak terlihat oleh orang tua)." : "Kelas berhasil dibuka (terlihat oleh orang tua).";
        redirect('index.php', 'success', $msg);
    } catch (PDOException $e) {
        if ($e->getCode() == '42S22') {
            $pdo->exec("ALTER TABLE kelas ADD COLUMN is_locked TINYINT(1) DEFAULT 0 AFTER nama_kelas");
            // Retry once
            $stmt = $pdo->prepare("UPDATE kelas SET is_locked = :status WHERE id = :id");
            $stmt->execute([':status' => $status, ':id' => $id]);
            redirect('index.php', 'success', "Status berhasil diubah (kolom database telah diperbaiki otomatis).");
        }
        redirect('index.php', 'danger', "Gagal mengubah status: " . $e->getMessage());
    }
} else {
    redirect('index.php', 'danger', "ID tidak valid.");
}
