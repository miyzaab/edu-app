<?php
/**
 * MANAJEMEN E-KANTIN & TOPUP SALDO SISWA - MODUL KEUANGAN ADMIN
 */
$pageTitle  = 'E-Kantin & Topup Saldo Siswa';
$activePage = 'kantin';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('transaksi');

$pdo = getConnection();

// Handle Manual Topup Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'manual_topup') {
    $siswaId = (int)($_POST['siswa_id'] ?? 0);
    $nominal = (float)str_replace(['.', ','], ['', '.'], $_POST['nominal'] ?? '0');
    $metode  = $_POST['metode_bayar'] ?? 'tunai';
    $userId  = $_SESSION['user_id'];

    if ($siswaId > 0 && $nominal > 0) {
        try {
            $pdo->beginTransaction();

            // Insert Record Topup
            $stmt = $pdo->prepare("INSERT INTO kantin_topup (siswa_id, nominal, metode_bayar, user_id) VALUES (:s, :n, :m, :u)");
            $stmt->execute([':s' => $siswaId, ':n' => $nominal, ':m' => $metode, ':u' => $userId]);

            // Update Saldo Siswa
            $stmtSaldo = $pdo->prepare("INSERT INTO saldo_siswa (siswa_id, saldo) VALUES (:s, :n) ON DUPLICATE KEY UPDATE saldo = saldo + :n2");
            $stmtSaldo->execute([':s' => $siswaId, ':n' => $nominal, ':n2' => $nominal]);

            // Kirim Notifikasi ke Ortu
            $stmtNotif = $pdo->prepare("INSERT INTO notifikasi_ortu (siswa_id, judul, pesan, tipe, icon) VALUES (:s, 'Top-Up Saldo Berhasil', :p, 'kantin', 'bi-wallet2')");
            $stmtNotif->execute([
                ':s' => $siswaId,
                ':p' => 'Alhamdulillah, isi ulang saldo E-Kantin sebesar Rp ' . number_format($nominal, 0, ',', '.') . ' telah dikonfirmasi dan masuk ke akun anak Anda.'
            ]);

            $pdo->commit();
            redirect(BASE_URL . '/pages/kantin/manage.php', 'success', 'Top-Up Saldo E-Kantin berhasil ditambahkan!');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errorMsg = 'Gagal memproses top-up: ' . $e->getMessage();
        }
    } else {
        $errorMsg = 'Mohon pilih siswa dan masukkan nominal top-up yang valid.';
    }
}

// Handle Approve Pending Topup dari Portal Ortu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_pending_topup') {
    $pendingId = (int)($_POST['pending_id'] ?? 0);
    $userId    = $_SESSION['user_id'];
    
    if ($pendingId > 0) {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("SELECT * FROM pembayaran_pending WHERE id = :id AND jenis = 'topup_kantin' AND status = 'pending' FOR UPDATE");
            $stmt->execute([':id' => $pendingId]);
            $pending = $stmt->fetch();
            
            if ($pending) {
                // Record log topup
                $stmtIns = $pdo->prepare("INSERT INTO kantin_topup (siswa_id, nominal, metode_bayar, user_id) VALUES (:s, :n, 'transfer_portal', :u)");
                $stmtIns->execute([':s' => $pending['siswa_id'], ':n' => $pending['nominal'], ':u' => $userId]);
                
                // Update Saldo Siswa
                $stmtSaldo = $pdo->prepare("INSERT INTO saldo_siswa (siswa_id, saldo) VALUES (:s, :n) ON DUPLICATE KEY UPDATE saldo = saldo + :n2");
                $stmtSaldo->execute([':s' => $pending['siswa_id'], ':n' => $pending['nominal'], ':n2' => $pending['nominal']]);
                
                // Update status pending
                $stmtUp = $pdo->prepare("UPDATE pembayaran_pending SET status = 'disetujui', verified_by = :u, verified_at = NOW() WHERE id = :id");
                $stmtUp->execute([':u' => $userId, ':id' => $pendingId]);
                
                // Kirim Notifikasi Ortu
                $stmtNotif = $pdo->prepare("INSERT INTO notifikasi_ortu (siswa_id, judul, pesan, tipe, icon) VALUES (:s, 'Top-Up Saldo Berhasil', :p, 'kantin', 'bi-wallet2')");
                $stmtNotif->execute([
                    ':s' => $pending['siswa_id'],
                    ':p' => 'Alhamdulillah, permohonan Top-Up Saldo E-Kantin sebesar Rp ' . number_format($pending['nominal'], 0, ',', '.') . ' telah disetujui dan saldo sudah otomatis bertambah!'
                ]);
                
                $pdo->commit();
                redirect(BASE_URL . '/pages/kantin/manage.php', 'success', 'Permohonan Top-Up Saldo Kantin berhasil disetujui & saldo siswa telah bertambah!');
            } else {
                throw new Exception("Data permohonan tidak ditemukan atau sudah diproses.");
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errorMsg = 'Gagal menyetujui permohonan top-up: ' . $e->getMessage();
        }
    }
}

// Handle Reject Pending Topup dari Portal Ortu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject_pending_topup') {
    $pendingId = (int)($_POST['pending_id'] ?? 0);
    $alasan    = trim($_POST['alasan_penolakan'] ?? 'Bukti transfer tidak valid');
    $userId    = $_SESSION['user_id'];
    
    if ($pendingId > 0) {
        try {
            $pdo->beginTransaction();
            
            $stmtUp = $pdo->prepare("UPDATE pembayaran_pending SET status = 'ditolak', catatan = :c, verified_by = :u, verified_at = NOW() WHERE id = :id AND jenis = 'topup_kantin'");
            $stmtUp->execute([':c' => $alasan, ':u' => $userId, ':id' => $pendingId]);
            
            $stmtSel = $pdo->prepare("SELECT siswa_id, nominal FROM pembayaran_pending WHERE id = :id");
            $stmtSel->execute([':id' => $pendingId]);
            $pData = $stmtSel->fetch();
            if ($pData) {
                $stmtNotif = $pdo->prepare("INSERT INTO notifikasi_ortu (siswa_id, judul, pesan, tipe, icon) VALUES (:s, 'Top-Up Saldo Ditolak', :p, 'kantin', 'bi-x-circle-fill')");
                $stmtNotif->execute([
                    ':s' => $pData['siswa_id'],
                    ':p' => 'Mohon maaf, permohonan Top-Up Saldo E-Kantin sebesar Rp ' . number_format($pData['nominal'], 0, ',', '.') . ' ditolak. Catatan: ' . $alasan
                ]);
            }
            
            $pdo->commit();
            redirect(BASE_URL . '/pages/kantin/manage.php', 'warning', 'Permohonan Top-Up Saldo Kantin telah ditolak.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errorMsg = 'Gagal memproses penolakan: ' . $e->getMessage();
        }
    }
}

// Fetch list saldo siswa
$stmtSaldoList = $pdo->query("
    SELECT s.id, s.nis, s.nama, k.nama_kelas, COALESCE(ss.saldo, 0) AS saldo
    FROM siswa s
    JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN saldo_siswa ss ON s.id = ss.siswa_id
    ORDER BY k.tingkat ASC, s.nama ASC
");
$siswaSaldoList = $stmtSaldoList->fetchAll();

// Fetch riwayat topup terbaru
$stmtTopup = $pdo->query("
    SELECT t.*, s.nama AS nama_siswa, s.nis, k.nama_kelas, u.nama_lengkap AS petugas
    FROM kantin_topup t
    JOIN siswa s ON t.siswa_id = s.id
    JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY t.created_at DESC
    LIMIT 30
");
$riwayatTopupList = $stmtTopup->fetchAll();

// Fetch permohonan topup pending dari portal ortu
$stmtPendingTopup = $pdo->query("
    SELECT p.*, s.nama AS nama_siswa, s.nis, k.nama_kelas
    FROM pembayaran_pending p
    JOIN siswa s ON p.siswa_id = s.id
    JOIN kelas k ON s.kelas_id = k.id
    WHERE p.jenis = 'topup_kantin' AND p.status = 'pending'
    ORDER BY p.created_at DESC
");
$pendingTopupList = $stmtPendingTopup->fetchAll();

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
                <i class="bi bi-shop-window fs-3 text-white"></i>
            </div>
            <div>
                <h4 class="fw-extrabold text-white mb-0" style="font-family: 'Outfit', sans-serif; letter-spacing: -0.02em;">E-Kantin &amp; Saldo E-Wallet Santri</h4>
                <p class="text-white-50 small mb-0">Kelola saldo E-Wallet siswa, permohonan top-up portal ortu &amp; transaksi kantin</p>
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

<?php if (isset($errorMsg)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($errorMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- BADGES STATISTIK -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-success-subtle text-success p-3 rounded-4 fs-3">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div>
                    <small class="text-muted fw-bold d-block">Total Saldo Terendap</small>
                    <h4 class="fw-bold text-dark mb-0">
                        <?php 
                            $totalAllSaldo = array_sum(array_column($siswaSaldoList, 'saldo'));
                            echo 'Rp ' . number_format($totalAllSaldo, 0, ',', '.');
                        ?>
                    </h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning-subtle text-warning p-3 rounded-4 fs-3">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <small class="text-muted fw-bold d-block">Permohonan Topup Portal Ortu</small>
                    <h4 class="fw-bold text-dark mb-0"><?= count($pendingTopupList) ?> <small class="fs-6 text-muted">permohonan</small></h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary-subtle text-primary p-3 rounded-4 fs-3">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <small class="text-muted fw-bold d-block">Siswa Memiliki Saldo</small>
                    <h4 class="fw-bold text-dark mb-0">
                        <?php 
                            $countWithSaldo = count(array_filter($siswaSaldoList, fn($s) => $s['saldo'] > 0));
                            echo $countWithSaldo . ' / ' . count($siswaSaldoList);
                        ?>
                    </h4>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TABEL REKAP SALDO & VERIFIKASI PENDING -->
<div class="row g-4 mb-4">
    <!-- PENDING TOPUP PORTAL ORTU -->
    <?php if (!empty($pendingTopupList)): ?>
        <div class="col-12">
            <div class="card border-warning border-2 shadow-sm rounded-4">
                <div class="card-header bg-warning-subtle py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-clock-history me-2"></i> Permohonan Top-Up Kantin Dari Portal Orang Tua (Pending)</h6>
                    <a href="<?= BASE_URL ?>/pages/verifikasi/index.php" class="btn btn-sm btn-warning fw-bold">Buka Menu Verifikasi Admin</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle m-0 small">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Nama Siswa</th>
                                <th>Kelas</th>
                                <th>Nominal</th>
                                <th>Bukti Transfer</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingTopupList as $pt): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($pt['created_at'])) ?></td>
                                    <td><strong><?= htmlspecialchars($pt['nama_siswa']) ?></strong> (<?= htmlspecialchars($pt['nis']) ?>)</td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($pt['nama_kelas']) ?></span></td>
                                    <td class="fw-bold text-success">Rp <?= number_format($pt['nominal'], 0, ',', '.') ?></td>
                                    <td>
                                        <?php if (!empty($pt['bukti_transfer'])): ?>
                                            <a href="<?= htmlspecialchars(getBuktiUrl($pt['bukti_transfer'])) ?>" target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-image"></i> Lihat Bukti</a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex align-items-center justify-content-end gap-1">
                                            <!-- FORM ACCEPT -->
                                            <form method="POST" action="manage.php" onsubmit="return confirm('Setujui permohonan top-up ini sebesar Rp <?= number_format($pt['nominal'],0,',','.') ?>?');">
                                                <input type="hidden" name="action" value="approve_pending_topup">
                                                <input type="hidden" name="pending_id" value="<?= $pt['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success fw-bold">
                                                    <i class="bi bi-check-circle-fill me-1"></i> Terima &amp; Tambah Saldo
                                                </button>
                                            </form>

                                            <!-- FORM REJECT -->
                                            <form method="POST" action="manage.php" onsubmit="return confirm('Tolak permohonan top-up ini?');">
                                                <input type="hidden" name="action" value="reject_pending_topup">
                                                <input type="hidden" name="pending_id" value="<?= $pt['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger fw-bold">
                                                    <i class="bi bi-x-circle-fill me-1"></i> Tolak
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- TABEL DAFTAR SALDO E-WALLET SISWA -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-wallet2 text-success me-2"></i> Daftar Saldo Kantin E-Wallet Siswa</h6>
            </div>
            <div class="table-responsive" style="max-height: 480px;">
                <table class="table table-hover align-middle small m-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th class="text-end">Saldo Kantin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($siswaSaldoList as $ss): ?>
                            <tr>
                                <td><span class="fw-bold text-dark"><?= htmlspecialchars($ss['nis']) ?></span></td>
                                <td><?= htmlspecialchars($ss['nama']) ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($ss['nama_kelas']) ?></span></td>
                                <td class="text-end fw-extrabold <?= $ss['saldo'] > 0 ? 'text-success' : 'text-muted' ?>">
                                    Rp <?= number_format($ss['saldo'], 0, ',', '.') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TABEL RIWAYAT TOPUP TERAKHIR -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-clock-history text-primary me-2"></i> Riwayat Top-Up Terakhir</h6>
            </div>
            <div class="table-responsive" style="max-height: 480px;">
                <table class="table table-hover align-middle extra-small m-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Waktu</th>
                            <th>Siswa</th>
                            <th>Nominal</th>
                            <th>Petugas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($riwayatTopupList)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Belum ada riwayat top-up.</td></tr>
                        <?php else: ?>
                            <?php foreach ($riwayatTopupList as $rt): ?>
                                <tr>
                                    <td><small><?= date('d/m H:i', strtotime($rt['created_at'])) ?></small></td>
                                    <td><strong><?= htmlspecialchars($rt['nama_siswa']) ?></strong></td>
                                    <td class="fw-bold text-success">+Rp <?= number_format($rt['nominal'], 0, ',', '.') ?></td>
                                    <td><small class="text-muted"><?= htmlspecialchars($rt['petugas'] ?? 'Sistem') ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL MANUAL TOPUP -->
<div class="modal fade" id="modalManualTopup" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-emerald text-white py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-plus-circle-fill me-2"></i> Top-Up Saldo E-Kantin Siswa</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="manual_topup">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Pilih Siswa *</label>
                        <select name="siswa_id" class="form-select rounded-3" required>
                            <option value="">-- Pilih Siswa --</option>
                            <?php foreach ($siswaSaldoList as $s): ?>
                                <option value="<?= $s['id'] ?>">
                                    <?= htmlspecialchars($s['nis']) ?> - <?= htmlspecialchars($s['nama']) ?> (<?= htmlspecialchars($s['nama_kelas']) ?>) - Saldo: Rp <?= number_format($s['saldo'], 0, ',', '.') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Nominal Top-Up (Rp) *</label>
                        <input type="number" name="nominal" class="form-control rounded-3 fw-bold fs-5 text-success" placeholder="Contoh: 50000" step="5000" min="5000" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Metode Pembayaran *</label>
                        <select name="metode_bayar" class="form-select rounded-3" required>
                            <option value="tunai">Tunai / Kasir</option>
                            <option value="transfer">Transfer Bank</option>
                            <option value="qris">QRIS</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-emerald text-white fw-bold rounded-pill px-4">Proses Top-Up</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
