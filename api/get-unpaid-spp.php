<?php
/**
 * API Endpoint: Ambil daftar bulan SPP yang BELUM LUNAS untuk siswa tertentu
 * Parameter: siswa_id, tahun
 * Returns: Array bulan yang belum dibayar (exclude yang sudah lunas & pending)
 */
require_once __DIR__ . '/../config/koneksi.php';

header('Content-Type: application/json');

$siswaId = (int)($_GET['siswa_id'] ?? 0);
$tahun   = (int)($_GET['tahun'] ?? date('Y'));

if (!$siswaId || !$tahun) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = getConnection();

    // Bulan yang sudah LUNAS (di tabel pembayaran_spp)
    $stmtLunas = $pdo->prepare("SELECT bulan FROM pembayaran_spp WHERE siswa_id = :s AND tahun = :t");
    $stmtLunas->execute([':s' => $siswaId, ':t' => $tahun]);
    $bulanLunas = $stmtLunas->fetchAll(PDO::FETCH_COLUMN);

    // Bulan yang sedang PENDING (di tabel pembayaran_pending)
    $stmtPending = $pdo->prepare("SELECT bulan FROM pembayaran_pending WHERE siswa_id = :s AND tahun = :t AND jenis = 'spp' AND status = 'pending'");
    $stmtPending->execute([':s' => $siswaId, ':t' => $tahun]);
    $bulanPending = $stmtPending->fetchAll(PDO::FETCH_COLUMN);

    // Gabungkan bulan yang tidak tersedia
    $bulanTerpakai = array_merge($bulanLunas, $bulanPending);

    // Nama bulan helper
    $namaBulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    // Buat array bulan yang tersedia
    $result = [];
    for ($m = 1; $m <= 12; $m++) {
        if (!in_array($m, $bulanTerpakai)) {
            $result[] = ['bulan' => $m, 'nama' => $namaBulan[$m]];
        }
    }

    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
