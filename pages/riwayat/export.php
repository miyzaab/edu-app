<?php
/**
 * EXPORT EXCEL - Riwayat Pembayaran
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('riwayat');

$pdo = getConnection();

// Ambil input filter
$tgl_awal  = $_GET['tgl_awal'] ?? '';
$tgl_akhir = $_GET['tgl_akhir'] ?? '';
$nama      = $_GET['nama'] ?? '';
$kelas_id  = $_GET['kelas_id'] ?? '';
$nominal   = $_GET['nominal'] ?? '';
$metode    = $_GET['metode'] ?? '';

$where = [];
$params = [];

if ($tgl_awal) {
    $where[] = "t.tanggal_bayar >= :tgl_awal";
    $params[':tgl_awal'] = $tgl_awal;
}
if ($tgl_akhir) {
    $where[] = "t.tanggal_bayar <= :tgl_akhir";
    $params[':tgl_akhir'] = $tgl_akhir;
}
if ($nama) {
    $where[] = "s.nama LIKE :nama";
    $params[':nama'] = "%$nama%";
}
if ($kelas_id) {
    $where[] = "s.kelas_id = :kelas_id";
    $params[':kelas_id'] = $kelas_id;
}
if ($nominal) {
    $clean_nominal = str_replace(['.', ','], ['', '.'], $nominal);
    $where[] = "t.nominal = :nominal";
    $params[':nominal'] = $clean_nominal;
}
if ($metode) {
    $where[] = "t.metode_bayar = :metode";
    $params[':metode'] = $metode;
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Query gabungan (UNION) untuk semua jenis pembayaran
$query = "
    SELECT t.*, s.nama, s.nis, k.nama_kelas 
    FROM (
        SELECT 'SPP' as tipe, id, siswa_id, nominal, tanggal_bayar, metode_bayar, CONCAT('Bulan ', bulan, '/', tahun) as detail FROM pembayaran_spp
        UNION ALL
        SELECT 'Uang Pangkal' as tipe, id, siswa_id, nominal, tanggal_bayar, metode_bayar, '-' as detail FROM pembayaran_uang_pangkal
        UNION ALL
        SELECT 'Lainnya' as tipe, id, siswa_id, nominal, tanggal_bayar, metode_bayar, keterangan as detail FROM pembayaran_lain
    ) t
    JOIN siswa s ON t.siswa_id = s.id
    JOIN kelas k ON s.kelas_id = k.id
    $whereClause
    ORDER BY t.tanggal_bayar DESC, t.id DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$riwayat = $stmt->fetchAll();

$totalNominal = array_sum(array_column($riwayat, 'nominal'));
$namaSekolah = getSetting('nama_sekolah', SCHOOL_NAME);

// Format filename
$suffix = date('Ymd_His');
if ($tgl_awal && $tgl_akhir) $suffix = $tgl_awal . '_sd_' . $tgl_akhir;

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Riwayat_Pembayaran_' . $suffix . '.xls"');
header('Cache-Control: max-age=0');
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
<meta charset="UTF-8">
<style>
    td, th { mso-number-format:\@; border: 0.5pt solid #ccc; padding: 5px; }
    .header { background: #2F5597; color: #ffffff; font-weight: bold; text-align: center; }
    .number { mso-number-format:"#,##0"; text-align: right; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .fw-bold { font-weight: bold; }
    .even { background: #D9E1F2; }
</style>
</head>
<body>

<table>
    <tr><td colspan="9" style="font-size:16pt;font-weight:bold"><?= htmlspecialchars($namaSekolah) ?></td></tr>
    <tr><td colspan="9" style="font-size:12pt;font-weight:bold">RIWAYAT PEMBAYARAN</td></tr>
    <tr><td colspan="9">Dicetak pada: <?= date('d/m/Y H:i') ?></td></tr>
    <?php if ($tgl_awal || $tgl_akhir): ?>
        <tr><td colspan="9">Periode: <?= $tgl_awal ?: '...' ?> s/d <?= $tgl_akhir ?: '...' ?></td></tr>
    <?php endif; ?>
    <tr><td colspan="9"></td></tr>
</table>

<table border="1">
    <thead>
        <tr class="header">
            <th>No</th>
            <th>Tanggal</th>
            <th>NIS</th>
            <th>Nama Siswa</th>
            <th>Kelas</th>
            <th>Jenis Pembayaran</th>
            <th>Detail</th>
            <th>Metode</th>
            <th>Nominal (Rp)</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($riwayat as $i => $r): ?>
        <tr class="<?= $i % 2 === 1 ? 'even' : '' ?>">
            <td class="text-center"><?= $i+1 ?></td>
            <td><?= date('d F Y', strtotime($r['tanggal_bayar'])) ?></td>
            <td><?= htmlspecialchars($r['nis']) ?></td>
            <td><?= htmlspecialchars($r['nama']) ?></td>
            <td class="text-center"><?= htmlspecialchars($r['nama_kelas']) ?></td>
            <td><?= htmlspecialchars($r['tipe']) ?></td>
            <td><?= htmlspecialchars($r['detail']) ?></td>
            <td><?= ucfirst($r['metode_bayar']) ?></td>
            <td class="number"><?= (int)$r['nominal'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr style="background:#f8f9fa;font-weight:bold">
            <td colspan="8" class="text-right">TOTAL</td>
            <td class="number"><?= (int)$totalNominal ?></td>
        </tr>
    </tfoot>
</table>

</body>
</html>
