<?php
/**
 * PERANGKAT AJAR KURIKULUM MERDEKA - SINGLE GENERATOR HUB
 * Form Input Satu Pintu: Guru hanya mengisi Bab Semester 1 & 2 serta JP per pekan.
 * Sistem otomatis meng-generate CP, TP, ATP, dan Modul Ajar berstandar manusiawi.
 */
$pageTitle  = 'Buat Perangkat Ajar Otomatis';
$activePage = 'perangkat-ajar';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_ajar');
require_once __DIR__ . '/../../includes/perangkat_generator.php';

$pdo = getConnection();
$userId = (int)$_SESSION['user_id'];
$userRole = strtolower($_SESSION['role'] ?? '');
$isGuruRole = ($userRole === 'guru');

// Default Values dari Setting Sekolah
$defaultNamaSekolah = getSetting('nama_sekolah', SCHOOL_NAME);
$defaultNamaKepsek  = getSetting('nama_kepsek', 'Kepala Sekolah, M.Pd');
$defaultNipKepsek   = getSetting('nip_kepsek', '—');
$defaultTahun       = date('Y') . '/' . (date('Y') + 1);

// Dapatkan data guru login
$guruNama = $_SESSION['nama_lengkap'] ?? 'Guru Pengampu';
$guruNip  = '—';
try {
    $stmtUser = $pdo->prepare("
        SELECT u.nama_lengkap, gd.nip 
        FROM users u 
        LEFT JOIN guru_detail gd ON u.id = gd.user_id 
        WHERE u.id = :id LIMIT 1
    ");
    $stmtUser->execute([':id' => $userId]);
    $userData = $stmtUser->fetch();
    if ($userData) {
        $guruNama = $userData['nama_lengkap'] ?? $guruNama;
        $guruNip  = !empty($userData['nip']) ? $userData['nip'] : '—';
    }
} catch (Exception $e) {
    // Fallback jika guru_detail tidak ada
}

// Plotting Mata Pelajaran Guru jika role Guru
$assignedMapels = [];
if ($isGuruRole) {
    $stmtPlot = $pdo->prepare("
        SELECT DISTINCT m.nama_mapel
        FROM guru_mapel_kelas gmk
        JOIN mata_pelajaran m ON gmk.mapel_id = m.id
        WHERE gmk.user_id = :uid
        ORDER BY m.nama_mapel ASC
    ");
    $stmtPlot->execute([':uid' => $userId]);
    $assignedMapels = $stmtPlot->fetchAll(PDO::FETCH_COLUMN);
    $mapelOptions = !empty($assignedMapels) ? $assignedMapels : [];
} else {
    $mapelOptions = [];
}

// Fallback jika bukan guru atau guru belum diplot mapel
if (empty($mapelOptions)) {
    try {
        $mapelOptions = $pdo->query("SELECT nama_mapel FROM mata_pelajaran WHERE status = 'aktif' ORDER BY kelompok ASC, kode_mapel ASC")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $mapelOptions = [];
    }
}

// PROSES SUBMIT GENERATE
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_perangkat') {
    $mapel       = trim($_POST['mapel'] ?? '');
    $kelas       = trim($_POST['kelas'] ?? 'VII');
    $jpPerPekan  = max(1, min(10, (int)($_POST['jp_per_pekan'] ?? 4)));
    $tahunAjaran = trim($_POST['tahun_ajaran'] ?? $defaultTahun);
    $babSmt1     = trim($_POST['bab_semester_1'] ?? '');
    $babSmt2     = trim($_POST['bab_semester_2'] ?? '');

    if (empty($mapel)) {
        $_SESSION['flash_msg'] = ['type' => 'danger', 'text' => 'Mohon pilih mata pelajaran.'];
    } elseif (empty($babSmt1) && empty($babSmt2)) {
        $_SESSION['flash_msg'] = ['type' => 'danger', 'text' => 'Mohon isi daftar bab pelajaran setidaknya untuk satu semester.'];
    } else {
        try {
            // Generate seluruh perangkat ajar via Generator Engine
            $generated = PerangkatGenerator::generatePerangkatLengkap([
                'mapel'          => $mapel,
                'kelas'          => $kelas,
                'jp_per_pekan'   => $jpPerPekan,
                'tahun_ajaran'   => $tahunAjaran,
                'nama_sekolah'   => $defaultNamaSekolah,
                'nama_guru'      => $guruNama,
                'nip_guru'       => $guruNip,
                'nama_kepsek'    => $defaultNamaKepsek,
                'nip_kepsek'     => $defaultNipKepsek,
                'bab_semester_1' => $babSmt1,
                'bab_semester_2' => $babSmt2
            ]);

            // Simpan ke database
            $stmtInsert = $pdo->prepare("
                INSERT INTO perangkat_ajar 
                (user_id, mapel, kelas, fase, semester, tahun_ajaran, topik, elemen, alokasi_waktu, jp_per_pekan, capaian_pembelajaran, tujuan_pembelajaran, alur_tujuan_pembelajaran, modul_ajar_json)
                VALUES 
                (:uid, :mapel, :kelas, :fase, :sem, :th, :topik, :elem, :alokasi, :jp, :cp, :tp, :atp, :json)
            ");

            $stmtInsert->execute([
                ':uid'     => $userId,
                ':mapel'   => $generated['mapel'],
                ':kelas'   => $generated['kelas'],
                ':fase'    => $generated['fase'],
                ':sem'     => $generated['semester'],
                ':th'      => $generated['tahun_ajaran'],
                ':topik'   => $generated['topik'],
                ':elem'    => $generated['elemen'],
                ':alokasi' => $generated['alokasi_waktu'],
                ':jp'      => $generated['jp_per_pekan'],
                ':cp'      => $generated['capaian_pembelajaran'],
                ':tp'      => $generated['tujuan_pembelajaran'],
                ':atp'     => $generated['alur_tujuan_pembelajaran'],
                ':json'    => $generated['modul_ajar_json']
            ]);

            $newId = (int)$pdo->lastInsertId();

            // Redirect ke halaman View / Hub Dokumen
            redirect("view.php?id={$newId}", 'success', 'Alhamdulillah! Perangkat Ajar lengkap 1 Tahun (CP, TP, ATP, & Modul Ajar) berhasil dibuat secara otomatis!');
        } catch (Exception $e) {
            $_SESSION['flash_msg'] = ['type' => 'danger', 'text' => 'Gagal membuat perangkat ajar: ' . $e->getMessage()];
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/subnav.php';
?>

<div class="row justify-content-center">
    <div class="col-xl-10 col-lg-11 col-12">

        <!-- HEADER BANNER -->
        <div class="card border-0 rounded-4 shadow-sm mb-4 overflow-hidden" 
             style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #047857 100%); color: #ffffff;">
            <div class="card-body p-4 p-md-5 position-relative">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3"
                    style="background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.22); backdrop-filter: blur(10px);">
                    <i class="bi bi-magic text-emerald-400 fs-6"></i>
                    <span class="small fw-bold text-white tracking-wide">GENERATOR SATU PINTU (SINGLE HUB)</span>
                </div>
                <h2 class="fw-extrabold mb-2" style="font-family: 'Outfit', sans-serif;">
                    Buat Perangkat Ajar Kurikulum Merdeka
                </h2>
                <p class="text-white-50 mb-0 fs-6" style="max-width: 780px; line-height: 1.6;">
                    Guru cukup memasukkan <strong>daftar bab pelajaran</strong> untuk Semester 1 &amp; Semester 2 serta <strong>beban jam pelajaran per pekan</strong>. Sistem secara otomatis menyusun narasi lengkap berstandar pedagogik resmi untuk <strong>CP, TP, ATP, dan Modul Ajar</strong> yang siap diunduh ke PDF.
                </p>
            </div>
        </div>

        <?php if (!empty($_SESSION['flash_msg'])): ?>
            <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_msg']['type']) ?> alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i> <?= htmlspecialchars($_SESSION['flash_msg']['text']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash_msg']); ?>
        <?php endif; ?>

        <!-- FORM UTAMA -->
        <form method="POST" action="create.php" id="formGeneratorPerangkat">
            <input type="hidden" name="action" value="generate_perangkat">

            <!-- KARTU 1: IDENTITAS PEMBELAJARAN -->
            <div class="card border-0 rounded-4 shadow-sm mb-4" style="background: #ffffff;">
                <div class="card-body p-4 p-md-4">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width: 32px; height: 32px; background: #e0f2fe; color: #0284c7;">
                            <span class="fw-bold fs-6">1</span>
                        </div>
                        <h5 class="fw-bold mb-0 text-dark" style="font-family: 'Outfit', sans-serif;">
                            Identitas Pembelajaran &amp; Beban Jam
                        </h5>
                    </div>

                    <div class="row g-3">
                        <!-- Mata Pelajaran -->
                        <div class="col-md-5 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">
                                MATA PELAJARAN <span class="text-danger">*</span>
                            </label>
                            <select name="mapel" id="selectMapel" class="form-select rounded-3 py-2.5 fw-semibold border-secondary-subtle" required>
                                <option value="" disabled selected>-- Pilih Mata Pelajaran --</option>
                                <?php foreach ($mapelOptions as $m): ?>
                                    <option value="<?= htmlspecialchars($m) ?>" <?= (isset($_POST['mapel']) && $_POST['mapel'] === $m) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($m) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text extra-small text-muted">
                                Mendukung Mapel Umum, Eksakta, Bahasa, maupun Mapel Diniyah/Pesantren (Fiqih, Aqidah, Nahwu, dll).
                            </div>
                        </div>

                        <!-- Jenjang Kelas (Pill Buttons) -->
                        <div class="col-md-4 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">
                                TINGKAT KELAS (FASE D) <span class="text-danger">*</span>
                            </label>
                            <div class="d-flex gap-2">
                                <?php $selectedKelas = $_POST['kelas'] ?? 'VII'; ?>
                                <?php foreach (['VII' => 'Kelas VII', 'VIII' => 'Kelas VIII', 'IX' => 'Kelas IX'] as $kVal => $kLabel): ?>
                                    <label class="flex-fill text-center border rounded-3 py-2 cursor-pointer transition-all btn-kelas-option <?= $selectedKelas === $kVal ? 'bg-primary text-white border-primary fw-bold shadow-sm' : 'bg-light text-dark border-secondary-subtle' ?>"
                                           style="cursor: pointer;">
                                        <input type="radio" name="kelas" value="<?= $kVal ?>" class="d-none" <?= $selectedKelas === $kVal ? 'checked' : '' ?> onchange="updateKelasPills()">
                                        <?= $kLabel ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-text extra-small text-muted">
                                Fase D (SMP / MTs) mencakup jenjang Kelas 7, 8, dan 9.
                            </div>
                        </div>

                        <!-- Jam Pelajaran (JP) Per Pekan -->
                        <div class="col-md-3 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">
                                JP PER PEKAN <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <select name="jp_per_pekan" id="selectJp" class="form-select rounded-start-3 py-2.5 fw-bold text-primary border-secondary-subtle" onchange="recalculateEstimations()">
                                    <?php 
                                    $currentJp = (int)($_POST['jp_per_pekan'] ?? 4);
                                    for ($i = 2; $i <= 8; $i++): 
                                    ?>
                                        <option value="<?= $i ?>" <?= $currentJp === $i ? 'selected' : '' ?>>
                                            <?= $i ?> JP / Pekan
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <span class="input-group-text bg-light text-muted small fw-semibold rounded-end-3 border-secondary-subtle">
                                    @ 40 Mnt
                                </span>
                            </div>
                            <div class="form-text extra-small text-muted">
                                Menentukan alokasi waktu otomatis di ATP &amp; Modul.
                            </div>
                        </div>
                    </div>

                    <!-- Tahun Ajaran & Info Guru Ringkas -->
                    <div class="row g-3 mt-1 pt-3 border-top">
                        <div class="col-md-4 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">TAHUN AJARAN</label>
                            <input type="text" name="tahun_ajaran" value="<?= htmlspecialchars($_POST['tahun_ajaran'] ?? $defaultTahun) ?>" class="form-control form-control-sm rounded-3 py-2 fw-semibold" placeholder="2026/2027" required>
                        </div>
                        <div class="col-md-4 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">GURU MATA PELAJARAN</label>
                            <input type="text" value="<?= htmlspecialchars($guruNama) ?>" class="form-control form-control-sm rounded-3 py-2 bg-light text-muted" readonly>
                        </div>
                        <div class="col-md-4 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">KEPALA SEKOLAH</label>
                            <input type="text" value="<?= htmlspecialchars($defaultNamaKepsek) ?>" class="form-control form-control-sm rounded-3 py-2 bg-light text-muted" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KARTU 2: INPUT DAFTAR BAB PELAJARAN (1 TAHUN) -->
            <div class="card border-0 rounded-4 shadow-sm mb-4" style="background: #ffffff;">
                <div class="card-body p-4 p-md-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle d-flex align-items-center justify-content-center"
                                 style="width: 32px; height: 32px; background: #dcfce7; color: #16a34a;">
                                <span class="fw-bold fs-6">2</span>
                            </div>
                            <h5 class="fw-bold mb-0 text-dark" style="font-family: 'Outfit', sans-serif;">
                                Input Daftar Bab Pelajaran (Semester 1 &amp; 2)
                            </h5>
                        </div>
                        <span class="badge bg-light text-muted border px-3 py-2 rounded-pill small fw-normal">
                            <i class="bi bi-info-circle me-1"></i> Ketik atau tempel (paste) 1 baris untuk 1 bab
                        </span>
                    </div>

                    <div class="row g-4">
                        <!-- SEMESTER 1 (GANJIL) -->
                        <div class="col-lg-6 col-12">
                            <div class="p-3 rounded-4 border" style="background: #f8fafc; border-color: #e2e8f0 !important;">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-primary text-white px-2.5 py-1 rounded-pill small fw-bold">
                                            SEMESTER 1 (GANJIL)
                                        </span>
                                    </div>
                                    <span id="badgeInfoSmt1" class="extra-small fw-bold text-primary">
                                        18 Pekan Effective (72 JP)
                                    </span>
                                </div>
                                <p class="extra-small text-muted mb-2">
                                    Masukkan judul bab pelajaran ganjil. Contoh:
                                </p>
                                <textarea name="bab_semester_1" id="inputBabSmt1" rows="7" 
                                          class="form-control rounded-3 font-monospace small p-3 bg-white"
                                          placeholder="Bab 1: Thaharah dan Bersuci&#10;Bab 2: Shalat Fardhu dan Ketentuannya&#10;Bab 3: Shalat Sunnah dan Keutamaannya"
                                          style="line-height: 1.6; resize: vertical;"
                                          oninput="recalculateEstimations()"><?= htmlspecialchars($_POST['bab_semester_1'] ?? '') ?></textarea>
                                
                                <div class="d-flex align-items-center justify-content-between mt-2 pt-1">
                                    <small id="countBabSmt1" class="text-muted extra-small fw-semibold">
                                        0 Bab terdeteksi
                                    </small>
                                    <small id="jpPerBabSmt1" class="text-muted extra-small">
                                        Alokasi per bab: —
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- SEMESTER 2 (GENAP) -->
                        <div class="col-lg-6 col-12">
                            <div class="p-3 rounded-4 border" style="background: #f8fafc; border-color: #e2e8f0 !important;">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge text-white px-2.5 py-1 rounded-pill small fw-bold" style="background: #6366f1;">
                                            SEMESTER 2 (GENAP)
                                        </span>
                                    </div>
                                    <span id="badgeInfoSmt2" class="extra-small fw-bold text-indigo" style="color: #4f46e5;">
                                        16 Pekan Effective (64 JP)
                                    </span>
                                </div>
                                <p class="extra-small text-muted mb-2">
                                    Masukkan judul bab pelajaran genap. Contoh:
                                </p>
                                <textarea name="bab_semester_2" id="inputBabSmt2" rows="7" 
                                          class="form-control rounded-3 font-monospace small p-3 bg-white"
                                          placeholder="Bab 4: Puasa Wajib dan Puasa Sunnah&#10;Bab 5: Zakat Fitrah dan Zakat Mal&#10;Bab 6: Haji dan Umrah"
                                          style="line-height: 1.6; resize: vertical;"
                                          oninput="recalculateEstimations()"><?= htmlspecialchars($_POST['bab_semester_2'] ?? '') ?></textarea>

                                <div class="d-flex align-items-center justify-content-between mt-2 pt-1">
                                    <small id="countBabSmt2" class="text-muted extra-small fw-semibold">
                                        0 Bab terdeteksi
                                    </small>
                                    <small id="jpPerBabSmt2" class="text-muted extra-small">
                                        Alokasi per bab: —
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TOMBOL AKSI GENERATE -->
            <div class="card border-0 rounded-4 shadow-sm mb-5 p-3" style="background: #ffffff;">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <a href="index.php" class="btn btn-light rounded-pill px-4 fw-bold text-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Batal &amp; Kembali
                    </a>

                    <button type="submit" id="btnSubmitGenerate" class="btn btn-emerald px-5 py-3 rounded-pill fw-extrabold text-white shadow-lg d-flex align-items-center gap-2"
                            style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none; font-size: 1.05rem; letter-spacing: 0.3px; transition: all 0.25s ease;">
                        <i class="bi bi-stars fs-5"></i>
                        <span>✨ Generate Perangkat Ajar Lengkap (1 Tahun)</span>
                    </button>
                </div>
            </div>

        </form>

    </div>
</div>

<script>
    // Update visual radio button pill kelas
    function updateKelasPills() {
        const labels = document.querySelectorAll('.btn-kelas-option');
        labels.forEach(lbl => {
            const radio = lbl.querySelector('input[type="radio"]');
            if (radio && radio.checked) {
                lbl.classList.remove('bg-light', 'text-dark', 'border-secondary-subtle');
                lbl.classList.add('bg-primary', 'text-white', 'border-primary', 'fw-bold', 'shadow-sm');
            } else {
                lbl.classList.add('bg-light', 'text-dark', 'border-secondary-subtle');
                lbl.classList.remove('bg-primary', 'text-white', 'border-primary', 'fw-bold', 'shadow-sm');
            }
        });
    }

    // Hitung estimasi alokasi waktu JP & Pertemuan secara dinamis
    function recalculateEstimations() {
        const jp = parseInt(document.getElementById('selectJp')?.value || '4', 10);
        const txtSmt1 = (document.getElementById('inputBabSmt1')?.value || '').trim();
        const txtSmt2 = (document.getElementById('inputBabSmt2')?.value || '').trim();

        const babsSmt1 = txtSmt1 ? txtSmt1.split('\n').filter(l => l.trim().length > 0) : [];
        const babsSmt2 = txtSmt2 ? txtSmt2.split('\n').filter(l => l.trim().length > 0) : [];

        const totalJpSmt1 = 18 * jp;
        const totalJpSmt2 = 16 * jp;

        // Semester 1
        const count1 = babsSmt1.length;
        document.getElementById('badgeInfoSmt1').textContent = `18 Pekan Efektif (${totalJpSmt1} JP)`;
        document.getElementById('countBabSmt1').textContent = `${count1} Bab terdeteksi`;
        if (count1 > 0) {
            const jpPerBab = Math.round(totalJpSmt1 / count1);
            const pert = Math.round(jpPerBab / jp);
            document.getElementById('jpPerBabSmt1').textContent = `Alokasi rata-rata: ~${jpPerBab} JP (${pert} Pertemuan / bab)`;
        } else {
            document.getElementById('jpPerBabSmt1').textContent = `Alokasi per bab: —`;
        }

        // Semester 2
        const count2 = babsSmt2.length;
        document.getElementById('badgeInfoSmt2').textContent = `16 Pekan Efektif (${totalJpSmt2} JP)`;
        document.getElementById('countBabSmt2').textContent = `${count2} Bab terdeteksi`;
        if (count2 > 0) {
            const jpPerBab = Math.round(totalJpSmt2 / count2);
            const pert = Math.round(jpPerBab / jp);
            document.getElementById('jpPerBabSmt2').textContent = `Alokasi rata-rata: ~${jpPerBab} JP (${pert} Pertemuan / bab)`;
        } else {
            document.getElementById('jpPerBabSmt2').textContent = `Alokasi per bab: —`;
        }
    }

    // Auto-spinner saat tombol submit ditekan
    document.addEventListener('DOMContentLoaded', function() {
        updateKelasPills();
        recalculateEstimations();

        const form = document.getElementById('formGeneratorPerangkat');
        const btn = document.getElementById('btnSubmitGenerate');
        if (form && btn) {
            form.addEventListener('submit', function() {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Meng-generate Perangkat Ajar (CP, TP, ATP, Modul)...';
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
