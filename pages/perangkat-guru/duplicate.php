<?php
/**
 * DUPLIKASI NASKAH SOAL (CLONE)
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_guru');

$pdo = getConnection();
require_once __DIR__ . '/init_soal.php';

$naskahId = (int) ($_GET['id'] ?? 0);
$userId = (int) $_SESSION['user_id'];

if ($naskahId <= 0) {
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM soal_naskah WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $naskahId]);
    $src = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$src) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Naskah soal sumber tidak ditemukan.'];
        header('Location: index.php');
        exit;
    }

    // Insert salinan naskah
    $stmtCopy = $pdo->prepare("
        INSERT INTO soal_naskah (
            guru_id, mapel_id, nama_mapel, tingkat_kelas, kelas_id, tipe_ujian, 
            judul_ujian, semester, tahun_ajaran, alokasi_waktu, 
            tanggal_ujian, petunjuk_umum, total_poin, status
        ) VALUES (
            :guru_id, :mapel_id, :nama_mapel, :tingkat_kelas, :kelas_id, :tipe_ujian, 
            :judul_ujian, :semester, :tahun_ajaran, :alokasi_waktu, 
            :tanggal_ujian, :petunjuk_umum, 0, 'draft'
        )
    ");
    $stmtCopy->execute([
        ':guru_id' => $userId,
        ':mapel_id' => $src['mapel_id'],
        ':nama_mapel' => $src['nama_mapel'],
        ':tingkat_kelas' => $src['tingkat_kelas'],
        ':kelas_id' => $src['kelas_id'],
        ':tipe_ujian' => $src['tipe_ujian'],
        ':judul_ujian' => $src['judul_ujian'] . ' (Salinan)',
        ':semester' => $src['semester'],
        ':tahun_ajaran' => $src['tahun_ajaran'],
        ':alokasi_waktu' => $src['alokasi_waktu'],
        ':tanggal_ujian' => $src['tanggal_ujian'],
        ':petunjuk_umum' => $src['petunjuk_umum'],
    ]);

    $newId = (int) $pdo->lastInsertId();

    // Copy butir soal
    $stmtItems = $pdo->prepare("SELECT * FROM soal_butir WHERE naskah_id = :nid ORDER BY id ASC");
    $stmtItems->execute([':nid' => $naskahId]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    $stmtInsItem = $pdo->prepare("
        INSERT INTO soal_butir (
            naskah_id, nomor_urut, tipe_soal, pertanyaan, gambar, 
            pilihan_a, pilihan_b, pilihan_c, pilihan_d, kunci_jawaban, poin
        ) VALUES (
            :nid, :no, :tipe, :tanya, :gambar, 
            :pa, :pb, :pc, :pd, :kunci, :poin
        )
    ");

    foreach ($items as $it) {
        $stmtInsItem->execute([
            ':nid' => $newId,
            ':no' => $it['nomor_urut'],
            ':tipe' => $it['tipe_soal'],
            ':tanya' => $it['pertanyaan'],
            ':gambar' => $it['gambar'],
            ':pa' => $it['pilihan_a'],
            ':pb' => $it['pilihan_b'],
            ':pc' => $it['pilihan_c'],
            ':pd' => $it['pilihan_d'],
            ':kunci' => $it['kunci_jawaban'],
            ':poin' => $it['poin'],
        ]);
    }

    rehitungPoinNaskah($newId, $pdo);

    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => 'Naskah soal berhasil diduplikasi menjadi paket baru!'
    ];

    header('Location: edit.php?id=' . $newId);
    exit;

} catch (Exception $e) {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Gagal menduplikasi naskah: ' . $e->getMessage()
    ];
    header('Location: index.php');
    exit;
}
