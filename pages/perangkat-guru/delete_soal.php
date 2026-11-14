<?php
/**
 * HAPUS SATU BUTIR SOAL
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_guru');

$pdo = getConnection();
require_once __DIR__ . '/init_soal.php';

$soalId = (int) ($_GET['id'] ?? 0);
$naskahId = (int) ($_GET['naskah_id'] ?? 0);

if ($soalId > 0 && $naskahId > 0) {
    // Ambil info gambar jika ada
    $stmtG = $pdo->prepare("SELECT gambar FROM soal_butir WHERE id = :id AND naskah_id = :nid");
    $stmtG->execute([':id' => $soalId, ':nid' => $naskahId]);
    $gambar = $stmtG->fetchColumn();

    if (!empty($gambar)) {
        $imgPath = __DIR__ . '/../../uploads/soal/' . $gambar;
        if (file_exists($imgPath)) {
            @unlink($imgPath);
        }
    }

    $stmtDel = $pdo->prepare("DELETE FROM soal_butir WHERE id = :id AND naskah_id = :nid");
    $stmtDel->execute([':id' => $soalId, ':nid' => $naskahId]);

    rehitungPoinNaskah($naskahId, $pdo);

    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => 'Butir soal berhasil dihapus.'
    ];
}

header('Location: edit.php?id=' . $naskahId);
exit;
