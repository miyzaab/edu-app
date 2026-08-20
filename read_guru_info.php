<?php
require_once __DIR__ . '/config/koneksi.php';
$pdo = getConnection();

echo "=== USERS ===\n";
$users = $pdo->query("SELECT id, username, role, nama_lengkap FROM users")->fetchAll(PDO::FETCH_ASSOC);
print_r($users);

echo "=== GURU PERMISSIONS ===\n";
foreach ($users as $u) {
    if ($u['role'] === 'guru') {
        echo "User: {$u['username']} (ID: {$u['id']})\n";
        $perms = $pdo->query("SELECT permission_key, is_allowed FROM user_permissions WHERE user_id = {$u['id']}")->fetchAll(PDO::FETCH_ASSOC);
        print_r($perms);
    }
}
