<?php
/**
 * REKAP UANG PANGKAL KELAS - Laporan Rekap Kelunasan Uang Pangkal
 */
$pageTitle  = 'Rekap Uang Pangkal';
$activePage = 'rekap-uang-pangkal';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('rekap_uang_pangkal');

$pdo = getConnection();

// Filter
$kelasId = (int)($_GET['kelas_id'] ?? 0);

// Ambil list kelas untuk dropdown
$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();

if ($kelasId === 0 && !empty($kelasList)) {
    $kelasId = (int)$kelasList[0]['id'];
}

$siswaData = [];
$upData    = [];

if ($kelasId > 0) {
    // Ambil data siswa di kelas tersebut (Aktif)
    try {
        $stmtSiswa = $pdo->prepare("SELECT id, nis, nama, COALESCE(target_uang_pangkal, 0) as target_uang_pangkal FROM siswa WHERE kelas_id = :k_id AND status = 'aktif' ORDER BY nama");
        $stmtSiswa->execute([':k_id' => $kelasId]);
        $siswaData = $stmtSiswa->fetchAll();
    } catch (PDOException $e) {
        $siswaData = [];
    }

    if (!empty($siswaData)) {
        $siswaIds = array_column($siswaData, 'id');
        $inQuery = implode(',', array_fill(0, count($siswaIds), '?'));
        
        $sqlUP = "SELECT siswa_id, SUM(nominal) AS total_dibayar, MAX(tanggal_bayar) AS last_bayar FROM pembayaran_uang_pangkal WHERE siswa_id IN ($inQuery) GROUP BY siswa_id";
        $stmtUP = $pdo->prepare($sqlUP);
        $stmtUP->execute($siswaIds);
        
        while ($row = $stmtUP->fetch()) {
            $upData[$row['siswa_id']] = [
                'total_dibayar' => (float)$row['total_dibayar'],
                'last_bayar'    => $row['last_bayar']
            ];
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="form-card mb-4">
    <h5 class="mb-3"><i class="bi bi-wallet2"></i> Rekap Uang Pangkal Kelas</h5>
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label">Pilih Kelas</label>
            <select name="kelas_id" class="form-select form-select-sm" required>
                <option value="">-- Pilih Kelas --</option>
                <?php foreach ($kelasList as $k): ?>
                    <option value="<?= $k['id'] ?>" <?= $kelasId == $k['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($k['nama_kelas']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-sm btn-primary-custom w-100"><i class="bi bi-search"></i> Tampilkan Rekap</button>
        </div>
        <?php if ($kelasId > 0): ?>
        <div class="col-md-4 d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="window.print()"><i class="bi bi-file-earmark-pdf"></i> Cetak PDF</button>
            <button type="button" class="btn btn-sm btn-outline-success w-100" onclick="exportRekapExcel()"><i class="bi bi-file-earmark-excel"></i> Excel</button>
        </div>
        <?php endif; ?>
    </form>
</div>

<?php if ($kelasId > 0): ?>
    <?php
    $sumTarget = 0;
    $sumDibayar = 0;
    $sumSisa = 0;
    ?>
    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="table-light text-center">
                    <tr>
                        <th style="width: 40px;">No</th>
                        <th style="width: 100px;">NIS</th>
                        <th class="text-start">Nama Siswa</th>
                        <th style="width: 130px;">Target (Rp)</th>
                        <th style="width: 130px;">Sudah Dibayar (Rp)</th>
                        <th style="width: 130px;">Sisa Tanggungan (Rp)</th>
                        <th style="width: 110px;">Status</th>
                        <th style="width: 120px;">Tgl Bayar Terakhir</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($siswaData)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada siswa aktif di kelas ini.</td></tr>
                <?php else: ?>
                    <?php foreach ($siswaData as $i => $s): ?>
                        <?php
                        $target = (float)($s['target_uang_pangkal'] ?? 0);
                        $dibayar = (float)($upData[$s['id']]['total_dibayar'] ?? 0);
                        $sisa = max(0, $target - $dibayar);
                        $lastBayar = $upData[$s['id']]['last_bayar'] ?? null;

                        $sumTarget += $target;
                        $sumDibayar += $dibayar;
                        $sumSisa += $sisa;

                        $isLunas = ($target > 0 && $dibayar >= $target);
                        $percent = ($target > 0) ? min(100, round(($dibayar / $target) * 100)) : 0;
                        ?>
                        <tr>
                            <td class="text-center"><?= $i+1 ?></td>
                            <td class="text-center"><code><?= htmlspecialchars($s['nis']) ?></code></td>
                            <td class="fw-medium"><?= htmlspecialchars($s['nama']) ?></td>
                            <td class="text-end"><?= number_format($target, 0, ',', '.') ?></td>
                            <td class="text-end text-success fw-bold"><?= number_format($dibayar, 0, ',', '.') ?></td>
                            <td class="text-end text-danger fw-bold"><?= number_format($sisa, 0, ',', '.') ?></td>
                            <td class="text-center">
                                <?php if ($isLunas): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i> LUNAS</span>
                                <?php elseif ($dibayar > 0): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1"><i class="bi bi-clock-history me-1"></i> <?= $percent ?>% (Dicicil)</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><i class="bi bi-x-circle me-1"></i> Belum</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center small text-muted"><?= $lastBayar ? formatTanggal($lastBayar) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="3" class="text-end">TOTAL:</td>
                        <td class="text-end"><?= number_format($sumTarget, 0, ',', '.') ?></td>
                        <td class="text-end text-success"><?= number_format($sumDibayar, 0, ',', '.') ?></td>
                        <td class="text-end text-danger"><?= number_format($sumSisa, 0, ',', '.') ?></td>
                        <td colspan="2" class="text-center">
                            <?php $totalPercent = ($sumTarget > 0) ? round(($sumDibayar / $sumTarget) * 100, 1) : 0; ?>
                            Capaian: <?= $totalPercent ?>%
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-wallet2" style="font-size: 3rem;"></i>
        <p class="mt-2">Silakan pilih kelas di atas untuk melihat rekap Uang Pangkal.</p>
    </div>
<?php endif; ?>

<script>
function exportRekapExcel() {
    const table = document.querySelector('.table-card table');
    if (!table) return;

    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        let rowData = [];
        const cols = row.querySelectorAll('th, td');
        
        cols.forEach(col => {
            let text = col.innerText.trim();
            text = text.replace(/\n/g, ' ').replace(/"/g, '""');
            rowData.push('"' + text + '"');
        });
        csv.push(rowData.join(','));
    });

    const filename = 'Rekap_Uang_Pangkal_<?= htmlspecialchars($kelasList[array_search($kelasId, array_column($kelasList, "id"))]["nama_kelas"] ?? "Kelas") ?>.csv';
    const blob = new Blob(['\ufeff' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
}
</script>

<style>
@media print {
    .sidebar, .top-header, .form-card, .bottom-nav, .btn-sm-action { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; width: 100% !important; }
    .content-wrapper { padding: 0 !important; }
    .table-card { border: none !important; box-shadow: none !important; }
    .table { font-size: 11px !important; }
    body { background: white !important; }
    .print-only { display: block !important; margin-bottom: 20px; text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; }
}
.print-only { display: none; }
</style>

<div class="print-only">
    <h3 style="margin:0">REKAP PEMBAYARAN UANG PANGKAL</h3>
    <p style="margin:5px 0">
        Kelas: <strong><?= htmlspecialchars($kelasList[array_search($kelasId, array_column($kelasList, "id"))]["nama_kelas"] ?? "-") ?></strong>
    </p>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
