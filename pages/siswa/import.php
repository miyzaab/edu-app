<?php
/**
 * DATA SISWA - Import / Tambah Massal
 * Mendukung 2 mode:
 * 1. Form dinamis (tambah baris manual)
 * 2. Upload file CSV/Excel
 */
$pageTitle  = 'Tambah Siswa Massal';
$activePage = 'siswa';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();
$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY nama_kelas")->fetchAll();

$successCount = 0;
$errorList = [];

// ========== PROSES FORM MASSAL ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mode'])) {

    // --- MODE 1: Form Dinamis ---
    if ($_POST['mode'] === 'form') {
        $rows = $_POST['siswa'] ?? [];
        foreach ($rows as $idx => $row) {
            $nis   = trim($row['nis'] ?? '');
            $nama  = trim($row['nama'] ?? '');
            $kelasId = (int)($row['kelas_id'] ?? 0);
            $jk    = $row['jenis_kelamin'] ?? '';
            $tahun = (int)($row['tahun_masuk'] ?? date('Y'));

            // Skip baris kosong
            if (empty($nis) && empty($nama)) continue;

            // Validasi
            if (empty($nis) || empty($nama) || !$kelasId || empty($jk)) {
                $errorList[] = "Baris " . ($idx + 1) . ": Data tidak lengkap (NIS: $nis, Nama: $nama).";
                continue;
            }

            try {
                $stmt = $pdo->prepare("INSERT INTO siswa (nis, nama, kelas_id, jenis_kelamin, tahun_masuk) VALUES (:nis,:nama,:kelas,:jk,:tahun)");
                $stmt->execute([':nis'=>$nis, ':nama'=>$nama, ':kelas'=>$kelasId, ':jk'=>$jk, ':tahun'=>$tahun]);
                $successCount++;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $errorList[] = "Baris " . ($idx + 1) . ": NIS '$nis' sudah terdaftar.";
                } else {
                    $errorList[] = "Baris " . ($idx + 1) . ": Gagal menyimpan — " . $e->getMessage();
                }
            }
        }
    }

    // --- MODE 2: Upload CSV ---
    if ($_POST['mode'] === 'csv' && isset($_FILES['csv_file'])) {
        $file = $_FILES['csv_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorList[] = "Gagal mengupload file.";
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv', 'txt'])) {
                $errorList[] = "Format file harus .csv atau .txt";
            } else {
                $handle = fopen($file['tmp_name'], 'r');
                $lineNum = 0;
                $kelasMap = [];
                foreach ($kelasList as $k) {
                    $kelasMap[strtolower(trim($k['nama_kelas']))] = $k['id'];
                }

                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    $lineNum++;
                    // Skip header (baris pertama)
                    if ($lineNum === 1) {
                        // Cek apakah ini header
                        if (stripos($data[0] ?? '', 'nis') !== false || stripos($data[0] ?? '', 'no') !== false) {
                            continue;
                        }
                    }

                    // Format CSV: NIS, Nama, Kelas, L/P, Tahun Masuk
                    $nis   = trim($data[0] ?? '');
                    $nama  = trim($data[1] ?? '');
                    $kelas = strtolower(trim($data[2] ?? ''));
                    $jk    = strtoupper(trim($data[3] ?? ''));
                    $tahun = (int)(trim($data[4] ?? date('Y')));

                    if (empty($nis) || empty($nama)) continue;

                    // Cari kelas_id dari nama kelas
                    $kelasId = $kelasMap[$kelas] ?? 0;
                    if (!$kelasId) {
                        $errorList[] = "Baris $lineNum: Kelas '$kelas' tidak ditemukan. (NIS: $nis)";
                        continue;
                    }

                    if (!in_array($jk, ['L', 'P'])) {
                        $errorList[] = "Baris $lineNum: Jenis kelamin harus L atau P. (NIS: $nis)";
                        continue;
                    }

                    if ($tahun < 2000) $tahun = (int)date('Y');

                    try {
                        $stmt = $pdo->prepare("INSERT INTO siswa (nis, nama, kelas_id, jenis_kelamin, tahun_masuk) VALUES (:nis,:nama,:kelas,:jk,:tahun)");
                        $stmt->execute([':nis'=>$nis, ':nama'=>$nama, ':kelas'=>$kelasId, ':jk'=>$jk, ':tahun'=>$tahun]);
                        $successCount++;
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) {
                            $errorList[] = "Baris $lineNum: NIS '$nis' sudah terdaftar.";
                        } else {
                            $errorList[] = "Baris $lineNum: Gagal menyimpan.";
                        }
                    }
                }
                fclose($handle);
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Hasil Import -->
<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <?php if ($successCount > 0): ?>
        <div class="alert alert-success alert-dismissible fade show">
            ✅ <strong><?= $successCount ?></strong> siswa berhasil ditambahkan!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($errorList)): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            ⚠️ <strong><?= count($errorList) ?></strong> data gagal diproses:
            <ul class="mb-0 mt-1" style="font-size:.8rem">
                <?php foreach ($errorList as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Tab Navigation -->
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabForm" type="button">
            <i class="bi bi-ui-checks-grid"></i> Form Dinamis
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabCsv" type="button">
            <i class="bi bi-file-earmark-arrow-up"></i> Upload CSV
        </button>
    </li>
</ul>

<div class="tab-content">

    <!-- ========== TAB 1: FORM DINAMIS ========== -->
    <div class="tab-pane fade show active" id="tabForm">
        <div class="form-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-people-fill"></i> Input Siswa Massal</h5>
                <button type="button" class="btn-primary-custom" onclick="tambahBaris()">
                    <i class="bi bi-plus-lg"></i> Tambah Baris
                </button>
            </div>

            <form method="POST" id="formMassal">
                <input type="hidden" name="mode" value="form">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="tableMassal" style="font-size:.85rem">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px">No</th>
                                <th style="width:130px">NIS <span class="text-danger">*</span></th>
                                <th>Nama Lengkap <span class="text-danger">*</span></th>
                                <th style="width:130px">Kelas <span class="text-danger">*</span></th>
                                <th style="width:100px">L/P <span class="text-danger">*</span></th>
                                <th style="width:110px">Thn Masuk</th>
                                <th style="width:50px"></th>
                            </tr>
                        </thead>
                        <tbody id="tbodyMassal">
                            <!-- Baris akan ditambahkan oleh JavaScript -->
                        </tbody>
                    </table>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn-primary-custom"><i class="bi bi-save"></i> Simpan Semua</button>
                    <a href="index.php" class="btn btn-light">Kembali</a>
                </div>
            </form>
        </div>
    </div>

    <!-- ========== TAB 2: UPLOAD CSV ========== -->
    <div class="tab-pane fade" id="tabCsv">
        <div class="form-card">
            <h5 class="mb-3"><i class="bi bi-file-earmark-arrow-up"></i> Import dari File CSV</h5>

            <div class="alert alert-info" style="font-size:.8rem">
                <strong>ℹ️ Format CSV yang diterima:</strong><br>
                Kolom harus berurutan: <code>NIS, Nama, Kelas, L/P, Tahun Masuk</code><br>
                Baris pertama boleh berupa header (akan otomatis dilewati).<br>
                Nama kelas harus sesuai: <strong><?= implode(', ', array_column($kelasList, 'nama_kelas')) ?></strong>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="mode" value="csv">
                <div class="mb-3">
                    <label class="form-label">Pilih File CSV <span class="text-danger">*</span></label>
                    <input type="file" name="csv_file" class="form-control" accept=".csv,.txt" required>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn-primary-custom"><i class="bi bi-upload"></i> Import Data</button>
                    <a href="index.php" class="btn btn-light">Kembali</a>
                    <a href="template_siswa.php" class="btn btn-outline-success btn-sm ms-auto"><i class="bi bi-download"></i> Download Template CSV</a>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- JavaScript untuk Form Dinamis -->
<script>
// Data kelas untuk dropdown
const kelasOptions = <?= json_encode($kelasList) ?>;
let rowCount = 0;

function tambahBaris(nis = '', nama = '', kelasId = '', jk = '', tahun = '<?= date('Y') ?>') {
    rowCount++;
    const tbody = document.getElementById('tbodyMassal');

    let kelasSelect = '<option value="">--</option>';
    kelasOptions.forEach(k => {
        const sel = (k.id == kelasId) ? 'selected' : '';
        kelasSelect += `<option value="${k.id}" ${sel}>${k.nama_kelas}</option>`;
    });

    const row = document.createElement('tr');
    row.innerHTML = `
        <td class="text-center row-number">${rowCount}</td>
        <td><input type="text" name="siswa[${rowCount}][nis]" class="form-control form-control-sm" value="${nis}" placeholder="NIS"></td>
        <td><input type="text" name="siswa[${rowCount}][nama]" class="form-control form-control-sm" value="${nama}" placeholder="Nama lengkap"></td>
        <td><select name="siswa[${rowCount}][kelas_id]" class="form-select form-select-sm">${kelasSelect}</select></td>
        <td><select name="siswa[${rowCount}][jenis_kelamin]" class="form-select form-select-sm">
                <option value="">--</option>
                <option value="L" ${jk==='L'?'selected':''}>L</option>
                <option value="P" ${jk==='P'?'selected':''}>P</option>
            </select></td>
        <td><input type="number" name="siswa[${rowCount}][tahun_masuk]" class="form-control form-control-sm" value="${tahun}" min="2000" max="2099"></td>
        <td><button type="button" class="btn-sm-action btn-delete" onclick="hapusBaris(this)"><i class="bi bi-x-lg"></i></button></td>
    `;
    tbody.appendChild(row);
}

function hapusBaris(btn) {
    btn.closest('tr').remove();
    updateNomor();
}

function updateNomor() {
    const rows = document.querySelectorAll('#tbodyMassal tr');
    rows.forEach((row, i) => {
        row.querySelector('.row-number').textContent = i + 1;
    });
}

// Mulai dengan 5 baris kosong
document.addEventListener('DOMContentLoaded', function() {
    for (let i = 0; i < 5; i++) tambahBaris();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
