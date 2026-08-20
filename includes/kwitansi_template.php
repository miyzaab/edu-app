<?php
/**
 * TEMPLATE KWITANSI - Shared layout untuk semua kwitansi
 * 
 * File ini di-include oleh semua halaman kwitansi.
 * Variabel yang harus di-set sebelum include:
 * - $kwitansiNo   : Nomor kwitansi (misal: SPP-000001)
 * - $kwitansiJudul: Jenis kwitansi (misal: KWITANSI PEMBAYARAN SPP)
 * - $data         : Array data transaksi (tanggal_bayar, nis, nama, nama_kelas, dll)
 * - $rows         : Array of ['label' => ..., 'value' => ...] untuk detail pembayaran
 * - $nominal      : Total nominal
 * - $backUrl      : URL tombol kembali
 */

$namaSekolah    = getSetting('nama_sekolah', SCHOOL_NAME);
$alamatSekolah  = getSetting('alamat_sekolah', '');
$teleponSekolah = getSetting('telepon_sekolah', '');
$logoPath       = getSetting('logo_path', '');
$kwitansiFooter = getSetting('kwitansi_footer', 'Terima kasih atas pembayarannya');
$waTemplate     = getSetting('wa_share_template', "Halo Bapak/Ibu Wali Murid {nama},\n\nBerikut adalah rincian pembayaran Anda di {sekolah}:\n\n*{judul}*\nNo: {no}\nTotal: *{nominal}*\nStatus: *LUNAS*\n\nLink Kwitansi Digital: {link}\n\nTerima kasih.");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($kwitansiJudul) ?> #<?= htmlspecialchars($kwitansiNo) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f0f0; padding: 2rem; font-size: .85rem; color: #333; }
        .receipt-container { max-width: 420px; margin: 0 auto; }
        .actions { display: flex; gap: 8px; margin-bottom: 1rem; }
        .btn { padding: 8px 18px; border-radius: 8px; font-size: .8rem; font-weight: 600; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; border: none; }
        .btn-print { background: linear-gradient(135deg, #007bff, #00c6ff); color: #fff; }
        .btn-print:hover { box-shadow: 0 4px 15px rgba(0,123,255,.4); }
        .btn-back { background: #eee; color: #333; border: 1px solid #ccc; }

        .receipt {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
        }

        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed #ccc;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }
        .receipt-logo { margin-bottom: .5rem; }
        .receipt-logo img { width: 50px; height: 50px; object-fit: contain; }
        .receipt-header h4 { font-size: .95rem; font-weight: 800; margin-bottom: 2px; }
        .receipt-header .addr { font-size: .7rem; color: #666; margin: 1px 0; }
        .receipt-header .title { font-size: .8rem; font-weight: 700; letter-spacing: .06em; margin-top: .75rem; padding-top: .5rem; border-top: 1px solid #eee; }
        .receipt-header .no { font-size: .7rem; color: #999; }

        .receipt-body { padding: .5rem 0; }
        .receipt-row { display: flex; justify-content: space-between; padding: .35rem 0; border-bottom: 1px dotted #eee; }
        .receipt-row:last-child { border-bottom: none; }
        .receipt-row span { color: #666; font-size: .8rem; }
        .receipt-row strong { font-size: .8rem; text-align: right; }

        .receipt-total {
            border-top: 2px dashed #ccc;
            margin-top: .75rem;
            padding-top: .75rem;
            display: flex;
            justify-content: space-between;
            font-size: 1rem;
            font-weight: 800;
        }
        .receipt-total .amount { color: #198754; }

        .receipt-footer {
            margin-top: 1.5rem;
            text-align: center;
            font-size: .72rem;
            color: #999;
        }
        .receipt-footer .sign { margin-top: .5rem; }

        @media print {
            body { background: #fff; padding: .5rem; }
            .actions { display: none !important; }
            .receipt { box-shadow: none; border: none; }
        }
    </style>
</head>
<body>

<div class="receipt-container">
    <div class="actions">
        <button onclick="window.print()" class="btn btn-print"><i class="bi bi-printer"></i> Cetak</button>
        <button onclick="saveAsImage()" class="btn btn-save" style="background:#6c757d;color:#fff;"><i class="bi bi-image"></i> Simpan Gambar</button>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <button onclick="shareKwitansi()" class="btn btn-share" style="background:#25D366;color:#fff;"><i class="bi bi-whatsapp"></i> Share WA</button>
            <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-back"><i class="bi bi-arrow-left"></i> Kembali</a>
        <?php endif; ?>
    </div>

    <div class="receipt" id="receiptArea">
        <!-- Header dengan Logo -->
        <div class="receipt-header">
            <div class="receipt-logo">
                <?= getLogoHtml(50) ?>
            </div>
            <h4><?= htmlspecialchars($namaSekolah) ?></h4>
            <?php if ($alamatSekolah): ?><p class="addr"><?= htmlspecialchars($alamatSekolah) ?></p><?php endif; ?>
            <?php if ($teleponSekolah): ?><p class="addr">Telp: <?= htmlspecialchars($teleponSekolah) ?></p><?php endif; ?>
            <p class="title"><?= htmlspecialchars($kwitansiJudul) ?></p>
            <p class="no">No: <?= htmlspecialchars($kwitansiNo) ?></p>
        </div>

        <!-- Detail Pembayaran -->
        <div class="receipt-body">
            <?php foreach ($rows as $row): ?>
                <div class="receipt-row">
                    <span><?= htmlspecialchars($row['label']) ?></span>
                    <strong><?= $row['value'] ?></strong>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Total -->
        <div class="receipt-total">
            <span>TOTAL</span>
            <span class="amount"><?= formatRupiah($nominal) ?></span>
        </div>

        <!-- Footer -->
        <div class="receipt-footer">
            <p>Diterima oleh: <strong><?= htmlspecialchars($data['bendahara'] ?? $_SESSION['nama_lengkap'] ?? '') ?></strong></p>
            <p class="sign">--- <?= htmlspecialchars($kwitansiFooter) ?> ---</p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
function saveAsImage() {
    const area = document.getElementById('receiptArea');
    const btnSave = document.querySelector('.btn-save');
    const originalText = btnSave.innerHTML;
    btnSave.innerHTML = '<i class="bi bi-hourglass-split"></i> Memproses...';
    btnSave.disabled = true;

    html2canvas(area, {
        scale: 2,
        backgroundColor: '#ffffff',
        logging: false,
        useCORS: true
    }).then(canvas => {
        const link = document.createElement('a');
        link.download = 'Kwitansi_<?= $kwitansiNo ?>.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
        btnSave.innerHTML = originalText;
        btnSave.disabled = false;
    }).catch(err => {
        alert('Gagal menyimpan gambar. Pastikan browser mendukung.');
        btnSave.innerHTML = originalText;
        btnSave.disabled = false;
    });
}

function shareKwitansi() {
    const no = '<?= $kwitansiNo ?>';
    const nominal = '<?= formatRupiah($nominal) ?>';
    const nama = '<?= htmlspecialchars($data['nama'] ?? '') ?>';
    const judul = '<?= htmlspecialchars($kwitansiJudul) ?>';
    const school = '<?= htmlspecialchars($namaSekolah) ?>';
    const link = window.location.href;
    
    let template = `<?= str_replace(["\r", "\n"], ["", "\\n"], addslashes($waTemplate)) ?>`;
    
    // Replace placeholders
    let message = template
        .replace(/{nama}/g, nama)
        .replace(/{sekolah}/g, school)
        .replace(/{judul}/g, judul)
        .replace(/{no}/g, no)
        .replace(/{nominal}/g, nominal)
        .replace(/{link}/g, link);
    
    window.open('https://wa.me/?text=' + encodeURIComponent(message), '_blank');
}
</script>

</body>
</html>
