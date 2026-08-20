<?php
/**
 * API Endpoint: Cari & Ambil Data Siswa + Saldo Kantin (Untuk Live Scanner Barcode POS)
 */
require_once __DIR__ . '/../config/koneksi.php';

header('Content-Type: application/json');

$nis     = trim($_GET['nis'] ?? $_POST['nis'] ?? '');
$siswaId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if (empty($nis) && $siswaId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'NIS atau ID Siswa tidak boleh kosong.'
    ]);
    exit;
}

try {
    $pdo = getConnection();
    
    if (!empty($nis)) {
        $stmt = $pdo->prepare("
            SELECT s.id, s.nis, s.nama, s.status, k.nama_kelas, COALESCE(ss.saldo, 0) AS saldo
            FROM siswa s
            JOIN kelas k ON s.kelas_id = k.id
            LEFT JOIN saldo_siswa ss ON s.id = ss.siswa_id
            WHERE (s.nis = :nis OR s.nama = :nis_nama) AND s.status = 'aktif'
            LIMIT 1
        ");
        $stmt->execute([':nis' => $nis, ':nis_nama' => $nis]);
    } else {
        $stmt = $pdo->prepare("
            SELECT s.id, s.nis, s.nama, s.status, k.nama_kelas, COALESCE(ss.saldo, 0) AS saldo
            FROM siswa s
            JOIN kelas k ON s.kelas_id = k.id
            LEFT JOIN saldo_siswa ss ON s.id = ss.siswa_id
            WHERE s.id = :sid AND s.status = 'aktif'
            LIMIT 1
        ");
        $stmt->execute([':sid' => $siswaId]);
    }

    $siswa = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($siswa) {
        $siswa['saldo'] = (float)$siswa['saldo'];
        $siswa['formatted_saldo'] = 'Rp ' . number_format($siswa['saldo'], 0, ',', '.');
        echo json_encode([
            'success' => true,
            'siswa'   => $siswa
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Siswa dengan NIS / Barcode [' . htmlspecialchars($nis) . '] tidak ditemukan atau status tidak aktif.'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error Server: ' . $e->getMessage()
    ]);
}
