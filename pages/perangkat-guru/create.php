<?php
/**
 * FORM BUAT NASKAH SOAL BARU
 */
$pageTitle = 'Buat Naskah Soal Baru - Perangkat Guru';
$activePage = 'perangkat-guru';
$activeModule = 'akademik';

require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_guru');

$pdo = getConnection();
require_once __DIR__ . '/init_soal.php';

$stmtMapels = $pdo->query("SELECT id, nama_mapel, kelompok FROM mata_pelajaran WHERE status = 'aktif' ORDER BY kelompok ASC, nama_mapel ASC");
$allMapels = $stmtMapels->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/subnav.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="card-header text-white px-4 py-3" style="background: linear-gradient(135deg, #1e1b4b 0%, #3730a3 100%);">
                <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-magic fs-5"></i> Buat Naskah Soal Ujian Baru
                </h5>
            </div>
            <form action="create_action.php" method="POST">
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Mata Pelajaran <span class="text-danger">*</span></label>
                            <select name="mapel_id" class="form-select" required onchange="syncMapelName(this)">
                                <option value="">-- Pilih Mata Pelajaran --</option>
                                <?php foreach ($allMapels as $m): ?>
                                    <option value="<?= $m['id'] ?>" data-name="<?= htmlspecialchars($m['nama_mapel']) ?>">
                                        <?= htmlspecialchars($m['nama_mapel']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="nama_mapel" id="namaMapelInput" value="">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Tingkat Kelas <span class="text-danger">*</span></label>
                            <select name="tingkat_kelas" class="form-select" required>
                                <option value="VII">Kelas VII (Tujuh)</option>
                                <option value="VIII">Kelas VIII (Delapan)</option>
                                <option value="IX">Kelas IX (Sembilan)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Target Kolom Penilaian Guru <span class="text-danger">*</span></label>
                            <select name="tipe_ujian" class="form-select fw-bold" id="tipeUjianSelect" required onchange="syncJudulUjian(this)">
                                <option value="sumatif_1">Sumatif 1 (Lingkup Materi 1 / Bab 1)</option>
                                <option value="sumatif_2">Sumatif 2 (Lingkup Materi 2 / Bab 2)</option>
                                <option value="sumatif_3">Sumatif 3 (Lingkup Materi 3 / Bab 3)</option>
                                <option value="sumatif_4">Sumatif 4 (Lingkup Materi 4 / Bab 4)</option>
                                <option value="ats">ATS (Asesmen Tengah Semester / PTS)</option>
                                <option value="aas" selected>AAS (Asesmen Akhir Semester / PAS)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Judul Kop Lembar Ujian <span class="text-danger">*</span></label>
                            <input type="text" name="judul_ujian" id="judulUjianInput" class="form-control" value="ASESMEN AKHIR SEMESTER (AAS)" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted">Semester <span class="text-danger">*</span></label>
                            <select name="semester" class="form-select" required>
                                <option value="Ganjil" selected>Ganjil</option>
                                <option value="Genap">Genap</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted">Tahun Ajaran <span class="text-danger">*</span></label>
                            <input type="text" name="tahun_ajaran" class="form-control font-monospace" value="2025 – 2026" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Alokasi Waktu (Menit) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="alokasi_waktu" class="form-control" value="90" min="15" max="300" required>
                                <span class="input-group-text">Menit</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Tanggal Ujian (Opsional)</label>
                            <input type="date" name="tanggal_ujian" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light px-4 py-3 d-flex justify-content-between align-items-center">
                    <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">Kembali</a>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                        <i class="bi bi-arrow-right-circle me-1"></i> Simpan &amp; Mulai Susun Soal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function syncMapelName(select) {
    var opt = select.options[select.selectedIndex];
    var name = opt.getAttribute('data-name') || '';
    document.getElementById('namaMapelInput').value = name;
}

function syncJudulUjian(select) {
    var val = select.value;
    var judulInput = document.getElementById('judulUjianInput');
    if (!judulInput) return;
    
    switch(val) {
        case 'sumatif_1':
            judulInput.value = 'PENILAIAN SUMATIF 1 (BAB 1)';
            break;
        case 'sumatif_2':
            judulInput.value = 'PENILAIAN SUMATIF 2 (BAB 2)';
            break;
        case 'sumatif_3':
            judulInput.value = 'PENILAIAN SUMATIF 3 (BAB 3)';
            break;
        case 'sumatif_4':
            judulInput.value = 'PENILAIAN SUMATIF 4 (BAB 4)';
            break;
        case 'ats':
            judulInput.value = 'ASESMEN TENGAH SEMESTER (ATS)';
            break;
        case 'aas':
            judulInput.value = 'ASESMEN AKHIR SEMESTER (AAS)';
            break;
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
