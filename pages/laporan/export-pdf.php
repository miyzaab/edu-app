<?php
/**
 * EXPORT PDF - Laporan Keuangan (print-friendly page)
 * Menggunakan window.print() — browser akan menyediakan opsi "Save as PDF"
 */
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

$filterBulan = $_GET['bulan'] ?? '';
$filterTahun = $_GET['tahun'] ?? date('Y');
$filterJenis = $_GET['jenis'] ?? 'semua';

// Ambil data (sama seperti di laporan index)
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
$alamat      = getSetting('alamat_sekolah', '');
$logoPath    = getSetting('logo_path', '');

$judulPeriode = 'Tahun ' . $filterTahun;
if ($filterBulan) $judulPeriode = namaBulan((int)$filterBulan) . ' ' . $filterTahun;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Keuangan - <?= $judulPeriode ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; font-size: 11px; color: #222; padding: 20px; }
        
        .header { text-align: center; border-bottom: 3px double #333; padding-bottom: 12px; margin-bottom: 16px; display: flex; align-items: center; gap: 16px; justify-content: center; }
        .header img { width: 60px; height: 60px; object-fit: contain; }
        .header .info { text-align: left; }
        .header h1 { font-size: 16px; font-weight: 800; margin-bottom: 2px; }
        .header p { font-size: 10px; color: #555; }
        
        .title { text-align: center; margin-bottom: 16px; }
        .title h2 { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; }
        .title p { font-size: 10px; color: #666; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th { background: #f0f0f0; font-size: 9px; text-transform: uppercase; letter-spacing: .05em; font-weight: 700; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        td { font-size: 10px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        tfoot td { font-weight: 700; background: #f8f8f8; font-size: 11px; }
        tbody tr:nth-child(even) { background: #fafafa; }
        
        .footer { display: flex; justify-content: space-between; margin-top: 40px; font-size: 10px; }
        .footer .sign { text-align: center; width: 200px; }
        .footer .sign .line { border-top: 1px solid #333; margin-top: 60px; padding-top: 4px; }

        .no-print { margin-bottom: 16px; }
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
            @page { margin: 15mm; size: A4 landscape; }
        }
    </style>
</head>
<body>

<div class="no-print" style="display:flex;gap:8px">
    <button onclick="window.print()" style="padding:8px 20px;background:#667eea;color:#fff;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600">🖨️ Cetak / Simpan PDF</button>
    <button onclick="window.close()" style="padding:8px 20px;background:#eee;border:1px solid #ccc;border-radius:6px;font-size:12px;cursor:pointer">Tutup</button>
</div>

<!-- Kop Surat -->
<div class="header">
    <?php if ($logoPath): ?>
        <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo">
    <?php endif; ?>
    <div class="info">
        <h1><?= htmlspecialchars($namaSekolah) ?></h1>
        <?php if ($alamat): ?><p><?= htmlspecialchars($alamat) ?></p><?php endif; ?>
    </div>
</div>

<div class="title">
    <h2>Laporan Keuangan</h2>
    <p>Periode: <?= $judulPeriode ?> | Jenis: <?= ucfirst($filterJenis) ?> | Dicetak: <?= formatTanggal(date('Y-m-d')) ?></p>
</div>

<table>
    <thead>
        <tr><th class="text-center">No</th><th>Tanggal</th><th>NIS</th><th>Nama Siswa</th><th>Kelas</th><th>Jenis</th><th>Detail</th><th>Metode</th><th class="text-right">Nominal</th></tr>
    </thead>
    <tbody>
    <?php foreach ($results as $i => $r): ?>
        <tr>
            <td class="text-center"><?= $i+1 ?></td>
            <td><?= formatTanggal($r['tanggal_bayar']) ?></td>
            <td><?= htmlspecialchars($r['nis']) ?></td>
            <td><?= htmlspecialchars($r['nama']) ?></td>
            <td><?= htmlspecialchars($r['nama_kelas']) ?></td>
            <td><?= htmlspecialchars($r['jenis']) ?></td>
            <td><?= htmlspecialchars($r['detail']) ?></td>
            <td><?= ucfirst($r['metode_bayar']) ?></td>
            <td class="text-right"><?= formatRupiah($r['nominal']) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($results)): ?>
        <tr><td colspan="9" class="text-center">Tidak ada data.</td></tr>
    <?php endif; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="8" class="text-right">TOTAL PEMASUKAN</td>
            <td class="text-right"><?= formatRupiah($totalNominal) ?></td>
        </tr>
        <tr>
            <td colspan="8" class="text-right">JUMLAH TRANSAKSI</td>
            <td class="text-right"><?= count($results) ?> transaksi</td>
        </tr>
    </tfoot>
</table>

<div class="footer">
    <div style="font-size:9px;color:#999">Dicetak dari <?= APP_NAME ?> v<?= APP_VERSION ?></div>
    <div class="sign">
        <p>Mengetahui,</p>
        <p>Bendahara</p>
        <div class="line"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? '') ?></div>
    </div>
</div>

</body>
</html>
