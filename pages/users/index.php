<?php
/**
 * KELOLA USER - List, Tambah, Edit, Hapus, Role & Class Tabs/Filters & Bulk Auto-Generate Ortu Accounts
 */
$pageTitle = 'Kelola User';
$activePage = 'users';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('users');

$pdo = getConnection();

// Auto-check & auto-migrate tabel dan kolom jika belum ada (mencegah HTTP 500 di server live)
require_once __DIR__ . '/../../config/db_auto_sync.php';
runDatabaseAutoSync($pdo);


// ===== PROSES TAMBAH USER =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $role = $_POST['role'] ?? 'bendahara';
    $siswaId = ($role === 'ortu' && !empty($_POST['siswa_id'])) ? (int) $_POST['siswa_id'] : null;

    if ($username && $password && $nama) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role, siswa_id) VALUES (:u,:p,:n,:r,:sid)");
            $stmt->execute([':u' => $username, ':p' => $hash, ':n' => $nama, ':r' => $role, ':sid' => $siswaId]);
            redirect('index.php?role=' . $role, 'success', "User '$username' berhasil ditambahkan.");
        } catch (PDOException $e) {
            redirect('index.php?role=' . $role, 'danger', 'Username sudah digunakan.');
        }
    } else {
        redirect('index.php?role=' . $role, 'danger', 'Semua field wajib diisi.');
    }
}

// ===== PROSES BULK GENERATE ORTU ACCOUNTS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_generate_ortu') {
    try {
        $pdo->beginTransaction();

        // Cari siswa yang memiliki NIS tapi belum terhubung dengan akun user role ortu
        $unlinkedSiswaList = $pdo->query("
            SELECT id, nis, nama 
            FROM siswa 
            WHERE nis IS NOT NULL AND nis != ''
              AND id NOT IN (SELECT siswa_id FROM users WHERE role = 'ortu' AND siswa_id IS NOT NULL)
              AND nis NOT IN (SELECT username FROM users WHERE role = 'ortu')
        ")->fetchAll();

        $insertedCount = 0;
        $stmtInsOrtu = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role, siswa_id, is_active) VALUES (:u, :p, :n, 'ortu', :sid, 1)");

        foreach ($unlinkedSiswaList as $s) {
            $nis = trim($s['nis']);
            $hash = password_hash($nis, PASSWORD_DEFAULT);
            $stmtInsOrtu->execute([
                ':u' => $nis,
                ':p' => $hash,
                ':n' => 'Orang Tua ' . $s['nama'],
                ':sid' => (int) $s['id']
            ]);
            $insertedCount++;
        }

        $pdo->commit();
        redirect('index.php?role=ortu', 'success', "Berhasil membuat otomatis $insertedCount akun Orang Tua.");
    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        redirect('index.php?role=ortu', 'danger', 'Gagal membuat akun otomatis: ' . $e->getMessage());
    }
}

// ===== PROSES SINKRONISASI / FORMAT USERNAME ORTU MASSAL =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_sync_username_ortu') {
    if (($_SESSION['role'] ?? '') !== 'admin') {
        redirect('index.php?role=ortu', 'danger', 'Hanya administrator yang memiliki izin.');
    }

    $formatType = $_POST['format_type'] ?? 'nis'; // 'nis' atau 'no_hp'
    $resetPassword = isset($_POST['reset_password_to_new_username']) ? 1 : 0;

    try {
        $pdo->beginTransaction();

        // Ambil semua user ortu yang terhubung dengan siswa_id
        $stmtOrtu = $pdo->query("
            SELECT u.id, u.username, u.siswa_id, s.nis, s.no_hp_ortu, s.nama as nama_siswa
            FROM users u
            INNER JOIN siswa s ON u.siswa_id = s.id
            WHERE u.role = 'ortu'
        ");
        $ortuList = $stmtOrtu->fetchAll();

        $updatedCount = 0;
        $skippedCount = 0;

        $stmtUpdateUser = $pdo->prepare("
            UPDATE users 
            SET username = :new_uname, 
                password = CASE WHEN :reset_pass = 1 THEN :new_pass ELSE password END,
                nama_lengkap = :new_nama
            WHERE id = :uid
        ");

        foreach ($ortuList as $row) {
            $newUname = '';
            if ($formatType === 'no_hp') {
                $rawHp = preg_replace('/[^0-9]/', '', (string) ($row['no_hp_ortu'] ?? ''));
                if (!empty($rawHp)) {
                    $newUname = $rawHp;
                }
            } else {
                $rawNis = trim((string) ($row['nis'] ?? ''));
                if (!empty($rawNis)) {
                    $newUname = $rawNis;
                }
            }

            if (empty($newUname)) {
                $skippedCount++;
                continue;
            }

            // Cek apakah username ini dipakai user lain
            $stmtCek = $pdo->prepare("SELECT id FROM users WHERE username = :uname AND id != :uid");
            $stmtCek->execute([':uname' => $newUname, ':uid' => $row['id']]);
            if ($stmtCek->fetch()) {
                $skippedCount++;
                continue;
            }

            $newNama = 'Orang Tua / Wali ' . $row['nama_siswa'];
            $newPassHash = password_hash($newUname, PASSWORD_DEFAULT);

            $stmtUpdateUser->execute([
                ':new_uname' => $newUname,
                ':reset_pass' => $resetPassword,
                ':new_pass' => $newPassHash,
                ':new_nama' => $newNama,
                ':uid' => $row['id']
            ]);
            $updatedCount++;
        }

        $pdo->commit();

        $formatLabel = ($formatType === 'no_hp') ? 'Nomor HP Orang Tua' : 'NIS Siswa';
        $passInfo = $resetPassword ? " dan password disamakan dengan username baru" : "";
        $msg = "🎉 Berhasil menyinkronkan <b>$updatedCount</b> akun Orang Tua menjadi format <b>$formatLabel</b>$passInfo.";
        if ($skippedCount > 0) {
            $msg .= " ($skippedCount akun dilewati karena data nomor HP/NIS belum diisi di master santri).";
        }
        redirect('index.php?role=ortu', 'success', $msg);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirect('index.php?role=ortu', 'danger', 'Gagal menyinkronkan username: ' . $e->getMessage());
    }
}

// ===== PROSES RESET PASSWORD KESELURUHAN OLEH ADMIN =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_all_passwords') {
    // Hanya admin yang diizinkan
    if (($_SESSION['role'] ?? '') !== 'admin') {
        redirect('index.php', 'danger', 'Hanya administrator yang memiliki izin untuk mereset password keseluruhan.');
    }

    $targetRole = $_POST['target_role'] ?? 'all'; // all, ortu, guru, bendahara
    $resetMode = $_POST['reset_mode'] ?? 'fixed'; // fixed, username
    $newPassword = trim($_POST['new_password'] ?? '');
    $excludeSelf = isset($_POST['exclude_self']) ? (int) $_POST['exclude_self'] : 1;
    $currentUserId = (int) ($_SESSION['user_id'] ?? 0);

    if ($resetMode === 'fixed' && empty($newPassword)) {
        redirect('index.php', 'danger', 'Password baru tidak boleh kosong.');
    }

    try {
        $pdo->beginTransaction();

        $where = ["1=1"];
        $params = [];

        if (in_array($targetRole, ['admin', 'bendahara', 'guru', 'kantin', 'ortu'])) {
            $where[] = "role = :r";
            $params[':r'] = $targetRole;
        }

        if ($excludeSelf && $currentUserId > 0) {
            $where[] = "id != :current_uid";
            $params[':current_uid'] = $currentUserId;
        }

        $whereSql = implode(" AND ", $where);
        $stmtUsers = $pdo->prepare("SELECT id, username, role, siswa_id FROM users WHERE $whereSql");
        $stmtUsers->execute($params);
        $targetUsers = $stmtUsers->fetchAll();

        $affectedCount = 0;
        if (!empty($targetUsers)) {
            if ($resetMode === 'fixed') {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $userIds = array_column($targetUsers, 'id');
                $inQuery = implode(',', array_fill(0, count($userIds), '?'));
                $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE id IN ($inQuery)");
                $stmtUpdate->execute(array_merge([$hash], $userIds));
                $affectedCount = count($userIds);
            } else {
                // Reset to username masing-masing
                $stmtUpdate = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id");
                foreach ($targetUsers as $tu) {
                    $passValue = $tu['username'];
                    $hash = password_hash($passValue, PASSWORD_DEFAULT);
                    $stmtUpdate->execute([':p' => $hash, ':id' => $tu['id']]);
                    $affectedCount++;
                }
            }
        }

        $pdo->commit();

        $targetDesc = ($targetRole === 'all') ? 'seluruh pengguna' : "seluruh pengguna dengan role " . strtoupper($targetRole);
        $modeDesc = ($resetMode === 'fixed') ? "password default '<b>" . htmlspecialchars($newPassword) . "</b>'" : "username masing-masing";
        redirect('index.php' . ($targetRole !== 'all' ? '?role=' . $targetRole : ''), 'success', "🎉 Berhasil mereset password <b>$affectedCount</b> akun ($targetDesc) menjadi $modeDesc.");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirect('index.php', 'danger', 'Gagal mereset password: ' . $e->getMessage());
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $id = (int) ($_POST['id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $role = trim(strtolower($_POST['role'] ?? 'bendahara'));
    $password = $_POST['password'] ?? '';
    $siswaId = ($role === 'ortu' && !empty($_POST['siswa_id'])) ? (int) $_POST['siswa_id'] : null;

    if ($id && $username && $nama) {
        try {
            // Ambil role lama sebelum update
            $stmtOld = $pdo->prepare("SELECT role FROM users WHERE id = :id");
            $stmtOld->execute([':id' => $id]);
            $oldRole = strtolower($stmtOld->fetchColumn() ?: '');

            $isActive = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;

            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username=:u, password=:p, nama_lengkap=:n, role=:r, siswa_id=:sid, is_active=:act WHERE id=:id");
                $stmt->execute([':u' => $username, ':p' => $hash, ':n' => $nama, ':r' => $role, ':sid' => $siswaId, ':act' => $isActive, ':id' => $id]);
                if ($id === (int) ($_SESSION['user_id'] ?? 0)) {
                    $_SESSION['auth_password_hash'] = $hash;
                }
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username=:u, nama_lengkap=:n, role=:r, siswa_id=:sid, is_active=:act WHERE id=:id");
                $stmt->execute([':u' => $username, ':n' => $nama, ':r' => $role, ':sid' => $siswaId, ':act' => $isActive, ':id' => $id]);
            }

            // Jika role berubah, sesuaikan permissions dan detail tambahan
            if ($oldRole !== $role) {
                // 1. Jika diubah ke Guru, pastikan ada baris di guru_detail
                if ($role === 'guru') {
                    $pdo->prepare("INSERT IGNORE INTO guru_detail (user_id) VALUES (:uid)")->execute([':uid' => $id]);
                }

                // 2. Reset permissions ke default role baru
                require_once __DIR__ . '/../../config/auth_functions.php';
                $allPerms = getAllPermissions();
                $defaults = getDefaultPermissions($role);

                // Hapus permissions lama & masukkan permission sesuai role baru
                $pdo->prepare("DELETE FROM user_permissions WHERE user_id = :uid")->execute([':uid' => $id]);
                $stmtInsPerm = $pdo->prepare("INSERT INTO user_permissions (user_id, permission_key, is_allowed) VALUES (:uid, :pkey, :allowed)");
                foreach (array_keys($allPerms) as $perm) {
                    $allowed = in_array($perm, $defaults) ? 1 : 0;
                    $stmtInsPerm->execute([':uid' => $id, ':pkey' => $perm, ':allowed' => $allowed]);
                }
            }

            // Jika user mengedit akunnya sendiri yang sedang login, update session
            if ($id === (int) ($_SESSION['user_id'] ?? 0)) {
                $_SESSION['role'] = $role;
                $_SESSION['nama_lengkap'] = $nama;
                $_SESSION['username'] = $username;
                require_once __DIR__ . '/../../config/auth_functions.php';
                $_SESSION['permissions'] = loadUserPermissions($id);
            }

            redirect('index.php?role=' . $role, 'success', "User '$username' berhasil diperbarui (Role: " . strtoupper($role) . ").");
        } catch (PDOException $e) {
            redirect('index.php?role=' . $role, 'danger', 'Gagal memperbarui user: ' . $e->getMessage());
        }
    }
}

// ===== PROSES UPDATE PERMISSIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_permissions') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $selectedPerms = $_POST['permissions'] ?? [];
    $role = $_POST['role'] ?? 'all';

    if ($userId > 0) {
        try {
            $pdo->beginTransaction();

            // Hapus izin lama
            $stmtDel = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = :uid");
            $stmtDel->execute([':uid' => $userId]);

            // Simpan izin baru
            require_once __DIR__ . '/../../config/auth_functions.php';
            $allPerms = getAllPermissions();
            $stmtInsert = $pdo->prepare("INSERT INTO user_permissions (user_id, permission_key, is_allowed) VALUES (:uid, :pkey, :allowed)");

            foreach (array_keys($allPerms) as $perm) {
                $allowed = in_array($perm, $selectedPerms) ? 1 : 0;
                $stmtInsert->execute([
                    ':uid' => $userId,
                    ':pkey' => $perm,
                    ':allowed' => $allowed
                ]);
            }

            $pdo->commit();

            // Jika user mengupdate izin dirinya sendiri, refresh session
            if ($userId === (int) ($_SESSION['user_id'] ?? 0)) {
                $_SESSION['permissions'] = loadUserPermissions($userId);
            }

            redirect('index.php?role=' . $role, 'success', 'Hak akses user berhasil diperbarui.');
        } catch (Exception $e) {
            if ($pdo->inTransaction())
                $pdo->rollBack();
            redirect('index.php?role=' . $role, 'danger', 'Gagal memperbarui hak akses: ' . $e->getMessage());
        }
    }
}

// ===== PROSES HAPUS USER =====
if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];

    // Jangan hapus diri sendiri
    if ($deleteId === (int) ($_SESSION['user_id'] ?? 0)) {
        redirect('index.php', 'danger', 'Tidak bisa menghapus akun sendiri.');
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute([':id' => $deleteId]);

            if ($stmt->rowCount() > 0) {
                redirect('index.php', 'success', 'User berhasil dihapus.');
            } else {
                redirect('index.php', 'warning', 'User tidak ditemukan atau sudah dihapus.');
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000' || strpos($e->getMessage(), '1217') !== false || strpos($e->getMessage(), '1451') !== false) {
                redirect('index.php', 'danger', 'User tidak bisa dihapus karena telah tercatat dalam transaksi (SPP, Uang Pangkal, atau Kas). Silakan nonaktifkan saja jika perlu.');
            } else {
                redirect('index.php', 'danger', 'Gagal menghapus user: ' . $e->getMessage());
            }
        }
    }
}

// ===== PROSES TOGGLE STATUS AKTIF / NONAKTIF USER =====
if (isset($_GET['toggle_status'])) {
    $toggleId = (int) $_GET['toggle_status'];
    $currentRole = $_GET['role'] ?? 'all';
    $currentKelas = (int) ($_GET['kelas_id'] ?? 0);

    // Jangan nonaktifkan akun sendiri
    if ($toggleId === (int) ($_SESSION['user_id'] ?? 0)) {
        redirect('index.php?role=' . $currentRole . '&kelas_id=' . $currentKelas, 'danger', 'Tidak dapat menonaktifkan akun sendiri yang sedang aktif digunakan.');
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET is_active = IF(COALESCE(is_active, 1) = 1, 0, 1) WHERE id = :id");
            $stmt->execute([':id' => $toggleId]);

            $stmtUname = $pdo->prepare("SELECT username, COALESCE(is_active, 1) AS is_active FROM users WHERE id = :id");
            $stmtUname->execute([':id' => $toggleId]);
            $uData = $stmtUname->fetch();

            $statusStr = ($uData && (int) $uData['is_active'] === 1) ? 'diaktifkan kembali' : 'dinonaktifkan';
            $alertType = ($uData && (int) $uData['is_active'] === 1) ? 'success' : 'warning';
            redirect('index.php?role=' . $currentRole . '&kelas_id=' . $currentKelas, $alertType, "Akun '<b>" . htmlspecialchars($uData['username'] ?? '') . "</b>' berhasil $statusStr.");
        } catch (Exception $e) {
            redirect('index.php?role=' . $currentRole . '&kelas_id=' . $currentKelas, 'danger', 'Gagal mengubah status akun: ' . $e->getMessage());
        }
    }
}

// ===== FILTER ROLE, KELAS, STATUS, LOGIN & SEARCH =====
$filterRole = $_GET['role'] ?? 'all';
$filterKelas = isset($_GET['kelas_id']) ? (int) $_GET['kelas_id'] : 0;
$filterStatus = $_GET['status'] ?? 'all'; // 'all', 'aktif', 'nonaktif'
$filterLogin = $_GET['login'] ?? 'all'; // 'all', 'ever', 'never'
$search = trim($_GET['search'] ?? ($_GET['q'] ?? ''));
$validRoles = ['admin', 'bendahara', 'guru', 'kantin', 'ortu'];

// Fetch daftar kelas untuk filter dropdown
$classList = $pdo->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC")->fetchAll();

$query = "
    SELECT u.id, u.username, u.nama_lengkap, u.role, u.last_login, u.created_at, u.siswa_id,
           COALESCE(u.is_active, 1) AS is_active,
           s.nama AS nama_siswa, s.nis, s.no_hp_ortu, k.nama_kelas, s.kelas_id
    FROM users u
    LEFT JOIN siswa s ON u.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    WHERE 1=1
";
$params = [];

if (in_array($filterRole, $validRoles)) {
    $query .= " AND u.role = :r";
    $params[':r'] = $filterRole;
}

if ($filterKelas > 0) {
    $query .= " AND s.kelas_id = :kid";
    $params[':kid'] = $filterKelas;
}

if ($filterStatus === 'aktif' || $filterStatus === '1') {
    $query .= " AND COALESCE(u.is_active, 1) = 1";
} elseif ($filterStatus === 'nonaktif' || $filterStatus === '0') {
    $query .= " AND COALESCE(u.is_active, 1) = 0";
}

if ($filterLogin === 'ever') {
    $query .= " AND u.last_login IS NOT NULL";
} elseif ($filterLogin === 'never') {
    $query .= " AND u.last_login IS NULL";
}

if (!empty($search)) {
    $query .= " AND (u.username LIKE :q OR u.nama_lengkap LIKE :q OR s.nama LIKE :q OR s.nis LIKE :q OR s.no_hp_ortu LIKE :q)";
    $params[':q'] = "%$search%";
}

$query .= " ORDER BY COALESCE(u.is_active, 1) DESC, FIELD(u.role, 'admin', 'bendahara', 'guru', 'kantin', 'ortu') ASC, u.nama_lengkap ASC";
$stmtUser = $pdo->prepare($query);
$stmtUser->execute($params);
$userList = $stmtUser->fetchAll();

$isAnyFilterActive = (!empty($search) || $filterStatus !== 'all' || $filterKelas > 0 || $filterLogin !== 'all');

// Hitung santri yang belum punya akun Ortu
$countUnlinkedOrangTua = 0;
if ($filterRole === 'all' || $filterRole === 'ortu') {
    $countUnlinkedOrangTua = (int) $pdo->query("
        SELECT COUNT(*) 
        FROM siswa 
        WHERE nis IS NOT NULL AND nis != ''
          AND id NOT IN (SELECT siswa_id FROM users WHERE role = 'ortu' AND siswa_id IS NOT NULL)
          AND nis NOT IN (SELECT username FROM users WHERE role = 'ortu')
    ")->fetchColumn();
}

// Hitung jumlah user per role untuk badge modern filter
$roleCounts = [
    'all' => (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'admin' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
    'bendahara' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'bendahara'")->fetchColumn(),
    'guru' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'guru'")->fetchColumn(),
    'kantin' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'kantin'")->fetchColumn(),
    'ortu' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'ortu'")->fetchColumn(),
];

// Ambil seluruh siswa untuk pilihan relasi akun orang tua
$allSiswaList = $pdo->query("
    SELECT s.id, s.nis, s.nama, k.nama_kelas 
    FROM siswa s 
    LEFT JOIN kelas k ON s.kelas_id = k.id 
    WHERE s.status = 'aktif' 
    ORDER BY s.nama ASC
")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- TAB FILTER ROLE, KELAS & ACTION TOOLBAR -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3">
        <!-- Baris 1: Filter Role & Action Buttons -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
            <!-- Filter Role -->
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="modern-filter-nav">
                    <a class="modern-filter-btn <?= $filterRole === 'all' ? 'active' : '' ?>"
                        href="index.php?role=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $filterStatus !== 'all' ? '&status=' . urlencode($filterStatus) : '' ?><?= $filterKelas > 0 ? '&kelas_id=' . $filterKelas : '' ?><?= $filterLogin !== 'all' ? '&login=' . urlencode($filterLogin) : '' ?>">
                        Semua <span class="modern-filter-badge"><?= $roleCounts['all'] ?></span>
                    </a>
                    <a class="modern-filter-btn <?= $filterRole === 'admin' ? 'active' : '' ?>"
                        href="index.php?role=admin<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $filterStatus !== 'all' ? '&status=' . urlencode($filterStatus) : '' ?>">
                        Admin <span class="modern-filter-badge"><?= $roleCounts['admin'] ?></span>
                    </a>
                    <a class="modern-filter-btn <?= $filterRole === 'bendahara' ? 'active' : '' ?>"
                        href="index.php?role=bendahara<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $filterStatus !== 'all' ? '&status=' . urlencode($filterStatus) : '' ?>">
                        Bendahara <span class="modern-filter-badge"><?= $roleCounts['bendahara'] ?></span>
                    </a>
                    <a class="modern-filter-btn <?= $filterRole === 'guru' ? 'active' : '' ?>"
                        href="index.php?role=guru<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $filterStatus !== 'all' ? '&status=' . urlencode($filterStatus) : '' ?>">
                        Guru <span class="modern-filter-badge"><?= $roleCounts['guru'] ?></span>
                    </a>
                    <a class="modern-filter-btn <?= $filterRole === 'kantin' ? 'active' : '' ?>"
                        href="index.php?role=kantin<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $filterStatus !== 'all' ? '&status=' . urlencode($filterStatus) : '' ?>">
                        Kantin <span class="modern-filter-badge"><?= $roleCounts['kantin'] ?></span>
                    </a>
                    <a class="modern-filter-btn <?= $filterRole === 'ortu' ? 'active' : '' ?>"
                        href="index.php?role=ortu<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $filterStatus !== 'all' ? '&status=' . urlencode($filterStatus) : '' ?><?= $filterKelas > 0 ? '&kelas_id=' . $filterKelas : '' ?>">
                        Orang Tua (Ortu) <span class="modern-filter-badge"><?= $roleCounts['ortu'] ?></span>
                    </a>
                </div>
            </div>

            <!-- Action Buttons Group (Rapi & Selaras) -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <!-- Edit Massal Akun (Spreadsheet) -->
                    <a href="edit_massal.php?role=<?= htmlspecialchars($filterRole) ?>&kelas_id=<?= $filterKelas ?>"
                        class="btn btn-outline-primary fw-semibold d-flex align-items-center gap-2 px-3 py-2 rounded-3">
                        <i class="bi bi-pencil-square"></i> Edit Massal
                    </a>

                    <!-- Dropdown Alat Akun Massal -->
                    <div class="dropdown">
                        <button
                            class="btn btn-light border fw-semibold d-flex align-items-center gap-2 px-3 py-2 rounded-3 dropdown-toggle"
                            type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-gear-wide-connected text-secondary"></i> Alat Akun
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 p-2"
                            style="min-width: 240px;">
                            <li>
                                <h6 class="dropdown-header text-uppercase small fw-bold text-muted px-2 py-1">Aksi &
                                    Sinkronisasi</h6>
                            </li>
                            <li>
                                <a class="dropdown-item rounded-2 d-flex align-items-center gap-2 py-2 text-primary"
                                    href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalSyncUsername">
                                    <i class="bi bi-arrow-repeat fs-6"></i>
                                    <div>
                                        <div class="fw-semibold">Format Username Ortu</div>
                                        <small class="text-muted" style="font-size: 0.72rem;">Ubah ke No HP Wali / NIS
                                            Santri</small>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <hr class="dropdown-divider my-1">
                            </li>
                            <li>
                                <a class="dropdown-item rounded-2 d-flex align-items-center gap-2 py-2 text-danger"
                                    href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalResetAll">
                                    <i class="bi bi-shield-lock-fill fs-6"></i>
                                    <div>
                                        <div class="fw-semibold">Reset Password Keseluruhan</div>
                                        <small class="text-muted" style="font-size: 0.72rem;">Setel password serentak per
                                            role</small>
                                    </div>
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Tambah User Baru -->
                <button class="btn btn-primary fw-bold d-flex align-items-center gap-2 px-3 py-2 rounded-3 shadow-sm"
                    data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="bi bi-person-plus-fill"></i> Tambah User
                </button>
            </div>
        </div>

        <!-- Baris 2: Toolbar Pencarian & Multi-Filter Terpadu -->
        <div class="pt-3 border-top">
            <form id="filterForm" method="GET" action="index.php" class="m-0">
                <input type="hidden" name="role" id="filterRoleHidden" value="<?= htmlspecialchars($filterRole) ?>">

                <div class="row g-2 align-items-center">
                    <!-- Search Box -->
                    <div class="col-12 col-md-4 col-lg-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-2 border-end-0 text-muted px-2.5">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" name="search" id="userSearchInput"
                                class="form-control form-control-sm border-2 border-start-0 ps-1"
                                placeholder="Cari username, nama, NIS, wali..." value="<?= htmlspecialchars($search) ?>"
                                autocomplete="off">
                            <button
                                class="btn btn-white border-2 border-start-0 text-muted px-2 <?= empty($search) ? 'd-none' : '' ?>"
                                type="button" id="btnClearSearch" onclick="clearUserSearch()" title="Hapus pencarian">
                                <i class="bi bi-x-circle-fill text-secondary"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Filter Status -->
                    <div class="col-6 col-md-2 col-lg-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted border-2 border-end-0 small px-2">
                                <i class="bi bi-toggle2-on"></i>
                            </span>
                            <select name="status" id="filterStatusSelect"
                                class="form-select form-select-sm border-2 border-start-0 rounded-end-3"
                                onchange="onFilterChange()">
                                <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Semua Status</option>
                                <option value="aktif" <?= ($filterStatus === 'aktif' || $filterStatus === '1') ? 'selected' : '' ?>>Aktif</option>
                                <option value="nonaktif" <?= ($filterStatus === 'nonaktif' || $filterStatus === '0') ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                        </div>
                    </div>

                    <!-- Filter Kelas (Khusus Ortu / Umum) -->
                    <div class="col-6 col-md-2 col-lg-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted border-2 border-end-0 small px-2">
                                <i class="bi bi-door-closed"></i>
                            </span>
                            <select name="kelas_id" id="filterKelasSelect"
                                class="form-select form-select-sm border-2 border-start-0 rounded-end-3"
                                onchange="onFilterChange()">
                                <option value="0">Semua Kelas</option>
                                <?php foreach ($classList as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $filterKelas === (int) $c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['nama_kelas']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Filter Status Login -->
                    <div class="col-6 col-md-2 col-lg-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted border-2 border-end-0 small px-2">
                                <i class="bi bi-clock-history"></i>
                            </span>
                            <select name="login" id="filterLoginSelect"
                                class="form-select form-select-sm border-2 border-start-0 rounded-end-3"
                                onchange="onFilterChange()">
                                <option value="all" <?= $filterLogin === 'all' ? 'selected' : '' ?>>Semua Login</option>
                                <option value="ever" <?= $filterLogin === 'ever' ? 'selected' : '' ?>>Pernah Login</option>
                                <option value="never" <?= $filterLogin === 'never' ? 'selected' : '' ?>>Belum Pernah
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Tombol Reset Filter -->
                    <div class="col-6 col-md-2 col-lg-2 d-flex align-items-center gap-1">
                        <button type="submit"
                            class="btn btn-sm btn-primary fw-semibold rounded-3 flex-fill d-flex align-items-center justify-content-center gap-1.5 py-1.5">
                            <i class="bi bi-funnel"></i> Terapkan
                        </button>
                        <?php if ($isAnyFilterActive): ?>
                            <a href="index.php?role=<?= htmlspecialchars($filterRole) ?>"
                                class="btn btn-sm btn-outline-danger rounded-3 px-2 py-1.5" title="Reset Semua Filter">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ALERT BULK GENERATE ACCOUNTS -->
<?php if ($countUnlinkedOrangTua > 0): ?>
    <div
        class="alert alert-info border-0 shadow-sm rounded-4 p-3 d-flex align-items-center justify-content-between gap-3 mb-4 flex-wrap">
        <div>
            <h6 class="mb-1 fw-bold text-primary-emphasis"><i class="bi bi-person-plus-fill"></i> Sinkronisasi Massal Akun
                Orang Tua (Ortu)</h6>
            <p class="mb-0 text-muted small">Terdapat <strong><?= $countUnlinkedOrangTua ?></strong> santri aktif yang belum
                memiliki akun masuk Orang Tua / Wali Murid.</p>
        </div>
        <form method="POST" class="m-0">
            <input type="hidden" name="action" value="bulk_generate_ortu">
            <button type="submit" class="btn btn-sm btn-primary fw-bold px-3 py-1.5 shadow"
                onclick="return confirm('Buat otomatis <?= $countUnlinkedOrangTua ?> akun Ortu dengan password default menggunakan NIS?')"><i
                    class="bi bi-magic"></i> Buat Akun Otomatis</button>
        </form>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <p class="text-muted mb-0">
            Menampilkan: <strong id="displayedUserCount"><?= count($userList) ?></strong> user
            <?php if ($filterRole !== 'all'): ?>
                (Role: <strong><?= strtoupper($filterRole) ?></strong>)
            <?php endif; ?>
        </p>
        <?php if ($isAnyFilterActive): ?>
            <span
                class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-2 py-1 small">
                <i class="bi bi-funnel-fill me-1"></i> Filter Aktif
            </span>
        <?php endif; ?>
    </div>
</div>

<form id="bulkActionForm" action="bulk_action.php" method="POST">
    <input type="hidden" name="bulk_action_type" id="bulkActionType" value="">

    <div class="table-card">
        <div class="table-responsive">
            <table class="data-table" id="tableUsers">
                <thead>
                    <tr>
                        <th style="width: 40px;" class="text-center">
                            <input type="checkbox" id="selectAll" class="bulk-checkbox" title="Pilih Semua">
                        </th>
                        <th>No</th>
                        <th>Username</th>
                        <th>Nama Lengkap</th>
                        <th>Role</th>
                        <th class="text-center">Status</th>
                        <th>Login Terakhir</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="userTableBody">
                    <?php if (empty($userList)): ?>
                        <tr id="emptyServerRow">
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block text-secondary opacity-50 mb-2"></i>
                                Belum ada user dengan kriteria filter ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($userList as $i => $u): ?>
                            <?php $isActive = (int) ($u['is_active'] ?? 1); ?>
                            <tr class="user-row <?= $isActive === 0 ? 'table-light opacity-75' : '' ?>"
                                data-username="<?= strtolower(htmlspecialchars($u['username'])) ?>"
                                data-nama="<?= strtolower(htmlspecialchars($u['nama_lengkap'])) ?>"
                                data-siswa="<?= strtolower(htmlspecialchars($u['nama_siswa'] ?? '')) ?>"
                                data-nis="<?= strtolower(htmlspecialchars($u['nis'] ?? '')) ?>"
                                data-nohp="<?= strtolower(htmlspecialchars($u['no_hp_ortu'] ?? '')) ?>"
                                data-role="<?= strtolower(htmlspecialchars($u['role'])) ?>"
                                data-status="<?= $isActive === 1 ? 'aktif' : 'nonaktif' ?>"
                                data-kelas="<?= (int) ($u['kelas_id'] ?? 0) ?>"
                                data-login="<?= !empty($u['last_login']) ? 'ever' : 'never' ?>">
                                <td class="text-center">
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <input type="checkbox" name="ids[]" value="<?= $u['id'] ?>"
                                            class="bulk-checkbox bulk-item-check"
                                            data-name="<?= htmlspecialchars($u['username']) ?>">
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border rounded-circle" title="Akun Anda"><i
                                                class="bi bi-person-fill"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted font-monospace row-number"><?= $i + 1 ?></td>
                                <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                                <td>
                                    <div class="table-avatar-item">
                                        <div class="table-avatar-circle <?= $isActive === 0 ? 'bg-secondary' : '' ?>">
                                            <?= strtoupper(substr($u['nama_lengkap'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong
                                                class="d-block text-dark"><?= htmlspecialchars($u['nama_lengkap']) ?></strong>
                                            <?php if ($u['role'] === 'ortu' && !empty($u['nama_siswa'])): ?>
                                                <small class="text-muted d-block mt-1" style="font-size: 0.73rem;">
                                                    <i class="bi bi-person-heart text-danger me-1"></i> Wali dari:
                                                    <strong><?= htmlspecialchars($u['nama_siswa']) ?></strong>
                                                    <span
                                                        class="badge bg-light text-secondary border rounded-pill ms-1 font-monospace"
                                                        style="font-size: 0.65rem;"><?= htmlspecialchars($u['nama_kelas'] ?? '-') ?></span>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $roleClass = 'badge-aktif';
                                    if ($u['role'] === 'admin')
                                        $roleClass = 'bg-danger text-white';
                                    elseif ($u['role'] === 'bendahara')
                                        $roleClass = 'bg-primary text-white';
                                    elseif ($u['role'] === 'guru')
                                        $roleClass = 'bg-success text-white';
                                    elseif ($u['role'] === 'kantin')
                                        $roleClass = 'bg-warning text-dark';
                                    elseif ($u['role'] === 'ortu')
                                        $roleClass = 'bg-info text-dark';
                                    ?>
                                    <span class="badge <?= $roleClass ?> px-2.5 py-1.5 rounded-3 font-monospace"
                                        style="font-size: 0.72rem; letter-spacing: 0.05em;"><?= strtoupper($u['role']) ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($isActive === 1): ?>
                                        <span
                                            class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2.5 py-1 rounded-pill small">
                                            <i class="bi bi-check-circle-fill"></i> Aktif
                                        </span>
                                    <?php else: ?>
                                        <span
                                            class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2.5 py-1 rounded-pill small">
                                            <i class="bi bi-slash-circle-fill"></i> Nonaktif
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $u['last_login'] ? formatTanggal($u['last_login']) : '<span class="text-muted">Belum pernah</span>' ?>
                                </td>
                                <td>
                                    <button type="button" class="btn-sm-action btn-edit"
                                        onclick='editUser(<?= htmlspecialchars(json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>)'
                                        title="Edit User"><i class="bi bi-pencil"></i></button>
                                    <button type="button" class="btn-sm-action btn-permission"
                                        onclick='editPermissions(<?= htmlspecialchars(json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>)'
                                        title="Kelola Hak Akses"><i class="bi bi-shield-lock-fill"></i></button>
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <a href="index.php?toggle_status=<?= $u['id'] ?>&role=<?= htmlspecialchars($filterRole) ?>&kelas_id=<?= $filterKelas ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                                            class="btn-sm-action <?= $isActive === 1 ? 'text-warning' : 'text-success' ?>"
                                            title="<?= $isActive === 1 ? 'Nonaktifkan Akun' : 'Aktifkan Akun' ?>"
                                            onclick="return confirm('Apakah Anda yakin ingin <?= $isActive === 1 ? 'MENONAKTIFKAN' : 'MENGAKTIFKAN' ?> user <?= addslashes(htmlspecialchars($u['username'])) ?>?')">
                                            <i class="bi <?= $isActive === 1 ? 'bi-person-slash' : 'bi-person-check-fill' ?>"></i>
                                        </a>
                                        <button type="button"
                                            onclick="confirmDelete('index.php?delete=<?= $u['id'] ?>','<?= addslashes(htmlspecialchars($u['username'])) ?>')"
                                            class="btn-sm-action btn-delete" title="Hapus User">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr id="noLiveMatchRow" class="d-none">
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-search fs-3 d-block text-secondary opacity-50 mb-2"></i>
                            Tidak ada user yang sesuai dengan kriteria pencarian / filter ini.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FLOATING ACTION TOOLBAR -->
    <div class="bulk-floating-bar" id="bulkFloatingBar">
        <div class="d-flex align-items-center gap-2">
            <span class="bulk-count-badge" id="bulkCountBadge">
                <i class="bi bi-check2-circle"></i> <span>0 user dipilih</span>
            </span>
        </div>

        <div class="bulk-actions-group">
            <button type="button" class="btn-bulk-action btn-bulk-secondary"
                style="background: #10b981; border-color: #10b981; color: #fff;" onclick="handleBulkActivateUsers()">
                <i class="bi bi-person-check-fill"></i> Aktifkan
            </button>
            <button type="button" class="btn-bulk-action btn-bulk-secondary"
                style="background: #f59e0b; border-color: #f59e0b; color: #fff;" onclick="handleBulkDeactivateUsers()">
                <i class="bi bi-person-slash"></i> Nonaktifkan
            </button>
            <button type="button" class="btn-bulk-action btn-bulk-secondary" onclick="handleBulkResetPassword()">
                <i class="bi bi-key-fill"></i> Reset Password
            </button>
            <button type="button" class="btn-bulk-action btn-bulk-delete" onclick="handleBulkDeleteUsers()">
                <i class="bi bi-trash-fill"></i> Hapus User
            </button>
            <button type="button" class="btn-bulk-cancel" title="Batalkan Pilihan">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>
</form>

<!-- Modal Tambah User -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus"></i> Tambah User</h5><button type="button"
                        class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Username *</label><input type="text" name="username"
                            class="form-control" required autocomplete="off"></div>
                    <div class="mb-3"><label class="form-label">Password *</label><input type="password" name="password"
                            class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Nama Lengkap *</label><input type="text"
                            name="nama_lengkap" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Role</label>
                        <select name="role" id="createRole" class="form-select"
                            onchange="toggleSiswaSelect('create', this.value)">
                            <option value="bendahara" <?= $filterRole === 'bendahara' ? 'selected' : '' ?>>Bendahara
                            </option>
                            <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="guru" <?= $filterRole === 'guru' ? 'selected' : '' ?>>Guru</option>
                            <option value="kantin" <?= $filterRole === 'kantin' ? 'selected' : '' ?>>Kantin (Kasir /
                                Petugas)</option>
                            <option value="ortu" <?= $filterRole === 'ortu' ? 'selected' : '' ?>>Orang Tua (Ortu)</option>
                        </select>
                    </div>
                    <div class="mb-3 <?= $filterRole === 'ortu' ? '' : 'd-none' ?>" id="createSiswaContainer">
                        <label class="form-label fw-bold text-primary"><i class="bi bi-person-heart me-1"></i> Hubungkan
                            ke Data Santri/Siswa</label>
                        <select name="siswa_id" id="createSiswaId" class="form-select">
                            <option value="">-- Pilih Santri/Siswa --</option>
                            <?php foreach ($allSiswaList as $as): ?>
                                <option value="<?= $as['id'] ?>">
                                    <?= htmlspecialchars($as['nama']) ?> (NIS: <?= htmlspecialchars($as['nis'] ?: '-') ?>
                                    &bull; <?= htmlspecialchars($as['nama_kelas'] ?? '-') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Akun orang tua ini akan otomatis mengelola data santri yang
                            dipilih.</small>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light"
                        data-bs-dismiss="modal">Batal</button><button type="submit"
                        class="btn-primary-custom">Simpan</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit User -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editUserId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit User</h5><button type="button"
                        class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Username *</label><input type="text" name="username"
                            id="editUsername" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Password <small class="text-muted">(Kosongkan jika tidak
                                diubah)</small></label><input type="password" name="password" class="form-control"
                            placeholder="Kosongkan jika tidak diubah"></div>
                    <div class="mb-3"><label class="form-label">Nama Lengkap *</label><input type="text"
                            name="nama_lengkap" id="editNamaUser" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Role</label>
                        <select name="role" id="editRole" class="form-select"
                            onchange="toggleSiswaSelect('edit', this.value)">
                            <option value="bendahara">Bendahara</option>
                            <option value="admin">Admin</option>
                            <option value="guru">Guru</option>
                            <option value="kantin">Kantin (Kasir / Petugas)</option>
                            <option value="ortu">Orang Tua (Ortu)</option>
                        </select>
                    </div>
                    <div class="mb-3 d-none" id="editSiswaContainer">
                        <label class="form-label fw-bold text-primary"><i class="bi bi-person-heart me-1"></i> Hubungkan
                            ke Data Santri/Siswa</label>
                        <select name="siswa_id" id="editSiswaId" class="form-select">
                            <option value="">-- Pilih Santri/Siswa --</option>
                            <?php foreach ($allSiswaList as $as): ?>
                                <option value="<?= $as['id'] ?>">
                                    <?= htmlspecialchars($as['nama']) ?> (NIS: <?= htmlspecialchars($as['nis'] ?: '-') ?>
                                    &bull; <?= htmlspecialchars($as['nama_kelas'] ?? '-') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Akun orang tua ini akan otomatis mengelola data santri yang
                            dipilih.</small>
                    </div>

                    <div class="mb-3 p-3 bg-light border rounded-3">
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive" value="1"
                                checked>
                            <label class="form-check-label fw-bold text-dark" for="editIsActive">
                                Status Akun Aktif
                            </label>
                            <div class="text-muted small">Jika dinonaktifkan, pengguna tidak akan bisa login ke dalam
                                aplikasi.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light"
                        data-bs-dismiss="modal">Batal</button><button type="submit"
                        class="btn-primary-custom">Simpan</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Kelola Hak Akses -->
<div class="modal fade" id="modalPermissions" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_permissions">
                <input type="hidden" name="user_id" id="permUserId">
                <input type="hidden" name="role" value="<?= htmlspecialchars($filterRole) ?>">

                <div class="modal-header bg-light">
                    <div>
                        <h5 class="modal-title fw-bold text-dark"><i class="bi bi-shield-lock-fill text-primary"></i>
                            Kelola Hak Akses</h5>
                        <small class="text-muted" id="permUserTitle">User: -</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body bg-white" style="max-height: 70vh; overflow-y: auto;">
                    <!-- Shortcut Buttons -->
                    <div class="d-flex gap-2 mb-3 pb-3 border-bottom flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleAllPerms(true)"><i
                                class="bi bi-check-all"></i> Pilih Semua</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                            onclick="toggleAllPerms(false)"><i class="bi bi-x"></i> Kosongkan Semua</button>
                        <button type="button" class="btn btn-sm btn-outline-warning text-dark"
                            onclick="resetToRoleDefault()"><i class="bi bi-arrow-counterclockwise"></i> Reset ke Default
                            Role</button>
                    </div>

                    <div id="permLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted small">Memuat hak akses...</p>
                    </div>

                    <div id="permContent" class="permission-grid d-none">
                        <?php
                        require_once __DIR__ . '/../../config/auth_functions.php';
                        $allPerms = getAllPermissions();

                        // Group permissions by 'group' field
                        $groupedPerms = [];
                        foreach ($allPerms as $pkey => $info) {
                            $groupName = $info['group'] ?? 'Lainnya';
                            $groupedPerms[$groupName][$pkey] = $info;
                        }

                        // Icon map for groups
                        $groupIcons = [
                            'Menu Utama' => 'bi-grid-fill',
                            'Transaksi' => 'bi-cash-stack',
                            'Kantin Sekolah' => 'bi-shop',
                            'Master Data' => 'bi-database-fill',
                            'Akademik & Guru' => 'bi-journal-text',
                            'Laporan' => 'bi-file-earmark-bar-graph-fill',
                            'Pengaturan' => 'bi-gear-fill',
                        ];

                        foreach ($groupedPerms as $groupName => $perms):
                            $icon = $groupIcons[$groupName] ?? 'bi-folder-fill';
                            ?>
                            <div class="permission-group-card">
                                <div class="permission-group-title">
                                    <i class="bi <?= $icon ?> text-primary"></i> <?= htmlspecialchars($groupName) ?>
                                </div>
                                <?php foreach ($perms as $pkey => $info): ?>
                                    <div class="permission-item">
                                        <span class="permission-label">
                                            <i class="bi <?= htmlspecialchars($info['icon'] ?? 'bi-dot') ?>"></i>
                                            <?= htmlspecialchars($info['label'] ?? $pkey) ?>
                                        </span>
                                        <div class="form-check form-switch m-0">
                                            <input class="form-check-input perm-checkbox-switch" type="checkbox"
                                                name="permissions[]" value="<?= htmlspecialchars($pkey) ?>"
                                                id="perm_<?= $pkey ?>">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan Hak
                        Akses</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL SINKRONISASI FORMAT USERNAME ORTU -->
<div class="modal fade" id="modalSyncUsername" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom bg-primary bg-opacity-10 px-4 py-3">
                <h6 class="modal-title fw-bold text-primary d-flex align-items-center gap-2">
                    <i class="bi bi-arrow-repeat fs-5"></i> Format & Sinkronisasi Username Ortu
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="bulk_sync_username_ortu">
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 rounded-3 small mb-3 d-flex gap-2">
                        <i class="bi bi-info-circle-fill fs-5 text-primary flex-shrink-0"></i>
                        <div>
                            Fitur ini memungkinkan Anda mengubah format username seluruh akun <strong>Orang Tua / Wali
                                Murid</strong> secara serentak dan fleksibel sesuai kebutuhan sekolah.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pilih Format Username Baru <span
                                class="text-danger">*</span></label>
                        <div class="d-flex flex-column gap-2">
                            <div class="form-check p-3 border rounded-3 bg-light">
                                <input class="form-check-input" type="radio" name="format_type" id="formatNoHp"
                                    value="no_hp" checked>
                                <label class="form-check-label fw-medium" for="formatNoHp">
                                    <strong><i class="bi bi-telephone-fill text-success me-1"></i> Gunakan Nomor HP
                                        Orang Tua / Wali</strong>
                                    <div class="text-muted small">Username diubah menjadi No. HP wali (misal:
                                        <code>081234567890</code>). Sangat memudahkan wali murid saat login.
                                    </div>
                                </label>
                            </div>
                            <div class="form-check p-3 border rounded-3 bg-light">
                                <input class="form-check-input" type="radio" name="format_type" id="formatNis"
                                    value="nis">
                                <label class="form-check-label fw-medium" for="formatNis">
                                    <strong><i class="bi bi-person-badge-fill text-primary me-1"></i> Gunakan NIS Santri
                                        / Siswa</strong>
                                    <div class="text-muted small">Username disamakan dengan NIS santri yang bersangkutan
                                        (misal: <code>20250721001</code>).</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-check form-switch p-3 border rounded-3 bg-light mt-3">
                        <input class="form-check-input ms-0 me-2" type="checkbox" name="reset_password_to_new_username"
                            value="1" id="syncResetPass" checked>
                        <label class="form-check-label fw-medium" for="syncResetPass">
                            <strong>Samakan Password dengan Username Baru</strong>
                            <div class="text-muted small">Password akun akan otomatis di-reset menjadi sama persis
                                dengan username baru (misal password = No HP atau NIS).</div>
                        </label>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4"
                        onclick="return confirm('Apakah Anda yakin ingin menyinkronkan seluruh username akun Orang Tua sekarang?')">
                        <i class="bi bi-check-circle me-1"></i> Jalankan Sinkronisasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL RESET PASSWORD KESELURUHAN -->
<div class="modal fade" id="modalResetAll" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom bg-danger bg-opacity-10 px-4 py-3">
                <h6 class="modal-title fw-bold text-danger d-flex align-items-center gap-2">
                    <i class="bi bi-shield-lock-fill fs-5"></i> Reset Password Keseluruhan
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="reset_all_passwords">
                <div class="modal-body p-4">
                    <div class="alert alert-warning border-0 rounded-3 small mb-3 d-flex gap-2">
                        <i class="bi bi-exclamation-triangle-fill fs-5 text-warning flex-shrink-0"></i>
                        <div>
                            <strong>Peringatan Penting:</strong> Fitur ini akan mereset password akun pengguna secara
                            massal/keseluruhan. Pastikan Anda menginformasikan perubahan password kepada pengguna
                            terkait.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Target Akun yang Direset <span
                                class="text-danger">*</span></label>
                        <select name="target_role" class="form-select border-2" id="resetTargetRole">
                            <option value="all">Semua Pengguna (Seluruh Akun)</option>
                            <option value="ortu" <?= $filterRole === 'ortu' ? 'selected' : '' ?>>Semua Orang Tua (Ortu /
                                Wali Murid)</option>
                            <option value="guru" <?= $filterRole === 'guru' ? 'selected' : '' ?>>Semua Guru</option>
                            <option value="bendahara" <?= $filterRole === 'bendahara' ? 'selected' : '' ?>>Semua Bendahara
                            </option>
                            <option value="kantin" <?= $filterRole === 'kantin' ? 'selected' : '' ?>>Semua Petugas Kantin
                            </option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Metode Reset Password <span
                                class="text-danger">*</span></label>
                        <div class="d-flex flex-column gap-2">
                            <div class="form-check p-3 border rounded-3 bg-light">
                                <input class="form-check-input" type="radio" name="reset_mode" id="modeFixed"
                                    value="fixed" checked onchange="toggleResetModeUI()">
                                <label class="form-check-label fw-medium" for="modeFixed">
                                    <strong>Setel Password Seragam / Standar</strong>
                                    <div class="text-muted small">Semua akun target akan memiliki password baru yang
                                        sama (contoh: <code>123456</code>).</div>
                                </label>
                            </div>
                            <div class="form-check p-3 border rounded-3 bg-light">
                                <input class="form-check-input" type="radio" name="reset_mode" id="modeUsername"
                                    value="username" onchange="toggleResetModeUI()">
                                <label class="form-check-label fw-medium" for="modeUsername">
                                    <strong>Samakan dengan Username / NIS Masing-masing</strong>
                                    <div class="text-muted small">Setiap akun akan direset dengan password sama persis
                                        dengan username/NIS login miliknya.</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3" id="fieldNewPassword">
                        <label class="form-label fw-semibold">Password Baru <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-key-fill text-muted"></i></span>
                            <input type="text" name="new_password" id="inputNewPassword" class="form-control"
                                value="123456" placeholder="Masukkan password baru">
                            <button type="button" class="btn btn-outline-secondary btn-sm px-3"
                                onclick="document.getElementById('inputNewPassword').value = '123456'">Default
                                123456</button>
                        </div>
                        <div class="form-text">Pengguna nantinya dapat mengganti password ini setelah berhasil login.
                        </div>
                    </div>

                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="exclude_self" value="1"
                            id="excludeSelfCheck" checked>
                        <label class="form-check-label small fw-medium" for="excludeSelfCheck">
                            Kecualikan akun saya saat ini
                            (<strong><?= htmlspecialchars($_SESSION['username'] ?? 'admin') ?></strong>)
                        </label>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger fw-bold px-4"
                        onclick="return confirm('⚠️ PERINGATAN KONFIRMASI:\n\nApakah Anda benar-benar yakin ingin mereset password akun pengguna terpilih sekarang?')">
                        <i class="bi bi-arrow-clockwise me-1"></i> Jalankan Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function toggleResetModeUI() {
        const modeFixed = document.getElementById('modeFixed');
        const fieldNewPass = document.getElementById('fieldNewPassword');
        if (modeFixed && fieldNewPass) {
            if (modeFixed.checked) {
                fieldNewPass.classList.remove('d-none');
            } else {
                fieldNewPass.classList.add('d-none');
            }
        }
    }

    function toggleSiswaSelect(type, role) {
        const container = document.getElementById(type + 'SiswaContainer');
        if (container) {
            if (role === 'ortu') {
                container.classList.remove('d-none');
            } else {
                container.classList.add('d-none');
            }
        }
    }

    function editUser(data) {
        document.getElementById('editUserId').value = data.id;
        document.getElementById('editUsername').value = data.username;
        document.getElementById('editNamaUser').value = data.nama_lengkap;
        document.getElementById('editRole').value = data.role;
        document.getElementById('editSiswaId').value = data.siswa_id || '';
        const editIsActive = document.getElementById('editIsActive');
        if (editIsActive) {
            editIsActive.checked = (parseInt(data.is_active || 1) === 1);
        }
        toggleSiswaSelect('edit', data.role);
        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    }

    let currentRoleDefaults = [];

    function editPermissions(user) {
        document.getElementById('permUserId').value = user.id;
        document.getElementById('permUserTitle').innerText = 'User: ' + user.nama_lengkap + ' (' + user.username + ') - Role: ' + user.role.toUpperCase();

        // Show loading
        document.getElementById('permLoading').classList.remove('d-none');
        document.getElementById('permContent').classList.add('d-none');

        // Open Modal
        const modal = new bootstrap.Modal(document.getElementById('modalPermissions'));
        modal.show();

        // Fetch permissions via AJAX
        fetch('<?= BASE_URL ?>/ajax/get_user_permissions.php?user_id=' + user.id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentRoleDefaults = data.defaults || [];

                    // Set checked state of switches
                    const checkboxes = document.querySelectorAll('.perm-checkbox-switch');
                    checkboxes.forEach(cb => {
                        const key = cb.value;
                        cb.checked = !!data.permissions[key];
                    });

                    // Hide loading, show content
                    document.getElementById('permLoading').classList.add('d-none');
                    document.getElementById('permContent').classList.remove('d-none');
                } else {
                    alert('Gagal memuat hak akses: ' + data.message);
                    modal.hide();
                }
            })
            .catch(err => {
                alert('Terjadi kesalahan saat memuat data.');
                modal.hide();
            });
    }

    function toggleAllPerms(state) {
        const checkboxes = document.querySelectorAll('.perm-checkbox-switch');
        checkboxes.forEach(cb => cb.checked = state);
    }

    function resetToRoleDefault() {
        if (!currentRoleDefaults) return;
        const checkboxes = document.querySelectorAll('.perm-checkbox-switch');
        checkboxes.forEach(cb => {
            cb.checked = currentRoleDefaults.includes(cb.value);
        });
    }

    // ===== INSTANT LIVE FILTER & SEARCH =====
    let searchDebounceTimer = null;

    function applyUserLiveFilter() {
        const searchInput = document.getElementById('userSearchInput');
        const clearBtn = document.getElementById('btnClearSearch');
        const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
        const statusVal = document.getElementById('filterStatusSelect') ? document.getElementById('filterStatusSelect').value : 'all';
        const kelasVal = document.getElementById('filterKelasSelect') ? parseInt(document.getElementById('filterKelasSelect').value || 0) : 0;
        const loginVal = document.getElementById('filterLoginSelect') ? document.getElementById('filterLoginSelect').value : 'all';

        // Toggle clear search button visibility
        if (clearBtn) {
            if (query.length > 0) {
                clearBtn.classList.remove('d-none');
            } else {
                clearBtn.classList.add('d-none');
            }
        }

        const rows = document.querySelectorAll('#tableUsers tbody tr.user-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const username = row.getAttribute('data-username') || '';
            const nama = row.getAttribute('data-nama') || '';
            const siswa = row.getAttribute('data-siswa') || '';
            const nis = row.getAttribute('data-nis') || '';
            const nohp = row.getAttribute('data-nohp') || '';
            const status = row.getAttribute('data-status') || '';
            const kelas = parseInt(row.getAttribute('data-kelas') || 0);
            const login = row.getAttribute('data-login') || '';

            // Check Search match
            let matchSearch = true;
            if (query !== '') {
                const combined = `${username} ${nama} ${siswa} ${nis} ${nohp}`.toLowerCase();
                matchSearch = combined.includes(query);
            }

            // Check Status match
            let matchStatus = true;
            if (statusVal === 'aktif') matchStatus = (status === 'aktif');
            else if (statusVal === 'nonaktif') matchStatus = (status === 'nonaktif');

            // Check Kelas match
            let matchKelas = true;
            if (kelasVal > 0) matchKelas = (kelas === kelasVal);

            // Check Login match
            let matchLogin = true;
            if (loginVal === 'ever') matchLogin = (login === 'ever');
            else if (loginVal === 'never') matchLogin = (login === 'never');

            if (matchSearch && matchStatus && matchKelas && matchLogin) {
                row.style.display = '';
                visibleCount++;
                const rowNumCol = row.querySelector('.row-number');
                if (rowNumCol) {
                    rowNumCol.textContent = visibleCount;
                }
            } else {
                row.style.display = 'none';
                const itemCheck = row.querySelector('.bulk-item-check');
                if (itemCheck && itemCheck.checked) {
                    itemCheck.checked = false;
                    row.classList.remove('row-selected');
                }
            }
        });

        // Update count text
        const countBadge = document.getElementById('displayedUserCount');
        if (countBadge) {
            countBadge.textContent = visibleCount;
        }

        // Show/hide no results row
        const noLiveRow = document.getElementById('noLiveMatchRow');
        const emptyServerRow = document.getElementById('emptyServerRow');
        if (emptyServerRow) {
            emptyServerRow.classList.toggle('d-none', visibleCount > 0);
        }
        if (noLiveRow) {
            noLiveRow.classList.toggle('d-none', visibleCount > 0 || rows.length === 0);
        }

        // Trigger bulk UI update
        if (typeof window.updateBulkUI === 'function') {
            window.updateBulkUI();
        }
    }

    function clearUserSearch() {
        const searchInput = document.getElementById('userSearchInput');
        if (searchInput) {
            searchInput.value = '';
            searchInput.focus();
        }
        applyUserLiveFilter();
    }

    function onFilterChange() {
        applyUserLiveFilter();
    }

    document.addEventListener('DOMContentLoaded', function () {
        initBulkTable({
            tableId: 'tableUsers',
            floatingBarId: 'bulkFloatingBar',
            countBadgeId: 'bulkCountBadge',
            masterCheckId: 'selectAll',
            itemCheckClass: 'bulk-item-check',
            formId: 'bulkActionForm'
        });

        const searchInput = document.getElementById('userSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(searchDebounceTimer);
                searchDebounceTimer = setTimeout(applyUserLiveFilter, 100);
            });
        }
    });

    function handleBulkActivateUsers() {
        const selected = window.getSelectedBulkIds();
        if (selected.length === 0) return;
        if (confirm(`🟢 AKTIFKAN PENGGUNA:\n\nApakah Anda yakin ingin MENGAKTIFKAN ${selected.length} user yang dipilih?`)) {
            document.getElementById('bulkActionType').value = 'activate';
            document.getElementById('bulkActionForm').submit();
        }
    }

    function handleBulkDeactivateUsers() {
        const selected = window.getSelectedBulkIds();
        if (selected.length === 0) return;
        if (confirm(`🔴 NONAKTIFKAN PENGGUNA:\n\nApakah Anda yakin ingin MENONAKTIFKAN ${selected.length} user yang dipilih? Pengguna tidak akan bisa login sampai diaktifkan kembali.`)) {
            document.getElementById('bulkActionType').value = 'deactivate';
            document.getElementById('bulkActionForm').submit();
        }
    }

    function handleBulkDeleteUsers() {
        const selected = window.getSelectedBulkIds();
        if (selected.length === 0) return;
        if (confirm(`⚠️ PERINGATAN:\n\nApakah Anda yakin ingin MENGHAPUS ${selected.length} user yang dipilih? Tindakan ini permanen dan tidak dapat dibatalkan.`)) {
            document.getElementById('bulkActionType').value = 'delete';
            document.getElementById('bulkActionForm').submit();
        }
    }

    function handleBulkResetPassword() {
        const selected = window.getSelectedBulkIds();
        if (selected.length === 0) return;
        if (confirm(`🔑 RESET PASSWORD MASSAL:\n\nPassword untuk ${selected.length} user yang dipilih akan di-reset ke USERNAME masing-masing. Lanjutkan?`)) {
            document.getElementById('bulkActionType').value = 'reset_password';
            document.getElementById('bulkActionForm').submit();
        }
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>