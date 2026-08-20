<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/auth_functions.php';

header('Content-Type: application/json');

if (!isset(['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No user ID provided']);
    exit;
}

 = (int)['user_id'];
 = getConnection();

 = ->prepare('SELECT role FROM users WHERE id = :id');
->execute([':id' => ]);
 = ->fetch();

if (!) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

 = ['role'];
 = loadUserPermissions();
 = getDefaultPermissions();

// We need to merge them for the JS if the custom isn't set, default should be checked?
// Actually, if customPerms doesn't have a key, it falls back to default.
// Let's build the final effective permissions map so the UI can check the right boxes.
 = array_keys(getAllPermissions());
 = [];
foreach ( as ) {
    if (isset([])) {
        [] = [];
    } else {
        [] = in_array(, );
    }
}

echo json_encode([
    'success' => true,
    'role' => ,
    'permissions' => ,
    'defaults' => 
]);