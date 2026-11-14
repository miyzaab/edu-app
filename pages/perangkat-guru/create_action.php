<?php
/**
 * ACTION: BUAT NASKAH SOAL BARU
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_guru');

$pdo = getConnection();
require_once __DIR__ . '/init_soal.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$mapelId = !empty($_POST['mapel_id']) ? (int) $_POST['mapel_id'] : null;
$namaMapel = trim($_POST['nama_mapel'] ?? '');

// Jika nama mapel kosong, cari dari mapel_id
if ($namaMapel === '' && $mapelId) {
    $stmtM = $pdo->prepare("SELECT nama_mapel FROM mata_pelajaran WHERE id = :id");
    $stmtM->execute([':id' => $mapelId]);
    $namaMapel = (string) $stmtM->fetchColumn();
}
if ($namaMapel === '') {
    $namaMapel = 'Mata Pelajaran';
}

$tingkatKelas = trim($_POST['tingkat_kelas'] ?? 'VII');
$tipeUjian = trim($_POST['tipe_ujian'] ?? 'AAS');
$judulUjian = trim($_POST['judul_ujian'] ?? 'UJIAN AKHIR SEMESTER');
$semester = in_array($_POST['semester'] ?? '', ['Ganjil', 'Genap']) ? $_POST['semester'] : 'Ganjil';
$tahunAjaran = trim($_POST['tahun_ajaran'] ?? '2025 – 2026');
$alokasiWaktu = (int) ($_POST['alokasi_waktu'] ?? 90);
$tanggalUjian = !empty($_POST['tanggal_ujian']) ? $_POST['tanggal_ujian'] : null;
$petunjukUmum = trim($_POST['petunjuk_umum'] ?? '');

try {
    $stmt = $pdo->prepare("
        INSERT INTO soal_naskah (
            guru_id, mapel_id, nama_mapel, tingkat_kelas, tipe_ujian, 
            judul_ujian, semester, tahun_ajaran, alokasi_waktu, 
            tanggal_ujian, petunjuk_umum, total_poin, status
        ) VALUES (
            :guru_id, :mapel_id, :nama_mapel, :tingkat_kelas, :tipe_ujian, 
            :judul_ujian, :semester, :tahun_ajaran, :alokasi_waktu, 
            :tanggal_ujian, :petunjuk_umum, 0, 'draft'
        )
    ");
    $stmt->execute([
        ':guru_id' => $userId,
        ':mapel_id' => $mapelId,
        ':nama_mapel' => $namaMapel,
        ':tingkat_kelas' => $tingkatKelas,
        ':tipe_ujian' => $tipeUjian,
        ':judul_ujian' => $judulUjian,
        ':semester' => $semester,
        ':tahun_ajaran' => $tahunAjaran,
        ':alokasi_waktu' => $alokasiWaktu,
        ':tanggal_ujian' => $tanggalUjian,
        ':petunjuk_umum' => $petunjukUmum,
    ]);

    $naskahId = (int) $pdo->lastInsertId();

    // Buat butir soal awal sebagai panduan
    $stmtSample = $pdo->prepare("
        INSERT INTO soal_butir (naskah_id, nomor_urut, tipe_soal, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, kunci_jawaban, poin)
        VALUES 
        (:nid1, 1, 'pg', 'Contoh butir soal pilihan ganda pertama...', 'Pilihan Jawaban A', 'Pilihan Jawaban B', 'Pilihan Jawaban C', 'Pilihan Jawaban D', 'A', 2.00),
        (:nid2, 2, 'isian', 'Contoh butir pertanyaan isian singkat pertama...', NULL, NULL, NULL, NULL, 'Kunci Isian', 3.00),
        (:nid3, 3, 'essay', 'Contoh butir pertanyaan essay / uraian pertama...', NULL, NULL, NULL, NULL, 'Pedoman penskoran essay', 5.00)
    ");
    $stmtSample->execute([
        ':nid1' => $naskahId,
        ':nid2' => $naskahId,
        ':nid3' => $naskahId
    ]);

    rehitungPoinNaskah($naskahId, $pdo);

    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => 'Naskah soal berhasil dibuat! Silakan lanjutkan menyusun butir soal Anda di bawah ini.'
    ];

    header('Location: edit.php?id=' . $naskahId);
    exit;

} catch (Exception $e) {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Gagal membuat naskah soal: ' . $e->getMessage()
    ];
    header('Location: index.php');
    exit;
}
