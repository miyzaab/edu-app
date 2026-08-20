<?php
/**
 * LEADERBOARD POIN KESANTRIAN - Ranking Siswa
 * Menampilkan ranking siswa berdasarkan total poin penghargaan - pelanggaran
 */

$pageTitle  = 'Leaderboard Poin Kesantrian';
$activePage = 'poin_leaderboard';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('kesantrian');

$pdo = getConnection();
$bulan = (int)($_GET['bulan'] ?? date('m'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));
$tipeFilter = $_GET['tipe'] ?? 'total'; // 'total', 'penghargaan', 'pelanggaran'

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    :root {
        --primary: #8b5cf6;
        --success: #10b981;
        --danger: #ef4444;
        --info: #06b6d4;
        --light: #f3f4f6;
    }

    body {
        background: linear-gradient(135deg, #f5f3ff 0%, #e0e7ff 100%);
    }

    .glass-card {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 20px;
        box-shadow: 0 8px 32px rgba(99, 102, 241, 0.1);
    }

    .rank-badge {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        font-size: 1.5rem;
        font-weight: 900;
        color: white;
        margin-right: 1rem;
    }

    .rank-1 { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); }
    .rank-2 { background: linear-gradient(135deg, #d1d5db 0%, #9ca3af 100%); }
    .rank-3 { background: linear-gradient(135deg, #fb923c 0%, #ea580c 100%); }
    .rank-default { background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); }

    .leaderboard-row {
        display: flex;
        align-items: center;
        padding: 1.5rem;
        margin-bottom: 1rem;
        border-radius: 15px;
        background: white;
        border-left: 4px solid #8b5cf6;
        transition: all 0.3s ease;
    }

    .leaderboard-row:hover {
        box-shadow: 0 10px 30px rgba(139, 92, 246, 0.2);
        transform: translateX(5px);
    }

    .student-info {
        flex: 1;
    }

    .student-name {
        font-size: 1.1rem;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 0.25rem;
    }

    .student-class {
        font-size: 0.9rem;
        color: #6b7280;
    }

    .points-section {
        display: flex;
        gap: 2rem;
        align-items: center;
    }

    .points-item {
        text-align: center;
    }

    .points-value {
        font-size: 1.5rem;
        font-weight: 900;
        line-height: 1;
    }

    .points-label {
        font-size: 0.8rem;
        color: #6b7280;
        margin-top: 0.25rem;
    }

    .medal-icon {
        font-size: 2rem;
        margin-right: 0.5rem;
    }

    /* Filter Pills */
    .filter-pills {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-bottom: 2rem;
    }

    .filter-pill {
        padding: 0.75rem 1.5rem;
        border: 2px solid #e5e7eb;
        border-radius: 25px;
        background: white;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .filter-pill.active {
        background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
        color: white;
        border-color: #8b5cf6;
    }

    .filter-pill:hover {
        border-color: #8b5cf6;
        color: #8b5cf6;
    }

    .month-selector {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        margin-bottom: 2rem;
    }

    .stat-mini {
        padding: 1.5rem;
        border-radius: 15px;
        text-align: center;
        color: white;
        font-weight: 600;
    }

    .stat-mini.penghargaan {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .stat-mini.pelanggaran {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }

    @media (max-width: 768px) {
        .leaderboard-row {
            flex-direction: column;
            text-align: center;
        }

        .points-section {
            margin-top: 1rem;
            width: 100%;
            justify-content: space-around;
        }

        .rank-badge {
            width: 40px;
            height: 40px;
            font-size: 1.2rem;
            margin-right: 0;
            margin-bottom: 0.5rem;
        }
    }

    /* Tampilan leaderboard: selaras dengan permukaan bersih aplikasi utama */
    body { background: #f8fafc; }
    .poin-leaderboard { max-width: 1440px; }
    .leaderboard-hero { padding: 1.5rem 1.75rem; border-radius: 22px; color: #fff; background: linear-gradient(120deg, #172554, #3730a3); box-shadow: 0 18px 35px rgba(30, 41, 59, .16); }
    .leaderboard-hero .eyebrow { font-size: .7rem; font-weight: 800; letter-spacing: .1em; opacity: .72; }
    .leaderboard-hero h1 { font-size: clamp(1.35rem, 2vw, 1.8rem); font-weight: 800; margin: .25rem 0; }
    .leaderboard-hero p { margin: 0; font-size: .84rem; opacity: .82; }
    .leaderboard-hero .btn { border-radius: 11px; font-weight: 700; }
    .filter-card, .leaderboard-card { background: #fff; border: 1px solid #e7edf5; border-radius: 20px; box-shadow: 0 8px 22px rgba(15, 23, 42, .05); }
    .filter-card { padding: 1.25rem; }
    .filter-heading { font-size: .78rem; font-weight: 800; color: #475569; letter-spacing: .03em; text-transform: uppercase; }
    .filter-pills { gap: .45rem; margin: .7rem 0 1.1rem; }
    .filter-pill { border: 1px solid #dbe3ef; border-radius: 10px; padding: .58rem .8rem; color: #475569; font-size: .78rem; font-weight: 700; }
    .filter-pill.active { background: #eef2ff; border-color: #c7d2fe; color: #4338ca; }
    .filter-pill:hover { border-color: #a5b4fc; color: #4338ca; }
    .filter-card .form-label { font-size: .72rem; color: #64748b; margin-bottom: .35rem; }
    .filter-card .form-select { min-height: 42px; border-color: #dbe3ef; font-size: .84rem; }
    .btn-reload { height: 42px; border: 0; border-radius: 10px; background: #2563eb; font-size: .82rem; font-weight: 700; }
    .metric-card { display: flex; align-items: center; gap: .8rem; padding: 1rem 1.1rem; border: 1px solid #e7edf5; border-radius: 18px; background: #fff; box-shadow: 0 6px 18px rgba(15, 23, 42, .04); }
    .metric-icon { width: 42px; height: 42px; display: grid; place-items: center; border-radius: 13px; font-size: 1.05rem; }
    .metric-card.reward .metric-icon { background: #dcfce7; color: #15803d; }.metric-card.violation .metric-icon { background: #fee2e2; color: #dc2626; }
    .metric-label { display: block; font-size: .7rem; color: #64748b; font-weight: 700; }.metric-value { display: block; margin-top: .15rem; font-size: 1.25rem; color: #0f172a; font-weight: 800; }
    .leaderboard-card { padding: 1.25rem; }.leaderboard-card h5 { font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.05rem; }
    .leaderboard-row { padding: .9rem; margin-bottom: .55rem; border: 1px solid #edf1f6; border-left: 0; border-radius: 15px; box-shadow: none; }.leaderboard-row:hover { transform: translateY(-2px); box-shadow: 0 9px 18px rgba(15,23,42,.07); }
    .rank-badge { width: 38px; height: 38px; margin-right: .75rem; font-size: .9rem; border-radius: 12px; }.rank-default { background: #eef2ff; color: #4338ca; }
    .student-name { font-size: .88rem; font-weight: 800; }.student-class, .points-label { font-size: .68rem; }.points-section { gap: 1.1rem; }.points-value { font-size: 1rem; }
    @media (max-width: 768px) { .poin-leaderboard { padding: 1rem !important; }.leaderboard-hero { padding: 1.25rem; }.leaderboard-row { flex-direction: row; text-align: left; }.points-section { width: auto; margin: 0; gap: .65rem; }.points-item:not(:last-child) { display: none; } }
</style>

<div class="container-fluid px-4 py-4 poin-leaderboard">
    <!-- HEADER -->
    <section class="leaderboard-hero d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <div class="eyebrow"><i class="bi bi-trophy-fill me-1"></i> KESANTRIAN & DISIPLIN</div>
            <h1>Leaderboard Poin Santri</h1>
            <p>Lihat capaian positif dan kedisiplinan siswa berdasarkan periode pilihan.</p>
        </div>
        <a href="<?= BASE_URL ?>/pages/halaqah/poin_dashboard.php" class="btn btn-light px-3 py-2">
            <i class="bi bi-grid-1x2 me-2"></i>Dashboard Poin
        </a>
    </section>

    <!-- FILTERS -->
    <div class="filter-card mb-3">
        <!-- Filter Tipe Poin -->
        <div class="mb-4">
            <label class="filter-heading">Tampilkan peringkat berdasarkan</label>
            <div class="filter-pills">
                <button type="button" class="filter-pill active" onclick="filterType('total', this)">
                    <i class="bi bi-star-fill me-2"></i>Total Poin
                </button>
                <button type="button" class="filter-pill" onclick="filterType('penghargaan', this)">
                    <i class="bi bi-hand-thumbs-up me-2"></i>Penghargaan
                </button>
                <button type="button" class="filter-pill" onclick="filterType('pelanggaran', this)">
                    <i class="bi bi-exclamation-triangle me-2"></i>Pelanggaran
                </button>
            </div>
        </div>

        <!-- Filter Bulan & Tahun -->
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-bold">Bulan</label>
                <select class="form-select rounded-3" id="bulanSelect" onchange="loadLeaderboard()">
                    <?php 
                    $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                    for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $i == $bulan ? 'selected' : '' ?>>
                            <?= $months[$i-1] ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Tahun</label>
                <select class="form-select rounded-3" id="tahunSelect" onchange="loadLeaderboard()">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= $y == $tahun ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">&nbsp;</label>
                <button class="btn btn-primary btn-reload w-100" onclick="loadLeaderboard()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Muat Ulang
                </button>
            </div>
        </div>
    </div>

    <!-- STATISTICS -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="metric-card reward">
                <div class="metric-icon"><i class="bi bi-hand-thumbs-up-fill"></i></div>
                <div><span class="metric-label">TOTAL PENGHARGAAN</span><strong class="metric-value" id="total-penghargaan-stat">0</strong></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="metric-card violation">
                <div class="metric-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div><span class="metric-label">TOTAL PELANGGARAN</span><strong class="metric-value" id="total-pelanggaran-stat">0</strong></div>
            </div>
        </div>
    </div>

    <!-- LEADERBOARD -->
    <div class="leaderboard-card">
        <h5 class="fw-bold mb-4">
            <i class="bi bi-list-ol me-2"></i>Ranking Siswa
        </h5>
        <div id="leaderboard-container">
            <div class="text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted">Memuat data leaderboard...</p>
            </div>
        </div>
    </div>
</div>

<script>
    const apiUrl = '<?= BASE_URL ?>/pages/halaqah/poin_api.php';
    let currentType = 'total';

    function filterType(type, button) {
        currentType = type;
        document.querySelectorAll('.filter-pill').forEach(pill => {
            pill.classList.remove('active');
        });
        button.classList.add('active');
        loadLeaderboard();
    }

    async function loadLeaderboard() {
        const bulan = document.getElementById('bulanSelect').value;
        const tahun = document.getElementById('tahunSelect').value;

        try {
            const response = await fetch(`${apiUrl}?action=get_leaderboard&tipe=${currentType}&limit=100&bulan=${bulan}&tahun=${tahun}`);
            const data = await response.json();

            if (data.success && data.data.length > 0) {
                const container = document.getElementById('leaderboard-container');
                const html = data.data.map((item, idx) => {
                    let rankClass = 'rank-default';
                    let medalIcon = '';
                    
                    if (idx === 0) {
                        rankClass = 'rank-1';
                        medalIcon = '🥇';
                    } else if (idx === 1) {
                        rankClass = 'rank-2';
                        medalIcon = '🥈';
                    } else if (idx === 2) {
                        rankClass = 'rank-3';
                        medalIcon = '🥉';
                    }

                    const rewardPoints = Number(item.poin_penghargaan) || 0;
                    const violationPoints = Number(item.poin_pelanggaran) || 0;
                    const totalPoints = rewardPoints - violationPoints;

                    return `
                        <div class="leaderboard-row">
                            <div class="rank-badge ${rankClass}">
                                ${medalIcon || (idx + 1)}
                            </div>
                            <div class="student-info">
                                <div class="student-name">${item.nama}</div>
                                <div class="student-class">${item.nis} • ${item.nama_kelas}</div>
                            </div>
                            <div class="points-section">
                                <div class="points-item">
                                    <div class="points-value" style="color: #059669;">+${rewardPoints.toLocaleString('id-ID')}</div>
                                    <div class="points-label">Penghargaan</div>
                                </div>
                                <div class="points-item">
                                    <div class="points-value" style="color: #dc2626;">-${violationPoints.toLocaleString('id-ID')}</div>
                                    <div class="points-label">Pelanggaran</div>
                                </div>
                                <div class="points-item">
                                    <div class="points-value" style="color: #8b5cf6;">
                                        ${(currentType === 'total' ? totalPoints : currentType === 'penghargaan' ? rewardPoints : violationPoints).toLocaleString('id-ID')}
                                    </div>
                                    <div class="points-label">${currentType === 'total' ? 'Total' : currentType === 'penghargaan' ? 'Penghargaan' : 'Pelanggaran'}</div>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');

                container.innerHTML = html;

                // Update stats
                let totalPenghargaan = 0, totalPelanggaran = 0;
                data.data.forEach(item => {
                    totalPenghargaan += Number(item.poin_penghargaan) || 0;
                    totalPelanggaran += Number(item.poin_pelanggaran) || 0;
                });
                document.getElementById('total-penghargaan-stat').textContent = totalPenghargaan.toLocaleString('id-ID');
                document.getElementById('total-pelanggaran-stat').textContent = totalPelanggaran.toLocaleString('id-ID');
            } else {
                document.getElementById('total-penghargaan-stat').textContent = '0';
                document.getElementById('total-pelanggaran-stat').textContent = '0';
                document.getElementById('leaderboard-container').innerHTML = `
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 opacity-25"></i>
                        <p>Belum ada data poin untuk periode ini</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error:', error);
            document.getElementById('leaderboard-container').innerHTML = `
                <div class="alert alert-danger">Error memuat data: ${error.message}</div>
            `;
        }
    }

    // Load on page load
    document.addEventListener('DOMContentLoaded', () => {
        loadLeaderboard();
        // Set the first filter pill as active
        document.querySelector('.filter-pill').classList.add('active');
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
