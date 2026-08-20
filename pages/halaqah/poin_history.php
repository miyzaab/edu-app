<?php
/**
 * HISTORY POIN KESANTRIAN - Riwayat Lengkap Poin
 * Menampilkan riwayat semua pemberian poin dengan filter dan pencarian
 */

$pageTitle  = 'Riwayat Poin Kesantrian';
$activePage = 'poin_history';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('kesantrian');

$pdo = getConnection();
$siswaId = (int)($_GET['siswa_id'] ?? 0);

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    :root {
        --primary: #8b5cf6;
        --success: #10b981;
        --danger: #ef4444;
        --info: #06b6d4;
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

    .history-table tbody tr {
        border-bottom: 1px solid #e5e7eb;
        transition: all 0.3s ease;
    }

    .history-table tbody tr:hover {
        background: #f9fafb;
    }

    .poin-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .poin-badge.penghargaan {
        background: #dcfce7;
        color: #166534;
    }

    .poin-badge.pelanggaran {
        background: #fee2e2;
        color: #991b1b;
    }

    .icon-poin {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        margin-right: 0.75rem;
        font-weight: bold;
        color: white;
    }

    .icon-poin.penghargaan {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .icon-poin.pelanggaran {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }

    .kategori-pill {
        display: inline-block;
        padding: 0.5rem 0.75rem;
        border-radius: 8px;
        background: rgba(139, 92, 246, 0.1);
        color: #8b5cf6;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .filter-section {
        padding: 2rem;
        background: white;
        border-radius: 15px;
        margin-bottom: 2rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .no-data {
        text-align: center;
        padding: 3rem;
        color: #9ca3af;
    }

    .no-data i {
        font-size: 3rem;
        opacity: 0.3;
        display: block;
        margin-bottom: 1rem;
    }

    @media (max-width: 768px) {
        .history-table {
            font-size: 0.9rem;
        }

        .history-table thead {
            display: none;
        }

        .history-table tbody tr {
            display: block;
            margin-bottom: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 1rem;
        }

        .history-table td {
            display: block;
            text-align: right;
            padding-left: 50%;
            position: relative;
            border: none;
        }

        .history-table td:before {
            content: attr(data-label);
            position: absolute;
            left: 0;
            font-weight: 600;
            color: #6b7280;
        }
    }
</style>

<div class="container-fluid px-4 py-4">
    <!-- HEADER -->
    <div class="d-flex align-items-center justify-content-between mb-5">
        <div>
            <h1 class="h2 fw-bold text-dark mb-2">
                <i class="bi bi-clock-history me-2"></i>Riwayat Poin Kesantrian
            </h1>
            <p class="text-muted">Lihat catatan lengkap pemberian poin kepada siswa</p>
        </div>
        <a href="/pages/halaqah/poin_dashboard.php" class="btn btn-outline-primary btn-lg rounded-3">
            <i class="bi bi-arrow-left me-2"></i>Kembali
        </a>
    </div>

    <!-- FILTERS -->
    <div class="filter-section">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-bold">Pilih Siswa</label>
                <select class="form-select rounded-3" id="siswaFilter" onchange="loadHistory()">
                    <option value="">-- Semua Siswa --</option>
                    <?php
                    $siswaList = $pdo->query("
                        SELECT s.id, s.nama, s.nis, k.nama_kelas
                        FROM siswa s
                        JOIN kelas k ON s.kelas_id = k.id
                        WHERE s.status = 'aktif'
                        ORDER BY k.nama_kelas ASC, s.nama ASC
                    ")->fetchAll();

                    foreach ($siswaList as $s):
                    ?>
                        <option value="<?= $s['id'] ?>" <?= $s['id'] == $siswaId ? 'selected' : '' ?>>
                            <?= $s['nama'] ?> (<?= $s['nis'] ?>) - <?= $s['nama_kelas'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Filter Tipe</label>
                <select class="form-select rounded-3" id="tipeFilter" onchange="loadHistory()">
                    <option value="">-- Semua Tipe --</option>
                    <option value="penghargaan">Penghargaan</option>
                    <option value="pelanggaran">Pelanggaran</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Pencarian</label>
                <input type="text" class="form-control rounded-3" id="searchInput" placeholder="Cari kategori, siswa, atau deskripsi..." onkeyup="debounceSearch()">
            </div>
        </div>
    </div>

    <!-- HISTORY TABLE -->
    <div class="glass-card p-4">
        <div class="table-responsive">
            <table class="table table-hover history-table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 15%;">Tanggal</th>
                        <th style="width: 20%;">Siswa</th>
                        <th style="width: 20%;">Kategori</th>
                        <th style="width: 15%;">Poin</th>
                        <th style="width: 20%;">Deskripsi</th>
                        <th style="width: 10%;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="historyTableBody">
                    <?php
                        // SSR fallback: tampilkan 50 riwayat terbaru untuk mencegah "Memuat data..." jika JS gagal
                        $limitSSR = 50;
                        $sql = "SELECT spr.id, spr.nilai_poin, spr.tipe_poin, spr.deskripsi, spc.nama_kategori, spc.icon, spc.color, COALESCE(u.nama_lengkap, 'Sistem') as input_by_name, spr.tanggal, spr.jam, s.nama as nama_siswa
                                FROM siswa_poin_riwayat spr
                                JOIN siswa_poin_kategori spc ON spr.kategori_poin_id = spc.id
                                LEFT JOIN users u ON spr.input_by = u.id
                                LEFT JOIN siswa s ON spr.siswa_id = s.id
                                WHERE 1=1";
                        $params = [];
                        if ($siswaId > 0) {
                            $sql .= " AND spr.siswa_id = :sid";
                            $params[':sid'] = $siswaId;
                        }
                        $sql .= " ORDER BY spr.tanggal DESC, spr.jam DESC, spr.id DESC LIMIT :lim";
                        $stmtSSR = $pdo->prepare($sql);
                        if ($siswaId > 0) $stmtSSR->bindValue(':sid', $siswaId, PDO::PARAM_INT);
                        $stmtSSR->bindValue(':lim', $limitSSR, PDO::PARAM_INT);
                        $stmtSSR->execute();
                        $rowsSSR = $stmtSSR->fetchAll(PDO::FETCH_ASSOC);

                        if (empty($rowsSSR)) {
                            echo '<tr><td colspan="6"><div class="no-data"><i class="bi bi-inbox"></i><p>Tidak ada riwayat poin</p></div></td></tr>';
                        } else {
                            foreach ($rowsSSR as $item) {
                                $dateObj = date_create($item['tanggal']);
                                $dateStr = $dateObj ? date_format($dateObj, 'd M Y') : $item['tanggal'];
                                $pointsBadge = '<span class="poin-badge ' . ($item['tipe_poin'] === 'penghargaan' ? 'penghargaan' : 'pelanggaran') . '">'
                                    . ($item['tipe_poin'] === 'penghargaan' ? '+' : '-') . abs($item['nilai_poin']) . '</span>';

                                echo '<tr>';
                                echo '<td data-label="Tanggal"><i class="bi bi-calendar3 me-2 text-muted"></i>' . htmlspecialchars($dateStr) . '</td>';
                                echo '<td data-label="Siswa"><i class="bi bi-person-circle me-2 text-primary"></i><strong>' . htmlspecialchars($item['nama_siswa'] ?? 'N/A') . '</strong></td>';
                                echo '<td data-label="Kategori"><div class="d-flex align-items-center"><div class="icon-poin ' . ($item['tipe_poin'] === 'penghargaan' ? 'penghargaan' : 'pelanggaran') . '"><i class="bi ' . htmlspecialchars($item['icon'] ?? '') . '"></i></div><span class="kategori-pill">' . htmlspecialchars($item['nama_kategori']) . '</span></div></td>';
                                echo '<td data-label="Poin">' . $pointsBadge . '</td>';
                                echo '<td data-label="Deskripsi"><small class="text-muted">' . htmlspecialchars(mb_strimwidth($item['deskripsi'] ?? '-', 0, 80, '...')) . '</small></td>';
                                echo '<td data-label="Aksi"><button class="btn btn-sm btn-outline-danger rounded-2" onclick="hapusPoin(' . (int)$item['id'] . ')"><i class="bi bi-trash"></i></button></td>';
                                echo '</tr>';
                            }
                        }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- PAGINATION -->
    <div class="d-flex justify-content-center mt-4">
        <nav aria-label="Page navigation">
            <ul class="pagination" id="paginationContainer">
                <!-- Pagination will be added here by JS -->
            </ul>
        </nav>
    </div>
</div>

<script>
    const apiUrl = '<?= BASE_URL ?>/pages/halaqah/poin_api.php';
    let allHistory = [];
    let searchTimeout;

    async function loadHistory() {
        const siswaId = document.getElementById('siswaFilter').value;
        const tipe = document.getElementById('tipeFilter').value;
        const search = document.getElementById('searchInput').value;

        try {
            let query = `${apiUrl}?action=get_riwayat_poin&limit=500`;
            
            if (siswaId) {
                query += `&siswa_id=${siswaId}`;
            }

            const response = await fetch(query);
            const data = await response.json();

            if (data.success) {
                allHistory = data.data;

                // Apply filters
                let filtered = allHistory;

                if (tipe) {
                    filtered = filtered.filter(item => item.tipe_poin === tipe);
                }

                if (search.trim()) {
                    const searchLower = search.toLowerCase();
                    filtered = filtered.filter(item =>
                        item.nama_kategori.toLowerCase().includes(searchLower) ||
                        item.deskripsi?.toLowerCase().includes(searchLower)
                    );
                }

                displayHistory(filtered);
            }
        } catch (error) {
            console.error('Error:', error);
            document.getElementById('historyTableBody').innerHTML = `
                <tr><td colspan="6" class="alert alert-danger">Error memuat data</td></tr>
            `;
        }
    }

    function displayHistory(items) {
        const tbody = document.getElementById('historyTableBody');

        if (items.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6">
                        <div class="no-data">
                            <i class="bi bi-inbox"></i>
                            <p>Tidak ada riwayat poin</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = items.map(item => {
            const dateObj = new Date(item.tanggal);
            const dateStr = dateObj.toLocaleDateString('id-ID', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });

            const pointsDisplay = `
                <span class="poin-badge ${item.tipe_poin}">
                    ${item.tipe_poin === 'penghargaan' ? '+' : '-'}${Math.abs(item.nilai_poin)}
                </span>
            `;

            return `
                <tr>
                    <td data-label="Tanggal">
                        <i class="bi bi-calendar3 me-2 text-muted"></i>
                        ${dateStr}
                    </td>
                    <td data-label="Siswa">
                        <i class="bi bi-person-circle me-2 text-primary"></i>
                        <strong>${item.nama_siswa || 'N/A'}</strong>
                    </td>
                    <td data-label="Kategori">
                        <div class="d-flex align-items-center">
                            <div class="icon-poin ${item.tipe_poin}">
                                <i class="bi ${item.icon}"></i>
                            </div>
                            <span class="kategori-pill">${item.nama_kategori}</span>
                        </div>
                    </td>
                    <td data-label="Poin">
                        ${pointsDisplay}
                    </td>
                    <td data-label="Deskripsi">
                        <small class="text-muted">${item.deskripsi ? item.deskripsi.substring(0, 40) + '...' : '-'}</small>
                    </td>
                    <td data-label="Aksi">
                        <button class="btn btn-sm btn-outline-danger rounded-2" onclick="hapusPoin(${item.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function debounceSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadHistory();
        }, 300);
    }

    async function hapusPoin(id) {
        if (!confirm('Apakah Anda yakin ingin menghapus poin ini?')) return;

        const formData = new FormData();
        formData.append('action', 'hapus_poin');
        formData.append('id', id);

        try {
            const response = await fetch(apiUrl, { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                alert('✅ Poin berhasil dihapus');
                loadHistory();
            } else {
                alert('❌ ' + data.error);
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }

    // Load on page load
    document.addEventListener('DOMContentLoaded', () => {
        loadHistory();
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
