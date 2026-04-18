<?php
/**
 * EXPORT EXCEL - Laporan Keuangan
 * Menggunakan format HTML table + MIME type Excel agar rapih dibuka di Excel/Spreadsheet
 */
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

$filterBulan = $_GET['bulan'] ?? '';
$filterTahun = $_GET['tahun'] ?? date('Y');
$filterJenis = $_GET['jenis'] ?? 'semua';

// Ambil data
$results = [];
$whereMonth = $filterBulan ? " AND MONTH(tanggal_bayar) = " . (int)$filterBulan : "";
$whereYear  = " AND YEAR(tanggal_bayar) = " . (int)$filterTahun;

if ($filterJenis === 'semua' || $filterJenis === 'spp') {
    $rows = $pdo->query("SELECT spp.tanggal_bayar, s.nis, s.nama, k.nama_kelas, 'SPP' AS jenis, CONCAT('Bulan ', spp.bulan, '/', spp.tahun) AS detail, spp.nominal, spp.metode_bayar FROM pembayaran_spp spp JOIN siswa s ON spp.siswa_id=s.id JOIN kelas k ON s.kelas_id=k.id WHERE 1=1 $whereYear $whereMonth")->fetchAll();
    $results = array_merge($results, $rows);
}
if ($filterJenis === 'semua' || $filterJenis === 'uang_pangkal') {
    $rows = $pdo->query("SELECT up.tanggal_bayar, s.nis, s.nama, k.nama_kelas, 'Uang Pangkal' AS jenis, '-' AS detail, up.nominal, up.metode_bayar FROM pembayaran_uang_pangkal up JOIN siswa s ON up.siswa_id=s.id JOIN kelas k ON s.kelas_id=k.id WHERE 1=1 $whereYear $whereMonth")->fetchAll();
    $results = array_merge($results, $rows);
}
if ($filterJenis === 'semua' || $filterJenis === 'lainnya') {
    $rows = $pdo->query("SELECT pl.tanggal_bayar, s.nis, s.nama, k.nama_kelas, jp.nama_pembayaran AS jenis, '-' AS detail, pl.nominal, pl.metode_bayar FROM pembayaran_lain pl JOIN siswa s ON pl.siswa_id=s.id JOIN kelas k ON s.kelas_id=k.id JOIN jenis_pembayaran jp ON pl.jenis_pembayaran_id=jp.id WHERE 1=1 $whereYear $whereMonth")->fetchAll();
    $results = array_merge($results, $rows);
}

usort($results, fn($a, $b) => strcmp($a['tanggal_bayar'], $b['tanggal_bayar']));
$totalNominal = array_sum(array_column($results, 'nominal'));

$namaSekolah = getSetting('nama_sekolah', SCHOOL_NAME);
$judulPeriode = 'Tahun ' . $filterTahun;
if ($filterBulan) $judulPeriode = namaBulan((int)$filterBulan) . ' ' . $filterTahun;

// Set header untuk download Excel
$filename = 'Laporan_Keuangan_' . str_replace(' ', '_', $judulPeriode) . '.xls';
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
<meta charset="UTF-8">
<!--[if gte mso 9]>
<xml>
<x:ExcelWorkbook>
<x:ExcelWorksheets>
<x:ExcelWorksheet>
<x:Name>Laporan Keuangan</x:Name>
<x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
</x:ExcelWorksheet>
</x:ExcelWorksheets>
</x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
    td, th { mso-number-format:\@; }
    .number { mso-number-format:"#,##0"; }
</style>
</head>
<body>

<table>
    <tr><td colspan="9" style="font-size:16pt;font-weight:bold"><?= htmlspecialchars($namaSekolah) ?></td></tr>
    <tr><td colspan="9" style="font-size:12pt;font-weight:bold">LAPORAN KEUANGAN</td></tr>
    <tr><td colspan="9">Periode: <?= $judulPeriode ?> | Jenis: <?= ucfirst($filterJenis) ?></td></tr>
    <tr><td colspan="9">Tanggal Cetak: <?= formatTanggal(date('Y-m-d')) ?></td></tr>
    <tr><td colspan="9"></td></tr>
</table>

<table border="1" cellpadding="4" cellspacing="0">
    <thead>
        <tr style="background:#4472C4;color:#fff;font-weight:bold;font-size:10pt">
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
    <?php foreach ($results as $i => $r): ?>
        <tr style="<?= $i % 2 === 0 ? '' : 'background:#D6E4F0' ?>">
            <td style="text-align:center"><?= $i+1 ?></td>
            <td><?= formatTanggal($r['tanggal_bayar']) ?></td>
            <td><?= htmlspecialchars($r['nis']) ?></td>
            <td><?= htmlspecialchars($r['nama']) ?></td>
            <td><?= htmlspecialchars($r['nama_kelas']) ?></td>
            <td><?= htmlspecialchars($r['jenis']) ?></td>
            <td><?= htmlspecialchars($r['detail']) ?></td>
            <td><?= ucfirst($r['metode_bayar']) ?></td>
            <td class="number" style="text-align:right"><?= (int)$r['nominal'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr style="background:#4472C4;color:#fff;font-weight:bold;font-size:10pt">
            <td colspan="8" style="text-align:right">TOTAL PEMASUKAN</td>
            <td class="number" style="text-align:right"><?= (int)$totalNominal ?></td>
        </tr>
        <tr style="background:#f0f0f0;font-weight:bold">
            <td colspan="8" style="text-align:right">JUMLAH TRANSAKSI</td>
            <td style="text-align:right"><?= count($results) ?></td>
        </tr>
    </tfoot>
</table>

</body>
</html>
