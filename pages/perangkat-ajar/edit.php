<?php
/**
 * PERANGKAT AJAR KURIKULUM MERDEKA - Edit Perangkat Ajar
 */
$pageTitle  = 'Edit Perangkat Ajar';
$activePage = 'perangkat-ajar';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_ajar');

$pdo = getConnection();
$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'guru';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM perangkat_ajar WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$doc = $stmt->fetch();

if (!$doc) {
    redirect(BASE_URL . '/pages/perangkat-ajar/index.php', 'danger', 'Dokumen Perangkat Ajar tidak ditemukan.');
}

// Cek hak edit: hanya pembuat atau admin
if ($userRole !== 'admin' && $doc['user_id'] != $userId) {
    redirect(BASE_URL . '/pages/perangkat-ajar/index.php', 'danger', 'Anda tidak memiliki hak untuk mengedit dokumen ini.');
}

$modulData = json_decode($doc['modul_ajar_json'] ?? '{}', true);

// Submit Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $mapel    = trim($_POST['mapel'] ?? '');
    $kelas    = trim($_POST['kelas'] ?? 'VII');
    $fase     = trim($_POST['fase'] ?? 'D');
    $semester = $_POST['semester'] ?? 'Ganjil';
    $tahun    = trim($_POST['tahun_ajaran'] ?? '');
    $topik    = trim($_POST['topik'] ?? '');
    $elemen   = trim($_POST['elemen'] ?? '');
    $alokasi  = trim($_POST['alokasi_waktu'] ?? '');
    
    $cp  = trim($_POST['capaian_pembelajaran'] ?? '');
    $tp  = trim($_POST['tujuan_pembelajaran'] ?? '');
    $atp = trim($_POST['alur_tujuan_pembelajaran'] ?? '');

    $newModulData = [
        'nama_sekolah'         => trim($_POST['nama_sekolah'] ?? ''),
        'nama_guru'            => trim($_POST['nama_guru'] ?? ''),
        'nip_guru'             => trim($_POST['nip_guru'] ?? ''),
        'nama_kepsek'          => trim($_POST['nama_kepsek'] ?? ''),
        'nip_kepsek'           => trim($_POST['nip_kepsek'] ?? ''),
        'model_pembelajaran'   => trim($_POST['model_pembelajaran'] ?? ''),
        'profil_pancasila'     => $_POST['profil_pancasila'] ?? [],
        'kompetensi_awal'      => trim($_POST['kompetensi_awal'] ?? ''),
        'sarana_prasarana'     => trim($_POST['sarana_prasarana'] ?? ''),
        'target_siswa'         => trim($_POST['target_siswa'] ?? ''),
        'pemahaman_bermakna'   => trim($_POST['pemahaman_bermakna'] ?? ''),
        'pertanyaan_pemantik'  => trim($_POST['pertanyaan_pemantik'] ?? ''),
        'kegiatan_pendahuluan' => trim($_POST['kegiatan_pendahuluan'] ?? ''),
        'kegiatan_inti'        => trim($_POST['kegiatan_inti'] ?? ''),
        'kegiatan_penutup'     => trim($_POST['kegiatan_penutup'] ?? ''),
        'asesmen_diagnostik'   => trim($_POST['asesmen_diagnostik'] ?? ''),
        'asesmen_formatif'     => trim($_POST['asesmen_formatif'] ?? ''),
        'asesmen_sumatif'      => trim($_POST['asesmen_sumatif'] ?? ''),
        'lkpd_content'         => trim($_POST['lkpd_content'] ?? ''),
        'bahan_bacaan'         => trim($_POST['bahan_bacaan'] ?? ''),
        'glosarium'            => trim($_POST['glosarium'] ?? ''),
        'daftar_pustaka'       => trim($_POST['daftar_pustaka'] ?? '')
    ];

    if ($mapel && $kelas && $topik) {
        try {
            $stmtUpdate = $pdo->prepare("
                UPDATE perangkat_ajar SET 
                mapel = :mapel, kelas = :kelas, fase = :fase, semester = :sem, tahun_ajaran = :th,
                topik = :topik, elemen = :elem, alokasi_waktu = :alokasi,
                capaian_pembelajaran = :cp, tujuan_pembelajaran = :tp, alur_tujuan_pembelajaran = :atp,
                modul_ajar_json = :json
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                ':mapel'   => $mapel,
                ':kelas'   => $kelas,
                ':fase'    => $fase,
                ':sem'     => $semester,
                ':th'      => $tahun,
                ':topik'   => $topik,
                ':elem'    => $elemen,
                ':alokasi' => $alokasi,
                ':cp'      => $cp,
                ':tp'      => $tp,
                ':atp'     => $atp,
                ':json'    => json_encode($newModulData, JSON_UNESCAPED_UNICODE),
                ':id'      => $id
            ]);

            redirect(BASE_URL . "/pages/perangkat-ajar/print.php?id=$id", 'success', 'Perangkat Ajar berhasil diperbarui!');
        } catch (PDOException $e) {
            $errorMsg = 'Gagal memperbarui dokumen: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/subnav.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-pencil-square text-primary"></i> Edit Perangkat Ajar</h5>
        <small class="text-muted"><?= htmlspecialchars($doc['mapel']) ?> — <?= htmlspecialchars($doc['topik']) ?></small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/pages/perangkat-ajar/print.php?id=<?= $id ?>" target="_blank" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-file-earmark-pdf"></i> Pratinjau PDF
        </a>
        <a href="<?= BASE_URL ?>/pages/perangkat-ajar/index.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<?php if (isset($errorMsg)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($errorMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="action" value="update">

    <!-- STEP 1: IDENTITAS DOKUMEN -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-primary text-white py-3">
            <h6 class="m-0 fw-bold"><i class="bi bi-info-circle-fill me-2"></i> 1. Identitas Mata Pelajaran & Kelas</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Mata Pelajaran *</label>
                    <input type="text" name="mapel" class="form-control" value="<?= htmlspecialchars($doc['mapel']) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Kelas *</label>
                    <input type="text" name="kelas" class="form-control" value="<?= htmlspecialchars($doc['kelas']) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Fase</label>
                    <input type="text" name="fase" class="form-control" value="<?= htmlspecialchars($doc['fase']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Semester</label>
                    <select name="semester" class="form-select">
                        <option value="Ganjil" <?= $doc['semester'] === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                        <option value="Genap" <?= $doc['semester'] === 'Genap' ? 'selected' : '' ?>>Genap</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Tahun Ajaran</label>
                    <input type="text" name="tahun_ajaran" class="form-control" value="<?= htmlspecialchars($doc['tahun_ajaran']) ?>" required>
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Elemen / Domain</label>
                    <input type="text" name="elemen" class="form-control" value="<?= htmlspecialchars($doc['elemen'] ?? '') ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold">Topik / Materi Pokok *</label>
                    <input type="text" name="topik" class="form-control" value="<?= htmlspecialchars($doc['topik']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Alokasi Waktu</label>
                    <input type="text" name="alokasi_waktu" class="form-control" value="<?= htmlspecialchars($doc['alokasi_waktu']) ?>">
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Nama Guru Pengampu *</label>
                    <input type="text" name="nama_guru" class="form-control" value="<?= htmlspecialchars($modulData['nama_guru'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">NIP Guru</label>
                    <input type="text" name="nip_guru" class="form-control" value="<?= htmlspecialchars($modulData['nip_guru'] ?? '') ?>">
                </div>
            </div>

            <div class="mt-4">
                <label class="form-label fw-bold text-dark">Capaian Pembelajaran (CP) *</label>
                <textarea name="capaian_pembelajaran" id="inputCP" class="form-control" rows="4" required><?= htmlspecialchars($doc['capaian_pembelajaran']) ?></textarea>
                <small class="text-muted"><i class="bi bi-info-circle"></i> Anda dapat meng-generate ulang TP, ATP, dan Modul Ajar jika CP diubah.</small>
            </div>

            <div class="mt-4 text-end">
                <button type="button" class="btn btn-success fw-bold px-4 py-2" id="btnGenerateAI" onclick="generatePerangkatAI()">
                    <i class="bi bi-cpu-fill me-2"></i> Re-Generate TP, ATP & Modul Ajar dengan AI
                </button>
            </div>
        </div>
    </div>

    <!-- STEP 2: TUJUAN (TP) & ALUR (ATP) -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-dark text-white py-3">
            <h6 class="m-0 fw-bold"><i class="bi bi-diagram-3-fill me-2"></i> 2. Tujuan (TP) & Alur Tujuan Pembelajaran (ATP)</h6>
        </div>
        <div class="card-body p-4">
            <div class="mb-3">
                <label class="form-label fw-bold">Tujuan Pembelajaran (TP)</label>
                <textarea name="tujuan_pembelajaran" id="inputTP" class="form-control" rows="4"><?= htmlspecialchars($doc['tujuan_pembelajaran']) ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Alur Tujuan Pembelajaran (ATP)</label>
                <textarea name="alur_tujuan_pembelajaran" id="inputATP" class="form-control" rows="4"><?= htmlspecialchars($doc['alur_tujuan_pembelajaran']) ?></textarea>
            </div>
        </div>
    </div>

    <!-- STEP 3: MODUL AJAR LENGKAP -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-primary text-white py-3">
            <h6 class="m-0 fw-bold"><i class="bi bi-file-earmark-text-fill me-2"></i> 3. Komponen Rincian Modul Ajar (RPP Merdeka)</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Model Pembelajaran</label>
                    <input type="text" name="model_pembelajaran" class="form-control" value="<?= htmlspecialchars($modulData['model_pembelajaran'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Profil Pelajar Pancasila</label>
                    <?php $pancasila = $modulData['profil_pancasila'] ?? []; ?>
                    <div class="d-flex flex-wrap gap-3 mt-1">
                        <?php foreach (['Bernalar Kritis', 'Gotong Royong', 'Kreatif', 'Mandiri'] as $p): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="profil_pancasila[]" value="<?= $p ?>" id="p_<?= $p ?>" <?= in_array($p, $pancasila) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="p_<?= $p ?>"><?= $p ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Kompetensi Awal</label>
                    <textarea name="kompetensi_awal" id="inputKompetensiAwal" class="form-control" rows="2"><?= htmlspecialchars($modulData['kompetensi_awal'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Sarana & Prasarana</label>
                    <textarea name="sarana_prasarana" id="inputSarana" class="form-control" rows="2"><?= htmlspecialchars($modulData['sarana_prasarana'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Pemahaman Bermakna</label>
                    <textarea name="pemahaman_bermakna" class="form-control" rows="2"><?= htmlspecialchars($modulData['pemahaman_bermakna'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Pertanyaan Pemantik</label>
                    <textarea name="pertanyaan_pemantik" class="form-control" rows="2"><?= htmlspecialchars($modulData['pertanyaan_pemantik'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold text-success">Kegiatan Pendahuluan</label>
                <textarea name="kegiatan_pendahuluan" class="form-control" rows="3"><?= htmlspecialchars($modulData['kegiatan_pendahuluan'] ?? '') ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold text-primary">Kegiatan Inti</label>
                <textarea name="kegiatan_inti" class="form-control" rows="5"><?= htmlspecialchars($modulData['kegiatan_inti'] ?? '') ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold text-danger">Kegiatan Penutup</label>
                <textarea name="kegiatan_penutup" class="form-control" rows="3"><?= htmlspecialchars($modulData['kegiatan_penutup'] ?? '') ?></textarea>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Asesmen Diagnostik</label>
                    <textarea name="asesmen_diagnostik" class="form-control" rows="3"><?= htmlspecialchars($modulData['asesmen_diagnostik'] ?? '') ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Asesmen Formatif</label>
                    <textarea name="asesmen_formatif" class="form-control" rows="3"><?= htmlspecialchars($modulData['asesmen_formatif'] ?? '') ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Asesmen Sumatif</label>
                    <textarea name="asesmen_sumatif" class="form-control" rows="3"><?= htmlspecialchars($modulData['asesmen_sumatif'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Lembar Kerja Peserta Didik (LKPD)</label>
                <textarea name="lkpd_content" class="form-control" rows="4"><?= htmlspecialchars($modulData['lkpd_content'] ?? '') ?></textarea>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Glosarium</label>
                    <textarea name="glosarium" class="form-control" rows="2"><?= htmlspecialchars($modulData['glosarium'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Daftar Pustaka</label>
                    <textarea name="daftar_pustaka" class="form-control" rows="2"><?= htmlspecialchars($modulData['daftar_pustaka'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="row g-3 mt-2 border-top pt-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Nama Kepala Sekolah *</label>
                    <input type="text" name="nama_kepsek" class="form-control" value="<?= htmlspecialchars($modulData['nama_kepsek'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">NIP Kepala Sekolah</label>
                    <input type="text" name="nip_kepsek" class="form-control" value="<?= htmlspecialchars($modulData['nip_kepsek'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="card-footer p-3 text-end bg-light">
            <button type="submit" class="btn btn-primary-custom px-5 py-2 fw-bold fs-6">
                <i class="bi bi-check-circle-fill me-2"></i> Simpan Perubahan
            </button>
        </div>
    </div>
</form>

<script>
async function generatePerangkatAI() {
    const mapel = document.querySelector('input[name="mapel"]') ? document.querySelector('input[name="mapel"]').value : '<?= htmlspecialchars($doc['mapel']) ?>';
    const kelas = document.querySelector('select[name="kelas"]') ? document.querySelector('select[name="kelas"]').value : '<?= htmlspecialchars($doc['kelas']) ?>';
    const topik = document.querySelector('input[name="topik"]').value;
    const cp = document.getElementById('inputCP').value.trim();
    const btn = document.getElementById('btnGenerateAI');

    if (!topik || !cp) {
        alert('Mohon isi Topik dan Capaian Pembelajaran (CP) terlebih dahulu sebelum meng-generate AI.');
        return;
    }

    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> AI sedang berpikir...';
    btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('mapel', mapel);
        formData.append('kelas', kelas);
        formData.append('topik', topik);
        formData.append('cp', cp);

        const response = await fetch('<?= BASE_URL ?>/ajax/generate-perangkat-ai.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
            const data = result.data;
            
            if(data.tp) document.getElementById('inputTP').value = data.tp;
            if(data.atp) document.getElementById('inputATP').value = data.atp;
            
            if(data.kompetensi_awal) document.getElementById('inputKompetensiAwal').value = data.kompetensi_awal;
            if(data.sarana_prasarana) document.getElementById('inputSarana').value = data.sarana_prasarana;
            if(data.pemahaman_bermakna) document.querySelector('textarea[name="pemahaman_bermakna"]').value = data.pemahaman_bermakna;
            if(data.pertanyaan_pemantik) document.querySelector('textarea[name="pertanyaan_pemantik"]').value = data.pertanyaan_pemantik;
            if(data.kegiatan_pendahuluan) document.querySelector('textarea[name="kegiatan_pendahuluan"]').value = data.kegiatan_pendahuluan;
            if(data.kegiatan_inti) document.querySelector('textarea[name="kegiatan_inti"]').value = data.kegiatan_inti;
            if(data.kegiatan_penutup) document.querySelector('textarea[name="kegiatan_penutup"]').value = data.kegiatan_penutup;
            if(data.asesmen_diagnostik) document.querySelector('textarea[name="asesmen_diagnostik"]').value = data.asesmen_diagnostik;
            if(data.asesmen_formatif) document.querySelector('textarea[name="asesmen_formatif"]').value = data.asesmen_formatif;
            if(data.asesmen_sumatif) document.querySelector('textarea[name="asesmen_sumatif"]').value = data.asesmen_sumatif;
            if(data.lkpd_content) document.querySelector('textarea[name="lkpd_content"]').value = data.lkpd_content;
            if(data.glosarium) document.querySelector('textarea[name="glosarium"]').value = data.glosarium;
            if(data.daftar_pustaka) document.querySelector('textarea[name="daftar_pustaka"]').value = data.daftar_pustaka;

            alert('✨ AI Berhasil meng-update kelengkapan dokumen TP, ATP, dan Modul Ajar! Silakan periksa atau sesuaikan kembali bila diperlukan.');
        } else {
            alert('Gagal: ' + result.message);
        }
    } catch (error) {
        alert('Terjadi kesalahan jaringan atau server saat menghubungi AI.');
        console.error(error);
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
