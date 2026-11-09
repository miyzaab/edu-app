<?php
/**
 * DATA SISWA - List semua siswa dengan Aksi Massal (Bulk Edit & Bulk Delete)
 */
$pageTitle = 'Data Siswa';
$activePage = 'siswa';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('siswa');

$pdo = getConnection();

// Auto-sync database jika ada kolom baru
require_once __DIR__ . '/../../config/db_auto_sync.php';
runDatabaseAutoSync($pdo);

// Ambil data siswa + nama kelas (Urutkan Aktif di atas, nonaktif di bawah)
$stmt = $pdo->query("
    SELECT s.*, k.nama_kelas 
    FROM siswa s 
    JOIN kelas k ON s.kelas_id = k.id 
    ORDER BY (s.status = 'aktif') DESC, k.nama_kelas ASC, s.nama ASC
");
$siswaList = $stmt->fetchAll();

// Ambil daftar kelas untuk dropdown Ubah Kelas Massal
$kelasList = $pdo->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <p class="text-muted mb-0">Total: <strong><?= count($siswaList) ?></strong> siswa</p>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="kartu.php" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1.5 rounded-3"><i
                class="bi bi-upc-scan"></i> Cetak Kartu / Barcode</a>
        <a href="bulk_uang_pangkal.php"
            class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1.5 rounded-3"><i
                class="bi bi-gear-fill"></i> Atur Uang Pangkal</a>
        <a href="edit_massal.php" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1.5 rounded-3"><i
                class="bi bi-pencil-square"></i> Edit Massal</a>
        <a href="import.php" class="btn btn-outline-success btn-sm d-flex align-items-center gap-1.5 rounded-3"><i
                class="bi bi-people-fill"></i> Tambah Massal</a>
        <a href="create.php" class="btn-primary-custom d-flex align-items-center gap-1.5"><i class="bi bi-plus-lg"></i>
            Tambah Siswa</a>
    </div>
</div>

<form id="bulkActionForm" action="bulk_action.php" method="POST">
    <input type="hidden" name="bulk_action_type" id="bulkActionType" value="">
    <input type="hidden" name="target_kelas_id" id="hiddenTargetKelasId" value="">
    <input type="hidden" name="target_status" id="hiddenTargetStatus" value="">

    <div class="table-card">
        <div class="table-responsive">
            <table class="data-table" id="tableSiswa">
                <thead>
                    <tr>
                        <th style="width: 40px;" class="text-center">
                            <input type="checkbox" id="selectAll" class="bulk-checkbox" title="Pilih Semua">
                        </th>
                        <th>No</th>
                        <th>NIS / NISN</th>
                        <th>Nama & No. HP Ortu</th>
                        <th>Kelas</th>
                        <th>L/P</th>
                        <th>Thn Masuk</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($siswaList as $i => $s): ?>
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" name="ids[]" value="<?= $s['id'] ?>"
                                    class="bulk-checkbox bulk-item-check" data-name="<?= htmlspecialchars($s['nama']) ?>">
                            </td>
                            <td class="text-muted font-monospace"><?= $i + 1 ?></td>
                            <td>
                                <code><?= htmlspecialchars($s['nis']) ?></code>
                                <?php if (!empty($s['nisn'])): ?>
                                    <small class="text-muted d-block font-monospace mt-0.5" style="font-size: 0.73rem;">
                                        NISN: <?= htmlspecialchars($s['nisn']) ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-avatar-item">
                                    <div class="table-avatar-circle"><?= strtoupper(substr($s['nama'], 0, 1)) ?></div>
                                    <div>
                                        <span class="fw-bold text-dark d-block"><?= htmlspecialchars($s['nama']) ?></span>
                                        <?php if (!empty($s['no_hp_ortu'])): ?>
                                            <small class="text-success d-inline-flex align-items-center gap-1 mt-0.5"
                                                style="font-size: 0.75rem;">
                                                <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($s['no_hp_ortu']) ?>
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted" style="font-size: 0.72rem;"><em>No HP belum
                                                    diisi</em></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><span
                                    class="badge bg-light text-dark border px-2.5 py-1 rounded-pill fw-semibold"><?= htmlspecialchars($s['nama_kelas']) ?></span>
                            </td>
                            <td><?= $s['jenis_kelamin'] ?></td>
                            <td><?= $s['tahun_masuk'] ?></td>
                            <td><span
                                    class="badge-status <?= $s['status'] === 'aktif' ? 'badge-aktif' : 'badge-belum' ?>"><?= ucfirst($s['status']) ?></span>
                            </td>
                            <td>
                                <div class="d-inline-flex align-items-center gap-1.5">
                                    <a href="kartu.php?id=<?= $s['id'] ?>" class="btn-sm-action btn-edit text-primary"
                                        title="Cetak Kartu & Barcode Siswa"><i class="bi bi-upc-scan"></i></a>
                                    <a href="history.php?id=<?= $s['id'] ?>" class="btn-table-info"
                                        title="Riwayat Pembayaran"><i class="bi bi-clock-history"></i> Riwayat</a>
                                    <a href="edit.php?id=<?= $s['id'] ?>" class="btn-sm-action btn-edit" title="Edit"><i
                                            class="bi bi-pencil"></i></a>
                                    <a href="delete.php?id=<?= $s['id'] ?>"
                                        class="btn-sm-action <?= ($s['status'] === 'aktif') ? 'text-warning' : 'text-success' ?>"
                                        title="<?= ($s['status'] === 'aktif') ? 'Nonaktifkan Santri' : 'Aktifkan Kembali Santri' ?>"
                                        onclick="return confirm('Apakah Anda yakin ingin <?= ($s['status'] === 'aktif') ? 'MENONAKTIFKAN' : 'MENGAKTIFKAN KEMBALI' ?> data santri <?= addslashes(htmlspecialchars($s['nama'])) ?>?')">
                                        <i class="bi <?= ($s['status'] === 'aktif') ? 'bi-person-slash' : 'bi-person-check-fill' ?>"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($siswaList)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">Belum ada data siswa.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FLOATING ACTION TOOLBAR -->
    <div class="bulk-floating-bar" id="bulkFloatingBar">
        <div class="d-flex align-items-center gap-2">
            <span class="bulk-count-badge" id="bulkCountBadge">
                <i class="bi bi-check2-circle"></i> <span>0 item dipilih</span>
            </span>
        </div>

        <div class="bulk-actions-group">
            <button type="button" class="btn-bulk-action btn-bulk-secondary" onclick="handleBulkCetakKartu()">
                <i class="bi bi-upc-scan"></i> Cetak Kartu Barcode
            </button>
            <button type="button" class="btn-bulk-action btn-bulk-edit" data-bs-toggle="modal"
                data-bs-target="#modalBulkKelas">
                <i class="bi bi-building"></i> Pindah Kelas
            </button>
            <button type="button" class="btn-bulk-action btn-bulk-secondary" data-bs-toggle="modal"
                data-bs-target="#modalBulkStatus">
                <i class="bi bi-toggle2-on"></i> Ubah Status
            </button>
            <button type="button" class="btn-bulk-action btn-bulk-deactivate" onclick="handleBulkDeactivate()">
                <i class="bi bi-person-slash"></i> Nonaktifkan
            </button>
            <button type="button" class="btn-bulk-cancel" title="Batalkan Pilihan">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>
</form>

<!-- MODAL BULK PINDAH KELAS -->
<div class="modal fade" id="modalBulkKelas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom bg-light px-4 py-3">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-building text-primary me-2"></i> Pindah Kelas
                    Massal</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">Pindahkan seluruh siswa yang dipilih ke kelas baru:</p>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Pilih Kelas Tujuan</label>
                    <select id="modalSelectKelas" class="form-select form-select-lg">
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($kelasList as $k): ?>
                            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-top px-4 py-3 bg-light">
                <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-sm btn-primary fw-bold px-4" onclick="applyBulkKelas()">Simpan
                    Perubahan</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL BULK UBAH STATUS -->
<div class="modal fade" id="modalBulkStatus" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom bg-light px-4 py-3">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-toggle2-on text-primary me-2"></i> Ubah Status
                    Massal</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">Ubah status keaktifan untuk siswa yang dipilih:</p>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Status Siswa Baru</label>
                    <select id="modalSelectStatus" class="form-select form-select-lg">
                        <option value="aktif">🟢 Aktif (Masih Sekolah)</option>
                        <option value="nonaktif">🔴 Nonaktif</option>
                        <option value="lulus">🎓 Lulus (Alumni)</option>
                        <option value="pindah">📦 Pindah Sekolah</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-top px-4 py-3 bg-light">
                <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-sm btn-primary fw-bold px-4" onclick="applyBulkStatus()">Terapkan
                    Status</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        initBulkTable({
            tableId: 'tableSiswa',
            floatingBarId: 'bulkFloatingBar',
            countBadgeId: 'bulkCountBadge',
            masterCheckId: 'selectAll',
            itemCheckClass: 'bulk-item-check',
            formId: 'bulkActionForm'
        });
    });

    function handleBulkCetakKartu() {
        const selected = window.getSelectedBulkIds();
        if (selected.length === 0) return;
        window.open('kartu.php?ids=' + selected.join(','), '_blank');
    }

    function handleBulkDeactivate() {
        const selected = window.getSelectedBulkIds();
        if (selected.length === 0) return;
        if (confirm(`🔴 NONAKTIFKAN SANTRI:\n\nApakah Anda yakin ingin MENONAKTIFKAN ${selected.length} santri terpilih? Seluruh data tagihan dan riwayat pembayaran tetap tersimpan aman.`)) {
            document.getElementById('bulkActionType').value = 'deactivate';
            document.getElementById('bulkActionForm').submit();
        }
    }

    function applyBulkKelas() {
        const kelasId = document.getElementById('modalSelectKelas').value;
        if (!kelasId) {
            alert('Silakan pilih kelas tujuan terlebih dahulu.');
            return;
        }
        document.getElementById('bulkActionType').value = 'edit_kelas';
        document.getElementById('hiddenTargetKelasId').value = kelasId;
        document.getElementById('bulkActionForm').submit();
    }

    function applyBulkStatus() {
        const status = document.getElementById('modalSelectStatus').value;
        document.getElementById('bulkActionType').value = 'edit_status';
        document.getElementById('hiddenTargetStatus').value = status;
        document.getElementById('bulkActionForm').submit();
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>