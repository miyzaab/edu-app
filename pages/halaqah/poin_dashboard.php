<?php
/**
 * DASHBOARD POIN KESANTRIAN - Ultra Modern UI/UX (Glassmorphism & Vibrant Design)
 * Menampilkan poin pelanggaran & penghargaan siswa dengan visualisasi yang menarik & interaktif
 */

$pageTitle = 'Poin Kesantrian & Disiplin';
$activePage = 'poin_dashboard';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('kesantrian');

$pdo = getConnection();
$userId = $_SESSION['user_id'] ?? null;

// Ambil daftar siswa aktif
$siswaList = $pdo->query("
    SELECT s.id, s.nis, s.nama, k.nama_kelas
    FROM siswa s
    JOIN kelas k ON s.kelas_id = k.id
    WHERE s.status = 'aktif'
    ORDER BY k.tingkat ASC, k.nama_kelas ASC, s.nama ASC
")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="poin-dashboard-wrapper">
    <!-- 1. HERO HEADER BANNER -->
    <div class="poin-hero-banner mb-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 position-relative"
            style="z-index: 2;">
            <div>
                <div class="hero-badge-pill mb-2">
                    <i class="bi bi-moon-stars-fill me-1.5"></i> KESANTRIAN & DISIPLIN
                </div>
                <h1 class="hero-main-title">Dashboard Poin Santri</h1>
                <p class="hero-main-desc">Ringkasan aktivitas poin kedisiplinan dan apresiasi santri teladan minggu
                    berjalan.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-hero-cta" data-bs-toggle="modal" data-bs-target="#tambahPoinModal">
                    <i class="bi bi-plus-circle-fill"></i> Catat Poin
                </button>
                <button type="button" class="btn btn-hero-secondary" onclick="goToLeaderboard()">
                    <i class="bi bi-trophy-fill"></i> Leaderboard
                </button>
            </div>
        </div>
    </div>

    <!-- 2. VIBRANT STAT CARDS (Green, Red, Purple) -->
    <div class="row g-3 mb-4">
        <!-- Card 1: Penghargaan (Green) -->
        <div class="col-md-4">
            <div class="kpi-card kpi-card-green">
                <div class="kpi-icon-box">
                    <i class="bi bi-trophy-fill"></i>
                </div>
                <div class="kpi-value-num" id="total-penghargaan">0</div>
                <div class="kpi-title-text">Total Penghargaan (Minggu Ini)</div>
            </div>
        </div>

        <!-- Card 2: Pelanggaran (Red) -->
        <div class="col-md-4">
            <div class="kpi-card kpi-card-red">
                <div class="kpi-icon-box">
                    <i class="bi bi-exclamation-octagon-fill"></i>
                </div>
                <div class="kpi-value-num" id="total-pelanggaran">0</div>
                <div class="kpi-title-text">Total Pelanggaran (Minggu Ini)</div>
            </div>
        </div>

        <!-- Card 3: Total Siswa Aktif (Purple) -->
        <div class="col-md-4">
            <div class="kpi-card kpi-card-purple">
                <div class="kpi-icon-box">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="kpi-value-num" id="total-siswa"><?= count($siswaList) ?></div>
                <div class="kpi-title-text">Total Siswa Aktif Terdata</div>
            </div>
        </div>
    </div>

    <!-- 3. MAIN CONTENT GRID -->
    <div class="row g-4 mb-4">
        <!-- TOP PERFORMERS -->
        <div class="col-lg-7">
            <div class="content-glass-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h5 class="fw-black text-dark mb-1 d-flex align-items-center gap-2">
                            <i class="bi bi-star-fill text-warning"></i> Top Performers Minggu Ini
                        </h5>
                        <small class="text-muted">Santri dengan akumulasi poin positif tertinggi</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold"
                        onclick="goToLeaderboard()">
                        Lihat Semua <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>

                <div id="top-performers-container">
                    <div class="text-center py-5 text-muted">
                        <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                        <p class="small mb-0">Memuat data santri teladan...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- QUICK ACTIONS & INFO -->
        <div class="col-lg-5">
            <div class="content-glass-card h-100 p-4">
                <div class="mb-4">
                    <h5 class="fw-black text-dark mb-1 d-flex align-items-center gap-2">
                        <i class="bi bi-lightning-charge-fill text-warning"></i> Aksi Cepat & Navigasi
                    </h5>
                    <small class="text-muted">Akses instan pencatatan dan monitoring poin</small>
                </div>

                <div class="d-grid gap-3">
                    <div class="action-tile action-tile-green" onclick="setPoinType('penghargaan')"
                        data-bs-toggle="modal" data-bs-target="#tambahPoinModal">
                        <div class="action-tile-icon">
                            <i class="bi bi-hand-thumbs-up-fill"></i>
                        </div>
                        <div class="action-tile-content">
                            <div class="action-tile-title">Tambah Penghargaan</div>
                            <div class="action-tile-desc">Beri poin prestasi, hafalan, atau akhlak terpuji</div>
                        </div>
                        <i class="bi bi-chevron-right action-tile-arrow"></i>
                    </div>

                    <div class="action-tile action-tile-red" onclick="setPoinType('pelanggaran')" data-bs-toggle="modal"
                        data-bs-target="#tambahPoinModal">
                        <div class="action-tile-icon">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                        </div>
                        <div class="action-tile-content">
                            <div class="action-tile-title">Catat Pelanggaran</div>
                            <div class="action-tile-desc">Input catatan kedisiplinan dan tata tertib</div>
                        </div>
                        <i class="bi bi-chevron-right action-tile-arrow"></i>
                    </div>

                    <div class="action-tile action-tile-purple" onclick="goToLeaderboard()">
                        <div class="action-tile-icon">
                            <i class="bi bi-trophy-fill"></i>
                        </div>
                        <div class="action-tile-content">
                            <div class="action-tile-title">Leaderboard Peringkat</div>
                            <div class="action-tile-desc">Lihat ranking poin seluruh santri per kelas</div>
                        </div>
                        <i class="bi bi-chevron-right action-tile-arrow"></i>
                    </div>

                    <div class="action-tile action-tile-blue" onclick="goToHistory()">
                        <div class="action-tile-icon">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div class="action-tile-content">
                            <div class="action-tile-title">Riwayat & Log Poin</div>
                            <div class="action-tile-desc">Daftar lengkap catatan poin masuk dari musyrif/guru</div>
                        </div>
                        <i class="bi bi-chevron-right action-tile-arrow"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: TAMBAH POIN (ULTRA MODERN DESIGN) -->
<div class="modal fade" id="tambahPoinModal" tabindex="-1" aria-labelledby="tambahPoinLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-gradient-purple text-white p-4 border-0">
                <div>
                    <h5 class="modal-title fw-black mb-1" id="tambahPoinLabel">
                        <i class="bi bi-star-fill me-2 text-warning"></i> Catat Poin Santri
                    </h5>
                    <small class="text-white-50">Input poin penghargaan atau pelanggaran santri dengan cepat</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" style="background: #f8fafc;">
                <form id="formTambahPoin">
                    <!-- 1. PILIH SISWA -->
                    <div class="card border-0 shadow-xs rounded-3 p-3 mb-3 bg-white">
                        <label class="form-label small fw-bold text-dark mb-1">
                            <i class="bi bi-person-fill text-primary me-1"></i> Pilih Santri Target
                        </label>
                        <select class="form-select rounded-3 py-2" id="siswaSelect" required>
                            <option value="">-- Pilih Santri --</option>
                            <?php foreach ($siswaList as $s): ?>
                                <option value="<?= $s['id'] ?>">
                                    <?= htmlspecialchars($s['nama']) ?> (<?= htmlspecialchars($s['nis']) ?>) —
                                    <?= htmlspecialchars($s['nama_kelas']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 2. TABS TIPE POIN -->
                    <div class="card border-0 shadow-xs rounded-3 p-3 mb-3 bg-white">
                        <label class="form-label small fw-bold text-dark mb-2">
                            <i class="bi bi-tags-fill text-primary me-1"></i> Jenis Poin
                        </label>
                        <div class="d-flex gap-2 mb-3">
                            <button type="button" class="btn btn-type-tab active w-50 py-2.5 fw-bold rounded-3"
                                id="tab-penghargaan" onclick="switchTab('penghargaan')">
                                <i class="bi bi-hand-thumbs-up-fill me-1.5 text-success"></i> Penghargaan (Positif)
                            </button>
                            <button type="button" class="btn btn-type-tab w-50 py-2.5 fw-bold rounded-3"
                                id="tab-pelanggaran" onclick="switchTab('pelanggaran')">
                                <i class="bi bi-exclamation-triangle-fill me-1.5 text-danger"></i> Pelanggaran
                                (Disiplin)
                            </button>
                        </div>

                        <!-- Kategori Grid -->
                        <div id="container-penghargaan">
                            <div class="small fw-bold text-muted mb-2">Pilih Kategori Penghargaan:</div>
                            <div class="row g-2" id="kategoriPenghargaanContainer">
                                <div class="col-12 text-center text-muted py-3">
                                    <div class="spinner-border spinner-border-sm text-success me-1"></div> Memuat
                                    kategori...
                                </div>
                            </div>
                        </div>

                        <div id="container-pelanggaran" style="display: none;">
                            <div class="small fw-bold text-muted mb-2">Pilih Kategori Pelanggaran:</div>
                            <div class="row g-2" id="kategoriPelanggaranContainer">
                                <div class="col-12 text-center text-muted py-3">
                                    <div class="spinner-border spinner-border-sm text-danger me-1"></div> Memuat
                                    kategori...
                                </div>
                            </div>
                        </div>

                        <input type="hidden" id="kategoriSelect" name="kategori_id">
                    </div>

                    <!-- 3. DESKRIPSI & WAKTU -->
                    <div class="card border-0 shadow-xs rounded-3 p-3 bg-white">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark mb-1">
                                <i class="bi bi-pencil-square text-primary me-1"></i> Keterangan / Catatan (Opsional)
                            </label>
                            <textarea class="form-control rounded-3" id="deskripsiInput" rows="2"
                                placeholder="Contoh: Juara 1 MHQ, Membantu santri sakit, Terlambat shalat shubuh..."></textarea>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">Tanggal</label>
                                <input type="date" class="form-control rounded-3" id="tanggalInput"
                                    value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">Waktu / Jam</label>
                                <input type="time" class="form-control rounded-3" id="jamInput"
                                    value="<?= date('H:i') ?>">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 p-3 bg-white">
                <button type="button" class="btn btn-light rounded-pill px-4 fw-bold"
                    data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary-custom rounded-pill px-4 fw-bold shadow-sm"
                    onclick="submitPoin()">
                    <i class="bi bi-check-circle-fill me-1.5"></i> Simpan Poin
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    /* ==========================================================================
       DASHBOARD POIN KESANTRIAN - CUSTOM STYLES
       ========================================================================== */
    .poin-dashboard-wrapper {
        width: 100%;
        max-width: 1440px;
        margin: 0 auto;
    }

    /* 1. HERO BANNER */
    .poin-hero-banner {
        background: linear-gradient(135deg, #6d28d9 0%, #5b21b6 100%);
        border-radius: 24px;
        padding: 30px 34px;
        color: #ffffff;
        box-shadow: 0 12px 30px -5px rgba(109, 40, 217, 0.35);
        position: relative;
        overflow: hidden;
    }

    .poin-hero-banner::after {
        content: '';
        position: absolute;
        top: -40px;
        right: -40px;
        width: 200px;
        height: 200px;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.16) 0%, rgba(255, 255, 255, 0) 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .hero-badge-pill {
        display: inline-flex;
        align-items: center;
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(8px);
        padding: 4px 14px;
        border-radius: 50px;
        font-size: 0.73rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .hero-main-title {
        font-size: clamp(1.6rem, 2.5vw, 2.1rem);
        font-weight: 900;
        letter-spacing: -0.02em;
        margin-bottom: 6px;
        color: #ffffff;
    }

    .hero-main-desc {
        font-size: 0.92rem;
        color: rgba(255, 255, 255, 0.85);
        margin-bottom: 0;
    }

    .btn-hero-cta {
        background: #ffffff !important;
        color: #5b21b6 !important;
        font-weight: 800 !important;
        border-radius: 50px !important;
        padding: 10px 22px !important;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12) !important;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
        border: none;
    }

    .btn-hero-cta:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.18) !important;
        color: #4c1d95 !important;
    }

    .btn-hero-secondary {
        background: rgba(255, 255, 255, 0.15) !important;
        color: #ffffff !important;
        font-weight: 700 !important;
        border-radius: 50px !important;
        padding: 10px 20px !important;
        backdrop-filter: blur(6px);
        border: 1px solid rgba(255, 255, 255, 0.25) !important;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }

    .btn-hero-secondary:hover {
        background: rgba(255, 255, 255, 0.25) !important;
        color: #ffffff !important;
        transform: translateY(-2px);
    }

    /* 2. VIBRANT KPI CARDS */
    .kpi-card {
        border-radius: 22px;
        padding: 24px 26px;
        color: #ffffff;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 165px;
        transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.22s ease;
        position: relative;
        overflow: hidden;
    }

    .kpi-card:hover {
        transform: translateY(-4px);
    }

    .kpi-card-green {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.4);
    }

    .kpi-card-red {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        box-shadow: 0 10px 25px -5px rgba(239, 68, 68, 0.4);
    }

    .kpi-card-purple {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        box-shadow: 0 10px 25px -5px rgba(139, 92, 246, 0.4);
    }

    .kpi-icon-box {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.22);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        color: #ffffff;
        backdrop-filter: blur(4px);
    }

    .kpi-value-num {
        font-size: clamp(2.3rem, 3.2vw, 3rem);
        font-weight: 900;
        letter-spacing: -0.03em;
        line-height: 1;
        margin-top: 14px;
        margin-bottom: 4px;
        color: #ffffff;
    }

    .kpi-title-text {
        font-size: 0.86rem;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.9);
    }

    /* 3. CONTENT GLASS CARD */
    .content-glass-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.04);
    }

    /* 4. LEADERBOARD LIST ITEM */
    .performer-row {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 16px;
        border-radius: 16px;
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        margin-bottom: 10px;
        transition: all 0.2s ease;
    }

    .performer-row:hover {
        background: #ffffff;
        border-color: #cbd5e1;
        transform: translateX(4px);
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
    }

    .rank-badge-circle {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        font-size: 0.95rem;
        color: #ffffff;
        flex-shrink: 0;
    }

    .rank-gold {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        box-shadow: 0 4px 10px rgba(245, 158, 11, 0.35);
    }

    .rank-silver {
        background: linear-gradient(135deg, #94a3b8, #64748b);
        box-shadow: 0 4px 10px rgba(148, 163, 184, 0.35);
    }

    .rank-bronze {
        background: linear-gradient(135deg, #ea580c, #c2410c);
        box-shadow: 0 4px 10px rgba(234, 88, 12, 0.35);
    }

    .rank-default {
        background: #e2e8f0;
        color: #475569;
    }

    /* 5. ACTION TILES */
    .action-tile {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 15px 18px;
        border-radius: 18px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        cursor: pointer;
        transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        text-decoration: none !important;
    }

    .action-tile:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    }

    .action-tile-icon {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
    }

    .action-tile-green .action-tile-icon {
        background: #ecfdf5;
        color: #059669;
    }

    .action-tile-green:hover {
        border-color: #10b981;
    }

    .action-tile-red .action-tile-icon {
        background: #fef2f2;
        color: #dc2626;
    }

    .action-tile-red:hover {
        border-color: #ef4444;
    }

    .action-tile-purple .action-tile-icon {
        background: #f5f3ff;
        color: #7c3aed;
    }

    .action-tile-purple:hover {
        border-color: #8b5cf6;
    }

    .action-tile-blue .action-tile-icon {
        background: #eff6ff;
        color: #2563eb;
    }

    .action-tile-blue:hover {
        border-color: #3b82f6;
    }

    .action-tile-content {
        flex-grow: 1;
    }

    .action-tile-title {
        font-size: 0.95rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 2px;
    }

    .action-tile-desc {
        font-size: 0.78rem;
        color: #64748b;
    }

    .action-tile-arrow {
        font-size: 1rem;
        color: #94a3b8;
        transition: transform 0.2s ease;
    }

    .action-tile:hover .action-tile-arrow {
        transform: translateX(3px);
        color: #1e293b;
    }

    /* Modal Styling */
    .bg-gradient-purple {
        background: linear-gradient(135deg, #6d28d9 0%, #5b21b6 100%);
    }

    .btn-type-tab {
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        color: #64748b;
        transition: all 0.2s ease;
    }

    .btn-type-tab.active {
        background: #ffffff !important;
        border-color: #7c3aed !important;
        color: #1e293b !important;
        box-shadow: 0 4px 12px rgba(124, 58, 237, 0.15) !important;
    }

    .kat-choice-card {
        display: block;
        padding: 10px 14px;
        border: 1.5px solid #e2e8f0;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s ease;
        background: #ffffff;
    }

    .btn-check:checked+.kat-choice-card.kat-green {
        border-color: #10b981;
        background: #ecfdf5;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
    }

    .btn-check:checked+.kat-choice-card.kat-red {
        border-color: #ef4444;
        background: #fef2f2;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
    }
</style>

<script>
    const apiUrl = '<?= BASE_URL ?>/pages/halaqah/poin_api.php';

    // 1. Load Kategori Poin
    async function loadKategori() {
        try {
            // Load Penghargaan
            const resPenghargaan = await fetch(`${apiUrl}?action=get_kategori&tipe=penghargaan`);
            const dataPenghargaan = await resPenghargaan.json();

            if (dataPenghargaan.success) {
                const container = document.getElementById('kategoriPenghargaanContainer');
                container.innerHTML = dataPenghargaan.data.map(kat => `
                    <div class="col-md-6">
                        <input type="radio" name="kategoriPoinRadio" value="${kat.id}" id="kat-${kat.id}" 
                            onchange="selectKategori(${kat.id})" class="btn-check">
                        <label for="kat-${kat.id}" class="kat-choice-card kat-green">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="fw-bold text-dark small"><i class="bi ${kat.icon || 'bi-star-fill'} text-success me-1"></i> ${kat.nama_kategori}</div>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-monospace">+${kat.nilai_poin}</span>
                            </div>
                        </label>
                    </div>
                `).join('');
            }

            // Load Pelanggaran
            const resPelanggaran = await fetch(`${apiUrl}?action=get_kategori&tipe=pelanggaran`);
            const dataPelanggaran = await resPelanggaran.json();

            if (dataPelanggaran.success) {
                const container = document.getElementById('kategoriPelanggaranContainer');
                container.innerHTML = dataPelanggaran.data.map(kat => `
                    <div class="col-md-6">
                        <input type="radio" name="kategoriPoinRadio" value="${kat.id}" id="kat-${kat.id}" 
                            onchange="selectKategori(${kat.id})" class="btn-check">
                        <label for="kat-${kat.id}" class="kat-choice-card kat-red">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="fw-bold text-dark small"><i class="bi ${kat.icon || 'bi-exclamation-triangle-fill'} text-danger me-1"></i> ${kat.nama_kategori}</div>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill font-monospace">-${kat.nilai_poin}</span>
                            </div>
                        </label>
                    </div>
                `).join('');
            }
        } catch (error) {
            console.error('Error loading kategori:', error);
        }
    }

    function selectKategori(id) {
        document.getElementById('kategoriSelect').value = id;
    }

    function switchTab(tipe) {
        const tabPenghargaan = document.getElementById('tab-penghargaan');
        const tabPelanggaran = document.getElementById('tab-pelanggaran');
        const contPenghargaan = document.getElementById('container-penghargaan');
        const contPelanggaran = document.getElementById('container-pelanggaran');

        // Reset hidden kategori
        document.getElementById('kategoriSelect').value = '';
        document.querySelectorAll('input[name="kategoriPoinRadio"]').forEach(r => r.checked = false);

        if (tipe === 'penghargaan') {
            tabPenghargaan.classList.add('active');
            tabPelanggaran.classList.remove('active');
            contPenghargaan.style.display = 'block';
            contPelanggaran.style.display = 'none';
        } else {
            tabPelanggaran.classList.add('active');
            tabPenghargaan.classList.remove('active');
            contPelanggaran.style.display = 'block';
            contPenghargaan.style.display = 'none';
        }
    }

    function setPoinType(tipe) {
        switchTab(tipe);
    }

    // 2. Submit Poin
    async function submitPoin() {
        const siswaId = document.getElementById('siswaSelect').value;
        const kategoriId = document.getElementById('kategoriSelect').value;
        const deskripsi = document.getElementById('deskripsiInput').value;
        const tanggal = document.getElementById('tanggalInput').value;
        const jam = document.getElementById('jamInput').value;

        if (!siswaId) {
            alert('Silakan pilih santri terlebih dahulu.');
            return;
        }
        if (!kategoriId) {
            alert('Silakan klik salah satu kategori poin.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'tambah_poin');
        formData.append('siswa_id', siswaId);
        formData.append('kategori_id', kategoriId);
        formData.append('deskripsi', deskripsi);
        formData.append('tanggal', tanggal);
        formData.append('jam', jam);

        try {
            const response = await fetch(apiUrl, { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                alert('✅ Poin santri berhasil dicatat!');
                document.getElementById('formTambahPoin').reset();
                document.getElementById('kategoriSelect').value = '';
                bootstrap.Modal.getInstance(document.getElementById('tambahPoinModal')).hide();
                loadStats();
            } else {
                alert('❌ Gagal: ' + (data.error || 'Terjadi kesalahan sistem'));
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }

    // 3. Load Dashboard Stats & Top Performers
    async function loadStats() {
        try {
            const res = await fetch(`${apiUrl}?action=get_dashboard_summary`);
            const data = await res.json();

            if (data.success) {
                const summary = data.data;
                const formatNumber = value => Number(value || 0).toLocaleString('id-ID');
                document.getElementById('total-penghargaan').textContent = formatNumber(summary.total_penghargaan);
                document.getElementById('total-pelanggaran').textContent = formatNumber(summary.total_pelanggaran);
                document.getElementById('total-siswa').textContent = formatNumber(summary.total_siswa);
                renderTopPerformers(summary.top_performers || []);
            } else {
                throw new Error(data.error || 'Ringkasan poin tidak dapat dimuat');
            }
        } catch (error) {
            console.error('Error loading stats:', error);
            document.getElementById('top-performers-container').innerHTML = '<div class="text-center py-4 text-muted"><i class="bi bi-exclamation-circle fs-3 d-block mb-1"></i>Gagal memuat ringkasan. <button class="btn btn-sm btn-link" onclick="loadStats()">Coba lagi</button></div>';
        }
    }

    function renderTopPerformers(items) {
        const container = document.getElementById('top-performers-container');
        if (items.length > 0) {
            container.innerHTML = items.map((item, idx) => {
                let rankClass = 'rank-default';
                let rankLabel = idx + 1;
                if (idx === 0) { rankClass = 'rank-gold'; rankLabel = '🥇'; }
                else if (idx === 1) { rankClass = 'rank-silver'; rankLabel = '🥈'; }
                else if (idx === 2) { rankClass = 'rank-bronze'; rankLabel = '🥉'; }

                return `
                    <div class="performer-row">
                        <div class="rank-badge-circle ${rankClass}">${rankLabel}</div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-bold text-dark text-truncate" style="font-size: 0.92rem;">${item.nama}</div>
                            <div class="text-muted extra-small font-monospace">
                                <span>${item.nis}</span> • <span class="badge bg-light text-dark border rounded-pill px-2">${item.nama_kelas}</span>
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <div class="fw-black text-success" style="font-size: 0.95rem;">+${item.poin_penghargaan || 0}</div>
                            ${item.poin_pelanggaran > 0 ? `<small class="text-danger fw-bold">-${item.poin_pelanggaran}</small>` : '<small class="text-muted opacity-50">0</small>'}
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            container.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-stars fs-1 text-warning opacity-50 mb-2 d-block"></i>
                    <p class="mb-0 fw-bold">Belum ada poin positif tercatat minggu ini.</p>
                    <small class="text-muted">Jadilah yang pertama memberikan apresiasi santri!</small>
                </div>
            `;
        }
    }

    function goToLeaderboard() {
        window.location.href = '<?= BASE_URL ?>/pages/halaqah/poin_leaderboard.php';
    }

    function goToHistory() {
        window.location.href = '<?= BASE_URL ?>/pages/halaqah/poin_history.php';
    }

    // Initial load
    document.addEventListener('DOMContentLoaded', () => {
        loadKategori();
        loadStats();
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>