<?php
/**
 * PETTY CASH - Cetak / Export PDF
 * Semua teks header & tanda tangan diambil dari Pengaturan (database).
 */
session_start();

require_once __DIR__ . '/../../config/koneksi.php';

if (!isset($_SESSION['user_id'])) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

$pdo = getConnection();

$filterBulan = (int)($_GET['bulan'] ?? date('m'));
$filterTahun = (int)($_GET['tahun'] ?? date('Y'));

// Ambil pengaturan dari DB
$namaSekolah = getSetting('nama_sekolah', SCHOOL_NAME);
$alamatSekolah = getSetting('alamat_sekolah', '');
$teleponSekolah = getSetting('telepon_sekolah', '');
$kotaSekolah = getSetting('kota_sekolah', '');
$namaKepala = getSetting('nama_kepala_sekolah', '');
$pdfJudul = getSetting('pdf_judul', 'BUKU KAS UMUM (PETTY CASH)');
$pdfFooterText = getSetting('pdf_footer', '');
$namaBendahara = $_SESSION['nama_lengkap'] ?? 'Bendahara';

// Saldo Awal
$startDate = "$filterTahun-" . str_pad($filterBulan, 2, '0', STR_PAD_LEFT) . "-01";
$stmtSaldoAwal = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN jenis = 'masuk' THEN nominal ELSE 0 END), 0) -
        COALESCE(SUM(CASE WHEN jenis = 'keluar' THEN nominal ELSE 0 END), 0) AS saldo_awal
    FROM petty_cash 
    WHERE tanggal < :start_date
");
$stmtSaldoAwal->execute([':start_date' => $startDate]);
$saldoAwal = $stmtSaldoAwal->fetchColumn() ?: 0;

// Transaksi
$stmtTransaksi = $pdo->prepare("
    SELECT pc.*, u.nama_lengkap 
    FROM petty_cash pc
    JOIN users u ON pc.user_id = u.id
    WHERE MONTH(pc.tanggal) = :bulan AND YEAR(pc.tanggal) = :tahun
    ORDER BY pc.tanggal ASC, pc.id ASC
");
$stmtTransaksi->execute([':bulan' => $filterBulan, ':tahun' => $filterTahun]);
$transaksi = $stmtTransaksi->fetchAll();

// Total
$totalMasuk = 0;
$totalKeluar = 0;
foreach ($transaksi as $t) {
    if ($t['jenis'] === 'masuk') $totalMasuk += $t['nominal'];
    if ($t['jenis'] === 'keluar') $totalKeluar += $t['nominal'];
}
$saldoAkhir = $saldoAwal + $totalMasuk - $totalKeluar;

// Tanggal cetak
$tanggalCetak = $kotaSekolah ? "$kotaSekolah, " . date('d F Y') : date('d F Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pdfJudul) ?> - <?= namaBulan($filterBulan) ?> <?= $filterTahun ?></title>
    <style>
        body { font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.5; color: #000; padding: 20px; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        h2, h3, h4, p { margin: 0; padding: 0; }
        .header { border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 6px 10px; text-align: left; }
        th { background-color: #f2f2f2; }
        .table-secondary td { background-color: #f2f2f2; }
        .footer-ttd { width: 100%; margin-top: 50px; }
        .footer-ttd td { border: none; text-align: center; width: 33%; vertical-align: top; }
        .pdf-footer-note { text-align: center; font-size: 9pt; color: #666; margin-top: 30px; border-top: 1px dashed #ccc; padding-top: 8px; }
        
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 20px; display:flex; gap:10px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 14pt; cursor: pointer; border-radius: 8px; border: 1px solid #ccc;">🖨️ Cetak / Simpan ke PDF</button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 14pt; cursor: pointer; border-radius: 8px; border: 1px solid #ccc;">✕ Tutup</button>
    </div>

    <div class="header text-center">
        <h2><?= htmlspecialchars($namaSekolah) ?></h2>
        <?php if ($alamatSekolah): ?>
            <p><?= htmlspecialchars($alamatSekolah) ?><?= $teleponSekolah ? ' | Telp: ' . htmlspecialchars($teleponSekolah) : '' ?></p>
        <?php endif; ?>
        <h3 style="margin-top: 15px; text-decoration: underline;"><?= htmlspecialchars($pdfJudul) ?></h3>
        <p>Periode: <?= namaBulan($filterBulan) ?> <?= $filterTahun ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 15%;">Tanggal</th>
                <th style="width: 35%;">Uraian / Keterangan</th>
                <th style="width: 15%;" class="text-end">Penerimaan (Rp)</th>
                <th style="width: 15%;" class="text-end">Pengeluaran (Rp)</th>
                <th style="width: 15%;" class="text-end">Saldo (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <tr class="table-secondary fw-bold">
                <td colspan="3" class="text-end">SALDO AWAL</td>
                <td class="text-end"></td>
                <td class="text-end"></td>
                <td class="text-end"><?= formatRupiah($saldoAwal) ?></td>
            </tr>
            
            <?php 
            $saldoBerjalan = $saldoAwal;
            $no = 1;
            foreach ($transaksi as $t): 
                if ($t['jenis'] === 'masuk') {
                    $masuk = $t['nominal'];
                    $keluar = 0;
                    $saldoBerjalan += $masuk;
                } else {
                    $masuk = 0;
                    $keluar = $t['nominal'];
                    $saldoBerjalan -= $keluar;
                }
            ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td><?= date('d-m-Y', strtotime($t['tanggal'])) ?></td>
                    <td><?= htmlspecialchars($t['keterangan']) ?></td>
                    <td class="text-end"><?= $masuk > 0 ? formatRupiah($masuk) : '-' ?></td>
                    <td class="text-end"><?= $keluar > 0 ? formatRupiah($keluar) : '-' ?></td>
                    <td class="text-end"><?= formatRupiah($saldoBerjalan) ?></td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($transaksi)): ?>
                <tr><td colspan="6" class="text-center">Tidak ada transaksi pada periode ini.</td></tr>
            <?php endif; ?>

            <tr class="table-secondary fw-bold">
                <td colspan="3" class="text-end">TOTAL MUTASI</td>
                <td class="text-end"><?= formatRupiah($totalMasuk) ?></td>
                <td class="text-end"><?= formatRupiah($totalKeluar) ?></td>
                <td></td>
            </tr>
            <tr class="fw-bold" style="background-color: #ddd;">
                <td colspan="5" class="text-end">SALDO AKHIR</td>
                <td class="text-end"><?= formatRupiah($saldoAkhir) ?></td>
            </tr>
        </tbody>
    </table>

    <table class="footer-ttd">
        <tr>
            <td>
                <p>Mengetahui,</p>
                <p style="margin-bottom: 70px;">Kepala Sekolah</p>
                <p class="fw-bold" style="text-decoration: underline;"><?= $namaKepala ? htmlspecialchars($namaKepala) : '( ......................................... )' ?></p>
            </td>
            <td></td>
            <td>
                <p><?= htmlspecialchars($tanggalCetak) ?></p>
                <p style="margin-bottom: 70px;">Bendahara Sekolah</p>
                <p class="fw-bold" style="text-decoration: underline;"><?= htmlspecialchars($namaBendahara) ?></p>
            </td>
        </tr>
    </table>

    <?php if ($pdfFooterText): ?>
        <div class="pdf-footer-note"><?= htmlspecialchars($pdfFooterText) ?></div>
    <?php endif; ?>

</body>
</html>
