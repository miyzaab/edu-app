<?php
/**
 * KWITANSI SPP - Menggunakan template shared
 */
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT spp.*, s.nama, s.nis, k.nama_kelas, u.nama_lengkap AS bendahara
    FROM pembayaran_spp spp
    JOIN siswa s ON spp.siswa_id = s.id
    JOIN kelas k ON s.kelas_id = k.id
    JOIN users u ON spp.user_id = u.id
    WHERE spp.id = :id
");
$stmt->execute([':id' => $id]);
$data = $stmt->fetch();
if (!$data) { redirect('index.php', 'danger', 'Data tidak ditemukan.'); }

// Siapkan variabel untuk template
$kwitansiNo    = 'SPP-' . str_pad($data['id'], 6, '0', STR_PAD_LEFT);
$kwitansiJudul = 'KWITANSI PEMBAYARAN SPP';
$nominal       = $data['nominal'];
$backUrl       = 'index.php';

$rows = [
    ['label' => 'Tanggal',      'value' => formatTanggal($data['tanggal_bayar'])],
    ['label' => 'NIS',           'value' => htmlspecialchars($data['nis'])],
    ['label' => 'Nama Siswa',   'value' => htmlspecialchars($data['nama'])],
    ['label' => 'Kelas',        'value' => htmlspecialchars($data['nama_kelas'])],
    ['label' => 'Pembayaran',   'value' => 'SPP ' . namaBulan($data['bulan']) . ' ' . $data['tahun']],
    ['label' => 'Metode',       'value' => ucfirst($data['metode_bayar'])],
];
if ($data['keterangan']) {
    $rows[] = ['label' => 'Keterangan', 'value' => htmlspecialchars($data['keterangan'])];
}

require_once __DIR__ . '/../../includes/kwitansi_template.php';
