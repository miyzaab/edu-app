<?php
/**
 * IDENTITAS MODUL & PROFIL PELAJAR PANCASILA - Dedicated Menu
 * Modul Perangkat Ajar Kurikulum Merdeka
 */
$pageTitle  = 'Identitas Modul & Profil Pancasila';
$activePage = 'perangkat-ajar';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_ajar');

$pdo = getConnection();
$userId = (int)$_SESSION['user_id'];

// Default Values dari system settings / session
$namaSekolah       = getSetting('identitas_nama_sekolah', getSetting('nama_sekolah', SCHOOL_NAME));
$namaGuruOto       = $_SESSION['nama_lengkap'] ?? 'Guru Pengampu';
$nipGuruOto        = getSetting('identitas_nip_guru_' . $userId, '—');
$namaKepsek        = getSetting('nama_kepsek', 'Kepala Sekolah, M.Pd');
$nipKepsek         = getSetting('nip_kepsek', '');
$modelPembelajaran = getSetting('identitas_model_pembelajaran', 'Problem-Based Learning (PBL)');
$savedPancasila    = getSetting('identitas_profil_pancasila', '["Bernalar Kritis","Gotong Royong","Kreatif"]');
$profilPancasila   = json_decode($savedPancasila, true) ?: ['Bernalar Kritis', 'Gotong Royong', 'Kreatif'];

// Handle Save Identitas Modul & Profil Pancasila
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_identitas') {
    $namaSekolahInput  = trim($_POST['nama_sekolah'] ?? $namaSekolah);
    $namaKepsekInput   = trim($_POST['nama_kepsek'] ?? $namaKepsek);
    $nipKepsekInput    = trim($_POST['nip_kepsek'] ?? '');
    $modelPembInput    = trim($_POST['model_pembelajaran'] ?? $modelPembelajaran);
    $pancasilaInput    = $_POST['profil_pancasila'] ?? ['Bernalar Kritis', 'Gotong Royong', 'Kreatif'];

    // Simpan ke Setting DB
    updateSetting('identitas_nama_sekolah', $namaSekolahInput);
    updateSetting('nama_sekolah', $namaSekolahInput);
    updateSetting('nama_kepsek', $namaKepsekInput);
    updateSetting('nip_kepsek', $nipKepsekInput);
    updateSetting('identitas_model_pembelajaran', $modelPembInput);
    updateSetting('identitas_profil_pancasila', json_encode($pancasilaInput, JSON_UNESCAPED_UNICODE));

    // Update seluruh modul_ajar_json milik user ini agar tersinkronisasi otomatis dengan nama guru login
    try {
        $stmtAll = $pdo->prepare("SELECT id, modul_ajar_json FROM perangkat_ajar WHERE user_id = :uid");
        $stmtAll->execute([':uid' => $userId]);
        $rows = $stmtAll->fetchAll();

        foreach ($rows as $r) {
            $mJson = json_decode($r['modul_ajar_json'] ?? '[]', true) ?: [];
            $mJson['nama_sekolah']       = $namaSekolahInput;
            $mJson['nama_guru']          = $namaGuruOto;
            $mJson['nip_guru']           = $nipGuruOto;
            $mJson['nama_kepsek']        = $namaKepsekInput;
            $mJson['nip_kepsek']         = $nipKepsekInput;
            $mJson['model_pembelajaran'] = $modelPembInput;
            $mJson['profil_pancasila']   = $pancasilaInput;

            $stmtUp = $pdo->prepare("UPDATE perangkat_ajar SET modul_ajar_json = :json WHERE id = :id");
            $stmtUp->execute([':json' => json_encode($mJson, JSON_UNESCAPED_UNICODE), ':id' => $r['id']]);
        }
    } catch (Exception $e) {}

    redirect('identitas.php', 'success', 'Identitas Modul & Profil Pelajar Pancasila berhasil diperbarui!');
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/subnav.php';
?>

<!-- HEADER PAGE IDENTITAS -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-primary bg-opacity-10 text-primary mb-2">
                <i class="bi bi-person-badge-fill fs-6"></i>
                <span class="small fw-bold">IDENTITAS MODUL & PROFIL PANCASILA</span>
            </div>
            <h4 class="fw-extrabold text-dark mb-1">Pengaturan Identitas & Profil Pelajar Pancasila</h4>
            <p class="text-muted small mb-0">Identitas sekolah, Kepala Sekolah, dan Profil Pelajar Pancasila akan tersinkronisasi otomatis pada seluruh Perangkat Ajar.</p>
        </div>
        <a href="index.php" class="btn btn-outline-primary fw-bold px-3">
            <i class="bi bi-speedometer2 me-1"></i> Dashboard
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white p-3.5 border-bottom d-flex align-items-center gap-2">
                <i class="bi bi-journal-bookmark-fill text-primary fs-5"></i>
                <h5 class="fw-bold m-0 text-dark">Identitas Modul & Profil Pelajar Pancasila</h5>
            </div>
            <div class="card-body p-4">
                
                <!-- NOTIFIKASI OTOMATIS GURU PENGAMPU -->
                <div class="alert alert-info border-0 rounded-3 p-3 mb-4 d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-info bg-opacity-20 text-info p-2.5 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; flex-shrink: 0;">
                        <i class="bi bi-person-check-fill fs-5"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark fs-7">Guru Pengampu Terdeteksi Otomatis</div>
                        <div class="small text-muted">
                            Nama Guru: <strong><?= htmlspecialchars($namaGuruOto) ?></strong> | NIP: <strong><?= htmlspecialchars($nipGuruOto) ?></strong>
                            <br><span class="fst-italic opacity-85">Data guru pengampu diambil otomatis dari profil login Anda sehingga Anda tidak perlu mengisinya lagi.</span>
                        </div>
                    </div>
                </div>

                <form method="POST" action="identitas.php">
                    <input type="hidden" name="action" value="save_identitas">

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-7">Nama Sekolah</label>
                        <input type="text" name="nama_sekolah" class="form-control border-2" value="<?= htmlspecialchars($namaSekolah) ?>" placeholder="Contoh: SMP Bagus Anti Korupsi" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark fs-7">Nama Kepala Sekolah</label>
                            <input type="text" name="nama_kepsek" class="form-control border-2" value="<?= htmlspecialchars($namaKepsek) ?>" placeholder="Nama Kepala Sekolah">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark fs-7">NIP Kepala Sekolah</label>
                            <input type="text" name="nip_kepsek" class="form-control border-2" value="<?= htmlspecialchars($nipKepsek) ?>" placeholder="NIP Kepala Sekolah">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-7">Model Pembelajaran Utama</label>
                        <select name="model_pembelajaran" class="form-select border-2">
                            <option value="Problem-Based Learning (PBL)" <?= $modelPembelajaran === 'Problem-Based Learning (PBL)' ? 'selected' : '' ?>>Problem-Based Learning (PBL)</option>
                            <option value="Project-Based Learning (PjBL)" <?= $modelPembelajaran === 'Project-Based Learning (PjBL)' ? 'selected' : '' ?>>Project-Based Learning (PjBL)</option>
                            <option value="Discovery Learning" <?= $modelPembelajaran === 'Discovery Learning' ? 'selected' : '' ?>>Discovery Learning</option>
                            <option value="Inquiry Learning" <?= $modelPembelajaran === 'Inquiry Learning' ? 'selected' : '' ?>>Inquiry Learning</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark fs-7 d-block mb-2">Dimensi Profil Pelajar Pancasila</label>
                        <div class="d-flex flex-wrap gap-3 p-3 rounded-3 bg-light border">
                            <?php 
                            $dimensiList = ['Beriman & Bertakwa', 'Bernalar Kritis', 'Gotong Royong', 'Kreatif', 'Mandiri', 'Berkebinekaan Global'];
                            foreach ($dimensiList as $dim):
                                $checked = in_array($dim, $profilPancasila) ? 'checked' : '';
                            ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="profil_pancasila[]" value="<?= $dim ?>" id="dim_<?= md5($dim) ?>" <?= $checked ?>>
                                    <label class="form-check-label fs-7 fw-semibold text-dark" for="dim_<?= md5($dim) ?>"><?= $dim ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary-custom w-100 py-3 fw-bold shadow-sm">
                        <i class="bi bi-save-fill me-2"></i> Simpan Identitas & Profil Pancasila
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
