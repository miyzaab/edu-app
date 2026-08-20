<?php
/**
 * POS KASIR KANTIN DIGITAL - E-Wallet & Barcode Scanner (Kamera & Physical Scanner)
 */
$pageTitle  = 'POS Kasir Kantin';
$activePage = 'kantin';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('kantin');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$flash  = getFlash();
$namaSekolah = getSetting('nama_sekolah', SCHOOL_NAME);

// Search or scan student NIS
$selectedSiswa = null;
$studentBalance = 0;

$searchNis = $_GET['nis'] ?? '';
if (!empty($searchNis)) {
    $stmtS = $pdo->prepare("
        SELECT s.*, k.nama_kelas, COALESCE(ss.saldo, 0) AS saldo
        FROM siswa s
        JOIN kelas k ON s.kelas_id = k.id
        LEFT JOIN saldo_siswa ss ON s.id = ss.siswa_id
        WHERE (s.nis = :nis OR s.nama LIKE :nama) AND s.status = 'aktif'
        LIMIT 1
    ");
    $stmtS->execute([':nis' => $searchNis, ':nama' => '%' . $searchNis . '%']);
    $selectedSiswa = $stmtS->fetch();
    if ($selectedSiswa) {
        $studentBalance = (float)$selectedSiswa['saldo'];
    }
}

// All active students for dropdown selector
$siswaList = $pdo->query("
    SELECT s.id, s.nis, s.nama, k.nama_kelas, COALESCE(ss.saldo, 0) as saldo 
    FROM siswa s
    JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN saldo_siswa ss ON s.id = ss.siswa_id
    WHERE s.status = 'aktif'
    ORDER BY s.nama ASC
")->fetchAll();

// Menu items
$kategoriFilter = $_GET['kategori'] ?? 'semua';
$whereKategori = ($kategoriFilter !== 'semua') ? "AND kategori = " . $pdo->quote($kategoriFilter) : "";
$menuList = $pdo->query("SELECT * FROM kantin_menu WHERE status = 'tersedia' $whereKategori ORDER BY kategori ASC, nama_item ASC")->fetchAll();

require_once __DIR__ . '/../../classes/TransaksiKantin.php';

// PROSES TRANSAKSI KASIR VIA TRANSAKSI KANTIN CLASS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    $siswaId     = !empty($_POST['siswa_id']) ? (int)$_POST['siswa_id'] : null;
    $metodeBayar = $_POST['metode_bayar'] ?? 'saldo'; // saldo / tunai
    $itemsJson   = $_POST['items_json'] ?? '[]';
    $cartItems   = json_decode($itemsJson, true);

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
    $pId = (int)$_GET['print_id'];
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
            'header'  => $printHeader,
            'details' => $printDetails
        ];
    }
}

$pendingTopupCount = 0;
try {
    $stmtPCount = $pdo->query("SELECT COUNT(*) FROM pembayaran_pending WHERE jenis = 'topup_kantin' AND status = 'pending'");
    $pendingTopupCount = (int)$stmtPCount->fetchColumn();
} catch (Exception $e) {}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Include Html5Qrcode Library for Fast Camera Barcode Scanning -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<!-- POS DIGITAL LUXURY SYSTEM -->
<style>
    /* GLOBAL KANTIN DESIGN SYSTEM */
    .kantin-hero-card {
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 55%, #1e40af 100%);
        border-radius: 22px;
        border: none;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
    }
    .pos-nav-container {
        display: inline-flex;
        align-items: center;
        background: rgba(255, 255, 255, 0.12);
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
        color: rgba(255, 255, 255, 0.85);
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

    /* SEARCH INPUT GROUP WITH CORNER SAFETY */
    .pos-search-box {
        display: flex;
        align-items: center;
        background: #f8fafc;
        border: 1.5px solid #cbd5e1;
        border-radius: 14px;
        padding: 5px 5px 5px 16px;
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
        font-size: 0.9rem;
        color: #0f172a;
        padding: 6px 10px;
    }
    .btn-camera-trigger {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: #ffffff;
        border: none;
        border-radius: 10px;
        padding: 9px 18px;
        font-weight: 800;
        font-size: 0.82rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        white-space: nowrap;
        box-shadow: 0 3px 8px rgba(37, 99, 235, 0.25);
        transition: all 0.2s ease;
    }
    .btn-camera-trigger:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
    }

    /* CATEGORY PILLS WITH INNER SAFETY PADDING */
    .pos-cat-pill {
        border-radius: 50px;
        font-weight: 700;
        font-size: 0.8rem;
        padding: 8px 20px;
        transition: all 0.2s ease;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: #64748b;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
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
        border: 1px solid #f1f5f9;
        border-radius: 16px;
        overflow: hidden;
        transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .product-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 26px rgba(15, 23, 42, 0.08);
        border-color: #cbd5e1;
    }
    .product-card-body {
        padding: 14px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: calc(100% - 110px);
    }
    .btn-add-product {
        background: #2563eb;
        color: #ffffff;
        border: none;
        border-radius: 10px;
        padding: 8px 14px;
        font-size: 0.82rem;
        font-weight: 800;
        width: 100%;
        transition: all 0.18s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
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
        padding: 12px 14px;
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
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .btn-qty-step {
        width: 26px;
        height: 26px;
        border-radius: 7px;
        border: none !important;
        background: #f1f5f9;
        color: #0f172a;
        font-weight: 800;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.15s ease;
        padding: 0;
        outline: none !important;
        box-shadow: none !important;
    }
    .btn-qty-step:hover {
        background: #2563eb;
        color: #ffffff;
    }
    .qty-number-badge {
        font-weight: 800;
        font-size: 0.85rem;
        color: #0f172a;
        min-width: 22px;
        text-align: center;
        font-family: 'Outfit', sans-serif;
    }
</style>

<!-- HERO HEADER BANNER (INDIGO BLUE DEEP THEME) -->
<div class="card kantin-hero-card text-white p-4 p-md-4.5 mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <!-- VISIBLE GLASSMORPHIC ICON BOX -->
            <div class="rounded-4 p-2.5 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 50px; height: 50px; background: rgba(255, 255, 255, 0.18); border: 1px solid rgba(255, 255, 255, 0.25); backdrop-filter: blur(10px);">
                <i class="bi bi-shop-window fs-3 text-white"></i>
            </div>
            <div>
                <h4 class="fw-extrabold text-white mb-0" style="font-family: 'Outfit', sans-serif; letter-spacing: -0.02em;">Kasir Kantin Digital (POS)</h4>
                <p class="text-white-50 small mb-0">Transaksi cepat dengan E-Wallet Saldo Santri &amp; Live Barcode Scanner</p>
            </div>
        </div>

        <!-- SEGMENTED NAVIGATION CONTROLLER -->
        <div class="pos-nav-container">
            <a href="index.php" class="pos-nav-link active"><i class="bi bi-calculator-fill"></i> Kasir POS</a>
            <a href="topup.php" class="pos-nav-link"><i class="bi bi-wallet2"></i> Top-Up Saldo <?= $pendingTopupCount > 0 ? '<span class="badge bg-danger rounded-pill ms-1">' . $pendingTopupCount . '</span>' : '' ?></a>
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

<div class="row g-4">
    <!-- LEFT COLUMN: SCANNER, STUDENT INFO, MENU CATALOG -->
    <div class="col-lg-7">
        <!-- SCANNER & SEARCH SISWA CARD WITH 32PX GENEROUS BREATHING ROOM -->
        <div class="card border-0 shadow-sm bg-white mb-4" style="border-radius: 24px; padding: 2rem !important;">
            <form method="GET" action="index.php" id="formScanNis" class="row g-4 align-items-center mb-4">
                <div class="col-md-7">
                    <label class="form-label extra-small fw-extrabold text-muted mb-2 px-1"><i class="bi bi-qr-code-scan text-primary me-1.5"></i> Scan Barcode NIS / Cari Siswa</label>
                    <div class="pos-search-box">
                        <i class="bi bi-search text-primary fs-6 ms-1"></i>
                        <input type="text" name="nis" id="inputScanNis" class="pos-search-input" placeholder="Scan Barcode NIS / Ketik Nama..." value="<?= htmlspecialchars($searchNis) ?>" autofocus>
                        <button type="button" class="btn-camera-trigger" onclick="openCameraScanner()" title="Buka Kamera Barcode Scanner">
                            <i class="bi bi-camera-fill"></i> <span>Kamera</span>
                        </button>
                    </div>
                </div>
                <div class="col-md-5">
                    <label class="form-label extra-small fw-extrabold text-muted mb-2 px-1">Atau Pilih dari Daftar</label>
                    <select class="form-select border-0 bg-light fw-bold py-2.5" id="selectSiswaDropdown" style="border-radius: 14px; border: 1.5px solid #cbd5e1 !important; font-size: 0.85rem;" onchange="if(this.value) window.location.href='index.php?nis='+this.value">
                        <option value="">-- Pilih Siswa --</option>
                        <?php foreach ($siswaList as $s): ?>
                            <option value="<?= htmlspecialchars($s['nis']) ?>" <?= ($selectedSiswa && $selectedSiswa['id'] == $s['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['nama']) ?> (<?= htmlspecialchars($s['nama_kelas']) ?>) - Rp <?= number_format($s['saldo'],0,',','.') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <div id="siswaInfoCard" class="mt-2">
                <?php if ($selectedSiswa): ?>
                    <!-- FINTECH E-WALLET STUDENT CARD WITH CLEAR GAP & LUXURY SPACING -->
                    <div class="p-4 rounded-4 text-white position-relative overflow-hidden" style="background: linear-gradient(135deg, #059669 0%, #047857 100%); border-radius: 18px; box-shadow: 0 8px 22px rgba(5, 150, 105, 0.2) !important;">
                        <div class="d-flex align-items-center justify-content-between gap-3">
                            <div class="d-flex align-items-center gap-3.5" style="min-width: 0;">
                                <div class="rounded-circle bg-white text-emerald p-2 d-flex align-items-center justify-content-center fw-bold shadow-sm flex-shrink-0" style="width: 44px; height: 44px; color: #059669;">
                                    <i class="bi bi-person-fill fs-4"></i>
                                </div>
                                <div style="min-width: 0;">
                                    <h6 class="fw-extrabold text-white mb-1 text-truncate" style="font-size: 1rem; font-family: 'Outfit', sans-serif; letter-spacing: -0.01em;"><?= htmlspecialchars($selectedSiswa['nama']) ?></h6>
                                    <div class="extra-small text-white-50 text-truncate">NIS: <strong class="text-white"><?= htmlspecialchars($selectedSiswa['nis']) ?></strong> &bull; Kelas <?= htmlspecialchars($selectedSiswa['nama_kelas']) ?></div>
                                </div>
                            </div>
                            <div class="text-end flex-shrink-0 pe-1">
                                <div class="extra-small text-white-50 fw-extrabold uppercase mb-0.5" style="font-size: 0.65rem; letter-spacing: 0.8px;">SALDO KANTIN ACTIVE</div>
                                <h4 class="fw-extrabold mb-0" style="font-family: 'Outfit', sans-serif; color: #a7f3d0; font-size: 1.3rem;">Rp <?= number_format($studentBalance, 0, ',', '.') ?></h4>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="p-3.5 px-4 rounded-4 bg-light border text-muted extra-small d-flex align-items-center gap-3" style="border-radius: 16px !important; border-color: #e2e8f0 !important;">
                        <i class="bi bi-info-circle-fill text-primary fs-5 flex-shrink-0 ms-1"></i>
                        <span style="line-height: 1.55;">Scan barcode NIS siswa (manual atau via tombol <strong>Kamera</strong>) untuk cek Saldo Kantin &amp; pembayaran otomatis.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- MENU CATALOG GRID WITH GENEROUS 32PX PADDING & CORNER SAFETY -->
        <div class="card border-0 shadow-sm bg-white" style="border-radius: 24px; padding: 2rem !important;">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 px-1">
                <h6 class="fw-extrabold mb-0 text-dark" style="font-family: 'Outfit', sans-serif; font-size: 1.05rem;">
                    <i class="bi bi-grid-fill text-primary me-2"></i>Katalog Menu Kantin
                </h6>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a href="index.php?nis=<?= urlencode($searchNis) ?>&kategori=semua" class="pos-cat-pill <?= $kategoriFilter === 'semua' ? 'active' : '' ?>">Semua</a>
                    <a href="index.php?nis=<?= urlencode($searchNis) ?>&kategori=makanan" class="pos-cat-pill <?= $kategoriFilter === 'makanan' ? 'active' : '' ?>">🍱 Makanan</a>
                    <a href="index.php?nis=<?= urlencode($searchNis) ?>&kategori=minuman" class="pos-cat-pill <?= $kategoriFilter === 'minuman' ? 'active' : '' ?>">🥤 Minuman</a>
                    <a href="index.php?nis=<?= urlencode($searchNis) ?>&kategori=jajanan" class="pos-cat-pill <?= $kategoriFilter === 'jajanan' ? 'active' : '' ?>">🍿 Jajanan</a>
                </div>
            </div>

            <div class="row g-3.5">
                <?php if (empty($menuList)): ?>
                    <div class="col-12 text-center py-5 text-muted small">
                        <i class="bi bi-egg-fried fs-1 opacity-40 d-block mb-2 text-secondary"></i>
                        Tidak ada menu kantin yang tersedia dalam kategori ini.
                    </div>
                <?php endif; ?>
                <?php foreach ($menuList as $m): ?>
                    <?php 
                        $stokBg = ($m['stok'] > 10) ? 'bg-success' : (($m['stok'] > 0) ? 'bg-primary' : 'bg-danger');
                    ?>
                    <div class="col-md-4 col-6">
                        <div class="product-card h-100 position-relative shadow-2xs bg-white">
                            <span class="position-absolute top-0 end-0 m-2 badge <?= $stokBg ?> extra-small rounded-pill z-1 shadow-2xs fw-bold px-2.5 py-1">
                                Stok: <?= $m['stok'] ?>
                            </span>

                            <!-- MENU PHOTO HEADER -->
                            <?php if (!empty($m['foto']) && file_exists(__DIR__ . '/../../uploads/kantin/' . $m['foto'])): ?>
                                <div style="height: 110px; overflow: hidden;" class="position-relative bg-light border-bottom">
                                    <img src="../../uploads/kantin/<?= htmlspecialchars($m['foto']) ?>" alt="<?= htmlspecialchars($m['nama_item']) ?>" class="w-100 h-100" style="object-fit: cover;">
                                </div>
                            <?php else: ?>
                                <div style="height: 105px;" class="d-flex align-items-center justify-content-center bg-light border-bottom">
                                    <?php if ($m['kategori'] === 'makanan'): ?>
                                        <span class="fs-1">🍱</span>
                                    <?php elseif ($m['kategori'] === 'minuman'): ?>
                                        <span class="fs-1">🥤</span>
                                    <?php else: ?>
                                        <span class="fs-1">🍿</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="product-card-body">
                                <div class="mb-2">
                                    <h6 class="fw-extrabold text-dark mb-1 small text-truncate" style="font-family: 'Outfit', sans-serif;" title="<?= htmlspecialchars($m['nama_item']) ?>"><?= htmlspecialchars($m['nama_item']) ?></h6>
                                    <div class="fw-extrabold text-dark" style="font-size: 0.95rem; font-family: 'Outfit', sans-serif;">Rp <?= number_format($m['harga'], 0, ',', '.') ?></div>
                                </div>
                                <button class="btn-add-product" onclick="addToCart(<?= $m['id'] ?>, '<?= addslashes(htmlspecialchars($m['nama_item'])) ?>', <?= $m['harga'] ?>, <?= $m['stok'] ?>)">
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
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm bg-white sticky-top d-flex flex-column" style="top: 85px; border-radius: 24px; padding: 2rem !important; min-height: 500px;">
            <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-3 px-1">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: #eff6ff; color: #2563eb;">
                        <i class="bi bi-cart3 fs-5"></i>
                    </div>
                    <h6 class="fw-extrabold text-dark mb-0" style="font-family: 'Outfit', sans-serif; font-size: 1.05rem;">Keranjang Kasir</h6>
                </div>
                <button class="btn btn-link text-danger text-decoration-none p-0 extra-small fw-extrabold" onclick="clearCart()">
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
                <input type="hidden" name="siswa_id" id="siswaIdInput" value="<?= $selectedSiswa ? $selectedSiswa['id'] : '' ?>">
                <input type="hidden" name="items_json" id="itemsJsonInput" value="[]">

                <div class="p-3.5 rounded-4 mb-3 border" style="background: #f8fafc; border-color: #e2e8f0 !important;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted extra-small fw-extrabold" style="letter-spacing: 0.5px;">TOTAL BELANJA</span>
                        <h3 class="fw-extrabold text-dark mb-0" id="totalHargaText" style="font-family: 'Outfit', sans-serif; font-size: 1.6rem;">Rp 0</h3>
                    </div>

                    <!-- METODE PEMBAYARAN TOGGLE -->
                    <label class="form-label extra-small fw-extrabold text-muted uppercase mb-2"><i class="bi bi-credit-card-2-front me-1 text-primary"></i> Metode Pembayaran</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="metode_bayar" id="bayarSaldo" value="saldo" <?= $selectedSiswa ? 'checked' : 'disabled' ?>>
                            <label class="btn btn-outline-primary w-100 rounded-3 py-2 extra-small fw-extrabold d-flex align-items-center justify-content-center gap-1.5" for="bayarSaldo">
                                <i class="bi bi-wallet2"></i> Saldo Siswa
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="metode_bayar" id="bayarTunai" value="tunai" <?= !$selectedSiswa ? 'checked' : '' ?>>
                            <label class="btn btn-outline-secondary w-100 rounded-3 py-2 extra-small fw-extrabold d-flex align-items-center justify-content-center gap-1.5" for="bayarTunai">
                                <i class="bi bi-cash-stack"></i> Tunai (Cash)
                            </label>
                        </div>
                    </div>
                </div>

                <button type="submit" id="btnCheckout" class="btn btn-success w-100 rounded-3 py-3 fw-extrabold text-uppercase shadow-sm" disabled style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none; font-size: 0.92rem; letter-spacing: 0.5px;">
                    <i class="bi bi-cart-check-fill me-2 fs-6"></i> Proses Pembayaran Kasir
                </button>
            </form>
        </div>
    </div>
<!-- MODAL LIVE CAMERA BARCODE SCANNER -->
<div class="modal fade" id="modalCameraScanner" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white p-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-camera-fill me-2 text-primary"></i>Live Camera Barcode Scanner Siswa</h6>
                <button type="button" class="btn-close btn-close-white" onclick="closeCameraScanner()" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 bg-light text-center">
                <!-- CAMERA SELECTOR -->
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="extra-small fw-bold text-muted"><i class="bi bi-webcam me-1"></i>Pilih Kamera:</span>
                    <select id="cameraSelect" class="form-select form-select-sm w-auto extra-small border-0 bg-white shadow-sm fw-bold" onchange="switchCamera(this.value)">
                        <option value="">Memuat kamera...</option>
                    </select>
                </div>

                <!-- SCANNER VIEWPORT CONTAINER -->
                <div id="cameraReader" class="rounded-3 shadow-sm bg-black overflow-hidden position-relative" style="width: 100%; min-height: 250px;">
                </div>

                <!-- SCAN STATUS ALERT -->
                <div id="scannerStatusBox" class="mt-3 p-2 rounded-3 bg-white border text-muted extra-small d-flex align-items-center justify-content-center gap-2">
                    <span class="spinner-grow spinner-grow-sm text-primary" role="status"></span>
                    <span id="scannerStatusText">Mengarahkan kamera ke barcode NIS siswa...</span>
                </div>

                <div class="mt-2 d-flex justify-content-between align-items-center extra-small text-muted px-1">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="chkAutoCloseScanner" checked>
                        <label class="form-check-label extra-small fw-semibold" for="chkAutoCloseScanner">Auto Tutup setelah scan</label>
                    </div>
                    <span><i class="bi bi-lightning-fill text-primary me-1"></i>Deteksi Cepat 20 FPS</span>
                </div>
            </div>
            <div class="modal-footer p-2 bg-white">
                <button type="button" class="btn btn-secondary btn-sm w-100 rounded-3 fw-bold" onclick="closeCameraScanner()">
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
                    <span class="fs-6 text-teal">Rp <?= number_format($printData['header']['total_harga'], 0, ',', '.') ?></span>
                </div>
                <div class="extra-small text-muted mt-1">Bayar: <?= strtoupper($printData['header']['metode_bayar']) ?></div>
                <div class="extra-small text-muted mt-3">Terima kasih & Selamat Menikmati! 😊</div>
            </div>
            <div class="modal-footer p-2 bg-light">
                <button type="button" class="btn btn-primary btn-sm w-100 rounded-3 fw-bold" onclick="window.print()">
                    <i class="bi bi-printer-fill me-1"></i> Cetak Struk
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
let cart = [];
let studentBalance = <?= (float)$studentBalance ?>;

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

    if (cart.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5 text-muted small">
                <i class="bi bi-cart-x fs-1 d-block mb-1 text-secondary opacity-40"></i>
                Keranjang masih kosong.<br>Klik item dari katalog untuk menambah.
            </div>`;
        totalText.innerText = 'Rp 0';
        inputJson.value = '[]';
        btnCheckout.disabled = true;
        return;
    }

    let html = '<div class="d-flex flex-column gap-2">';
    let total = 0;

    cart.forEach(item => {
        const subtotal = item.harga * item.qty;
        total += subtotal;
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
    totalText.innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(total);
    inputJson.value = JSON.stringify(cart);
    btnCheckout.disabled = false;
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

    if (metode === 'saldo' && total > studentBalance) {
        alert('Saldo siswa tidak mencukupi! Total: Rp ' + new Intl.NumberFormat('id-ID').format(total) + ', Saldo: Rp ' + new Intl.NumberFormat('id-ID').format(studentBalance));
        return false;
    }
    return true;
}

// --- FAST LIVE CAMERA BARCODE SCANNER LOGIC ---
let html5QrCodeScanner = null;
let currentCameraId = null;
let isScanningActive = false;
let lastScannedCode = '';
let lastScanTime = 0;

function playBeepSound() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, audioCtx.currentTime); // 880 Hz
        gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.15);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.15);
    } catch(e) {}
}

function openCameraScanner() {
    const modalEl = document.getElementById('modalCameraScanner');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    modalEl.addEventListener('shown.bs.modal', function onShown() {
        modalEl.removeEventListener('shown.bs.modal', onShown);
        initCamera();
    });
}

function closeCameraScanner() {
    stopCamera().then(() => {
        const modalEl = document.getElementById('modalCameraScanner');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    });
}

function initCamera() {
    if (!window.Html5Qrcode) {
        document.getElementById('scannerStatusText').innerHTML = '<span class="text-danger">Gagal memuat pustaka scanner. Pastikan ada jaringan internet.</span>';
        return;
    }

    Html5Qrcode.getCameras().then(devices => {
        const select = document.getElementById('cameraSelect');
        select.innerHTML = '';
        if (devices && devices.length) {
            devices.forEach((dev, idx) => {
                const opt = document.createElement('option');
                opt.value = dev.id;
                opt.text = dev.label || `Kamera ${idx + 1}`;
                select.appendChild(opt);
            });
            
            // Prefer back/rear camera if available
            const backCam = devices.find(d => {
                const lbl = (d.label || '').toLowerCase();
                return lbl.includes('back') || lbl.includes('belakang') || lbl.includes('environment');
            });
            currentCameraId = backCam ? backCam.id : devices[0].id;
            select.value = currentCameraId;
            startScanning(currentCameraId);
        } else {
            document.getElementById('scannerStatusText').innerText = 'Kamera tidak ditemukan pada perangkat ini.';
        }
    }).catch(err => {
        document.getElementById('scannerStatusText').innerHTML = '<span class="text-danger">Akses kamera ditolak atau tidak tersedia: ' + err + '</span>';
    });
}

function startScanning(cameraId) {
    if (html5QrCodeScanner) {
        stopCamera().then(() => startScanning(cameraId));
        return;
    }

    html5QrCodeScanner = new Html5Qrcode("cameraReader");
    const config = { 
        fps: 20, 
        qrbox: { width: 260, height: 180 },
        aspectRatio: 1.33333
    };

    html5QrCodeScanner.start(
        cameraId, 
        config, 
        onScanSuccess, 
        onScanError
    ).then(() => {
        isScanningActive = true;
        document.getElementById('scannerStatusText').innerHTML = '<span class="text-dark fw-bold"><i class="bi bi-camera-video me-1 text-success"></i>Kamera aktif. Arahkan ke barcode NIS siswa...</span>';
    }).catch(err => {
        document.getElementById('scannerStatusText').innerHTML = '<span class="text-danger">Gagal mengaktifkan kamera: ' + err + '</span>';
    });
}

function switchCamera(cameraId) {
    if (cameraId) {
        currentCameraId = cameraId;
        startScanning(cameraId);
    }
}

function stopCamera() {
    if (html5QrCodeScanner && isScanningActive) {
        return html5QrCodeScanner.stop().then(() => {
            html5QrCodeScanner.clear();
            html5QrCodeScanner = null;
            isScanningActive = false;
        }).catch(err => {
            html5QrCodeScanner = null;
            isScanningActive = false;
        });
    }
    return Promise.resolve();
}

function onScanSuccess(decodedText, decodedResult) {
    const now = Date.now();
    // Prevent duplicate scans within 1.5 seconds
    if (decodedText === lastScannedCode && (now - lastScanTime < 1500)) {
        return;
    }
    lastScannedCode = decodedText;
    lastScanTime = now;

    playBeepSound();

    document.getElementById('scannerStatusText').innerHTML = '<span class="text-primary fw-bold">Memproses NIS [' + decodedText + ']...</span>';

    // Fast AJAX lookup without reloading whole page
    fetch('../../api/get-siswa-kantin.php?nis=' + encodeURIComponent(decodedText))
        .then(res => res.json())
        .then(data => {
            if (data.success && data.siswa) {
                const s = data.siswa;
                document.getElementById('scannerStatusText').innerHTML = 
                    `<span class="text-success fw-bold">✅ Siswa Ditemukan: ${s.nama} (${s.nama_kelas}) - Saldo: ${s.formatted_saldo}</span>`;

                // Update input & state
                document.getElementById('inputScanNis').value = s.nis;
                studentBalance = parseFloat(s.saldo);

                // Update Student Info Banner
                const infoCard = document.getElementById('siswaInfoCard');
                infoCard.innerHTML = `
                    <div class="mt-3.5 p-3.5 rounded-4 shadow-sm text-white d-flex align-items-center justify-content-between position-relative overflow-hidden" style="background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%); border-radius: 18px;">
                        <div class="d-flex align-items-center gap-3 position-relative z-1">
                            <div class="rounded-circle bg-white p-2 d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 46px; height: 46px; color: #0d9488;">
                                <i class="bi bi-person-fill fs-4"></i>
                            </div>
                            <div>
                                <h6 class="fw-extrabold text-white mb-0" style="font-size: 1.02rem; font-family: 'Outfit', sans-serif;">${s.nama}</h6>
                                <span class="extra-small text-white-50">NIS: <strong class="text-white">${s.nis}</strong> &bull; Kelas ${s.nama_kelas}</span>
                            </div>
                        </div>
                        <div class="text-end position-relative z-1">
                            <div class="extra-small text-white-50 fw-bold uppercase" style="letter-spacing: 0.5px;">SALDO KANTIN ACTIVE</div>
                            <h4 class="fw-extrabold text-emerald mb-0" style="font-family: 'Outfit', sans-serif; color: #a7f3d0;">${s.formatted_saldo}</h4>
                        </div>
                    </div>`;

                // Update form hidden input & check radio
                document.getElementById('siswaIdInput').value = s.id;
                const rSaldo = document.getElementById('bayarSaldo');
                rSaldo.disabled = false;
                rSaldo.checked = true;

                // Update dropdown if option exists
                const drop = document.getElementById('selectSiswaDropdown');
                if (drop) {
                    drop.value = s.nis;
                }

                // Check auto close setting
                const autoClose = document.getElementById('chkAutoCloseScanner').checked;
                if (autoClose) {
                    setTimeout(() => {
                        closeCameraScanner();
                    }, 800);
                }
            } else {
                document.getElementById('scannerStatusText').innerHTML = 
                    `<span class="text-danger fw-bold">❌ ${data.message || 'Siswa tidak ditemukan'}</span>`;
            }
        })
        .catch(err => {
            document.getElementById('scannerStatusText').innerHTML = `<span class="text-danger">❌ Gagal mengambil data siswa dari server.</span>`;
        });
}

function onScanError(errorMessage) {
    // Frame search loop, silent ignore
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
