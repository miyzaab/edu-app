<?php
/**
 * DATA KELAS - Edit Kelas
 */
$pageTitle = 'Edit Data Kelas';
$activePage = 'kelas';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    redirect('index.php', 'danger', 'Data kelas tidak ditemukan.');
}

$stmt = $pdo->prepare("SELECT * FROM kelas WHERE id = :id");
$stmt->execute([':id' => $id]);
$kelas = $stmt->fetch();

if (!$kelas) {
    redirect('index.php', 'danger', 'Data kelas tidak ditemukan.');
}

// Helper format nama guru dengan gelar
function formatNamaGuruGelar($nama, $gDepan = '', $gBelakang = '')
{
    $depan = !empty($gDepan) ? trim($gDepan) . ' ' : '';
    $belakang = !empty($gBelakang) ? ', ' . trim($gBelakang) : '';
    return htmlspecialchars($depan . $nama . $belakang);
}

// Ambil Daftar Guru Aktif
$guruList = $pdo->query("
    SELECT u.id, u.nama_lengkap, gd.gelar_depan, gd.gelar_belakang, gd.nip
    FROM users u 
    LEFT JOIN guru_detail gd ON u.id = gd.user_id 
    WHERE u.role IN ('guru', 'bendahara', 'admin', 'operator') AND u.is_active = 1 
    ORDER BY u.nama_lengkap ASC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $namaKelas = trim($_POST['nama_kelas']);
    $tingkat = $_POST['tingkat'];
    $statusKelas = ($_POST['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
    $isActive = ($statusKelas === 'aktif') ? 1 : 0;
    $waliKelasId = !empty($_POST['wali_kelas_id']) ? (int) $_POST['wali_kelas_id'] : null;

    if ($namaKelas && $tingkat) {
        try {
            // Pastikan kolom status & is_active ada
            $checkCol = $pdo->query("SHOW COLUMNS FROM kelas LIKE 'status'")->fetch();
            if (!$checkCol) {
                $pdo->exec("ALTER TABLE kelas ADD COLUMN status ENUM('aktif','nonaktif') DEFAULT 'aktif' AFTER tingkat");
                $pdo->exec("ALTER TABLE kelas ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER status");
            }

            $update = $pdo->prepare("
                UPDATE kelas 
                SET nama_kelas = :nama, tingkat = :tingkat, wali_kelas_id = :wali, status = :status, is_active = :is_act 
                WHERE id = :id
            ");
            $update->execute([
                ':nama' => $namaKelas,
                ':tingkat' => $tingkat,
                ':wali' => $waliKelasId,
                ':status' => $statusKelas,
                ':is_act' => $isActive,
                ':id' => $id
            ]);
            redirect('index.php', 'success', "Data kelas '$namaKelas' berhasil diubah.");
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Nama kelas '$namaKelas' sudah digunakan oleh kelas lain.";
            } else {
                $error = "Gagal mengubah kelas: " . $e->getMessage();
            }
        }
    } else {
        $error = "Nama kelas dan tingkat wajib diisi.";
    }
}

$currentStatus = $kelas['status'] ?? (($kelas['is_active'] ?? 1) == 0 ? 'nonaktif' : 'aktif');

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="form-card">
            <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3">
                <h5 class="mb-0"><i class="bi bi-pencil-square text-primary"></i> Edit Data Kelas</h5>
                <a href="index.php" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i> Kembali</a>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-bold">Tingkat <span class="text-danger">*</span></label>
                    <select name="tingkat" class="form-select" required>
                        <option value="VII" <?= $kelas['tingkat'] === 'VII' ? 'selected' : '' ?>>VII (Tujuh)</option>
                        <option value="VIII" <?= $kelas['tingkat'] === 'VIII' ? 'selected' : '' ?>>VIII (Delapan)</option>
                        <option value="IX" <?= $kelas['tingkat'] === 'IX' ? 'selected' : '' ?>>IX (Sembilan)</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Nama Kelas <span class="text-danger">*</span></label>
                    <input type="text" name="nama_kelas" class="form-control"
                        value="<?= htmlspecialchars($kelas['nama_kelas']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Status Kelas <span class="text-danger">*</span></label>
                    <select name="status"
                        class="form-select fw-semibold <?= $currentStatus === 'aktif' ? 'text-success' : 'text-danger' ?>"
                        required>
                        <option value="aktif" class="text-success" <?= $currentStatus === 'aktif' ? 'selected' : '' ?>>
                            🟢 Aktif (Rombel Berjalan / Terbuka)
                        </option>
                        <option value="nonaktif" class="text-danger" <?= $currentStatus === 'nonaktif' ? 'selected' : '' ?>>
                            🔴 Tidak Aktif / Nonaktif (Arsip / Ditutup)
                        </option>
                    </select>
                    <small class="text-muted" style="font-size: 0.75rem;">Kelas nonaktif tidak akan muncul pada pilihan
                        input siswa baru.</small>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Wali Kelas <small
                            class="text-muted fw-normal">(Opsional)</small></label>
                    <select name="wali_kelas_id" class="form-select">
                        <option value="">-- Belum Ditentukan / Kosongkan --</option>
                        <?php foreach ($guruList as $g):
                            $selected = ((int) $kelas['wali_kelas_id'] === (int) $g['id']) ? 'selected' : '';
                            $nGuru = formatNamaGuruGelar($g['nama_lengkap'], $g['gelar_depan'], $g['gelar_belakang']);
                            ?>
                            <option value="<?= $g['id'] ?>" <?= $selected ?>><?= $nGuru ?>
                                <?= !empty($g['nip']) ? '(' . $g['nip'] . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted" style="font-size: 0.75rem;">Pilih guru yang ditugaskan sebagai wali kelas
                        ini.</small>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn-primary-custom"><i class="bi bi-save"></i> Simpan
                        Perubahan</button>
                    <a href="index.php" class="btn btn-light">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>