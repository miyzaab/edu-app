<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/auth_functions.php';

header('Content-Type: application/json');

if (!isset($_GET['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No user ID provided']);
    exit;
}

$userId = (int) $_GET['user_id'];
$pdo = getConnection();

$stmt = $pdo->prepare('SELECT role, username, nama_lengkap FROM users WHERE id = :id');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$role = strtolower($user['role'] ?? '');
$customPerms = loadUserPermissions($userId);
$defaultPerms = getDefaultPermissions($role);

$allPermKeys = array_keys(getAllPermissions());
$effectivePerms = [];
$hasCustom = !empty($customPerms);

foreach ($allPermKeys as $key) {
    if ($hasCustom) {
        $effectivePerms[$key] = !empty($customPerms[$key]);
    } else {
        $effectivePerms[$key] = in_array($key, $defaultPerms);
    }
}

echo json_encode([
    'success' => true,
    'role' => $role,
    'permissions' => $effectivePerms,
    'defaults' => $defaultPerms
]);