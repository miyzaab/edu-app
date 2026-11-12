<?php
/**
 * API Endpoint: Submit Permohonan Top-Up E-Kantin Siswa dari Portal Orang Tua
 */
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/proof_upload_helper.php';

header('Content-Type: application/json; charset=utf-8');

// Proteksi Sesi Ortu
if (!isset($_SESSION['user_id']) && empty($_POST['siswa_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesi login telah berakhir. Silakan login kembali.']);
    exit;
}

$pdo = getConnection();
$siswaId = (int) ($_POST['siswa_id'] ?? $_SESSION['siswa_id'] ?? 0);

// 1. Cek dari database users berdasarkan user_id login
if (!$siswaId && !empty($_SESSION['user_id'])) {
    $stmtU = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmtU->execute([':id' => (int) $_SESSION['user_id']]);
    $userRow = $stmtU->fetch(PDO::FETCH_ASSOC);
    if (!empty($userRow['siswa_id'])) {
        $siswaId = (int) $userRow['siswa_id'];
    }
}

// 2. Fallback cek NIS siswa dari username
if (!$siswaId && !empty($_SESSION['username'])) {
    $stmtS = $pdo->prepare("SELECT id FROM siswa WHERE nis = :nis LIMIT 1");
    $stmtS->execute([':nis' => $_SESSION['username']]);
    $siswaId = (int) $stmtS->fetchColumn();
}

// 3. Fallback nama lengkap jika format Orang Tua [Nama Siswa]
if (!$siswaId && !empty($_SESSION['nama_lengkap'])) {
    $cleanName = trim(str_ireplace(['Orang Tua', 'Ortu', 'Wali Murid', 'Wali dari'], '', $_SESSION['nama_lengkap']));
    $ignoredNames = ['tes', 'test', 'admin', 'user', 'demo', 'orang tua', 'ortu', ''];
    if (!in_array(strtolower($cleanName), $ignoredNames)) {
        $stmtS = $pdo->prepare("SELECT id FROM siswa WHERE LOWER(nama) LIKE LOWER(:n) LIMIT 1");
        $stmtS->execute([':n' => '%' . $cleanName . '%']);
        $siswaId = (int) $stmtS->fetchColumn();
    }
}

// 4. Fallback test_siswa_id
if (!$siswaId && !empty($_SESSION['test_siswa_id'])) {
    $siswaId = (int) $_SESSION['test_siswa_id'];
}

if (!$siswaId) {
    echo json_encode(['success' => false, 'message' => 'Akun Anda belum terhubung dengan data siswa. Silakan hubungi admin sekolah.']);
    exit;
}

$rawNom = trim((string) ($_POST['nominal_topup'] ?? $_POST['nominal'] ?? '0'));
$cleanNom = preg_replace('/[^0-9]/', '', $rawNom);
$nominal = (float) $cleanNom;
$catatan = trim($_POST['catatan_topup'] ?? $_POST['catatan'] ?? '');

if ($nominal <= 0) {
    echo json_encode(['success' => false, 'message' => 'Nominal top-up harus lebih dari Rp 0.']);
    exit;
}

$dbPath = saveValidatedProofUpload(
    __DIR__ . '/..',
    $_FILES['bukti_transfer'] ?? null,
    $_POST['bukti_base64'] ?? null,
    'topup'
) ?? '';

if (empty($dbPath)) {
    echo json_encode(['success' => false, 'message' => 'Bukti transfer wajib berupa file gambar/PDF valid (tidak kosong/korup).']);
    exit;
}

try {
    $stmtIns = $pdo->prepare("INSERT INTO pembayaran_pending (siswa_id, jenis, jenis_pembayaran_id, bulan, tahun, nominal, bukti_transfer, catatan, status) VALUES (:s, 'topup_kantin', NULL, NULL, NULL, :n, :b, :c, 'pending')");
    $stmtIns->execute([
        ':s' => $siswaId,
        ':n' => $nominal,
        ':b' => $dbPath,
        ':c' => $catatan
    ]);

    $_SESSION['flash_ortu'] = [
        'type' => 'success',
        'message' => 'Alhamdulillah, permohonan Top-Up Saldo E-Kantin sebesar Rp ' . number_format($nominal, 0, ',', '.') . ' telah terkirim! Silakan tunggu verifikasi admin kantin.'
    ];

    echo json_encode([
        'success' => true,
        'message' => 'Permohonan Top-Up Saldo sebesar Rp ' . number_format($nominal, 0, ',', '.') . ' berhasil dikirim!',
        'redirect' => 'portal-ortu.php?tab=kantin'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan permohonan: ' . $e->getMessage()]);
}
