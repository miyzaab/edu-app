<?php
/**
 * MANAJEMEN MENU & STOK KANTIN - Dengan Foto Menu
 */
$pageTitle = 'Kelola Menu Kantin';
$activePage = 'kantin';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('kantin');

$pdo = getConnection();
$flash = getFlash();

// PROSES TAMBAH / EDIT / HAPUS MENU
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create' || $action === 'update') {
            $namaItem = trim($_POST['nama_item'] ?? '');
            $kategori = $_POST['kategori'] ?? 'makanan';
            $harga = (float) str_replace(['.', ','], ['', '.'], $_POST['harga'] ?? '0');
            $stok = (int) $_POST['stok'];
            $status = $_POST['status'] ?? 'tersedia';

            if (empty($namaItem)) {
                throw new Exception("Nama item menu wajib diisi!");
            }
            if ($harga <= 0) {
                throw new Exception("Harga menu tidak boleh nol!");
            }

            // Handle photo upload
            $fotoName = null;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['foto'];
                $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
                if (in_array(strtolower($file['type']), $allowed) || in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'])) {
                    if ($file['size'] <= 5 * 1024 * 1024) {
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $fotoName = 'menu_' . time() . '_' . rand(100, 999) . '.' . $ext;
                        $uploadDir = __DIR__ . '/../../uploads/kantin/';
                        if (!is_dir($uploadDir))
                            mkdir($uploadDir, 0755, true);
                        move_uploaded_file($file['tmp_name'], $uploadDir . $fotoName);
                    } else {
                        throw new Exception("Ukuran berkas foto maksimal 5MB!");
                    }
                } else {
                    throw new Exception("Format berkas foto tidak didukung. Gunakan JPG, PNG, atau WebP.");
                }
            }

            if ($action === 'create') {
                $stmt = $pdo->prepare("
                    INSERT INTO kantin_menu (nama_item, kategori, harga, stok, foto, status)
                    VALUES (:nama, :kat, :harga, :stok, :foto, :status)
                ");
                $stmt->execute([
                    ':nama' => $namaItem,
                    ':kat' => $kategori,
                    ':harga' => $harga,
                    ':stok' => $stok,
                    ':foto' => $fotoName,
                    ':status' => $status
                ]);
                redirect('menu.php', 'success', 'Item menu baru berhasil ditambahkan!');
            } else {
                $id = (int) $_POST['id'];
                if ($fotoName) {
                    // Hapus foto lama jika ada
                    $stmtOld = $pdo->prepare("SELECT foto FROM kantin_menu WHERE id = :id");
                    $stmtOld->execute([':id' => $id]);
                    $oldFoto = $stmtOld->fetchColumn();
                    if ($oldFoto && file_exists(__DIR__ . '/../../uploads/kantin/' . $oldFoto)) {
                        @unlink(__DIR__ . '/../../uploads/kantin/' . $oldFoto);
                    }

                    $stmt = $pdo->prepare("UPDATE kantin_menu SET nama_item=:n, kategori=:k, harga=:h, stok=:s, foto=:f, status=:st WHERE id=:id");
                    $stmt->execute([':n' => $namaItem, ':k' => $kategori, ':h' => $harga, ':s' => $stok, ':f' => $fotoName, ':st' => $status, ':id' => $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE kantin_menu SET nama_item=:n, kategori=:k, harga=:h, stok=:s, status=:st WHERE id=:id");
                    $stmt->execute([':n' => $namaItem, ':k' => $kategori, ':h' => $harga, ':s' => $stok, ':st' => $status, ':id' => $id]);
                }
                redirect('menu.php', 'success', 'Item menu berhasil diperbarui!');
            }
        } elseif ($action === 'delete') {
            $id = (int) $_POST['id'];
            // Hapus berkas foto lama jika ada
            $stmtOld = $pdo->prepare("SELECT foto FROM kantin_menu WHERE id = :id");
            $stmtOld->execute([':id' => $id]);
            $oldFoto = $stmtOld->fetchColumn();
            if ($oldFoto && file_exists(__DIR__ . '/../../uploads/kantin/' . $oldFoto)) {
                @unlink(__DIR__ . '/../../uploads/kantin/' . $oldFoto);
            }

            $stmt = $pdo->prepare("DELETE FROM kantin_menu WHERE id = :id");
            $stmt->execute([':id' => $id]);
            redirect('menu.php', 'info', 'Item menu berhasil dihapus.');
        }
    } catch (Exception $e) {
        redirect('menu.php', 'danger', $e->getMessage());
    }
}

// Fetch all menu items
$menuList = $pdo->query("SELECT * FROM kantin_menu ORDER BY kategori ASC, nama_item ASC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .kantin-hero-card {
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 45%, #2563eb 100%);
        border-radius: 20px;
        border: none;
        box-shadow: 0 10px 30px rgba(30, 27, 75, 0.15);
    }

    .pos-nav-container {
        display: flex;
        align-items: center;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 14px;
        padding: 4px;
        overflow-x: auto;
        white-space: nowrap;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        max-width: 100%;
    }

    .pos-nav-container::-webkit-scrollbar {
        display: none;
    }

    .pos-nav-link {
        padding: 8px 16px;
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 700;
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .pos-nav-link:hover {
        color: #ffffff;
        background: rgba(255, 255, 255, 0.15);
    }

    .pos-nav-link.active {
        background: #ffffff;
        color: #0f172a;
        font-weight: 800;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
    }

    @media (max-width: 767.98px) {
        .kantin-hero-card {
            border-radius: 16px !important;
            padding: 1.25rem !important;
        }

        .pos-nav-container {
            width: 100%;
        }

        .pos-nav-link {
            padding: 6px 12px;
            font-size: 0.76rem;
        }
    }
</style>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4"
        role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?= $flash['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold text-dark mb-0">Total Menu: <strong><?= count($menuList) ?></strong> Item</h6>
    <button class="btn btn-primary-custom px-3 py-2 rounded-3 fw-bold small" data-bs-toggle="modal"
        data-bs-target="#modalTambahMenu">
        <i class="bi bi-plus-lg me-1"></i> Tambah Menu Baru
    </button>
</div>

<!-- MENU LIST TABLE -->
<form id="bulkActionForm" action="bulk_action.php" method="POST">
    <input type="hidden" name="bulk_action_type" id="bulkActionType" value="">
    <input type="hidden" name="target_kategori" id="hiddenTargetKategori" value="">

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle extra-small mb-0" id="tableMenuKantin">
                <thead class="table-light">
                    <tr>
                        <th style="width: 40px;" class="ps-3 text-center">
                            <input type="checkbox" id="selectAll" class="bulk-checkbox" title="Pilih Semua">
                        </th>
                        <th style="width: 60px;">Foto</th>
                        <th>Nama Menu</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($menuList)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">Belum ada item menu kantin. Silakan tambah
                                menu baru.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($menuList as $m): ?>
                        <tr>
                            <td class="ps-3 text-center">
                                <input type="checkbox" name="ids[]" value="<?= $m['id'] ?>"
                                    class="bulk-checkbox bulk-item-check"
                                    data-name="<?= htmlspecialchars($m['nama_item']) ?>">
                            </td>
                            <td>
                                <?php if (!empty($m['foto']) && file_exists(__DIR__ . '/../../uploads/kantin/' . $m['foto'])): ?>
                                    <img src="../../uploads/kantin/<?= htmlspecialchars($m['foto']) ?>"
                                        alt="<?= htmlspecialchars($m['nama_item']) ?>" class="rounded-3 border shadow-sm"
                                        style="width: 46px; height: 46px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-3 bg-light d-flex align-items-center justify-content-center border"
                                        style="width: 46px; height: 46px; font-size: 1.3rem;">
                                        <?php if ($m['kategori'] === 'makanan'): ?>🍱
                                        <?php elseif ($m['kategori'] === 'minuman'): ?>🥤
                                        <?php else: ?>🍿<?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold text-dark">
                                <?= htmlspecialchars($m['nama_item']) ?>
                            </td>
                            <td>
                                <span
                                    class="badge bg-light text-dark border text-capitalize"><?= htmlspecialchars($m['kategori']) ?></span>
                            </td>
                            <td class="fw-bold text-teal" style="color: #0d9488;">Rp
                                <?= number_format($m['harga'], 0, ',', '.') ?>
                            </td>
                            <td>
                                <span
                                    class="badge <?= $m['stok'] > 10 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> fw-bold px-2 py-1">
                                    <?= $m['stok'] ?> Porsi
                                </span>
                            </td>
                            <td>
                                <?php if ($m['status'] === 'tersedia'): ?>
                                    <span class="badge bg-success rounded-pill px-2 py-1"><i
                                            class="bi bi-check-circle me-1"></i>Tersedia</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary rounded-pill px-2 py-1"><i
                                            class="bi bi-x-circle me-1"></i>Habis</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary me-1"
                                    onclick='editMenu(<?= htmlspecialchars(json_encode($m, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>)'
                                    title="Edit Menu">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <form method="POST" action="menu.php" class="d-inline"
                                    onsubmit="return confirm('Hapus menu <?= addslashes(htmlspecialchars($m['nama_item'])) ?>?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Menu">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FLOATING ACTION TOOLBAR -->
    <div class="bulk-floating-bar" id="bulkFloatingBar">
        <div class="d-flex align-items-center gap-2">
            <span class="bulk-count-badge" id="bulkCountBadge">
                <i class="bi bi-check2-circle"></i> <span>0 menu dipilih</span>
            </span>
        </div>

        <div class="bulk-actions-group">
            <button type="button" class="btn-bulk-action btn-bulk-secondary"
                onclick="handleBulkStatusKantin('status_tersedia')">
                <i class="bi bi-check-circle-fill text-success"></i> Set Tersedia
            </button>
            <button type="button" class="btn-bulk-action btn-bulk-secondary"
                onclick="handleBulkStatusKantin('status_habis')">
                <i class="bi bi-x-circle-fill text-danger"></i> Set Habis
            </button>
            <button type="button" class="btn-bulk-action btn-bulk-edit" data-bs-toggle="modal"
                data-bs-target="#modalBulkKategori">
                <i class="bi bi-tags-fill"></i> Ubah Kategori
            </button>
            <button type="button" class="btn-bulk-action btn-bulk-delete" onclick="handleBulkDeleteKantin()">
                <i class="bi bi-trash-fill"></i> Hapus
            </button>
            <button type="button" class="btn-bulk-cancel" title="Batalkan Pilihan">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>
</form>

<!-- MODAL BULK UBAH KATEGORI -->
<div class="modal fade" id="modalBulkKategori" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom bg-light px-4 py-3">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-tags-fill text-primary me-2"></i> Ubah
                    Kategori Massal</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">Pilih kategori baru untuk semua menu yang dipilih:</p>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Kategori Baru</label>
                    <select id="modalSelectKategori" class="form-select form-select-lg">
                        <option value="makanan">🍱 Makanan Berat / Lauk</option>
                        <option value="minuman">🥤 Minuman Segar / Hangat</option>
                        <option value="snack">🍿 Snack / Jajanan Ringan</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-top px-4 py-3 bg-light">
                <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-sm btn-primary fw-bold px-4" onclick="applyBulkKategori()">Simpan
                    Kategori</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH MENU -->
<div class="modal fade" id="modalTambahMenu" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white p-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Tambah Menu Kantin Baru</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form method="POST" action="menu.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                <div class="modal-body p-4 bg-white">
                    <div class="mb-3">
                        <label class="form-label extra-small fw-bold">Nama Menu Jajanan</label>
                        <input type="text" name="nama_item" class="form-control bg-light border-0 fw-bold"
                            placeholder="Contoh: Es Teh Manis / Nasi Goreng" required>
                    </div>

                    <!-- FOTO MENU INPUT & PREVIEW -->
                    <div class="mb-3">
                        <label class="form-label extra-small fw-bold"><i class="bi bi-image me-1"></i>Foto Menu
                            (Opsional)</label>
                        <div class="d-flex align-items-center gap-3">
                            <div id="tambahPreviewBox"
                                class="rounded-3 border bg-light d-flex align-items-center justify-content-center overflow-hidden"
                                style="width: 64px; height: 64px; flex-shrink: 0;">
                                <i class="bi bi-camera text-muted fs-4"></i>
                            </div>
                            <div class="flex-grow-1">
                                <input type="file" name="foto" class="form-control bg-light border-0 extra-small"
                                    accept="image/jpeg,image/png,image/webp"
                                    onchange="previewImage(this, 'tambahPreviewBox')">
                                <span class="extra-small text-muted">Format: JPG, PNG, WebP (Max: 5MB)</span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label extra-small fw-bold">Kategori</label>
                            <select name="kategori" class="form-select bg-light border-0 fw-bold">
                                <option value="makanan">🍱 Makanan</option>
                                <option value="minuman">🥤 Minuman</option>
                                <option value="jajanan">🍿 Jajanan</option>
                                <option value="lainnya">🛒 Lainnya</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label extra-small fw-bold">Harga (Rp)</label>
                            <input type="text" name="harga" class="form-control bg-light border-0 fw-bold"
                                placeholder="10.000" required oninput="formatRupiahInput(this)">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label extra-small fw-bold">Jumlah Stok</label>
                            <input type="number" name="stok" class="form-control bg-light border-0 fw-bold" value="50"
                                required>
                        </div>
                        <div class="col-6">
                            <label class="form-label extra-small fw-bold">Status Ketersediaan</label>
                            <select name="status" class="form-select bg-light border-0 fw-bold">
                                <option value="tersedia">Tersedia</option>
                                <option value="habis">Habis</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer p-3 bg-light">
                    <button type="submit" class="btn btn-primary w-100 rounded-3 fw-bold">Simpan Menu Baru</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDIT MENU -->
<div class="modal fade" id="modalEditMenu" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white p-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Menu Kantin</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form method="POST" action="menu.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body p-4 bg-white">
                    <div class="mb-3">
                        <label class="form-label extra-small fw-bold">Nama Menu Jajanan</label>
                        <input type="text" name="nama_item" id="editNama" class="form-control bg-light border-0 fw-bold"
                            required>
                    </div>

                    <!-- FOTO MENU EDIT & PREVIEW -->
                    <div class="mb-3">
                        <label class="form-label extra-small fw-bold"><i class="bi bi-image me-1"></i>Foto Menu</label>
                        <div class="d-flex align-items-center gap-3">
                            <div id="editPreviewBox"
                                class="rounded-3 border bg-light d-flex align-items-center justify-content-center overflow-hidden"
                                style="width: 64px; height: 64px; flex-shrink: 0;">
                                <i class="bi bi-camera text-muted fs-4"></i>
                            </div>
                            <div class="flex-grow-1">
                                <input type="file" name="foto" class="form-control bg-light border-0 extra-small"
                                    accept="image/jpeg,image/png,image/webp"
                                    onchange="previewImage(this, 'editPreviewBox')">
                                <span class="extra-small text-muted">Biarkan kosong jika tidak ingin mengubah
                                    foto.</span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label extra-small fw-bold">Kategori</label>
                            <select name="kategori" id="editKategori" class="form-select bg-light border-0 fw-bold">
                                <option value="makanan">🍱 Makanan</option>
                                <option value="minuman">🥤 Minuman</option>
                                <option value="jajanan">🍿 Jajanan</option>
                                <option value="lainnya">🛒 Lainnya</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label extra-small fw-bold">Harga (Rp)</label>
                            <input type="text" name="harga" id="editHarga"
                                class="form-control bg-light border-0 fw-bold" required
                                oninput="formatRupiahInput(this)">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label extra-small fw-bold">Jumlah Stok</label>
                            <input type="number" name="stok" id="editStok"
                                class="form-control bg-light border-0 fw-bold" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label extra-small fw-bold">Status Ketersediaan</label>
                            <select name="status" id="editStatus" class="form-select bg-light border-0 fw-bold">
                                <option value="tersedia">Tersedia</option>
                                <option value="habis">Habis</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer p-3 bg-light">
                    <button type="submit" class="btn btn-dark w-100 rounded-3 fw-bold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function previewImage(input, containerId) {
        const container = document.getElementById(containerId);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                container.innerHTML = `<img src="${e.target.result}" class="w-100 h-100" style="object-fit: cover;">`;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function editMenu(menu) {
        document.getElementById('editId').value = menu.id;
        document.getElementById('editNama').value = menu.nama_item;
        document.getElementById('editKategori').value = menu.kategori;
        document.getElementById('editHarga').value = new Intl.NumberFormat('id-ID').format(menu.harga);
        document.getElementById('editStok').value = menu.stok;
        document.getElementById('editStatus').value = menu.status;

        const previewBox = document.getElementById('editPreviewBox');
        if (menu.foto) {
            previewBox.innerHTML = `<img src="../../uploads/kantin/${menu.foto}" class="w-100 h-100" style="object-fit: cover;">`;
        } else {
            previewBox.innerHTML = `<i class="bi bi-camera text-muted fs-4"></i>`;
        }

        new bootstrap.Modal(document.getElementById('modalEditMenu')).show();
    }

    function formatRupiahInput(input) {
        let value = input.value.replace(/[^0-9]/g, '');
        if (value) {
            input.value = new Intl.NumberFormat('id-ID').format(value);
        } else {
            input.value = '';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initBulkTable({
            tableId: 'tableMenuKantin',
            floatingBarId: 'bulkFloatingBar',
            countBadgeId: 'bulkCountBadge',
            masterCheckId: 'selectAll',
            itemCheckClass: 'bulk-item-check',
            formId: 'bulkActionForm'
        });
    });

    function handleBulkDeleteKantin() {
        const selected = window.getSelectedBulkIds();
        if (selected.length === 0) return;
        if (confirm(`⚠️ PERINGATAN:\n\nApakah Anda yakin ingin MENGHAPUS ${selected.length} item menu kantin yang dipilih? Tindakan ini tidak dapat dibatalkan.`)) {
            document.getElementById('bulkActionType').value = 'delete';
            document.getElementById('bulkActionForm').submit();
        }
    }

    function handleBulkStatusKantin(type) {
        const selected = window.getSelectedBulkIds();
        if (selected.length === 0) return;
        const label = type === 'status_tersedia' ? 'TERSEDIA' : 'HABIS';
        if (confirm(`Ubah status ketersediaan ${selected.length} menu yang dipilih menjadi ${label}?`)) {
            document.getElementById('bulkActionType').value = type;
            document.getElementById('bulkActionForm').submit();
        }
    }

    function applyBulkKategori() {
        const kat = document.getElementById('modalSelectKategori').value;
        document.getElementById('bulkActionType').value = 'edit_kategori';
        document.getElementById('hiddenTargetKategori').value = kat;
        document.getElementById('bulkActionForm').submit();
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>