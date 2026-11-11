<?php
/**
 * DATA GURU - CRUD Master Data Guru Lengkap (Modul Master Data)
 * Pengelolaan profil detail pengajar / ustadz
 */
$pageTitle = 'Data Guru';
$activePage = 'guru';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('guru');

$pdo = getConnection();
require_once __DIR__ . '/../../config/db_auto_sync.php';
runDatabaseAutoSync($pdo);
$allPermissions = getAllPermissions();

// Pastikan direktori upload foto guru ada
$uploadDir = __DIR__ . '/../../assets/uploads/guru/';
if (!file_exists($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

// ===== HELPER FOTO GURU =====
function uploadFotoGuru($fileInputKey, $existingFoto = '')
{
    global $uploadDir;
    if (isset($_FILES[$fileInputKey]) && $_FILES[$fileInputKey]['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES[$fileInputKey]['tmp_name'];
        $fileName = $_FILES[$fileInputKey]['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ext, $allowed)) {
            $newName = 'guru_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $target = $uploadDir . $newName;
            if (move_uploaded_file($tmpName, $target)) {
                return 'assets/uploads/guru/' . $newName;
            }
        }
    }
    return $existingFoto;
}

// ===== HELPER UPSERT GURU DETAIL =====
function upsertGuruDetail(PDO $pdo, int $uid, array $data): void
{
    $stmtDet = $pdo->prepare("
        INSERT INTO guru_detail
        (user_id, nip, gelar_depan, gelar_belakang, tempat_lahir, tanggal_lahir, jenis_kelamin, alamat, no_hp, foto, status_kepegawaian, pendidikan_jenjang, pendidikan_jurusan, pendidikan_kampus, spesialisasi, jabatan_sekolah)
        VALUES
        (:uid, :nip, :gdepan, :gbelakang, :tlahir, :tgl, :jk, :alamat, :hp, :foto, :st, :pjenjang, :pjurusan, :pkampus, :spesial, :jabatan)
        ON DUPLICATE KEY UPDATE
        nip = :nip2, gelar_depan = :gdepan2, gelar_belakang = :gbelakang2, tempat_lahir = :tlahir2, tanggal_lahir = :tgl2,
        jenis_kelamin = :jk2, alamat = :alamat2, no_hp = :hp2, foto = :foto2, status_kepegawaian = :st2,
        pendidikan_jenjang = :pjenjang2, pendidikan_jurusan = :pjurusan2, pendidikan_kampus = :pkampus2,
        spesialisasi = :spesial2, jabatan_sekolah = :jabatan2
    ");
    $stmtDet->execute([
        ':uid'       => $uid,
        ':nip'       => $data['nip'],
        ':gdepan'    => $data['gelar_depan'],
        ':gbelakang' => $data['gelar_belakang'],
        ':tlahir'    => $data['tempat_lahir'],
        ':tgl'       => $data['tanggal_lahir'],
        ':jk'        => $data['jenis_kelamin'],
        ':alamat'    => $data['alamat'],
        ':hp'        => $data['no_hp'],
        ':foto'      => $data['foto'],
        ':st'        => $data['status_kepegawaian'],
        ':pjenjang'  => $data['pendidikan_jenjang'],
        ':pjurusan'  => $data['pendidikan_jurusan'],
        ':pkampus'   => $data['pendidikan_kampus'],
        ':spesial'   => $data['spesialisasi'],
        ':jabatan'   => $data['jabatan_sekolah'],
        ':nip2'      => $data['nip'],
        ':gdepan2'   => $data['gelar_depan'],
        ':gbelakang2'=> $data['gelar_belakang'],
        ':tlahir2'   => $data['tempat_lahir'],
        ':tgl2'      => $data['tanggal_lahir'],
        ':jk2'       => $data['jenis_kelamin'],
        ':alamat2'   => $data['alamat'],
        ':hp2'       => $data['no_hp'],
        ':foto2'     => $data['foto'],
        ':st2'       => $data['status_kepegawaian'],
        ':pjenjang2' => $data['pendidikan_jenjang'],
        ':pjurusan2' => $data['pendidikan_jurusan'],
        ':pkampus2'  => $data['pendidikan_kampus'],
        ':spesial2'  => $data['spesialisasi'],
        ':jabatan2'  => $data['jabatan_sekolah'],
    ]);
}

// ===== PROSES TAMBAH GURU =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $addMode = ($_POST['add_mode'] ?? 'new') === 'existing' ? 'existing' : 'new';
    $existingUserId = (int) ($_POST['existing_user_id'] ?? 0);

    $nama = trim($_POST['nama_lengkap'] ?? '');
    $gelarDepan = trim($_POST['gelar_depan'] ?? '');
    $gelarBelakang = trim($_POST['gelar_belakang'] ?? '');
    $nip = trim($_POST['nip'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $detailData = [
        'nip'                => $nip,
        'gelar_depan'        => $gelarDepan,
        'gelar_belakang'     => $gelarBelakang,
        'tempat_lahir'       => trim($_POST['tempat_lahir'] ?? ''),
        'tanggal_lahir'      => !empty($_POST['tanggal_lahir']) ? $_POST['tanggal_lahir'] : null,
        'jenis_kelamin'      => $_POST['jenis_kelamin'] ?? 'L',
        'alamat'             => trim($_POST['alamat'] ?? ''),
        'no_hp'              => trim($_POST['no_hp'] ?? ''),
        'foto'               => uploadFotoGuru('foto', ''),
        'status_kepegawaian' => trim($_POST['status_kepegawaian'] ?? 'GTY (Guru Tetap Yayasan)'),
        'pendidikan_jenjang' => trim($_POST['pendidikan_jenjang'] ?? 'S1'),
        'pendidikan_jurusan' => trim($_POST['pendidikan_jurusan'] ?? ''),
        'pendidikan_kampus'  => trim($_POST['pendidikan_kampus'] ?? ''),
        'spesialisasi'       => trim($_POST['spesialisasi'] ?? ''),
        'jabatan_sekolah'    => trim($_POST['jabatan_sekolah'] ?? 'Guru Mata Pelajaran'),
    ];

    if ($addMode === 'existing') {
        if ($existingUserId <= 0) {
            redirect('index.php', 'warning', 'Silakan pilih akun yang akan ditambahkan ke Data Guru.');
        }

        try {
            $stmtUser = $pdo->prepare("SELECT id, nama_lengkap, username, role FROM users WHERE id = :id LIMIT 1");
            $stmtUser->execute([':id' => $existingUserId]);
            $targetUser = $stmtUser->fetch();

            if (!$targetUser) {
                redirect('index.php', 'danger', 'Akun pengguna tidak ditemukan.');
            }

            $pdo->beginTransaction();

            // Perbarui nama lengkap bila diisi, tanpa mengubah role akun (Admin/Operator/Bendahara tetap aman)
            if ($nama !== '' && $nama !== $targetUser['nama_lengkap']) {
                $pdo->prepare("UPDATE users SET nama_lengkap = :n WHERE id = :id")->execute([':n' => $nama, ':id' => $existingUserId]);
                if ($existingUserId === (int) ($_SESSION['user_id'] ?? 0)) {
                    $_SESSION['nama_lengkap'] = $nama;
                }
            }

            upsertGuruDetail($pdo, $existingUserId, $detailData);

            // Assign Wali Kelas jika dipilih
            $waliKelasId = !empty($_POST['wali_kelas_id']) ? (int) $_POST['wali_kelas_id'] : 0;
            if ($waliKelasId > 0) {
                $pdo->prepare("UPDATE kelas SET wali_kelas_id = NULL WHERE wali_kelas_id = :uid AND id != :kid")->execute([':uid' => $existingUserId, ':kid' => $waliKelasId]);
                $pdo->prepare("UPDATE kelas SET wali_kelas_id = :uid WHERE id = :kid")->execute([':uid' => $existingUserId, ':kid' => $waliKelasId]);
            }

            $pdo->commit();
            $displayName = $nama !== '' ? $nama : $targetUser['nama_lengkap'];
            redirect('index.php', 'success', "Akun '{$displayName}' ({$targetUser['role']}) berhasil ditambahkan ke Data Guru tanpa mengubah hak aksesnya.");
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            redirect('index.php', 'danger', 'Gagal menautkan akun ke Data Guru: ' . $e->getMessage());
        }
    }

    if ($nama && $username && $password) {
        try {
            $pdo->beginTransaction();

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role, is_active) VALUES (:u, :p, :n, 'guru', :a)");
            $stmt->execute([':u' => $username, ':p' => $hash, ':n' => $nama, ':a' => $isActive]);
            $newGuruId = (int) $pdo->lastInsertId();

            upsertGuruDetail($pdo, $newGuruId, $detailData);

            // Set permission default untuk guru
            $defaults = getDefaultPermissions('guru');
            $stmtPerm = $pdo->prepare("INSERT INTO user_permissions (user_id, permission_key, is_allowed) VALUES (:uid, :pkey, :allowed)");
            foreach (array_keys($allPermissions) as $perm) {
                $allowed = in_array($perm, $defaults) ? 1 : 0;
                $stmtPerm->execute([':uid' => $newGuruId, ':pkey' => $perm, ':allowed' => $allowed]);
            }

            // Assign Wali Kelas jika dipilih
            $waliKelasId = !empty($_POST['wali_kelas_id']) ? (int) $_POST['wali_kelas_id'] : 0;
            if ($waliKelasId > 0) {
                $stmtWk = $pdo->prepare("UPDATE kelas SET wali_kelas_id = :uid WHERE id = :kid");
                $stmtWk->execute([':uid' => $newGuruId, ':kid' => $waliKelasId]);
            }

            $pdo->commit();
            redirect('index.php', 'success', "Data Guru '{$nama}' berhasil ditambahkan secara lengkap.");
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e->getCode() == 23000) {
                $stmtDup = $pdo->prepare("SELECT u.role, d.user_id AS has_detail FROM users u LEFT JOIN guru_detail d ON u.id = d.user_id WHERE u.username = :u LIMIT 1");
                $stmtDup->execute([':u' => $username]);
                $dupRow = $stmtDup->fetch();
                if ($dupRow && $dupRow['role'] !== 'guru' && empty($dupRow['has_detail'])) {
                    redirect('index.php', 'danger', "Username '{$username}' sudah terdaftar sebagai akun {$dupRow['role']}. Klik Tambah Data Guru lalu pilih opsi 'Pilih dari Akun yang Sudah Ada' untuk menambahkan akun tersebut sebagai Guru.");
                }
                redirect('index.php', 'danger', "Username '{$username}' sudah digunakan oleh akun lain.");
            } else {
                redirect('index.php', 'danger', "Gagal menambah data guru: " . $e->getMessage());
            }
        }
    } else {
        redirect('index.php', 'warning', "Nama Lengkap, Username, dan Password wajib diisi.");
    }
}

// ===== PROSES EDIT GURU =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $id = (int) ($_POST['id'] ?? 0);
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $gelarDepan = trim($_POST['gelar_depan'] ?? '');
    $gelarBelakang = trim($_POST['gelar_belakang'] ?? '');
    $nip = trim($_POST['nip'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    if ($id === (int) ($_SESSION['user_id'] ?? 0)) {
        $isActive = 1;
    }

    if ($id > 0 && $nama && $username) {
        try {
            $pdo->beginTransaction();

            // Pertahankan role akun (jangan menimpa role Admin/Operator/Bendahara menjadi 'guru')
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = :n, username = :u, password = :p, is_active = :a WHERE id = :id");
                $stmt->execute([':n' => $nama, ':u' => $username, ':p' => $hash, ':a' => $isActive, ':id' => $id]);
                if ($id === (int) ($_SESSION['user_id'] ?? 0)) {
                    $_SESSION['auth_password_hash'] = $hash;
                }
            } else {
                $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = :n, username = :u, is_active = :a WHERE id = :id");
                $stmt->execute([':n' => $nama, ':u' => $username, ':a' => $isActive, ':id' => $id]);
            }

            if ($id === (int) ($_SESSION['user_id'] ?? 0)) {
                $_SESSION['nama_lengkap'] = $nama;
            }

            // Ambil foto lama
            $stmtOld = $pdo->prepare("SELECT foto FROM guru_detail WHERE user_id = :uid LIMIT 1");
            $stmtOld->execute([':uid' => $id]);
            $oldFoto = $stmtOld->fetchColumn() ?: '';

            upsertGuruDetail($pdo, $id, [
                'nip'                => $nip,
                'gelar_depan'        => $gelarDepan,
                'gelar_belakang'     => $gelarBelakang,
                'tempat_lahir'       => trim($_POST['tempat_lahir'] ?? ''),
                'tanggal_lahir'      => !empty($_POST['tanggal_lahir']) ? $_POST['tanggal_lahir'] : null,
                'jenis_kelamin'      => $_POST['jenis_kelamin'] ?? 'L',
                'alamat'             => trim($_POST['alamat'] ?? ''),
                'no_hp'              => trim($_POST['no_hp'] ?? ''),
                'foto'               => uploadFotoGuru('foto', $oldFoto),
                'status_kepegawaian' => trim($_POST['status_kepegawaian'] ?? 'GTY (Guru Tetap Yayasan)'),
                'pendidikan_jenjang' => trim($_POST['pendidikan_jenjang'] ?? 'S1'),
                'pendidikan_jurusan' => trim($_POST['pendidikan_jurusan'] ?? ''),
                'pendidikan_kampus'  => trim($_POST['pendidikan_kampus'] ?? ''),
                'spesialisasi'       => trim($_POST['spesialisasi'] ?? ''),
                'jabatan_sekolah'    => trim($_POST['jabatan_sekolah'] ?? 'Guru Mata Pelajaran'),
            ]);

            // Update penugasan Wali Kelas
            $waliKelasId = isset($_POST['wali_kelas_id']) ? (int) $_POST['wali_kelas_id'] : 0;
            if ($waliKelasId > 0) {
                // Reset kelas lain yang sebelumnya ditugaskan ke guru ini
                $pdo->prepare("UPDATE kelas SET wali_kelas_id = NULL WHERE wali_kelas_id = :uid AND id != :kid")->execute([':uid' => $id, ':kid' => $waliKelasId]);
                // Tetapkan guru ini ke kelas yang dipilih
                $pdo->prepare("UPDATE kelas SET wali_kelas_id = :uid WHERE id = :kid")->execute([':uid' => $id, ':kid' => $waliKelasId]);
            } else {
                // Hapus jabatan wali kelas jika dipilih bukan wali kelas
                $pdo->prepare("UPDATE kelas SET wali_kelas_id = NULL WHERE wali_kelas_id = :uid")->execute([':uid' => $id]);
            }

            $pdo->commit();
            redirect('index.php', 'success', "Data Guru '{$nama}' berhasil diperbarui.");
        } catch (Exception $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000) {
                redirect('index.php', 'danger', "Username '{$username}' sudah digunakan.");
            } else {
                redirect('index.php', 'danger', "Gagal memperbarui data guru: " . $e->getMessage());
            }
        }
    } else {
        redirect('index.php', 'warning', "Nama dan Username tidak boleh kosong.");
    }
}

// AMBIL LIST KELAS UNTUK DROPDOWN WALI KELAS
$allKelas = $pdo->query("SELECT id, nama_kelas, tingkat FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();

// AMBIL AKUN ADMIN / STAFF YANG BELUM MASUK DATA GURU
$existingStaffUsers = $pdo->query("
    SELECT u.id, u.username, u.nama_lengkap, u.role
    FROM users u
    LEFT JOIN guru_detail d ON u.id = d.user_id
    WHERE u.role != 'guru' AND d.user_id IS NULL AND u.is_active = 1
    ORDER BY u.nama_lengkap ASC
")->fetchAll();

// AMBIL LIST DATA GURU LENGKAP WITH LEFT JOIN (termasuk Admin/Staff yang bertindak juga sebagai Guru)
$guruList = $pdo->query("
    SELECT u.id, u.username, u.nama_lengkap, u.role, u.is_active, u.created_at,
           d.nip, d.gelar_depan, d.gelar_belakang, d.tempat_lahir, d.tanggal_lahir,
           d.jenis_kelamin, d.alamat, d.no_hp, d.foto, d.status_kepegawaian,
           d.pendidikan_jenjang, d.pendidikan_jurusan, d.pendidikan_kampus,
           d.spesialisasi, d.jabatan_sekolah,
           (SELECT k.nama_kelas FROM kelas k WHERE k.wali_kelas_id = u.id LIMIT 1) AS kelas_wali,
           (SELECT k.id FROM kelas k WHERE k.wali_kelas_id = u.id LIMIT 1) AS kelas_wali_id
    FROM users u
    LEFT JOIN guru_detail d ON u.id = d.user_id
    WHERE (u.role = 'guru' OR d.user_id IS NOT NULL)
    ORDER BY u.nama_lengkap ASC
")->fetchAll();

// AMBIL MAPEL & KELAS YANG DIAMPU DARI GURU_MAPEL_KELAS
$plottingRaw = $pdo->query("
    SELECT gmk.user_id, m.nama_mapel, k.nama_kelas
    FROM guru_mapel_kelas gmk
    JOIN mata_pelajaran m ON gmk.mapel_id = m.id
    JOIN kelas k ON gmk.kelas_id = k.id
    ORDER BY m.nama_mapel, k.nama_kelas
")->fetchAll();

$guruAmpu = [];
foreach ($plottingRaw as $p) {
    $uid = $p['user_id'];
    $mName = $p['nama_mapel'];
    $kName = $p['nama_kelas'];
    if (!isset($guruAmpu[$uid][$mName])) {
        $guruAmpu[$uid][$mName] = [];
    }
    $guruAmpu[$uid][$mName][] = $kName;
}

// HITUNG STATISTIK
$totalGuru = count($guruList);
$guruAktif = count(array_filter($guruList, fn($g) => (int) $g['is_active'] === 1));
$totalPlotting = count($plottingRaw);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- STATISTIC CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 d-flex flex-row align-items-center gap-3">
                <div class="rounded-3 p-3 text-white d-flex align-items-center justify-content-center"
                    style="background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%); width: 54px; height: 54px;">
                    <i class="bi bi-person-badge fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Total Guru</div>
                    <h3 class="fw-bold mb-0 text-dark"><?= number_format($totalGuru) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 d-flex flex-row align-items-center gap-3">
                <div class="rounded-3 p-3 text-white d-flex align-items-center justify-content-center"
                    style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); width: 54px; height: 54px;">
                    <i class="bi bi-check-circle-fill fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Guru Aktif</div>
                    <h3 class="fw-bold mb-0 text-success"><?= number_format($guruAktif) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 d-flex flex-row align-items-center gap-3">
                <div class="rounded-3 p-3 text-white d-flex align-items-center justify-content-center"
                    style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); width: 54px; height: 54px;">
                    <i class="bi bi-journal-bookmark-fill fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Total Penugasan Kelas</div>
                    <h3 class="fw-bold mb-0 text-primary"><?= number_format($totalPlotting) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN TABLE CARD -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
        <div
            class="card-header bg-white p-3.5 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h5 class="fw-extrabold text-dark mb-0"><i class="bi bi-person-badge text-teal me-2"></i> Data Detail
                    Guru / Ustadz </h5>
                <p class="text-muted small mb-0">Kelola biodata lengkap, NIP, gelar, kontak, foto, spesialisasi, dan
                    penugasan mengajar</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="../mapel/plotting.php"
                    class="btn btn-outline-primary rounded-3 px-3 py-2 fw-bold small d-inline-flex align-items-center gap-2">
                    <i class="bi bi-person-workspace"></i> Plotting Guru Pengampu
                </a>
                <button type="button"
                    class="btn btn-primary-custom px-3 py-2 rounded-3 fw-bold small d-inline-flex align-items-center gap-2"
                    data-bs-toggle="modal" data-bs-target="#modalTambahGuru">
                    <i class="bi bi-plus-lg"></i> Tambah Data Guru
                </button>
            </div>
        </div>

        <div class="card-body p-0">
            <!-- SEARCH BAR -->
            <div class="p-3 bg-light border-bottom">
                <div class="input-group" style="max-width: 380px;">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchGuru" class="form-control border-start-0 ps-0"
                        placeholder="Cari nama, NIP, spesialisasi...">
                </div>
            </div>

            <form id="bulkActionForm" action="bulk_action.php" method="POST">
                <input type="hidden" name="bulk_action_type" id="bulkActionType" value="">

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tableGuru">
                        <thead class="bg-light text-muted small fw-bold text-uppercase" style="letter-spacing: 0.5px;">
                            <tr>
                                <th style="width: 40px;" class="ps-3 text-center">
                                    <input type="checkbox" id="selectAll" class="bulk-checkbox" title="Pilih Semua">
                                </th>
                                <th style="width: 50px;">No</th>
                                <th>Guru & Gelar Akademik</th>
                                <th>NIP / Jabatan</th>
                                <th>Pendidikan & Spesialisasi</th>
                                <th>Kontak & WA</th>
                                <th class="text-center">Status</th>
                                <th class="text-end pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($guruList)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="bi bi-person-slash fs-1 d-block mb-2 text-secondary"></i>
                                        Belum ada data guru terdaftar. Klik <strong>Tambah Data Guru</strong> untuk
                                        menambah.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($guruList as $idx => $g):
                                    $namaLengkapGelar = trim(($g['gelar_depan'] ? $g['gelar_depan'] . ' ' : '') . $g['nama_lengkap'] . ($g['gelar_belakang'] ? ', ' . $g['gelar_belakang'] : ''));
                                    $fotoImg = !empty($g['foto']) ? BASE_URL . '/' . $g['foto'] : null;
                                    ?>
                                    <tr class="guru-row">
                                        <td class="ps-3 text-center">
                                            <?php if ($g['role'] === 'guru'): ?>
                                                <input type="checkbox" name="ids[]" value="<?= $g['id'] ?>"
                                                    class="bulk-checkbox bulk-item-check"
                                                    data-name="<?= htmlspecialchars($g['nama_lengkap']) ?>">
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold text-muted font-monospace"><?= $idx + 1 ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <?php if ($fotoImg): ?>
                                                    <img src="<?= $fotoImg ?>" alt="Foto"
                                                        class="rounded-circle object-fit-cover shadow-sm border"
                                                        style="width: 44px; height: 44px;">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-teal-subtle text-teal fw-bold d-flex align-items-center justify-content-center border"
                                                        style="width: 44px; height: 44px; background-color: #ccfbf1; color: #0f766e;">
                                                        <i class="bi bi-person-fill fs-5"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="fw-bold text-dark guru-nama fs-7">
                                                        <?= htmlspecialchars($namaLengkapGelar) ?>
                                                    </div>
                                                    <small class="text-muted">Username: <span
                                                            class="font-monospace text-primary fw-semibold"><?= htmlspecialchars($g['username']) ?></span>
                                                        <?php if ($g['role'] !== 'guru'): ?>
                                                            <span class="badge bg-info bg-opacity-10 text-info border border-info-subtle rounded-pill ms-1 text-capitalize"><?= htmlspecialchars($g['role']) ?></span>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark fs-7"><?= htmlspecialchars($g['nip'] ?: '—') ?>
                                            </div>
                                            <small
                                                class="badge bg-secondary bg-opacity-10 text-dark border-0 mb-1"><?= htmlspecialchars($g['jabatan_sekolah'] ?: 'Guru Mapel') ?></small>
                                            <?php if (!empty($g['kelas_wali'])): ?>
                                                <small
                                                    class="badge bg-primary-subtle text-primary border border-primary-subtle d-block text-truncate"
                                                    style="font-size: 0.68rem;">
                                                    <i class="bi bi-person-badge-fill me-1"></i>Wali Kelas
                                                    <?= htmlspecialchars($g['kelas_wali']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark fs-7">
                                                <?= htmlspecialchars($g['pendidikan_jenjang'] ?: 'S1') ?>
                                                <?= htmlspecialchars($g['pendidikan_jurusan'] ? '(' . $g['pendidikan_jurusan'] . ')' : '') ?>
                                            </div>
                                            <small class="text-primary fw-semibold d-block"><i
                                                    class="bi bi-award me-1"></i><?= htmlspecialchars($g['spesialisasi'] ?: 'Spesialisasi Umum') ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($g['no_hp'])): ?>
                                                <a href="https://wa.me/<?= preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $g['no_hp'])) ?>"
                                                    target="_blank"
                                                    class="btn btn-sm btn-outline-success rounded-pill px-2.5 py-0.5 font-monospace fs-7">
                                                    <i class="bi bi-whatsapp me-1"></i><?= htmlspecialchars($g['no_hp']) ?>
                                                </a>
                                            <?php else: ?>
                                                <small class="text-muted">—</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ((int) $g['is_active'] === 1): ?>
                                                <span
                                                    class="badge bg-success bg-opacity-10 text-success border border-success-subtle rounded-pill px-3 py-1">Aktif</span>
                                            <?php else: ?>
                                                <span
                                                    class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle rounded-pill px-3 py-1">Non-Aktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-info" title="Lihat Profil Detail"
                                                    onclick='showModalDetail(<?= htmlspecialchars(json_encode($g, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($guruAmpu[$g['id']] ?? [], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>)'>
                                                    <i class="bi bi-eye-fill"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-primary" title="Edit Guru"
                                                    onclick='editGuru(<?= htmlspecialchars(json_encode($g, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>)'>
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <?php if ($g['role'] === 'guru' && (int) $g['id'] !== (int) ($_SESSION['user_id'] ?? 0)): ?>
                                                    <a href="delete.php?id=<?= $g['id'] ?>" class="btn btn-outline-<?= ($g['is_active'] ?? 1) ? 'warning' : 'success' ?>" title="<?= ($g['is_active'] ?? 1) ? 'Nonaktifkan Guru' : 'Aktifkan Kembali Guru' ?>" onclick="return confirm('Apakah Anda yakin ingin <?= ($g['is_active'] ?? 1) ? 'menonaktifkan' : 'mengaktifkan kembali' ?> akun guru <?= htmlspecialchars(addslashes($namaLengkapGelar), ENT_QUOTES) ?>?')">
                                                        <i class="bi <?= ($g['is_active'] ?? 1) ? 'bi-person-slash' : 'bi-person-check-fill' ?>"></i>
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

                <!-- FLOATING ACTION TOOLBAR -->
                <div class="bulk-floating-bar" id="bulkFloatingBar">
                    <div class="d-flex align-items-center gap-2">
                        <span class="bulk-count-badge" id="bulkCountBadge">
                            <i class="bi bi-check2-circle"></i> <span>0 guru dipilih</span>
                        </span>
                    </div>

                    <div class="bulk-actions-group">
                        <button type="button" class="btn-bulk-action btn-bulk-secondary"
                            onclick="handleBulkStatusGuru('activate')">
                            <i class="bi bi-check-circle-fill text-success"></i> Aktifkan
                        </button>
                        <button type="button" class="btn-bulk-action btn-bulk-secondary"
                            onclick="handleBulkStatusGuru('deactivate')">
                            <i class="bi bi-dash-circle-fill text-warning"></i> Nonaktifkan
                        </button>
                        <button type="button" class="btn-bulk-cancel" title="Batalkan Pilihan">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= MODAL TAMBAH GURU ================= -->
    <div class="modal fade" id="modalTambahGuru" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-gradient bg-emerald text-white p-3.5 rounded-top-4"
                    style="background: linear-gradient(135deg, #0d9488, #0f766e);">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i> Tambah Data Detail Guru
                        Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="index.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body p-4 fs-7">

                        <?php if (!empty($existingStaffUsers)): ?>
                        <div class="alert alert-info border-info-subtle rounded-3 p-3 mb-3">
                            <div class="fw-bold text-dark mb-2 small">
                                <i class="bi bi-person-check-fill text-primary me-1"></i> Jenis Penambahan Data Guru
                            </div>
                            <div class="d-flex flex-wrap gap-3 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="add_mode" id="addModeNew" value="new" checked onchange="toggleAddGuruMode()">
                                    <label class="form-check-label fw-semibold" for="addModeNew">
                                        Buat Akun Guru Baru
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="add_mode" id="addModeExisting" value="existing" onchange="toggleAddGuruMode()">
                                    <label class="form-check-label fw-semibold" for="addModeExisting">
                                        Pilih dari Akun yang Sudah Ada (Admin / Staff)
                                    </label>
                                </div>
                            </div>
                            <div id="existingUserPickerBox" class="mt-2" style="display: none;">
                                <label class="form-label fw-bold text-dark small mb-1">Pilih Akun Terdaftar <span class="text-danger">*</span></label>
                                <select name="existing_user_id" id="selectExistingUser" class="form-select border-2" onchange="onSelectExistingUser(this)">
                                    <option value="">-- Pilih Akun Admin / Staff --</option>
                                    <?php foreach ($existingStaffUsers as $eu): ?>
                                        <option value="<?= (int) $eu['id'] ?>"
                                                data-nama="<?= htmlspecialchars($eu['nama_lengkap']) ?>"
                                                data-username="<?= htmlspecialchars($eu['username']) ?>"
                                                data-role="<?= htmlspecialchars($eu['role']) ?>">
                                            <?= htmlspecialchars($eu['nama_lengkap']) ?> — <?= strtoupper(htmlspecialchars($eu['role'])) ?> (@<?= htmlspecialchars($eu['username']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted d-block mt-1">
                                    Hak akses akun (sebagai Admin/Staff) tetap terjaga, hanya ditambahkan profil kepegawaian &amp; NIP guru.
                                </small>
                            </div>
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="add_mode" value="new">
                        <?php endif; ?>

                        <h6 class="fw-bold text-primary mb-3 border-bottom pb-2"><i
                                class="bi bi-person-badge-fill me-1"></i> Identitas & Akun Login</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark">Gelar Depan</label>
                                <input type="text" name="gelar_depan" class="form-control border-2"
                                    placeholder="Drs. / Ustadz">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Nama Lengkap <span
                                        class="text-danger">*</span></label>
                                <input type="text" name="nama_lengkap" id="addNamaLengkap" class="form-control border-2"
                                    placeholder="Ahmad Syauqi" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark">Gelar Belakang</label>
                                <input type="text" name="gelar_belakang" class="form-control border-2"
                                    placeholder="M.Pd / S.Pd.I">
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">NIP / NUPTK</label>
                                <input type="text" name="nip" class="form-control border-2"
                                    placeholder="Nomor Induk Pegawai">
                            </div>
                            <div class="col-md-4 new-account-field">
                                <label class="form-label fw-bold text-dark">Username Akun <span
                                        class="text-danger">*</span></label>
                                <input type="text" name="username" id="addUsernameInput" class="form-control border-2"
                                    placeholder="guru_ahmad" required>
                            </div>
                            <div class="col-md-4 new-account-field">
                                <label class="form-label fw-bold text-dark">Password <span
                                        class="text-danger">*</span></label>
                                <input type="password" name="password" id="addPasswordInput" class="form-control border-2" required>
                            </div>
                            <div class="col-md-8 existing-account-note" style="display: none;">
                                <label class="form-label fw-bold text-dark">Akun Login Terhubung</label>
                                <div class="form-control bg-light border-2 text-muted" id="existingAccountSummary">
                                    Pilih akun terdaftar di atas. Username &amp; password mengikuti akun yang sudah ada.
                                </div>
                            </div>
                        </div>

                        <h6 class="fw-bold text-primary mb-3 border-bottom pb-2 mt-4"><i
                                class="bi bi-card-checklist me-1"></i> Biodata, Kepegawaian & Jabatan</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Tempat Lahir</label>
                                <input type="text" name="tempat_lahir" class="form-control border-2"
                                    placeholder="Surabaya">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Tanggal Lahir</label>
                                <input type="date" name="tanggal_lahir" class="form-control border-2">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Jenis Kelamin</label>
                                <select name="jenis_kelamin" class="form-select border-2">
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark">No. WhatsApp / HP</label>
                                <input type="text" name="no_hp" class="form-control border-2"
                                    placeholder="081234567890">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark">Status Kepegawaian</label>
                                <select name="status_kepegawaian" class="form-select border-2">
                                    <option value="GTY (Guru Tetap Yayasan)">GTY (Guru Tetap Yayasan)</option>
                                    <option value="GTT (Guru Honorer)">GTT (Guru Honorer)</option>
                                    <option value="Guru PNS DPK">Guru PNS DPK</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark">Jabatan di Sekolah</label>
                                <input type="text" name="jabatan_sekolah" class="form-control border-2"
                                    value="Guru Mata Pelajaran" placeholder="Kepala Sekolah / Guru Mapel / Wali Kelas">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark">Wali Kelas <small
                                        class="text-muted">(Opsional)</small></label>
                                <select name="wali_kelas_id" class="form-select border-2">
                                    <option value="">-- Bukan Wali Kelas --</option>
                                    <?php foreach ($allKelas as $k): ?>
                                        <option value="<?= $k['id'] ?>">Wali Kelas <?= htmlspecialchars($k['nama_kelas']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Alamat Domisili Lengkap</label>
                            <textarea name="alamat" class="form-control border-2" rows="2"
                                placeholder="Jl. Raya Utama No. 12, Kel. Sukamaju..."></textarea>
                        </div>

                        <h6 class="fw-bold text-primary mb-3 border-bottom pb-2 mt-4"><i
                                class="bi bi-mortarboard-fill me-1"></i> Pendidikan & Spesialisasi</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark">Jenjang Pendidikan</label>
                                <select name="pendidikan_jenjang" class="form-select border-2">
                                    <option value="S1">S1 (Sarjana)</option>
                                    <option value="S2">S2 (Magister)</option>
                                    <option value="S3">S3 (Doktor)</option>
                                    <option value="D3/D4">D3 / D4</option>
                                    <option value="SMA/MA">SMA / MA</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Jurusan / Program Studi</label>
                                <input type="text" name="pendidikan_jurusan" class="form-control border-2"
                                    placeholder="Pendidikan Agama Islam">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold text-dark">Perguruan Tinggi / Universitas</label>
                                <input type="text" name="pendidikan_kampus" class="form-control border-2"
                                    placeholder="Universitas Negeri Jakarta">
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Spesialisasi / Bidang Studi</label>
                                <input type="text" name="spesialisasi" class="form-control border-2"
                                    placeholder="Fiqh, Bahasa Arab, Hadits">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Foto Profil Guru</label>
                                <input type="file" name="foto" class="form-control border-2" accept="image/*">
                            </div>
                        </div>

                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="is_active" id="addIsActive" value="1"
                                checked>
                            <label class="form-check-label fw-bold text-dark" for="addIsActive">Status Akun Aktif (Dapat
                                Login)</label>
                        </div>

                    </div>
                    <div class="modal-footer bg-light p-3">
                        <button type="button" class="btn btn-secondary fw-bold px-3"
                            data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary-custom fw-bold px-4">Simpan Data Guru</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ================= MODAL EDIT GURU ================= -->
    <div class="modal fade" id="modalEditGuru" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-gradient bg-primary text-white p-3.5 rounded-top-4">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i> Edit Data Detail Guru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="index.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="editId">

                    <div class="modal-body p-4 fs-7">
                        <h6 class="fw-bold text-primary mb-3 border-bottom pb-2"><i
                                class="bi bi-person-badge-fill me-1"></i> Identitas & Akun Login</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark">Gelar Depan</label>
                                <input type="text" name="gelar_depan" id="editGelarDepan" class="form-control border-2">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Nama Lengkap <span
                                        class="text-danger">*</span></label>
                                <input type="text" name="nama_lengkap" id="editNamaLengkap"
                                    class="form-control border-2" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark">Gelar Belakang</label>
                                <input type="text" name="gelar_belakang" id="editGelarBelakang"
                                    class="form-control border-2">
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">NIP / NUPTK</label>
                                <input type="text" name="nip" id="editNip" class="form-control border-2">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Username Akun <span
                                        class="text-danger">*</span></label>
                                <input type="text" name="username" id="editUsername" class="form-control border-2"
                                    required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Password Baru (Opsional)</label>
                                <input type="password" name="password" class="form-control border-2"
                                    placeholder="Kosongkan jika tidak diganti">
                            </div>
                        </div>

                        <h6 class="fw-bold text-primary mb-3 border-bottom pb-2 mt-4"><i
                                class="bi bi-card-checklist me-1"></i> Biodata, Kepegawaian & Jabatan</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Tempat Lahir</label>
                                <input type="text" name="tempat_lahir" id="editTempatLahir"
                                    class="form-control border-2">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Tanggal Lahir</label>
                                <input type="date" name="tanggal_lahir" id="editTanggalLahir"
                                    class="form-control border-2">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Jenis Kelamin</label>
                                <select name="jenis_kelamin" id="editJenisKelamin" class="form-select border-2">
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark">No. WhatsApp / HP</label>
                                <input type="text" name="no_hp" id="editNoHp" class="form-control border-2">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark">Status Kepegawaian</label>
                                <select name="status_kepegawaian" id="editStatusKepegawaian"
                                    class="form-select border-2">
                                    <option value="GTY (Guru Tetap Yayasan)">GTY (Guru Tetap Yayasan)</option>
                                    <option value="GTT (Guru Honorer)">GTT (Guru Honorer)</option>
                                    <option value="Guru PNS DPK">Guru PNS DPK</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark">Jabatan di Sekolah</label>
                                <input type="text" name="jabatan_sekolah" id="editJabatanSekolah"
                                    class="form-control border-2">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark">Wali Kelas <small
                                        class="text-muted">(Opsional)</small></label>
                                <select name="wali_kelas_id" id="editWaliKelasId" class="form-select border-2">
                                    <option value="">-- Bukan Wali Kelas / Kosongkan --</option>
                                    <?php foreach ($allKelas as $k): ?>
                                        <option value="<?= $k['id'] ?>">Wali Kelas <?= htmlspecialchars($k['nama_kelas']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Alamat Domisili Lengkap</label>
                            <textarea name="alamat" id="editAlamat" class="form-control border-2" rows="2"></textarea>
                        </div>

                        <h6 class="fw-bold text-primary mb-3 border-bottom pb-2 mt-4"><i
                                class="bi bi-mortarboard-fill me-1"></i> Pendidikan & Spesialisasi</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark">Jenjang Pendidikan</label>
                                <select name="pendidikan_jenjang" id="editPendidikanJenjang"
                                    class="form-select border-2">
                                    <option value="S1">S1 (Sarjana)</option>
                                    <option value="S2">S2 (Magister)</option>
                                    <option value="S3">S3 (Doktor)</option>
                                    <option value="D3/D4">D3 / D4</option>
                                    <option value="SMA/MA">SMA / MA</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Jurusan / Program Studi</label>
                                <input type="text" name="pendidikan_jurusan" id="editPendidikanJurusan"
                                    class="form-control border-2">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold text-dark">Perguruan Tinggi / Universitas</label>
                                <input type="text" name="pendidikan_kampus" id="editPendidikanKampus"
                                    class="form-control border-2">
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Spesialisasi / Bidang Studi</label>
                                <input type="text" name="spesialisasi" id="editSpesialisasi"
                                    class="form-control border-2">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Ganti Foto Profil (Opsional)</label>
                                <input type="file" name="foto" class="form-control border-2" accept="image/*">
                            </div>
                        </div>

                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive"
                                value="1">
                            <label class="form-check-label fw-bold text-dark" for="editIsActive">Status Akun Aktif
                                (Dapat Login)</label>
                        </div>

                    </div>
                    <div class="modal-footer bg-light p-3">
                        <button type="button" class="btn btn-secondary fw-bold px-3"
                            data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary-custom fw-bold px-4">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ================= MODAL DETAIL PROFIL GURU (EXECUTIVE VIEW) ================= -->
    <div class="modal fade" id="modalDetailGuru" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="p-4 text-white text-center position-relative"
                    style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3"
                        data-bs-dismiss="modal"></button>
                    <div id="detailFotoBox" class="mb-2"></div>
                    <h4 class="fw-extrabold text-white mb-0" id="detailNamaLengkap"></h4>
                    <div class="badge bg-teal mt-1 px-3 py-1 rounded-pill" id="detailJabatan"></div>
                </div>
                <div class="modal-body p-4 fs-7 bg-light">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 bg-white rounded-3 shadow-xs border h-100">
                                <h6 class="fw-bold text-primary mb-2 border-bottom pb-1"><i
                                        class="bi bi-person-vcard me-1"></i> Data Kepegawaian & Kontak</h6>
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <td class="text-muted" style="width: 40%;">NIP / NUPTK</td>
                                        <td class="fw-bold text-dark" id="detailNip"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Status Pegawai</td>
                                        <td class="fw-bold text-dark" id="detailStatusKpeg"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Wali Kelas</td>
                                        <td class="fw-bold text-dark" id="detailWaliKelas"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">TTL</td>
                                        <td class="fw-bold text-dark" id="detailTtl"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Jenis Kelamin</td>
                                        <td class="fw-bold text-dark" id="detailJk"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">No. WhatsApp</td>
                                        <td class="fw-bold text-dark" id="detailNoHp"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Alamat</td>
                                        <td class="fw-bold text-dark" id="detailAlamat"></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 bg-white rounded-3 shadow-xs border h-100">
                                <h6 class="fw-bold text-primary mb-2 border-bottom pb-1"><i
                                        class="bi bi-mortarboard me-1"></i> Pendidikan & Spesialisasi</h6>
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <td class="text-muted" style="width: 40%;">Jenjang</td>
                                        <td class="fw-bold text-dark" id="detailJenjang"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Jurusan</td>
                                        <td class="fw-bold text-dark" id="detailJurusan"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Kampus</td>
                                        <td class="fw-bold text-dark" id="detailKampus"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Spesialisasi</td>
                                        <td class="fw-bold text-primary" id="detailSpesialisasi"></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- DAFTAR MAPEL & KELAS DIAMPU -->
                    <div class="p-3 bg-white rounded-3 shadow-xs border mt-3">
                        <h6 class="fw-bold text-primary mb-2 border-bottom pb-1"><i class="bi bi-book-half me-1"></i>
                            Daftar Mata Pelajaran & Kelas Diampu</h6>
                        <div id="detailMapelAmpu"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleAddGuruMode() {
            const existingRadio = document.getElementById('addModeExisting');
            const isExisting = existingRadio && existingRadio.checked;

            const pickerBox = document.getElementById('existingUserPickerBox');
            const pickerSelect = document.getElementById('selectExistingUser');
            const usernameInput = document.getElementById('addUsernameInput');
            const passwordInput = document.getElementById('addPasswordInput');

            if (pickerBox) pickerBox.style.display = isExisting ? 'block' : 'none';
            if (pickerSelect) pickerSelect.required = isExisting;

            document.querySelectorAll('#modalTambahGuru .new-account-field').forEach(el => {
                el.style.display = isExisting ? 'none' : '';
            });
            document.querySelectorAll('#modalTambahGuru .existing-account-note').forEach(el => {
                el.style.display = isExisting ? '' : 'none';
            });

            if (usernameInput) usernameInput.required = !isExisting;
            if (passwordInput) passwordInput.required = !isExisting;
        }

        function onSelectExistingUser(sel) {
            const opt = sel.options[sel.selectedIndex];
            const namaInput = document.getElementById('addNamaLengkap');
            const summary = document.getElementById('existingAccountSummary');
            if (opt && opt.value) {
                if (namaInput && opt.dataset.nama) {
                    namaInput.value = opt.dataset.nama;
                }
                if (summary) {
                    summary.innerHTML = 'Terhubung ke akun <strong>@' + (opt.dataset.username || '') + '</strong> (Role: ' + (opt.dataset.role || '').toUpperCase() + ')';
                }
            } else if (summary) {
                summary.textContent = 'Pilih akun terdaftar di atas. Username & password mengikuti akun yang sudah ada.';
            }
        }

        // Live Search
        document.getElementById('searchGuru').addEventListener('keyup', function () {
            const val = this.value.toLowerCase();
            document.querySelectorAll('.guru-row').forEach(tr => {
                const text = tr.innerText.toLowerCase();
                tr.style.display = text.includes(val) ? '' : 'none';
            });
        });

        function editGuru(g) {
            document.getElementById('editId').value = g.id;
            document.getElementById('editNamaLengkap').value = g.nama_lengkap || '';
            document.getElementById('editGelarDepan').value = g.gelar_depan || '';
            document.getElementById('editGelarBelakang').value = g.gelar_belakang || '';
            document.getElementById('editNip').value = g.nip || '';
            document.getElementById('editUsername').value = g.username || '';
            document.getElementById('editTempatLahir').value = g.tempat_lahir || '';
            document.getElementById('editTanggalLahir').value = g.tanggal_lahir || '';
            document.getElementById('editJenisKelamin').value = g.jenis_kelamin || 'L';
            document.getElementById('editNoHp').value = g.no_hp || '';
            document.getElementById('editStatusKepegawaian').value = g.status_kepegawaian || 'GTY (Guru Tetap Yayasan)';
            document.getElementById('editJabatanSekolah').value = g.jabatan_sekolah || 'Guru Mata Pelajaran';
            document.getElementById('editWaliKelasId').value = g.kelas_wali_id || '';
            document.getElementById('editAlamat').value = g.alamat || '';
            document.getElementById('editPendidikanJenjang').value = g.pendidikan_jenjang || 'S1';
            document.getElementById('editPendidikanJurusan').value = g.pendidikan_jurusan || '';
            document.getElementById('editPendidikanKampus').value = g.pendidikan_kampus || '';
            document.getElementById('editSpesialisasi').value = g.spesialisasi || '';
            document.getElementById('editIsActive').checked = (parseInt(g.is_active) === 1);

            new bootstrap.Modal(document.getElementById('modalEditGuru')).show();
        }

        function showModalDetail(g, ampuData) {
            var namaGelar = (g.gelar_depan ? g.gelar_depan + ' ' : '') + g.nama_lengkap + (g.gelar_belakang ? ', ' + g.gelar_belakang : '');
            document.getElementById('detailNamaLengkap').innerText = namaGelar;
            document.getElementById('detailJabatan').innerText = g.jabatan_sekolah || 'Guru Mata Pelajaran';
            document.getElementById('detailNip').innerText = g.nip || '—';
            document.getElementById('detailStatusKpeg').innerText = g.status_kepegawaian || '—';

            if (g.kelas_wali) {
                document.getElementById('detailWaliKelas').innerHTML = '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle px-2.5 py-1 rounded-pill"><i class="bi bi-person-badge-fill me-1"></i>Wali Kelas ' + g.kelas_wali + '</span>';
            } else {
                document.getElementById('detailWaliKelas').innerHTML = '<span class="text-muted">— (Bukan Wali Kelas)</span>';
            }

            document.getElementById('detailTtl').innerText = (g.tempat_lahir || '—') + (g.tanggal_lahir ? ', ' + g.tanggal_lahir : '');
            document.getElementById('detailJk').innerText = (g.jenis_kelamin === 'P' ? 'Perempuan' : 'Laki-laki');

            if (g.no_hp) {
                var cleanHp = g.no_hp.replace(/^0/, '62').replace(/[^0-9]/g, '');
                document.getElementById('detailNoHp').innerHTML = '<a href="https://wa.me/' + cleanHp + '" target="_blank" class="text-success text-decoration-none fw-bold"><i class="bi bi-whatsapp me-1"></i>' + g.no_hp + '</a>';
            } else {
                document.getElementById('detailNoHp').innerText = '—';
            }

            document.getElementById('detailAlamat').innerText = g.alamat || '—';
            document.getElementById('detailJenjang').innerText = g.pendidikan_jenjang || 'S1';
            document.getElementById('detailJurusan').innerText = g.pendidikan_jurusan || '—';
            document.getElementById('detailKampus').innerText = g.pendidikan_kampus || '—';
            document.getElementById('detailSpesialisasi').innerText = g.spesialisasi || '—';

            // Foto Box
            var imgHtml = '';
            if (g.foto) {
                imgHtml = '<img src="<?= BASE_URL ?>/' + g.foto + '" class="rounded-circle object-fit-cover shadow border border-3 border-white" style="width: 90px; height: 90px;">';
            } else {
                imgHtml = '<div class="rounded-circle bg-teal text-white fw-bold d-inline-flex align-items-center justify-content-center shadow border border-3 border-white" style="width: 90px; height: 90px; font-size: 2.5rem;"><i class="bi bi-person-fill"></i></div>';
            }
            document.getElementById('detailFotoBox').innerHTML = imgHtml;

            // Mapel Ampu HTML
            var mapelHtml = '';
            if (ampuData && Object.keys(ampuData).length > 0) {
                mapelHtml = '<div class="d-flex flex-wrap gap-2">';
                for (var mapelName in ampuData) {
                    var kelasList = ampuData[mapelName].join(', ');
                    mapelHtml += '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle p-2 text-start">' +
                        '<strong class="d-block">' + mapelName + '</strong>' +
                        '<small class="text-muted">Kelas: ' + kelasList + '</small>' +
                        '</span>';
                }
                mapelHtml += '</div>';
            } else {
                mapelHtml = '<small class="text-muted">Belum ada plotting mata pelajaran yang diampu.</small>';
            }
            document.getElementById('detailMapelAmpu').innerHTML = mapelHtml;

            new bootstrap.Modal(document.getElementById('modalDetailGuru')).show();
        }

        document.addEventListener('DOMContentLoaded', function () {
            initBulkTable({
                tableId: 'tableGuru',
                floatingBarId: 'bulkFloatingBar',
                countBadgeId: 'bulkCountBadge',
                masterCheckId: 'selectAll',
                itemCheckClass: 'bulk-item-check',
                formId: 'bulkActionForm'
            });
        });

        function handleBulkStatusGuru(type) {
            const selected = window.getSelectedBulkIds();
            if (selected.length === 0) return;
            const label = type === 'activate' ? 'MENGAKTIFKAN' : 'MENONAKTIFKAN';
            if (confirm(`Apakah Anda yakin ingin ${label} ${selected.length} guru yang dipilih?`)) {
                document.getElementById('bulkActionType').value = type;
                document.getElementById('bulkActionForm').submit();
            }
        }
    </script>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>