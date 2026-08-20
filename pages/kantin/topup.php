<?php
/**
 * TOP-UP SALDO KANTIN SISWA
 */
$pageTitle  = 'Top-Up Saldo Kantin Siswa';
$activePage = 'kantin';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('kantin');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$flash  = getFlash();

// Search Siswa
$search = $_GET['search'] ?? '';
$selectedSiswa = null;

if (!empty($_GET['siswa_id'])) {
    $sId = (int)$_GET['siswa_id'];
    $stmtS = $pdo->prepare("
        SELECT s.*, k.nama_kelas, COALESCE(ss.saldo, 0) as saldo
        FROM siswa s
        JOIN kelas k ON s.kelas_id = k.id
        LEFT JOIN saldo_siswa ss ON s.id = ss.siswa_id
        WHERE s.id = :id
    ");
    $stmtS->execute([':id' => $sId]);
    $selectedSiswa = $stmtS->fetch();
}

// Active student list for search
$siswaQuery = "
    SELECT s.id, s.nis, s.nama, k.nama_kelas, COALESCE(ss.saldo, 0) as saldo
    FROM siswa s
    JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN saldo_siswa ss ON s.id = ss.siswa_id
    WHERE s.status = 'aktif'
";
if (!empty($search)) {
    $siswaQuery .= " AND (s.nama LIKE " . $pdo->quote('%' . $search . '%') . " OR s.nis LIKE " . $pdo->quote('%' . $search . '%') . ")";
}
$siswaQuery .= " ORDER BY s.nama ASC LIMIT 50";
$siswaList = $pdo->query($siswaQuery)->fetchAll();

// Recent Top-Up History
$topupHistory = $pdo->query("
    SELECT t.*, s.nama AS nama_siswa, s.nis, k.nama_kelas, u.nama_lengkap AS nama_petugas
    FROM kantin_topup t
    JOIN siswa s ON t.siswa_id = s.id
    JOIN kelas k ON s.kelas_id = k.id
    JOIN users u ON t.user_id = u.id
    ORDER BY t.created_at DESC
    LIMIT 20
")->fetchAll();

// Fetch Pending Top-Up Requests from Parents
$pendingTopupList = $pdo->query("
    SELECT p.*, s.nama AS nama_siswa, s.nis, k.nama_kelas
    FROM pembayaran_pending p
    JOIN siswa s ON p.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    WHERE p.jenis = 'topup_kantin' AND p.status = 'pending'
    ORDER BY p.created_at ASC
")->fetchAll();

// PROSES ACC / APPROVE TOP-UP ORTU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'acc_topup') {
    $pendingId = (int)$_POST['pending_id'];
    try {
        $stmtP = $pdo->prepare("SELECT * FROM pembayaran_pending WHERE id = :id AND jenis = 'topup_kantin' AND status = 'pending'");
        $stmtP->execute([':id' => $pendingId]);
        $pending = $stmtP->fetch();

        if (!$pending) {
            throw new Exception("Data pengajuan top-up tidak ditemukan atau sudah diproses.");
        }

        $pdo->beginTransaction();

        // 1. Record log topup
        $stmtLog = $pdo->prepare("
            INSERT INTO kantin_topup (siswa_id, nominal, metode_bayar, user_id)
            VALUES (:sid, :nom, 'transfer', :uid)
        ");
        $stmtLog->execute([
            ':sid' => $pending['siswa_id'],
            ':nom' => $pending['nominal'],
            ':uid' => $userId
        ]);
        $realPaymentId = $pdo->lastInsertId();

        // 2. Update Saldo Siswa
        $stmtUp = $pdo->prepare("
            INSERT INTO saldo_siswa (siswa_id, saldo) VALUES (:sid, :n)
            ON DUPLICATE KEY UPDATE saldo = saldo + :n2
        ");
        $stmtUp->execute([':sid' => $pending['siswa_id'], ':n' => $pending['nominal'], ':n2' => $pending['nominal']]);

        // 3. Fetch new balance
        $stmtNew = $pdo->prepare("SELECT saldo FROM saldo_siswa WHERE siswa_id = :sid");
        $stmtNew->execute([':sid' => $pending['siswa_id']]);
        $newSaldo = (float)$stmtNew->fetchColumn();

        // 4. Update status pending
        $stmtUpdate = $pdo->prepare("UPDATE pembayaran_pending SET status = 'disetujui', verified_by = :v, verified_at = NOW(), real_payment_id = :rid WHERE id = :id");
        $stmtUpdate->execute([':v' => $userId, ':rid' => $realPaymentId, ':id' => $pendingId]);

        // 5. Notify parent
        try {
            $stmtNotif = $pdo->prepare("INSERT INTO notifikasi_ortu (siswa_id, judul, pesan, tipe, icon) VALUES (:s, :j, :p, 'pembayaran', 'bi-check-circle-fill')");
            $stmtNotif->execute([
                ':s' => $pending['siswa_id'],
                ':j' => 'Top-Up Saldo Disetujui',
                ':p' => 'Alhamdulillah, pengajuan Top-Up Saldo Kantin sebesar Rp ' . number_format($pending['nominal'], 0, ',', '.') . ' telah diverifikasi & disetujui. Sisa Saldo Kantin Anak saat ini: Rp ' . number_format($newSaldo, 0, ',', '.')
            ]);
        } catch (Exception $exNotif) {}

        $pdo->commit();
        redirect('topup.php', 'success', '✨ Pengajuan Top-Up Saldo Kantin sebesar Rp ' . number_format($pending['nominal'], 0, ',', '.') . ' berhasil disetujui (ACC)!');

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redirect('topup.php', 'danger', $e->getMessage());
    }
}

// PROSES REJECT / TOLAK TOP-UP ORTU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject_topup') {
    $pendingId = (int)$_POST['pending_id'];
    $alasan    = trim($_POST['alasan_tolak'] ?? '');

    try {
        if (empty($alasan)) {
            throw new Exception("Alasan penolakan pengajuan top-up wajib diisi.");
        }

        $stmtP = $pdo->prepare("SELECT * FROM pembayaran_pending WHERE id = :id AND jenis = 'topup_kantin' AND status = 'pending'");
        $stmtP->execute([':id' => $pendingId]);
        $pending = $stmtP->fetch();

        if (!$pending) {
            throw new Exception("Data pengajuan top-up tidak ditemukan atau sudah diproses.");
        }

        $pdo->beginTransaction();

        $stmtUpdate = $pdo->prepare("UPDATE pembayaran_pending SET status = 'ditolak', alasan_tolak = :a, verified_by = :v, verified_at = NOW() WHERE id = :id");
        $stmtUpdate->execute([':a' => $alasan, ':v' => $userId, ':id' => $pendingId]);

        // Notify parent
        try {
            $stmtNotif = $pdo->prepare("INSERT INTO notifikasi_ortu (siswa_id, judul, pesan, tipe, icon) VALUES (:s, :j, :p, 'pembayaran', 'bi-x-circle-fill')");
            $stmtNotif->execute([
                ':s' => $pending['siswa_id'],
                ':j' => 'Top-Up Saldo Ditolak',
                ':p' => 'Pengajuan Top-Up Saldo Kantin sebesar Rp ' . number_format($pending['nominal'], 0, ',', '.') . ' ditolak. Alasan: ' . $alasan
            ]);
        } catch (Exception $exNotif) {}

        $pdo->commit();
        redirect('topup.php', 'warning', 'Pengajuan Top-Up Saldo Kantin berhasil ditolak.');

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redirect('topup.php', 'danger', $e->getMessage());
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- TOPUP SALDO LUXURY SYSTEM -->
<style>
    .kantin-hero-card {
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 45%, #2563eb 100%);
        border-radius: 20px;
        border: none;
        box-shadow: 0 10px 30px rgba(30, 27, 75, 0.15);
    }
    .pos-nav-container {
        display: inline-flex;
        align-items: center;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 14px;
        padding: 4px;
    }
    .pos-nav-link {
        padding: 8px 18px;
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
</style>

<!-- HERO HEADER BANNER -->
<div class="card kantin-hero-card text-white p-4 p-md-4.5 mb-4" style="border-radius: 22px;">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-4 p-2.5 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px; background: rgba(255, 255, 255, 0.18); border: 1px solid rgba(255, 255, 255, 0.25); backdrop-filter: blur(10px);">
                <i class="bi bi-wallet2 fs-3 text-white"></i>
            </div>
            <div>
                <h4 class="fw-extrabold text-white mb-0" style="font-family: 'Outfit', sans-serif; letter-spacing: -0.02em;">Top-Up Saldo Kantin Santri</h4>
                <p class="text-white-50 small mb-0">Pengisian &amp; verifikasi saldo E-Wallet Kantin belanja nontunai siswa</p>
            </div>
        </div>

        <!-- SEGMENTED NAVIGATION CONTROLLER -->
        <div class="pos-nav-container">
            <a href="index.php" class="pos-nav-link"><i class="bi bi-calculator-fill"></i> Kasir POS</a>
            <a href="topup.php" class="pos-nav-link active"><i class="bi bi-wallet2"></i> Top-Up Saldo <?= count($pendingTopupList) > 0 ? '<span class="badge bg-danger rounded-pill ms-1">' . count($pendingTopupList) . '</span>' : '' ?></a>
            <a href="menu.php" class="pos-nav-link"><i class="bi bi-egg-fried"></i> Kelola Menu</a>
            <a href="laporan.php" class="pos-nav-link"><i class="bi bi-graph-up-arrow"></i> Laporan</a>
        </div>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?= $flash['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- PERMOHONAN TOP-UP ORTU (PENDING ACC) SECTION -->
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="border-radius: 20px !important;">
    <div class="card-header bg-white py-3.5 px-4 d-flex align-items-center justify-content-between border-bottom">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle p-2.5 bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                <i class="bi bi-bell-fill fs-5"></i>
            </div>
            <div>
                <h6 class="fw-extrabold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Permohonan Top-Up Saldo Kantin dari Orang Tua (Pending ACC)</h6>
                <p class="text-muted extra-small mb-0">Daftar pengajuan saldo e-wallet kantin melalui transfer bank/e-wallet yang membutuhkan verifikasi</p>
            </div>
        </div>
        <div>
            <span class="badge <?= count($pendingTopupList) > 0 ? 'bg-danger text-white' : 'bg-success text-white' ?> fw-extrabold px-3 py-2 rounded-pill extra-small">
                <?= count($pendingTopupList) ?> Permohonan Pending
            </span>
        </div>
    </div>

    <?php if (empty($pendingTopupList)): ?>
        <div class="card-body text-center py-4 bg-light">
            <i class="bi bi-check-circle-fill text-success fs-2 d-block mb-1"></i>
            <span class="text-muted small fw-bold">Tidak ada permohonan top-up kantin dari orang tua yang pending. Semua permohonan telah diproses!</span>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light extra-small">
                    <tr>
                        <th class="ps-3">Waktu Pengajuan</th>
                        <th>Siswa & Kelas</th>
                        <th>Nominal Top-Up</th>
                        <th>Bukti Transfer</th>
                        <th>Catatan Ortu</th>
                        <th class="text-end pe-3">Aksi Persetujuan (ACC)</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php foreach ($pendingTopupList as $pt): ?>
                        <tr>
                            <td class="ps-3 text-muted extra-small">
                                <i class="bi bi-clock me-1"></i><?= date('d M Y H:i', strtotime($pt['created_at'])) ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($pt['nama_siswa']) ?></strong><br>
                                <span class="extra-small text-muted">NIS: <?= htmlspecialchars($pt['nis']) ?> • Kelas <?= htmlspecialchars($pt['nama_kelas']) ?></span>
                            </td>
                            <td>
                                <strong class="text-success fs-6">Rp <?= number_format($pt['nominal'], 0, ',', '.') ?></strong>
                            </td>
                            <td>
                                <?php if (!empty($pt['bukti_transfer'])): ?>
                                    <button type="button" class="btn btn-sm btn-outline-info rounded-pill extra-small fw-bold py-1 px-2" onclick="previewBukti('<?= htmlspecialchars(getBuktiUrl($pt['bukti_transfer'])) ?>')">
                                        <i class="bi bi-image me-1"></i> Lihat Bukti
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted extra-small italic">Tanpa Foto</span>
                                <?php endif; ?>
                            </td>
                            <td class="extra-small text-muted">
                                <?= htmlspecialchars($pt['catatan'] ?: '-') ?>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-inline-flex gap-2">
                                    <!-- ACC BUTTON -->
                                    <form method="POST" action="topup.php" onsubmit="return confirm('Apakah Anda yakin ingin menyetujui (ACC) Top-Up Saldo sebesar Rp <?= number_format($pt['nominal'], 0, ',', '.') ?> untuk <?= htmlspecialchars($pt['nama_siswa']) ?>?')">
                                        <input type="hidden" name="action" value="acc_topup">
                                        <input type="hidden" name="pending_id" value="<?= $pt['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success rounded-3 fw-bold px-3 py-1 text-nowrap shadow-sm">
                                            <i class="bi bi-check-circle-fill me-1"></i> ACC / Setujui
                                        </button>
                                    </form>

                                    <!-- REJECT BUTTON -->
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-3 fw-bold px-3 py-1 text-nowrap" onclick="openRejectModal(<?= $pt['id'] ?>, '<?= htmlspecialchars(addslashes($pt['nama_siswa'])) ?>', '<?= number_format($pt['nominal'], 0, ',', '.') ?>')">
                                        <i class="bi bi-x-circle me-1"></i> Tolak
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <!-- LEFT COLUMN: SEARCH SISWA & FORM TOPUP -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-person-plus-fill text-success me-2"></i>Pilih Siswa & Nominal Top-Up</h6>

            <!-- SEARCH INPUT -->
            <form method="GET" action="topup.php" class="mb-3">
                <div class="input-group">
                    <input type="text" name="search" class="form-control bg-light border-0 fw-bold" placeholder="Ketik Nama Siswa / NIS..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-teal px-3 fw-bold"><i class="bi bi-search me-1"></i> Cari</button>
                </div>
            </form>

            <form method="POST" action="topup.php">
                <input type="hidden" name="action" value="topup">

                <div class="mb-3">
                    <label class="form-label extra-small fw-bold text-muted">Pilih Siswa</label>
                    <select name="siswa_id" class="form-select bg-light border-0 fw-bold" required onchange="window.location.href='topup.php?siswa_id='+this.value">
                        <option value="">-- Pilih Siswa --</option>
                        <?php foreach ($siswaList as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($selectedSiswa && $selectedSiswa['id'] == $s['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['nama']) ?> (<?= htmlspecialchars($s['nama_kelas']) ?>) - Saldo: Rp <?= number_format($s['saldo'], 0, ',', '.') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($selectedSiswa): ?>
                    <div class="p-3 rounded-4 bg-success-subtle text-success mb-3 border border-success d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($selectedSiswa['nama']) ?></h6>
                            <span class="extra-small text-muted">NIS: <?= htmlspecialchars($selectedSiswa['nis']) ?> • Kelas <?= htmlspecialchars($selectedSiswa['nama_kelas']) ?></span>
                        </div>
                        <div class="text-end">
                            <span class="extra-small text-muted fw-bold d-block">Saldo Sekarang</span>
                            <h5 class="fw-extrabold text-success mb-0">Rp <?= number_format($selectedSiswa['saldo'], 0, ',', '.') ?></h5>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label extra-small fw-bold text-muted">Nominal Top-Up (Rp)</label>
                    <input type="text" name="nominal" id="inputNominal" class="form-control form-control-lg bg-light border-0 fw-extrabold text-success" placeholder="0" required oninput="formatRupiahInput(this)">
                </div>

                <!-- PRESET AMOUNT BUTTONS -->
                <div class="mb-4">
                    <label class="form-label extra-small fw-bold text-muted mb-2">Pilihan Cepat Nominal</label>
                    <div class="row g-2">
                        <div class="col-3">
                            <button type="button" class="btn btn-outline-success w-100 rounded-3 small fw-bold" onclick="setNominal(10000)">+10.000</button>
                        </div>
                        <div class="col-3">
                            <button type="button" class="btn btn-outline-success w-100 rounded-3 small fw-bold" onclick="setNominal(20000)">+20.000</button>
                        </div>
                        <div class="col-3">
                            <button type="button" class="btn btn-outline-success w-100 rounded-3 small fw-bold" onclick="setNominal(50000)">+50.000</button>
                        </div>
                        <div class="col-3">
                            <button type="button" class="btn btn-outline-success w-100 rounded-3 small fw-bold" onclick="setNominal(100000)">+100.000</button>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="metode_bayar" value="cash">

                <button type="submit" class="btn btn-success w-100 rounded-3 py-2.5 fw-bold shadow-sm" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <i class="bi bi-plus-circle-fill me-2"></i> Proses Top-Up Saldo Siswa
                </button>
            </form>
        </div>
    </div>

    <!-- RIGHT COLUMN: RECENT TOP-UP LOGS -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-clock-history text-teal me-2"></i>Riwayat Top-Up Terakhir</h6>

            <?php if (empty($topupHistory)): ?>
                <div class="text-center py-5 text-muted small">
                    <i class="bi bi-inbox fs-1 d-block mb-1 text-secondary opacity-50"></i>
                    Belum ada riwayat top-up saldo kantin.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle extra-small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Waktu</th>
                                <th>Siswa</th>
                                <th>Nominal</th>
                                <th>Petugas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topupHistory as $th): ?>
                                <tr>
                                    <td><?= date('d/m/y H:i', strtotime($th['created_at'])) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($th['nama_siswa']) ?></strong><br>
                                        <span class="text-muted"><?= htmlspecialchars($th['nama_kelas']) ?></span>
                                    </td>
                                    <td class="fw-extrabold text-success">+Rp <?= number_format($th['nominal'], 0, ',', '.') ?></td>
                                    <td class="text-muted"><?= htmlspecialchars($th['nama_petugas']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODAL PREVIEW BUKTI TRANSFER -->
<div class="modal fade" id="modalBuktiPreview" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content rounded-4 border-0 p-3 text-center">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-image me-1 text-info"></i> Bukti Transfer Top-Up Saldo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <img id="imgBuktiPreview" src="" alt="Bukti Transfer" class="img-fluid rounded-3 border shadow-sm mb-2" style="max-height: 420px; object-fit: contain;">
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary w-100 rounded-3 fw-bold" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TOLAK TOP-UP -->
<div class="modal fade" id="modalTolakTopup" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 p-3">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-x-circle-fill text-danger me-1"></i> Tolak Pengajuan Top-Up Saldo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="topup.php">
                <input type="hidden" name="action" value="reject_topup">
                <input type="hidden" name="pending_id" id="rejectPendingId" value="">
                <div class="modal-body py-3">
                    <div class="p-3 bg-danger-subtle text-danger rounded-3 mb-3 extra-small">
                        Tolak permohonan top-up dari <strong id="rejectSiswaNama"></strong> sebesar <strong id="rejectNominal"></strong>.
                    </div>
                    <div class="mb-2">
                        <label class="form-label extra-small fw-bold text-muted">Alasan Penolakan</label>
                        <textarea name="alasan_tolak" class="form-control bg-light border-0 fw-bold extra-small" rows="3" placeholder="Tuliskan alasan penolakan (misal: Bukti transfer tidak terbaca / belum masuk rekening)..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 fw-bold extra-small me-auto" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger rounded-3 fw-bold extra-small px-3">Tolak Permohonan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function setNominal(amount) {
    const input = document.getElementById('inputNominal');
    input.value = new Intl.NumberFormat('id-ID').format(amount);
}

function formatRupiahInput(input) {
    let value = input.value.replace(/[^0-9]/g, '');
    if (value) {
        input.value = new Intl.NumberFormat('id-ID').format(value);
    } else {
        input.value = '';
    }
}

function previewBukti(url) {
    document.getElementById('imgBuktiPreview').src = url;
    const modal = new bootstrap.Modal(document.getElementById('modalBuktiPreview'));
    modal.show();
}

function openRejectModal(id, nama, nominal) {
    document.getElementById('rejectPendingId').value = id;
    document.getElementById('rejectSiswaNama').innerText = nama;
    document.getElementById('rejectNominal').innerText = 'Rp ' + nominal;
    const modal = new bootstrap.Modal(document.getElementById('modalTolakTopup'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
