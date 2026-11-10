<?php
/**
 * PERANGKAT AJAR KURIKULUM MERDEKA - EDIT METADATA DOKUMEN
 */
$pageTitle  = 'Edit Dokumen Perangkat Ajar';
$activePage = 'perangkat-ajar';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_ajar');

$pdo = getConnection();
$userId = (int)$_SESSION['user_id'];
$userRole = strtolower($_SESSION['role'] ?? 'guru');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM perangkat_ajar WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$doc = $stmt->fetch();

if (!$doc) {
    redirect('index.php', 'danger', 'Dokumen Perangkat Ajar tidak ditemukan.');
}

// Cek hak edit: hanya pembuat atau admin
if ($userRole !== 'admin' && $doc['user_id'] != $userId) {
    redirect('index.php', 'danger', 'Anda tidak memiliki hak untuk mengedit dokumen ini.');
}

$modulData = json_decode($doc['modul_ajar_json'] ?? '{}', true) ?: [];

// Submit Edit
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $mapel       = trim($_POST['mapel'] ?? $doc['mapel']);
    $kelas       = trim($_POST['kelas'] ?? $doc['kelas']);
    $fase        = trim($_POST['fase'] ?? 'D');
    $tahun       = trim($_POST['tahun_ajaran'] ?? $doc['tahun_ajaran']);
    $topik       = trim($_POST['topik'] ?? $doc['topik']);
    $elemen      = trim($_POST['elemen'] ?? $doc['elemen']);
    $jpPerPekan  = max(1, min(10, (int)($_POST['jp_per_pekan'] ?? ($doc['jp_per_pekan'] ?? 4))));
    $alokasi     = "{$jpPerPekan} JP/Pekan";
    
    $cp  = trim($_POST['capaian_pembelajaran'] ?? $doc['capaian_pembelajaran']);
    $tp  = trim($_POST['tujuan_pembelajaran'] ?? $doc['tujuan_pembelajaran']);
    $atp = trim($_POST['alur_tujuan_pembelajaran'] ?? $doc['alur_tujuan_pembelajaran']);

    // Update data umum di JSON tanpa menghapus struktur bab yang sudah ada
    $modulData['nama_sekolah'] = trim($_POST['nama_sekolah'] ?? ($modulData['nama_sekolah'] ?? ''));
    $modulData['nama_guru']    = trim($_POST['nama_guru'] ?? ($modulData['nama_guru'] ?? ''));
    $modulData['nip_guru']     = trim($_POST['nip_guru'] ?? ($modulData['nip_guru'] ?? ''));
    $modulData['nama_kepsek']  = trim($_POST['nama_kepsek'] ?? ($modulData['nama_kepsek'] ?? ''));
    $modulData['nip_kepsek']   = trim($_POST['nip_kepsek'] ?? ($modulData['nip_kepsek'] ?? ''));
    $modulData['jp_per_pekan'] = $jpPerPekan;
    $modulData['mapel']        = $mapel;
    $modulData['kelas']        = $kelas;
    $modulData['tahun_ajaran'] = $tahun;

    try {
        $stmtUpdate = $pdo->prepare("
            UPDATE perangkat_ajar SET 
            mapel = :mapel, kelas = :kelas, fase = :fase, tahun_ajaran = :th,
            topik = :topik, elemen = :elem, alokasi_waktu = :alokasi, jp_per_pekan = :jp,
            capaian_pembelajaran = :cp, tujuan_pembelajaran = :tp, alur_tujuan_pembelajaran = :atp,
            modul_ajar_json = :json
            WHERE id = :id
        ");
        $stmtUpdate->execute([
            ':mapel'   => $mapel,
            ':kelas'   => $kelas,
            ':fase'    => $fase,
            ':th'      => $tahun,
            ':topik'   => $topik,
            ':elem'    => $elemen,
            ':alokasi' => $alokasi,
            ':jp'      => $jpPerPekan,
            ':cp'      => $cp,
            ':tp'      => $tp,
            ':atp'     => $atp,
            ':json'    => json_encode($modulData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ':id'      => $id
        ]);

        redirect("view.php?id=$id", 'success', 'Perubahan dokumen Perangkat Ajar berhasil disimpan!');
    } catch (PDOException $e) {
        $errorMsg = 'Gagal memperbarui dokumen: ' . $e->getMessage();
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/subnav.php';
?>

<div class="row justify-content-center">
    <div class="col-xl-9 col-lg-10 col-12">

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1" style="font-family: 'Outfit', sans-serif;">
                    <i class="bi bi-pencil-square text-primary me-2"></i> Edit Data Perangkat Ajar
                </h4>
                <p class="text-muted extra-small mb-0">
                    Perbarui informasi umum identitas mapel, guru pengampu, atau narasi CP/ATP.
                </p>
            </div>
            <a href="view.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Dokumen
            </a>
        </div>

        <?php if (isset($errorMsg)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($errorMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="update">

            <!-- KARTU IDENTITAS -->
            <div class="card border-0 rounded-4 shadow-sm mb-4" style="background: #ffffff;">
                <div class="card-body p-4 p-md-4">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">1. Identitas Pembelajaran</h6>

                    <div class="row g-3 mb-3">
                        <div class="col-md-5 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">MATA PELAJARAN</label>
                            <input type="text" name="mapel" class="form-control rounded-3 fw-semibold" value="<?= htmlspecialchars($doc['mapel']) ?>" required>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label extra-small fw-bold text-muted mb-1">KELAS</label>
                            <select name="kelas" class="form-select rounded-3 fw-semibold">
                                <option value="VII" <?= $doc['kelas'] === 'VII' ? 'selected' : '' ?>>Kelas VII</option>
                                <option value="VIII" <?= $doc['kelas'] === 'VIII' ? 'selected' : '' ?>>Kelas VIII</option>
                                <option value="IX" <?= $doc['kelas'] === 'IX' ? 'selected' : '' ?>>Kelas IX</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-6">
                            <label class="form-label extra-small fw-bold text-muted mb-1">JP PER PEKAN</label>
                            <input type="number" name="jp_per_pekan" class="form-control rounded-3 fw-semibold" min="1" max="10" value="<?= htmlspecialchars($doc['jp_per_pekan'] ?? 4) ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">TOPIK / JUDUL PERANGKAT</label>
                            <input type="text" name="topik" class="form-control rounded-3" value="<?= htmlspecialchars($doc['topik']) ?>" required>
                        </div>
                        <div class="col-md-4 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">TAHUN AJARAN</label>
                            <input type="text" name="tahun_ajaran" class="form-control rounded-3" value="<?= htmlspecialchars($doc['tahun_ajaran']) ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 pt-3 border-top">
                        <div class="col-md-6 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">GURU PENGAMPU</label>
                            <input type="text" name="nama_guru" class="form-control rounded-3" value="<?= htmlspecialchars($modulData['nama_guru'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">NIP GURU</label>
                            <input type="text" name="nip_guru" class="form-control rounded-3" value="<?= htmlspecialchars($modulData['nip_guru'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">KEPALA SEKOLAH</label>
                            <input type="text" name="nama_kepsek" class="form-control rounded-3" value="<?= htmlspecialchars((!empty($modulData['nama_kepsek']) && $modulData['nama_kepsek'] !== 'Kepala Sekolah, M.Pd') ? $modulData['nama_kepsek'] : getSetting('nama_kepsek', 'Kepala Sekolah')) ?>" required>
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">NIP KEPALA SEKOLAH</label>
                            <input type="text" name="nip_kepsek" class="form-control rounded-3" value="<?= htmlspecialchars((!empty($modulData['nip_kepsek']) && $modulData['nip_kepsek'] !== '—' && $modulData['nip_kepsek'] !== '-') ? $modulData['nip_kepsek'] : getSetting('nip_kepsek', '—')) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- KARTU CP & ATP -->
            <div class="card border-0 rounded-4 shadow-sm mb-4" style="background: #ffffff;">
                <div class="card-body p-4 p-md-4">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">2. Capaian &amp; Alur Pembelajaran</h6>

                    <div class="mb-3">
                        <label class="form-label extra-small fw-bold text-muted mb-1">CAPAIAN PEMBELAJARAN (CP) GLOBAL</label>
                        <textarea name="capaian_pembelajaran" rows="4" class="form-control rounded-3 small"><?= htmlspecialchars($doc['capaian_pembelajaran']) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label extra-small fw-bold text-muted mb-1">ALUR TUJUAN PEMBELAJARAN (ATP)</label>
                        <textarea name="alur_tujuan_pembelajaran" rows="4" class="form-control rounded-3 small"><?= htmlspecialchars($doc['alur_tujuan_pembelajaran']) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- TOMBOL SIMPAN -->
            <div class="d-flex align-items-center justify-content-between mb-5">
                <a href="view.php?id=<?= $id ?>" class="btn btn-light rounded-pill px-4 fw-bold text-secondary">
                    Batal
                </a>
                <button type="submit" class="btn btn-primary rounded-pill px-5 py-2.5 fw-bold shadow-sm">
                    <i class="bi bi-save me-1"></i> Simpan Perubahan
                </button>
            </div>
        </form>

    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
