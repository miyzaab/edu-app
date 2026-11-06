<?php
/**
 * REKAP SPP KELAS - Tampilan Matriks 12 Bulan (Otomatis Tampil Semua Kelas)
 */
$pageTitle = 'Rekap SPP Bulanan';
$activePage = 'rekap-spp';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('rekap_spp');

$pdo = getConnection();

// Filter
$kelasId = (int) ($_GET['kelas_id'] ?? 0);
$tahun = (int) ($_GET['tahun'] ?? date('Y'));

// Ambil list kelas untuk dropdown
$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();

$siswaData = [];
$sppData = [];

// Jika kelasId > 0 filter kelas tersebut, jika 0 tampilkan semua siswa aktif
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

    $sqlSpp = "SELECT siswa_id, bulan FROM pembayaran_spp WHERE tahun = ? AND siswa_id IN ($inQuery)";
    $stmtSpp = $pdo->prepare($sqlSpp);

    $params = array_merge([$tahun], $siswaIds);
    $stmtSpp->execute($params);

    while ($row = $stmtSpp->fetch()) {
        $sppData[$row['siswa_id']][$row['bulan']] = true;
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

<!-- 2. FORM FILTER KELAS & TAHUN -->
<div class="form-card mb-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="mb-0 fw-bold"><i class="bi bi-grid-3x3 me-2 text-primary"></i> Matriks Rekap SPP Bulanan (Tahun
            <?= $tahun ?>)
        </h5>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="window.print()" title="Cetak PDF"><i
                    class="bi bi-file-earmark-pdf"></i> PDF</button>
            <button type="button" class="btn btn-sm btn-outline-success" onclick="exportRekapExcel()"
                title="Ekspor Excel"><i class="bi bi-file-earmark-excel"></i> Excel</button>
        </div>
    </div>
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-5">
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
        <div class="col-md-4">
            <label class="form-label small fw-bold">Tahun Pembayaran</label>
            <input type="number" name="tahun" class="form-control form-control-sm" value="<?= $tahun ?>" min="2020"
                max="2099" required>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-sm btn-primary-custom w-100"><i class="bi bi-search me-1"></i> Terapkan
                Filter</button>
        </div>
    </form>
</div>

<!-- 3. TABEL MATRIKS SPP -->
<div class="table-card border-0 shadow-sm overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle text-center mb-0" style="font-size: 0.85rem; white-space: nowrap;">
            <thead class="table-light">
                <tr>
                    <th class="text-start ps-4" style="width: 40px;">NO</th>
                    <th class="text-start" style="min-width: 180px;">NAMA SISWA</th>
                    <th style="width: 90px;">KELAS</th>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <th style="width: 50px;"><?= substr(namaBulan($m), 0, 3) ?></th>
                    <?php endfor; ?>
                    <th style="width: 90px;">PROGRES</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($siswaData)): ?>
                    <tr>
                        <td colspan="16" class="text-muted py-5">Belum ada data santri aktif.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($siswaData as $i => $s):
                        $paidMonths = count($sppData[$s['id']] ?? []);
                        $isFull = ($paidMonths >= 12);
                        ?>
                        <tr style="transition: all 0.2s;">
                            <td class="text-start ps-4 text-muted font-monospace"><?= $i + 1 ?></td>
                            <td class="text-start">
                                <div class="fw-bold text-dark" style="font-size: 0.88rem;"><?= htmlspecialchars($s['nama']) ?>
                                </div>
                                <div class="text-muted extra-small font-monospace"><i
                                        class="bi bi-person-badge opacity-50 me-1"></i><?= htmlspecialchars($s['nis']) ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2 py-1 rounded-pill"
                                    style="font-size: 0.75rem;">
                                    <?= htmlspecialchars($s['nama_kelas'] ?? '-') ?>
                                </span>
                            </td>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <td class="p-1">
                                    <?php if (isset($sppData[$s['id']][$m])): ?>
                                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle"
                                            style="width: 26px; height: 26px; background: rgba(16, 185, 129, 0.15);"
                                            title="<?= namaBulan($m) ?>: Lunas">
                                            <i class="bi bi-check-lg text-success fw-bold" style="font-size: 0.95rem;"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle"
                                            style="width: 26px; height: 26px; background: rgba(241, 245, 249, 0.8);"
                                            title="<?= namaBulan($m) ?>: Belum">
                                            <i class="bi bi-x text-muted opacity-40" style="font-size: 1rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php endfor; ?>
                            <td>
                                <span
                                    class="badge <?= $isFull ? 'bg-success text-white' : ($paidMonths > 0 ? 'bg-warning text-dark' : 'bg-danger text-white') ?> rounded-pill px-2.5 py-1 fw-bold"
                                    style="font-size: 0.72rem;">
                                    <?= $paidMonths ?>/12
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
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
    <h3 style="margin:0; text-transform:uppercase;">REKAP MATRIKS SPP BULANAN</h3>
    <p style="margin:5px 0">
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

        const filename = 'Rekap_SPP_<?= $kelasId > 0 ? htmlspecialchars($kelasList[array_search($kelasId, array_column($kelasList, "id"))]["nama_kelas"] ?? "Kelas") : "Semua_Kelas" ?>_<?= $tahun ?>.csv';
        const blob = new Blob(['\ufeff' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.click();
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>