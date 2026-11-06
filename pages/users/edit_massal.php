<?php
/**
 * EDIT USER MASSAL - Spreadsheet-style Dynamic Batch Editor for Usernames & Passwords
 */
$pageTitle = 'Edit User & Password Massal';
$activePage = 'users';
$activeModule = 'pengaturan';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('users');

$pdo = getConnection();

// Auto-check & auto-migrate tabel dan kolom jika belum ada
require_once __DIR__ . '/../../config/db_auto_sync.php';
runDatabaseAutoSync($pdo);

// Filter
$filterRole = $_GET['role'] ?? 'ortu';
$filterKelas = (int) ($_GET['kelas_id'] ?? 0);
// PROSES SIMPAN TEMPLATE PESAN WHATSAPP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_wa_template') {
    if (($_SESSION['role'] ?? '') !== 'admin' && ($_SESSION['role'] ?? '') !== 'bendahara') {
        redirect('edit_massal.php?role=' . $filterRole . '&kelas_id=' . $filterKelas, 'danger', 'Hanya administrator yang memiliki izin.');
    }
    $tpl = trim($_POST['user_pesan_wa'] ?? '');
    updateSetting('user_pesan_wa', $tpl);
    redirect('edit_massal.php?role=' . $filterRole . '&kelas_id=' . $filterKelas, 'success', 'Template pesan WhatsApp berhasil disimpan.');
}

$defaultWaTemplate = "Assalamu'alaikum Wr. Wb.\n\nYth. Orang Tua / Wali dari *{nama_siswa}* (Kelas {nama_kelas})\n\nBerikut kami sampaikan informasi akun login untuk mengakses *{app_name} ({nama_sekolah})*:\n\n🌐 *Link Login Portal*: {link_login}\n👤 *Username*: *{username}*\n🔑 *Password*: *{password}*\n\nAkun ini dapat digunakan untuk mengakses Portal Orang Tua (informasi kehadiran, nilai rapor santri, dan rincian administrasi sekolah).\n\nMohon simpan informasi login ini dengan baik. Apabila ada pertanyaan, silakan hubungi pihak sekolah.\n\nTerima kasih.\nWassalamu'alaikum Wr. Wb.\n_{nama_sekolah}_";

$userWaTemplate = getSetting('user_pesan_wa', $defaultWaTemplate);

// PROSES SIMPAN PERUBAHAN MASSAL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_mass_edit') {
    if (($_SESSION['role'] ?? '') !== 'admin') {
        redirect('edit_massal.php?role=' . $filterRole . '&kelas_id=' . $filterKelas, 'danger', 'Hanya administrator yang memiliki izin mengedit massal.');
    }

    $submittedUsers = $_POST['users'] ?? [];
    if (empty($submittedUsers)) {
        redirect('edit_massal.php?role=' . $filterRole . '&kelas_id=' . $filterKelas, 'warning', 'Tidak ada data user yang dikirim.');
    }

    $updatedCount = 0;
    $errors = [];

    try {
        $pdo->beginTransaction();

        $stmtUpdate = $pdo->prepare("
            UPDATE users 
            SET username = :username,
                nama_lengkap = :nama,
                is_active = :is_active
                " . (!empty($updatePassSql) ? ", password = :password" : "") . "
            WHERE id = :id
        ");

        $stmtCheckUname = $pdo->prepare("SELECT id FROM users WHERE username = :u AND id != :id");

        foreach ($submittedUsers as $userId => $data) {
            $userId = (int) $userId;
            $newUname = trim($data['username'] ?? '');
            $newNama = trim($data['nama_lengkap'] ?? '');
            $newPass = trim($data['password'] ?? '');
            $isActive = isset($data['is_active']) ? 1 : 0;

            if (empty($newUname) || empty($newNama)) {
                $errors[] = "User ID #$userId: Username dan Nama tidak boleh kosong.";
                continue;
            }

            // Cek duplikasi username
            $stmtCheckUname->execute([':u' => $newUname, ':id' => $userId]);
            if ($stmtCheckUname->fetch()) {
                $errors[] = "Username <b>'$newUname'</b> sudah dipakai oleh user lain.";
                continue;
            }

            // Update user
            if (!empty($newPass)) {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET username = :u, 
                        nama_lengkap = :n, 
                        password = :p, 
                        is_active = :act 
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':u' => $newUname,
                    ':n' => $newNama,
                    ':p' => $hash,
                    ':act' => $isActive,
                    ':id' => $userId
                ]);

                if ($userId === (int) ($_SESSION['user_id'] ?? 0)) {
                    $_SESSION['auth_password_hash'] = $hash;
                }
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET username = :u, 
                        nama_lengkap = :n, 
                        is_active = :act 
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':u' => $newUname,
                    ':n' => $newNama,
                    ':act' => $isActive,
                    ':id' => $userId
                ]);
            }

            $updatedCount++;
        }

        $pdo->commit();

        $msg = "🎉 Berhasil menyimpan pembaruan untuk <b>$updatedCount</b> pengguna.";
        if (!empty($errors)) {
            $msg .= "<br><small>" . implode("<br>", array_slice($errors, 0, 5)) . (count($errors) > 5 ? "... dan " . (count($errors) - 5) . " lainnya." : "") . "</small>";
        }

        redirect('edit_massal.php?role=' . $filterRole . '&kelas_id=' . $filterKelas, empty($errors) ? 'success' : 'warning', $msg);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirect('edit_massal.php?role=' . $filterRole . '&kelas_id=' . $filterKelas, 'danger', 'Gagal menyimpan perubahan: ' . $e->getMessage());
    }
}

// Ambil list kelas untuk filter
$classList = $pdo->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC")->fetchAll();

// Hitung total per role
$roleCounts = [
    'all' => (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'admin' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
    'bendahara' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'bendahara'")->fetchColumn(),
    'guru' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'guru'")->fetchColumn(),
    'kantin' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'kantin'")->fetchColumn(),
    'ortu' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'ortu'")->fetchColumn(),
];

// Query data user
$sql = "
    SELECT 
        u.id, 
        u.username, 
        u.nama_lengkap, 
        u.role, 
        u.siswa_id, 
        COALESCE(u.is_active, 1) AS is_active,
        s.nis, 
        s.nama AS nama_siswa, 
        s.no_hp_ortu, 
        k.nama_kelas,
        k.id AS kelas_id
    FROM users u
    LEFT JOIN siswa s ON u.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    WHERE 1=1
";
$params = [];

if ($filterRole !== 'all') {
    $sql .= " AND u.role = :role";
    $params[':role'] = $filterRole;
}

if ($filterRole === 'ortu' && $filterKelas > 0) {
    $sql .= " AND s.kelas_id = :kelas_id";
    $params[':kelas_id'] = $filterKelas;
}

if (!empty($search)) {
    $sql .= " AND (u.username LIKE :q OR u.nama_lengkap LIKE :q OR s.nama LIKE :q OR s.nis LIKE :q OR s.no_hp_ortu LIKE :q)";
    $params[':q'] = "%$search%";
}

$sql .= " ORDER BY COALESCE(u.is_active, 1) DESC, u.role ASC, k.nama_kelas ASC, u.nama_lengkap ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$userList = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .mass-edit-table thead th {
        background: #f8fafc;
        color: #475569;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 12px 14px;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
    }

    .mass-edit-table tbody td {
        padding: 10px 14px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }

    .mass-edit-table tbody tr:hover {
        background-color: #f8fafc;
    }

    .modern-filter-nav {
        position: relative;
        z-index: 5;
    }

    .modern-filter-btn {
        cursor: pointer !important;
        user-select: none;
        -webkit-tap-highlight-color: transparent;
        position: relative;
        z-index: 6;
    }

    .modern-filter-badge {
        pointer-events: none !important;
    }

    .quick-action-bar {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 16px 20px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -2px rgba(0, 0, 0, 0.02);
    }

    .cell-input {
        border-radius: 8px;
        border: 1.5px solid #cbd5e1;
        font-size: 0.88rem;
        padding: 7px 12px;
        transition: all 0.2s ease;
    }

    .cell-input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        background: #fff;
    }

    .cell-input.modified {
        border-color: #f59e0b;
        background-color: #fffbeb;
    }

    .btn-quick-chip {
        font-size: 0.78rem;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #334155;
        transition: all 0.15s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-quick-chip:hover {
        background: #f1f5f9;
        border-color: #94a3b8;
        color: #0f172a;
        transform: translateY(-1px);
    }

    .btn-quick-chip.primary {
        background: #eff6ff;
        border-color: #bfdbfe;
        color: #1d4ed8;
    }

    .btn-quick-chip.primary:hover {
        background: #dbeafe;
        border-color: #93c5fd;
        color: #1e40af;
    }

    .btn-quick-chip.success {
        background: #f0fdf4;
        border-color: #bbf7d0;
        color: #15803d;
    }

    .btn-quick-chip.success:hover {
        background: #dcfce7;
        border-color: #86efac;
        color: #166534;
    }

    .sticky-save-bar {
        position: sticky;
        bottom: 20px;
        z-index: 100;
        background: rgba(15, 23, 42, 0.92);
        backdrop-filter: blur(12px);
        border-radius: 16px;
        padding: 12px 24px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
        color: #ffffff;
    }
</style>

<!-- HEADER SECTION -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="index.php?role=<?= htmlspecialchars($filterRole) ?>"
                class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                <i class="bi bi-arrow-left"></i> Kembali ke Daftar User
            </a>
            <span
                class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-20 px-3 py-1.5 rounded-pill font-monospace">
                <i class="bi bi-table"></i> Mode Edit Massal Dinamis
            </span>
        </div>
        <h4 class="fw-bold text-dark mb-0">✏️ Edit Massal Username & Password</h4>
        <p class="text-muted small mb-0">Ubah username dan reset password puluhan pengguna sekaligus seperti spreadsheet
            secara instan.</p>
    </div>

    <div class="d-flex align-items-center gap-2">
        <a href="index.php?role=<?= htmlspecialchars($filterRole) ?>"
            class="btn btn-light border px-3 py-2 rounded-3 fw-semibold">
            Batal
        </a>
        <button type="button"
            class="btn btn-primary px-4 py-2 rounded-3 fw-bold shadow-sm d-flex align-items-center gap-2"
            onclick="document.getElementById('massEditForm').submit()">
            <i class="bi bi-save-fill"></i> Simpan Semua Perubahan
        </button>
    </div>
</div>

<!-- FILTER & SEARCH BAR -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <!-- Tabs Role -->
            <div class="modern-filter-nav">
                <a class="modern-filter-btn <?= $filterRole === 'ortu' ? 'active' : '' ?>"
                    href="edit_massal.php?role=ortu" onclick="window.location.href='edit_massal.php?role=ortu';">
                    Orang Tua (Ortu) <span class="modern-filter-badge"><?= $roleCounts['ortu'] ?></span>
                </a>
                <a class="modern-filter-btn <?= $filterRole === 'guru' ? 'active' : '' ?>"
                    href="edit_massal.php?role=guru" onclick="window.location.href='edit_massal.php?role=guru';">
                    Guru <span class="modern-filter-badge"><?= $roleCounts['guru'] ?></span>
                </a>
                <a class="modern-filter-btn <?= $filterRole === 'bendahara' ? 'active' : '' ?>"
                    href="edit_massal.php?role=bendahara"
                    onclick="window.location.href='edit_massal.php?role=bendahara';">
                    Bendahara <span class="modern-filter-badge"><?= $roleCounts['bendahara'] ?></span>
                </a>
                <a class="modern-filter-btn <?= $filterRole === 'kantin' ? 'active' : '' ?>"
                    href="edit_massal.php?role=kantin" onclick="window.location.href='edit_massal.php?role=kantin';">
                    Kantin <span class="modern-filter-badge"><?= $roleCounts['kantin'] ?></span>
                </a>
                <a class="modern-filter-btn <?= $filterRole === 'admin' ? 'active' : '' ?>"
                    href="edit_massal.php?role=admin" onclick="window.location.href='edit_massal.php?role=admin';">
                    Admin <span class="modern-filter-badge"><?= $roleCounts['admin'] ?></span>
                </a>
                <a class="modern-filter-btn <?= $filterRole === 'all' ? 'active' : '' ?>"
                    href="edit_massal.php?role=all" onclick="window.location.href='edit_massal.php?role=all';">
                    Semua <span class="modern-filter-badge"><?= $roleCounts['all'] ?></span>
                </a>
            </div>

            <!-- Filter Kelas & Search -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <?php if ($filterRole === 'ortu'): ?>
                    <div class="d-flex align-items-center gap-1.5">
                        <label class="small fw-bold text-muted mb-0" for="filterKelasSelect"><i class="bi bi-filter"></i>
                            Kelas:</label>
                        <select id="filterKelasSelect" class="form-select form-select-sm border-2"
                            style="width: auto; min-width: 140px;"
                            onchange="location.href='edit_massal.php?role=ortu&kelas_id=' + this.value">
                            <option value="0">Semua Kelas</option>
                            <?php foreach ($classList as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $filterKelas === (int) $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nama_kelas']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="input-group input-group-sm" style="max-width: 250px;">
                    <span class="input-group-text bg-white border-2 border-end-0"><i
                            class="bi bi-search text-muted"></i></span>
                    <input type="text" id="tableSearchInput" class="form-control border-2 border-start-0"
                        placeholder="Cari di tabel..." onkeyup="filterTableRows(this.value)">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QUICK ACTION TOOLBAR (MODULAR & RAPI) -->
<div class="quick-action-bar mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <!-- Grup Aksi Otomatis Massal -->
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="small fw-bold text-dark d-flex align-items-center gap-1.5 me-1">
                <i class="bi bi-lightning-charge-fill text-warning"></i> Aksi Cepat:
            </span>

            <span id="selectedBadge"
                class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-2.5 py-1 small fw-bold d-none">
                <i class="bi bi-check2-square"></i> <span id="selectedCountNum">0</span> akun dipilih
            </span>

            <?php if ($filterRole === 'ortu'): ?>
                <!-- Dropdown Username Ortu -->
                <div class="dropdown">
                    <button
                        class="btn btn-sm btn-white border fw-semibold dropdown-toggle d-flex align-items-center gap-1.5 rounded-3 shadow-2xs"
                        type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-badge text-primary"></i> Atur Username
                    </button>
                    <ul class="dropdown-menu shadow-sm border-0 rounded-3 p-2">
                        <li>
                            <h6 class="dropdown-header text-uppercase small fw-bold text-muted px-2 py-1">Pilihan Format
                            </h6>
                        </li>
                        <li>
                            <a class="dropdown-item rounded-2 py-2 d-flex align-items-center gap-2 text-success"
                                href="javascript:void(0)" onclick="applyAllUsernames('no_hp')">
                                <i class="bi bi-telephone-fill"></i> Gunakan No. HP Wali Murid
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item rounded-2 py-2 d-flex align-items-center gap-2 text-primary"
                                href="javascript:void(0)" onclick="applyAllUsernames('nis')">
                                <i class="bi bi-card-text"></i> Gunakan NIS Santri
                            </a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Dropdown Password Massal -->
            <div class="dropdown">
                <button
                    class="btn btn-sm btn-white border fw-semibold dropdown-toggle d-flex align-items-center gap-1.5 rounded-3 shadow-2xs"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-key-fill text-warning"></i> Atur Password
                </button>
                <ul class="dropdown-menu shadow-sm border-0 rounded-3 p-2">
                    <li>
                        <h6 class="dropdown-header text-uppercase small fw-bold text-muted px-2 py-1">Opsi Password Baru
                        </h6>
                    </li>
                    <li>
                        <a class="dropdown-item rounded-2 py-2 d-flex align-items-center gap-2 text-primary"
                            href="javascript:void(0)" onclick="applyAllPasswords('username')">
                            <i class="bi bi-arrow-repeat"></i> Samakan dengan Username Masing-masing
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item rounded-2 py-2 d-flex align-items-center gap-2 text-dark"
                            href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalCustomPass">
                            <i class="bi bi-shield-lock-fill text-danger"></i> Setel Password Seragam / Pilihan User...
                        </a>
                    </li>
                    <li>
                        <hr class="dropdown-divider my-1">
                    </li>
                    <li>
                        <a class="dropdown-item rounded-2 py-2 d-flex align-items-center gap-2 text-muted"
                            href="javascript:void(0)" onclick="clearAllPasswords()">
                            <i class="bi bi-x-circle"></i> Kosongkan Kolom Password
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Dropdown Status Akun -->
            <div class="dropdown">
                <button
                    class="btn btn-sm btn-white border fw-semibold dropdown-toggle d-flex align-items-center gap-1.5 rounded-3 shadow-2xs"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-shield-check text-success"></i> Status Akun
                </button>
                <ul class="dropdown-menu shadow-sm border-0 rounded-3 p-2">
                    <li>
                        <a class="dropdown-item rounded-2 py-2 d-flex align-items-center gap-2 text-success"
                            href="javascript:void(0)" onclick="applyAllActive(true)">
                            <i class="bi bi-check-circle-fill"></i> Aktifkan (Semua / Terpilih)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item rounded-2 py-2 d-flex align-items-center gap-2 text-danger"
                            href="javascript:void(0)" onclick="applyAllActive(false)">
                            <i class="bi bi-slash-circle-fill"></i> Nonaktifkan (Semua / Terpilih)
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Tombol Template WA -->
            <button type="button"
                class="btn btn-sm btn-white border fw-semibold d-flex align-items-center gap-1.5 rounded-3 shadow-2xs"
                data-bs-toggle="modal" data-bs-target="#modalTemplateWa" onclick="openTemplateWaModal()"
                title="Atur format baku template pesan WhatsApp">
                <i class="bi bi-whatsapp text-success"></i> Template WA
            </button>
        </div>

        <!-- Kontrol Tampilan & Reset -->
        <div class="d-flex align-items-center gap-2">
            <button type="button"
                class="btn btn-sm btn-white border rounded-3 fw-semibold text-secondary d-flex align-items-center gap-1.5"
                id="toggleShowPassBtn" onclick="toggleShowAllPasswords()">
                <i class="bi bi-eye-fill"></i> Tampilkan Password
            </button>
            <button type="button"
                class="btn btn-sm btn-light border rounded-3 fw-semibold text-muted d-flex align-items-center gap-1.5"
                onclick="resetFormToOriginal()">
                <i class="bi bi-arrow-counterclockwise"></i> Reset Nilai Awal
            </button>
        </div>
    </div>
</div>

<!-- FORM UTAMA -->
<form id="massEditForm" method="POST"
    action="edit_massal.php?role=<?= htmlspecialchars($filterRole) ?>&kelas_id=<?= $filterKelas ?>">
    <input type="hidden" name="action" value="save_mass_edit">

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table mass-edit-table align-middle mb-0" id="massEditTable">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 40px;">
                            <input type="checkbox" id="selectAllRows" class="form-check-input"
                                onchange="toggleSelectAllRows(this.checked)" title="Pilih Semua Baris">
                        </th>
                        <th class="text-center" style="width: 45px;">NO</th>
                        <th style="min-width: 220px;">NAMA LENGKAP & IDENTITAS</th>
                        <th style="min-width: 180px;">INFO SANTRI (WALI / KELAS)</th>
                        <th style="width: 100px;">ROLE</th>
                        <th style="min-width: 200px;">
                            USERNAME LOGIN <span class="text-danger">*</span>
                        </th>
                        <th style="min-width: 220px;">
                            PASSWORD BARU
                            <small class="d-block text-muted text-lowercase fw-normal"
                                style="font-size: 0.7rem;">(Kosongkan jika tetap)</small>
                        </th>
                        <th class="text-center" style="width: 90px;">STATUS</th>
                        <th class="text-center" style="width: 85px;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($userList)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                Tidak ada data pengguna untuk kriteria filter ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($userList as $i => $u): ?>
                            <?php
                            $cleanPhone = preg_replace('/[^0-9]/', '', (string) ($u['no_hp_ortu'] ?? ''));
                            $nis = trim((string) ($u['nis'] ?? ''));
                            $waPhone = $cleanPhone;
                            if (!empty($waPhone)) {
                                if (substr($waPhone, 0, 1) === '0') {
                                    $waPhone = '62' . substr($waPhone, 1);
                                } elseif (substr($waPhone, 0, 1) === '8') {
                                    $waPhone = '62' . $waPhone;
                                }
                            }
                            ?>
                            <tr class="user-row" id="row-<?= $u['id'] ?>" data-id="<?= $u['id'] ?>"
                                data-role="<?= $u['role'] ?>">
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input row-select-check" data-id="<?= $u['id'] ?>"
                                        data-role="<?= $u['role'] ?>" onchange="updateSelectedCount()">
                                </td>
                                <td class="text-center text-muted font-monospace fw-semibold"><?= $i + 1 ?></td>

                                <!-- Nama Lengkap -->
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                                            style="width: 32px; height: 32px; font-size: 0.85rem;">
                                            <?= strtoupper(substr($u['nama_lengkap'] ?: 'U', 0, 1)) ?>
                                        </div>
                                        <div class="w-100">
                                            <input type="text" name="users[<?= $u['id'] ?>][nama_lengkap]"
                                                class="form-control cell-input py-1 fw-semibold text-dark"
                                                value="<?= htmlspecialchars($u['nama_lengkap']) ?>"
                                                data-original="<?= htmlspecialchars($u['nama_lengkap']) ?>" required>
                                        </div>
                                    </div>
                                </td>

                                <!-- Info Santri / Kelas -->
                                <td>
                                    <?php if ($u['role'] === 'ortu' && !empty($u['nama_siswa'])): ?>
                                        <div class="small">
                                            <div class="fw-bold text-dark d-flex align-items-center gap-1">
                                                <i class="bi bi-person text-primary"></i> <?= htmlspecialchars($u['nama_siswa']) ?>
                                                <span class="badge bg-light text-secondary border font-monospace"
                                                    style="font-size: 0.68rem;"><?= htmlspecialchars($u['nama_kelas'] ?? '-') ?></span>
                                            </div>
                                            <div class="text-muted mt-0.5 d-flex gap-2" style="font-size: 0.72rem;">
                                                <span class="badge-nis" title="Klik untuk jadikan username"
                                                    onclick="setRowUsername(<?= $u['id'] ?>, '<?= addslashes($nis) ?>')"
                                                    style="cursor: pointer;">
                                                    <i class="bi bi-person-badge"></i> NIS: <code><?= $nis ?: '-' ?></code>
                                                </span>
                                                <span class="badge-hp" title="Klik untuk jadikan username"
                                                    onclick="setRowUsername(<?= $u['id'] ?>, '<?= addslashes($cleanPhone) ?>')"
                                                    style="cursor: pointer;">
                                                    <i class="bi bi-telephone"></i> HP: <code><?= $cleanPhone ?: '-' ?></code>
                                                </span>
                                            </div>
                                            <?php if (!empty($waPhone)): ?>
                                                <div class="mt-1">
                                                    <a href="javascript:void(0)"
                                                        onclick="sendUserWa(<?= $u['id'] ?>, '<?= $waPhone ?>', '<?= htmlspecialchars(addslashes($u['nama_siswa'])) ?>', '<?= htmlspecialchars(addslashes($u['nama_kelas'] ?? '')) ?>', 'ortu')"
                                                        class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 text-decoration-none d-inline-flex align-items-center gap-1"
                                                        style="font-size: 0.72rem; padding: 4px 8px; cursor: pointer;"
                                                        title="Kirim Akun Login via WhatsApp">
                                                        <i class="bi bi-whatsapp"></i> Chat WhatsApp
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Role -->
                                <td>
                                    <?php
                                    $roleBadge = 'bg-secondary';
                                    if ($u['role'] === 'admin')
                                        $roleBadge = 'bg-danger';
                                    elseif ($u['role'] === 'bendahara')
                                        $roleBadge = 'bg-primary';
                                    elseif ($u['role'] === 'guru')
                                        $roleBadge = 'bg-success';
                                    elseif ($u['role'] === 'kantin')
                                        $roleBadge = 'bg-warning text-dark';
                                    elseif ($u['role'] === 'ortu')
                                        $roleBadge = 'bg-info text-dark';
                                    ?>
                                    <span class="badge <?= $roleBadge ?> px-2 py-1 rounded-2 font-monospace"
                                        style="font-size: 0.7rem;"><?= strtoupper($u['role']) ?></span>
                                </td>

                                <!-- Username -->
                                <td>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted"><i
                                                class="bi bi-person-circle"></i></span>
                                        <input type="text" name="users[<?= $u['id'] ?>][username]" id="uname-<?= $u['id'] ?>"
                                            class="form-control cell-input font-monospace fw-bold uname-input"
                                            value="<?= htmlspecialchars($u['username']) ?>"
                                            data-original="<?= htmlspecialchars($u['username']) ?>"
                                            data-nis="<?= htmlspecialchars($nis) ?>"
                                            data-hp="<?= htmlspecialchars($cleanPhone) ?>" required autocomplete="off">
                                    </div>
                                </td>

                                <!-- Password Baru -->
                                <td>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted"><i class="bi bi-key"></i></span>
                                        <input type="password" name="users[<?= $u['id'] ?>][password]" id="pass-<?= $u['id'] ?>"
                                            class="form-control cell-input pass-input" placeholder="Tetap (tidak diubah)"
                                            autocomplete="new-password">
                                        <button type="button" class="btn btn-outline-secondary"
                                            title="Lihat / Sembunyikan baris ini" onclick="toggleRowPass(<?= $u['id'] ?>)">
                                            <i class="bi bi-eye" id="eye-<?= $u['id'] ?>"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" title="Samakan dengan username"
                                            onclick="setRowPassToUname(<?= $u['id'] ?>)">
                                            <i class="bi bi-arrow-left-short"></i> =User
                                        </button>
                                    </div>
                                </td>

                                <!-- Status Aktif -->
                                <td class="text-center">
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input" type="checkbox" name="users[<?= $u['id'] ?>][is_active]"
                                            value="1" <?= $u['is_active'] ? 'checked' : '' ?> title="Status Aktif / Nonaktif">
                                    </div>
                                </td>

                                <!-- Aksi Baris -->
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <?php if (!empty($waPhone)): ?>
                                            <button type="button" class="btn btn-sm btn-success rounded-circle p-1"
                                                style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;"
                                                title="Kirim Username & Password via WhatsApp"
                                                onclick="sendUserWa(<?= $u['id'] ?>, '<?= $waPhone ?>', '<?= htmlspecialchars(addslashes($u['nama_siswa'] ?: $u['nama_lengkap'])) ?>', '<?= htmlspecialchars(addslashes($u['nama_kelas'] ?? '')) ?>', '<?= $u['role'] ?>')">
                                                <i class="bi bi-whatsapp"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-light border rounded-circle p-1"
                                            style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;"
                                            title="Reset baris ini" onclick="resetRow(<?= $u['id'] ?>)">
                                            <i class="bi bi-arrow-counterclockwise text-muted"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- STICKY SAVE BAR -->
    <?php if (!empty($userList)): ?>
        <div class="sticky-save-bar d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="fw-bold fs-6 d-flex align-items-center gap-2">
                    <i class="bi bi-pencil-square text-warning"></i> Total: <strong><?= count($userList) ?></strong> Akun
                    Terbuka
                </span>
                <span class="text-light opacity-75 small d-none d-md-inline">&bull; Klik tombol di bawah untuk menyimpan
                    perubahan data login secara permanen.</span>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="index.php?role=<?= htmlspecialchars($filterRole) ?>"
                    class="btn btn-outline-light btn-sm px-3 rounded-3">
                    Batal
                </a>
                <button type="submit"
                    class="btn btn-success fw-bold px-4 py-2 rounded-3 shadow d-flex align-items-center gap-2"
                    onclick="return confirm('Apakah Anda yakin ingin menyimpan seluruh perubahan username & password pengguna?')">
                    <i class="bi bi-check2-circle fs-5"></i> Simpan Semua Perubahan
                </button>
            </div>
        </div>
    <?php endif; ?>
</form>

<!-- MODAL INPUT PASSWORD SERAGAM DENGAN PILIHAN TARGET PENGGUNA -->
<div class="modal fade" id="modalCustomPass" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom bg-light px-3.5 py-3">
                <h6 class="modal-title fw-bold text-dark d-flex align-items-center gap-2 fs-6">
                    <i class="bi bi-shield-lock-fill text-danger"></i> Setel Password Seragam / Pilihan Akun
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3.5">
                <!-- Target Pilihan Pengguna -->
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark mb-1.5 d-flex align-items-center gap-1.5">
                        <i class="bi bi-people-fill text-primary"></i> Terapkan Password Untuk Target:
                    </label>
                    <div class="d-flex flex-column gap-2 p-2.5 rounded-3 bg-light border">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="targetPassOption" id="targetAll"
                                value="all" checked onchange="toggleCustomPassTarget()">
                            <label class="form-check-label small fw-semibold text-dark" for="targetAll">
                                Semua akun yang tampil di tabel (<span
                                    id="countTargetAll"><?= count($userList) ?></span> akun)
                            </label>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="targetPassOption" id="targetSelected"
                                value="selected" onchange="toggleCustomPassTarget()">
                            <label
                                class="form-check-label small fw-semibold text-dark d-flex align-items-center gap-1.5"
                                for="targetSelected">
                                Hanya akun yang dicentang / dipilih
                                <span id="countTargetSelectedBadge" class="badge bg-primary rounded-pill px-2">0
                                    dipilih</span>
                            </label>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="targetPassOption" id="targetRole"
                                value="role" onchange="toggleCustomPassTarget()">
                            <label class="form-check-label small fw-semibold text-dark" for="targetRole">
                                Berdasarkan Role Tertentu Saja
                            </label>
                        </div>

                        <div id="roleSelectWrapper" class="ms-4 mt-0.5" style="display: none;">
                            <select id="targetRoleSelect" class="form-select form-select-sm border-2 rounded-3">
                                <option value="ortu">Orang Tua (Ortu) Saja</option>
                                <option value="guru">Guru Saja</option>
                                <option value="bendahara">Bendahara Saja</option>
                                <option value="admin">Admin Saja</option>
                            </select>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="targetPassOption" id="targetEmptyOnly"
                                value="empty" onchange="toggleCustomPassTarget()">
                            <label class="form-check-label small fw-semibold text-dark" for="targetEmptyOnly">
                                Hanya akun yang kolom passwordnya masih kosong
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Input Password Baru -->
                <div class="mb-2">
                    <label class="form-label small fw-bold text-dark mb-1 d-flex align-items-center gap-1.5">
                        <i class="bi bi-key-fill text-warning"></i> Password Baru:
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-lock text-muted"></i></span>
                        <input type="text" id="customPassInput" class="form-control fw-bold font-monospace"
                            value="123456" placeholder="Misal: 123456" autocomplete="off">
                    </div>
                    <div class="d-flex gap-1.5 mt-2">
                        <button type="button" class="btn btn-sm btn-light border flex-grow-1 font-monospace"
                            onclick="document.getElementById('customPassInput').value='123456'">123456</button>
                        <button type="button" class="btn btn-sm btn-light border flex-grow-1 font-monospace"
                            onclick="document.getElementById('customPassInput').value='smpismi2026'">smpismi2026</button>
                        <button type="button" class="btn btn-sm btn-light border flex-grow-1 font-monospace"
                            onclick="document.getElementById('customPassInput').value='bismillah'">bismillah</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light px-3.5 py-2.5">
                <button type="button" class="btn btn-sm btn-secondary rounded-3 px-3"
                    data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-sm btn-primary fw-bold rounded-3 px-3.5"
                    onclick="applyCustomPassword()">
                    <i class="bi bi-check2"></i> Terapkan Password
                </button>
            </div>
        </div>
    </div>
</div>

<!-- TOAST NOTIFICATION CONTAINER -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;">
    <div id="liveToast" class="toast align-items-center text-bg-dark border-0 rounded-3 shadow-lg" role="alert"
        aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body py-2.5 px-3 fw-medium" id="toastMessage">
                Aksi berhasil dijalankan.
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                aria-label="Close"></button>
        </div>
    </div>
</div>

<!-- MODAL: PREVIEW & EDIT PESAN WHATSAPP -->
<div class="modal fade" id="modalSendWa" tabindex="-1" aria-labelledby="modalSendWaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center"
                        style="width: 38px; height: 38px;">
                        <i class="bi bi-whatsapp fs-5"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0" id="modalSendWaLabel">Kirim Informasi Akun via
                            WhatsApp</h6>
                        <small class="text-muted">Preview dan sesuaikan teks pesan sebelum dikirim ke orang tua /
                            santri</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-7">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1">Penerima Pesan:</label>
                        <div class="d-flex align-items-center gap-2.5 p-2.5 bg-light rounded-3 border">
                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                                style="width: 36px; height: 36px;">
                                <i class="bi bi-person-fill"></i>
                            </div>
                            <div class="text-truncate">
                                <div class="fw-bold text-dark text-truncate" id="waModalTargetName">-</div>
                                <div class="small text-muted" id="waModalTargetDesc">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1">Nomor WhatsApp
                            Tujuan:</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-success"><i
                                    class="bi bi-telephone-fill"></i></span>
                            <input type="text" id="waRecipientPhone" class="form-control font-monospace fw-bold"
                                placeholder="62812xxxx">
                        </div>
                        <small class="text-muted" style="font-size: 0.72rem;">Bisa diganti jika wali memiliki nomor
                            lain.</small>
                    </div>
                </div>

                <div class="mb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <label class="form-label small fw-bold text-dark mb-0 d-flex align-items-center gap-1.5">
                        <i class="bi bi-pencil-square text-primary"></i> Isi Pesan WhatsApp (Dapat diedit langsung):
                    </label>
                    <button type="button" class="btn btn-xs btn-outline-secondary rounded-2 px-2 py-1"
                        onclick="resetWaModalToTemplate()" title="Kembalikan teks sesuai template default">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset Teks ke Template
                    </button>
                </div>

                <div class="position-relative">
                    <textarea id="waMessageEditor" class="form-control font-monospace text-dark p-3" rows="11"
                        style="font-size: 0.88rem; line-height: 1.55; border-radius: 10px; resize: vertical;"
                        placeholder="Ketik atau edit isi pesan di sini..."></textarea>
                </div>

                <div class="d-flex align-items-center justify-content-between mt-2 text-muted"
                    style="font-size: 0.75rem;">
                    <span><i class="bi bi-info-circle text-primary"></i> Anda bebas menambah catatan khusus pada pesan
                        di atas sebelum dikirim. Format WA: *tebal*, _miring_.</span>
                    <span id="waCharCounter" class="font-monospace fw-semibold">0 karakter</span>
                </div>
            </div>
            <div class="modal-footer bg-light px-4 py-2.5 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-3"
                        onclick="copyWaMessageText()">
                        <i class="bi bi-clipboard"></i> Salin Teks
                    </button>
                    <button type="button" class="btn btn-sm btn-link text-muted text-decoration-none"
                        onclick="openMasterTemplateModal()">
                        <i class="bi bi-gear-fill text-secondary"></i> Template Master
                    </button>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-secondary rounded-3 px-3"
                        data-bs-dismiss="modal">Batal</button>
                    <button type="button"
                        class="btn btn-sm btn-success fw-bold rounded-3 px-3.5 shadow-sm d-flex align-items-center gap-1.5"
                        onclick="sendWaFromModal()">
                        <i class="bi bi-whatsapp"></i> Buka WhatsApp & Kirim
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: MASTER TEMPLATE PESAN WHATSAPP -->
<div class="modal fade" id="modalTemplateWa" tabindex="-1" aria-labelledby="modalTemplateWaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST"
                action="edit_massal.php?role=<?= htmlspecialchars($filterRole) ?>&kelas_id=<?= $filterKelas ?>">
                <input type="hidden" name="action" value="save_wa_template">
                <div class="modal-header bg-light border-bottom px-4 py-3">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center"
                            style="width: 38px; height: 38px;">
                            <i class="bi bi-sliders fs-5"></i>
                        </div>
                        <div>
                            <h6 class="modal-title fw-bold text-dark mb-0" id="modalTemplateWaLabel">Format Template
                                Pesan WhatsApp</h6>
                            <small class="text-muted">Atur format baku pesan pengiriman akun login ke orang tua /
                                wali</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark mb-1">Klik Placeholder untuk Disisipkan ke
                            Kursor:</label>
                        <div class="d-flex flex-wrap gap-1.5">
                            <button type="button" class="btn btn-xs btn-light border font-monospace text-primary"
                                onclick="insertPlaceholder('{nama_siswa}')"
                                title="Nama santri/siswa">{nama_siswa}</button>
                            <button type="button" class="btn btn-xs btn-light border font-monospace text-primary"
                                onclick="insertPlaceholder('{nama_kelas}')"
                                title="Nama kelas santri">{nama_kelas}</button>
                            <button type="button"
                                class="btn btn-xs btn-light border font-monospace text-success fw-bold"
                                onclick="insertPlaceholder('{username}')" title="Username login">{username}</button>
                            <button type="button" class="btn btn-xs btn-light border font-monospace text-danger fw-bold"
                                onclick="insertPlaceholder('{password}')" title="Password login">{password}</button>
                            <button type="button" class="btn btn-xs btn-light border font-monospace text-info"
                                onclick="insertPlaceholder('{link_login}')"
                                title="Link URL portal">{link_login}</button>
                            <button type="button" class="btn btn-xs btn-light border font-monospace text-dark"
                                onclick="insertPlaceholder('{app_name}')" title="Nama aplikasi">{app_name}</button>
                            <button type="button" class="btn btn-xs btn-light border font-monospace text-dark"
                                onclick="insertPlaceholder('{nama_sekolah}')"
                                title="Nama sekolah/pesantren">{nama_sekolah}</button>
                        </div>
                    </div>

                    <div class="mb-2 d-flex justify-content-between align-items-center">
                        <label class="form-label small fw-bold text-dark mb-0">Template Pesan:</label>
                        <button type="button" class="btn btn-xs btn-outline-secondary"
                            onclick="restoreDefaultWaTemplate()">
                            <i class="bi bi-arrow-counterclockwise"></i> Pulihkan Standar Awal
                        </button>
                    </div>

                    <textarea name="user_pesan_wa" id="masterWaTemplateInput" class="form-control font-monospace p-3"
                        rows="10"
                        style="font-size: 0.88rem; line-height: 1.55; border-radius: 10px;"><?= htmlspecialchars($userWaTemplate) ?></textarea>

                    <small class="text-muted d-block mt-2" style="font-size: 0.74rem;">
                        <i class="bi bi-lightbulb-fill text-warning"></i>
                        Template ini akan otomatis memuat nama siswa, kelas, username, dan password baris yang
                        bersangkutan saat Anda menekan tombol WhatsApp.
                    </small>
                </div>
                <div class="modal-footer bg-light px-4 py-2.5">
                    <button type="button" class="btn btn-sm btn-secondary rounded-3 px-3"
                        data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold rounded-3 px-4">
                        <i class="bi bi-check2"></i> Simpan Template
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Toast Notifikasi Modern
    function showToast(msg, isSuccess = true) {
        const toastEl = document.getElementById('liveToast');
        const toastMsg = document.getElementById('toastMessage');
        if (toastEl && toastMsg) {
            toastMsg.innerHTML = (isSuccess ? '<i class="bi bi-check-circle-fill text-success me-1.5"></i> ' : '<i class="bi bi-exclamation-triangle-fill text-warning me-1.5"></i> ') + msg;
            const toast = new bootstrap.Toast(toastEl, { delay: 3500 });
            toast.show();
        }
    }

    // Filter realtime pencarian di tabel
    function filterTableRows(query) {
        const q = query.toLowerCase().trim();
        const rows = document.querySelectorAll('#massEditTable tbody tr.user-row');
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(q) ? '' : 'none';
        });
    }

    // Toggle Select All Checkbox
    function toggleSelectAllRows(isChecked) {
        const checkboxes = document.querySelectorAll('.row-select-check');
        checkboxes.forEach(cb => {
            const row = cb.closest('tr');
            if (row && row.style.display !== 'none') {
                cb.checked = isChecked;
            }
        });
        updateSelectedCount();
    }

    // Update Counter Jumlah Baris Terpilih
    function updateSelectedCount() {
        const checked = document.querySelectorAll('.row-select-check:checked');
        const count = checked.length;
        const badge = document.getElementById('selectedBadge');
        const countNum = document.getElementById('selectedCountNum');
        const modalBadge = document.getElementById('countTargetSelectedBadge');
        const radioSelected = document.getElementById('targetSelected');

        if (countNum) countNum.innerText = count;
        if (modalBadge) modalBadge.innerText = count + ' dipilih';

        if (badge) {
            if (count > 0) {
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
        }

        // Jika ada yang dicentang saat modal dibuka, defaultkan ke pilihan 'selected'
        if (count > 0 && radioSelected) {
            radioSelected.checked = true;
            toggleCustomPassTarget();
        }
    }

    // Toggle Tampilan Dropdown Role pada Modal Target
    function toggleCustomPassTarget() {
        const radioRole = document.getElementById('targetRole');
        const roleWrapper = document.getElementById('roleSelectWrapper');
        if (radioRole && roleWrapper) {
            roleWrapper.style.display = radioRole.checked ? 'block' : 'none';
        }
    }

    // Set username untuk 1 baris
    function setRowUsername(id, value) {
        if (!value) {
            showToast('Data tidak tersedia untuk santri ini.', false);
            return;
        }
        const input = document.getElementById('uname-' + id);
        if (input) {
            input.value = value;
            input.classList.add('modified');
            showToast(`Username ID #${id} diset ke ${value}`);
        }
    }

    // Set password 1 baris sama dengan usernamenya
    function setRowPassToUname(id) {
        const unameInput = document.getElementById('uname-' + id);
        const passInput = document.getElementById('pass-' + id);
        if (unameInput && passInput) {
            passInput.value = unameInput.value;
            passInput.classList.add('modified');
            showToast(`Password baris ID #${id} disamakan dengan username.`);
        }
    }

    // Set semua username (No HP atau NIS) - Mendukung baris terpilih jika ada yang dicentang
    function applyAllUsernames(type) {
        const selectedChecks = document.querySelectorAll('.row-select-check:checked');
        const hasSelection = selectedChecks.length > 0;
        const rows = hasSelection
            ? Array.from(selectedChecks).map(cb => cb.closest('tr'))
            : document.querySelectorAll('#massEditTable tbody tr.user-row');

        let count = 0;
        rows.forEach(row => {
            if (!row || row.style.display === 'none') return;
            const input = row.querySelector('.uname-input');
            if (input) {
                const targetVal = (type === 'no_hp') ? input.getAttribute('data-hp') : input.getAttribute('data-nis');
                if (targetVal && targetVal.trim() !== '') {
                    input.value = targetVal.trim();
                    input.classList.add('modified');
                    count++;
                }
            }
        });
        showToast(`Berhasil menerapkan <b>${count}</b> username dari ${type === 'no_hp' ? 'Nomor HP' : 'NIS'}${hasSelection ? ' (pada akun terpilih)' : ''}.`);
    }

    // Set semua password sama dengan username - Mendukung baris terpilih jika ada yang dicentang
    function applyAllPasswords(mode) {
        if (mode === 'username') {
            const selectedChecks = document.querySelectorAll('.row-select-check:checked');
            const hasSelection = selectedChecks.length > 0;
            const rows = hasSelection
                ? Array.from(selectedChecks).map(cb => cb.closest('tr'))
                : document.querySelectorAll('#massEditTable tbody tr.user-row');

            let count = 0;
            rows.forEach(row => {
                if (!row || row.style.display === 'none') return;
                const unameInput = row.querySelector('.uname-input');
                const passInput = row.querySelector('.pass-input');
                if (unameInput && passInput) {
                    passInput.value = unameInput.value;
                    passInput.classList.add('modified');
                    count++;
                }
            });
            showToast(`Berhasil menyamakan <b>${count}</b> password dengan username masing-masing${hasSelection ? ' (pada akun terpilih)' : ''}.`);
        }
    }

    // Setel password seragam dari modal dengan filter target lengkap
    function applyCustomPassword() {
        const passVal = document.getElementById('customPassInput').value.trim();
        if (passVal === '') {
            alert('Password tidak boleh kosong.');
            return;
        }

        const targetOption = document.querySelector('input[name="targetPassOption"]:checked')?.value || 'all';
        const targetRole = document.getElementById('targetRoleSelect')?.value || 'ortu';
        const rows = document.querySelectorAll('#massEditTable tbody tr.user-row');
        let count = 0;

        rows.forEach(row => {
            if (row.style.display === 'none') return;

            const rowId = row.getAttribute('data-id');
            const rowRole = row.getAttribute('data-role');
            const passInput = row.querySelector('.pass-input');
            const check = row.querySelector('.row-select-check');

            if (!passInput) return;

            let matches = false;
            if (targetOption === 'all') {
                matches = true;
            } else if (targetOption === 'selected') {
                matches = check && check.checked;
            } else if (targetOption === 'role') {
                matches = (rowRole === targetRole);
            } else if (targetOption === 'empty') {
                matches = (passInput.value.trim() === '');
            }

            if (matches) {
                passInput.value = passVal;
                passInput.classList.add('modified');
                count++;
            }
        });

        const modalEl = document.getElementById('modalCustomPass');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();

        if (count > 0) {
            showToast(`Berhasil menyetel password "<b>${passVal}</b>" untuk <b>${count}</b> akun pengguna.`);
        } else {
            showToast('Tidak ada akun yang sesuai dengan kriteria target yang dipilih.', false);
        }
    }

    // Kosongkan semua password (atau baris terpilih)
    function clearAllPasswords() {
        const selectedChecks = document.querySelectorAll('.row-select-check:checked');
        const hasSelection = selectedChecks.length > 0;
        const rows = hasSelection
            ? Array.from(selectedChecks).map(cb => cb.closest('tr'))
            : document.querySelectorAll('#massEditTable tbody tr.user-row');

        let count = 0;
        rows.forEach(row => {
            if (!row || row.style.display === 'none') return;
            const passInput = row.querySelector('.pass-input');
            if (passInput) {
                passInput.value = '';
                passInput.classList.remove('modified');
                count++;
            }
        });
        showToast(`Kolom password baru untuk <b>${count}</b> akun telah dikosongkan.`);
    }

    // Aktifkan atau Nonaktifkan baris (Semua atau yang dicentang)
    function applyAllActive(state) {
        const selectedChecks = document.querySelectorAll('.row-select-check:checked');
        const hasSelection = selectedChecks.length > 0;
        const rows = hasSelection
            ? Array.from(selectedChecks).map(cb => cb.closest('tr'))
            : document.querySelectorAll('#massEditTable tbody tr.user-row');

        let count = 0;
        rows.forEach(row => {
            if (!row || row.style.display === 'none') return;
            const sw = row.querySelector('input[type="checkbox"][name*="[is_active]"]');
            if (sw) {
                sw.checked = state;
                count++;
            }
        });
        showToast(`<b>${count}</b> akun pengguna berhasil di-<b>${state ? 'AKTIFKAN' : 'NONAKTIFKAN'}</b>.`);
    }

    // Toggle Show/Hide password per baris
    function toggleRowPass(id) {
        const passInput = document.getElementById('pass-' + id);
        const eyeIcon = document.getElementById('eye-' + id);
        if (passInput) {
            const isPass = (passInput.type === 'password');
            passInput.type = isPass ? 'text' : 'password';
            if (eyeIcon) {
                eyeIcon.className = isPass ? 'bi bi-eye-slash-fill text-primary' : 'bi bi-eye';
            }
            if (isPass && passInput.value.trim() === '') {
                showToast(`Baris #${id}: Kolom password masih kosong (akan tetap menggunakan password akun saat ini).`, false);
            }
        }
    }

    // Toggle Show/Hide semua password
    let isShowingPass = false;
    function toggleShowAllPasswords() {
        isShowingPass = !isShowingPass;
        const passInputs = document.querySelectorAll('.pass-input');
        const btn = document.getElementById('toggleShowPassBtn');
        let filledCount = 0;

        passInputs.forEach(input => {
            input.type = isShowingPass ? 'text' : 'password';
            if (input.value.trim() !== '') {
                filledCount++;
            }
        });

        // Update semua icon mata di baris
        document.querySelectorAll('[id^="eye-"]').forEach(eye => {
            eye.className = isShowingPass ? 'bi bi-eye-slash-fill text-primary' : 'bi bi-eye';
        });

        if (btn) {
            btn.innerHTML = isShowingPass ? '<i class="bi bi-eye-slash-fill text-primary"></i> Sembunyikan Password' : '<i class="bi bi-eye-fill"></i> Tampilkan Password';
        }

        if (isShowingPass) {
            if (filledCount > 0) {
                showToast(`Menampilkan <b>${filledCount}</b> password yang telah diisi.`);
            } else {
                showToast('Mode teks aktif. Masukkan password baru pada kolom atau gunakan tombol Aksi Cepat.');
            }
        } else {
            showToast('Password disembunyikan (mode sensor titik).');
        }
    }

    // Reset 1 baris ke original
    function resetRow(id) {
        const row = document.getElementById('row-' + id);
        if (!row) return;
        const inputs = row.querySelectorAll('.cell-input');
        inputs.forEach(input => {
            const orig = input.getAttribute('data-original');
            if (orig !== null) {
                input.value = orig;
            } else {
                input.value = '';
            }
            input.classList.remove('modified');
        });
        showToast(`Baris ID #${id} dikembalikan ke nilai awal.`);
    }

    // Reset semua ke original
    function resetFormToOriginal() {
        if (!confirm('Kembalikan seluruh kolom ke nilai awal?')) return;
        const inputs = document.querySelectorAll('.cell-input');
        inputs.forEach(input => {
            const orig = input.getAttribute('data-original');
            if (orig !== null) {
                input.value = orig;
            } else {
                input.value = '';
            }
            input.classList.remove('modified');
        });
        showToast('Seluruh formulir dikembalikan ke nilai awal.');
    }

    // Deteksi perubahan manual pada input
    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('cell-input')) {
            const orig = e.target.getAttribute('data-original');
            if (orig !== null && e.target.value !== orig) {
                e.target.classList.add('modified');
            } else if (orig === null && e.target.value !== '') {
                e.target.classList.add('modified');
            } else {
                e.target.classList.remove('modified');
            }
        }
    });

    // ==========================================
    // INTEGRASI WHATSAPP & EDIT TEKS PESAN
    // ==========================================
    let currentWaTarget = {
        id: 0,
        phone: '',
        nama: '',
        kelas: '',
        role: ''
    };

    const userWaTemplateStr = <?= json_encode($userWaTemplate) ?>;
    const defaultWaTemplateStr = <?= json_encode($defaultWaTemplate) ?>;
    const schoolNameStr = <?= json_encode(getSetting('nama_sekolah', SCHOOL_NAME)) ?>;
    const appNameStr = <?= json_encode(getSetting('app_name', APP_NAME)) ?>;
    const portalUrlStr = <?= json_encode(defined('BASE_URL') ? BASE_URL : '') ?>;

    // Membangun isi pesan WhatsApp berdasarkan input terkini pada baris tabel
    function buildWaMessage(target) {
        const unameInput = document.getElementById('uname-' + target.id);
        const passInput = document.getElementById('pass-' + target.id);

        const username = unameInput ? unameInput.value.trim() : '';
        const password = passInput ? passInput.value.trim() : '';

        let passInfo = "";
        if (password !== "") {
            passInfo = `*${password}*`;
        } else {
            passInfo = `_(Gunakan password yang telah diberikan sebelumnya / tidak diubah)_`;
        }

        let msg = userWaTemplateStr || defaultWaTemplateStr;

        // Penyesuaian khusus jika role bukan ortu
        if (target.role && target.role !== 'ortu') {
            msg = msg.replace(/Yth\.\s*Orang\s*Tua\s*\/\s*Wali\s*dari\s*\*\{nama_siswa\}\*(\s*\(Kelas\s*\{nama_kelas\}\))?/gi, `Yth. *${target.nama}*`);
        }

        msg = msg.replace(/{nama_siswa}/g, target.nama || '');
        msg = msg.replace(/{nama_kelas}/g, target.kelas || '-');
        msg = msg.replace(/{username}/g, username);
        msg = msg.replace(/{password}/g, passInfo);
        msg = msg.replace(/{link_login}/g, portalUrlStr);
        msg = msg.replace(/{app_name}/g, appNameStr);
        msg = msg.replace(/{nama_sekolah}/g, schoolNameStr);

        return msg;
    }

    // Buka Modal Edit Pesan WhatsApp Sebelum Dikirim
    function sendUserWa(id, phone, nama, kelas, role) {
        currentWaTarget = { id, phone, nama, kelas, role };

        const targetNameEl = document.getElementById('waModalTargetName');
        const targetDescEl = document.getElementById('waModalTargetDesc');
        const phoneInput = document.getElementById('waRecipientPhone');
        const editor = document.getElementById('waMessageEditor');

        if (targetNameEl) targetNameEl.innerText = nama || 'Pengguna #' + id;
        if (targetDescEl) {
            let desc = (role === 'ortu') ? `Wali Santri ${kelas ? '• Kelas ' + kelas : ''}` : `Role: ${role.toUpperCase()}`;
            targetDescEl.innerText = desc;
        }
        if (phoneInput) phoneInput.value = phone || '';

        const msg = buildWaMessage(currentWaTarget);
        if (editor) {
            editor.value = msg;
            updateWaCharCount();
        }

        const modalEl = document.getElementById('modalSendWa');
        if (modalEl) {
            try {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.show();
                } else if (typeof $ !== 'undefined' && $.fn.modal) {
                    $(modalEl).modal('show');
                }
            } catch (err) {
                console.error('Error opening WA Send modal:', err);
            }
        }
    }

    // Buka Modal Master Template WA
    function openTemplateWaModal() {
        const el = document.getElementById('modalTemplateWa');
        if (!el) return;
        try {
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const modal = bootstrap.Modal.getOrCreateInstance(el);
                modal.show();
            } else if (typeof $ !== 'undefined' && $.fn.modal) {
                $(el).modal('show');
            }
        } catch (err) {
            console.error('Error opening template modal:', err);
        }
    }

    // Reset isi teks di modal ke template baku
    function resetWaModalToTemplate() {
        const editor = document.getElementById('waMessageEditor');
        if (editor && currentWaTarget.id) {
            editor.value = buildWaMessage(currentWaTarget);
            updateWaCharCount();
            showToast('Teks dikembalikan ke template default.');
        }
    }

    // Update counter karakter pesan WA
    function updateWaCharCount() {
        const editor = document.getElementById('waMessageEditor');
        const counter = document.getElementById('waCharCounter');
        if (editor && counter) {
            counter.innerText = editor.value.length + ' karakter';
        }
    }

    // Listener realtime perubahan teks editor WA
    const waTextareaEl = document.getElementById('waMessageEditor');
    if (waTextareaEl) {
        waTextareaEl.addEventListener('input', updateWaCharCount);
    }

    // Salin Teks Pesan WA ke Clipboard
    function copyWaMessageText() {
        const editor = document.getElementById('waMessageEditor');
        if (!editor || !editor.value.trim()) {
            showToast('Tidak ada teks untuk disalin.', false);
            return;
        }
        navigator.clipboard.writeText(editor.value).then(() => {
            showToast('<i class="bi bi-clipboard-check text-success"></i> Teks pesan berhasil disalin ke clipboard.');
        }).catch(() => {
            editor.select();
            document.execCommand('copy');
            showToast('Teks berhasil disalin.');
        });
    }

    // Eksekusi Buka WhatsApp & Kirim Teks yang Telah Diedit
    function sendWaFromModal() {
        const phoneInput = document.getElementById('waRecipientPhone');
        const editor = document.getElementById('waMessageEditor');

        let rawPhone = phoneInput ? phoneInput.value.trim() : '';
        let cleanPhone = rawPhone.replace(/[^0-9]/g, '');

        if (!cleanPhone) {
            showToast('Nomor WhatsApp tujuan belum diisi.', false);
            if (phoneInput) phoneInput.focus();
            return;
        }

        if (cleanPhone.startsWith('0')) {
            cleanPhone = '62' + cleanPhone.slice(1);
        } else if (cleanPhone.startsWith('8')) {
            cleanPhone = '62' + cleanPhone;
        }

        const message = editor ? editor.value.trim() : '';
        if (!message) {
            showToast('Pesan WhatsApp tidak boleh kosong.', false);
            if (editor) editor.focus();
            return;
        }

        const waUrl = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(message)}`;
        window.open(waUrl, '_blank');

        const modalEl = document.getElementById('modalSendWa');
        if (modalEl) {
            try {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                } else if (typeof $ !== 'undefined' && $.fn.modal) {
                    $(modalEl).modal('hide');
                }
            } catch (e) { }
        }

        showToast(`Membuka WhatsApp untuk <b>${currentWaTarget.nama || cleanPhone}</b>.`);
    }

    // Buka Modal Master Template dari Modal Kirim
    function openMasterTemplateModal() {
        const sendModalEl = document.getElementById('modalSendWa');
        if (sendModalEl) {
            try {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    const sendModal = bootstrap.Modal.getInstance(sendModalEl);
                    if (sendModal) sendModal.hide();
                } else if (typeof $ !== 'undefined' && $.fn.modal) {
                    $(sendModalEl).modal('hide');
                }
            } catch (e) { }
        }
        setTimeout(() => {
            openTemplateWaModal();
        }, 300);
    }

    // Sisipkan placeholder ke textarea master template pada posisi kursor
    function insertPlaceholder(tag) {
        const el = document.getElementById('masterWaTemplateInput');
        if (!el) return;
        const start = el.selectionStart;
        const end = el.selectionEnd;
        const text = el.value;
        el.value = text.substring(0, start) + tag + text.substring(end);
        el.focus();
        el.setSelectionRange(start + tag.length, start + tag.length);
    }

    // Pulihkan Template Master ke Standar Bawaan
    function restoreDefaultWaTemplate() {
        if (!confirm('Kembalikan template ke format standar sistem?')) return;
        const el = document.getElementById('masterWaTemplateInput');
        if (el) {
            el.value = defaultWaTemplateStr;
            showToast('Template dikembalikan ke teks standar. Silakan klik Simpan Template.');
        }
    }

    // Inisialisasi awal saat load
    document.addEventListener('DOMContentLoaded', function () {
        updateSelectedCount();
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>