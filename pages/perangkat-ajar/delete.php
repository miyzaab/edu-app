<?php
/**
 * PERANGKAT AJAR - Hapus Dokumen
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_ajar');

$pdo = getConnection();
$id  = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$redirectUrl = 'index.php';

// Menentukan URL kembali setelah hapus
if (!empty($_SERVER['HTTP_REFERER'])) {
    $ref = $_SERVER['HTTP_REFERER'];
    if (str_contains($ref, '/pages/perangkat-ajar/')) {
        $parsed = parse_url($ref, PHP_URL_PATH);
        if ($parsed) {
            $base = basename($parsed);
            if (!in_array($base, ['edit.php', 'print.php', 'delete.php'])) {
                $redirectUrl = $base;
            }
        }
    }
}

if ($id > 0) {
    try {
        // Cek apakah dokumen ada
        $stmtCheck = $pdo->prepare("SELECT id, topik FROM perangkat_ajar WHERE id = :id LIMIT 1");
        $stmtCheck->execute([':id' => $id]);
        $doc = $stmtCheck->fetch();

        if ($doc) {
            $stmtDel = $pdo->prepare("DELETE FROM perangkat_ajar WHERE id = :id");
            $stmtDel->execute([':id' => $id]);

            redirect($redirectUrl, 'success', '🗑️ Dokumen Perangkat Ajar ("' . htmlspecialchars($doc['topik']) . '") berhasil dihapus.');
        } else {
            redirect($redirectUrl, 'warning', 'Dokumen Perangkat Ajar tidak ditemukan atau sudah dihapus.');
        }
    } catch (PDOException $e) {
        redirect($redirectUrl, 'danger', 'Gagal menghapus dokumen: ' . $e->getMessage());
    }
} else {
    redirect($redirectUrl, 'danger', 'ID Dokumen tidak valid.');
}
