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
                            <label class="form-label fw-bold small text-muted">Tipe &amp; Judul Ujian <span class="text-danger">*</span></label>
                            <input type="text" name="judul_ujian" class="form-control" value="UJIAN AKHIR SEMESTER" required>
                            <input type="hidden" name="tipe_ujian" value="AAS">
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
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted">Petunjuk Umum</label>
                            <textarea name="petunjuk_umum" class="form-control" rows="4" placeholder="Petunjuk pengerjaan soal...">1. Berdoalah sebelum mengerjakan soal.
2. Tulislah nama, kelas, dan nomor absen pada lembar jawaban yang telah disediakan.
3. Periksa dan bacalah soal-soal dengan teliti sebelum Anda menjawabnya.
4. Dahulukan menjawab soal-soal yang Anda anggap mudah.</textarea>
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
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
