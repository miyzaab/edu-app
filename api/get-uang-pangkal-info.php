<?php
/**
 * API: Get Uang Pangkal Progress for a student
 */
require_once __DIR__ . '/../config/koneksi.php';

$siswa_id = (int)($_GET['siswa_id'] ?? 0);

if (!$siswa_id) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid Student ID']);
    exit;
}

try {
    $pdo = getConnection();
    
    // 1. Ambil target dari tabel siswa
    try {
        $stmt = $pdo->prepare("SELECT target_uang_pangkal, is_lunas_uang_pangkal FROM siswa WHERE id = :id");
        $stmt->execute([':id' => $siswa_id]);
        $siswa = $stmt->fetch();
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database belum diperbarui. Harap jalankan update_db_feature.php']);
        exit;
    }
    
    if (!$siswa) {
        throw new Exception('Siswa tidak ditemukan');
    }

    $target = (float)$siswa['target_uang_pangkal'];

    // 2. Ambil total yang sudah dibayar dari tabel pembayaran_uang_pangkal
    $stmt = $pdo->prepare("SELECT SUM(nominal) as total_paid FROM pembayaran_uang_pangkal WHERE siswa_id = :id");
    $stmt->execute([':id' => $siswa_id]);
    $paid = (float)$stmt->fetch()['total_paid'];

    // 3. Ambil total yang sedang menunggu verifikasi
    $stmt = $pdo->prepare("SELECT SUM(nominal) as pending_paid FROM pembayaran_pending WHERE siswa_id = :id AND jenis = 'uang_pangkal' AND status = 'pending'");
    $stmt->execute([':id' => $siswa_id]);
    $pending = (float)$stmt->fetch()['pending_paid'];

    $total_efektif = $paid; // Kita bisa memilih apakah menyertakan pending atau tidak. Biasanya tidak sampai disetujui.
    $remaining = $target - $total_efektif;
    if ($remaining < 0) $remaining = 0;

    $percent = ($target > 0) ? ($total_efektif / $target) * 100 : 0;
    if ($percent > 100) $percent = 100;

    header('Content-Type: application/json');
    echo json_encode([
        'target' => $target,
        'paid' => $total_efektif,
        'pending' => $pending,
        'remaining' => $remaining,
        'percent' => round($percent, 2),
        'is_lunas' => ($siswa['is_lunas_uang_pangkal'] == 1) || ($remaining <= 0 && $target > 0)
    ]);

} catch (Exception $e) {
    header('Content-Type: application/json', true, 500);
    echo json_encode(['error' => $e->getMessage()]);
}
