<?php
/**
 * PORTAL ORANG TUA - Konfirmasi Pembayaran Online
 * Halaman publik, tidak memerlukan login.
 */
session_start();
require_once __DIR__ . '/config/koneksi.php';

$pdo = getConnection();

// Ambil data untuk dropdown awal
$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();
$jenisLainnya = $pdo->query("SELECT * FROM jenis_pembayaran WHERE status='aktif' ORDER BY nama_pembayaran")->fetchAll();

$namaSekolah = getSetting('nama_sekolah', SCHOOL_NAME);
$logoPath = getSetting('logo_path', '');

// Proses Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siswa_id   = (int)($_POST['siswa_id'] ?? 0);
    $jenis_raw  = $_POST['jenis'] ?? '';
    $nominal    = (float)str_replace(['.', ','], ['', '.'], $_POST['nominal'] ?? '0');
    $catatan    = trim($_POST['catatan'] ?? '');
    
    // Validasi file
    $fileOk = false;
    $dbPath = '';
    if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['bukti_transfer'];
        $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        if (in_array($file['type'], $allowed) && $file['size'] <= 5 * 1024 * 1024) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'bukti_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $uploadDir = __DIR__ . '/assets/uploads/bukti/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $uploadPath = $uploadDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $dbPath = BASE_URL . '/assets/uploads/bukti/' . $filename;
                $fileOk = true;
            }
        } else {
            $_SESSION['flash_portal'] = ['type' => 'warning', 'message' => 'Format file tidak didukung atau ukuran melebihi 5MB. Gunakan JPG/PNG/PDF.'];
        }
    }

    if ($siswa_id && $nominal > 0 && $fileOk) {
        try {
            // === SPP: Insert per bulan yang dicentang ===
            if ($jenis_raw === 'spp') {
                $bulanDipilih = $_POST['bulan_spp'] ?? [];
                $tahunSpp     = (int)($_POST['tahun_spp'] ?? date('Y'));
                
                if (empty($bulanDipilih)) {
                    $_SESSION['flash_portal'] = ['type' => 'warning', 'message' => 'Pilih minimal 1 bulan SPP.'];
                    header("Location: portal-ortu.php");
                    exit;
                }

                $nominalPerBulan = $nominal / count($bulanDipilih);

                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO pembayaran_pending (siswa_id, jenis, jenis_pembayaran_id, bulan, tahun, nominal, bukti_transfer, catatan) VALUES (:s, 'spp', NULL, :b, :t, :n, :bukti, :c)");
                
                foreach ($bulanDipilih as $bln) {
                    $stmt->execute([
                        ':s' => $siswa_id,
                        ':b' => (int)$bln,
                        ':t' => $tahunSpp,
                        ':n' => $nominalPerBulan,
                        ':bukti' => $dbPath,
                        ':c' => $catatan
                    ]);
                }
                $pdo->commit();
                
                $_SESSION['flash_portal'] = ['type' => 'success', 'message' => 'Bukti pembayaran SPP untuk ' . count($bulanDipilih) . ' bulan berhasil dikirim! Menunggu verifikasi dari bendahara.'];
                header("Location: portal-ortu.php");
                exit;
            }
            
            // === Pembayaran Lainnya ===
            if (strpos($jenis_raw, 'lainnya_') === 0) {
                $jenis_pembayaran_id = (int)str_replace('lainnya_', '', $jenis_raw);
                
                $stmt = $pdo->prepare("INSERT INTO pembayaran_pending (siswa_id, jenis, jenis_pembayaran_id, bulan, tahun, nominal, bukti_transfer, catatan) VALUES (:s, 'lainnya', :jp, NULL, NULL, :n, :bukti, :c)");
                $stmt->execute([
                    ':s' => $siswa_id,
                    ':jp' => $jenis_pembayaran_id,
                    ':n' => $nominal,
                    ':bukti' => $dbPath,
                    ':c' => $catatan
                ]);
                
                $_SESSION['flash_portal'] = ['type' => 'success', 'message' => 'Bukti pembayaran berhasil dikirim! Menunggu verifikasi dari bendahara sekolah.'];
                header("Location: portal-ortu.php");
                exit;
            }

            $_SESSION['flash_portal'] = ['type' => 'warning', 'message' => 'Jenis pembayaran tidak valid.'];

        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_portal'] = ['type' => 'danger', 'message' => 'Terjadi kesalahan database: ' . $e->getMessage()];
        }
    } elseif (!$fileOk && !isset($_SESSION['flash_portal'])) {
        $_SESSION['flash_portal'] = ['type' => 'warning', 'message' => 'Mohon lengkapi semua data dan lampirkan bukti transfer.'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Orang Tua - Konfirmasi Pembayaran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        body { background: #f4f7fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem 0; }
        .portal-card { background: #fff; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); overflow: hidden; width: 100%; max-width: 650px; }
        .portal-header { background: linear-gradient(135deg, #007bff 0%, #00c6ff 100%); color: #fff; padding: 2rem; text-align: center; }
        .portal-body { padding: 2.5rem; }
        .wizard-step { display: none; }
        .wizard-step.active { display: block; animation: fadeIn 0.4s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .step-indicator { display: flex; justify-content: center; margin-bottom: 2rem; gap: 8px; }
        .step-dot { width: 12px; height: 12px; border-radius: 50%; background: #e0e0e0; transition: all 0.3s; }
        .step-dot.active { background: #007bff; width: 24px; border-radius: 6px; }
        .step-dot.completed { background: #198754; }
        .bulan-box { border: 2px solid #dee2e6; border-radius: 10px; padding: 10px; text-align: center; cursor: pointer; transition: all .2s; user-select: none; }
        .bulan-box:hover { border-color: #007bff; background: #f0f7ff; }
        .bulan-box.checked { border-color: #198754; background: #d1e7dd; }
        .bulan-box input[type=checkbox] { display: none; }
    </style>
</head>
<body>

<div class="container d-flex justify-content-center">
    <div class="portal-card">
        <div class="portal-header">
            <?php if ($logoPath): ?>
                <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo" style="height: 60px; margin-bottom: 1rem;">
            <?php else: ?>
                <div style="font-size: 3rem; margin-bottom: 1rem;">🏫</div>
            <?php endif; ?>
            <h3 class="fw-bold mb-1">Konfirmasi Pembayaran</h3>
            <p class="mb-0 opacity-75">Portal Orang Tua & Wali Murid<br><?= htmlspecialchars($namaSekolah) ?></p>
        </div>

        <div class="portal-body">
            <?php if (isset($_SESSION['flash_portal'])): ?>
                <div class="alert alert-<?= $_SESSION['flash_portal']['type'] ?> alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['flash_portal']['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_portal']); ?>
            <?php endif; ?>

            <div class="step-indicator" id="stepIndicator">
                <div class="step-dot active"></div>
                <div class="step-dot"></div>
                <div class="step-dot"></div>
            </div>

            <form id="formPortal" method="POST" enctype="multipart/form-data">
                
                <!-- STEP 1: Pilih Siswa -->
                <div class="wizard-step active" id="step1">
                    <h5 class="mb-4 text-center fw-bold text-dark"><i class="bi bi-search"></i> Identitas Siswa</h5>
                    <div class="mb-3">
                        <label class="form-label text-muted fw-semibold">Pilih Kelas</label>
                        <select id="kelas_id" class="form-select form-select-lg" onchange="loadSiswa()">
                            <option value="">-- Pilih Kelas --</option>
                            <?php foreach ($kelasList as $k): ?>
                                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-muted fw-semibold">Pilih Siswa</label>
                        <select name="siswa_id" id="siswa_id" class="form-select form-select-lg" disabled>
                            <option value="">-- Pilih Kelas Terlebih Dahulu --</option>
                        </select>
                    </div>
                    <button type="button" class="btn-primary-custom w-100 py-3" onclick="nextStep(2)">Lanjut ke Pembayaran <i class="bi bi-arrow-right"></i></button>
                    <div class="text-center mt-3">
                        <a href="<?= BASE_URL ?>/index.php" class="text-muted text-decoration-none" style="font-size: .85rem;">Login Staf Bendahara</a>
                    </div>
                </div>

                <!-- STEP 2: Rincian Pembayaran -->
                <div class="wizard-step" id="step2">
                    <h5 class="mb-4 text-center fw-bold text-dark"><i class="bi bi-wallet2"></i> Rincian Pembayaran</h5>
                    <div class="mb-3">
                        <label class="form-label text-muted fw-semibold">Jenis Pembayaran</label>
                        <select name="jenis" id="jenis" class="form-select form-select-lg" onchange="toggleJenis()">
                            <option value="" data-tipe="" data-nominal="0">-- Pilih Jenis --</option>
                            <option value="spp" data-tipe="spp" data-nominal="0">📅 Pembayaran SPP</option>
                            <?php foreach ($jenisLainnya as $j): ?>
                                <option value="lainnya_<?= $j['id'] ?>" data-tipe="lainnya" data-nominal="<?= $j['nominal_default'] ?>">
                                    <?= htmlspecialchars($j['nama_pembayaran']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Panel SPP: Pilih Bulan -->
                    <div id="panelSpp" style="display:none;">
                        <div class="mb-3">
                            <label class="form-label text-muted fw-semibold">Tahun SPP</label>
                            <input type="number" name="tahun_spp" id="tahun_spp" class="form-control" value="<?= date('Y') ?>" min="2020" max="2099" onchange="loadUnpaidMonths()">
                        </div>
                        <label class="form-label text-muted fw-semibold d-block">Pilih Bulan yang Akan Dibayar</label>
                        <div id="containerBulan" class="row g-2 mb-3">
                            <div class="text-center text-muted py-3"><i class="bi bi-hourglass-split"></i> Memuat data...</div>
                        </div>
                        <div class="alert alert-info" style="font-size:.8rem">
                            <i class="bi bi-info-circle"></i> Hanya bulan yang <strong>belum lunas</strong> dan belum menunggu verifikasi yang ditampilkan. Bulan yang sudah dibayar otomatis tersembunyi.
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-muted fw-semibold">Nominal yang Ditransfer (Rp)</label>
                        <input type="text" name="nominal" id="nominal" class="form-control form-control-lg fw-bold" placeholder="Contoh: 350.000" onkeyup="formatRupiahInput(this)">
                        <small class="text-muted" id="hintNominal"></small>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light py-3 w-50" onclick="prevStep(1)"><i class="bi bi-arrow-left"></i> Kembali</button>
                        <button type="button" class="btn-primary-custom py-3 w-50" onclick="nextStep(3)">Lanjut Upload <i class="bi bi-cloud-upload"></i></button>
                    </div>
                </div>

                <!-- STEP 3: Upload Bukti -->
                <div class="wizard-step" id="step3">
                    <h5 class="mb-4 text-center fw-bold text-dark"><i class="bi bi-cloud-upload"></i> Upload Bukti Transfer</h5>
                    
                    <div class="alert alert-info" style="font-size: .85rem;">
                        Pastikan foto/screenshot bukti transfer terlihat jelas. Maksimal ukuran file 5MB.
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted fw-semibold">Foto / Bukti Transfer *</label>
                        <input type="file" name="bukti_transfer" id="bukti_transfer" class="form-control form-control-lg" accept="image/*,.pdf" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-muted fw-semibold">Catatan Tambahan (Opsional)</label>
                        <textarea name="catatan" class="form-control" rows="2" placeholder="Nama pengirim rekening, dll..."></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light py-3 w-50" onclick="prevStep(2)"><i class="bi bi-arrow-left"></i> Kembali</button>
                        <button type="submit" class="btn btn-success py-3 w-50 fw-bold" onclick="return validasiSubmit()"><i class="bi bi-send-check"></i> Kirim Bukti</button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
function formatRupiahInput(input) {
    let value = input.value.replace(/[^,\d]/g, '').toString();
    let split = value.split(',');
    let sisa  = split[0].length % 3;
    let rupiah  = split[0].substr(0, sisa);
    let ribuan  = split[0].substr(sisa).match(/\d{3}/gi);

    if (ribuan) {
        let separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }
    input.value = rupiah;
}

function updateIndicators(step) {
    const dots = document.querySelectorAll('.step-dot');
    dots.forEach((dot, index) => {
        dot.className = 'step-dot';
        if (index + 1 < step) dot.classList.add('completed');
        if (index + 1 === step) dot.classList.add('active');
    });
}

function nextStep(step) {
    if (step === 2) {
        if (!document.getElementById('siswa_id').value) {
            alert('Silakan pilih kelas dan siswa terlebih dahulu!');
            return;
        }
    }
    if (step === 3) {
        const jenis = document.getElementById('jenis').value;
        const nominal = document.getElementById('nominal').value;
        if (!jenis || !nominal) {
            alert('Silakan pilih jenis pembayaran dan masukkan nominal!');
            return;
        }
        // Validasi SPP: minimal 1 bulan dicentang
        if (jenis === 'spp') {
            const checked = document.querySelectorAll('input[name="bulan_spp[]"]:checked');
            if (checked.length === 0) {
                alert('Silakan centang minimal 1 bulan SPP yang ingin dibayar!');
                return;
            }
        }
    }

    document.querySelectorAll('.wizard-step').forEach(el => el.classList.remove('active'));
    document.getElementById('step' + step).classList.add('active');
    updateIndicators(step);
}

function prevStep(step) {
    document.querySelectorAll('.wizard-step').forEach(el => el.classList.remove('active'));
    document.getElementById('step' + step).classList.add('active');
    updateIndicators(step);
}

function toggleJenis() {
    const sel = document.getElementById('jenis');
    const opt = sel.options[sel.selectedIndex];
    const tipe = opt.dataset.tipe || '';
    const nom = opt.dataset.nominal || 0;
    const panelSpp = document.getElementById('panelSpp');
    const inputNominal = document.getElementById('nominal');
    const hintNominal = document.getElementById('hintNominal');

    // Tampilkan/Sembunyikan panel SPP
    if (tipe === 'spp') {
        panelSpp.style.display = 'block';
        loadUnpaidMonths();
        hintNominal.textContent = 'Masukkan total nominal yang akan ditransfer (akan dibagi rata ke bulan yang dicentang).';
        inputNominal.value = '';
    } else {
        panelSpp.style.display = 'none';
        hintNominal.textContent = '';
        if (nom > 0) {
            inputNominal.value = nom;
            formatRupiahInput(inputNominal);
        } else {
            inputNominal.value = '';
        }
    }
}

function loadUnpaidMonths() {
    const siswaId = document.getElementById('siswa_id').value;
    const tahun = document.getElementById('tahun_spp').value;
    const container = document.getElementById('containerBulan');

    container.innerHTML = '<div class="col-12 text-center text-muted py-3"><i class="bi bi-hourglass-split"></i> Memuat data...</div>';

    if (!siswaId || !tahun) {
        container.innerHTML = '<div class="col-12 text-center text-muted py-3">Pilih siswa dan tahun terlebih dahulu.</div>';
        return;
    }

    fetch('<?= BASE_URL ?>/api/get-unpaid-spp.php?siswa_id=' + siswaId + '&tahun=' + tahun)
        .then(r => r.json())
        .then(data => {
            if (data.length === 0) {
                container.innerHTML = '<div class="col-12 text-center text-success py-3"><i class="bi bi-check-circle-fill"></i> Semua bulan sudah lunas untuk tahun ini! 🎉</div>';
                return;
            }
            let html = '';
            data.forEach(item => {
                html += `
                <div class="col-4 col-md-3">
                    <label class="bulan-box d-block" onclick="toggleBulanBox(this)">
                        <input type="checkbox" name="bulan_spp[]" value="${item.bulan}">
                        <div class="fw-bold" style="font-size:.85rem">${item.nama}</div>
                    </label>
                </div>`;
            });
            container.innerHTML = html;
        })
        .catch(err => {
            container.innerHTML = '<div class="col-12 text-center text-danger py-3">Gagal memuat data bulan.</div>';
        });
}

function toggleBulanBox(label) {
    const cb = label.querySelector('input[type=checkbox]');
    // Toggle happens naturally from label click
    setTimeout(() => {
        if (cb.checked) {
            label.classList.add('checked');
        } else {
            label.classList.remove('checked');
        }
    }, 10);
}

function validasiSubmit() {
    if (!document.getElementById('bukti_transfer').value) {
        alert('Mohon lampirkan file bukti transfer!');
        return false;
    }
    return true;
}

// AJAX Load Siswa
function loadSiswa() {
    const kelasId = document.getElementById('kelas_id').value;
    const siswaSelect = document.getElementById('siswa_id');
    
    siswaSelect.innerHTML = '<option value="">Loading...</option>';
    siswaSelect.disabled = true;

    if (kelasId) {
        fetch('<?= BASE_URL ?>/api/get-siswa.php?kelas_id=' + kelasId)
            .then(response => response.json())
            .then(data => {
                siswaSelect.innerHTML = '<option value="">-- Pilih Siswa --</option>';
                if (data.length > 0) {
                    data.forEach(s => {
                        siswaSelect.innerHTML += `<option value="${s.id}">${s.nis} - ${s.nama}</option>`;
                    });
                    siswaSelect.disabled = false;
                } else {
                    siswaSelect.innerHTML = '<option value="">(Belum ada siswa di kelas ini)</option>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                siswaSelect.innerHTML = '<option value="">Gagal memuat data</option>';
            });
    } else {
        siswaSelect.innerHTML = '<option value="">-- Pilih Kelas Terlebih Dahulu --</option>';
    }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
