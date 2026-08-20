<?php
/**
 * DATA SISWA - List semua siswa
 */
$pageTitle  = 'Data Siswa';
$activePage = 'siswa';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

// Ambil data siswa + nama kelas
$stmt = $pdo->query("
    SELECT s.*, k.nama_kelas 
    FROM siswa s 
    JOIN kelas k ON s.kelas_id = k.id 
    ORDER BY k.nama_kelas, s.nama
");
$siswaList = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Total: <strong><?= count($siswaList) ?></strong> siswa</p>
    <div class="d-flex gap-2">
        <a href="bulk_uang_pangkal.php" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1"><i class="bi bi-gear-fill"></i> Atur Uang Pangkal</a>
        <a href="create.php" class="btn-primary-custom"><i class="bi bi-plus-lg"></i> Tambah Siswa</a>
        <a href="import.php" class="btn btn-outline-success btn-sm d-flex align-items-center gap-1"><i class="bi bi-people-fill"></i> Tambah Massal</a>
    </div>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="data-table" id="tableSiswa">
            <thead>
                <tr>
                    <th>No</th><th>NIS</th><th>Nama</th><th>Kelas</th><th>L/P</th><th>Thn Masuk</th><th>Status</th><th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($siswaList as $i => $s): ?>
                <tr>
                    <td class="text-muted font-monospace"><?= $i+1 ?></td>
                    <td><code><?= htmlspecialchars($s['nis']) ?></code></td>
                    <td>
                        <div class="table-avatar-item">
                            <div class="table-avatar-circle"><?= strtoupper(substr($s['nama'], 0, 1)) ?></div>
                            <span class="fw-bold text-dark"><?= htmlspecialchars($s['nama']) ?></span>
                        </div>
                    </td>
                    <td><span class="badge bg-light text-dark border px-2.5 py-1 rounded-pill fw-semibold"><?= htmlspecialchars($s['nama_kelas']) ?></span></td>
                    <td><?= $s['jenis_kelamin'] ?></td>
                    <td><?= $s['tahun_masuk'] ?></td>
                    <td><span class="badge-status <?= $s['status']==='aktif'?'badge-aktif':'badge-belum' ?>"><?= ucfirst($s['status']) ?></span></td>
                    <td>
                        <div class="d-inline-flex align-items-center gap-1.5">
                            <a href="history.php?id=<?= $s['id'] ?>" class="btn-table-info" title="Riwayat Pembayaran"><i class="bi bi-clock-history"></i> Riwayat</a>
                            <a href="edit.php?id=<?= $s['id'] ?>" class="btn-sm-action btn-edit" title="Edit"><i class="bi bi-pencil"></i></a>
                            <button onclick="confirmDelete('delete.php?id=<?= $s['id'] ?>','<?= htmlspecialchars($s['nama']) ?>')" class="btn-sm-action btn-delete" title="Hapus"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
