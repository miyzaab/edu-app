<?php
/**
 * POS KASIR KANTIN DIGITAL - E-Wallet & Barcode Scanner (Kamera & Physical Scanner)
 */
$pageTitle = 'POS Kasir Kantin';
$activePage = 'kantin';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('kantin');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$flash = getFlash();
$namaSekolah = getSetting('nama_sekolah', SCHOOL_NAME);

// Search or scan student NIS
$selectedSiswa = null;
$studentBalance = 0;

// All active students for dropdown selector
$siswaList = $pdo->query("
    SELECT s.id, s.nis, s.nama, COALESCE(k.nama_kelas, 'Tanpa Kelas') as nama_kelas, COALESCE(ss.saldo, 0) as saldo,
           COALESCE(ss.limit_tipe, 'none') as limit_tipe,
           COALESCE(ss.limit_nominal, 0) as limit_nominal,
           COALESCE(ss.limit_aktif, 0) as limit_aktif
    FROM siswa s
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN saldo_siswa ss ON s.id = ss.siswa_id
    WHERE (s.status = 'aktif' OR s.status = 'Aktif' OR s.status IS NULL OR s.status = '')
    ORDER BY s.nama ASC
")->fetchAll();

$selectedSiswaLimit = [
    'aktif' => 0,
    'tipe' => 'none',
    'nominal' => 0,
    'terpakai' => 0,
    'sisa' => 0,
    'is_locked' => false
];

$searchNis = $_GET['nis'] ?? '';
if (!empty($searchNis)) {
    foreach ($siswaList as $s) {
        if ($s['nis'] === $searchNis || stripos($s['nama'], $searchNis) !== false) {
            $selectedSiswa = $s;
            $studentBalance = (float) $s['saldo'];

            if (!empty($s['limit_aktif']) && (float) $s['limit_nominal'] > 0 && $s['limit_tipe'] !== 'none') {
                $selectedSiswaLimit['aktif'] = 1;
                $selectedSiswaLimit['tipe'] = $s['limit_tipe'];
                $selectedSiswaLimit['nominal'] = (float) $s['limit_nominal'];

                if ($s['limit_tipe'] === 'harian') {
                    $stSpent = $pdo->prepare("SELECT COALESCE(SUM(total_harga), 0) FROM kantin_transaksi WHERE siswa_id = :sid AND metode_bayar = 'saldo' AND DATE(created_at) = CURDATE()");
                    $stSpent->execute([':sid' => $s['id']]);
                    $selectedSiswaLimit['terpakai'] = (float) $stSpent->fetchColumn();
                } elseif ($s['limit_tipe'] === 'pekanan') {
                    $stSpent = $pdo->prepare("SELECT COALESCE(SUM(total_harga), 0) FROM kantin_transaksi WHERE siswa_id = :sid AND metode_bayar = 'saldo' AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)");
                    $stSpent->execute([':sid' => $s['id']]);
                    $selectedSiswaLimit['terpakai'] = (float) $stSpent->fetchColumn();
                } elseif ($s['limit_tipe'] === 'bulanan') {
                    $stSpent = $pdo->prepare("SELECT COALESCE(SUM(total_harga), 0) FROM kantin_transaksi WHERE siswa_id = :sid AND metode_bayar = 'saldo' AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
                    $stSpent->execute([':sid' => $s['id']]);
                    $selectedSiswaLimit['terpakai'] = (float) $stSpent->fetchColumn();
                }
                $selectedSiswaLimit['sisa'] = max(0, $selectedSiswaLimit['nominal'] - $selectedSiswaLimit['terpakai']);
                $selectedSiswaLimit['is_locked'] = ($selectedSiswaLimit['terpakai'] >= $selectedSiswaLimit['nominal']);
            }
            break;
        }
    }
}

// Menu items
$kategoriFilter = $_GET['kategori'] ?? 'semua';
$menuList = $pdo->query("SELECT * FROM kantin_menu WHERE status = 'tersedia' ORDER BY kategori ASC, nama_item ASC")->fetchAll();

require_once __DIR__ . '/../../classes/TransaksiKantin.php';

// PROSES TRANSAKSI KASIR VIA TRANSAKSI KANTIN CLASS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    $siswaId = !empty($_POST['siswa_id']) ? (int) $_POST['siswa_id'] : null;
    $metodeBayar = $_POST['metode_bayar'] ?? 'saldo'; // saldo / tunai
    $itemsJson = $_POST['items_json'] ?? '[]';
    $cartItems = json_decode($itemsJson, true);

    try {
        $transaksiService = new TransaksiKantin();
        $result = $transaksiService->prosesTransaksi($siswaId, $userId, $cartItems, $metodeBayar);

        redirect('index.php?print_id=' . $result['transaksi_id'], 'success', $result['message']);

    } catch (Exception $e) {
        redirect('index.php', 'danger', $e->getMessage());
    }
}

// Receipt data if print requested
$printData = null;
if (isset($_GET['print_id'])) {
    $pId = (int) $_GET['print_id'];
    $stmtP = $pdo->prepare("
        SELECT t.*, s.nama AS nama_siswa, s.nis, k.nama_kelas, u.nama_lengkap AS nama_kasir
        FROM kantin_transaksi t
        LEFT JOIN siswa s ON t.siswa_id = s.id
        LEFT JOIN kelas k ON s.kelas_id = k.id
        LEFT JOIN users u ON t.kasir_user_id = u.id
        WHERE t.id = :id
    ");
    $stmtP->execute([':id' => $pId]);
    $printHeader = $stmtP->fetch();

    if ($printHeader) {
        $stmtD = $pdo->prepare("
            SELECT d.*, m.nama_item
            FROM kantin_transaksi_detail d
            JOIN kantin_menu m ON d.menu_id = m.id
            WHERE d.transaksi_id = :id
        ");
        $stmtD->execute([':id' => $pId]);
        $printDetails = $stmtD->fetchAll();

        $printData = [
            'header' => $printHeader,
            'details' => $printDetails
        ];
    }
}

$pendingTopupCount = 0;
try {
    $stmtPCount = $pdo->query("SELECT COUNT(*) FROM pembayaran_pending WHERE (jenis = 'topup_kantin' OR jenis = 'topup' OR jenis = '' OR jenis IS NULL OR jenis NOT IN ('spp', 'uang_pangkal', 'lainnya')) AND status = 'pending'");
    $pendingTopupCount = (int) $stmtPCount->fetchColumn();
} catch (Exception $e) {
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Include Html5Qrcode Library for Fast Camera Barcode Scanning (Local first with CDN fallback) -->
<script src="../../assets/js/html5-qrcode.min.js"></script>
<script>
    if (typeof Html5Qrcode === 'undefined') {
        document.write('<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"><\/script>');
    }
</script>

<!-- POS DIGITAL LUXURY SYSTEM -->
<style>
    /* GLOBAL KANTIN DESIGN SYSTEM & MOBILE RESPONSIVENESS */
    .kantin-hero-card {
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 55%, #1e40af 100%);
        border-radius: 20px;
        border: none;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
    }

    .pos-nav-container {
        display: flex;
        align-items: center;
        background: rgba(255, 255, 255, 0.12);
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
        color: rgba(255, 255, 255, 0.85);
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

    /* POS PANEL CARD RESPONSIVE SYSTEM */
    .pos-panel-card {
        background: #ffffff;
        border-radius: 22px;
        border: 1px solid #f1f5f9;
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);
        padding: 1.75rem;
    }

    @media (max-width: 767.98px) {
        .pos-panel-card {
            border-radius: 18px;
            padding: 1.15rem !important;
            margin-bottom: 1rem;
        }

        .kantin-hero-card {
            border-radius: 16px;
            padding: 1.25rem !important;
        }

        .pos-nav-container {
            width: 100%;
            justify-content: flex-start;
        }

        .pos-nav-link {
            padding: 6px 12px;
            font-size: 0.76rem;
        }
    }

    /* SEARCH INPUT GROUP WITH CORNER SAFETY */
    .pos-search-box {
        display: flex;
        align-items: center;
        background: #f8fafc;
        border: 1.5px solid #cbd5e1;
        border-radius: 14px;
        padding: 4px 4px 4px 12px;
        transition: all 0.2s ease;
    }

    .pos-search-box:focus-within {
        border-color: #2563eb;
        background: #ffffff;
        box-shadow: 0 0 0 3.5px rgba(37, 99, 235, 0.15);
    }

    .pos-search-input {
        border: none;
        outline: none;
        background: transparent;
        width: 100%;
        font-weight: 700;
        font-size: 0.92rem;
        color: #0f172a;
        padding: 6px 8px;
    }

    .btn-camera-trigger {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: #ffffff;
        border: none;
        border-radius: 10px;
        padding: 8px 14px;
        font-weight: 800;
        font-size: 0.8rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        white-space: nowrap;
        box-shadow: 0 3px 8px rgba(37, 99, 235, 0.25);
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .btn-camera-trigger:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
    }

    /* CAMERA SCANNER MODAL & MOBILE OPTIMIZATIONS */
    #modalCameraScanner .modal-dialog {
        max-width: 480px;
        margin: 1.25rem auto;
    }

    @media (max-width: 576px) {
        #modalCameraScanner .modal-dialog {
            margin: 0.5rem;
            max-width: 100%;
        }
    }

    #cameraReader {
        width: 100% !important;
        min-height: 260px !important;
        max-height: 380px !important;
        aspect-ratio: 4/3;
        background: #090d16 !important;
        border-radius: 16px !important;
        overflow: hidden !important;
        position: relative !important;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #cameraReader video {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        border-radius: 16px !important;
    }

    #cameraReader canvas {
        display: none !important;
    }

    #cameraReader__scan_region {
        border-radius: 12px;
    }

    /* Scanner laser animation line */
    .scanner-laser-beam {
        position: absolute;
        top: 0;
        left: 5%;
        width: 90%;
        height: 2.5px;
        background: linear-gradient(90deg, transparent, #38bdf8, #2563eb, transparent);
        box-shadow: 0 0 10px #38bdf8, 0 0 20px #2563eb;
        animation: scannerLaserScan 2s infinite ease-in-out;
        z-index: 20;
        pointer-events: none;
    }

    @keyframes scannerLaserScan {
        0% {
            top: 15%;
            opacity: 0.3;
        }

        50% {
            top: 85%;
            opacity: 1;
        }

        100% {
            top: 15%;
            opacity: 0.3;
        }
    }

    .scanner-corner-guide {
        position: absolute;
        width: 24px;
        height: 24px;
        border-color: #38bdf8;
        border-style: solid;
        z-index: 19;
        pointer-events: none;
    }

    .corner-tl {
        top: 18%;
        left: 12%;
        border-width: 3px 0 0 3px;
        border-top-left-radius: 8px;
    }

    .corner-tr {
        top: 18%;
        right: 12%;
        border-width: 3px 3px 0 0;
        border-top-right-radius: 8px;
    }

    .corner-bl {
        bottom: 18%;
        left: 12%;
        border-width: 0 0 3px 3px;
        border-bottom-left-radius: 8px;
    }

    .corner-br {
        bottom: 18%;
        right: 12%;
        border-width: 0 3px 3px 0;
        border-bottom-right-radius: 8px;
    }

    /* LIVE STUDENT SEARCH AUTOCOMPLETE DROPDOWN */
    .pos-student-suggestions-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #ffffff;
        border: 1.5px solid #cbd5e1;
        border-radius: 14px;
        margin-top: 6px;
        max-height: 290px;
        overflow-y: auto;
        z-index: 1060;
        box-shadow: 0 14px 35px rgba(15, 23, 42, 0.16);
    }

    .pos-suggestion-item {
        padding: 10px 14px;
        border-bottom: 1px solid #f1f5f9;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        transition: all 0.15s ease;
    }

    .pos-suggestion-item:last-child {
        border-bottom: none;
    }

    .pos-suggestion-item:hover,
    .pos-suggestion-item.selected {
        background: #eff6ff;
    }

    .pos-suggestion-item:hover .student-name,
    .pos-suggestion-item.selected .student-name {
        color: #2563eb;
    }

    /* MODERN MENU SEARCH BAR */
    .pos-menu-search-wrapper {
        width: 100%;
    }

    .pos-menu-search-box {
        display: flex;
        align-items: center;
        background: #f8fafc;
        border: 1.5px solid #e2e8f0;
        border-radius: 12px;
        padding: 5px 12px;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        gap: 10px;
    }

    .pos-menu-search-box:focus-within {
        border-color: #2563eb;
        background: #ffffff;
        box-shadow: 0 0 0 3.5px rgba(37, 99, 235, 0.12), 0 4px 12px rgba(37, 99, 235, 0.05);
    }

    .pos-menu-search-icon {
        font-size: 1rem;
        color: #2563eb;
        flex-shrink: 0;
        transition: transform 0.2s ease;
    }

    .pos-menu-search-box:focus-within .pos-menu-search-icon {
        transform: scale(1.1);
    }

    .pos-menu-search-input {
        border: none;
        outline: none;
        background: transparent;
        width: 100%;
        font-weight: 600;
        font-size: 0.85rem;
        color: #0f172a;
        padding: 4px 0;
    }

    .pos-menu-search-input::placeholder {
        color: #94a3b8;
        font-weight: 500;
    }

    .btn-clear-menu-search {
        background: none;
        border: none;
        padding: 0;
        color: #94a3b8;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.05rem;
        transition: color 0.15s ease, transform 0.15s ease;
        flex-shrink: 0;
    }

    .btn-clear-menu-search:hover {
        color: #ef4444;
        transform: scale(1.1);
    }

    .menu-item-wrapper {
        transition: transform 0.2s ease, opacity 0.2s ease;
    }

    /* CATEGORY PILLS HORIZONTAL SCROLL FOR MOBILE */
    .pos-cat-scroll {
        display: flex;
        align-items: center;
        gap: 8px;
        overflow-x: auto;
        white-space: nowrap;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        padding-bottom: 2px;
        max-width: 100%;
    }

    .pos-cat-scroll::-webkit-scrollbar {
        display: none;
    }

    .pos-cat-pill {
        border-radius: 50px;
        font-weight: 700;
        font-size: 0.78rem;
        padding: 7px 16px;
        transition: all 0.2s ease;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: #64748b;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
        flex-shrink: 0;
        cursor: pointer;
    }

    .pos-cat-pill:hover {
        background: #ffffff;
        color: #0f172a;
        border-color: #cbd5e1;
    }

    .pos-cat-pill.active {
        background: #2563eb;
        color: #ffffff;
        border-color: #2563eb;
        box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
    }

    /* PRODUCT CARD STYLING */
    .product-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
    }

    .product-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.08);
        border-color: #94a3b8;
    }

    .product-card-body {
        padding: 10px 12px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        flex-grow: 1;
    }

    .btn-add-product {
        background: #2563eb;
        color: #ffffff;
        border: none;
        border-radius: 10px;
        padding: 8px 12px;
        font-size: 0.8rem;
        font-weight: 800;
        width: 100%;
        transition: all 0.18s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 36px;
    }

    .btn-add-product:active {
        transform: scale(0.97);
    }

    .btn-add-product:hover {
        background: #1d4ed8;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }

    /* LUXURY QUANTITY STEPPER PILL */
    .cart-item-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 10px 12px;
        transition: all 0.2s ease;
    }

    .cart-item-card:hover {
        background: #ffffff;
        border-color: #2563eb;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.08);
    }

    .qty-stepper-pill {
        display: inline-flex;
        align-items: center;
        background: #ffffff;
        border: 1.5px solid #cbd5e1;
        border-radius: 10px;
        padding: 2px;
        gap: 2px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    .btn-qty-step {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        border: none !important;
        background: #f1f5f9;
        color: #0f172a;
        font-weight: 800;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.15s ease;
        padding: 0;
        outline: none !important;
        box-shadow: none !important;
    }

    .btn-qty-step:hover,
    .btn-qty-step:active {
        background: #2563eb;
        color: #ffffff;
    }

    .qty-number-badge {
        font-weight: 800;
        font-size: 0.88rem;
        color: #0f172a;
        min-width: 24px;
        text-align: center;
        font-family: 'Outfit', sans-serif;
    }

    /* FLOATING MOBILE CART BAR */
    .mobile-cart-float-bar {
        position: fixed;
        bottom: 14px;
        left: 14px;
        right: 14px;
        background: rgba(15, 23, 42, 0.94);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 16px;
        padding: 12px 16px;
        color: #ffffff;
        z-index: 1030;
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.35);
        display: none;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        transform: translateY(120%);
        opacity: 0;
    }

    .mobile-cart-float-bar.show {
        display: flex;
        transform: translateY(0);
        opacity: 1;
    }
</style>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show rounded-4 shadow-sm border-0 mb-3"
        role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?= $flash['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-3 g-md-4">
    <!-- LEFT COLUMN: SCANNER, STUDENT INFO, MENU CATALOG -->
    <div class="col-lg-7">
        <!-- SCANNER & SEARCH SISWA CARD -->
        <div class="pos-panel-card mb-3 mb-md-4">
            <form onsubmit="handleScanFormSubmit(event)" id="formScanNis" class="row g-2.5 align-items-center mb-3">
                <div class="col-12 col-md-7 position-relative">
                    <label class="form-label extra-small fw-extrabold text-muted mb-1 px-1">
                        <i class="bi bi-qr-code-scan text-primary me-1.5"></i> Scan Barcode NIS / Ketik Nama
                    </label>
                    <div class="pos-search-box">
                        <i class="bi bi-search text-primary fs-6 ms-1"></i>
                        <input type="text" name="nis" id="inputScanNis" class="pos-search-input"
                            placeholder="Scan Barcode NIS / Ketik Nama Siswa..."
                            value="<?= htmlspecialchars($searchNis) ?>" autocomplete="off" autofocus
                            oninput="handleStudentLiveSearch(this.value)" onfocus="handleStudentLiveSearch(this.value)">
                        <button type="button" class="btn-camera-trigger" onclick="openCameraScanner()"
                            title="Buka Kamera Barcode Scanner">
                            <i class="bi bi-camera-fill"></i> <span>Kamera</span>
                        </button>
                    </div>

                    <!-- FLOATING LIVE SEARCH AUTOCOMPLETE DROPDOWN -->
                    <div id="studentSearchSuggestions" class="pos-student-suggestions-dropdown" style="display: none;">
                    </div>
                </div>
                <div class="col-12 col-md-5">
                    <label class="form-label extra-small fw-extrabold text-muted mb-1 px-1">
                        <i class="bi bi-search text-primary me-1"></i> Cari &amp; Pilih dari Daftar
                    </label>
                    <select class="form-select select2 border-0 bg-light fw-bold py-2" id="selectSiswaDropdown"
                        style="border-radius: 14px; font-size: 0.85rem;"
                        data-placeholder="🔍 Cari nama / NIS santri...">
                        <option value="">-- Cari &amp; Pilih Siswa --</option>
                        <?php foreach ($siswaList as $s): ?>
                            <option value="<?= htmlspecialchars($s['nis']) ?>" <?= ($selectedSiswa && $selectedSiswa['id'] == $s['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['nama']) ?> (<?= htmlspecialchars($s['nama_kelas']) ?>) - Rp
                                <?= number_format($s['saldo'], 0, ',', '.') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <div id="siswaInfoCard">
                <?php if ($selectedSiswa): ?>
                    <!-- FINTECH E-WALLET STUDENT CARD WITH RESPONSIVE SPACING -->
                    <div class="p-3 p-md-3.5 rounded-4 text-white position-relative overflow-hidden"
                        style="background: linear-gradient(135deg, #059669 0%, #047857 100%); border-radius: 16px; box-shadow: 0 8px 22px rgba(5, 150, 105, 0.2) !important;">
                        <div class="d-flex align-items-center justify-content-between gap-2.5 flex-wrap">
                            <div class="d-flex align-items-center gap-2.5" style="min-width: 0;">
                                <div class="rounded-circle bg-white text-emerald p-2 d-flex align-items-center justify-content-center fw-bold shadow-sm flex-shrink-0"
                                    style="width: 40px; height: 40px; color: #059669;">
                                    <i class="bi bi-person-fill fs-4"></i>
                                </div>
                                <div style="min-width: 0;">
                                    <h6 class="fw-extrabold text-white mb-0.5 text-truncate"
                                        style="font-size: 0.95rem; font-family: 'Outfit', sans-serif; letter-spacing: -0.01em;">
                                        <?= htmlspecialchars($selectedSiswa['nama']) ?>
                                    </h6>
                                    <div class="extra-small text-white-50 text-truncate">NIS: <strong
                                            class="text-white"><?= htmlspecialchars($selectedSiswa['nis']) ?></strong>
                                        &bull; Kelas <?= htmlspecialchars($selectedSiswa['nama_kelas']) ?></div>
                                </div>
                            </div>
                            <div class="text-start text-md-end flex-shrink-0">
                                <div class="extra-small text-white-50 fw-extrabold uppercase mb-0.5"
                                    style="font-size: 0.65rem; letter-spacing: 0.8px;">SALDO KANTIN ACTIVE</div>
                                <h4 class="fw-extrabold mb-0"
                                    style="font-family: 'Outfit', sans-serif; color: #a7f3d0; font-size: 1.25rem;">Rp
                                    <?= number_format($studentBalance, 0, ',', '.') ?>
                                </h4>
                            </div>
                        </div>

                        <?php if ($selectedSiswaLimit['aktif']): ?>
                            <div
                                class="mt-2.5 pt-2 border-top border-white border-opacity-25 d-flex align-items-center justify-content-between extra-small">
                                <?php if ($selectedSiswaLimit['is_locked']): ?>
                                    <span class="badge bg-danger text-white fw-bold px-2 py-1"><i class="bi bi-lock-fill me-1"></i>
                                        Terkunci (Mencapai Limit)</span>
                                    <span class="text-white fw-semibold">Batas <?= ucfirst($selectedSiswaLimit['tipe']) ?>: Rp
                                        <?= number_format($selectedSiswaLimit['nominal'], 0, ',', '.') ?></span>
                                <?php else: ?>
                                    <span class="text-white-50"><i class="bi bi-shield-lock-fill text-warning me-1"></i> Batas
                                        <?= ucfirst($selectedSiswaLimit['tipe']) ?>: <strong class="text-white">Rp
                                            <?= number_format($selectedSiswaLimit['nominal'], 0, ',', '.') ?></strong></span>
                                    <span class="text-white">Sisa Limit: <strong class="text-warning">Rp
                                            <?= number_format($selectedSiswaLimit['sisa'], 0, ',', '.') ?></strong></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($selectedSiswaLimit['aktif'] && $selectedSiswaLimit['is_locked']): ?>
                        <div
                            class="alert alert-danger p-2.5 rounded-3 extra-small fw-bold mt-2 mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-octagon-fill fs-5 flex-shrink-0"></i>
                            <span>Maaf uang jajan sudah sampai maksimal pembatasan yang dibatasi oleh orang tua.</span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- MENU CATALOG GRID -->
        <div class="pos-panel-card mb-3 mb-md-4">
            <div class="d-flex flex-column gap-2.5 mb-3 px-1">
                <!-- TOP ROW: Title, Counter & Category Pills -->
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center shadow-2xs"
                            style="width: 32px; height: 32px; background: #eff6ff; color: #2563eb;">
                            <i class="bi bi-grid-fill fs-6"></i>
                        </div>
                        <h6 class="fw-extrabold mb-0 text-dark"
                            style="font-family: 'Outfit', sans-serif; font-size: 1rem;">
                            Katalog Menu Kantin
                        </h6>
                        <span id="menuCountBadge"
                            class="badge bg-light text-secondary border rounded-pill px-2.5 py-1 extra-small fw-bold">
                            <?= count($menuList) ?> Menu
                        </span>
                    </div>

                    <!-- CATEGORY PILLS -->
                    <div class="pos-cat-scroll" id="categoryPillContainer">
                        <button type="button" onclick="setKategoriFilter('semua', this)"
                            class="pos-cat-pill <?= $kategoriFilter === 'semua' ? 'active' : '' ?>" data-kat="semua">
                            <i class="bi bi-collection-fill me-1"></i>Semua
                        </button>
                        <button type="button" onclick="setKategoriFilter('makanan', this)"
                            class="pos-cat-pill <?= $kategoriFilter === 'makanan' ? 'active' : '' ?>"
                            data-kat="makanan">
                            🍱 Makanan
                        </button>
                        <button type="button" onclick="setKategoriFilter('minuman', this)"
                            class="pos-cat-pill <?= $kategoriFilter === 'minuman' ? 'active' : '' ?>"
                            data-kat="minuman">
                            🥤 Minuman
                        </button>
                        <button type="button" onclick="setKategoriFilter('jajanan', this)"
                            class="pos-cat-pill <?= $kategoriFilter === 'jajanan' ? 'active' : '' ?>"
                            data-kat="jajanan">
                            🍿 Jajanan
                        </button>
                    </div>
                </div>

                <!-- LIVE SEARCH BAR -->
                <div class="pos-menu-search-wrapper">
                    <div class="pos-menu-search-box">
                        <i class="bi bi-search pos-menu-search-icon"></i>
                        <input type="text" id="inputSearchMenu" class="pos-menu-search-input"
                            placeholder="Cari nama menu kantin (cth: Mie, Teh, Chitato...)" autocomplete="off"
                            oninput="filterMenuCatalog()">
                        <button type="button" id="btnClearMenuSearch" class="btn-clear-menu-search"
                            onclick="clearMenuSearch()" style="display: none;" title="Hapus Pencarian">
                            <i class="bi bi-x-circle-fill"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="row g-2.5 g-md-3" id="menuCatalogGrid">
                <?php if (empty($menuList)): ?>
                    <div class="col-12 text-center py-5 text-muted small">
                        <i class="bi bi-egg-fried fs-1 opacity-40 d-block mb-2 text-secondary"></i>
                        Tidak ada menu kantin yang tersedia.
                    </div>
                <?php endif; ?>

                <!-- NO RESULTS PLACEHOLDER -->
                <div id="noMenuFoundAlert" class="col-12 text-center py-5 text-muted small" style="display: none;">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2 shadow-2xs"
                        style="width: 52px; height: 52px; background: #fef2f2; color: #ef4444;">
                        <i class="bi bi-search fs-4"></i>
                    </div>
                    <h6 class="fw-extrabold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">Menu Tidak
                        Ditemukan</h6>
                    <p class="text-muted extra-small mb-3">Tidak ada menu yang sesuai dengan pencarian "<span
                            id="searchedQueryText" class="fw-bold text-dark"></span>".</p>
                    <button type="button"
                        class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1.5 fw-bold extra-small"
                        onclick="clearMenuSearch()">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Pencarian
                    </button>
                </div>

                <?php foreach ($menuList as $m): ?>
                    <?php
                    $stokBg = ($m['stok'] > 10) ? 'bg-success' : (($m['stok'] > 0) ? 'bg-primary' : 'bg-danger');
                    ?>
                    <div class="col-6 col-sm-6 col-md-4 col-xl-4 menu-item-wrapper"
                        data-kategori="<?= htmlspecialchars(strtolower($m['kategori'])) ?>"
                        data-nama="<?= htmlspecialchars(strtolower($m['nama_item'])) ?>" data-id="<?= (int) $m['id'] ?>">
                        <div class="product-card h-100 position-relative shadow-2xs bg-white">
                            <span
                                class="position-absolute top-0 end-0 m-1.5 badge <?= $stokBg ?> extra-small rounded-pill z-1 shadow-2xs fw-bold px-2 py-0.5"
                                style="font-size: 0.68rem;">
                                Stok: <?= $m['stok'] ?>
                            </span>

                            <!-- MENU PHOTO HEADER -->
                            <?php if (!empty($m['foto']) && file_exists(__DIR__ . '/../../uploads/kantin/' . $m['foto'])): ?>
                                <div style="height: 100px; overflow: hidden;" class="position-relative bg-light border-bottom">
                                    <img src="../../uploads/kantin/<?= htmlspecialchars($m['foto']) ?>"
                                        alt="<?= htmlspecialchars($m['nama_item']) ?>" class="w-100 h-100"
                                        style="object-fit: cover;">
                                </div>
                            <?php else: ?>
                                <div style="height: 95px;"
                                    class="d-flex align-items-center justify-content-center bg-light border-bottom">
                                    <?php if ($m['kategori'] === 'makanan'): ?>
                                        <span class="fs-2">🍱</span>
                                    <?php elseif ($m['kategori'] === 'minuman'): ?>
                                        <span class="fs-2">🥤</span>
                                    <?php else: ?>
                                        <span class="fs-2">🍿</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="product-card-body">
                                <div class="mb-2">
                                    <h6 class="fw-extrabold text-dark mb-1 small text-truncate"
                                        style="font-family: 'Outfit', sans-serif;"
                                        title="<?= htmlspecialchars($m['nama_item']) ?>">
                                        <?= htmlspecialchars($m['nama_item']) ?>
                                    </h6>
                                    <div class="fw-extrabold text-dark"
                                        style="font-size: 0.9rem; font-family: 'Outfit', sans-serif;">Rp
                                        <?= number_format($m['harga'], 0, ',', '.') ?>
                                    </div>
                                </div>
                                <button type="button" class="btn-add-product"
                                    onclick="addToCart(<?= $m['id'] ?>, '<?= addslashes(htmlspecialchars($m['nama_item'])) ?>', <?= $m['harga'] ?>, <?= $m['stok'] ?>)">
                                    <i class="bi bi-plus-lg"></i> Tambah
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN: CART SUMMARY & CHECKOUT TERMINAL -->
    <div class="col-lg-5" id="cartSection">
        <div class="pos-panel-card sticky-top d-flex flex-column" id="cartContainer"
            style="top: 85px; min-height: 480px;">
            <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2.5 px-1">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                        style="width: 34px; height: 34px; background: #eff6ff; color: #2563eb;">
                        <i class="bi bi-cart3 fs-5"></i>
                    </div>
                    <h6 class="fw-extrabold text-dark mb-0" style="font-family: 'Outfit', sans-serif; font-size: 1rem;">
                        Keranjang Kasir</h6>
                </div>
                <button type="button" class="btn btn-link text-danger text-decoration-none p-0 extra-small fw-extrabold"
                    onclick="clearCart()">
                    <i class="bi bi-trash3-fill me-1"></i> Kosongkan
                </button>
            </div>

            <!-- CART ITEMS LIST -->
            <div id="cartItemsList" class="flex-grow-1 overflow-auto pe-1 mb-3" style="max-height: 260px;">
                <div class="text-center py-5 text-muted small">
                    <i class="bi bi-cart-x fs-1 d-block mb-1 text-secondary opacity-30"></i>
                    Keranjang masih kosong.<br>Klik item dari katalog untuk menambah.
                </div>
            </div>

            <!-- CHECKOUT FORM & TERMINAL TOTAL -->
            <form method="POST" action="index.php" onsubmit="return validateCheckout()" class="mt-auto">
                <input type="hidden" name="action" value="checkout">
                <input type="hidden" name="siswa_id" id="siswaIdInput"
                    value="<?= $selectedSiswa ? $selectedSiswa['id'] : '' ?>">
                <input type="hidden" name="items_json" id="itemsJsonInput" value="[]">

                <div class="p-3.5 rounded-4 mb-3 border" style="background: #f8fafc; border-color: #e2e8f0 !important;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted extra-small fw-extrabold" style="letter-spacing: 0.5px;">TOTAL
                            BELANJA</span>
                        <h3 class="fw-extrabold text-dark mb-0" id="totalHargaText"
                            style="font-family: 'Outfit', sans-serif; font-size: 1.6rem;">Rp 0</h3>
                    </div>

                    <!-- METODE PEMBAYARAN TOGGLE -->
                    <label class="form-label extra-small fw-extrabold text-muted uppercase mb-2"><i
                            class="bi bi-credit-card-2-front me-1 text-primary"></i> Metode Pembayaran</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="metode_bayar" id="bayarSaldo" value="saldo"
                                <?= $selectedSiswa ? 'checked' : 'disabled' ?>>
                            <label
                                class="btn btn-outline-primary w-100 rounded-3 py-2 extra-small fw-extrabold d-flex align-items-center justify-content-center gap-1.5"
                                for="bayarSaldo">
                                <i class="bi bi-wallet2"></i> Saldo Siswa
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="metode_bayar" id="bayarTunai" value="tunai"
                                <?= !$selectedSiswa ? 'checked' : '' ?>>
                            <label
                                class="btn btn-outline-secondary w-100 rounded-3 py-2 extra-small fw-extrabold d-flex align-items-center justify-content-center gap-1.5"
                                for="bayarTunai">
                                <i class="bi bi-cash-stack"></i> Tunai (Cash)
                            </label>
                        </div>
                    </div>
                </div>

                <button type="submit" id="btnCheckout"
                    class="btn btn-success w-100 rounded-3 py-3 fw-extrabold text-uppercase shadow-sm" disabled
                    style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none; font-size: 0.92rem; letter-spacing: 0.5px;">
                    <i class="bi bi-cart-check-fill me-2 fs-6"></i> Proses Pembayaran Kasir
                </button>
            </form>
        </div>
    </div>
    <!-- MODAL LIVE CAMERA BARCODE SCANNER (INSTANT OPEN, ZERO LOADING DELAY) -->
    <div class="modal" id="modalCameraScanner" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-dark text-white p-3 border-0">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-1.5 rounded-3 bg-primary bg-opacity-25 text-primary">
                            <i class="bi bi-qr-code-scan fs-6"></i>
                        </div>
                        <div>
                            <h6 class="modal-title fw-bold mb-0">Scanner Barcode / QR Siswa</h6>
                            <span class="extra-small text-muted">Arahkan kamera ke kartu santri</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" onclick="closeCameraScanner()"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-3 bg-light">
                    <!-- CAMERA SELECTOR & TORCH BUTTON -->
                    <div class="d-flex align-items-center justify-content-between mb-2.5 gap-2">
                        <div class="d-flex align-items-center gap-1.5 flex-grow-1 overflow-hidden">
                            <i class="bi bi-webcam text-primary extra-small"></i>
                            <select id="cameraSelect"
                                class="form-select form-select-sm border-0 bg-white shadow-sm fw-bold extra-small text-truncate"
                                onchange="switchCamera(this.value)">
                                <option value="environment">⚡ Kamera Belakang (Otomatis)</option>
                            </select>
                        </div>
                        <button type="button" id="btnToggleFlash"
                            class="btn btn-sm btn-white bg-white shadow-sm fw-bold extra-small px-2.5 d-none"
                            onclick="toggleScannerFlash()" title="Nyalakan Lampu Flash">
                            <i class="bi bi-lightning-fill text-warning"></i> <span id="lblToggleFlash">Flash</span>
                        </button>
                    </div>

                    <!-- SCANNER VIEWPORT CONTAINER -->
                    <div id="cameraReader" class="position-relative">
                        <div id="scannerPreloader"
                            class="d-flex flex-column align-items-center justify-content-center text-white py-5"
                            style="display: none;">
                            <div class="spinner-border text-primary mb-2" role="status"
                                style="width: 2rem; height: 2rem;"></div>
                            <span class="extra-small text-white-50 fw-semibold">Menghubungkan sensor kamera...</span>
                        </div>
                        <div class="scanner-laser-beam" id="scannerLaser"></div>
                        <div class="scanner-corner-guide corner-tl" id="scannerCornerTL"></div>
                        <div class="scanner-corner-guide corner-tr" id="scannerCornerTR"></div>
                        <div class="scanner-corner-guide corner-bl" id="scannerCornerBL"></div>
                        <div class="scanner-corner-guide corner-br" id="scannerCornerBR"></div>
                    </div>

                    <!-- SCAN STATUS ALERT -->
                    <div id="scannerStatusBox"
                        class="mt-2.5 p-2 rounded-3 bg-white border text-dark extra-small d-flex align-items-center justify-content-center gap-2 fw-bold shadow-xs">
                        <i class="bi bi-camera-video-fill text-success" id="scannerStatusIcon"></i>
                        <span id="scannerStatusText" class="text-success fw-bold">Kamera aktif! Arahkan ke barcode NIS
                            santri...</span>
                    </div>

                    <div class="mt-2 d-flex justify-content-between align-items-center extra-small text-muted px-1">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="chkAutoCloseScanner" checked>
                            <label class="form-check-label extra-small fw-semibold" for="chkAutoCloseScanner">Auto Tutup
                                setelah scan</label>
                        </div>
                        <span class="badge bg-success bg-opacity-10 text-success fw-bold"><i
                                class="bi bi-lightning-charge-fill me-1"></i>Instant Ready</span>
                    </div>
                </div>
                <div class="modal-footer p-2 bg-white border-0">
                    <button type="button" class="btn btn-secondary btn-sm w-100 rounded-3 fw-bold py-2"
                        onclick="closeCameraScanner()">
                        Tutup Scanner
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL PRINT RECEIPT -->
    <?php if ($printData): ?>
        <div class="modal fade show d-block" id="modalStruk" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header bg-dark text-white p-3">
                        <h6 class="modal-title fw-bold mb-0"><i class="bi bi-receipt me-2"></i>Struk Kasir Kantin</h6>
                        <a href="index.php" class="btn-close btn-close-white"></a>
                    </div>
                    <div class="modal-body p-4 text-center bg-white" id="receiptPrintArea">
                        <h6 class="fw-extrabold mb-0"><?= htmlspecialchars($namaSekolah) ?></h6>
                        <div class="extra-small text-muted mb-2">KANTIN SEKOLAH DIGITAL</div>
                        <div class="border-top border-bottom py-1 my-2 extra-small text-start">
                            <div>No: <strong><?= htmlspecialchars($printData['header']['no_transaksi']) ?></strong></div>
                            <div>Waktu: <?= date('d/m/Y H:i', strtotime($printData['header']['created_at'])) ?></div>
                            <div>Siswa: <?= htmlspecialchars($printData['header']['nama_siswa'] ?? 'Umum / Cash') ?></div>
                            <div>Kasir: <?= htmlspecialchars($printData['header']['nama_kasir']) ?></div>
                        </div>

                        <table class="w-100 extra-small text-start mb-2">
                            <?php foreach ($printData['details'] as $d): ?>
                                <tr>
                                    <td><?= htmlspecialchars($d['nama_item']) ?> x<?= $d['jumlah'] ?></td>
                                    <td class="text-end fw-bold">Rp <?= number_format($d['subtotal'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>

                        <div class="border-top pt-2 d-flex justify-content-between align-items-center fw-bold">
                            <span>TOTAL</span>
                            <span class="fs-6 text-teal">Rp
                                <?= number_format($printData['header']['total_harga'], 0, ',', '.') ?></span>
                        </div>
                        <div class="extra-small text-muted mt-1">Bayar:
                            <?= strtoupper($printData['header']['metode_bayar']) ?>
                        </div>
                        <div class="extra-small text-muted mt-3">Terima kasih & Selamat Menikmati! 😊</div>
                    </div>
                    <div class="modal-footer p-2 bg-light">
                        <button type="button" class="btn btn-primary btn-sm w-100 rounded-3 fw-bold"
                            onclick="window.print()">
                            <i class="bi bi-printer-fill me-1"></i> Cetak Struk
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- FLOATING MOBILE CART BAR (MOBILE CHECKOUT SHORTCUT) -->
    <div id="mobileCartFloatingBar" class="mobile-cart-float-bar d-lg-none">
        <div class="d-flex align-items-center gap-2 overflow-hidden">
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary text-white flex-shrink-0"
                style="width: 36px; height: 36px;">
                <i class="bi bi-bag-check-fill fs-6"></i>
            </div>
            <div class="text-truncate">
                <div class="fw-extrabold text-white mb-0"
                    style="font-size: 0.88rem; font-family: 'Outfit', sans-serif;">
                    <span id="mobileCartCount">0</span> Item &bull; <span id="mobileCartTotal"
                        style="color: #6ee7b7;">Rp 0</span>
                </div>
                <div class="extra-small text-white-50 text-truncate">Ketuk untuk lihat keranjang &amp; bayar</div>
            </div>
        </div>
        <button type="button" class="btn btn-success btn-sm fw-extrabold px-3 py-2 rounded-3 shadow-sm flex-shrink-0"
            onclick="scrollToCart()" style="font-size: 0.82rem; background: #10b981; border: none;">
            Bayar <i class="bi bi-arrow-right-short ms-1"></i>
        </button>
    </div>

    <script>
        let cart = [];
        let studentBalance = <?= (float) $studentBalance ?>;
        let currentCategoryFilter = '<?= htmlspecialchars($kategoriFilter) ?>' || 'semua';

        // In-memory student cache for 0ms instantaneous barcode matching
        const allSiswaList = <?= json_encode(array_map(function ($s) {
            return [
                'id' => (int) $s['id'],
                'nis' => (string) $s['nis'],
                'nama' => $s['nama'],
                'nama_kelas' => $s['nama_kelas'],
                'saldo' => (float) $s['saldo'],
                'formatted_saldo' => 'Rp ' . number_format($s['saldo'], 0, ',', '.')
            ];
        }, $siswaList), JSON_UNESCAPED_UNICODE) ?>;

        const siswaByNisMap = {};
        allSiswaList.forEach(s => {
            if (s.nis) {
                siswaByNisMap[s.nis.trim().toLowerCase()] = s;
            }
        });

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        let currentStudentData = null;

        // --- ULTRA-FAST BARCODE & STUDENT SELECTION (WITH REAL-TIME LIMIT SYNC) ---
        function selectStudentByNis(query) {
            if (!query) return;
            const cleanQuery = query.trim().toLowerCase();

            // Quick display from local cache if found (for instant UI feedback)
            let s = siswaByNisMap[cleanQuery];
            if (!s) {
                s = allSiswaList.find(item =>
                    (item.nis && item.nis.toLowerCase() === cleanQuery) ||
                    (item.nama && item.nama.toLowerCase() === cleanQuery) ||
                    (item.nis && item.nis.toLowerCase().includes(cleanQuery)) ||
                    (item.nama && item.nama.toLowerCase().includes(cleanQuery))
                );
            }
            if (s) {
                applyStudentData(s);
            }

            // Always sync live from API to ensure parent's spending limit and today's total spent are 100% real-time
            fetch('../../api/get-siswa-kantin.php?nis=' + encodeURIComponent(query))
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.siswa) {
                        applyStudentData(data.siswa);
                        playBeepSound();
                    } else if (!s) {
                        alert(data.message || 'Siswa dengan NIS "' + query + '" tidak ditemukan!');
                    }
                })
                .catch(() => {
                    if (!s) alert('Gagal mencari data siswa.');
                });
        }

        function applyStudentData(s) {
            currentStudentData = s;
            studentBalance = parseFloat(s.saldo || 0);

            // Sync NIS Input
            const inputNis = document.getElementById('inputScanNis');
            if (inputNis) {
                inputNis.value = s.nis;
            }

            // Sync Dropdown (and Select2 if initialized)
            const drop = document.getElementById('selectSiswaDropdown');
            if (drop) {
                drop.value = s.nis;
                if (window.jQuery && $(drop).hasClass('select2-hidden-accessible')) {
                    $(drop).val(s.nis).trigger('change.select2');
                }
            }

            // Close suggestions popup
            const suggestions = document.getElementById('studentSearchSuggestions');
            if (suggestions) {
                suggestions.style.display = 'none';
            }

            // Update Form Hidden Fields & Toggle Saldo Payment
            const siswaIdInput = document.getElementById('siswaIdInput');
            if (siswaIdInput) {
                siswaIdInput.value = s.id;
            }

            const rSaldo = document.getElementById('bayarSaldo');
            const rTunai = document.getElementById('bayarTunai');
            const isLimitReached = !!s.is_limit_reached;

            if (rSaldo) {
                if (isLimitReached) {
                    rSaldo.disabled = true;
                    rSaldo.checked = false;
                    if (rTunai) rTunai.checked = true;
                } else {
                    rSaldo.disabled = false;
                    rSaldo.checked = true;
                }
            }

            // Smoothly render Student Info Card with modern Limit Status
            const infoCard = document.getElementById('siswaInfoCard');
            if (infoCard) {
                const formattedSaldo = s.formatted_saldo || ('Rp ' + new Intl.NumberFormat('id-ID').format(s.saldo));
                const bgGradient = isLimitReached
                    ? 'linear-gradient(135deg, #b91c1c 0%, #991b1b 100%)'
                    : 'linear-gradient(135deg, #059669 0%, #047857 100%)';
                const shadowColor = isLimitReached ? 'rgba(185, 28, 28, 0.25)' : 'rgba(5, 150, 105, 0.22)';

                let limitHtml = '';
                if (isLimitReached) {
                    limitHtml = `
                    <div class="mt-2.5 p-2.5 rounded-3 bg-black bg-opacity-25 border border-white border-opacity-25 text-white d-flex align-items-start gap-2 animate__animated animate__shakeX">
                        <i class="bi bi-exclamation-triangle-fill text-warning fs-5 flex-shrink-0 mt-0.5"></i>
                        <div style="font-size: 0.82rem; line-height: 1.35;">
                            <div class="fw-bold text-warning mb-0.5">TERKUNCI: BATAS JAJAN TERCAPAI</div>
                            <div>Maaf uang jajan sudah sampai maksimal pembatasan yang dibatasi oleh orang tua.</div>
                            <div class="extra-small text-white-50 mt-1">
                                Batas: <strong>Rp ${new Intl.NumberFormat('id-ID').format(s.limit_nominal || 0)}</strong> &bull; Terpakai: <strong>Rp ${new Intl.NumberFormat('id-ID').format(s.limit_spent || 0)}</strong>
                            </div>
                        </div>
                    </div>`;
                } else if (s.limit_aktif && s.limit_tipe && s.limit_tipe !== 'none') {
                    limitHtml = `
                    <div class="mt-2.5 p-2 px-2.5 rounded-3 bg-white bg-opacity-15 d-flex align-items-center justify-content-between text-white flex-wrap gap-1" style="font-size: 0.78rem;">
                        <div><i class="bi bi-shield-lock-fill me-1 text-warning"></i>${escapeHtml(s.limit_label || 'Pembatasan Aktif')}</div>
                        <div>Sisa Limit: <strong class="text-warning">Rp ${new Intl.NumberFormat('id-ID').format(s.limit_remaining || 0)}</strong></div>
                    </div>`;
                }

                infoCard.innerHTML = `
                <div class="p-3 p-md-3.5 rounded-4 text-white position-relative overflow-hidden shadow-sm"
                    style="background: ${bgGradient}; border-radius: 16px; box-shadow: 0 8px 22px ${shadowColor} !important; transition: all 0.3s ease;">
                    <div class="d-flex align-items-center justify-content-between gap-2.5 flex-wrap">
                        <div class="d-flex align-items-center gap-2.5" style="min-width: 0;">
                            <div class="rounded-circle bg-white p-2 d-flex align-items-center justify-content-center fw-bold shadow-sm flex-shrink-0"
                                style="width: 40px; height: 40px; color: ${isLimitReached ? '#dc2626' : '#059669'};">
                                <i class="bi ${isLimitReached ? 'bi-lock-fill' : 'bi-person-fill'} fs-4"></i>
                            </div>
                            <div style="min-width: 0;">
                                <h6 class="fw-extrabold text-white mb-0.5 text-truncate"
                                    style="font-size: 0.95rem; font-family: 'Outfit', sans-serif; letter-spacing: -0.01em;">
                                    ${escapeHtml(s.nama)}
                                </h6>
                                <div class="extra-small text-white-50 text-truncate">NIS: <strong
                                        class="text-white">${escapeHtml(s.nis)}</strong>
                                    &bull; Kelas ${escapeHtml(s.nama_kelas)}</div>
                            </div>
                        </div>
                        <div class="text-start text-md-end flex-shrink-0">
                            <div class="extra-small text-white-50 fw-extrabold uppercase mb-0.5"
                                style="font-size: 0.65rem; letter-spacing: 0.8px;">SALDO KANTIN ACTIVE</div>
                            <h4 class="fw-extrabold mb-0"
                                style="font-family: 'Outfit', sans-serif; color: ${isLimitReached ? '#fecaca' : '#a7f3d0'}; font-size: 1.25rem;">
                                ${formattedSaldo}
                            </h4>
                        </div>
                    </div>
                    ${limitHtml}
                </div>`;
            }
        }

        function clearSelectedStudent() {
            currentStudentData = null;
            studentBalance = 0;
            const inputNis = document.getElementById('inputScanNis');
            if (inputNis) inputNis.value = '';

            const drop = document.getElementById('selectSiswaDropdown');
            if (drop) {
                drop.value = '';
                if (window.jQuery && $(drop).hasClass('select2-hidden-accessible')) {
                    $(drop).val('').trigger('change.select2');
                }
            }

            document.getElementById('siswaIdInput').value = '';
            const rSaldo = document.getElementById('bayarSaldo');
            if (rSaldo) {
                rSaldo.disabled = true;
                rSaldo.checked = false;
            }
            const rTunai = document.getElementById('bayarTunai');
            if (rTunai) {
                rTunai.checked = true;
            }

            const infoCard = document.getElementById('siswaInfoCard');
            if (infoCard) {
                infoCard.innerHTML = '';
            }
        }

        // --- LIVE STUDENT SEARCH AUTOCOMPLETE ---
        function handleStudentLiveSearch(query) {
            const box = document.getElementById('studentSearchSuggestions');
            if (!box) return;

            const q = (query || '').trim().toLowerCase();
            if (q.length === 0) {
                box.style.display = 'none';
                box.innerHTML = '';
                return;
            }

            const matches = allSiswaList.filter(s =>
                (s.nis && s.nis.toLowerCase().includes(q)) ||
                (s.nama && s.nama.toLowerCase().includes(q)) ||
                (s.nama_kelas && s.nama_kelas.toLowerCase().includes(q))
            ).slice(0, 8);

            if (matches.length === 0) {
                box.innerHTML = `
                    <div class="p-3 text-center text-muted extra-small">
                        <i class="bi bi-person-x fs-4 d-block mb-1 text-secondary opacity-50"></i>
                        Tidak ada siswa yang cocok dengan "<strong>${escapeHtml(query)}</strong>"
                    </div>
                `;
                box.style.display = 'block';
                return;
            }

            let html = '';
            matches.forEach(s => {
                html += `
                    <div class="pos-suggestion-item" onclick="chooseStudentSuggestion('${escapeHtml(s.nis)}')">
                        <div class="d-flex align-items-center gap-2.5 overflow-hidden">
                            <div class="rounded-circle d-flex align-items-center justify-content-center bg-light text-primary flex-shrink-0"
                                style="width: 32px; height: 32px; font-size: 0.85rem; font-weight: 800;">
                                <i class="bi bi-person-fill"></i>
                            </div>
                            <div class="text-truncate">
                                <div class="student-name text-truncate fw-bold text-dark" style="font-size: 0.88rem;">${escapeHtml(s.nama)}</div>
                                <div class="student-meta extra-small text-muted">NIS: <strong class="text-dark">${escapeHtml(s.nis)}</strong> &bull; Kelas ${escapeHtml(s.nama_kelas)}</div>
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <div class="extra-small text-muted" style="font-size: 0.65rem;">Saldo Active</div>
                            <div class="student-saldo fw-bold text-emerald" style="color: #059669; font-size: 0.85rem;">${s.formatted_saldo}</div>
                        </div>
                    </div>
                `;
            });

            box.innerHTML = html;
            box.style.display = 'block';
        }

        function chooseStudentSuggestion(nis) {
            const box = document.getElementById('studentSearchSuggestions');
            if (box) box.style.display = 'none';
            selectStudentByNis(nis);
        }

        // Close suggestions on click outside
        document.addEventListener('click', function (e) {
            const box = document.getElementById('studentSearchSuggestions');
            const input = document.getElementById('inputScanNis');
            if (box && input && !box.contains(e.target) && e.target !== input) {
                box.style.display = 'none';
            }
        });

        // Initialize Select2 on document ready
        document.addEventListener('DOMContentLoaded', function () {
            if (window.jQuery && typeof $.fn.select2 === 'function') {
                $('#selectSiswaDropdown').select2({
                    theme: 'bootstrap-5',
                    placeholder: '🔍 Cari nama / NIS siswa...',
                    allowClear: true,
                    width: '100%'
                }).on('select2:select', function (e) {
                    if (this.value) {
                        selectStudentByNis(this.value);
                    }
                }).on('select2:clear', function (e) {
                    clearSelectedStudent();
                });
            }
        });

        function handleScanFormSubmit(e) {
            e.preventDefault();
            const input = document.getElementById('inputScanNis');
            if (input && input.value) {
                selectStudentByNis(input.value);
            }
        }

        // --- HARDWARE BARCODE SCANNER GLOBAL LISTENER ---
        // Automatically captures laser barcode scanners from anywhere on page
        let scannerBuffer = '';
        let lastKeypressTime = 0;

        document.addEventListener('keydown', function (e) {
            const activeTag = document.activeElement ? document.activeElement.tagName : '';
            const activeId = document.activeElement ? document.activeElement.id : '';

            // If typing in search box, handle normally unless it's a high-speed scanner burst
            const now = Date.now();
            const timeDiff = now - lastKeypressTime;
            lastKeypressTime = now;

            if (e.key === 'Enter') {
                if (scannerBuffer.length >= 3 && timeDiff < 60) {
                    // Scanner burst detected
                    e.preventDefault();
                    selectStudentByNis(scannerBuffer);
                    scannerBuffer = '';
                    return;
                }
                scannerBuffer = '';
            } else if (e.key.length === 1) {
                if (timeDiff > 80) {
                    scannerBuffer = e.key;
                } else {
                    scannerBuffer += e.key;
                }
            }

            // Keyboard shortcut '/' to search menu
            if (e.key === '/' && activeTag !== 'INPUT' && activeTag !== 'TEXTAREA') {
                e.preventDefault();
                const s = document.getElementById('inputSearchMenu');
                if (s) s.focus();
            }

            // Keyboard shortcut 'F2' to focus NIS Scan Barcode input
            if (e.key === 'F2') {
                e.preventDefault();
                const n = document.getElementById('inputScanNis');
                if (n) {
                    n.focus();
                    n.select();
                }
            }
        });

        // --- MENU CATALOG LIVE SEARCH & CATEGORY FILTER ---
        function setKategoriFilter(kat, btnElement) {
            currentCategoryFilter = kat;

            // Update active pill styling
            document.querySelectorAll('#categoryPillContainer .pos-cat-pill').forEach(btn => {
                btn.classList.remove('active');
            });
            if (btnElement) {
                btnElement.classList.add('active');
            }

            filterMenuCatalog();
        }

        function filterMenuCatalog() {
            const searchInput = document.getElementById('inputSearchMenu');
            const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
            const btnClear = document.getElementById('btnClearMenuSearch');
            const badge = document.getElementById('menuCountBadge');
            const noFound = document.getElementById('noMenuFoundAlert');
            const queryText = document.getElementById('searchedQueryText');

            if (btnClear) {
                btnClear.style.display = query.length > 0 ? 'inline-flex' : 'none';
            }
            if (queryText) {
                queryText.innerText = query;
            }

            const items = document.querySelectorAll('.menu-item-wrapper');
            let visibleCount = 0;

            items.forEach(el => {
                const itemKat = (el.getAttribute('data-kategori') || '').toLowerCase();
                const itemNama = (el.getAttribute('data-nama') || '').toLowerCase();

                const matchesCategory = (currentCategoryFilter === 'semua' || itemKat === currentCategoryFilter);
                const matchesQuery = (query === '' || itemNama.includes(query) || itemKat.includes(query));

                if (matchesCategory && matchesQuery) {
                    el.style.display = '';
                    visibleCount++;
                } else {
                    el.style.display = 'none';
                }
            });

            if (badge) {
                badge.innerText = visibleCount + ' Menu';
            }

            if (noFound) {
                noFound.style.display = (visibleCount === 0 && items.length > 0) ? 'block' : 'none';
            }
        }

        function clearMenuSearch() {
            const input = document.getElementById('inputSearchMenu');
            if (input) {
                input.value = '';
                input.focus();
            }
            filterMenuCatalog();
        }

        // Initialize category on load if specified
        document.addEventListener('DOMContentLoaded', function () {
            if (currentCategoryFilter && currentCategoryFilter !== 'semua') {
                const activeBtn = document.querySelector(`#categoryPillContainer .pos-cat-pill[data-kat="${currentCategoryFilter}"]`);
                if (activeBtn) {
                    document.querySelectorAll('#categoryPillContainer .pos-cat-pill').forEach(b => b.classList.remove('active'));
                    activeBtn.classList.add('active');
                }
            }
            filterMenuCatalog();
        });

        function scrollToCart() {
            const el = document.getElementById('cartSection');
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        // --- POS CART FUNCTIONS ---
        function addToCart(id, nama, harga, maxStok) {
            const existing = cart.find(item => item.id === id);
            if (existing) {
                if (existing.qty < maxStok) {
                    existing.qty++;
                } else {
                    alert('Stok item ' + nama + ' tidak mencukupi!');
                    return;
                }
            } else {
                cart.push({ id, nama, harga, qty: 1, maxStok });
            }
            renderCart();
        }

        function updateQty(id, delta) {
            const item = cart.find(i => i.id === id);
            if (item) {
                item.qty += delta;
                if (item.qty <= 0) {
                    cart = cart.filter(i => i.id !== id);
                } else if (item.qty > item.maxStok) {
                    item.qty = item.maxStok;
                    alert('Stok maksimal!');
                }
            }
            renderCart();
        }

        function clearCart() {
            cart = [];
            renderCart();
        }

        function renderCart() {
            const container = document.getElementById('cartItemsList');
            const totalText = document.getElementById('totalHargaText');
            const inputJson = document.getElementById('itemsJsonInput');
            const btnCheckout = document.getElementById('btnCheckout');
            const mobileBar = document.getElementById('mobileCartFloatingBar');
            const mobileCount = document.getElementById('mobileCartCount');
            const mobileTotal = document.getElementById('mobileCartTotal');

            if (cart.length === 0) {
                container.innerHTML = `
            <div class="text-center py-5 text-muted small">
                <i class="bi bi-cart-x fs-1 d-block mb-1 text-secondary opacity-40"></i>
                Keranjang masih kosong.<br>Klik item dari katalog untuk menambah.
            </div>`;
                totalText.innerText = 'Rp 0';
                inputJson.value = '[]';
                btnCheckout.disabled = true;
                if (mobileBar) {
                    mobileBar.classList.remove('show');
                }
                return;
            }

            let html = '<div class="d-flex flex-column gap-2">';
            let total = 0;
            let totalItems = 0;

            cart.forEach(item => {
                const subtotal = item.harga * item.qty;
                total += subtotal;
                totalItems += item.qty;
                html += `
            <div class="cart-item-card d-flex align-items-center justify-content-between gap-2">
                <div class="flex-grow-1 overflow-hidden">
                    <div class="fw-extrabold text-dark text-truncate" style="font-size: 0.88rem; font-family: 'Outfit', sans-serif;">${item.nama}</div>
                    <div class="text-muted extra-small">
                        Rp ${new Intl.NumberFormat('id-ID').format(item.harga)} &bull; Subtotal: <strong style="color: #2563eb;">Rp ${new Intl.NumberFormat('id-ID').format(subtotal)}</strong>
                    </div>
                </div>
                <div class="qty-stepper-pill flex-shrink-0">
                    <button type="button" class="btn-qty-step" onclick="updateQty(${item.id}, -1)"><i class="bi bi-dash"></i></button>
                    <span class="qty-number-badge">${item.qty}</span>
                    <button type="button" class="btn-qty-step" onclick="updateQty(${item.id}, 1)"><i class="bi bi-plus"></i></button>
                </div>
            </div>`;
            });
            html += '</div>';

            container.innerHTML = html;
            const formattedTotal = 'Rp ' + new Intl.NumberFormat('id-ID').format(total);
            totalText.innerText = formattedTotal;
            inputJson.value = JSON.stringify(cart);
            btnCheckout.disabled = false;

            // Sync Mobile Floating Bar
            if (mobileBar && mobileCount && mobileTotal) {
                mobileCount.innerText = totalItems;
                mobileTotal.innerText = formattedTotal;
                mobileBar.classList.add('show');
            }
        }

        function validateCheckout() {
            if (cart.length === 0) return false;
            const radioMetode = document.querySelector('input[name="metode_bayar"]:checked');
            if (!radioMetode) {
                alert('Pilih metode pembayaran terlebih dahulu!');
                return false;
            }
            const metode = radioMetode.value;
            let total = cart.reduce((sum, i) => sum + (i.harga * i.qty), 0);

            if (metode === 'saldo') {
                if (currentStudentData && currentStudentData.limit_aktif && currentStudentData.limit_tipe && currentStudentData.limit_tipe !== 'none') {
                    if (currentStudentData.is_limit_reached) {
                        alert("Maaf uang jajan sudah sampai maksimal pembatasan yang dibatasi oleh orang tua.");
                        return false;
                    }
                    if (total > currentStudentData.limit_remaining) {
                        alert("Maaf uang jajan melebihi batas pembatasan oleh orang tua!\nSisa batas jajan yang diperbolehkan: Rp " + new Intl.NumberFormat('id-ID').format(currentStudentData.limit_remaining));
                        return false;
                    }
                }

                if (total > studentBalance) {
                    alert('Saldo siswa tidak mencukupi! Total: Rp ' + new Intl.NumberFormat('id-ID').format(total) + ', Saldo: Rp ' + new Intl.NumberFormat('id-ID').format(studentBalance));
                    return false;
                }
            }
            return true;
        }

        // --- ULTRA-FAST INSTANT LIVE CAMERA BARCODE SCANNER (ZERO-LOADING LATENCY) ---
        let html5QrCodeScanner = null;
        let currentCameraId = null;
        let isScanningActive = false;
        let isScanningPaused = true;
        let isCameraStarting = false;
        let isFlashOn = false;
        let lastScannedCode = '';
        let lastScanTime = 0;
        let cachedCameras = null;

        function playBeepSound() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(1200, audioCtx.currentTime); // High-pitched crisp POS beep
                gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.12);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.12);
            } catch (e) { }
        }

        function openCameraScanner() {
            // Check secure context (HTTPS / localhost required for mobile cameras)
            if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
                alert('Akses kamera di HP diblokir browser: Website harus menggunakan HTTPS atau localhost.');
                return;
            }

            // Immediately unpause detection
            isScanningPaused = false;

            const modalEl = document.getElementById('modalCameraScanner');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();

            // If camera stream is not already running or starting, initialize immediately
            if (!isScanningActive && !isCameraStarting) {
                initFastCamera(false);
            } else {
                const statusBox = document.getElementById('scannerStatusBox');
                if (statusBox) {
                    statusBox.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-camera-video-fill me-1 text-success"></i>Kamera aktif! Arahkan ke barcode NIS santri...</span>';
                }
            }
        }

        function closeCameraScanner() {
            // Pause scanning detection so it doesn't trigger in background
            isScanningPaused = true;

            const modalEl = document.getElementById('modalCameraScanner');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) {
                modal.hide();
            }
            // Note: Camera stream is kept alive in background for 0ms instant reopening!
        }

        function toggleScannerFlash() {
            if (!html5QrCodeScanner || !isScanningActive) return;
            try {
                isFlashOn = !isFlashOn;
                html5QrCodeScanner.applyVideoConstraints({
                    advanced: [{ torch: isFlashOn }]
                }).then(() => {
                    const lbl = document.getElementById('lblToggleFlash');
                    if (lbl) lbl.textContent = isFlashOn ? 'Matikan' : 'Flash';
                }).catch(() => {
                    isFlashOn = false;
                });
            } catch (e) { }
        }

        // Asynchronously populate camera select dropdown in background
        function loadCamerasInBackground() {
            if (!window.Html5Qrcode || cachedCameras) return;
            Html5Qrcode.getCameras().then(devices => {
                if (devices && devices.length > 0) {
                    cachedCameras = devices;
                    const select = document.getElementById('cameraSelect');
                    if (select) {
                        select.innerHTML = '';
                        const optAuto = document.createElement('option');
                        optAuto.value = "environment";
                        optAuto.text = "⚡ Kamera Belakang (Otomatis)";
                        select.appendChild(optAuto);

                        devices.forEach((dev, idx) => {
                            const opt = document.createElement('option');
                            opt.value = dev.id;
                            const isBack = /back|belakang|rear|environment/i.test(dev.label || '');
                            opt.text = dev.label ? dev.label : (isBack ? `Kamera Belakang (${idx + 1})` : `Kamera Depan/Webcam (${idx + 1})`);
                            if (currentCameraId === dev.id) {
                                opt.selected = true;
                            }
                            select.appendChild(opt);
                        });
                    }
                }
            }).catch(e => {
                console.log("Device enumeration info:", e);
            });
        }

        function initFastCamera(isPrewarm = false) {
            if (!window.Html5Qrcode) {
                if (!isPrewarm) {
                    const statusBox = document.getElementById('scannerStatusBox');
                    if (statusBox) {
                        statusBox.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-exclamation-octagon me-1"></i>Pustaka scanner belum siap. Silakan refresh halaman.</span>';
                    }
                }
                return;
            }

            if (!isPrewarm) {
                const statusBox = document.getElementById('scannerStatusBox');
                if (statusBox) {
                    statusBox.innerHTML = '<span class="text-primary fw-bold"><span class="spinner-border spinner-border-sm me-1.5" role="status"></span>Membuka sensor kamera...</span>';
                }
            }

            const targetConfig = currentCameraId ? currentCameraId : { facingMode: "environment" };
            startScanning(targetConfig, isPrewarm);
            loadCamerasInBackground();
        }

        function startScanning(cameraConfig, isPrewarm = false) {
            if (isCameraStarting) return;
            isCameraStarting = true;

            const executeStart = () => {
                try {
                    const readerEl = document.getElementById('cameraReader');
                    if (readerEl && !html5QrCodeScanner) {
                        const oldVideos = readerEl.querySelectorAll('video, canvas');
                        oldVideos.forEach(el => el.remove());

                        html5QrCodeScanner = new Html5Qrcode("cameraReader", {
                            experimentalFeatures: {
                                useBarCodeDetectorIfSupported: true // Ultra-fast hardware C++ decoder in Chrome/Android
                            },
                            verbose: false
                        });
                    }
                } catch (e) {
                    console.error("Html5Qrcode init error:", e);
                    isCameraStarting = false;
                    return;
                }

                if (!html5QrCodeScanner) {
                    isCameraStarting = false;
                    return;
                }

                // Ultra-optimized config for sub-millisecond barcode detection
                const config = {
                    fps: 20,
                    qrbox: (viewfinderWidth, viewfinderHeight) => {
                        const minEdge = Math.min(viewfinderWidth || 320, viewfinderHeight || 240);
                        const w = Math.max(200, Math.floor(minEdge * 0.88));
                        const h = Math.max(140, Math.floor(w * 0.7));
                        return { width: w, height: h };
                    },
                    aspectRatio: 1.333333,
                    videoConstraints: {
                        facingMode: { ideal: "environment" },
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    }
                };

                html5QrCodeScanner.start(
                    cameraConfig,
                    config,
                    onScanSuccess,
                    onScanError
                ).then(() => {
                    isScanningActive = true;
                    isCameraStarting = false;

                    const preloader = document.getElementById('scannerPreloader');
                    if (preloader) preloader.style.display = 'none';

                    ['scannerLaser', 'scannerCornerTL', 'scannerCornerTR', 'scannerCornerBL', 'scannerCornerBR'].forEach(id => {
                        const el = document.getElementById(id);
                        if (el) el.style.display = 'block';
                    });

                    // Check Flashlight / Torch Support
                    try {
                        const capabilities = html5QrCodeScanner.getRunningTrackCameraCapabilities();
                        const btnFlash = document.getElementById('btnToggleFlash');
                        if (btnFlash && capabilities && typeof capabilities.torchFeature === 'function' && capabilities.torchFeature().isSupported()) {
                            btnFlash.classList.remove('d-none');
                        }
                    } catch (e) { }

                    const statusBox = document.getElementById('scannerStatusBox');
                    if (statusBox) {
                        statusBox.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-camera-video-fill me-1 text-success"></i>Kamera aktif! Arahkan ke barcode NIS santri...</span>';
                    }
                }).catch(err => {
                    console.warn("Scanner start error:", err);
                    isScanningActive = false;
                    isCameraStarting = false;

                    // Fallback to user facing mode if environment camera not found
                    if (cameraConfig && typeof cameraConfig === 'object' && cameraConfig.facingMode === 'environment') {
                        startScanning({ facingMode: "user" }, isPrewarm);
                    } else {
                        const preloader = document.getElementById('scannerPreloader');
                        if (preloader) preloader.style.display = 'none';

                        if (!isPrewarm) {
                            const statusBox = document.getElementById('scannerStatusBox');
                            if (statusBox) {
                                statusBox.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i>Izin kamera belum aktif atau sedang digunakan aplikasi lain.</span>';
                            }
                        }
                    }
                });
            };

            if (html5QrCodeScanner && isScanningActive) {
                stopCameraHardware().then(() => {
                    executeStart();
                });
            } else {
                executeStart();
            }
        }

        function switchCamera(cameraId) {
            if (!cameraId) return;
            if (cameraId === 'environment' || cameraId === 'user') {
                currentCameraId = null;
                startScanning({ facingMode: cameraId }, false);
            } else {
                currentCameraId = cameraId;
                startScanning(cameraId, false);
            }
        }

        function stopCameraHardware() {
            isScanningActive = false;
            isCameraStarting = false;
            isFlashOn = false;

            const btnFlash = document.getElementById('btnToggleFlash');
            if (btnFlash) {
                btnFlash.classList.add('d-none');
                const lbl = document.getElementById('lblToggleFlash');
                if (lbl) lbl.textContent = 'Flash';
            }

            if (html5QrCodeScanner) {
                const scannerInstance = html5QrCodeScanner;
                html5QrCodeScanner = null;
                return scannerInstance.stop().then(() => {
                    try { scannerInstance.clear(); } catch (e) { }
                }).catch(() => {
                    try { scannerInstance.clear(); } catch (e) { }
                });
            }
            return Promise.resolve();
        }

        function onScanSuccess(decodedText, decodedResult) {
            // Ignore scan events if scanner modal is not currently opened
            if (isScanningPaused) return;

            const now = Date.now();
            // Prevent duplicate scans within 1.0 second
            if (decodedText === lastScannedCode && (now - lastScanTime < 1000)) {
                return;
            }
            lastScannedCode = decodedText;
            lastScanTime = now;

            // Audio POS Beep
            playBeepSound();

            // Instant student lookup (0ms)
            selectStudentByNis(decodedText);

            const statusBox = document.getElementById('scannerStatusBox');
            if (statusBox) {
                statusBox.innerHTML = `<span class="text-success fw-bold">✅ Berhasil Scan NIS [${escapeHtml(decodedText)}]</span>`;
            }

            // Instant auto close if option checked
            const autoClose = document.getElementById('chkAutoCloseScanner')?.checked;
            if (autoClose) {
                closeCameraScanner();
            }
        }

        function onScanError(errorMessage) {
            // Frame search loop, silent ignore
        }

        // AUTO-PREWARM CAMERA STREAM IN BACKGROUND (0ms Instant Launch)
        document.addEventListener('DOMContentLoaded', () => {
            // Preload cameras list in background
            loadCamerasInBackground();

            // Silently warm up camera stream if permission was previously granted
            if (navigator.permissions && navigator.permissions.query) {
                navigator.permissions.query({ name: 'camera' }).then(permissionStatus => {
                    if (permissionStatus.state === 'granted') {
                        initFastCamera(true);
                    }
                }).catch(() => { });
            }

            // Cleanly release hardware only when navigating away from page
            window.addEventListener('beforeunload', () => {
                stopCameraHardware();
            });
            window.addEventListener('pagehide', () => {
                stopCameraHardware();
            });
        });
    </script>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>