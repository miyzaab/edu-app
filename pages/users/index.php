<?php
/**
 * KELOLA USER - List, Tambah, Edit, Hapus, Role & Class Tabs/Filters & Bulk Auto-Generate Ortu Accounts
 */
$pageTitle  = 'Kelola User';
$activePage = 'users';
require_once __DIR__ . '/../../config/auth.php';

$pdo = getConnection();

// ===== PROSES TAMBAH USER =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $nama     = trim($_POST['nama_lengkap'] ?? '');
    $role     = $_POST['role'] ?? 'bendahara';

    if ($username && $password && $nama) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (:u,:p,:n,:r)");
            $stmt->execute([':u'=>$username,':p'=>$hash,':n'=>$nama,':r'=>$role]);
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
                ':n' => 'Orang Tua / Wali ' . $s['nama'],
                ':sid' => $s['id']
            ]);
            $insertedCount++;
        }
        
        $pdo->commit();
        redirect('index.php?role=ortu', 'success', "Berhasil membuat otomatis $insertedCount akun Orang Tua.");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redirect('index.php?role=ortu', 'danger', 'Gagal membuat akun otomatis: ' . $e->getMessage());
    }
}

// ===== PROSES EDIT USER =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $id       = (int)($_POST['id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $nama     = trim($_POST['nama_lengkap'] ?? '');
    $role     = $_POST['role'] ?? 'bendahara';
    $password = $_POST['password'] ?? '';

    if ($id && $username && $nama) {
        try {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username=:u, password=:p, nama_lengkap=:n, role=:r WHERE id=:id");
                $stmt->execute([':u'=>$username,':p'=>$hash,':n'=>$nama,':r'=>$role,':id'=>$id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username=:u, nama_lengkap=:n, role=:r WHERE id=:id");
                $stmt->execute([':u'=>$username,':n'=>$nama,':r'=>$role,':id'=>$id]);
            }
            redirect('index.php?role=' . $role, 'success', "User '$username' berhasil diperbarui.");
        } catch (PDOException $e) {
            redirect('index.php?role=' . $role, 'danger', 'Username sudah digunakan oleh user lain.');
        }
    }
}

// ===== PROSES UPDATE PERMISSIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_permissions') {
    $userId = (int)($_POST['user_id'] ?? 0);
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
                    ':uid'     => $userId,
                    ':pkey'    => $perm,
                    ':allowed' => $allowed
                ]);
            }

            $pdo->commit();

            // Jika user mengupdate izin dirinya sendiri, refresh session
            if ($userId === (int)($_SESSION['user_id'] ?? 0)) {
                $_SESSION['permissions'] = loadUserPermissions($userId);
            }

            redirect('index.php?role=' . $role, 'success', 'Hak akses user berhasil diperbarui.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            redirect('index.php?role=' . $role, 'danger', 'Gagal memperbarui hak akses: ' . $e->getMessage());
        }
    }
}

// ===== PROSES HAPUS USER =====
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    
    // Jangan hapus diri sendiri
    if ($deleteId === (int)($_SESSION['user_id'] ?? 0)) {
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

// ===== FILTER ROLE, KELAS & QUERY =====
$filterRole  = $_GET['role'] ?? 'all';
$filterKelas = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
$validRoles  = ['admin', 'bendahara', 'guru', 'ortu'];

// Fetch daftar kelas untuk filter dropdown
$classList = $pdo->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC")->fetchAll();

$query = "
    SELECT u.id, u.username, u.nama_lengkap, u.role, u.last_login, u.created_at, u.siswa_id,
           s.nama AS nama_siswa, k.nama_kelas
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

if ($filterRole === 'ortu' && $filterKelas > 0) {
    $query .= " AND s.kelas_id = :kid";
    $params[':kid'] = $filterKelas;
}

$query .= " ORDER BY FIELD(u.role, 'admin', 'bendahara', 'guru', 'ortu') ASC, u.nama_lengkap ASC";
$stmtUser = $pdo->prepare($query);
$stmtUser->execute($params);
$userList = $stmtUser->fetchAll();

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
    'all'       => (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'admin'     => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
    'bendahara' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'bendahara'")->fetchColumn(),
    'guru'      => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'guru'")->fetchColumn(),
    'ortu'      => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'ortu'")->fetchColumn(),
];

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- TAB FILTER ROLE & KELAS -->
<div class="mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <div class="modern-filter-nav">
            <a class="modern-filter-btn <?= $filterRole === 'all' ? 'active' : '' ?>" href="index.php?role=all">
                Semua <span class="modern-filter-badge"><?= $roleCounts['all'] ?></span>
            </a>
            <a class="modern-filter-btn <?= $filterRole === 'admin' ? 'active' : '' ?>" href="index.php?role=admin">
                Admin <span class="modern-filter-badge"><?= $roleCounts['admin'] ?></span>
            </a>
            <a class="modern-filter-btn <?= $filterRole === 'bendahara' ? 'active' : '' ?>" href="index.php?role=bendahara">
                Bendahara <span class="modern-filter-badge"><?= $roleCounts['bendahara'] ?></span>
            </a>
            <a class="modern-filter-btn <?= $filterRole === 'guru' ? 'active' : '' ?>" href="index.php?role=guru">
                Guru <span class="modern-filter-badge"><?= $roleCounts['guru'] ?></span>
            </a>
            <a class="modern-filter-btn <?= $filterRole === 'ortu' ? 'active' : '' ?>" href="index.php?role=ortu">
                Orang Tua (Ortu) <span class="modern-filter-badge"><?= $roleCounts['ortu'] ?></span>
            </a>
        </div>

        <!-- Filter Kelas khusus untuk Role Orang Tua -->
        <?php if ($filterRole === 'ortu'): ?>
            <div class="d-flex align-items-center gap-2">
                <label class="small fw-bold text-muted mb-0" for="filterKelasSelect"><i class="bi bi-filter"></i> Kelas:</label>
                <select id="filterKelasSelect" class="form-select form-select-sm border-2" style="width: auto; min-width: 130px;" onchange="location.href='index.php?role=ortu&kelas_id=' + this.value">
                    <option value="0">Semua Kelas</option>
                    <?php foreach ($classList as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filterKelas === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nama_kelas']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </div>
    
    <div>
        <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalTambah"><i class="bi bi-person-plus"></i> Tambah User</button>
    </div>
</div>

<!-- ALERT BULK GENERATE ACCOUNTS -->
<?php if ($countUnlinkedOrangTua > 0): ?>
    <div class="alert alert-info border-0 shadow-sm rounded-4 p-3 d-flex align-items-center justify-content-between gap-3 mb-4 flex-wrap">
        <div>
            <h6 class="mb-1 fw-bold text-primary-emphasis"><i class="bi bi-person-plus-fill"></i> Sinkronisasi Massal Akun Orang Tua (Ortu)</h6>
            <p class="mb-0 text-muted small">Terdapat <strong><?= $countUnlinkedOrangTua ?></strong> santri aktif yang belum memiliki akun masuk Orang Tua / Wali Murid.</p>
        </div>
        <form method="POST" class="m-0">
            <input type="hidden" name="action" value="bulk_generate_ortu">
            <button type="submit" class="btn btn-sm btn-primary fw-bold px-3 py-1.5 shadow" onclick="return confirm('Buat otomatis <?= $countUnlinkedOrangTua ?> akun Ortu dengan password default menggunakan NIS?')"><i class="bi bi-magic"></i> Buat Akun Otomatis</button>
        </form>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Menampilkan: <strong><?= count($userList) ?></strong> user</p>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Username</th>
                    <th>Nama Lengkap</th>
                    <th>Role</th>
                    <th>Login Terakhir</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($userList)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">Belum ada user dengan kriteria filter ini.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($userList as $i => $u): ?>
                    <tr>
                        <td class="text-muted font-monospace"><?= $i+1 ?></td>
                        <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                        <td>
                            <div class="table-avatar-item">
                                <div class="table-avatar-circle"><?= strtoupper(substr($u['nama_lengkap'], 0, 1)) ?></div>
                                <div>
                                    <strong class="d-block text-dark"><?= htmlspecialchars($u['nama_lengkap']) ?></strong>
                                    <?php if ($u['role'] === 'ortu' && !empty($u['nama_siswa'])): ?>
                                        <small class="text-muted d-block mt-1" style="font-size: 0.73rem;">
                                            <i class="bi bi-person-heart text-danger me-1"></i> Wali dari: <strong><?= htmlspecialchars($u['nama_siswa']) ?></strong> 
                                            <span class="badge bg-light text-secondary border rounded-pill ms-1 font-monospace" style="font-size: 0.65rem;"><?= htmlspecialchars($u['nama_kelas'] ?? '-') ?></span>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php
                            $roleClass = 'badge-aktif';
                            if ($u['role'] === 'admin') $roleClass = 'bg-danger text-white';
                            elseif ($u['role'] === 'bendahara') $roleClass = 'bg-primary text-white';
                            elseif ($u['role'] === 'guru') $roleClass = 'bg-success text-white';
                            elseif ($u['role'] === 'ortu') $roleClass = 'bg-info text-dark';
                            ?>
                            <span class="badge <?= $roleClass ?> px-2.5 py-1.5 rounded-3 font-monospace" style="font-size: 0.72rem; letter-spacing: 0.05em;"><?= strtoupper($u['role']) ?></span>
                        </td>
                        <td><?= $u['last_login'] ? formatTanggal($u['last_login']) : '<span class="text-muted">Belum pernah</span>' ?></td>
                        <td>
                            <button class="btn-sm-action btn-edit" onclick='editUser(<?= json_encode($u) ?>)' title="Edit User"><i class="bi bi-pencil"></i></button>
                            <button class="btn-sm-action btn-permission" onclick='editPermissions(<?= json_encode($u) ?>)' title="Kelola Hak Akses"><i class="bi bi-shield-lock-fill"></i></button>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <button onclick="confirmDelete('index.php?delete=<?= $u['id'] ?>','<?= addslashes(htmlspecialchars($u['username'])) ?>')" class="btn-sm-action btn-delete" title="Hapus User">
                                    <i class="bi bi-trash"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah User -->
<div class="modal fade" id="modalTambah" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
    <form method="POST">
        <input type="hidden" name="action" value="create">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-person-plus"></i> Tambah User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Username *</label><input type="text" name="username" class="form-control" required autocomplete="off"></div>
            <div class="mb-3"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Nama Lengkap *</label><input type="text" name="nama_lengkap" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Role</label>
                <select name="role" class="form-select">
                    <option value="bendahara" <?= $filterRole === 'bendahara' ? 'selected' : '' ?>>Bendahara</option>
                    <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="guru" <?= $filterRole === 'guru' ? 'selected' : '' ?>>Guru</option>
                    <option value="ortu" <?= $filterRole === 'ortu' ? 'selected' : '' ?>>Orang Tua (Ortu)</option>
                </select>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn-primary-custom">Simpan</button></div>
    </form>
</div></div>
</div>

<!-- Modal Edit User -->
<div class="modal fade" id="modalEdit" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
    <form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="editUserId">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Username *</label><input type="text" name="username" id="editUsername" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Password <small class="text-muted">(Kosongkan jika tidak diubah)</small></label><input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah"></div>
            <div class="mb-3"><label class="form-label">Nama Lengkap *</label><input type="text" name="nama_lengkap" id="editNamaUser" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Role</label>
                <select name="role" id="editRole" class="form-select">
                    <option value="bendahara">Bendahara</option>
                    <option value="admin">Admin</option>
                    <option value="guru">Guru</option>
                    <option value="ortu">Orang Tua (Ortu)</option>
                </select>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn-primary-custom">Simpan</button></div>
    </form>
</div></div>
</div>

<!-- Modal Kelola Hak Akses -->
<div class="modal fade" id="modalPermissions" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <form method="POST">
        <input type="hidden" name="action" value="update_permissions">
        <input type="hidden" name="user_id" id="permUserId">
        <input type="hidden" name="role" value="<?= htmlspecialchars($filterRole) ?>">
        
        <div class="modal-header bg-light">
            <div>
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-shield-lock-fill text-primary"></i> Kelola Hak Akses</h5>
                <small class="text-muted" id="permUserTitle">User: -</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        
        <div class="modal-body bg-white" style="max-height: 70vh; overflow-y: auto;">
            <!-- Shortcut Buttons -->
            <div class="d-flex gap-2 mb-3 pb-3 border-bottom flex-wrap">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleAllPerms(true)"><i class="bi bi-check-all"></i> Pilih Semua</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllPerms(false)"><i class="bi bi-x"></i> Kosongkan Semua</button>
                <button type="button" class="btn btn-sm btn-outline-warning text-dark" onclick="resetToRoleDefault()"><i class="bi bi-arrow-counterclockwise"></i> Reset ke Default Role</button>
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
                                    <input class="form-check-input perm-checkbox-switch" type="checkbox" name="permissions[]" value="<?= htmlspecialchars($pkey) ?>" id="switch_<?= htmlspecialchars($pkey) ?>">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="modal-footer bg-light">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn-primary-custom px-4"><i class="bi bi-save"></i> Simpan Akses</button>
        </div>
    </form>
</div></div>
</div>

<script>
function editUser(data) {
    document.getElementById('editUserId').value = data.id;
    document.getElementById('editUsername').value = data.username;
    document.getElementById('editNamaUser').value = data.nama_lengkap;
    document.getElementById('editRole').value = data.role;
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
    fetch('../../ajax/get_user_permissions.php?user_id=' + user.id)
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
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
