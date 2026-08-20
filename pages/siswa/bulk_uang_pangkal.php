<?php
/**
 * BULK UPDATE UANG PANGKAL & LUNAS STATUS
 */
$pageTitle  = 'Update Massal Uang Pangkal';
$activePage = 'uang-pangkal';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

// AUTO MIGRATION: Pastikan kolom exists
try {
    $check = $pdo->query("SHOW COLUMNS FROM `siswa` LIKE 'is_lunas_uang_pangkal'")->fetch();
    if (!$check) {
        $pdo->exec("ALTER TABLE `siswa` ADD COLUMN is_lunas_uang_pangkal TINYINT(1) DEFAULT 0");
    }
} catch (Exception $e) {}

// Proses Bulk Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siswa_ids = $_POST['siswa_ids'] ?? [];
    $target_up = (float)str_replace(['.', ','], ['', '.'], $_POST['target_uang_pangkal'] ?? '0');
    $mark_lunas = isset($_POST['mark_lunas']) ? 1 : 0;
    $update_target = isset($_POST['update_target']) ? 1 : 0;

    if (!empty($siswa_ids)) {
        try {
            $pdo->beginTransaction();
            
            if ($update_target) {
                $stmt = $pdo->prepare("UPDATE siswa SET target_uang_pangkal = :up WHERE id = :id");
                foreach ($siswa_ids as $id) {
                    $stmt->execute([':up' => $target_up, ':id' => $id]);
                }
            }

            // Update status lunas manual
            $stmtLunas = $pdo->prepare("UPDATE siswa SET is_lunas_uang_pangkal = :lunas WHERE id = :id");
            foreach ($siswa_ids as $id) {
                $stmtLunas->execute([':lunas' => $mark_lunas, ':id' => $id]);
            }

            $pdo->commit();
            redirect('bulk_uang_pangkal.php', 'success', count($siswa_ids) . ' siswa berhasil diperbarui.');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Gagal memperbarui data: ' . $e->getMessage();
        }
    } else {
        $error = 'Pilih minimal satu siswa.';
    }
}

$kelas_filter = $_GET['kelas_id'] ?? '';
$where = "";
if ($kelas_filter) $where = " AND s.kelas_id = " . (int)$kelas_filter;

$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY nama_kelas")->fetchAll();
$siswaList = $pdo->query("SELECT s.*, k.nama_kelas FROM siswa s JOIN kelas k ON s.kelas_id = k.id WHERE s.status = 'aktif' $where ORDER BY k.nama_kelas, s.nama")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card shadow-sm border-0 rounded-3">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold m-0"><i class="bi bi-people-fill text-primary"></i> Update Massal & Checklist Lunas</h5>
            <a href="../uang-pangkal/monitoring.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger shadow-sm"><i class="bi bi-exclamation-circle-fill me-2"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="GET" class="row g-2 mb-4 bg-light p-3 rounded">
            <div class="col-md-4">
                <label class="form-label small fw-bold">Filter Kelas</label>
                <select name="kelas_id" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Semua Kelas --</option>
                    <?php foreach ($kelasList as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= $kelas_filter == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <a href="bulk_uang_pangkal.php" class="btn btn-light border w-100">Reset</a>
            </div>
        </form>

        <form method="POST">
            <div class="p-4 border rounded-3 bg-white shadow-sm mb-4">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-lightning-charge-fill text-warning"></i> Aksi Massal untuk Siswa Terpilih</h6>
                <div class="row g-4 align-items-center">
                    <div class="col-lg-4 col-md-6">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="update_target" id="updateTargetCheck" onchange="toggleTargetInput(this.checked)">
                            <label class="form-check-label fw-semibold" for="updateTargetCheck">Set Target Tagihan</label>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-light">Rp</span>
                            <input type="text" name="target_uang_pangkal" id="targetInput" class="form-control fw-bold" placeholder="Contoh: 10.000.000" onkeyup="formatRupiahInput(this)" disabled>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label fw-semibold d-block">Status Kelunasan</label>
                        <div class="form-check form-switch p-0 m-0">
                            <div class="d-flex align-items-center gap-3 p-2 border rounded bg-light" style="width: fit-content; min-width: 200px;">
                                <input class="form-check-input ms-0" type="checkbox" name="mark_lunas" id="markLunasCheck" style="width: 40px; height: 20px;">
                                <label class="form-check-label fw-bold text-success mb-0" for="markLunasCheck">
                                    <i class="bi bi-check-circle-fill"></i> Tandai Lunas
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold shadow-sm" onclick="return confirm('Terapkan perubahan?')">
                            <i class="bi bi-save me-1"></i> Terapkan Perubahan Massal
                        </button>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="alert alert-info py-2 px-3 mb-0" style="font-size: .8rem; border-left: 4px solid #0dcaf0;">
                        <i class="bi bi-info-circle-fill me-1"></i> 
                        <strong>Info:</strong> Menandai lunas akan menyembunyikan opsi pembayaran Uang Pangkal di portal orang tua bagi siswa tersebut.
                    </div>
                </div>
            </div>

            <div class="mb-3 d-flex justify-content-between align-items-center">
                <label class="form-label fw-bold">Daftar Siswa (<?= count($siswaList) ?>):</label>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" onclick="toggleAll(true)">Pilih Semua</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="toggleAll(false)">Hapus Semua</button>
                </div>
            </div>

            <div class="table-responsive shadow-sm" style="max-height: 500px; overflow-y: auto; border-radius: 12px;">
                <table class="table table-hover align-middle mb-0" style="border-collapse: separate; border-spacing: 0;">
                    <thead class="sticky-top" style="z-index: 10;">
                        <tr style="background: #1e293b; color: #fff;">
                            <th width="50" class="ps-4" style="background: inherit; color: inherit; border-bottom: none;">
                                <div class="form-check m-0">
                                    <input type="checkbox" class="form-check-input" id="checkAll" onclick="toggleAll(this.checked)" style="border-color: rgba(255,255,255,0.3);">
                                </div>
                            </th>
                            <th style="background: inherit; color: inherit; border-bottom: none; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.02em;">NIS</th>
                            <th style="background: inherit; color: inherit; border-bottom: none; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.02em;">Nama Siswa</th>
                            <th style="background: inherit; color: inherit; border-bottom: none; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.02em;">Kelas</th>
                            <th style="background: inherit; color: inherit; border-bottom: none; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.02em;">Target UP</th>
                            <th style="background: inherit; color: inherit; border-bottom: none; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.02em;">Status Manual</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        <?php foreach ($siswaList as $s): ?>
                        <tr style="transition: all 0.2s;">
                            <td class="ps-4">
                                <div class="form-check m-0">
                                    <input type="checkbox" name="siswa_ids[]" value="<?= $s['id'] ?>" class="form-check-input check-item">
                                </div>
                            </td>
                            <td><code style="color: var(--primary); font-weight: 600; font-size: 0.9rem;"><?= htmlspecialchars($s['nis']) ?></code></td>
                            <td class="fw-bold text-dark" style="font-size: 0.95rem;"><?= htmlspecialchars($s['nama']) ?></td>
                            <td><span class="badge" style="background: #e2e8f0; color: #475569; border-radius: 6px; padding: 5px 10px;"><?= htmlspecialchars($s['nama_kelas']) ?></span></td>
                            <td class="fw-bold"><?= formatRupiah($s['target_uang_pangkal']) ?></td>
                            <td>
                                <?php if ($s['is_lunas_uang_pangkal']): ?>
                                    <span class="badge bg-success-subtle text-success px-3 py-2" style="border-radius: 8px;">
                                        <i class="bi bi-lock-fill me-1"></i> Terkunci Lunas
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small" style="opacity: 0.6;">Terbuka</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAll(checked) {
    document.querySelectorAll('.check-item').forEach(el => el.checked = checked);
    document.getElementById('checkAll').checked = checked;
}
function toggleTargetInput(checked) {
    document.getElementById('targetInput').disabled = !checked;
    if(checked) document.getElementById('targetInput').focus();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
