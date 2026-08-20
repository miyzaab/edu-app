<?php
/**
 * PERANGKAT AJAR KURIKULUM MERDEKA - Form Generator (CP, TP, ATP, & Modul Ajar)
 */
$pageTitle  = 'Buat Perangkat Ajar AI';
$activePage = 'perangkat-ajar';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_ajar');

$pdo = getConnection();
$userId = (int)$_SESSION['user_id'];

// Default Values
$defaultNamaSekolah = getSetting('nama_sekolah', SCHOOL_NAME);
$defaultTahun = date('Y') . '/' . (date('Y') + 1);

$userRole = strtolower($_SESSION['role'] ?? '');
$isGuruRole = ($userRole === 'guru');

if ($isGuruRole) {
    $stmtPlot = $pdo->prepare("
        SELECT DISTINCT m.nama_mapel
        FROM guru_mapel_kelas gmk
        JOIN mata_pelajaran m ON gmk.mapel_id = m.id
        WHERE gmk.user_id = :uid
    ");
    $stmtPlot->execute([':uid' => $userId]);
    $mapelOptions = $stmtPlot->fetchAll(PDO::FETCH_COLUMN);
} else {
    try {
        $mapelOptions = $pdo->query("SELECT nama_mapel FROM mata_pelajaran WHERE status = 'aktif' ORDER BY kelompok ASC, kode_mapel ASC")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $mapelOptions = [];
    }
}

// Submit Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $mapel    = trim($_POST['mapel'] ?? '');
    $kelas    = trim($_POST['kelas'] ?? 'VII');
    $fase     = trim($_POST['fase'] ?? 'D');
    $semester = $_POST['semester'] ?? 'Ganjil';
    $tahun    = trim($_POST['tahun_ajaran'] ?? $defaultTahun);
    $topik    = trim($_POST['topik'] ?? '');
    $elemen   = trim($_POST['elemen'] ?? '');
    $alokasi  = trim($_POST['alokasi_waktu'] ?? '2 JP x 40 Menit');
    
    $cp  = trim($_POST['capaian_pembelajaran'] ?? '');
    $tp  = trim($_POST['tujuan_pembelajaran'] ?? '');
    $atp = trim($_POST['alur_tujuan_pembelajaran'] ?? '');

    // Modul Ajar JSON Structure
    $modulData = [
        'nama_sekolah'         => trim($_POST['nama_sekolah'] ?? $defaultNamaSekolah),
        'nama_guru'            => trim($_POST['nama_guru'] ?? $_SESSION['nama_lengkap']),
        'nip_guru'             => trim($_POST['nip_guru'] ?? ''),
        'nama_kepsek'          => trim($_POST['nama_kepsek'] ?? getSetting('nama_kepsek', 'Kepala Sekolah, M.Pd')),
        'nip_kepsek'           => trim($_POST['nip_kepsek'] ?? getSetting('nip_kepsek', '')),
        'model_pembelajaran'   => trim($_POST['model_pembelajaran'] ?? 'Problem-Based Learning (PBL)'),
        'profil_pancasila'     => $_POST['profil_pancasila'] ?? ['Bernalar Kritis', 'Gotong Royong', 'Kreatif'],
        'kompetensi_awal'      => trim($_POST['kompetensi_awal'] ?? ''),
        'sarana_prasarana'     => trim($_POST['sarana_prasarana'] ?? 'Buku Siswa, PPT, LCD Projector, LKPD, HP/Laptop'),
        'target_siswa'         => trim($_POST['target_siswa'] ?? 'Peserta didik reguler / tipikal (28-32 siswa)'),
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
            $stmtInsert = $pdo->prepare("
                INSERT INTO perangkat_ajar 
                (user_id, mapel, kelas, fase, semester, tahun_ajaran, topik, elemen, alokasi_waktu, capaian_pembelajaran, tujuan_pembelajaran, alur_tujuan_pembelajaran, modul_ajar_json)
                VALUES 
                (:uid, :mapel, :kelas, :fase, :sem, :th, :topik, :elem, :alokasi, :cp, :tp, :atp, :json)
            ");
            $stmtInsert->execute([
                ':uid'     => $userId,
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
                ':json'    => json_encode($modulData, JSON_UNESCAPED_UNICODE)
            ]);

            $newId = $pdo->lastInsertId();
            redirect(BASE_URL . "/pages/perangkat-ajar/print.php?id=$newId", 'success', 'Perangkat Ajar berhasil dibuat! Halaman siap cetak/ekspor PDF.');
        } catch (PDOException $e) {
            $errorMsg = 'Gagal menyimpan dokumen: ' . $e->getMessage();
        }
    } else {
        $errorMsg = 'Mata Pelajaran, Kelas, dan Topik Materi wajib diisi.';
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold m-0"><i class="bi bi-magic text-primary"></i> Generator Perangkat Ajar AI</h5>
        <small class="text-muted">Buat Dokumen CP, TP, ATP & Modul Ajar Kurikulum Merdeka Terstruktur</small>
    </div>
    <a href="<?= BASE_URL ?>/pages/perangkat-ajar/index.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali ke Daftar
    </a>
</div>

<?php if (isset($errorMsg)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($errorMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" id="formPerangkatAjar">
    <input type="hidden" name="action" value="save">

    <!-- STEP 1: IDENTITAS DOKUMEN -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-primary text-white py-3">
            <h6 class="m-0 fw-bold"><i class="bi bi-info-circle-fill me-2"></i> 1. Identitas Mata Pelajaran & Kelas</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Mata Pelajaran *</label>
                    <select name="mapel" id="inputMapel" class="form-select" required onchange="autoSuggestElemen()">
                        <option value="">-- Pilih Mata Pelajaran --</option>
                        <?php foreach ($mapelOptions as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Kelas *</label>
                    <select name="kelas" id="inputKelas" class="form-select" required>
                        <option value="VII (Tujuh)">Kelas VII</option>
                        <option value="VIII (Delapan)">Kelas VIII</option>
                        <option value="IX (Sembilan)">Kelas IX</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Fase</label>
                    <input type="text" name="fase" class="form-control bg-light" value="D" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Semester</label>
                    <select name="semester" class="form-select">
                        <option value="Ganjil">Ganjil</option>
                        <option value="Genap">Genap</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Tahun Ajaran</label>
                    <input type="text" name="tahun_ajaran" class="form-control" value="<?= $defaultTahun ?>" required>
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Elemen / Domain Kurikulum</label>
                    <input type="text" name="elemen" id="inputElemen" class="form-control" placeholder="Contoh: Aljabar / Pemahaman IPA / Menyimak">
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold">Topik / Materi Pokok *</label>
                    <input type="text" name="topik" id="inputTopik" class="form-control" placeholder="Contoh: Persamaan Bentuk Aljabar / Sistem Pencernaan" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Alokasi Waktu</label>
                    <input type="text" name="alokasi_waktu" class="form-control" value="2 JP x 40 Menit">
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Nama Guru Pengampu *</label>
                    <input type="text" name="nama_guru" class="form-control" value="<?= htmlspecialchars($_SESSION['nama_lengkap']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">NIP Guru (Opsional)</label>
                    <input type="text" name="nip_guru" class="form-control" placeholder="-">
                </div>
            </div>
            
            <div class="mt-4">
                <label class="form-label fw-bold text-dark">Capaian Pembelajaran (CP) *</label>
                <textarea name="capaian_pembelajaran" id="inputCP" class="form-control" rows="4" placeholder="Ketik atau paste teks Capaian Pembelajaran dari SK BSKAP Kemendikbudristek di sini..." required></textarea>
                <small class="text-muted"><i class="bi bi-info-circle"></i> AI akan membaca CP ini untuk men-generate TP, ATP, dan kelengkapan Modul Ajar secara otomatis.</small>
            </div>

            <!-- Old global generate button removed for step-by-step approach -->
        </div>
    </div>

    <!-- STEP 2: TUJUAN (TP) & ALUR (ATP) -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-dark text-white py-3">
            <h6 class="m-0 fw-bold"><i class="bi bi-diagram-3-fill me-2"></i> 2. Tujuan (TP) & Alur Tujuan Pembelajaran (ATP)</h6>
        </div>
        <div class="card-body p-4">
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label fw-bold text-dark m-0">Tujuan Pembelajaran (TP)</label>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnGenTP" onclick="generatePerangkatAI('generate_tp', 'btnGenTP')">
                        <i class="bi bi-cpu-fill"></i> Generate TP via AI
                    </button>
                </div>
                <textarea name="tujuan_pembelajaran" id="inputTP" class="form-control" rows="4" placeholder="Gunakan KKO Bloom: Peserta didik mampu menjelaskan..."></textarea>
            </div>
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label fw-bold text-dark m-0">Alur Tujuan Pembelajaran (ATP)</label>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnGenATP" onclick="generatePerangkatAI('generate_atp', 'btnGenATP')">
                        <i class="bi bi-cpu-fill"></i> Generate ATP via AI
                    </button>
                </div>
                <textarea name="alur_tujuan_pembelajaran" id="inputATP" class="form-control" rows="4" placeholder="Urutan alur pembelajaran dari awal hingga asesmen..."></textarea>
            </div>
        </div>
    </div>

    <!-- STEP 3: MODUL AJAR LENGKAP -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold"><i class="bi bi-file-earmark-text-fill me-2"></i> 3. Komponen Rincian Modul Ajar (RPP Merdeka)</h6>
            <div>
                <button type="button" class="btn btn-sm btn-light text-primary fw-bold" id="btnGenModul" onclick="generatePerangkatAI('generate_modul', 'btnGenModul')">
                    <i class="bi bi-cpu-fill"></i> Generate Modul Ajar via AI
                </button>
                <span class="badge bg-white text-primary ms-2">Standar Kemendikbudristek</span>
            </div>
        </div>
        <div class="card-body p-4">
            <!-- Sub 1: Informasi Umum -->
            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">A. Informasi Umum</h6>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Model Pembelajaran</label>
                    <select name="model_pembelajaran" id="inputModel" class="form-select">
                        <option value="Problem-Based Learning (PBL)">Problem-Based Learning (PBL)</option>
                        <option value="Project-Based Learning (PjBL)">Project-Based Learning (PjBL)</option>
                        <option value="Discovery Learning">Discovery Learning</option>
                        <option value="Cooperative Learning">Cooperative Learning</option>
                        <option value="Direct Instruction (Pembelajaran Langsung)">Direct Instruction</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Profil Pelajar Pancasila</label>
                    <div class="d-flex flex-wrap gap-3 mt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="profil_pancasila[]" value="Bernalar Kritis" id="p1" checked>
                            <label class="form-check-label" for="p1">Bernalar Kritis</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="profil_pancasila[]" value="Gotong Royong" id="p2" checked>
                            <label class="form-check-label" for="p2">Gotong Royong</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="profil_pancasila[]" value="Kreatif" id="p3" checked>
                            <label class="form-check-label" for="p3">Kreatif</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="profil_pancasila[]" value="Mandiri" id="p4">
                            <label class="form-check-label" for="p4">Mandiri</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Kompetensi Awal</label>
                    <textarea name="kompetensi_awal" id="inputKompetensiAwal" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Sarana & Prasarana</label>
                    <textarea name="sarana_prasarana" id="inputSarana" class="form-control" rows="2"></textarea>
                </div>
            </div>

            <!-- Sub 2: Komponen Inti -->
            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3 mt-4">B. Komponen Inti & Kegiatan Pembelajaran</h6>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Pemahaman Bermakna</label>
                    <textarea name="pemahaman_bermakna" id="inputPemahaman" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Pertanyaan Pemantik</label>
                    <textarea name="pertanyaan_pemantik" id="inputPemantik" class="form-control" rows="2"></textarea>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold text-success"><i class="bi bi-play-circle me-1"></i> Kegiatan Pendahuluan (10-15 Menit)</label>
                <textarea name="kegiatan_pendahuluan" id="inputPendahuluan" class="form-control" rows="3"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold text-primary"><i class="bi bi-gear-wide-connected me-1"></i> Kegiatan Inti (50-60 Menit)</label>
                <textarea name="kegiatan_inti" id="inputInti" class="form-control" rows="5"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold text-danger"><i class="bi bi-stop-circle me-1"></i> Kegiatan Penutup (10-15 Menit)</label>
                <textarea name="kegiatan_penutup" id="inputPenutup" class="form-control" rows="3"></textarea>
            </div>

            <!-- Sub 3: Asesmen -->
            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3 mt-4">C. Asesmen & Evaluasi</h6>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Asesmen Diagnostik</label>
                    <textarea name="asesmen_diagnostik" id="inputDiagnostik" class="form-control" rows="3"></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Asesmen Formatif</label>
                    <textarea name="asesmen_formatif" id="inputFormatif" class="form-control" rows="3"></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Asesmen Sumatif</label>
                    <textarea name="asesmen_sumatif" id="inputSumatif" class="form-control" rows="3"></textarea>
                </div>
            </div>

            <!-- Sub 4: Lampiran & LKPD -->
            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3 mt-4">D. Lampiran & LKPD</h6>
            <div class="mb-3">
                <label class="form-label fw-bold">Lembar Kerja Peserta Didik (LKPD)</label>
                <textarea name="lkpd_content" id="inputLKPD" class="form-control" rows="4" placeholder="Ringkasan tugas / instruksi soal untuk siswa..."></textarea>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Glosarium</label>
                    <textarea name="glosarium" id="inputGlosarium" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Daftar Pustaka</label>
                    <textarea name="daftar_pustaka" id="inputPustaka" class="form-control" rows="2"></textarea>
                </div>
            </div>

            <div class="row g-3 mt-2 border-top pt-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Nama Kepala Sekolah *</label>
                    <input type="text" name="nama_kepsek" class="form-control" value="<?= htmlspecialchars(getSetting('nama_kepsek', 'Kepala Sekolah, M.Pd')) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">NIP Kepala Sekolah (Opsional)</label>
                    <input type="text" name="nip_kepsek" class="form-control" value="<?= htmlspecialchars(getSetting('nip_kepsek', '')) ?>">
                </div>
            </div>
        </div>

        <div class="card-footer p-3 text-end bg-light">
            <button type="submit" class="btn btn-primary-custom px-5 py-2 fw-bold fs-6">
                <i class="bi bi-file-earmark-pdf-fill me-2"></i> Simpan & Generate Dokumen PDF
            </button>
        </div>
    </div>
</form>

<script>
function autoSuggestElemen() {
    const mapel = document.getElementById('inputMapel').value;
    if(mapel === 'Matematika') document.getElementById('inputElemen').value = 'Aljabar & Fungsi';
    else if(mapel === 'Bahasa Indonesia') document.getElementById('inputElemen').value = 'Menyimak & Membaca';
    else if(mapel === 'Ilmu Pengetahuan Alam (IPA)') document.getElementById('inputElemen').value = 'Pemahaman IPA & Keterampilan Proses';
}

async function generatePerangkatAI(actionType, btnId) {
    const mapel = document.getElementById('inputMapel').value;
    const kelas = document.getElementById('inputKelas').value;
    const topik = document.getElementById('inputTopik').value;
    const cp = document.getElementById('inputCP').value.trim();
    const tp = document.getElementById('inputTP').value.trim();
    const atp = document.getElementById('inputATP').value.trim();
    const btn = document.getElementById(btnId);

    if (!mapel || !topik || !cp) {
        alert('Mohon isi Mata Pelajaran, Topik, dan Capaian Pembelajaran (CP) terlebih dahulu sebelum menggunakan AI.');
        return;
    }

    if (actionType === 'generate_atp' && !tp) {
        alert('Tujuan Pembelajaran (TP) harus diisi atau di-generate terlebih dahulu untuk membuat ATP.');
        return;
    }

    if (actionType === 'generate_modul' && (!tp || !atp)) {
        alert('Tujuan (TP) dan Alur (ATP) harus diisi atau di-generate terlebih dahulu untuk membuat isi Modul Ajar.');
        return;
    }

    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> AI memproses...';
    btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('action', actionType);
        formData.append('mapel', mapel);
        formData.append('kelas', kelas);
        formData.append('topik', topik);
        formData.append('cp', cp);
        formData.append('tp', tp);
        formData.append('atp', atp);

        const response = await fetch('<?= BASE_URL ?>/ajax/generate-perangkat-ai.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
            const data = result.data;
            
            // Auto Fill Text Areas with AI response based on action
            if (actionType === 'generate_tp' && data.tp) {
                document.getElementById('inputTP').value = data.tp;
                alert('✨ Tujuan Pembelajaran (TP) berhasil dirumuskan AI! Silakan periksa dan edit bila perlu.');
            } else if (actionType === 'generate_atp' && data.atp) {
                document.getElementById('inputATP').value = data.atp;
                alert('✨ Alur Tujuan Pembelajaran (ATP) berhasil dirumuskan AI! Silakan periksa dan edit bila perlu.');
            } else if (actionType === 'generate_modul') {
                if(data.kompetensi_awal) document.getElementById('inputKompetensiAwal').value = data.kompetensi_awal;
                if(data.sarana_prasarana) document.getElementById('inputSarana').value = data.sarana_prasarana;
                if(data.pemahaman_bermakna) document.getElementById('inputPemahaman').value = data.pemahaman_bermakna;
                if(data.pertanyaan_pemantik) document.getElementById('inputPemantik').value = data.pertanyaan_pemantik;
                if(data.kegiatan_pendahuluan) document.getElementById('inputPendahuluan').value = data.kegiatan_pendahuluan;
                if(data.kegiatan_inti) document.getElementById('inputInti').value = data.kegiatan_inti;
                if(data.kegiatan_penutup) document.getElementById('inputPenutup').value = data.kegiatan_penutup;
                if(data.asesmen_diagnostik) document.getElementById('inputDiagnostik').value = data.asesmen_diagnostik;
                if(data.asesmen_formatif) document.getElementById('inputFormatif').value = data.asesmen_formatif;
                if(data.asesmen_sumatif) document.getElementById('inputSumatif').value = data.asesmen_sumatif;
                if(data.lkpd_content) document.getElementById('inputLKPD').value = data.lkpd_content;
                if(data.glosarium) document.getElementById('inputGlosarium').value = data.glosarium;
                if(data.daftar_pustaka) document.getElementById('inputPustaka').value = data.daftar_pustaka;
                
                alert('✨ Seluruh kelengkapan Modul Ajar berhasil dirancang oleh AI! Silakan review dan sesuaikan.');
            }
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
