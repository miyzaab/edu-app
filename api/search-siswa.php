<?php
/**
 * API Endpoint: Cari siswa berdasarkan nama atau NIS
 */
require_once __DIR__ . '/../config/koneksi.php';

header('Content-Type: application/json');

$query    = trim($_GET['q'] ?? '');
$kelas_id = (int)($_GET['kelas_id'] ?? 0);

if (strlen($query) < 1 && $kelas_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = getConnection();
    $kelas_id = (int)($_GET['kelas_id'] ?? 0);
    
    $sql = "
        SELECT s.id, s.nis, s.nama, k.nama_kelas, s.kelas_id 
        FROM siswa s 
        JOIN kelas k ON s.kelas_id = k.id 
        WHERE (s.nama LIKE :q1 OR s.nis LIKE :q2) 
    ";
    
    $params = [':q1' => "%$query%", ':q2' => "%$query%"];
    
    if ($kelas_id > 0) {
        $sql .= " AND s.kelas_id = :kelas_id";
        $params[':kelas_id'] = $kelas_id;
    }
    
    $sql .= " AND s.status = 'aktif' ORDER BY s.nama ASC LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $siswa = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($siswa);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
