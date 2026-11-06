<?php
/**
 * PETTY CASH - Input Transaksi Baru
 */
$pageTitle = 'Input Petty Cash';
$activePage = 'petty-cash';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('petty_cash');

$pdo = getConnection();

// Auto check columns
try {
    $checkCols = $pdo->query("SHOW COLUMNS FROM petty_cash LIKE 'qty'")->fetch();
    if (!$checkCols) {
        $pdo->exec("ALTER TABLE petty_cash ADD COLUMN no_transaksi VARCHAR(50) NULL AFTER id, ADD COLUMN qty DECIMAL(10,2) NOT NULL DEFAULT 1.00 AFTER jenis, ADD COLUMN satuan VARCHAR(30) NOT NULL DEFAULT 'Pcs' AFTER qty, ADD COLUMN harga_satuan DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER satuan");
    }
} catch (Exception $e) {
}

// Auto Sequence No. Bukti
$currentYM = date('Ym');
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM petty_cash WHERE DATE_FORMAT(tanggal, '%Y%m') = :ym");
$stmtCount->execute([':ym' => $currentYM]);
$nextSeq = ((int) $stmtCount->fetchColumn()) + 1;
$defaultNo = 'BKK-' . $currentYM . '-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jenis = $_POST['jenis'] ?? '';
    $noTransaksi = trim($_POST['no_transaksi'] ?? '');
    $nominal = (float) str_replace(['.', ','], ['', '.'], $_POST['nominal'] ?? '0');
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $ket = trim($_POST['keterangan'] ?? '');
    $qty = (float) str_replace(',', '.', $_POST['qty'] ?? '1');
    $satuan = trim($_POST['satuan'] ?? 'Pcs');
    $hargaSatuan = (float) str_replace(['.', ','], ['', '.'], $_POST['harga_satuan'] ?? '0');

    // Jika nominal 0 tapi qty & harga satuan terisi
    if ($nominal <= 0 && $qty > 0 && $hargaSatuan > 0) {
        $nominal = $qty * $hargaSatuan;
    }
    // Jika harga satuan 0 tapi nominal & qty terisi
    if ($hargaSatuan <= 0 && $nominal > 0 && $qty > 0) {
        $hargaSatuan = $nominal / $qty;
    }

    if (empty($noTransaksi)) {
        $prefix = ($jenis === 'masuk') ? 'BKM' : 'BKK';
        $ym = date('Ym', strtotime($tanggal));
        $noTransaksi = $prefix . '-' . $ym . '-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
    }

    if (in_array($jenis, ['masuk', 'keluar']) && $nominal > 0 && $tanggal && $ket) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO petty_cash 
                (no_transaksi, tanggal, jenis, qty, satuan, harga_satuan, nominal, keterangan, user_id) 
                VALUES 
                (:no, :tgl, :jns, :qty, :satuan, :hrg, :nom, :ket, :uid)
            ");
            $stmt->execute([
                ':no' => $noTransaksi,
                ':tgl' => $tanggal,
                ':jns' => $jenis,
                ':qty' => $qty > 0 ? $qty : 1,
                ':satuan' => $satuan ?: 'Pcs',
                ':hrg' => $hargaSatuan,
                ':nom' => $nominal,
                ':ket' => $ket,
                ':uid' => $_SESSION['user_id']
            ]);
            redirect('index.php', 'success', 'Transaksi berhasil disimpan.');
        } catch (PDOException $e) {
            $error = 'Gagal menyimpan transaksi: ' . $e->getMessage();
        }
    } else {
        $error = 'Semua field wajib diisi dengan benar.';
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="form-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0"><i class="bi bi-wallet2"></i> Input Transaksi Buku Kas</h5>
                <a href="index.php" class="btn btn-sm btn-light">Kembali</a>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jenis Transaksi <span class="text-danger">*</span></label>
                        <select name="jenis" id="selectJenis" class="form-select" required onchange="autoUpdateNoTrx()">
                            <option value="keluar">Uang Keluar (-)</option>
                            <option value="masuk">Uang Masuk (+)</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">No. Bukti / Transaksi</label>
                        <input type="text" name="no_transaksi" id="inputNoTrx" class="form-control"
                            value="<?= $defaultNo ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal" id="inputTanggal" class="form-control"
                            value="<?= date('Y-m-d') ?>" required onchange="autoUpdateNoTrx()">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nominal Total (Rp) <span class="text-danger">*</span></label>
                        <input type="text" name="nominal" id="inputNominal" class="form-control" required
                            placeholder="0" oninput="formatRupiah(this)">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Jumlah (QTY)</label>
                        <input type="number" step="any" name="qty" id="inputQty" class="form-control" value="1"
                            min="0.01" oninput="calcSubtotal()">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Satuan</label>
                        <input list="satuanOptions" name="satuan" id="inputSatuan" class="form-control" value="Pcs"
                            placeholder="Pcs, Rim, dll">
                        <datalist id="satuanOptions">
                            <option value="Pcs">
                            <option value="Rim">
                            <option value="Box">
                            <option value="Paket">
                            <option value="Unit">
                            <option value="Lembar">
                            <option value="Lusin">
                            <option value="Kg">
                            <option value="Liter">
                            <option value="Orang">
                            <option value="Bulan">
                            <option value="Hari">
                            <option value="Set">
                            <option value="Kegiatan">
                        </datalist>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Harga Satuan (Rp)</label>
                        <input type="text" name="harga_satuan" id="inputHargaSatuan" class="form-control"
                            placeholder="Opsional" oninput="formatRupiah(this); calcSubtotal();">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Keterangan / Uraian <span class="text-danger">*</span></label>
                    <textarea name="keterangan" class="form-control" rows="3"
                        placeholder="Contoh: Beli ATK Kantor, Kertas HVS, Tinta Printer..." required></textarea>
                </div>

                <button type="submit" class="btn-primary-custom w-100 py-2"><i class="bi bi-save"></i> Simpan
                    Transaksi</button>
            </form>
        </div>
    </div>
</div>

<script>
    const seqNum = <?= $nextSeq ?>;

    function autoUpdateNoTrx() {
        const jenis = document.getElementById('selectJenis').value;
        const tgl = document.getElementById('inputTanggal').value || '<?= date('Y-m-d') ?>';
        const ym = tgl.replace(/-/g, '').substring(0, 6);
        const prefix = (jenis === 'masuk') ? 'BKM' : 'BKK';
        const seqStr = String(seqNum).padStart(4, '0');
        document.getElementById('inputNoTrx').value = `${prefix}-${ym}-${seqStr}`;
    }

    function formatRupiah(input) {
        let value = input.value.replace(/[^0-9]/g, '');
        if (value) {
            input.value = new Intl.NumberFormat('id-ID').format(value);
        } else {
            input.value = '';
        }
    }

    function calcSubtotal() {
        const qty = parseFloat(document.getElementById('inputQty').value) || 0;
        const rawHrg = document.getElementById('inputHargaSatuan').value.replace(/[^0-9]/g, '');
        const harga = parseFloat(rawHrg) || 0;

        if (qty > 0 && harga > 0) {
            const total = qty * harga;
            document.getElementById('inputNominal').value = new Intl.NumberFormat('id-ID').format(total);
        }
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>