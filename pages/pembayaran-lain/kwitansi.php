<?php
/**
 * KWITANSI PEMBAYARAN LAIN - Menggunakan template shared
 */
require_once __DIR__ . '/../../config/koneksi.php';

$pdo = getConnection();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT pl.*, s.nama, s.nis, k.nama_kelas, jp.nama_pembayaran, u.nama_lengkap AS bendahara
    FROM pembayaran_lain pl
    JOIN siswa s ON pl.siswa_id = s.id
    JOIN kelas k ON s.kelas_id = k.id
    JOIN jenis_pembayaran jp ON pl.jenis_pembayaran_id = jp.id
    JOIN users u ON pl.user_id = u.id
    WHERE pl.id = :id
");
$stmt->execute([':id' => $id]);
$data = $stmt->fetch();
if (!$data) { redirect('index.php', 'danger', 'Data tidak ditemukan.'); }

$kwitansiNo    = 'PL-' . str_pad($data['id'], 6, '0', STR_PAD_LEFT);
$kwitansiJudul = 'KWITANSI PEMBAYARAN';
$nominal       = $data['nominal'];
$backUrl       = 'index.php';

$rows = [
    ['label' => 'Tanggal',      'value' => formatTanggal($data['tanggal_bayar'])],
    ['label' => 'NIS',           'value' => htmlspecialchars($data['nis'])],
    ['label' => 'Nama Siswa',   'value' => htmlspecialchars($data['nama'])],
    ['label' => 'Kelas',        'value' => htmlspecialchars($data['nama_kelas'])],
    ['label' => 'Pembayaran',   'value' => htmlspecialchars($data['nama_pembayaran'])],
    ['label' => 'Metode',       'value' => ucfirst($data['metode_bayar'])],
];
if ($data['keterangan']) {
    $rows[] = ['label' => 'Keterangan', 'value' => htmlspecialchars($data['keterangan'])];
}

require_once __DIR__ . '/../../includes/kwitansi_template.php';
