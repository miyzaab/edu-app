<?php
/**
 * DASHBOARD POIN KESANTRIAN - UI/UX Modern dengan Glassmorphism
 * Menampilkan poin pelanggaran & penghargaan siswa dengan visualisasi yang menarik
 */

$pageTitle  = 'Poin Kesantrian & Disiplin';
$activePage = 'poin';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('kesantrian');

$pdo = getConnection();
$userId = $_SESSION['user_id'];

// Ambil daftar siswa aktif
$siswaList = $pdo->query("
    SELECT s.id, s.nis, s.nama, k.nama_kelas
    FROM siswa s
    JOIN kelas k ON s.kelas_id = k.id
    WHERE s.status = 'aktif'
    ORDER BY k.nama_kelas ASC, s.nama ASC
")->fetchAll();

// Ambil kategori poin
$kategoriPelanggaran = $pdo->query("
    SELECT * FROM siswa_poin_kategori
    WHERE tipe_poin = 'pelanggaran' AND status = 'aktif'
    ORDER BY nama_kategori ASC
")->fetchAll();

$kategoriPenghargaan = $pdo->query("
    SELECT * FROM siswa_poin_kategori
    WHERE tipe_poin = 'penghargaan' AND status = 'aktif'
    ORDER BY nama_kategori ASC
")->fetchAll();

// Ambil top performers minggu ini
$topPerformers = $pdo->query("
    SELECT s.id, s.nama, s.nis, k.nama_kelas,
        SUM(CASE WHEN spr.tipe_poin = 'penghargaan' THEN spr.nilai_poin ELSE 0 END) as penghargaan,
        SUM(CASE WHEN spr.tipe_poin = 'pelanggaran' THEN spr.nilai_poin ELSE 0 END) as pelanggaran,
        SUM(CASE WHEN spr.tipe_poin = 'penghargaan' THEN spr.nilai_poin ELSE -spr.nilai_poin END) as total
    FROM siswa s
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN siswa_poin_riwayat spr ON s.id = spr.siswa_id 
        AND spr.tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    WHERE s.status = 'aktif'
    GROUP BY s.id
    HAVING total > 0
    ORDER BY total DESC
    LIMIT 10
")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    :root {
        --primary: #8b5cf6;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --info: #06b6d4;
        --light: #f3f4f6;
        --dark: #1f2937;
    }

    body {
        background: linear-gradient(135deg, #f5f3ff 0%, #e0e7ff 100%);
        min-height: 100vh;
    }

    /* Glassmorphism Cards */
    .glass-card {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 20px;
        box-shadow: 0 8px 32px rgba(99, 102, 241, 0.1);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .glass-card:hover {
        background: rgba(255, 255, 255, 0.95);
        box-shadow: 0 20px 50px rgba(99, 102, 241, 0.2);
        transform: translateY(-4px);
    }

    /* Stat Cards */
    .stat-card {
        padding: 2rem;
        border-radius: 16px;
        color: white;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
        animation: shimmer 3s infinite;
    }

    .stat-card.penghargaan {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .stat-card.pelanggaran {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }

    .stat-card.total {
        background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
    }

    .stat-card .number {
        font-size: 3rem;
        font-weight: 900;
        line-height: 1;
        margin-bottom: 0.5rem;
        position: relative;
        z-index: 1;
    }

    .stat-card .label {
        font-size: 0.95rem;
        opacity: 0.95;
        font-weight: 500;
        position: relative;
        z-index: 1;
    }

    /* Modal Styling */
    .modal-header {
        background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
        color: white;
        border: none;
    }

    .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }

    /* Tab Styling */
    .nav-tabs .nav-link {
        color: #6b7280;
        border: none;
        position: relative;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .nav-tabs .nav-link:hover {
        color: #8b5cf6;
    }

    .nav-tabs .nav-link.active {
        color: #8b5cf6;
        background: rgba(139, 92, 246, 0.1);
        border-radius: 10px 10px 0 0;
        border-bottom: 3px solid #8b5cf6;
    }

    /* Category Badge */
    .category-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        background: rgba(139, 92, 246, 0.1);
        color: #8b5cf6;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .category-badge:hover {
        background: #8b5cf6;
        color: white;
    }

    /* Button Styling */
    .btn-poin {
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        font-weight: 500;
        transition: all 0.3s ease;
        border: none;
    }

    .btn-poin-primary {
        background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
        color: white;
    }

    .btn-poin-primary:hover {
        box-shadow: 0 10px 25px rgba(139, 92, 246, 0.3);
        transform: translateY(-2px);
    }

    .btn-poin-success {
        background: #10b981;
        color: white;
    }

    .btn-poin-success:hover {
        background: #059669;
    }

    .btn-poin-danger {
        background: #ef4444;
        color: white;
    }

    .btn-poin-danger:hover {
        background: #dc2626;
    }

    /* Animation */
    @keyframes shimmer {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-slide-in {
        animation: slideIn 0.5s ease-out forwards;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .stat-card {
            padding: 1.5rem;
        }

        .stat-card .number {
            font-size: 2rem;
        }

        .stat-card .label {
            font-size: 0.85rem;
        }
    }

    /* History Table */
    .history-table {
        font-size: 0.95rem;
    }

    .history-table td {
        vertical-align: middle;
        padding: 1rem 0.75rem !important;
        border-color: #e5e7eb;
    }

    .poin-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .poin-badge.penghargaan {
        background: #dcfce7;
        color: #166534;
    }

    .poin-badge.pelanggaran {
        background: #fee2e2;
        color: #991b1b;
    }

    /* Leaderboard */
    .leaderboard-item {
        padding: 1rem;
        margin-bottom: 0.75rem;
        border-radius: 12px;
        background: #f9fafb;
        border-left: 4px solid #8b5cf6;
        transition: all 0.3s ease;
    }

    .leaderboard-item:hover {
        background: #f3f4f6;
        transform: translateX(4px);
    }

    .leaderboard-rank {
        font-size: 1.25rem;
        font-weight: 900;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
        color: white;
    }

    .leaderboard-rank.gold {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    }

    .leaderboard-rank.silver {
        background: linear-gradient(135deg, #d1d5db 0%, #9ca3af 100%);
    }

    .leaderboard-rank.bronze {
        background: linear-gradient(135deg, #fb923c 0%, #ea580c 100%);
    }

    /* Dashboard yang ringkas dan mudah dipindai */
    .poin-dashboard { max-width: 1440px; }
    .poin-hero { padding: 1.5rem 1.75rem; border-radius: 24px; color: #fff; background: linear-gradient(115deg, #312e81, #6d28d9 58%, #8b5cf6); box-shadow: 0 18px 35px rgba(109, 40, 217, .22); }
    .poin-hero .eyebrow { font-size: .72rem; font-weight: 800; letter-spacing: .09em; opacity: .76; }
    .poin-hero h1 { font-size: clamp(1.35rem, 2vw, 1.8rem); font-weight: 800; margin: .25rem 0; }
    .poin-hero p { margin: 0; opacity: .85; font-size: .85rem; }
    .poin-hero .btn { border-radius: 12px; font-weight: 700; }
    .stat-card { min-height: 160px; padding: 1.35rem; border-radius: 22px; box-shadow: 0 12px 24px rgba(30, 41, 59, .10); }
    .stat-card .number { font-size: clamp(2rem, 3vw, 2.65rem); letter-spacing: -.05em; }
    .stat-card .label { font-size: .78rem; font-weight: 700; }
    .stat-card .stat-icon { width: 42px; height: 42px; display: grid; place-items: center; border-radius: 14px; background: rgba(255,255,255,.18); font-size: 1.15rem; margin-bottom: 1rem; position: relative; z-index: 1; }
    .glass-card { border-radius: 22px; box-shadow: 0 10px 28px rgba(71, 85, 105, .08); }
    .dashboard-card-title { font-family: 'Outfit', sans-serif; font-size: 1.05rem; font-weight: 800; }
    .quick-action { display: flex; align-items: center; gap: .85rem; padding: .9rem 1rem; text-align: left; font-size: .88rem; font-weight: 700; }
    .quick-action i { width: 32px; height: 32px; display: grid; place-items: center; border-radius: 10px; background: rgba(255,255,255,.7); }
    .leaderboard-item { margin-bottom: .55rem; padding: .8rem; }
    @media (max-width: 576px) { .poin-dashboard { padding: 1rem !important; } .poin-hero { padding: 1.25rem; } }
</style>

<!-- HEADER SECTION -->
<div class="container-fluid px-4 py-4">
    <section class="poin-hero d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <div class="eyebrow"><i class="bi bi-moon-stars-fill me-1"></i> KESANTRIAN & DISIPLIN</div>
            <h1>Dashboard Poin Santri</h1>
            <p>Ringkasan aktivitas dan apresiasi siswa untuk minggu berjalan.</p>
        </div>
        <button class="btn btn-light px-3 py-2" data-bs-toggle="modal" data-bs-target="#tambahPoinModal">
            <i class="bi bi-plus-circle-fill me-2"></i>Catat Poin
        </button>
    </section>

    <!-- STAT CARDS -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="stat-card penghargaan animate-slide-in">
                <div class="stat-icon"><i class="bi bi-trophy-fill"></i></div>
                <div class="number" id="total-penghargaan">0</div>
                <div class="label">Total Penghargaan (Minggu Ini)</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card pelanggaran animate-slide-in">
                <div class="stat-icon"><i class="bi bi-exclamation-octagon-fill"></i></div>
                <div class="number" id="total-pelanggaran">0</div>
                <div class="label">Total Pelanggaran (Minggu Ini)</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card total animate-slide-in">
                <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                <div class="number" id="total-siswa">0</div>
                <div class="label">Total Siswa Aktif</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- TOP PERFORMERS -->
        <div class="col-lg-6">
            <div class="glass-card p-4">
                <h5 class="dashboard-card-title mb-4">
                    <i class="bi bi-star-fill text-warning me-2"></i>Top Performers Minggu Ini
                </h5>
                <div id="top-performers-container">
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 opacity-25"></i>
                        <p>Memuat data...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="col-lg-6">
            <div class="glass-card p-4">
                <h5 class="dashboard-card-title mb-4">
                    <i class="bi bi-lightning-fill text-info me-2"></i>Aksi Cepat
                </h5>
                
                <div class="d-grid gap-3">
                    <button class="btn btn-outline-success quick-action" data-bs-toggle="modal" data-bs-target="#tambahPoinModal" onclick="setPoinType('penghargaan')">
                        <i class="bi bi-hand-thumbs-up me-2"></i>Tambah Penghargaan
                    </button>
                    <button class="btn btn-outline-danger quick-action" data-bs-toggle="modal" data-bs-target="#tambahPoinModal" onclick="setPoinType('pelanggaran')">
                        <i class="bi bi-exclamation-triangle me-2"></i>Tambah Pelanggaran
                    </button>
                    <button class="btn btn-outline-primary quick-action" onclick="goToLeaderboard()">
                        <i class="bi bi-list-ul me-2"></i>Lihat Leaderboard
                    </button>
                    <button class="btn btn-outline-info quick-action" onclick="goToHistory()">
                        <i class="bi bi-clock-history me-2"></i>Riwayat Poin
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: TAMBAH POIN -->
<div class="modal fade" id="tambahPoinModal" tabindex="-1" aria-labelledby="tambahPoinLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="tambahPoinLabel">
                    <i class="bi bi-plus-circle me-2"></i>Tambah Poin Siswa
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formTambahPoin">
                    <!-- Pilih Siswa -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Pilih Siswa</label>
                        <select class="form-select form-select-lg rounded-3" id="siswaSelect" required>
                            <option value="">-- Pilih Siswa --</option>
                            <?php foreach ($siswaList as $s): ?>
                                <option value="<?= $s['id'] ?>">
                                    <?= $s['nama'] ?> (<?= $s['nis'] ?>) - <?= $s['nama_kelas'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Tabs untuk Tipe Poin -->
                    <ul class="nav nav-tabs mb-4" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-penghargaan" data-bs-toggle="tab" data-bs-target="#tabPenghargaan" type="button" role="tab" aria-selected="true">
                                <i class="bi bi-hand-thumbs-up me-2"></i>Penghargaan
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-pelanggaran" data-bs-toggle="tab" data-bs-target="#tabPelanggaran" type="button" role="tab" aria-selected="false">
                                <i class="bi bi-exclamation-triangle me-2"></i>Pelanggaran
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Penghargaan -->
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tabPenghargaan" role="tabpanel">
                            <div class="mb-4">
                                <label class="form-label fw-bold">Kategori Penghargaan</label>
                                <div class="row g-3" id="kategoriPenghargaanContainer">
                                    <div class="col-12 text-center text-muted py-3">
                                        <i class="bi bi-hourglass-split"></i> Memuat kategori...
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Pelanggaran -->
                        <div class="tab-pane fade" id="tabPelanggaran" role="tabpanel">
                            <div class="mb-4">
                                <label class="form-label fw-bold">Kategori Pelanggaran</label>
                                <div class="row g-3" id="kategoriPelanggaranContainer">
                                    <div class="col-12 text-center text-muted py-3">
                                        <i class="bi bi-hourglass-split"></i> Memuat kategori...
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kategori Poin (Hidden) -->
                    <input type="hidden" id="kategoriSelect">

                    <!-- Deskripsi -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Deskripsi (Opsional)</label>
                        <textarea class="form-control rounded-3" id="deskripsiInput" rows="3" placeholder="Jelaskan alasan pemberian poin..."></textarea>
                    </div>

                    <!-- Tanggal & Jam -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tanggal</label>
                            <input type="date" class="form-control rounded-3" id="tanggalInput" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Jam</label>
                            <input type="time" class="form-control rounded-3" id="jamInput" value="<?= date('H:i') ?>">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-poin-primary rounded-3" onclick="submitPoin()">
                    <i class="bi bi-check-circle me-2"></i>Simpan Poin
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const apiUrl = '<?= BASE_URL ?>/pages/halaqah/poin_api.php';

    // Load kategori poin
    async function loadKategori() {
        try {
            // Load penghargaan
            const resPenghargaan = await fetch(`${apiUrl}?action=get_kategori&tipe=penghargaan`);
            const dataPenghargaan = await resPenghargaan.json();
            
            if (dataPenghargaan.success) {
                const container = document.getElementById('kategoriPenghargaanContainer');
                container.innerHTML = dataPenghargaan.data.map(kat => `
                    <div class="col-md-6">
                        <input type="radio" name="kategoriPenghargaan" value="${kat.id}" id="kat-${kat.id}" 
                            onchange="selectKategori(${kat.id})" class="btn-check">
                        <label for="kat-${kat.id}" class="btn btn-outline-success w-100 rounded-3">
                            <i class="bi ${kat.icon} me-2"></i>${kat.nama_kategori}
                            <br><small>${kat.nilai_poin} poin</small>
                        </label>
                    </div>
                `).join('');
            }

            // Load pelanggaran
            const resPelanggaran = await fetch(`${apiUrl}?action=get_kategori&tipe=pelanggaran`);
            const dataPelanggaran = await resPelanggaran.json();
            
            if (dataPelanggaran.success) {
                const container = document.getElementById('kategoriPelanggaranContainer');
                container.innerHTML = dataPelanggaran.data.map(kat => `
                    <div class="col-md-6">
                        <input type="radio" name="kategoriPelanggaran" value="${kat.id}" id="kat-${kat.id}" 
                            onchange="selectKategori(${kat.id})" class="btn-check">
                        <label for="kat-${kat.id}" class="btn btn-outline-danger w-100 rounded-3">
                            <i class="bi ${kat.icon} me-2"></i>${kat.nama_kategori}
                            <br><small>${kat.nilai_poin} poin</small>
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

    function setPoinType(tipe) {
        if (tipe === 'penghargaan') {
            document.getElementById('tab-penghargaan').click();
        } else {
            document.getElementById('tab-pelanggaran').click();
        }
    }

    async function submitPoin() {
        const siswaId = document.getElementById('siswaSelect').value;
        const kategoriId = document.getElementById('kategoriSelect').value;
        const deskripsi = document.getElementById('deskripsiInput').value;
        const tanggal = document.getElementById('tanggalInput').value;
        const jam = document.getElementById('jamInput').value;

        if (!siswaId) return alert('Pilih siswa terlebih dahulu');
        if (!kategoriId) return alert('Pilih kategori poin');

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
                alert('✅ Poin berhasil ditambahkan!');
                document.getElementById('formTambahPoin').reset();
                bootstrap.Modal.getInstance(document.getElementById('tambahPoinModal')).hide();
                loadStats();
            } else {
                alert('❌ ' + data.error);
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }

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
            document.getElementById('top-performers-container').innerHTML = '<div class="text-center py-5 text-muted"><i class="bi bi-wifi-off fs-3 d-block mb-2"></i>Ringkasan belum dapat dimuat. <button class="btn btn-sm btn-link p-0" onclick="loadStats()">Coba lagi</button></div>';
        }
    }

    function renderTopPerformers(items) {
        const container = document.getElementById('top-performers-container');
        if (items.length > 0) {
            container.innerHTML = items.map((item, idx) => {
                    let rankClass = '';
                    if (idx === 0) rankClass = 'gold';
                    else if (idx === 1) rankClass = 'silver';
                    else if (idx === 2) rankClass = 'bronze';

                    return `
                        <div class="leaderboard-item">
                            <div class="d-flex align-items-center gap-3">
                                <div class="leaderboard-rank ${rankClass}">${idx + 1}</div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold">${item.nama}</div>
                                    <small class="text-muted">${item.nis} • ${item.nama_kelas}</small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold" style="color: #10b981;">+${item.poin_penghargaan || 0}</div>
                                    <small class="text-danger">-${item.poin_pelanggaran || 0}</small>
                                </div>
                            </div>
                        </div>
                    `;
            }).join('');
        } else {
            container.innerHTML = '<div class="text-center py-5 text-muted"><i class="bi bi-stars fs-1 opacity-25"></i><p class="mb-0 mt-2">Belum ada poin positif minggu ini</p></div>';
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
