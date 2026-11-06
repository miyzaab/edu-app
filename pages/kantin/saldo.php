<?php
/**
 * DAFTAR SALDO & RIWAYAT JAJAN SANTRI (E-WALLET KANTIN)
 */
$pageTitle = 'Daftar Saldo & Riwayat Jajan Santri';
$activePage = 'saldo-kantin';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('kantin');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$flash = getFlash();

// Handle Quick Top-Up from Saldo Page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'topup_quick') {
    $siswaId = (int) ($_POST['siswa_id'] ?? 0);
    $nominalRaw = $_POST['nominal'] ?? '0';
    $nominal = (float) str_replace(['.', ','], ['', '.'], $nominalRaw);
    $metodeBayar = $_POST['metode_bayar'] ?? 'cash';

    try {
        if ($siswaId <= 0) {
            throw new Exception("Silakan pilih siswa terlebih dahulu.");
        }
        if ($nominal <= 0) {
            throw new Exception("Nominal top-up harus lebih dari Rp 0.");
        }

        require_once __DIR__ . '/../../classes/TransaksiKantin.php';
        $transaksiService = new TransaksiKantin();
        $newSaldo = $transaksiService->topupSaldo($siswaId, $nominal, $userId, 'Top-Up Saldo Tunai via Daftar Saldo');

        $stmtS = $pdo->prepare("SELECT nama FROM siswa WHERE id = :id");
        $stmtS->execute([':id' => $siswaId]);
        $namaSiswa = $stmtS->fetchColumn() ?: 'Siswa';

        redirect('saldo.php', 'success', '✨ Berhasil top-up saldo sebesar Rp ' . number_format($nominal, 0, ',', '.') . ' untuk ' . htmlspecialchars($namaSiswa) . '! Saldo saat ini: Rp ' . number_format($newSaldo, 0, ',', '.'));
    } catch (Exception $e) {
        redirect('saldo.php', 'danger', $e->getMessage());
    }
}

// Handle Delete Transaksi Jajan (with stock & balance refund)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_transaksi') {
    $transaksiId = (int) ($_POST['transaksi_id'] ?? 0);
    try {
        if ($transaksiId <= 0) {
            throw new Exception("ID Transaksi tidak valid.");
        }

        $stmtTr = $pdo->prepare("SELECT * FROM kantin_transaksi WHERE id = :id");
        $stmtTr->execute([':id' => $transaksiId]);
        $tr = $stmtTr->fetch();

        if (!$tr) {
            throw new Exception("Transaksi jajan tidak ditemukan.");
        }

        $pdo->beginTransaction();

        // 1. Kembalikan stok menu
        $stmtDet = $pdo->prepare("SELECT menu_id, jumlah FROM kantin_transaksi_detail WHERE transaksi_id = :id");
        $stmtDet->execute([':id' => $transaksiId]);
        $details = $stmtDet->fetchAll();

        foreach ($details as $d) {
            if (!empty($d['menu_id']) && $d['jumlah'] > 0) {
                $stmtStok = $pdo->prepare("UPDATE kantin_menu SET stok = stok + :qty WHERE id = :mid");
                $stmtStok->execute([':qty' => $d['jumlah'], ':mid' => $d['menu_id']]);
            }
        }

        // 2. Jika bayar dengan Saldo Siswa, kembalikan saldo
        if ($tr['metode_bayar'] === 'saldo' && !empty($tr['siswa_id']) && $tr['total_harga'] > 0) {
            $stmtCekS = $pdo->prepare("SELECT saldo FROM saldo_siswa WHERE siswa_id = :sid FOR UPDATE");
            $stmtCekS->execute([':sid' => $tr['siswa_id']]);
            $saldoSebelum = (float) $stmtCekS->fetchColumn();
            $saldoSesudah = $saldoSebelum + (float) $tr['total_harga'];

            $stmtUpSaldo = $pdo->prepare("
                INSERT INTO saldo_siswa (siswa_id, saldo) VALUES (:sid, :n)
                ON DUPLICATE KEY UPDATE saldo = saldo + :n2
            ");
            $stmtUpSaldo->execute([':sid' => $tr['siswa_id'], ':n' => $tr['total_harga'], ':n2' => $tr['total_harga']]);

            try {
                $stmtMut = $pdo->prepare("
                    INSERT INTO mutasi_saldo (siswa_id, jenis, jumlah, saldo_sebelum, saldo_sesudah, keterangan, created_by) 
                    VALUES (:sid, 'topup', :nom, :seb, :ses, :ket, :uid)
                ");
                $stmtMut->execute([
                    ':sid' => $tr['siswa_id'],
                    ':nom' => $tr['total_harga'],
                    ':seb' => $saldoSebelum,
                    ':ses' => $saldoSesudah,
                    ':ket' => 'Pengembalian Dana Pembatalan Transaksi Jajan (' . $tr['no_transaksi'] . ')',
                    ':uid' => $userId
                ]);
            } catch (Exception $exM) {
            }
        }

        // 3. Hapus detail & master transaksi
        $stmtDelD = $pdo->prepare("DELETE FROM kantin_transaksi_detail WHERE transaksi_id = :id");
        $stmtDelD->execute([':id' => $transaksiId]);

        $stmtDelT = $pdo->prepare("DELETE FROM kantin_transaksi WHERE id = :id");
        $stmtDelT->execute([':id' => $transaksiId]);

        $pdo->commit();
        redirect('saldo.php', 'success', "✅ Transaksi {$tr['no_transaksi']} berhasil dihapus dan saldo/stok telah dikembalikan.");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirect('saldo.php', 'danger', "Gagal menghapus transaksi: " . $e->getMessage());
    }
}

// Ambil Pending Top-Up Count for Badge
$pendingTopupCount = (int) $pdo->query("
    SELECT COUNT(id) FROM pembayaran_pending 
    WHERE (jenis = 'topup_kantin' OR jenis = 'topup' OR jenis = '' OR jenis IS NULL OR jenis NOT IN ('spp', 'uang_pangkal', 'lainnya')) 
    AND status = 'pending'
")->fetchColumn();

// Ambil Daftar Kelas untuk Filter
$kelasList = $pdo->query("SELECT id, nama_kelas, tingkat FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();

// DAFTAR SALDO SANTRI BESERTA STATISTIK BELANJA PER SANTRI
$querySaldo = "
    SELECT 
        s.id, s.nis, s.nama, s.status AS status_siswa,
        COALESCE(k.nama_kelas, 'Tanpa Kelas') AS nama_kelas,
        COALESCE(ss.saldo, 0) AS saldo,
        ss.updated_at AS saldo_updated_at,
        COALESCE(jajan.total_jajan_keseluruhan, 0) AS total_jajan_keseluruhan,
        COALESCE(jajan.total_transaksi, 0) AS total_transaksi,
        jajan.terakhir_jajan
    FROM siswa s
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN saldo_siswa ss ON s.id = ss.siswa_id
    LEFT JOIN (
        SELECT 
            siswa_id,
            SUM(total_harga) AS total_jajan_keseluruhan,
            COUNT(id) AS total_transaksi,
            MAX(created_at) AS terakhir_jajan
        FROM kantin_transaksi
        WHERE siswa_id IS NOT NULL
        GROUP BY siswa_id
    ) jajan ON s.id = jajan.siswa_id
    WHERE (s.status = 'aktif' OR s.status = 'Aktif' OR s.status IS NULL OR s.status = '')
    ORDER BY ss.saldo DESC, s.nama ASC
";
$daftarSaldoSiswa = $pdo->query($querySaldo)->fetchAll();

// STATISTIK RINGKAS
$totalSaldoKeseluruhan = 0;
$countSiswaBersaldo = 0;
$totalJajanSemuaSiswa = 0;
$totalTransaksiSemuaSiswa = 0;

foreach ($daftarSaldoSiswa as $ds) {
    if ($ds['saldo'] > 0) {
        $totalSaldoKeseluruhan += (float) $ds['saldo'];
        $countSiswaBersaldo++;
    }
    $totalJajanSemuaSiswa += (float) $ds['total_jajan_keseluruhan'];
    $totalTransaksiSemuaSiswa += (int) $ds['total_transaksi'];
}
$totalSiswaAktif = count($daftarSaldoSiswa);

// RIWAYAT TRANSAKSI JAJAN / BELANJA SANTRI (RINCIAN LENGKAP ITEM)
$queryRiwayatJajan = "
    SELECT 
        t.id, t.no_transaksi, t.created_at, t.total_harga, t.metode_bayar,
        s.id AS siswa_id, s.nama AS nama_siswa, s.nis, COALESCE(k.nama_kelas, 'Umum') AS nama_kelas,
        COALESCE(u.nama_lengkap, '-') AS nama_kasir,
        GROUP_CONCAT(CONCAT(COALESCE(m.nama_item, 'Menu'), ' (x', d.jumlah, ')') SEPARATOR ', ') AS item_jajan,
        COALESCE(SUM(d.jumlah), 0) AS total_qty
    FROM kantin_transaksi t
    LEFT JOIN siswa s ON t.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN users u ON t.kasir_user_id = u.id
    LEFT JOIN kantin_transaksi_detail d ON t.id = d.transaksi_id
    LEFT JOIN kantin_menu m ON d.menu_id = m.id
    GROUP BY t.id
    ORDER BY t.created_at DESC
    LIMIT 50
";
$riwayatJajanList = $pdo->query($queryRiwayatJajan)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- MODERN COMPACT TOP BANNER WITH INTEGRATED NAVIGATION -->
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden"
    style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #064e3b 100%);">
    <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-4 p-2.5 d-flex align-items-center justify-content-center flex-shrink-0"
                style="width: 48px; height: 48px; background: rgba(255, 255, 255, 0.18); border: 1px solid rgba(255, 255, 255, 0.25); backdrop-filter: blur(10px);">
                <i class="bi bi-person-lines-fill fs-3 text-white"></i>
            </div>
            <div>
                <h4 class="fw-extrabold text-white mb-0"
                    style="font-family: 'Outfit', sans-serif; letter-spacing: -0.02em;">Daftar Saldo &amp; Riwayat Jajan
                </h4>
                <p class="text-white-50 small mb-0">Monitoring saldo e-wallet kantin dan rincian riwayat jajan santri
                </p>
            </div>
        </div>

        <!-- SEGMENTED NAVIGATION CONTROLLER -->
        <div class="pos-nav-container">
            <a href="index.php" class="pos-nav-link"><i class="bi bi-calculator-fill"></i> Kasir POS</a>
            <a href="topup.php" class="pos-nav-link"><i class="bi bi-wallet2"></i> Top-Up Saldo
                <?= $pendingTopupCount > 0 ? '<span class="badge bg-danger rounded-pill ms-1">' . $pendingTopupCount . '</span>' : '' ?></a>
            <a href="saldo.php" class="pos-nav-link active"><i class="bi bi-people-fill"></i> Saldo Santri</a>
            <a href="menu.php" class="pos-nav-link"><i class="bi bi-egg-fried"></i> Kelola Menu</a>
            <a href="laporan.php" class="pos-nav-link"><i class="bi bi-graph-up-arrow"></i> Laporan</a>
        </div>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4"
        role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?= $flash['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- STATS OVERVIEW ROW -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 p-2.5 d-flex align-items-center justify-content-center flex-shrink-0"
                    style="width: 44px; height: 44px; background: #ecfdf5; color: #059669;">
                    <i class="bi bi-wallet2 fs-4"></i>
                </div>
                <div>
                    <div class="text-muted extra-small fw-bold text-uppercase">TOTAL SALDO SANTRI</div>
                    <h4 class="fw-extrabold text-success mb-0" style="font-family: 'Outfit', sans-serif;">
                        Rp <?= number_format($totalSaldoKeseluruhan, 0, ',', '.') ?>
                    </h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 p-2.5 d-flex align-items-center justify-content-center flex-shrink-0"
                    style="width: 44px; height: 44px; background: #eff6ff; color: #2563eb;">
                    <i class="bi bi-people-fill fs-4"></i>
                </div>
                <div>
                    <div class="text-muted extra-small fw-bold text-uppercase">SANTRI BERSALDO</div>
                    <h4 class="fw-extrabold text-primary mb-0" style="font-family: 'Outfit', sans-serif;">
                        <?= number_format($countSiswaBersaldo) ?> <span class="fs-7 fw-normal text-muted">/
                            <?= $totalSiswaAktif ?> Santri</span>
                    </h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 p-2.5 d-flex align-items-center justify-content-center flex-shrink-0"
                    style="width: 44px; height: 44px; background: #fffbeb; color: #d97706;">
                    <i class="bi bi-cart-check-fill fs-4"></i>
                </div>
                <div>
                    <div class="text-muted extra-small fw-bold text-uppercase">TOTAL JAJAN TERCATAT</div>
                    <h4 class="fw-extrabold text-warning mb-0"
                        style="font-family: 'Outfit', sans-serif; color: #d97706 !important;">
                        Rp <?= number_format($totalJajanSemuaSiswa, 0, ',', '.') ?>
                    </h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 p-2.5 d-flex align-items-center justify-content-center flex-shrink-0"
                    style="width: 44px; height: 44px; background: #fdf2f8; color: #db2777;">
                    <i class="bi bi-receipt-cutoff fs-4"></i>
                </div>
                <div>
                    <div class="text-muted extra-small fw-bold text-uppercase">TOTAL TRANSAKSI</div>
                    <h4 class="fw-extrabold mb-0" style="font-family: 'Outfit', sans-serif; color: #db2777;">
                        <?= number_format($totalTransaksiSemuaSiswa) ?> <span
                            class="fs-7 fw-normal text-muted">Transaksi</span>
                    </h4>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 1: DAFTAR SALDO SANTRI KESELURUHAN -->
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="border-radius: 20px !important;">
    <div
        class="card-header bg-white py-3.5 px-4 d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle p-2.5 d-flex align-items-center justify-content-center"
                style="width: 42px; height: 42px; color: #059669; background: #ecfdf5;">
                <i class="bi bi-people-fill fs-5"></i>
            </div>
            <div>
                <h6 class="fw-extrabold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Daftar Saldo Santri
                    Aktif</h6>
                <p class="text-muted extra-small mb-0">Data saldo e-wallet dan rangkuman belanja masing-masing santri
                </p>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Filter Kelas -->
            <select id="filterKelasSelect" class="form-select form-select-sm bg-light border-0 fw-bold extra-small"
                style="width: 140px;" onchange="filterSaldoTable()">
                <option value="">Semua Kelas</option>
                <?php foreach ($kelasList as $k): ?>
                    <option value="<?= htmlspecialchars($k['nama_kelas']) ?>"><?= htmlspecialchars($k['nama_kelas']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Search -->
            <div class="input-group input-group-sm" style="width: 220px;">
                <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="filterSaldoSiswa" class="form-control bg-light border-0 fw-bold extra-small"
                    placeholder="Ketik nama/NIS..." onkeyup="filterSaldoTable()">
            </div>

            <a href="topup.php"
                class="btn btn-sm btn-success rounded-pill px-3 fw-bold extra-small d-flex align-items-center gap-1 shadow-xs">
                <i class="bi bi-plus-circle-fill"></i> Form Top-Up
            </a>
        </div>
    </div>

    <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
        <table class="table table-hover align-middle mb-0" id="tableSaldoSiswa">
            <thead class="table-light sticky-top" style="z-index: 2;">
                <tr>
                    <th class="ps-4" style="width: 50px;">No</th>
                    <th>NIS</th>
                    <th>Nama Santri</th>
                    <th>Kelas</th>
                    <th>Saldo E-Wallet</th>
                    <th>Total Jajan Tercatat</th>
                    <th>Jajan Terakhir</th>
                    <th class="text-center pe-4">Aksi</th>
                </tr>
            </thead>
            <tbody class="small">
                <?php foreach ($daftarSaldoSiswa as $i => $s):
                    $saldo = (float) $s['saldo'];
                    $totalJajan = (float) $s['total_jajan_keseluruhan'];
                    $jmlTr = (int) $s['total_transaksi'];
                    ?>
                    <tr class="saldo-row" data-kelas="<?= htmlspecialchars($s['nama_kelas']) ?>"
                        data-nama="<?= htmlspecialchars(strtolower($s['nama'])) ?>"
                        data-nis="<?= htmlspecialchars($s['nis']) ?>">
                        <td class="ps-4 text-muted font-monospace"><?= $i + 1 ?></td>
                        <td><code class="text-muted"><?= htmlspecialchars($s['nis']) ?></code></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                    style="width: 32px; height: 32px; font-size: 0.75rem; background: <?= $saldo > 0 ? '#10b981' : '#94a3b8' ?>;">
                                    <?= strtoupper(substr($s['nama'], 0, 1)) ?>
                                </div>
                                <div>
                                    <strong class="text-dark d-block"><?= htmlspecialchars($s['nama']) ?></strong>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border px-2.5 py-1 rounded-pill fw-semibold">
                                <?= htmlspecialchars($s['nama_kelas']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($saldo > 0): ?>
                                <span class="badge px-3 py-1.5 rounded-pill fw-extrabold text-success"
                                    style="background: #ecfdf5; font-size: 0.88rem; font-family: 'Outfit', sans-serif;">
                                    <i class="bi bi-wallet2 me-1"></i> Rp <?= number_format($saldo, 0, ',', '.') ?>
                                </span>
                            <?php else: ?>
                                <span class="badge px-2.5 py-1 rounded-pill text-muted bg-light border fw-normal"
                                    style="font-size: 0.78rem;">
                                    Rp 0
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($totalJajan > 0): ?>
                                <strong class="text-dark">Rp <?= number_format($totalJajan, 0, ',', '.') ?></strong>
                                <small class="text-muted d-block font-monospace">(<?= $jmlTr ?>x belanja)</small>
                            <?php else: ?>
                                <span class="text-muted opacity-50">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($s['terakhir_jajan'])): ?>
                                <span class="text-muted extra-small">
                                    <i
                                        class="bi bi-clock me-1 text-primary"></i><?= date('d M Y H:i', strtotime($s['terakhir_jajan'])) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted extra-small opacity-50">Belum pernah jajan</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center pe-4">
                            <div class="d-inline-flex gap-1 align-items-center">
                                <!-- Top Up Cepat Modal Button -->
                                <button type="button"
                                    class="btn btn-sm btn-outline-success rounded-3 px-2.5 py-1 extra-small fw-bold"
                                    title="Top-Up Saldo Siswa"
                                    onclick="bukaModalTopupCepat(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['nama'])) ?>', '<?= htmlspecialchars(addslashes($s['nama_kelas'])) ?>', '<?= number_format($saldo, 0, ',', '.') ?>')">
                                    <i class="bi bi-plus-lg me-1"></i> Top-Up
                                </button>

                                <!-- Riwayat Belanja Per Siswa Button -->
                                <button type="button"
                                    class="btn btn-sm btn-outline-primary rounded-3 px-2.5 py-1 extra-small fw-bold"
                                    title="Lihat Riwayat Jajan Siswa Ini"
                                    onclick="filterRiwayatBySiswa('<?= htmlspecialchars(addslashes($s['nama'])) ?>')">
                                    <i class="bi bi-clock-history me-1"></i> Riwayat
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- SECTION 2: RIWAYAT TRANSAKSI JAJAN / BELANJA SANTRI TERAKHIR (RINCIAN MENU & ITEM) -->
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" id="sectionRiwayatJajan"
    style="border-radius: 20px !important;">
    <div
        class="card-header bg-white py-3.5 px-4 d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle p-2.5 d-flex align-items-center justify-content-center"
                style="width: 42px; height: 42px; color: #d97706; background: #fffbeb;">
                <i class="bi bi-cart-check-fill fs-5"></i>
            </div>
            <div>
                <h6 class="fw-extrabold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Riwayat Jajan &amp;
                    Belanja Santri</h6>
                <p class="text-muted extra-small mb-0">Rincian menu & item yang dibeli oleh santri di kasir kantin
                    digital</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm" style="width: 220px;">
                <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="searchRiwayatInput" class="form-control bg-light border-0 fw-bold extra-small"
                    placeholder="Cari nama santri / menu..." onkeyup="filterRiwayatJajanTable()">
            </div>
            <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 extra-small fw-bold"
                onclick="resetFilterRiwayat()">
                <i class="bi bi-arrow-clockwise me-1"></i> Reset
            </button>
        </div>
    </div>

    <?php if (empty($riwayatJajanList)): ?>
        <div class="card-body text-center py-5 bg-light">
            <i class="bi bi-basket3 fs-1 text-secondary opacity-40 d-block mb-2"></i>
            <span class="text-muted small fw-bold">Belum ada riwayat transaksi jajan santri.</span>
        </div>
    <?php else: ?>
        <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
            <table class="table table-hover align-middle mb-0 extra-small" id="tableRiwayatJajan">
                <thead class="table-light sticky-top" style="z-index: 2;">
                    <tr>
                        <th class="ps-4">Waktu</th>
                        <th>No. Transaksi</th>
                        <th>Siswa &amp; Kelas</th>
                        <th style="min-width: 260px;">Item / Menu Jajan (Rincian)</th>
                        <th>Total Belanja</th>
                        <th>Metode Bayar</th>
                        <th>Kasir</th>
                        <th class="text-center pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($riwayatJajanList as $rj): ?>
                        <tr class="riwayat-row" data-siswa="<?= htmlspecialchars(strtolower($rj['nama_siswa'] ?? '')) ?>"
                            data-item="<?= htmlspecialchars(strtolower($rj['item_jajan'] ?? '')) ?>"
                            data-nota="<?= htmlspecialchars(strtolower($rj['no_transaksi'])) ?>">
                            <td class="ps-4 text-muted extra-small text-nowrap">
                                <i
                                    class="bi bi-clock me-1 text-primary"></i><?= date('d/m/Y H:i', strtotime($rj['created_at'])) ?>
                            </td>
                            <td>
                                <span
                                    class="badge bg-light text-dark border font-monospace extra-small"><?= htmlspecialchars($rj['no_transaksi']) ?></span>
                            </td>
                            <td>
                                <strong
                                    class="text-dark"><?= htmlspecialchars($rj['nama_siswa'] ?? 'Umum / Non-Siswa') ?></strong><br>
                                <span
                                    class="text-muted extra-small"><?= !empty($rj['nis']) ? 'NIS: ' . htmlspecialchars($rj['nis']) . ' • ' : '' ?><?= htmlspecialchars($rj['nama_kelas']) ?></span>
                            </td>
                            <td>
                                <?php if (!empty($rj['item_jajan'])): ?>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php
                                        $items = explode(', ', $rj['item_jajan']);
                                        foreach ($items as $item):
                                            ?>
                                            <span class="badge bg-light text-dark border px-2 py-1"
                                                style="font-size: 0.73rem; border-color: #e2e8f0 !important;">
                                                <i class="bi bi-egg-fried text-warning me-1"></i><?= htmlspecialchars($item) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted italic">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong class="text-dark fs-6" style="font-family: 'Outfit', sans-serif;">
                                    Rp <?= number_format($rj['total_harga'], 0, ',', '.') ?>
                                </strong>
                            </td>
                            <td>
                                <?php if ($rj['metode_bayar'] === 'saldo'): ?>
                                    <span class="badge bg-success-subtle text-success fw-bold px-2 py-1 rounded-pill">
                                        <i class="bi bi-wallet2 me-1"></i> Saldo E-Wallet
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary fw-bold px-2 py-1 rounded-pill">
                                        <i class="bi bi-cash-stack me-1"></i> Tunai (Cash)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted text-nowrap">
                                <?= htmlspecialchars($rj['nama_kasir']) ?>
                            </td>
                            <td class="text-center pe-4">
                                <div class="d-inline-flex gap-1 align-items-center">
                                    <a href="index.php?print_id=<?= $rj['id'] ?>"
                                        class="btn btn-sm btn-outline-secondary rounded-3 px-2 py-1" title="Cetak Struk">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <form method="POST" action="saldo.php"
                                        onsubmit="return confirm('Apakah Anda yakin ingin menghapus transaksi <?= htmlspecialchars($rj['no_transaksi']) ?>?\n\n- Jika menggunakan Saldo Siswa, saldo sebesar Rp <?= number_format($rj['total_harga'], 0, ',', '.') ?> akan otomatis dikembalikan ke siswa.\n- Stok barang akan otomatis dikembalikan.');"
                                        class="d-inline">
                                        <input type="hidden" name="action" value="delete_transaksi">
                                        <input type="hidden" name="transaksi_id" value="<?= $rj['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-3 px-2 py-1"
                                            title="Hapus Transaksi">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL QUICK TOP-UP SALDO -->
<div class="modal fade" id="modalTopupCepat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-success text-white border-0 py-3">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-wallet2 me-2"></i> Top-Up Saldo Siswa
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form method="POST" action="saldo.php">
                <input type="hidden" name="action" value="topup_quick">
                <input type="hidden" name="siswa_id" id="quick_siswa_id" value="">

                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted extra-small">Santri</div>
                                <h6 class="fw-bold text-dark mb-0" id="quick_nama_siswa">-</h6>
                                <span class="badge bg-white text-dark border extra-small mt-1"
                                    id="quick_kelas_siswa">-</span>
                            </div>
                            <div class="text-end">
                                <div class="text-muted extra-small">Saldo Saat Ini</div>
                                <h6 class="fw-bold text-success mb-0" id="quick_saldo_saat_ini">Rp 0</h6>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label extra-small fw-bold text-muted">Nominal Top-Up (Rp) <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white fw-bold text-success">Rp</span>
                            <input type="text" name="nominal" id="quick_nominal"
                                class="form-control form-control-lg fw-extrabold text-success" placeholder="0" required
                                oninput="formatRupiahInput(this)">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label extra-small fw-bold text-muted mb-2">Pilihan Cepat Nominal</label>
                        <div class="row g-2">
                            <div class="col-3">
                                <button type="button" class="btn btn-outline-success w-100 rounded-3 small fw-bold"
                                    onclick="setQuickNominal(10000)">+10rb</button>
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-outline-success w-100 rounded-3 small fw-bold"
                                    onclick="setQuickNominal(20000)">+20rb</button>
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-outline-success w-100 rounded-3 small fw-bold"
                                    onclick="setQuickNominal(50000)">+50rb</button>
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-outline-success w-100 rounded-3 small fw-bold"
                                    onclick="setQuickNominal(100000)">+100rb</button>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="metode_bayar" value="cash">
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-3"
                        data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                        <i class="bi bi-check-circle-fill me-1"></i> Konfirmasi Top-Up
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function filterSaldoTable() {
        const search = document.getElementById('filterSaldoSiswa').value.toLowerCase().trim();
        const kelas = document.getElementById('filterKelasSelect').value.toLowerCase().trim();
        const rows = document.querySelectorAll('.saldo-row');

        rows.forEach(row => {
            const rowNama = row.getAttribute('data-nama') || '';
            const rowNis = row.getAttribute('data-nis') || '';
            const rowKelas = (row.getAttribute('data-kelas') || '').toLowerCase();

            const matchSearch = !search || rowNama.includes(search) || rowNis.includes(search);
            const matchKelas = !kelas || rowKelas.includes(kelas);

            row.style.display = (matchSearch && matchKelas) ? '' : 'none';
        });
    }

    function filterRiwayatJajanTable() {
        const search = document.getElementById('searchRiwayatInput').value.toLowerCase().trim();
        const rows = document.querySelectorAll('.riwayat-row');

        rows.forEach(row => {
            const rowSiswa = row.getAttribute('data-siswa') || '';
            const rowItem = row.getAttribute('data-item') || '';
            const rowNota = row.getAttribute('data-nota') || '';

            const match = !search || rowSiswa.includes(search) || rowItem.includes(search) || rowNota.includes(search);
            row.style.display = match ? '' : 'none';
        });
    }

    function filterRiwayatBySiswa(namaSiswa) {
        const searchInput = document.getElementById('searchRiwayatInput');
        searchInput.value = namaSiswa;
        filterRiwayatJajanTable();

        // Scroll to riwayat section
        const target = document.getElementById('sectionRiwayatJajan');
        if (target) {
            target.scrollIntoView({ behavior: 'smooth' });
        }
    }

    function resetFilterRiwayat() {
        document.getElementById('searchRiwayatInput').value = '';
        filterRiwayatJajanTable();
    }

    function bukaModalTopupCepat(id, nama, kelas, saldo) {
        document.getElementById('quick_siswa_id').value = id;
        document.getElementById('quick_nama_siswa').textContent = nama;
        document.getElementById('quick_kelas_siswa').textContent = kelas;
        document.getElementById('quick_saldo_saat_ini').textContent = 'Rp ' + saldo;
        document.getElementById('quick_nominal').value = '';

        const modal = new bootstrap.Modal(document.getElementById('modalTopupCepat'));
        modal.show();
    }

    function setQuickNominal(val) {
        const input = document.getElementById('quick_nominal');
        input.value = val.toLocaleString('id-ID');
    }

    function formatRupiahInput(el) {
        let val = el.value.replace(/\D/g, '');
        if (val) {
            el.value = parseInt(val, 10).toLocaleString('id-ID');
        } else {
            el.value = '';
        }
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>