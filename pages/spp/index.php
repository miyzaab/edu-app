<?php
/**
 * PEMBAYARAN SPP - List & status pembayaran
 */
$pageTitle = 'Pembayaran SPP';
$activePage = 'spp';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('spp');

$pdo = getConnection();
$bulanFilter = (int) ($_GET['bulan'] ?? date('m'));
$tahunFilter = (int) ($_GET['tahun'] ?? date('Y'));

// Handle POST actions (Edit & Hapus SPP)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'edit_spp') {
        $sppId = (int) ($_POST['spp_id'] ?? 0);
        $nominal = (float) str_replace(['.', ','], ['', '.'], $_POST['nominal'] ?? '0');
        $tanggal = $_POST['tanggal_bayar'] ?? date('Y-m-d');
        $metode = $_POST['metode_bayar'] ?? 'tunai';
        $ket = trim($_POST['keterangan'] ?? '');

        if ($sppId > 0 && $nominal > 0) {
            try {
                $stmtUpdate = $pdo->prepare("
                    UPDATE pembayaran_spp 
                    SET nominal = :nom, tanggal_bayar = :tgl, metode_bayar = :met, keterangan = :ket 
                    WHERE id = :id
                ");
                $stmtUpdate->execute([
                    ':nom' => $nominal,
                    ':tgl' => $tanggal,
                    ':met' => $metode,
                    ':ket' => $ket,
                    ':id' => $sppId
                ]);
                redirect("index.php?bulan=$bulanFilter&tahun=$tahunFilter", 'success', 'Pembayaran SPP berhasil diperbarui.');
            } catch (PDOException $e) {
                redirect("index.php?bulan=$bulanFilter&tahun=$tahunFilter", 'danger', 'Gagal memperbarui: ' . $e->getMessage());
            }
        } else {
            redirect("index.php?bulan=$bulanFilter&tahun=$tahunFilter", 'danger', 'Nominal harus lebih dari 0.');
        }
    } elseif ($action === 'delete_spp') {
        $sppId = (int) ($_POST['spp_id'] ?? 0);
        if ($sppId > 0) {
            try {
                $stmtDel = $pdo->prepare("DELETE FROM pembayaran_spp WHERE id = :id");
                $stmtDel->execute([':id' => $sppId]);
                redirect("index.php?bulan=$bulanFilter&tahun=$tahunFilter", 'success', 'Data pembayaran SPP berhasil dihapus / dibatalkan.');
            } catch (PDOException $e) {
                redirect("index.php?bulan=$bulanFilter&tahun=$tahunFilter", 'danger', 'Gagal menghapus pembayaran: ' . $e->getMessage());
            }
        }
    }
}

// Ambil semua siswa aktif + status SPP
$stmt = $pdo->prepare("
    SELECT s.id, s.nis, s.nama, k.nama_kelas,
           spp.id AS spp_id, spp.nominal, spp.tanggal_bayar, spp.metode_bayar, spp.keterangan
    FROM siswa s
    JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN pembayaran_spp spp ON spp.siswa_id = s.id AND spp.bulan = :bulan AND spp.tahun = :tahun
    WHERE s.status = 'aktif'
    ORDER BY k.nama_kelas, s.nama
");
$stmt->execute([':bulan' => $bulanFilter, ':tahun' => $tahunFilter]);
$data = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- SUB-TAB PILL NAVIGATION (SPP, Uang Pangkal, Pembayaran Lain, Jenis Pembayaran) -->
<div class="rekap-tabs-bar mb-4 overflow-auto pb-1">
    <div class="rekap-tabs-pill-container">
        <?php if (hasPermission('spp')): ?>
            <a href="<?= BASE_URL ?>/pages/spp/index.php"
                class="rekap-tab-pill <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/pages/spp/') !== false ? 'active' : '' ?>">
                <i class="bi bi-cash-stack"></i> SPP Bulanan
            </a>
        <?php endif; ?>
        <?php if (hasPermission('uang_pangkal')): ?>
            <a href="<?= BASE_URL ?>/pages/uang-pangkal/index.php"
                class="rekap-tab-pill <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/pages/uang-pangkal/') !== false ? 'active' : '' ?>">
                <i class="bi bi-wallet2"></i> Uang Pangkal
            </a>
        <?php endif; ?>
        <?php if (hasPermission('pembayaran_lain')): ?>
            <a href="<?= BASE_URL ?>/pages/pembayaran-lain/index.php"
                class="rekap-tab-pill <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/pages/pembayaran-lain/') !== false ? 'active' : '' ?>">
                <i class="bi bi-receipt-cutoff"></i> Pembayaran Lain
            </a>
        <?php endif; ?>
        <?php if (hasPermission('jenis_pembayaran')): ?>
            <a href="<?= BASE_URL ?>/pages/jenis-pembayaran/index.php"
                class="rekap-tab-pill <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/pages/jenis-pembayaran/') !== false ? 'active' : '' ?>">
                <i class="bi bi-tags-fill"></i> Jenis Pembayaran
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <!-- Filter -->
    <form class="d-flex gap-2" method="GET">
        <select name="bulan" class="form-select form-select-sm" style="width:140px">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $bulanFilter == $m ? 'selected' : '' ?>><?= namaBulan($m) ?></option>
            <?php endfor; ?>
        </select>
        <input type="number" name="tahun" class="form-control form-control-sm" value="<?= $tahunFilter ?>"
            style="width:90px" min="2020" max="2099">
        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i> Filter</button>
    </form>
    <div class="d-flex gap-2">
        <a href="create-massal.php" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1"><i
                class="bi bi-ui-checks-grid"></i> Input Massal</a>
        <a href="create.php" class="btn-primary-custom"><i class="bi bi-plus-lg"></i> Input Pembayaran</a>
    </div>
</div>

<div class="table-card">
    <div class="card-header">
        <h5><i class="bi bi-cash-stack"></i> SPP <?= namaBulan($bulanFilter) ?> <?= $tahunFilter ?></h5>
    </div>
    <div class="table-responsive">
        <table class="data-table" id="tableSpp">
            <thead>
                <tr>
                    <th>No</th>
                    <th>NIS</th>
                    <th>Nama</th>
                    <th>Kelas</th>
                    <th>Status</th>
                    <th>Nominal</th>
                    <th>Tanggal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $i => $d): ?>
                    <tr>
                        <td class="text-muted font-monospace"><?= $i + 1 ?></td>
                        <td><code><?= htmlspecialchars($d['nis']) ?></code></td>
                        <td>
                            <div class="table-avatar-item">
                                <div class="table-avatar-circle"><?= strtoupper(substr($d['nama'], 0, 1)) ?></div>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($d['nama']) ?></span>
                            </div>
                        </td>
                        <td><span
                                class="badge bg-light text-dark border px-2.5 py-1 rounded-pill fw-semibold"><?= htmlspecialchars($d['nama_kelas']) ?></span>
                        </td>
                        <td>
                            <?php if ($d['spp_id']): ?>
                                <span class="badge-status badge-lunas">Lunas</span>
                            <?php else: ?>
                                <span class="badge-status badge-belum">Belum</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $d['nominal'] ? '<span class="nominal-pill">' . formatRupiah($d['nominal']) . '</span>' : '<span class="text-muted opacity-50">-</span>' ?>
                        </td>
                        <td><?= $d['tanggal_bayar'] ? formatTanggal($d['tanggal_bayar']) : '<span class="text-muted opacity-50">-</span>' ?>
                        </td>
                        <td>
                            <?php if ($d['spp_id']): ?>
                                <div class="d-inline-flex align-items-center gap-1">
                                    <a href="kwitansi.php?id=<?= $d['spp_id'] ?>" class="btn-sm-action btn-print"
                                        target="_blank" title="Cetak Kwitansi"><i class="bi bi-printer"></i></a>
                                    <button type="button" class="btn-sm-action btn-edit" title="Edit Pembayaran / Nominal"
                                        onclick='bukaModalEditSpp(<?= json_encode([
                                            "id" => (int) $d["spp_id"],
                                            "nama" => $d["nama"],
                                            "nis" => $d["nis"],
                                            "kelas" => $d["nama_kelas"],
                                            "nominal" => number_format((float) $d["nominal"], 0, ",", "."),
                                            "tanggal" => $d["tanggal_bayar"],
                                            "metode" => $d["metode_bayar"] ?? "tunai",
                                            "keterangan" => $d["keterangan"] ?? "",
                                            "periode" => namaBulan($bulanFilter) . " " . $tahunFilter
                                        ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn-sm-action btn-delete" title="Hapus / Batal Bayar"
                                        onclick="bukaModalHapusSpp(<?= (int) $d['spp_id'] ?>, '<?= htmlspecialchars(addslashes($d['nama'])) ?>', '<?= htmlspecialchars(formatRupiah($d['nominal'])) ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            <?php else: ?>
                                <a href="create.php?siswa_id=<?= $d['id'] ?>&bulan=<?= $bulanFilter ?>&tahun=<?= $tahunFilter ?>"
                                    class="btn-sm-action btn-edit" title="Bayar SPP"><i class="bi bi-plus-lg"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL EDIT SPP -->
<div class="modal fade" id="modalEditSpp" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h6 class="modal-title fw-bold" id="modalEditTitle">
                    <i class="bi bi-pencil-square me-1"></i> Edit Pembayaran SPP
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form method="POST" id="formEditSpp">
                <input type="hidden" name="action" value="edit_spp">
                <input type="hidden" name="spp_id" id="edit_spp_id" value="">

                <div class="modal-body p-4">
                    <!-- Info Siswa Box -->
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Siswa</div>
                                <div class="fw-bold text-dark fs-6" id="edit_nama_siswa">-</div>
                                <div class="text-muted small mt-0.5" id="edit_info_kelas">-</div>
                            </div>
                            <div class="text-end">
                                <div class="text-muted small">Periode</div>
                                <span class="badge bg-primary px-2.5 py-1.5" id="edit_periode_badge">-</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nominal SPP (Rp) <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white fw-bold">Rp</span>
                            <input type="text" name="nominal" id="edit_nominal"
                                class="form-control fw-bold fs-5 text-success" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Tanggal Bayar <span
                                    class="text-danger">*</span></label>
                            <input type="date" name="tanggal_bayar" id="edit_tanggal" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Metode Bayar</label>
                            <select name="metode_bayar" id="edit_metode" class="form-select">
                                <option value="tunai">Tunai</option>
                                <option value="transfer">Transfer</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold">Keterangan / Catatan</label>
                        <input type="text" name="keterangan" id="edit_keterangan" class="form-control"
                            placeholder="Opsional">
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4"
                        data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                        <i class="bi bi-save me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL HAPUS SPP -->
<div class="modal fade" id="modalHapusSpp" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header bg-danger text-white border-0 py-3">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Batal / Hapus SPP
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="delete_spp">
                <input type="hidden" name="spp_id" id="hapus_spp_id" value="">

                <div class="modal-body p-4 text-center">
                    <div class="mb-3 text-danger fs-1">
                        <i class="bi bi-trash3-fill"></i>
                    </div>
                    <p class="mb-1 text-muted">Apakah Anda yakin ingin membatalkan & menghapus pembayaran SPP untuk:</p>
                    <h6 class="fw-bold text-dark mb-1" id="hapus_nama_siswa">-</h6>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1"
                        id="hapus_nominal_badge">-</span>
                    <p class="small text-muted mt-3 mb-0">Status siswa akan otomatis kembali menjadi <strong>Belum
                            Bayar</strong>.</p>
                </div>
                <div class="modal-footer bg-light border-0 py-2.5 px-3 justify-content-center">
                    <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3"
                        data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm rounded-pill px-3 fw-semibold">
                        <i class="bi bi-trash3 me-1"></i> Ya, Hapus
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function bukaModalEditSpp(data) {
        document.getElementById('edit_spp_id').value = data.id;
        document.getElementById('edit_nama_siswa').textContent = data.nis + ' - ' + data.nama;
        document.getElementById('edit_info_kelas').textContent = 'Kelas ' + data.kelas;
        document.getElementById('edit_periode_badge').textContent = data.periode;
        document.getElementById('edit_nominal').value = data.nominal;
        document.getElementById('edit_tanggal').value = data.tanggal;
        document.getElementById('edit_metode').value = data.metode;
        document.getElementById('edit_keterangan').value = data.keterangan;

        const modal = new bootstrap.Modal(document.getElementById('modalEditSpp'));
        modal.show();
    }

    function bukaModalHapusSpp(id, nama, nominal) {
        document.getElementById('hapus_spp_id').value = id;
        document.getElementById('hapus_nama_siswa').textContent = nama;
        document.getElementById('hapus_nominal_badge').textContent = nominal;

        const modal = new bootstrap.Modal(document.getElementById('modalHapusSpp'));
        modal.show();
    }

    document.addEventListener('DOMContentLoaded', function () {
        const editNominal = document.getElementById('edit_nominal');
        if (editNominal) {
            editNominal.addEventListener('input', function (e) {
                let val = this.value.replace(/\D/g, '');
                if (val) {
                    this.value = parseInt(val, 10).toLocaleString('id-ID');
                } else {
                    this.value = '';
                }
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>