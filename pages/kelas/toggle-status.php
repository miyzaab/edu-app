<?php
/**
 * TOGGLE STATUS KELAS - Aktifkan / Nonaktifkan Kelas
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('kelas');

$pdo = getConnection();
$id = (int) ($_GET['id'] ?? 0);
$status = trim($_GET['status'] ?? '');

if (!in_array($status, ['aktif', 'nonaktif'])) {
    $status = 'aktif';
}

$isActive = ($status === 'aktif') ? 1 : 0;

if ($id > 0) {
    try {
        // Cek kolom di tabel kelas
        $checkCol = $pdo->query("SHOW COLUMNS FROM kelas LIKE 'status'")->fetch();
        if (!$checkCol) {
            $pdo->exec("ALTER TABLE kelas ADD COLUMN status ENUM('aktif','nonaktif') DEFAULT 'aktif' AFTER tingkat");
            $pdo->exec("ALTER TABLE kelas ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER status");
        }

        $stmt = $pdo->prepare("UPDATE kelas SET status = :status, is_active = :is_act WHERE id = :id");
        $stmt->execute([
            ':status' => $status,
            ':is_act' => $isActive,
            ':id' => $id
        ]);

        $msg = ($status === 'aktif')
            ? "Kelas berhasil diaktifkan."
            : "Kelas berhasil dinonaktifkan.";
        redirect('index.php', 'success', $msg);
    } catch (PDOException $e) {
        redirect('index.php', 'danger', "Gagal mengubah status kelas: " . $e->getMessage());
    }
} else {
    redirect('index.php', 'danger', "ID Kelas tidak valid.");
}
