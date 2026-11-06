<?php
/**
 * DATA SISWA - Edit Siswa Massal (Spreadsheet-style Dynamic Batch Editor)
 */
$pageTitle = 'Edit Siswa Massal';
$activePage = 'siswa';
$activeModule = 'akademik';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('siswa');

$pdo = getConnection();

// Auto-check & auto-migrate tabel dan kolom jika belum ada
require_once __DIR__ . '/../../config/db_auto_sync.php';
runDatabaseAutoSync($pdo);

// Ambil daftar kelas untuk filter dan dropdown baris
$kelasList = $pdo->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC")->fetchAll();

// Filter
$filterKelas = (int) ($_GET['kelas_id'] ?? 0);
$filterStatus = $_GET['status'] ?? 'aktif'; // 'aktif', 'lulus', 'keluar', 'pindah', 'all'
$search = trim($_GET['q'] ?? '');

// PROSES SIMPAN PERUBAHAN MASSAL SISWA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_mass_edit_siswa') {
    $submittedSiswa = $_POST['siswa'] ?? [];
    if (empty($submittedSiswa)) {
        redirect('edit_massal.php?kelas_id=' . $filterKelas . '&status=' . $filterStatus, 'warning', 'Tidak ada data siswa yang dikirim.');
    }

    $updatedCount = 0;
    $errors = [];

    try {
        $pdo->beginTransaction();

        $stmtUpdate = $pdo->prepare("
            UPDATE siswa 
            SET nis = :nis,
                nisn = :nisn,
                nama = :nama,
                no_hp_ortu = :no_hp,
                kelas_id = :kelas_id,
                jenis_kelamin = :jk,
                tahun_masuk = :tahun,
                status = :status
            WHERE id = :id
        ");

        $stmtCheckNis = $pdo->prepare("SELECT id FROM siswa WHERE nis = :nis AND id != :id");
        $stmtUpdateOrtuUser = $pdo->prepare("UPDATE users SET nama_lengkap = :nama_ortu WHERE siswa_id = :sid AND role = 'ortu'");

        foreach ($submittedSiswa as $sid => $data) {
            $sid = (int) $sid;
            $nis = trim($data['nis'] ?? '');
            $nisn = trim($data['nisn'] ?? '') ?: null;
            $nama = trim($data['nama'] ?? '');
            $noHp = trim($data['no_hp_ortu'] ?? '') ?: null;
            $kelasId = (int) ($data['kelas_id'] ?? 0);
            $jk = strtoupper(trim($data['jenis_kelamin'] ?? 'L'));
            $tahun = (int) ($data['tahun_masuk'] ?? date('Y'));
            $status = strtolower(trim($data['status'] ?? 'aktif'));

            if (empty($nis) || empty($nama) || !$kelasId) {
                $errors[] = "Siswa ID #$sid: NIS, Nama, dan Kelas tidak boleh kosong.";
                continue;
            }

            // Cek duplikasi NIS
            $stmtCheckNis->execute([':nis' => $nis, ':id' => $sid]);
            if ($stmtCheckNis->fetch()) {
                $errors[] = "NIS <b>'$nis'</b> sudah digunakan oleh siswa lain.";
                continue;
            }

            // Update siswa
            $stmtUpdate->execute([
                ':nis' => $nis,
                ':nisn' => $nisn,
                ':nama' => $nama,
                ':no_hp' => $noHp,
                ':kelas_id' => $kelasId,
                ':jk' => $jk,
                ':tahun' => $tahun,
                ':status' => $status,
                ':id' => $sid
            ]);

            // Sinkronisasi nama orang tua di tabel users jika terhubung
            $stmtUpdateOrtuUser->execute([
                ':nama_ortu' => 'Orang Tua / Wali ' . $nama,
                ':sid' => $sid
            ]);

            $updatedCount++;
        }

        $pdo->commit();

        $msg = "🎉 Berhasil menyimpan pembaruan untuk <b>$updatedCount</b> data siswa.";
        if (!empty($errors)) {
            $msg .= "<br><small>" . implode("<br>", array_slice($errors, 0, 5)) . (count($errors) > 5 ? "... dan " . (count($errors) - 5) . " lainnya." : "") . "</small>";
        }

        redirect('edit_massal.php?kelas_id=' . $filterKelas . '&status=' . $filterStatus, empty($errors) ? 'success' : 'warning', $msg);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirect('edit_massal.php?kelas_id=' . $filterKelas . '&status=' . $filterStatus, 'danger', 'Gagal menyimpan perubahan data siswa: ' . $e->getMessage());
    }
}

// Hitung total status
$statusCounts = [
    'all' => (int) $pdo->query("SELECT COUNT(*) FROM siswa")->fetchColumn(),
    'aktif' => (int) $pdo->query("SELECT COUNT(*) FROM siswa WHERE status = 'aktif'")->fetchColumn(),
    'lulus' => (int) $pdo->query("SELECT COUNT(*) FROM siswa WHERE status = 'lulus'")->fetchColumn(),
    'keluar' => (int) $pdo->query("SELECT COUNT(*) FROM siswa WHERE status IN ('keluar', 'pindah')")->fetchColumn(),
];

// Query data siswa
$sql = "
    SELECT s.*, k.nama_kelas
    FROM siswa s
    JOIN kelas k ON s.kelas_id = k.id
    WHERE 1=1
";
$params = [];

if ($filterStatus !== 'all') {
    if ($filterStatus === 'keluar') {
        $sql .= " AND s.status IN ('keluar', 'pindah')";
    } else {
        $sql .= " AND s.status = :status";
        $params[':status'] = $filterStatus;
    }
}

if ($filterKelas > 0) {
    $sql .= " AND s.kelas_id = :kelas_id";
    $params[':kelas_id'] = $filterKelas;
}

if (!empty($search)) {
    $sql .= " AND (s.nis LIKE :q OR s.nisn LIKE :q OR s.nama LIKE :q OR s.no_hp_ortu LIKE :q)";
    $params[':q'] = "%$search%";
}

// Urutkan siswa: Status Aktif di atas, nonaktif (keluar/lulus) di bawah
$sql .= " ORDER BY (s.status = 'aktif') DESC, k.nama_kelas ASC, s.nama ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$siswaList = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .mass-siswa-table thead th {
        background: #f8fafc;
        color: #475569;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 12px 14px;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
    }

    .mass-siswa-table tbody td {
        padding: 10px 14px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }

    .mass-siswa-table tbody tr:hover {
        background-color: #f8fafc;
    }

    .quick-action-bar {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 16px 20px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -2px rgba(0, 0, 0, 0.02);
    }

    .cell-input {
        border-radius: 8px;
        border: 1.5px solid #cbd5e1;
        font-size: 0.88rem;
        padding: 7px 12px;
        transition: all 0.2s ease;
    }

    .cell-input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        background: #fff;
    }

    .cell-input.modified {
        border-color: #f59e0b;
        background-color: #fffbeb;
    }

    .sticky-save-bar {
        position: sticky;
        bottom: 20px;
        z-index: 100;
        background: rgba(15, 23, 42, 0.92);
        backdrop-filter: blur(12px);
        border-radius: 16px;
        padding: 12px 24px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
        color: #ffffff;
    }
</style>

<!-- HEADER SECTION -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="index.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                <i class="bi bi-arrow-left"></i> Kembali ke Data Siswa
            </a>
            <span
                class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20 px-3 py-1.5 rounded-pill font-monospace">
                <i class="bi bi-table"></i> Mode Edit Massal Dinamis
            </span>
        </div>
        <h4 class="fw-bold text-dark mb-0">✏️ Edit Data Siswa Massal</h4>
        <p class="text-muted small mb-0">Ubah NIS, Nama, No. HP Ortu/WhatsApp, Kelas, dan Status puluhan santri
            sekaligus seperti spreadsheet.</p>
    </div>

    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-light border px-3 py-2 rounded-3 fw-semibold">
            Batal
        </a>
        <button type="button"
            class="btn btn-success px-4 py-2 rounded-3 fw-bold shadow-sm d-flex align-items-center gap-2"
            onclick="document.getElementById('massEditSiswaForm').submit()">
            <i class="bi bi-save-fill"></i> Simpan Semua Perubahan
        </button>
    </div>
</div>

<!-- FILTER & SEARCH BAR -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <!-- Filter Status -->
            <div class="modern-filter-nav">
                <a class="modern-filter-btn <?= $filterStatus === 'aktif' ? 'active' : '' ?>"
                    href="edit_massal.php?status=aktif&kelas_id=<?= $filterKelas ?>">
                    Aktif <span class="modern-filter-badge"><?= $statusCounts['aktif'] ?></span>
                </a>
                <a class="modern-filter-btn <?= $filterStatus === 'all' ? 'active' : '' ?>"
                    href="edit_massal.php?status=all&kelas_id=<?= $filterKelas ?>">
                    Semua <span class="modern-filter-badge"><?= $statusCounts['all'] ?></span>
                </a>
                <a class="modern-filter-btn <?= $filterStatus === 'lulus' ? 'active' : '' ?>"
                    href="edit_massal.php?status=lulus&kelas_id=<?= $filterKelas ?>">
                    Lulus <span class="modern-filter-badge"><?= $statusCounts['lulus'] ?></span>
                </a>
                <a class="modern-filter-btn <?= $filterStatus === 'keluar' ? 'active' : '' ?>"
                    href="edit_massal.php?status=keluar&kelas_id=<?= $filterKelas ?>">
                    Keluar/Pindah <span class="modern-filter-badge"><?= $statusCounts['keluar'] ?></span>
                </a>
            </div>

            <!-- Filter Kelas & Search -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="d-flex align-items-center gap-1.5">
                    <label class="small fw-bold text-muted mb-0" for="filterKelasSelect"><i class="bi bi-filter"></i>
                        Kelas:</label>
                    <select id="filterKelasSelect" class="form-select form-select-sm border-2 rounded-3"
                        style="width: auto; min-width: 140px;"
                        onchange="location.href='edit_massal.php?status=<?= htmlspecialchars($filterStatus) ?>&kelas_id=' + this.value">
                        <option value="0">Semua Kelas</option>
                        <?php foreach ($kelasList as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $filterKelas === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nama_kelas']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group input-group-sm" style="max-width: 250px;">
                    <span class="input-group-text bg-white border-2 border-end-0"><i
                            class="bi bi-search text-muted"></i></span>
                    <input type="text" id="tableSearchInput" class="form-control border-2 border-start-0"
                        placeholder="Cari santri..." onkeyup="filterTableRows(this.value)">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QUICK ACTION TOOLBAR (AKSI CEPAT MASSAL) -->
<div class="quick-action-bar mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="small fw-bold text-dark d-flex align-items-center gap-1.5 me-1">
                <i class="bi bi-lightning-charge-fill text-warning"></i> Aksi Cepat:
            </span>

            <!-- Dropdown Ubah Kelas Serentak -->
            <div class="dropdown">
                <button
                    class="btn btn-sm btn-white border fw-semibold dropdown-toggle d-flex align-items-center gap-1.5 rounded-3 shadow-2xs"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-diagram-3 text-primary"></i> Ubah Kelas Serentak
                </button>
                <ul class="dropdown-menu shadow-sm border-0 rounded-3 p-2" style="max-height: 250px; overflow-y: auto;">
                    <li>
                        <h6 class="dropdown-header text-uppercase small fw-bold text-muted px-2 py-1">Pilih Kelas Target
                        </h6>
                    </li>
                    <?php foreach ($kelasList as $c): ?>
                        <li>
                            <a class="dropdown-item rounded-2 py-1.5 small" href="javascript:void(0)"
                                onclick="applyAllKelas(<?= $c['id'] ?>, '<?= addslashes(htmlspecialchars($c['nama_kelas'])) ?>')">
                                <?= htmlspecialchars($c['nama_kelas']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Dropdown Ubah Tahun Masuk Serentak -->
            <div class="dropdown">
                <button
                    class="btn btn-sm btn-white border fw-semibold dropdown-toggle d-flex align-items-center gap-1.5 rounded-3 shadow-2xs"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-calendar3 text-success"></i> Ubah Tahun Masuk
                </button>
                <ul class="dropdown-menu shadow-sm border-0 rounded-3 p-2">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <li>
                            <a class="dropdown-item rounded-2 py-1.5 small" href="javascript:void(0)"
                                onclick="applyAllTahun(<?= $y ?>)">
                                Tahun <?= $y ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </div>

            <!-- Dropdown Ubah Status Serentak -->
            <div class="dropdown">
                <button
                    class="btn btn-sm btn-white border fw-semibold dropdown-toggle d-flex align-items-center gap-1.5 rounded-3 shadow-2xs"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-shield-check text-info"></i> Ubah Status
                </button>
                <ul class="dropdown-menu shadow-sm border-0 rounded-3 p-2">
                    <li>
                        <a class="dropdown-item rounded-2 py-1.5 text-success small" href="javascript:void(0)"
                            onclick="applyAllStatus('aktif')">
                            <i class="bi bi-check-circle-fill"></i> Set Semua Status: Aktif
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item rounded-2 py-1.5 text-primary small" href="javascript:void(0)"
                            onclick="applyAllStatus('lulus')">
                            <i class="bi bi-mortarboard-fill"></i> Set Semua Status: Lulus
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item rounded-2 py-1.5 text-danger small" href="javascript:void(0)"
                            onclick="applyAllStatus('keluar')">
                            <i class="bi bi-x-circle-fill"></i> Set Semua Status: Keluar
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Kontrol Reset -->
        <div class="d-flex align-items-center gap-2">
            <button type="button"
                class="btn btn-sm btn-light border rounded-3 fw-semibold text-muted d-flex align-items-center gap-1.5"
                onclick="resetFormToOriginal()">
                <i class="bi bi-arrow-counterclockwise"></i> Reset Nilai Awal
            </button>
        </div>
    </div>
</div>

<!-- FORM UTAMA -->
<form id="massEditSiswaForm" method="POST"
    action="edit_massal.php?status=<?= htmlspecialchars($filterStatus) ?>&kelas_id=<?= $filterKelas ?>">
    <input type="hidden" name="action" value="save_mass_edit_siswa">

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table mass-siswa-table align-middle mb-0" id="massSiswaTable">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 50px;">NO</th>
                        <th style="width: 140px;">NIS <span class="text-danger">*</span></th>
                        <th style="width: 140px;">NISN</th>
                        <th style="min-width: 230px;">NAMA LENGKAP SANTRI <span class="text-danger">*</span></th>
                        <th style="min-width: 180px;">NO. HP ORTU / WHATSAPP</th>
                        <th style="min-width: 140px;">KELAS <span class="text-danger">*</span></th>
                        <th style="width: 90px;">L/P</th>
                        <th style="width: 110px;">THN MASUK</th>
                        <th style="width: 130px;">STATUS</th>
                        <th class="text-center" style="width: 70px;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($siswaList)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                Tidak ada data santri/siswa untuk kriteria filter ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($siswaList as $i => $s): ?>
                            <?php $isAktif = ($s['status'] === 'aktif'); ?>
                            <tr class="siswa-row <?= !$isAktif ? 'table-light opacity-75' : '' ?>" id="row-<?= $s['id'] ?>">
                                <td class="text-center text-muted font-monospace fw-semibold"><?= $i + 1 ?></td>

                                <!-- NIS -->
                                <td>
                                    <input type="text" name="siswa[<?= $s['id'] ?>][nis]"
                                        class="form-control cell-input font-monospace fw-bold text-dark py-1"
                                        value="<?= htmlspecialchars($s['nis']) ?>"
                                        data-original="<?= htmlspecialchars($s['nis']) ?>" required autocomplete="off">
                                </td>

                                <!-- NISN -->
                                <td>
                                    <input type="text" name="siswa[<?= $s['id'] ?>][nisn]"
                                        class="form-control cell-input font-monospace py-1"
                                        value="<?= htmlspecialchars($s['nisn'] ?? '') ?>"
                                        data-original="<?= htmlspecialchars($s['nisn'] ?? '') ?>" placeholder="NISN..."
                                        autocomplete="off">
                                </td>

                                <!-- Nama Santri -->
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle <?= $isAktif ? 'bg-primary bg-opacity-10 text-primary' : 'bg-secondary bg-opacity-10 text-secondary' ?> fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                                            style="width: 32px; height: 32px; font-size: 0.85rem;">
                                            <?= strtoupper(substr($s['nama'] ?: 'S', 0, 1)) ?>
                                        </div>
                                        <div class="w-100">
                                            <input type="text" name="siswa[<?= $s['id'] ?>][nama]"
                                                class="form-control cell-input fw-semibold text-dark py-1"
                                                value="<?= htmlspecialchars($s['nama']) ?>"
                                                data-original="<?= htmlspecialchars($s['nama']) ?>" required autocomplete="off">
                                        </div>
                                    </div>
                                </td>

                                <!-- No HP Ortu -->
                                <td>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-success"><i
                                                class="bi bi-whatsapp"></i></span>
                                        <input type="text" name="siswa[<?= $s['id'] ?>][no_hp_ortu]"
                                            class="form-control cell-input py-1 font-monospace"
                                            value="<?= htmlspecialchars($s['no_hp_ortu'] ?? '') ?>"
                                            data-original="<?= htmlspecialchars($s['no_hp_ortu'] ?? '') ?>" placeholder="08..."
                                            autocomplete="off">
                                    </div>
                                </td>

                                <!-- Kelas -->
                                <td>
                                    <select name="siswa[<?= $s['id'] ?>][kelas_id]"
                                        class="form-select form-select-sm cell-input py-1 select-kelas"
                                        data-original="<?= $s['kelas_id'] ?>" required>
                                        <?php foreach ($kelasList as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= $s['kelas_id'] == $c['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($c['nama_kelas']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>

                                <!-- Jenis Kelamin -->
                                <td>
                                    <select name="siswa[<?= $s['id'] ?>][jenis_kelamin]"
                                        class="form-select form-select-sm cell-input py-1 font-monospace"
                                        data-original="<?= $s['jenis_kelamin'] ?>">
                                        <option value="L" <?= $s['jenis_kelamin'] === 'L' ? 'selected' : '' ?>>L</option>
                                        <option value="P" <?= $s['jenis_kelamin'] === 'P' ? 'selected' : '' ?>>P</option>
                                    </select>
                                </td>

                                <!-- Tahun Masuk -->
                                <td>
                                    <input type="number" name="siswa[<?= $s['id'] ?>][tahun_masuk]"
                                        class="form-control cell-input py-1 font-monospace select-tahun"
                                        value="<?= (int) $s['tahun_masuk'] ?>" data-original="<?= (int) $s['tahun_masuk'] ?>"
                                        min="2000" max="2099">
                                </td>

                                <!-- Status -->
                                <td>
                                    <select name="siswa[<?= $s['id'] ?>][status]"
                                        class="form-select form-select-sm cell-input py-1 fw-semibold select-status"
                                        data-original="<?= $s['status'] ?>">
                                        <option value="aktif" class="text-success" <?= $s['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                        <option value="lulus" class="text-primary" <?= $s['status'] === 'lulus' ? 'selected' : '' ?>>Lulus</option>
                                        <option value="keluar" class="text-danger" <?= $s['status'] === 'keluar' ? 'selected' : '' ?>>Keluar</option>
                                        <option value="pindah" class="text-warning" <?= $s['status'] === 'pindah' ? 'selected' : '' ?>>Pindah</option>
                                    </select>
                                </td>

                                <!-- Aksi Baris -->
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-light border rounded-circle p-1"
                                        title="Reset baris ini" onclick="resetRow(<?= $s['id'] ?>)">
                                        <i class="bi bi-arrow-counterclockwise text-muted"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- STICKY SAVE BAR -->
    <?php if (!empty($siswaList)): ?>
        <div class="sticky-save-bar d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="fw-bold fs-6 d-flex align-items-center gap-2">
                    <i class="bi bi-people-fill text-success"></i> Total: <strong><?= count($siswaList) ?></strong> Santri
                    Terbuka
                </span>
                <span class="text-light opacity-75 small d-none d-md-inline">&bull; Klik tombol di bawah untuk menyimpan
                    perubahan data santri secara serentak.</span>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="index.php" class="btn btn-outline-light btn-sm px-3 rounded-3">
                    Batal
                </a>
                <button type="submit"
                    class="btn btn-success fw-bold px-4 py-2 rounded-3 shadow d-flex align-items-center gap-2"
                    onclick="return confirm('Apakah Anda yakin ingin menyimpan seluruh perubahan data santri?')">
                    <i class="bi bi-check2-circle fs-5"></i> Simpan Semua Perubahan
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- TOAST NOTIFICATION CONTAINER -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;">
        <div id="liveToast" class="toast align-items-center text-bg-dark border-0 rounded-3 shadow-lg" role="alert"
            aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body py-2.5 px-3 fw-medium" id="toastMessage">
                    Aksi berhasil dijalankan.
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                    aria-label="Close"></button>
            </div>
        </div>
    </div>
</form>

<script>
    // Toast Notifikasi
    function showToast(msg, isSuccess = true) {
        const toastEl = document.getElementById('liveToast');
        const toastMsg = document.getElementById('toastMessage');
        if (toastEl && toastMsg) {
            toastMsg.innerHTML = (isSuccess ? '<i class="bi bi-check-circle-fill text-success me-1.5"></i> ' : '<i class="bi bi-exclamation-triangle-fill text-warning me-1.5"></i> ') + msg;
            const toast = new bootstrap.Toast(toastEl, { delay: 3500 });
            toast.show();
        }
    }

    // Filter realtime pencarian di tabel
    function filterTableRows(query) {
        const q = query.toLowerCase().trim();
        const rows = document.querySelectorAll('#massSiswaTable tbody tr.siswa-row');
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(q) ? '' : 'none';
        });
    }

    // Ubah kelas semua baris yang tampil
    function applyAllKelas(kelasId, kelasNama) {
        const selects = document.querySelectorAll('#massSiswaTable tbody select.select-kelas');
        selects.forEach(sel => {
            sel.value = kelasId;
            sel.classList.add('modified');
        });
        showToast(`Seluruh siswa diset ke kelas <b>${kelasNama}</b>.`);
    }

    // Ubah tahun masuk semua baris
    function applyAllTahun(tahun) {
        const inputs = document.querySelectorAll('#massSiswaTable tbody input.select-tahun');
        inputs.forEach(inp => {
            inp.value = tahun;
            inp.classList.add('modified');
        });
        showToast(`Tahun masuk seluruh siswa diset ke <b>${tahun}</b>.`);
    }

    // Ubah status semua baris
    function applyAllStatus(status) {
        const selects = document.querySelectorAll('#massSiswaTable tbody select.select-status');
        selects.forEach(sel => {
            sel.value = status;
            sel.classList.add('modified');
        });
        showToast(`Status seluruh siswa diset ke <b>${status.toUpperCase()}</b>.`);
    }

    // Reset 1 baris ke original
    function resetRow(id) {
        const row = document.getElementById('row-' + id);
        if (!row) return;
        const inputs = row.querySelectorAll('.cell-input');
        inputs.forEach(input => {
            const orig = input.getAttribute('data-original');
            if (orig !== null) {
                input.value = orig;
            }
            input.classList.remove('modified');
        });
        showToast(`Data santri ID #${id} dikembalikan ke nilai awal.`);
    }

    // Reset seluruh formulir ke original
    function resetFormToOriginal() {
        if (!confirm('Kembalikan seluruh nilai kolom ke data awal?')) return;
        const inputs = document.querySelectorAll('.cell-input');
        inputs.forEach(input => {
            const orig = input.getAttribute('data-original');
            if (orig !== null) {
                input.value = orig;
            }
            input.classList.remove('modified');
        });
        showToast('Seluruh data formulir dikembalikan ke nilai awal.');
    }

    // Deteksi perubahan manual pada input
    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('cell-input')) {
            const orig = e.target.getAttribute('data-original');
            if (orig !== null && e.target.value !== orig) {
                e.target.classList.add('modified');
            } else {
                e.target.classList.remove('modified');
            }
        }
    });

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('cell-input')) {
            const orig = e.target.getAttribute('data-original');
            if (orig !== null && e.target.value !== orig) {
                e.target.classList.add('modified');
            } else {
                e.target.classList.remove('modified');
            }
        }
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>