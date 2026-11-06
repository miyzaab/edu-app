<?php
/**
 * REKAP DAFTAR ULANG KELAS - Laporan Rekap Kelunasan Daftar Ulang (Otomatis Tampil Semua Kelas)
 */
$pageTitle = 'Rekap Daftar Ulang';
$activePage = 'rekap-daftar-ulang';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('rekap_daftar_ulang');

$pdo = getConnection();

// Filter
$kelasId = (int) ($_GET['kelas_id'] ?? 0);
$tahun = (int) ($_GET['tahun'] ?? date('Y'));
$jenisId = (int) ($_GET['jenis_id'] ?? 0);

// Ambil list jenis pembayaran yang bertipe Daftar Ulang (atau semua jenis aktif untuk fallback)
$jenisDaftarUlang = $pdo->query("SELECT * FROM jenis_pembayaran WHERE status='aktif' AND (nama_pembayaran LIKE '%Daftar Ulang%' OR nama_pembayaran LIKE '%Re-registrasi%') ORDER BY nama_pembayaran")->fetchAll();
if (empty($jenisDaftarUlang)) {
    $jenisDaftarUlang = $pdo->query("SELECT * FROM jenis_pembayaran WHERE status='aktif' ORDER BY nama_pembayaran")->fetchAll();
}

if ($jenisId === 0 && !empty($jenisDaftarUlang)) {
    $jenisId = (int) $jenisDaftarUlang[0]['id'];
}

// Ambil info jenis pembayaran terpilih
$selectedJenis = null;
if ($jenisId > 0) {
    $stmtJ = $pdo->prepare("SELECT * FROM jenis_pembayaran WHERE id = :id LIMIT 1");
    $stmtJ->execute([':id' => $jenisId]);
    $selectedJenis = $stmtJ->fetch();
}
$nominalTargetDefault = $selectedJenis ? (float) $selectedJenis['nominal_default'] : 0;

// Ambil list kelas untuk dropdown
$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();

$siswaData = [];
$duData = [];

if ($jenisId > 0) {
    if ($kelasId > 0) {
        $stmtSiswa = $pdo->prepare("
            SELECT s.id, s.nis, s.nama, s.kelas_id, k.nama_kelas 
            FROM siswa s 
            LEFT JOIN kelas k ON s.kelas_id = k.id 
            WHERE s.kelas_id = :k_id AND s.status = 'aktif' 
            ORDER BY s.nama
        ");
        $stmtSiswa->execute([':k_id' => $kelasId]);
    } else {
        $stmtSiswa = $pdo->prepare("
            SELECT s.id, s.nis, s.nama, s.kelas_id, k.nama_kelas 
            FROM siswa s 
            LEFT JOIN kelas k ON s.kelas_id = k.id 
            WHERE s.status = 'aktif' 
            ORDER BY k.tingkat ASC, k.nama_kelas ASC, s.nama ASC
        ");
        $stmtSiswa->execute();
    }
    $siswaData = $stmtSiswa->fetchAll();

    if (!empty($siswaData)) {
        $siswaIds = array_column($siswaData, 'id');
        $inQuery = implode(',', array_fill(0, count($siswaIds), '?'));

        $sqlDU = "SELECT siswa_id, SUM(nominal) AS total_dibayar, MAX(tanggal_bayar) AS last_bayar 
                  FROM pembayaran_lain 
                  WHERE jenis_pembayaran_id = ? AND YEAR(tanggal_bayar) = ? AND siswa_id IN ($inQuery) 
                  GROUP BY siswa_id";
        $stmtDU = $pdo->prepare($sqlDU);

        $params = array_merge([$jenisId, $tahun], $siswaIds);
        $stmtDU->execute($params);

        while ($row = $stmtDU->fetch()) {
            $duData[$row['siswa_id']] = [
                'total_dibayar' => (float) $row['total_dibayar'],
                'last_bayar' => $row['last_bayar']
            ];
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- 1. SUB-TAB PILL NAVIGATION (SPP, Daftar Ulang, Uang Pangkal, Rekap Tunggakan) -->
<div class="rekap-tabs-bar mb-4 overflow-auto pb-1">
    <div class="rekap-tabs-pill-container">
        <a href="<?= BASE_URL ?>/pages/riwayat/rekap-spp.php"
            class="rekap-tab-pill <?= $activePage === 'rekap-spp' ? 'active' : '' ?>">
            <i class="bi bi-grid-3x3-gap-fill"></i> Rekap SPP Bulanan
        </a>
        <a href="<?= BASE_URL ?>/pages/riwayat/rekap-daftar-ulang.php"
            class="rekap-tab-pill <?= $activePage === 'rekap-daftar-ulang' ? 'active' : '' ?>">
            <i class="bi bi-clipboard2-check-fill"></i> Rekap Daftar Ulang
        </a>
        <a href="<?= BASE_URL ?>/pages/riwayat/rekap-uang-pangkal.php"
            class="rekap-tab-pill <?= $activePage === 'rekap-uang-pangkal' ? 'active' : '' ?>">
            <i class="bi bi-wallet2"></i> Rekap Uang Pangkal
        </a>
        <?php if (hasPermission('tunggakan')): ?>
            <a href="<?= BASE_URL ?>/pages/tunggakan/index.php"
                class="rekap-tab-pill <?= $activePage === 'tunggakan' ? 'active' : '' ?>">
                <i class="bi bi-exclamation-triangle-fill text-warning"></i> Rekap Tunggakan
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- 2. FORM FILTER KATEGORI, KELAS & TAHUN -->
<div class="form-card mb-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-check me-2 text-primary"></i> Rekap Pembayaran Daftar Ulang
            (Tahun <?= $tahun ?>)</h5>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="window.print()" title="Cetak PDF"><i
                    class="bi bi-file-earmark-pdf"></i> PDF</button>
            <button type="button" class="btn btn-sm btn-outline-success" onclick="exportRekapExcel()"
                title="Ekspor Excel"><i class="bi bi-file-earmark-excel"></i> Excel</button>
        </div>
    </div>
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small fw-bold">Kategori Pembayaran</label>
            <select name="jenis_id" class="form-select form-select-sm" required>
                <?php foreach ($jenisDaftarUlang as $j): ?>
                    <option value="<?= $j['id'] ?>" <?= $jenisId == $j['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($j['nama_pembayaran']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-bold">Filter Kelas</label>
            <select name="kelas_id" class="form-select form-select-sm">
                <option value="0" <?= $kelasId == 0 ? 'selected' : '' ?>>🌟 Semua Kelas (Tampilkan Semua Siswa)</option>
                <?php foreach ($kelasList as $k): ?>
                    <option value="<?= $k['id'] ?>" <?= $kelasId == $k['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($k['nama_kelas']) ?> (Tingkat <?= htmlspecialchars($k['tingkat'] ?? '-') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-bold">Tahun</label>
            <input type="number" name="tahun" class="form-control form-control-sm" value="<?= $tahun ?>" min="2020"
                max="2099" required>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-sm btn-primary-custom w-100"><i class="bi bi-search me-1"></i>
                Tampilkan</button>
        </div>
    </form>
</div>

<!-- 3. TABEL DATA DAFTAR ULANG -->
<?php
$sumTarget = 0;
$sumDibayar = 0;
$sumSisa = 0;
?>
<div class="table-card border-0 shadow-sm overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0" style="font-size: 0.85rem;">
            <thead class="table-light text-center">
                <tr>
                    <th style="width: 40px;">No</th>
                    <th style="width: 100px;">NIS</th>
                    <th class="text-start">Nama Siswa</th>
                    <th style="width: 100px;">Kelas</th>
                    <th style="width: 130px;">Biaya (Rp)</th>
                    <th style="width: 130px;">Sudah Dibayar (Rp)</th>
                    <th style="width: 130px;">Sisa Tanggungan (Rp)</th>
                    <th style="width: 110px;">Status</th>
                    <th style="width: 120px;">Tgl Bayar Terakhir</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($siswaData)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Belum ada siswa aktif.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($siswaData as $i => $s): ?>
                        <?php
                        $target = $nominalTargetDefault;
                        $dibayar = (float) ($duData[$s['id']]['total_dibayar'] ?? 0);
                        $sisa = max(0, $target - $dibayar);
                        $lastBayar = $duData[$s['id']]['last_bayar'] ?? null;

                        $sumTarget += $target;
                        $sumDibayar += $dibayar;
                        $sumSisa += $sisa;

                        $isLunas = ($target > 0 && $dibayar >= $target);
                        $percent = ($target > 0) ? min(100, round(($dibayar / $target) * 100)) : ($dibayar > 0 ? 100 : 0);
                        ?>
                        <tr>
                            <td class="text-center"><?= $i + 1 ?></td>
                            <td class="text-center"><code><?= htmlspecialchars($s['nis']) ?></code></td>
                            <td class="fw-medium">
                                <a href="../siswa/history.php?id=<?= $s['id'] ?>#lain"
                                    class="text-decoration-none text-dark fw-bold hover-primary"
                                    title="Lihat Riwayat & Edit Transaksi Siswa">
                                    <?= htmlspecialchars($s['nama']) ?> <i
                                        class="bi bi-box-arrow-up-right small text-muted ms-1" style="font-size: 0.75rem;"></i>
                                </a>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border px-2 py-1 rounded-pill"
                                    style="font-size: 0.75rem;">
                                    <?= htmlspecialchars($s['nama_kelas'] ?? '-') ?>
                                </span>
                            </td>
                            <td class="text-end"><?= number_format($target, 0, ',', '.') ?></td>
                            <td class="text-end text-success fw-bold"><?= number_format($dibayar, 0, ',', '.') ?></td>
                            <td class="text-end text-danger fw-bold"><?= number_format($sisa, 0, ',', '.') ?></td>
                            <td class="text-center">
                                <?php if ($isLunas || ($target == 0 && $dibayar > 0)): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i
                                            class="bi bi-check-circle-fill me-1"></i> LUNAS</span>
                                <?php elseif ($dibayar > 0): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1"><i
                                            class="bi bi-clock-history me-1"></i> <?= $percent ?>% (Dicicil)</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><i
                                            class="bi bi-x-circle me-1"></i> Belum</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center small text-muted"><?= $lastBayar ? formatTanggal($lastBayar) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="4" class="text-end">TOTAL KESELURUHAN:</td>
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

<style>
    /* SUB-TAB PILL STYLING */
    .rekap-tabs-bar {
        width: 100%;
    }

    .rekap-tabs-pill-container {
        display: inline-flex;
        align-items: center;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 50px;
        padding: 5px;
        gap: 5px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
    }

    .rekap-tab-pill {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 18px;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 700;
        color: #475569 !important;
        text-decoration: none !important;
        transition: all 0.2s ease;
        white-space: nowrap;
        border: 1px solid transparent;
    }

    .rekap-tab-pill:hover {
        color: #0f172a !important;
        background: #ffffff;
        border-color: #cbd5e1;
    }

    .rekap-tab-pill.active {
        background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%) !important;
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.2);
        box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35);
    }

    .rekap-tab-pill.active i {
        color: #ffffff !important;
    }

    @media print {

        .sidebar,
        .top-header,
        .rekap-tabs-bar,
        .form-card,
        .btn {
            display: none !important;
        }

        .main-content {
            margin-left: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }

        .table-responsive {
            overflow: visible !important;
        }

        table {
            font-size: 11px !important;
        }

        body {
            background: white !important;
        }

        .print-only {
            display: block !important;
            margin-bottom: 20px;
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
    }

    .print-only {
        display: none;
    }
</style>

<div class="print-only">
    <h3 style="margin:0">REKAP PEMBAYARAN DAFTAR ULANG</h3>
    <p style="margin:5px 0">
        Kategori: <strong><?= htmlspecialchars($selectedJenis['nama_pembayaran'] ?? 'Daftar Ulang') ?></strong> |
        Kelas:
        <strong><?= $kelasId > 0 ? htmlspecialchars($kelasList[array_search($kelasId, array_column($kelasList, "id"))]["nama_kelas"] ?? "-") : "Semua Kelas" ?></strong>
        |
        Tahun: <strong><?= $tahun ?></strong>
    </p>
</div>

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

        const filename = 'Rekap_Daftar_Ulang_<?= $kelasId > 0 ? htmlspecialchars($kelasList[array_search($kelasId, array_column($kelasList, "id"))]["nama_kelas"] ?? "Kelas") : "Semua_Kelas" ?>_<?= $tahun ?>.csv';
        const blob = new Blob(['\ufeff' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.click();
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>