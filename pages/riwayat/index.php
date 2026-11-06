<?php
/**
 * RIWAYAT PEMBAYARAN - List semua transaksi terbaru
 */
$pageTitle = 'Riwayat Pembayaran';
$activePage = 'riwayat';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('riwayat');

$pdo = getConnection();

// Filter & Pagination
$limit = 50;
$page = (int) ($_GET['page'] ?? 1);
$offset = ($page - 1) * $limit;

// Query gabungan (UNION) untuk semua jenis pembayaran
$query = "
    SELECT t.*, s.nama, s.nis, k.nama_kelas 
    FROM (
        SELECT 'spp' as tipe, id, siswa_id, nominal, tanggal_bayar, metode_bayar, keterangan FROM pembayaran_spp
        UNION ALL
        SELECT 'uang_pangkal', id, siswa_id, nominal, tanggal_bayar, metode_bayar, keterangan FROM pembayaran_uang_pangkal
        UNION ALL
        SELECT 'lainnya', id, siswa_id, nominal, tanggal_bayar, metode_bayar, keterangan FROM pembayaran_lain
    ) t
    JOIN siswa s ON t.siswa_id = s.id
    JOIN kelas k ON s.kelas_id = k.id
    ORDER BY t.tanggal_bayar DESC, t.id DESC
    LIMIT $limit OFFSET $offset
";

$riwayat = $pdo->query($query)->fetchAll();

// Hitung total untuk pagination simple
$total = $pdo->query("
    SELECT (SELECT COUNT(*) FROM pembayaran_spp) + 
           (SELECT COUNT(*) FROM pembayaran_uang_pangkal) + 
           (SELECT COUNT(*) FROM pembayaran_lain)
")->fetchColumn();

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- SUB-TAB PILL NAVIGATION (Verifikasi & Riwayat Transaksi) -->
<div class="rekap-tabs-bar mb-4 overflow-auto pb-1">
    <div class="rekap-tabs-pill-container">
        <?php if (hasPermission('verifikasi')):
            $countPendingNav = (int) $pdo->query("SELECT COUNT(id) FROM pembayaran_pending WHERE status='pending' AND jenis IN ('spp', 'uang_pangkal', 'lainnya')")->fetchColumn();
            ?>
            <a href="<?= BASE_URL ?>/pages/verifikasi/index.php"
                class="rekap-tab-pill <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/pages/verifikasi/') !== false ? 'active' : '' ?>">
                <i class="bi bi-shield-check"></i> Verifikasi Transfer
                <?php if ($countPendingNav > 0): ?>
                    <span class="badge bg-danger ms-1 px-2 py-0.5 rounded-pill"><?= $countPendingNav ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>
        <?php if (hasPermission('riwayat')): ?>
            <a href="<?= BASE_URL ?>/pages/riwayat/index.php"
                class="rekap-tab-pill <?= (strpos($_SERVER['REQUEST_URI'] ?? '', '/pages/riwayat/') !== false) ? 'active' : '' ?>">
                <i class="bi bi-clock-history"></i> Riwayat Pembayaran
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Menampilkan <strong><?= count($riwayat) ?></strong> transaksi terakhir dari total
        <strong><?= number_format($total) ?></strong>
    </p>
    <div class="btn-group">
        <a href="../spp/index.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i> Bayar SPP</a>
    </div>
</div>

<form id="bulkActionForm" action="bulk_delete.php" method="POST">
    <div class="table-card">
        <div class="table-responsive">
            <table class="data-table" id="tableRiwayat">
                <thead>
                    <tr>
                        <th style="width: 40px;" class="text-center">
                            <input type="checkbox" id="selectAll" class="bulk-checkbox" title="Pilih Semua">
                        </th>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Nama Siswa</th>
                        <th>Kelas</th>
                        <th>Jenis</th>
                        <th>Nominal</th>
                        <th>Metode</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($riwayat as $i => $r): ?>
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" name="ids[]" value="<?= $r['tipe'] . '|' . $r['id'] ?>"
                                    class="bulk-checkbox bulk-item-check">
                            </td>
                            <td class="text-muted font-monospace"><?= $offset + $i + 1 ?></td>
                            <td class="text-muted small font-monospace"><?= formatTanggal($r['tanggal_bayar']) ?></td>
                            <td>
                                <div class="table-avatar-item">
                                    <div class="table-avatar-circle"><?= strtoupper(substr($r['nama'], 0, 1)) ?></div>
                                    <div>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($r['nama']) ?></div>
                                        <small class="text-muted font-monospace"><i
                                                class="bi bi-hash opacity-50"></i><?= htmlspecialchars($r['nis']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><span
                                    class="badge bg-light text-dark border px-2.5 py-1 rounded-pill fw-semibold"><?= htmlspecialchars($r['nama_kelas']) ?></span>
                            </td>
                            <td>
                                <?php if ($r['tipe'] === 'spp'): ?>
                                    <span
                                        class="badge rounded-pill bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2.5 py-1 font-monospace fw-bold"
                                        style="font-size: 0.73rem;"><i class="bi bi-calendar-check me-1"></i> SPP</span>
                                <?php elseif ($r['tipe'] === 'uang_pangkal'): ?>
                                    <span
                                        class="badge rounded-pill bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2.5 py-1 font-monospace fw-bold"
                                        style="font-size: 0.73rem;"><i class="bi bi-bank me-1"></i> Uang Pangkal</span>
                                <?php else: ?>
                                    <span
                                        class="badge rounded-pill bg-purple bg-opacity-10 text-purple border border-purple border-opacity-25 px-2.5 py-1 font-monospace fw-bold"
                                        style="font-size: 0.73rem; background: rgba(168, 85, 247, 0.1); color: #9333ea; border: 1px solid rgba(168, 85, 247, 0.2);"><i
                                            class="bi bi-tags me-1"></i> Lainnya</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="nominal-pill"><?= formatRupiah($r['nominal']) ?></span></td>
                            <td><span
                                    class="badge bg-light text-secondary border rounded-pill px-2.5 py-1 fw-bold"><?= ucfirst($r['metode_bayar']) ?></span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="edit.php?tipe=<?= $r['tipe'] ?>&id=<?= $r['id'] ?>&redirect=index.php"
                                        class="btn-sm-action btn-edit" title="Edit Transaksi">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <?php
                                    $printPath = match ($r['tipe']) {
                                        'spp' => '../spp/kwitansi.php',
                                        'uang_pangkal' => '../uang-pangkal/kwitansi.php',
                                        default => '../pembayaran-lain/kwitansi.php'
                                    };
                                    ?>
                                    <a href="<?= $printPath ?>?id=<?= $r['id'] ?>" class="btn-sm-action btn-print"
                                        target="_blank" title="Cetak Kwitansi">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($riwayat)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">Belum ada transaksi pembayaran.</td>
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
                <i class="bi bi-check2-circle"></i> <span>0 transaksi dipilih</span>
            </span>
        </div>

        <div class="bulk-actions-group">
            <button type="button" class="btn-bulk-action btn-bulk-edit" data-bs-toggle="modal"
                data-bs-target="#modalBulkEditRiwayat">
                <i class="bi bi-pencil-square"></i> Ubah Massal
            </button>
            <button type="button" class="btn-bulk-action btn-bulk-delete" onclick="handleBulkDeleteRiwayat()">
                <i class="bi bi-trash-fill"></i> Hapus Transaksi
            </button>
            <button type="button" class="btn-bulk-cancel" title="Batalkan Pilihan">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>
</form>

<!-- MODAL BULK EDIT RIWAYAT -->
<div class="modal fade" id="modalBulkEditRiwayat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="bulk_edit.php" method="POST" id="formBulkEditRiwayat">
                <div class="modal-header border-bottom bg-light px-4 py-3">
                    <h6 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-primary me-2"></i> Ubah
                        Transaksi Massal</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">Centang kolom yang ingin diperbarui untuk semua transaksi terpilih:
                    </p>

                    <!-- Ubah Tanggal -->
                    <div class="p-3 border rounded-3 bg-light mb-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="update_tanggal"
                                id="checkUpdateTanggal"
                                onchange="document.getElementById('inputBulkTanggal').disabled = !this.checked">
                            <label class="form-check-label fw-semibold" for="checkUpdateTanggal">Ubah Tanggal
                                Bayar</label>
                        </div>
                        <input type="date" name="tanggal_bayar" id="inputBulkTanggal"
                            class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" disabled>
                    </div>

                    <!-- Ubah Metode Bayar -->
                    <div class="p-3 border rounded-3 bg-light mb-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="update_metode" id="checkUpdateMetode"
                                onchange="document.getElementById('selectBulkMetode').disabled = !this.checked">
                            <label class="form-check-label fw-semibold" for="checkUpdateMetode">Ubah Metode
                                Pembayaran</label>
                        </div>
                        <select name="metode_bayar" id="selectBulkMetode" class="form-select form-select-sm" disabled>
                            <option value="tunai">Tunai</option>
                            <option value="transfer">Transfer Bank</option>
                            <option value="tabungan">Tabungan Siswa</option>
                        </select>
                    </div>

                    <!-- Ubah Keterangan -->
                    <div class="p-3 border rounded-3 bg-light">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="update_keterangan" id="checkUpdateKet"
                                onchange="document.getElementById('inputBulkKet').disabled = !this.checked">
                            <label class="form-check-label fw-semibold" for="checkUpdateKet">Ubah Keterangan /
                                Catatan</label>
                        </div>
                        <input type="text" name="keterangan" id="inputBulkKet" class="form-control form-control-sm"
                            placeholder="Contoh: Lunas via Bendahara" disabled>
                    </div>
                </div>
                <div class="modal-footer border-top px-4 py-3 bg-light">
                    <button type="button" class="btn btn-sm btn-light border px-3"
                        data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-sm btn-primary fw-bold px-4"
                        onclick="submitBulkEditRiwayat()">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        initBulkTable({
            tableId: 'tableRiwayat',
            floatingBarId: 'bulkFloatingBar',
            countBadgeId: 'bulkCountBadge',
            masterCheckId: 'selectAll',
            itemCheckClass: 'bulk-item-check',
            formId: 'bulkActionForm'
        });
    });

    function handleBulkDeleteRiwayat() {
        const selected = window.getSelectedBulkIds();
        if (selected.length === 0) return;
        if (confirm(`⚠️ PERINGATAN:\n\nApakah Anda yakin ingin MENGHAPUS ${selected.length} transaksi pembayaran yang dipilih? Data transaksi yang terhapus tidak dapat dikembalikan.`)) {
            document.getElementById('bulkActionForm').submit();
        }
    }

    function submitBulkEditRiwayat() {
        const isTgl = document.getElementById('checkUpdateTanggal').checked;
        const isMet = document.getElementById('checkUpdateMetode').checked;
        const isKet = document.getElementById('checkUpdateKet').checked;

        if (!isTgl && !isMet && !isKet) {
            alert('Silakan centang minimal satu opsi yang ingin diubah.');
            return;
        }

        const selected = window.getSelectedBulkIds();
        if (selected.length === 0) {
            alert('Tidak ada transaksi yang dipilih.');
            return;
        }

        const formEdit = document.getElementById('formBulkEditRiwayat');
        // Hapus input id lama jika ada
        formEdit.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());

        // Masukkan ID terpilih
        selected.forEach(idVal => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'ids[]';
            inp.value = idVal;
            formEdit.appendChild(inp);
        });

        formEdit.submit();
    }
</script>

<?php if ($total > $limit): ?>
    <div class="d-flex justify-content-center mt-4">
        <nav>
            <ul class="pagination pagination-sm">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>">Prev</a>
                </li>
                <li class="page-item disabled"><span class="page-link">Hal <?= $page ?></span></li>
                <li class="page-item <?= ($page * $limit) >= $total ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>