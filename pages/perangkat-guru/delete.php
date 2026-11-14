<?php
/**
 * HAPUS PAKET NASKAH SOAL
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_guru');

$pdo = getConnection();
require_once __DIR__ . '/init_soal.php';

$naskahId = (int) ($_GET['id'] ?? 0);
$userId = (int) $_SESSION['user_id'];
$userRole = strtolower($_SESSION['role'] ?? 'guru');

if ($naskahId <= 0) {
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM soal_naskah WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $naskahId]);
    $src = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$src) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Naskah soal tidak ditemukan.'];
        header('Location: index.php');
        exit;
    }

    if ($userRole !== 'admin' && $src['guru_id'] != $userId) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Anda tidak memiliki hak untuk menghapus naskah soal ini.'];
        header('Location: index.php');
        exit;
    }

    // Hapus file gambar soal terkait
    $stmtG = $pdo->prepare("SELECT gambar FROM soal_butir WHERE naskah_id = :nid AND gambar IS NOT NULL");
    $stmtG->execute([':nid' => $naskahId]);
    $images = $stmtG->fetchAll(PDO::FETCH_COLUMN);

    foreach ($images as $img) {
        if (!empty($img)) {
            $f = __DIR__ . '/../../uploads/soal/' . $img;
            if (file_exists($f)) {
                @unlink($f);
            }
        }
    }

    // Hapus butir soal & naskah
    $pdo->prepare("DELETE FROM soal_butir WHERE naskah_id = :nid")->execute([':nid' => $naskahId]);
    $pdo->prepare("DELETE FROM soal_naskah WHERE id = :id")->execute([':id' => $naskahId]);

    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => 'Naskah soal berhasil dihapus.'
    ];

} catch (Exception $e) {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Gagal menghapus naskah soal: ' . $e->getMessage()
    ];
}

header('Location: index.php');
exit;
