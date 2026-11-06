<?php
/**
 * TOP-UP SALDO KANTIN SISWA
 */
$pageTitle = 'Top-Up Saldo Kantin Siswa';
$activePage = 'kantin';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('kantin');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$flash = getFlash();

// Auto-migrate database tables & columns if needed
require_once __DIR__ . '/../../config/db_auto_sync.php';
runDatabaseAutoSync($pdo);

$activeTab = $_GET['tab'] ?? 'topup';
if (!in_array($activeTab, ['topup', 'saldo', 'jajan'])) {
    $activeTab = 'topup';
}

// Search Siswa
$search = $_GET['search'] ?? '';
$selectedSiswa = null;

if (!empty($_GET['siswa_id'])) {
    $sId = (int) $_GET['siswa_id'];
    $stmtS = $pdo->prepare("
        SELECT s.*, COALESCE(k.nama_kelas, 'Tanpa Kelas') as nama_kelas, COALESCE(ss.saldo, 0) as saldo
        FROM siswa s
        LEFT JOIN kelas k ON s.kelas_id = k.id
        LEFT JOIN saldo_siswa ss ON s.id = ss.siswa_id
        WHERE s.id = :id
    ");
    $stmtS->execute([':id' => $sId]);
    $selectedSiswa = $stmtS->fetch();
}

// DAFTAR SALDO SELURUH SANTRI (E-WALLET)
$daftarSaldoSiswa = $pdo->query("
    SELECT s.id, s.nis, s.nama, COALESCE(k.nama_kelas, 'Tanpa Kelas') as nama_kelas, COALESCE(ss.saldo, 0) as saldo, ss.updated_at
    FROM siswa s
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN saldo_siswa ss ON s.id = ss.siswa_id
    WHERE (s.status = 'aktif' OR s.status = 'Aktif' OR s.status IS NULL OR s.status = '')
    ORDER BY ss.saldo DESC, s.nama ASC
")->fetchAll();

// Active student list for search
if (!empty($search)) {
    $searchLower = mb_strtolower(trim($search));
    $siswaList = array_values(array_filter($daftarSaldoSiswa, function ($s) use ($searchLower) {
        return (strpos(mb_strtolower($s['nama']), $searchLower) !== false) || (strpos($s['nis'], $searchLower) !== false);
    }));
} else {
    $siswaList = $daftarSaldoSiswa;
    usort($siswaList, fn($a, $b) => strcmp($a['nama'], $b['nama']));
}

// Recent Top-Up History
$topupHistory = $pdo->query("
    SELECT t.*, s.nama AS nama_siswa, s.nis, COALESCE(k.nama_kelas, '-') as nama_kelas, COALESCE(u.nama_lengkap, '-') AS nama_petugas
    FROM kantin_topup t
    JOIN siswa s ON t.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY t.created_at DESC
    LIMIT 20
")->fetchAll();

// Fetch Pending Top-Up Requests from Parents
$pendingTopupList = $pdo->query("
    SELECT p.*, COALESCE(s.nama, CONCAT('Siswa (ID: ', p.siswa_id, ')')) AS nama_siswa, COALESCE(s.nis, '-') AS nis, COALESCE(k.nama_kelas, '-') AS nama_kelas
    FROM pembayaran_pending p
    LEFT JOIN siswa s ON p.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    WHERE (p.jenis = 'topup_kantin' OR p.jenis = 'topup' OR p.jenis = '' OR p.jenis IS NULL OR p.jenis NOT IN ('spp', 'uang_pangkal', 'lainnya')) AND p.status = 'pending'
    ORDER BY p.created_at ASC
")->fetchAll();

// Ambil Daftar Kelas untuk Filter
$kelasList = $pdo->query("SELECT id, nama_kelas, tingkat FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();

// STATISTIK SALDO & TRANSAKSI KESELURUHAN
$totalSaldoKeseluruhan = 0;
$countSiswaBersaldo = 0;
foreach ($daftarSaldoSiswa as $ds) {
    if ($ds['saldo'] > 0) {
        $totalSaldoKeseluruhan += (float) $ds['saldo'];
        $countSiswaBersaldo++;
    }
}
$totalSiswaAktif = count($daftarSaldoSiswa);

$statHariIni = $pdo->query("
    SELECT 
        (SELECT COALESCE(SUM(nominal), 0) FROM kantin_topup WHERE DATE(created_at) = CURDATE()) AS topup_hari_ini,
        (SELECT COALESCE(SUM(total_harga), 0) FROM kantin_transaksi WHERE DATE(created_at) = CURDATE()) AS jajan_hari_ini
    ")->fetch();
$totalTopupHariIni = (float) ($statHariIni['topup_hari_ini'] ?? 0);
$totalJajanHariIni = (float) ($statHariIni['jajan_hari_ini'] ?? 0);

// RIWAYAT TRANSAKSI JAJAN / BELANJA SANTRI TERBARU
$stmtJajan = $pdo->query("
    SELECT 
        t.id, t.no_transaksi, t.created_at, t.total_harga, t.metode_bayar,
        s.nama AS nama_siswa, s.nis, COALESCE(k.nama_kelas, 'Umum') AS nama_kelas,
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
    LIMIT 40
");
$riwayatJajanList = $stmtJajan->fetchAll();

// PROSES MANUAL TOP-UP OLEH ADMIN / KASIR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'topup') {
    $siswaId = (int) ($_POST['siswa_id'] ?? 0);
    $nominalRaw = $_POST['nominal'] ?? '0';
    $nominal = (float) str_replace(['.', ','], ['', '.'], $nominalRaw);
    $metodeBayar = $_POST['metode_bayar'] ?? 'cash';
    $keterangan = trim($_POST['keterangan'] ?? '');
    $redirectTab = $_POST['redirect_tab'] ?? 'topup';

    try {
        if ($siswaId <= 0) {
            throw new Exception("Silakan pilih siswa terlebih dahulu.");
        }
        if ($nominal <= 0) {
            throw new Exception("Nominal top-up harus lebih dari Rp 0.");
        }

        // Proses Upload Bukti Transfer jika ada
        $buktiPath = null;
        if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['bukti_transfer'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'jfif', 'heic'];
            if (!in_array($ext, $allowedExts)) {
                throw new Exception("Format bukti transfer tidak didukung. Harap upload file gambar JPG, PNG, WEBP, atau PDF.");
            }
            if ($file['size'] > 10 * 1024 * 1024) {
                throw new Exception("Ukuran file bukti transfer maksimal 10MB.");
            }

            $uploadDir = __DIR__ . '/../../assets/uploads/bukti/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            $filename = 'topup_' . time() . '_' . rand(1000, 9999) . '.' . ($ext === 'heic' ? 'jpg' : $ext);
            $targetPath = $uploadDir . $filename;
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $buktiPath = 'assets/uploads/bukti/' . $filename;
            }
        }

        if (empty($keterangan)) {
            $keterangan = ($metodeBayar === 'transfer')
                ? 'Top-Up Saldo Transfer via Kasir/Admin'
                : 'Top-Up Saldo Tunai via Kasir/Admin';
        }

        require_once __DIR__ . '/../../classes/TransaksiKantin.php';
        $transaksiService = new TransaksiKantin();
        $newSaldo = $transaksiService->topupSaldo($siswaId, $nominal, $userId, $keterangan, $metodeBayar, $buktiPath);

        $stmtS = $pdo->prepare("SELECT nama FROM siswa WHERE id = :id");
        $stmtS->execute([':id' => $siswaId]);
        $namaSiswa = $stmtS->fetchColumn() ?: 'Siswa';

        redirect("topup.php?tab={$redirectTab}&siswa_id={$siswaId}", 'success', '✨ Berhasil top-up saldo sebesar Rp ' . number_format($nominal, 0, ',', '.') . ' untuk ' . htmlspecialchars($namaSiswa) . '! Saldo saat ini: Rp ' . number_format($newSaldo, 0, ',', '.'));

    } catch (Exception $e) {
        redirect("topup.php?tab={$redirectTab}" . ($siswaId > 0 ? '&siswa_id=' . $siswaId : ''), 'danger', $e->getMessage());
    }
}

// PROSES ACC / APPROVE TOP-UP ORTU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'acc_topup') {
    $pendingId = (int) $_POST['pending_id'];
    try {
        $stmtP = $pdo->prepare("SELECT * FROM pembayaran_pending WHERE id = :id AND (jenis = 'topup_kantin' OR jenis = 'topup' OR jenis = '' OR jenis IS NULL OR jenis NOT IN ('spp', 'uang_pangkal', 'lainnya')) AND status = 'pending'");
        $stmtP->execute([':id' => $pendingId]);
        $pending = $stmtP->fetch();

        if (!$pending) {
            throw new Exception("Data pengajuan top-up tidak ditemukan atau sudah diproses.");
        }

        $pdo->beginTransaction();

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

        $stmtCek = $pdo->prepare("SELECT saldo FROM saldo_siswa WHERE siswa_id = :sid FOR UPDATE");
        $stmtCek->execute([':sid' => $pending['siswa_id']]);
        $saldoSebelum = (float) $stmtCek->fetchColumn();
        $saldoSesudah = $saldoSebelum + (float) $pending['nominal'];

        $stmtUp = $pdo->prepare("
            INSERT INTO saldo_siswa (siswa_id, saldo) VALUES (:sid, :n)
            ON DUPLICATE KEY UPDATE saldo = saldo + :n2
        ");
        $stmtUp->execute([':sid' => $pending['siswa_id'], ':n' => $pending['nominal'], ':n2' => $pending['nominal']]);

        try {
            $stmtMutasi = $pdo->prepare("INSERT INTO mutasi_saldo (siswa_id, jenis, jumlah, saldo_sebelum, saldo_sesudah, keterangan, created_by) VALUES (:sid, 'topup', :nom, :seb, :ses, 'Top-up E-Kantin via Portal Ortu (Disetujui Kantin)', :uid)");
            $stmtMutasi->execute([
                ':sid' => $pending['siswa_id'],
                ':nom' => $pending['nominal'],
                ':seb' => $saldoSebelum,
                ':ses' => $saldoSesudah,
                ':uid' => $userId
            ]);
        } catch (Exception $exM) {
        }

        $stmtUpdate = $pdo->prepare("UPDATE pembayaran_pending SET status = 'disetujui', verified_by = :v, verified_at = NOW(), real_payment_id = :rid WHERE id = :id");
        $stmtUpdate->execute([':v' => $userId, ':rid' => $realPaymentId, ':id' => $pendingId]);

        $pdo->commit();
        redirect('topup.php?tab=topup', 'success', '✨ Permohonan top-up Rp ' . number_format($pending['nominal'], 0, ',', '.') . ' berhasil disetujui (ACC) & saldo telah ditambahkan ke siswa!');

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirect('topup.php?tab=topup', 'danger', 'Gagal ACC top-up: ' . $e->getMessage());
    }
}

// PROSES REJECT TOP-UP ORTU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject_topup') {
    $pendingId = (int) $_POST['pending_id'];
    $alasan = trim($_POST['alasan_tolak'] ?? 'Bukti transfer tidak valid/belum masuk.');

    try {
        $stmtP = $pdo->prepare("SELECT * FROM pembayaran_pending WHERE id = :id AND (jenis = 'topup_kantin' OR jenis = 'topup' OR jenis = '' OR jenis IS NULL OR jenis NOT IN ('spp', 'uang_pangkal', 'lainnya')) AND status = 'pending'");
        $stmtP->execute([':id' => $pendingId]);
        $pending = $stmtP->fetch();

        if (!$pending) {
            throw new Exception("Data pengajuan top-up tidak ditemukan.");
        }

        $stmtUpdate = $pdo->prepare("UPDATE pembayaran_pending SET status = 'ditolak', alasan_tolak = :a, verified_by = :v, verified_at = NOW() WHERE id = :id");
        $stmtUpdate->execute([':a' => $alasan, ':v' => $userId, ':id' => $pendingId]);

        redirect('topup.php?tab=topup', 'warning', 'Permohonan top-up telah ditolak.');

    } catch (Exception $e) {
        redirect('topup.php?tab=topup', 'danger', 'Gagal menolak permohonan: ' . $e->getMessage());
    }
}

// PROSES HAPUS TRANSAKSI JAJAN DENGAN REFUND SALDO & STOK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_transaksi') {
    $transaksiId = (int) ($_POST['transaksi_id'] ?? 0);
    $redirectTab = $_POST['redirect_tab'] ?? 'jajan';

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

        $stmtDet = $pdo->prepare("SELECT menu_id, jumlah FROM kantin_transaksi_detail WHERE transaksi_id = :id");
        $stmtDet->execute([':id' => $transaksiId]);
        $details = $stmtDet->fetchAll();

        foreach ($details as $d) {
            if (!empty($d['menu_id']) && $d['jumlah'] > 0) {
                $stmtStok = $pdo->prepare("UPDATE kantin_menu SET stok = stok + :qty WHERE id = :mid");
                $stmtStok->execute([':qty' => $d['jumlah'], ':mid' => $d['menu_id']]);
            }
        }

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

        $stmtDelD = $pdo->prepare("DELETE FROM kantin_transaksi_detail WHERE transaksi_id = :id");
        $stmtDelD->execute([':id' => $transaksiId]);

        $stmtDelT = $pdo->prepare("DELETE FROM kantin_transaksi WHERE id = :id");
        $stmtDelT->execute([':id' => $transaksiId]);

        $pdo->commit();
        redirect("topup.php?tab={$redirectTab}", 'success', "✅ Transaksi {$tr['no_transaksi']} berhasil dihapus dan saldo/stok telah dikembalikan.");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirect("topup.php?tab={$redirectTab}", 'danger', "Gagal menghapus transaksi: " . $e->getMessage());
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .kantin-hero-card {
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #064e3b 100%);
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.25);
    }

    .pos-nav-container {
        display: inline-flex;
        background: rgba(255, 255, 255, 0.12);
        padding: 4px;
        border-radius: 9999px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(8px);
        overflow-x: auto;
        max-width: 100%;
        white-space: nowrap;
    }

    .pos-nav-link {
        color: rgba(255, 255, 255, 0.85);
        font-weight: 700;
        font-size: 0.82rem;
        padding: 6px 16px;
        border-radius: 9999px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
    }

    .pos-nav-link:hover {
        color: #ffffff;
        background: rgba(255, 255, 255, 0.15);
    }

    .pos-nav-link.active {
        color: #0f172a;
        background: #ffffff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }

    .kantin-tab-btn {
        font-family: 'Outfit', sans-serif;
        font-size: 0.88rem;
        font-weight: 700;
        padding: 10px 20px;
        border-radius: 9999px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #475569;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
    }

    .kantin-tab-btn:hover {
        background: #f8fafc;
        color: #1e293b;
        border-color: #cbd5e1;
    }

    .kantin-tab-btn.active {
        background: #1e1b4b;
        color: #ffffff;
        border-color: #1e1b4b;
        box-shadow: 0 4px 12px rgba(30, 27, 75, 0.25);
    }

    .kantin-tab-btn.active i {
        color: #38bdf8 !important;
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

<!-- STATS OVERVIEW ROW -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="text-muted extra-small fw-bold">TOTAL SALDO SANTRI</div>
            <h4 class="fw-extrabold text-teal mb-0" style="color: #0d9488; font-family: 'Outfit', sans-serif;">
                Rp <?= number_format($totalSaldoKeseluruhan, 0, ',', '.') ?>
            </h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="text-muted extra-small fw-bold">TOP-UP HARI INI</div>
            <h4 class="fw-extrabold text-primary mb-0" style="font-family: 'Outfit', sans-serif;">
                +Rp <?= number_format($totalTopupHariIni, 0, ',', '.') ?>
            </h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="text-muted extra-small fw-bold">JAJAN HARI INI</div>
            <h4 class="fw-extrabold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">
                Rp <?= number_format($totalJajanHariIni, 0, ',', '.') ?>
            </h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="text-muted extra-small fw-bold">SANTRI TERDAFTAR</div>
            <h4 class="fw-extrabold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">
                <?= $totalSiswaAktif ?> <small class="fs-6 text-muted fw-normal">Santri</small>
            </h4>
        </div>
    </div>
</div>

<!-- SUB-TAB PILLS BAR (IN-PAGE SWITCHER) -->
<div class="d-flex align-items-center gap-2 mb-4 overflow-auto pb-1" id="kantinSubTabs" role="tablist">
    <button type="button" class="kantin-tab-btn <?= ($activeTab === 'topup') ? 'active' : '' ?>" id="btn-tab-topup"
        data-bs-toggle="pill" data-bs-target="#pane-topup" role="tab" aria-controls="pane-topup"
        aria-selected="<?= ($activeTab === 'topup') ? 'true' : 'false' ?>" onclick="setKantinTab('topup')">
        <i class="bi bi-wallet2 text-primary"></i> Form &amp; Permohonan Top-Up
        <?php if (count($pendingTopupList) > 0): ?>
            <span class="badge bg-danger rounded-pill ms-1 px-2 py-0.5"><?= count($pendingTopupList) ?> Pending</span>
        <?php endif; ?>
    </button>

    <button type="button" class="kantin-tab-btn <?= ($activeTab === 'saldo') ? 'active' : '' ?>" id="btn-tab-saldo"
        data-bs-toggle="pill" data-bs-target="#pane-saldo" role="tab" aria-controls="pane-saldo"
        aria-selected="<?= ($activeTab === 'saldo') ? 'true' : 'false' ?>" onclick="setKantinTab('saldo')">
        <i class="bi bi-people-fill"></i> Daftar Saldo Santri
    </button>

    <button type="button" class="kantin-tab-btn <?= ($activeTab === 'jajan') ? 'active' : '' ?>" id="btn-tab-jajan"
        data-bs-toggle="pill" data-bs-target="#pane-jajan" role="tab" aria-controls="pane-jajan"
        aria-selected="<?= ($activeTab === 'jajan') ? 'true' : 'false' ?>" onclick="setKantinTab('jajan')">
        <i class="bi bi-cart-check-fill text-warning"></i> Riwayat Jajan Santri
    </button>
</div>

<!-- TAB PANES CONTENT -->
<div class="tab-content" id="kantinTabContent">

    <!-- PANE 1: FORM & PERMOHONAN TOP-UP -->
    <div class="tab-pane fade <?= ($activeTab === 'topup') ? 'show active' : '' ?>" id="pane-topup" role="tabpanel"
        aria-labelledby="btn-tab-topup">

        <!-- PERMOHONAN TOP-UP ORTU (PENDING ACC) SECTION -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="border-radius: 20px !important;">
            <div
                class="card-header bg-white py-3.5 px-4 d-flex align-items-center justify-content-between border-bottom">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle p-2.5 bg-primary-subtle text-primary d-flex align-items-center justify-content-center"
                        style="width: 42px; height: 42px;">
                        <i class="bi bi-bell-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="fw-extrabold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Permohonan
                            Top-Up Saldo Kantin dari Orang Tua (Pending ACC)</h6>
                        <p class="text-muted extra-small mb-0">Daftar pengajuan saldo e-wallet kantin melalui transfer
                            bank/e-wallet yang membutuhkan verifikasi</p>
                    </div>
                </div>
                <div>
                    <span
                        class="badge <?= count($pendingTopupList) > 0 ? 'bg-danger text-white' : 'bg-success text-white' ?> fw-extrabold px-3 py-2 rounded-pill extra-small">
                        <?= count($pendingTopupList) ?> Permohonan Pending
                    </span>
                </div>
            </div>

            <?php if (empty($pendingTopupList)): ?>
                <div class="card-body text-center py-4 bg-light">
                    <i class="bi bi-check-circle-fill text-success fs-2 d-block mb-1"></i>
                    <span class="text-muted small fw-bold">Tidak ada permohonan top-up kantin dari orang tua yang pending.
                        Semua permohonan telah diproses!</span>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light extra-small">
                            <tr>
                                <th class="ps-3">Waktu Pengajuan</th>
                                <th>Siswa &amp; Kelas</th>
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
                                        <span class="extra-small text-muted">NIS: <?= htmlspecialchars($pt['nis']) ?> • Kelas
                                            <?= htmlspecialchars($pt['nama_kelas']) ?></span>
                                    </td>
                                    <td>
                                        <strong class="text-success fs-6">Rp
                                            <?= number_format($pt['nominal'], 0, ',', '.') ?></strong>
                                    </td>
                                    <td>
                                        <?php if (!empty($pt['bukti_transfer'])): ?>
                                            <button type="button"
                                                class="btn btn-sm btn-outline-info rounded-pill extra-small fw-bold py-1 px-2"
                                                onclick="previewBukti('<?= htmlspecialchars(getBuktiUrl($pt['bukti_transfer'])) ?>')">
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
                                            <form method="POST" action="topup.php"
                                                onsubmit="return confirm('Apakah Anda yakin ingin menyetujui (ACC) Top-Up Saldo sebesar Rp <?= number_format($pt['nominal'], 0, ',', '.') ?> untuk <?= htmlspecialchars($pt['nama_siswa']) ?>?')">
                                                <input type="hidden" name="action" value="acc_topup">
                                                <input type="hidden" name="pending_id" value="<?= $pt['id'] ?>">
                                                <button type="submit"
                                                    class="btn btn-sm btn-success rounded-3 fw-bold px-3 py-1 text-nowrap shadow-sm">
                                                    <i class="bi bi-check-circle-fill me-1"></i> ACC / Setujui
                                                </button>
                                            </form>

                                            <button type="button"
                                                class="btn btn-sm btn-outline-danger rounded-3 fw-bold px-3 py-1 text-nowrap"
                                                onclick="openRejectModal(<?= $pt['id'] ?>, '<?= htmlspecialchars(addslashes($pt['nama_siswa'])) ?>', '<?= number_format($pt['nominal'], 0, ',', '.') ?>')">
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
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-person-plus-fill text-success me-2"></i>Pilih
                        Siswa &amp; Nominal Top-Up</h6>

                    <!-- SEARCH INPUT -->
                    <form method="GET" action="topup.php" class="mb-3">
                        <input type="hidden" name="tab" value="topup">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control bg-light border-0 fw-bold"
                                placeholder="Ketik Nama Siswa / NIS..." value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-teal px-3 fw-bold"
                                style="background: #0d9488; color: #fff;"><i class="bi bi-search me-1"></i>
                                Cari</button>
                        </div>
                    </form>

                    <form method="POST" action="topup.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="topup">
                        <input type="hidden" name="redirect_tab" value="topup">

                        <div class="mb-3">
                            <label class="form-label extra-small fw-bold text-muted">Pilih Siswa</label>
                            <select name="siswa_id" class="form-select bg-light border-0 fw-bold" required
                                onchange="window.location.href='topup.php?tab=topup&siswa_id='+this.value">
                                <option value="">-- Pilih Siswa --</option>
                                <?php foreach ($siswaList as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= ($selectedSiswa && $selectedSiswa['id'] == $s['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['nama']) ?> (<?= htmlspecialchars($s['nama_kelas']) ?>) -
                                        Saldo: Rp <?= number_format($s['saldo'], 0, ',', '.') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php if ($selectedSiswa): ?>
                            <div
                                class="p-3 rounded-4 bg-success-subtle text-success mb-3 border border-success d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($selectedSiswa['nama']) ?></h6>
                                    <span class="extra-small text-muted">NIS: <?= htmlspecialchars($selectedSiswa['nis']) ?>
                                        • Kelas <?= htmlspecialchars($selectedSiswa['nama_kelas']) ?></span>
                                </div>
                                <div class="text-end">
                                    <span class="extra-small text-muted fw-bold d-block">Saldo Sekarang</span>
                                    <h5 class="fw-extrabold text-success mb-0">Rp
                                        <?= number_format($selectedSiswa['saldo'], 0, ',', '.') ?>
                                    </h5>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label extra-small fw-bold text-muted">Nominal Top-Up (Rp)</label>
                            <input type="text" name="nominal" id="inputNominal"
                                class="form-control form-control-lg bg-light border-0 fw-extrabold text-success"
                                placeholder="0" required oninput="formatRupiahInput(this)">
                        </div>

                        <!-- PRESET AMOUNT BUTTONS -->
                        <div class="mb-3">
                            <label class="form-label extra-small fw-bold text-muted mb-2">Pilihan Cepat Nominal</label>
                            <div class="row g-2">
                                <div class="col-3">
                                    <button type="button" class="btn btn-outline-success w-100 rounded-3 small fw-bold"
                                        onclick="setNominal(10000)">+10.000</button>
                                </div>
                                <div class="col-3">
                                    <button type="button" class="btn btn-outline-success w-100 rounded-3 small fw-bold"
                                        onclick="setNominal(20000)">+20.000</button>
                                </div>
                                <div class="col-3">
                                    <button type="button" class="btn btn-outline-success w-100 rounded-3 small fw-bold"
                                        onclick="setNominal(50000)">+50.000</button>
                                </div>
                                <div class="col-3">
                                    <button type="button" class="btn btn-outline-success w-100 rounded-3 small fw-bold"
                                        onclick="setNominal(100000)">+100.000</button>
                                </div>
                            </div>
                        </div>

                        <!-- METODE PEMBAYARAN -->
                        <div class="mb-3">
                            <label class="form-label extra-small fw-bold text-muted d-block mb-1.5">Metode
                                Pembayaran</label>
                            <div class="d-flex gap-2">
                                <div class="flex-fill">
                                    <input class="btn-check" type="radio" name="metode_bayar" id="metodeCashMain"
                                        value="cash" checked>
                                    <label
                                        class="btn btn-outline-success w-100 rounded-3 py-2 extra-small fw-bold d-flex align-items-center justify-content-center gap-1.5"
                                        for="metodeCashMain">
                                        <i class="bi bi-cash-stack"></i> Tunai (Cash)
                                    </label>
                                </div>
                                <div class="flex-fill">
                                    <input class="btn-check" type="radio" name="metode_bayar" id="metodeTransferMain"
                                        value="transfer">
                                    <label
                                        class="btn btn-outline-primary w-100 rounded-3 py-2 extra-small fw-bold d-flex align-items-center justify-content-center gap-1.5"
                                        for="metodeTransferMain">
                                        <i class="bi bi-bank"></i> Transfer Bank
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- UPLOAD BUKTI TRANSFER -->
                        <div class="mb-3">
                            <label
                                class="form-label extra-small fw-bold text-muted d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-image me-1 text-primary"></i> Upload Bukti Transfer / Struk</span>
                                <span class="text-muted fw-normal" style="font-size: 0.72rem;">Opsional</span>
                            </label>
                            <div class="p-2.5 border rounded-3 bg-light"
                                style="border-style: dashed !important; border-width: 1.5px !important; border-color: #cbd5e1 !important;">
                                <input type="file" name="bukti_transfer" id="inputBuktiMain"
                                    class="form-control form-control-sm bg-white" accept="image/*,application/pdf"
                                    onchange="previewUploadFile(this, 'previewMainImg', 'previewMainWrap')">
                                <div id="previewMainWrap"
                                    class="mt-2 d-none position-relative text-center p-1 bg-white rounded-3 border">
                                    <img id="previewMainImg" src="" alt="Preview Bukti" class="img-fluid rounded-2"
                                        style="max-height: 140px; object-fit: contain;">
                                    <button type="button"
                                        class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 rounded-circle p-0 d-flex align-items-center justify-content-center"
                                        style="width: 22px; height: 22px;"
                                        onclick="clearUploadFile('inputBuktiMain', 'previewMainWrap', 'previewMainImg')"
                                        title="Hapus Bukti">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- CATATAN / KETERANGAN -->
                        <div class="mb-4">
                            <label class="form-label extra-small fw-bold text-muted">Catatan / Keterangan
                                (Opsional)</label>
                            <input type="text" name="keterangan"
                                class="form-control form-control-sm bg-light border-0 fw-medium"
                                placeholder="Misal: Titipan Wali Murid / Transfer via BCA...">
                        </div>

                        <button type="submit" class="btn btn-success w-100 rounded-3 py-2.5 fw-bold shadow-sm"
                            style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                            <i class="bi bi-plus-circle-fill me-2"></i> Proses Top-Up Saldo Siswa
                        </button>
                    </form>
                </div>
            </div>

            <!-- RIGHT COLUMN: RECENT TOP-UP LOGS -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-clock-history text-teal me-2"
                            style="color: #0d9488;"></i>Riwayat Top-Up Terakhir</h6>

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
                                        <th>Metode / Bukti</th>
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
                                            <td class="fw-extrabold text-success">+Rp
                                                <?= number_format($th['nominal'], 0, ',', '.') ?>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-1 flex-wrap">
                                                    <?php if (($th['metode_bayar'] ?? 'cash') === 'transfer'): ?>
                                                        <span
                                                            class="badge bg-primary-subtle text-primary border border-primary border-opacity-25 extra-small px-1.5 py-0.5">
                                                            <i class="bi bi-bank"></i> Transfer
                                                        </span>
                                                    <?php else: ?>
                                                        <span
                                                            class="badge bg-light text-secondary border extra-small px-1.5 py-0.5">
                                                            <i class="bi bi-cash"></i> Tunai
                                                        </span>
                                                    <?php endif; ?>

                                                    <?php if (!empty($th['bukti_transfer'])): ?>
                                                        <button type="button"
                                                            class="btn btn-sm btn-outline-info rounded-pill extra-small py-0 px-2 fw-bold"
                                                            onclick="previewBukti('<?= htmlspecialchars(getBuktiUrl($th['bukti_transfer'])) ?>')"
                                                            title="Lihat Bukti Transfer">
                                                            <i class="bi bi-image"></i> Bukti
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
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

    </div>

    <!-- PANE 2: DAFTAR SALDO SANTRI KESELURUHAN -->
    <div class="tab-pane fade <?= ($activeTab === 'saldo') ? 'show active' : '' ?>" id="pane-saldo" role="tabpanel"
        aria-labelledby="btn-tab-saldo">
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="border-radius: 20px !important;">
            <div
                class="card-header bg-white py-3.5 px-4 d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle p-2.5 d-flex align-items-center justify-content-center"
                        style="width: 42px; height: 42px; color: #059669; background: #ecfdf5;">
                        <i class="bi bi-people-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="fw-extrabold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Daftar Saldo
                            Santri Keseluruhan</h6>
                        <p class="text-muted extra-small mb-0">Monitoring saldo e-wallet seluruh santri aktif</p>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Filter Kelas -->
                    <select id="filterKelasTopup"
                        class="form-select form-select-sm bg-light border-0 fw-bold extra-small" style="width: 140px;"
                        onchange="filterSaldoTopupTable()">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($kelasList as $k): ?>
                            <option value="<?= htmlspecialchars($k['nama_kelas']) ?>">
                                <?= htmlspecialchars($k['nama_kelas']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Search Input -->
                    <div class="input-group input-group-sm" style="width: 240px;">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="filterSaldoSiswaTopup"
                            class="form-control bg-light border-0 fw-bold extra-small"
                            placeholder="Filter nama / kelas / NIS..." onkeyup="filterSaldoTopupTable()">
                    </div>

                    <span class="badge text-white fw-bold px-3 py-2 rounded-pill extra-small"
                        style="background: #059669;">
                        Total: Rp <?= number_format($totalSaldoKeseluruhan, 0, ',', '.') ?>
                    </span>
                </div>
            </div>

            <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
                <table class="table table-hover align-middle mb-0 small" id="tableSaldoSiswaTopup">
                    <thead class="table-light sticky-top" style="z-index: 2;">
                        <tr>
                            <th class="ps-4" style="width: 50px;">No</th>
                            <th>NIS</th>
                            <th>Nama Santri</th>
                            <th>Kelas</th>
                            <th>Status Saldo</th>
                            <th class="text-end">Saldo Saat Ini</th>
                            <th class="text-end pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($daftarSaldoSiswa)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Tidak ada data santri aktif.</td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1;
                            foreach ($daftarSaldoSiswa as $ds): ?>
                                <tr class="row-saldo-item" data-kelas="<?= htmlspecialchars($ds['nama_kelas']) ?>"
                                    data-nama="<?= htmlspecialchars(strtolower($ds['nama'])) ?>"
                                    data-nis="<?= htmlspecialchars($ds['nis']) ?>">
                                    <td class="ps-4 text-muted extra-small"><?= $no++ ?></td>
                                    <td class="extra-small font-monospace fw-bold text-muted cell-nis">
                                        <?= htmlspecialchars($ds['nis']) ?>
                                    </td>
                                    <td class="cell-nama">
                                        <strong class="text-dark"><?= htmlspecialchars($ds['nama']) ?></strong>
                                    </td>
                                    <td class="cell-kelas">
                                        <span
                                            class="badge bg-light text-dark border"><?= htmlspecialchars($ds['nama_kelas']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($ds['saldo'] > 0): ?>
                                            <span
                                                class="badge bg-success-subtle text-success fw-bold px-2 py-1 rounded-pill extra-small">
                                                <i class="bi bi-check-circle-fill me-1"></i> Saldo Aktif
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border px-2 py-1 rounded-pill extra-small">
                                                Rp 0
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <strong class="<?= $ds['saldo'] > 0 ? 'text-success' : 'text-muted' ?> fs-6"
                                            style="font-family: 'Outfit', sans-serif;">
                                            Rp <?= number_format($ds['saldo'], 0, ',', '.') ?>
                                        </strong>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button type="button"
                                            class="btn btn-sm btn-outline-success rounded-pill extra-small fw-bold px-2.5 py-1"
                                            onclick="openQuickTopupModal(<?= $ds['id'] ?>, '<?= htmlspecialchars(addslashes($ds['nama'])) ?>', '<?= htmlspecialchars(addslashes($ds['nama_kelas'])) ?>', '<?= number_format($ds['saldo'], 0, ',', '.') ?>')">
                                            <i class="bi bi-plus-circle me-1"></i> + Top-Up
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PANE 3: RIWAYAT TRANSAKSI JAJAN SANTRI -->
    <div class="tab-pane fade <?= ($activeTab === 'jajan') ? 'show active' : '' ?>" id="pane-jajan" role="tabpanel"
        aria-labelledby="btn-tab-jajan">
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="border-radius: 20px !important;">
            <div
                class="card-header bg-white py-3.5 px-4 d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle p-2.5 d-flex align-items-center justify-content-center"
                        style="width: 42px; height: 42px; color: #d97706; background: #fffbeb;">
                        <i class="bi bi-cart-check-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="fw-extrabold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Riwayat Jajan
                            &amp; Belanja Santri</h6>
                        <p class="text-muted extra-small mb-0">Rincian menu yang dibeli oleh siswa di kasir kantin
                            digital</p>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm" style="width: 220px;">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="searchJajanInput"
                            class="form-control bg-light border-0 fw-bold extra-small"
                            placeholder="Cari nama santri / menu..." onkeyup="filterJajanTable()">
                    </div>
                    <span class="badge bg-light text-dark border fw-bold px-3 py-2 rounded-pill extra-small">
                        <i class="bi bi-clock-history me-1 text-primary"></i> <?= count($riwayatJajanList) ?> Transaksi
                    </span>
                </div>
            </div>

            <?php if (empty($riwayatJajanList)): ?>
                <div class="card-body text-center py-5 bg-light">
                    <i class="bi bi-basket3 fs-1 text-secondary opacity-40 d-block mb-2"></i>
                    <span class="text-muted small fw-bold">Belum ada riwayat transaksi jajan santri.</span>
                </div>
            <?php else: ?>
                <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0 extra-small" id="tableJajanList">
                        <thead class="table-light sticky-top" style="z-index: 2;">
                            <tr>
                                <th class="ps-4">Waktu</th>
                                <th>No. Transaksi</th>
                                <th>Siswa &amp; Kelas</th>
                                <th style="min-width: 250px;">Item / Menu Jajan (Rincian)</th>
                                <th>Total Belanja</th>
                                <th>Metode Bayar</th>
                                <th>Kasir</th>
                                <th class="text-center pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($riwayatJajanList as $rj): ?>
                                <tr class="row-jajan-item"
                                    data-siswa="<?= htmlspecialchars(strtolower($rj['nama_siswa'] ?? '')) ?>"
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
                                                class="btn btn-sm btn-outline-secondary rounded-3 px-2 py-1"
                                                title="Cetak Struk">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                            <form method="POST" action="topup.php"
                                                onsubmit="return confirm('Apakah Anda yakin ingin menghapus transaksi <?= htmlspecialchars($rj['no_transaksi']) ?>?\n\n- Jika menggunakan Saldo Siswa, saldo sebesar Rp <?= number_format($rj['total_harga'], 0, ',', '.') ?> akan otomatis dikembalikan ke siswa.\n- Stok barang akan otomatis dikembalikan.');"
                                                class="d-inline">
                                                <input type="hidden" name="action" value="delete_transaksi">
                                                <input type="hidden" name="transaksi_id" value="<?= $rj['id'] ?>">
                                                <input type="hidden" name="redirect_tab" value="jajan">
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
    </div>

</div>

<!-- MODAL QUICK TOP-UP SALDO DARI TAB DAFTAR SALDO -->
<div class="modal fade" id="modalQuickTopup" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content rounded-4 border-0 p-3 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-wallet2 text-success me-1"></i> Top-Up Saldo
                    Siswa</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="topup.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="topup">
                <input type="hidden" name="redirect_tab" value="saldo">
                <input type="hidden" name="siswa_id" id="quickTopupSiswaId" value="">

                <div class="modal-body py-3">
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted extra-small">Santri</div>
                                <h6 class="fw-bold text-dark mb-0" id="quickTopupNama">-</h6>
                                <span class="badge bg-white text-dark border extra-small mt-1"
                                    id="quickTopupKelas">-</span>
                            </div>
                            <div class="text-end">
                                <div class="text-muted extra-small">Saldo Saat Ini</div>
                                <h6 class="fw-bold text-success mb-0" id="quickTopupSaldo">Rp 0</h6>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label extra-small fw-bold text-muted">Nominal Top-Up (Rp) <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white fw-bold text-success">Rp</span>
                            <input type="text" name="nominal" id="quickTopupNominal"
                                class="form-control form-control-lg fw-extrabold text-success" placeholder="0" required
                                oninput="formatRupiahInput(this)">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label extra-small fw-bold text-muted mb-2">Pilihan Cepat Nominal</label>
                        <div class="row g-2">
                            <div class="col-3">
                                <button type="button" class="btn btn-outline-success w-100 rounded-3 small fw-bold"
                                    onclick="setQuickModalNominal(10000)">+10rb</button>
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-outline-success w-100 rounded-3 small fw-bold"
                                    onclick="setQuickModalNominal(20000)">+20rb</button>
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-outline-success w-100 rounded-3 small fw-bold"
                                    onclick="setQuickModalNominal(50000)">+50rb</button>
                            </div>
                            <div class="col-3">
                                <button type="button" class="btn btn-outline-success w-100 rounded-3 small fw-bold"
                                    onclick="setQuickModalNominal(100000)">+100rb</button>
                            </div>
                        </div>
                    </div>

                    <!-- METODE PEMBAYARAN DI MODAL -->
                    <div class="mb-3">
                        <label class="form-label extra-small fw-bold text-muted d-block mb-1.5">Metode
                            Pembayaran</label>
                        <div class="d-flex gap-2">
                            <div class="flex-fill">
                                <input class="btn-check" type="radio" name="metode_bayar" id="quickMetodeCash"
                                    value="cash" checked>
                                <label
                                    class="btn btn-outline-success w-100 rounded-3 py-1.5 extra-small fw-bold d-flex align-items-center justify-content-center gap-1.5"
                                    for="quickMetodeCash">
                                    <i class="bi bi-cash-stack"></i> Tunai
                                </label>
                            </div>
                            <div class="flex-fill">
                                <input class="btn-check" type="radio" name="metode_bayar" id="quickMetodeTransfer"
                                    value="transfer">
                                <label
                                    class="btn btn-outline-primary w-100 rounded-3 py-1.5 extra-small fw-bold d-flex align-items-center justify-content-center gap-1.5"
                                    for="quickMetodeTransfer">
                                    <i class="bi bi-bank"></i> Transfer
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- UPLOAD BUKTI TRANSFER DI MODAL -->
                    <div class="mb-3">
                        <label
                            class="form-label extra-small fw-bold text-muted d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-image me-1 text-primary"></i> Upload Bukti Transfer</span>
                            <span class="text-muted fw-normal" style="font-size: 0.72rem;">Opsional</span>
                        </label>
                        <div class="p-2 border rounded-3 bg-light"
                            style="border-style: dashed !important; border-width: 1.5px !important; border-color: #cbd5e1 !important;">
                            <input type="file" name="bukti_transfer" id="inputBuktiQuick"
                                class="form-control form-control-sm bg-white" accept="image/*,application/pdf"
                                onchange="previewUploadFile(this, 'previewQuickImg', 'previewQuickWrap')">
                            <div id="previewQuickWrap"
                                class="mt-2 d-none position-relative text-center p-1 bg-white rounded-3 border">
                                <img id="previewQuickImg" src="" alt="Preview Bukti" class="img-fluid rounded-2"
                                    style="max-height: 120px; object-fit: contain;">
                                <button type="button"
                                    class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 rounded-circle p-0 d-flex align-items-center justify-content-center"
                                    style="width: 20px; height: 20px;"
                                    onclick="clearUploadFile('inputBuktiQuick', 'previewQuickWrap', 'previewQuickImg')"
                                    title="Hapus Bukti">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- CATATAN / KETERANGAN DI MODAL -->
                    <div class="mb-2">
                        <label class="form-label extra-small fw-bold text-muted">Catatan (Opsional)</label>
                        <input type="text" name="keterangan"
                            class="form-control form-control-sm bg-light border-0 fw-medium"
                            placeholder="Misal: Titipan Wali Murid / Transfer BCA...">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 fw-bold extra-small me-auto"
                        data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success rounded-3 fw-bold extra-small px-3 shadow-sm">
                        <i class="bi bi-check-circle-fill me-1"></i> Proses Top-Up
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL PREVIEW BUKTI TRANSFER -->
<div class="modal fade" id="modalBuktiPreview" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content rounded-4 border-0 p-3 text-center">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-image me-1 text-info"></i> Bukti Transfer
                    Top-Up Saldo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <img id="imgBuktiPreview" src="" alt="Bukti Transfer" class="img-fluid rounded-3 border shadow-sm mb-2"
                    style="max-height: 420px; object-fit: contain;">
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary w-100 rounded-3 fw-bold"
                    data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TOLAK TOP-UP -->
<div class="modal fade" id="modalTolakTopup" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 p-3">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-x-circle-fill text-danger me-1"></i> Tolak
                    Pengajuan Top-Up Saldo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="topup.php">
                <input type="hidden" name="action" value="reject_topup">
                <input type="hidden" name="pending_id" id="rejectPendingId" value="">
                <div class="modal-body py-3">
                    <div class="p-3 bg-danger-subtle text-danger rounded-3 mb-3 extra-small">
                        Tolak permohonan top-up dari <strong id="rejectSiswaNama"></strong> sebesar <strong
                            id="rejectNominal"></strong>.
                    </div>
                    <div class="mb-2">
                        <label class="form-label extra-small fw-bold text-muted">Alasan Penolakan</label>
                        <textarea name="alasan_tolak" class="form-control bg-light border-0 fw-bold extra-small"
                            rows="3"
                            placeholder="Tuliskan alasan penolakan (misal: Bukti transfer tidak terbaca / belum masuk rekening)..."
                            required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 fw-bold extra-small me-auto"
                        data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger rounded-3 fw-bold extra-small px-3">Tolak
                        Permohonan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function setKantinTab(tabName) {
        const buttons = document.querySelectorAll('.kantin-tab-btn');
        buttons.forEach(btn => btn.classList.remove('active'));

        const activeBtn = document.getElementById('btn-tab-' + tabName);
        if (activeBtn) activeBtn.classList.add('active');

        // Update URL state without full page reload
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.history.replaceState({}, '', url);
    }

    function setNominal(amount) {
        const input = document.getElementById('inputNominal');
        input.value = new Intl.NumberFormat('id-ID').format(amount);
    }

    function setQuickModalNominal(amount) {
        const input = document.getElementById('quickTopupNominal');
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

    function previewUploadFile(input, imgId, wrapId) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();
            reader.onload = function (e) {
                const img = document.getElementById(imgId);
                const wrap = document.getElementById(wrapId);
                if (img) img.src = e.target.result;
                if (wrap) wrap.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        }
    }

    function clearUploadFile(inputId, wrapId, imgId) {
        const input = document.getElementById(inputId);
        const wrap = document.getElementById(wrapId);
        const img = document.getElementById(imgId);
        if (input) input.value = '';
        if (img) img.src = '';
        if (wrap) wrap.classList.add('d-none');
    }

    function openRejectModal(id, nama, nominal) {
        document.getElementById('rejectPendingId').value = id;
        document.getElementById('rejectSiswaNama').innerText = nama;
        document.getElementById('rejectNominal').innerText = 'Rp ' + nominal;
        const modal = new bootstrap.Modal(document.getElementById('modalTolakTopup'));
        modal.show();
    }

    function openQuickTopupModal(id, nama, kelas, saldo) {
        document.getElementById('quickTopupSiswaId').value = id;
        document.getElementById('quickTopupNama').innerText = nama;
        document.getElementById('quickTopupKelas').innerText = 'Kelas ' + kelas;
        document.getElementById('quickTopupSaldo').innerText = 'Rp ' + saldo;
        document.getElementById('quickTopupNominal').value = '';
        clearUploadFile('inputBuktiQuick', 'previewQuickWrap', 'previewQuickImg');

        const modal = new bootstrap.Modal(document.getElementById('modalQuickTopup'));
        modal.show();
    }

    function filterSaldoTopupTable() {
        const input = document.getElementById('filterSaldoSiswaTopup');
        const filter = input.value.toLowerCase().trim();
        const filterKelas = (document.getElementById('filterKelasTopup')?.value || '').toLowerCase().trim();
        const rows = document.querySelectorAll('.row-saldo-item');

        rows.forEach(row => {
            const nama = row.getAttribute('data-nama') || '';
            const nis = row.getAttribute('data-nis') || '';
            const kelas = (row.getAttribute('data-kelas') || '').toLowerCase();

            const matchSearch = !filter || nama.includes(filter) || nis.includes(filter);
            const matchKelas = !filterKelas || kelas.includes(filterKelas);

            if (matchSearch && matchKelas) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function filterJajanTable() {
        const input = document.getElementById('searchJajanInput');
        const filter = input.value.toLowerCase().trim();
        const rows = document.querySelectorAll('.row-jajan-item');

        rows.forEach(row => {
            const siswa = row.getAttribute('data-siswa') || '';
            const item = row.getAttribute('data-item') || '';
            const nota = row.getAttribute('data-nota') || '';

            if (!filter || siswa.includes(filter) || item.includes(filter) || nota.includes(filter)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>