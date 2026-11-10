<?php
/**
 * DASHBOARD PERANGKAT AJAR KURIKULUM MERDEKA
 * Ringkasan Perangkat Ajar, Akses Single Generator Hub, & Akses Cepat Dokumen
 */
$pageTitle = 'Dashboard Perangkat Ajar';
$activePage = 'perangkat-ajar';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_ajar');

$pdo = getConnection();
$userId = (int) $_SESSION['user_id'];
$userRole = strtolower($_SESSION['role'] ?? 'guru');

// Load list perangkat ajar
if ($userRole === 'admin') {
    $stmt = $pdo->query("
        SELECT p.*, u.nama_lengkap AS nama_guru 
        FROM perangkat_ajar p 
        LEFT JOIN users u ON p.user_id = u.id 
        ORDER BY p.updated_at DESC
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT p.*, u.nama_lengkap AS nama_guru 
        FROM perangkat_ajar p 
        LEFT JOIN users u ON p.user_id = u.id 
        WHERE p.user_id = :uid OR p.user_id IS NOT NULL
        ORDER BY (p.user_id = :uid2) DESC, p.updated_at DESC
    ");
    $stmt->execute([':uid' => $userId, ':uid2' => $userId]);
}
$listPerangkat = $stmt->fetchAll();

// Statistics
$totalDocs = count($listPerangkat);
$myDocs = 0;
$mapelList = [];
foreach ($listPerangkat as $p) {
    if ($p['user_id'] == $userId)
        $myDocs++;
    if (!in_array($p['mapel'], $mapelList))
        $mapelList[] = $p['mapel'];
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/subnav.php';
?>

<!-- HEADER DASHBOARD PERANGKAT AJAR -->
<div class="card border-0 shadow-sm rounded-4 mb-4"
    style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #047857 100%); color: white;">
    <div class="card-body p-4 p-md-5 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2"
                style="background: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255, 255, 255, 0.25); backdrop-filter: blur(10px);">
                <i class="bi bi-journal-bookmark-fill text-white fs-6"></i>
                <span class="small fw-bold text-white" style="letter-spacing: 0.8px;">KURIKULUM MERDEKA DIGITAL</span>
            </div>
            <h2 class="fw-extrabold mb-1" style="font-family: 'Outfit', sans-serif;">Perangkat Ajar Terpadu</h2>
            <p class="opacity-75 small mb-0 fs-6" style="max-width: 650px;">
                Satu pintu input bab &amp; jam per pekan yang otomatis meng-generate Capaian Pembelajaran (CP), Tujuan
                Pembelajaran (TP), Alur (ATP), serta Modul Ajar lengkap yang siap diunduh ke PDF.
            </p>
        </div>
        <a href="create.php"
            class="btn btn-emerald px-4 py-3 fw-extrabold rounded-pill text-white shadow-lg d-inline-flex align-items-center gap-2"
            style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none; font-size: 1rem;">
            <i class="bi bi-magic fs-5"></i>
            <span>Buat Perangkat Ajar Baru</span>
        </a>
    </div>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
    <div class="col-md-4 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 d-flex flex-row align-items-center gap-3 bg-white">
            <div class="stat-icon"
                style="background: linear-gradient(135deg, #10b981, #059669); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.3rem;">
                <i class="bi bi-journal-check"></i>
            </div>
            <div>
                <div class="text-muted small fw-bold">Total Perangkat Ajar</div>
                <div class="h4 fw-bold mb-0 text-dark"><?= $totalDocs ?> <small class="text-muted fs-6">dokumen</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 d-flex flex-row align-items-center gap-3 bg-white">
            <div class="stat-icon"
                style="background: linear-gradient(135deg, #6366f1, #4f46e5); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.3rem;">
                <i class="bi bi-person-workspace"></i>
            </div>
            <div>
                <div class="text-muted small fw-bold">Dokumen Saya</div>
                <div class="h4 fw-bold mb-0 text-dark"><?= $myDocs ?> <small class="text-muted fs-6">dokumen</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-12">
        <div class="card border-0 shadow-sm rounded-4 p-3 d-flex flex-row align-items-center gap-3 bg-white">
            <div class="stat-icon"
                style="background: linear-gradient(135deg, #06b6d4, #0891b2); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.3rem;">
                <i class="bi bi-book-half"></i>
            </div>
            <div>
                <div class="text-muted small fw-bold">Mata Pelajaran</div>
                <div class="h4 fw-bold mb-0 text-dark"><?= count($mapelList) ?> <small class="text-muted fs-6">mapel
                        terdaftar</small></div>
            </div>
        </div>
    </div>
</div>

<!-- 4 FITUR KOMPONEN OTOMATIS -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="p-3 bg-white border-0 shadow-sm rounded-4 text-center h-100">
            <div class="rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center"
                style="width: 44px; height: 44px; background: rgba(6, 182, 212, 0.12); color: #0891b2; font-size: 1.3rem;">
                <i class="bi bi-journal-text"></i>
            </div>
            <h6 class="fw-bold text-dark mb-1">1. CP (Capaian)</h6>
            <small class="text-muted extra-small">Deskripsi capaian akhir fase &amp; per bab pembahasan.</small>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="p-3 bg-white border-0 shadow-sm rounded-4 text-center h-100">
            <div class="rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center"
                style="width: 44px; height: 44px; background: rgba(59, 130, 246, 0.12); color: #2563eb; font-size: 1.3rem;">
                <i class="bi bi-bullseye"></i>
            </div>
            <h6 class="fw-bold text-dark mb-1">2. TP (Tujuan)</h6>
            <small class="text-muted extra-small">Indikator Bloom bertingkat (ABCD) operasional.</small>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="p-3 bg-white border-0 shadow-sm rounded-4 text-center h-100">
            <div class="rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center"
                style="width: 44px; height: 44px; background: rgba(139, 92, 246, 0.12); color: #7c3aed; font-size: 1.3rem;">
                <i class="bi bi-diagram-3-fill"></i>
            </div>
            <h6 class="fw-bold text-dark mb-1">3. ATP (Alur)</h6>
            <small class="text-muted extra-small">Alokasi JP realistis 1 tahun sesuai jam/pekan.</small>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="p-3 bg-white border-0 shadow-sm rounded-4 text-center h-100">
            <div class="rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center"
                style="width: 44px; height: 44px; background: rgba(236, 72, 153, 0.12); color: #db2777; font-size: 1.3rem;">
                <i class="bi bi-box-seam-fill"></i>
            </div>
            <h6 class="fw-bold text-dark mb-1">4. Modul Ajar</h6>
            <small class="text-muted extra-small">Sintaks kegiatan, asesmen, pemantik &amp; LKPD.</small>
        </div>
    </div>
</div>

<!-- TABEL DOKUMEN -->
<div class="card border-0 shadow-sm rounded-4 bg-white mb-5 overflow-hidden">
    <div
        class="card-header bg-white border-bottom p-3.5 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h5 class="m-0 fw-bold text-dark" style="font-family: 'Outfit', sans-serif;">
            <i class="bi bi-folder-check text-primary me-2"></i> Daftar Perangkat Ajar Kurikulum Merdeka
        </h5>
        <a href="create.php" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">
            <i class="bi bi-plus-lg me-1"></i> Buat Dokumen Baru
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="tablePerangkat">
            <thead class="table-light extra-small text-uppercase fw-bold text-muted">
                <tr>
                    <th width="40" class="text-center">No</th>
                    <th>Mata Pelajaran</th>
                    <th>Kelas / Fase</th>
                    <th>Beban Belajar</th>
                    <th>Guru Pengampu</th>
                    <th>Tahun Ajaran</th>
                    <th width="260" class="text-end">Aksi &amp; Unduh</th>
                </tr>
            </thead>
            <tbody class="small">
                <?php if (empty($listPerangkat)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-journal-x fs-1 d-block mb-2 text-secondary"></i>
                            <div class="fw-bold mb-1">Belum ada Perangkat Ajar yang dibuat.</div>
                            <small class="d-block mb-3">Klik tombol di bawah ini untuk membuat perangkat ajar 1 tahun ajaran
                                secara otomatis.</small>
                            <a href="create.php" class="btn btn-emerald btn-sm rounded-pill px-4 fw-bold text-white"
                                style="background: #10b981;">
                                <i class="bi bi-plus-circle me-1"></i> Mulai Buat Perangkat Ajar
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1;
                    foreach ($listPerangkat as $row): ?>
                        <tr>
                            <td class="text-center text-muted fw-bold"><?= $no++ ?></td>
                            <td>
                                <a href="view.php?id=<?= $row['id'] ?>" class="fw-bold text-dark text-decoration-none d-block">
                                    <?= htmlspecialchars($row['mapel']) ?>
                                </a>
                                <small class="text-muted extra-small"><?= htmlspecialchars($row['topik']) ?></small>
                            </td>
                            <td>
                                <span
                                    class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 extra-small fw-bold">
                                    Kelas <?= htmlspecialchars($row['kelas']) ?> (Fase
                                    <?= htmlspecialchars($row['fase'] ?? 'D') ?>)
                                </span>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark d-block">
                                    <?= htmlspecialchars($row['jp_per_pekan'] ?? 4) ?> JP / Pekan
                                </span>
                                <small class="text-muted extra-small"><?= htmlspecialchars($row['alokasi_waktu']) ?></small>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark"><?= htmlspecialchars($row['nama_guru'] ?? '-') ?></span>
                            </td>
                            <td>
                                <span class="badge bg-light text-muted border px-2.5 py-1 rounded-pill extra-small">
                                    <?= htmlspecialchars($row['tahun_ajaran']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex align-items-center gap-1.5">
                                    <!-- Buka Dokumen Hub -->
                                    <a href="view.php?id=<?= $row['id'] ?>"
                                        class="btn btn-sm btn-primary rounded-pill px-3 fw-bold"
                                        title="Buka Dokumen & Unduh PDF">
                                        <i class="bi bi-eye-fill me-1"></i> Buka
                                    </a>

                                    <!-- Unduh Cepat Bundel Lengkap -->
                                    <a href="print.php?id=<?= $row['id'] ?>&doc_type=all" target="_blank"
                                        class="btn btn-sm btn-outline-danger rounded-pill px-2.5"
                                        title="Cetak PDF Bundel Lengkap">
                                        <i class="bi bi-file-earmark-pdf-fill"></i>
                                    </a>

                                    <!-- Hapus -->
                                    <?php if ($userRole === 'admin' || $row['user_id'] == $userId): ?>
                                        <a href="delete.php?id=<?= $row['id'] ?>"
                                            class="btn btn-sm btn-light text-danger rounded-circle p-1.5"
                                            onclick="return confirm('Apakah Anda yakin ingin menghapus dokumen Perangkat Ajar ini secara permanen?');"
                                            title="Hapus Dokumen">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>