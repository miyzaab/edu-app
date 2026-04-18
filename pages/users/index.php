<?php
/**
 * KELOLA USER - List, Tambah, Edit, Hapus
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
            redirect('index.php', 'success', "User '$username' berhasil ditambahkan.");
        } catch (PDOException $e) {
            redirect('index.php', 'danger', 'Username sudah digunakan.');
        }
    } else {
        redirect('index.php', 'danger', 'Semua field wajib diisi.');
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
            redirect('index.php', 'success', "User '$username' berhasil diperbarui.");
        } catch (PDOException $e) {
            redirect('index.php', 'danger', 'Username sudah digunakan oleh user lain.');
        }
    }
}

// ===== PROSES HAPUS USER =====
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    // Jangan hapus diri sendiri
    if ($deleteId === (int)$_SESSION['user_id']) {
        redirect('index.php', 'danger', 'Tidak bisa menghapus akun sendiri.');
    } else {
        $pdo->prepare("DELETE FROM users WHERE id=:id")->execute([':id'=>$deleteId]);
        redirect('index.php', 'success', 'User berhasil dihapus.');
    }
}

$userList = $pdo->query("SELECT id, username, nama_lengkap, role, last_login, created_at FROM users ORDER BY id")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Total: <strong><?= count($userList) ?></strong> user</p>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalTambah"><i class="bi bi-person-plus"></i> Tambah User</button>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>No</th><th>Username</th><th>Nama Lengkap</th><th>Role</th><th>Login Terakhir</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($userList as $i => $u): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                    <td><?= htmlspecialchars($u['nama_lengkap']) ?></td>
                    <td><span class="badge-status badge-aktif"><?= ucfirst($u['role']) ?></span></td>
                    <td><?= $u['last_login'] ? formatTanggal($u['last_login']) : '<span class="text-muted">Belum pernah</span>' ?></td>
                    <td>
                        <button class="btn-sm-action btn-edit" onclick='editUser(<?= json_encode($u) ?>)'><i class="bi bi-pencil"></i></button>
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <button onclick="confirmDelete('index.php?delete=<?= $u['id'] ?>','<?= htmlspecialchars($u['username']) ?>')" class="btn-sm-action btn-delete"><i class="bi bi-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
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
                    <option value="bendahara">Bendahara</option>
                    <option value="admin">Admin</option>
                    <option value="operator">Operator</option>
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
                    <option value="operator">Operator</option>
                </select>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn-primary-custom">Simpan</button></div>
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
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
