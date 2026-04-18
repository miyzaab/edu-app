<?php
/**
 * DATA KELAS - Hapus Kelas
 */
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();
$id = (int)($_GET['id'] ?? 0);

if ($id) {
    try {
        // Cek apakah ada siswa di kelas ini
        $cek = $pdo->prepare("SELECT COUNT(id) FROM siswa WHERE kelas_id = :id");
        $cek->execute([':id' => $id]);
        $jumlah = $cek->fetchColumn();
        
        if ($jumlah > 0) {
            redirect('index.php', 'danger', "Tidak dapat menghapus kelas ini karena masih ada $jumlah siswa yang terdaftar di dalamnya.");
        } else {
            $stmt = $pdo->prepare("DELETE FROM kelas WHERE id = :id");
            $stmt->execute([':id' => $id]);
            redirect('index.php', 'success', 'Data kelas berhasil dihapus.');
        }
    } catch (PDOException $e) {
        redirect('index.php', 'danger', 'Gagal menghapus data: ' . $e->getMessage());
    }
} else {
    redirect('index.php', 'danger', 'ID Kelas tidak valid.');
}
