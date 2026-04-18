<?php
/**
 * API Endpoint: Ambil daftar siswa berdasarkan kelas
 */
require_once __DIR__ . '/../config/koneksi.php';

header('Content-Type: application/json');

$kelas_id = (int)($_GET['kelas_id'] ?? 0);

if (!$kelas_id) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT id, nis, nama FROM siswa WHERE kelas_id = :kelas_id ORDER BY nama ASC");
    $stmt->execute([':kelas_id' => $kelas_id]);
    $siswa = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($siswa);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
