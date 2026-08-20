<?php
/**
 * KWITANSI UANG PANGKAL - Menggunakan template shared
 */
require_once __DIR__ . '/../../config/koneksi.php';

$pdo = getConnection();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT up.*, s.nama, s.nis, k.nama_kelas, u.nama_lengkap AS bendahara
    FROM pembayaran_uang_pangkal up
    JOIN siswa s ON up.siswa_id = s.id
    JOIN kelas k ON s.kelas_id = k.id
    JOIN users u ON up.user_id = u.id
    WHERE up.id = :id
");
$stmt->execute([':id' => $id]);
$data = $stmt->fetch();
if (!$data) { redirect('index.php', 'danger', 'Data tidak ditemukan.'); }

$kwitansiNo    = 'UP-' . str_pad($data['id'], 6, '0', STR_PAD_LEFT);
$kwitansiJudul = 'KWITANSI UANG PANGKAL';
$nominal       = $data['nominal'];
$backUrl       = 'index.php';

$rows = [
    ['label' => 'Tanggal',      'value' => formatTanggal($data['tanggal_bayar'])],
    ['label' => 'NIS',           'value' => htmlspecialchars($data['nis'])],
    ['label' => 'Nama Siswa',   'value' => htmlspecialchars($data['nama'])],
    ['label' => 'Kelas',        'value' => htmlspecialchars($data['nama_kelas'])],
    ['label' => 'Pembayaran',   'value' => 'Uang Pangkal'],
    ['label' => 'Metode',       'value' => ucfirst($data['metode_bayar'])],
];
if ($data['keterangan']) {
    $rows[] = ['label' => 'Keterangan', 'value' => htmlspecialchars($data['keterangan'])];
}

require_once __DIR__ . '/../../includes/kwitansi_template.php';
