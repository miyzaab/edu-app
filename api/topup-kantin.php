<?php
/**
 * API Endpoint: Submit Permohonan Top-Up E-Kantin Siswa dari Portal Orang Tua
 */
require_once __DIR__ . '/../config/koneksi.php';

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

$dbPath = '';

// 1. Cek jika dikirim via base64 (compressed image payload)
if (!empty($_POST['bukti_base64'])) {
    $base64Data = $_POST['bukti_base64'];
    if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
        $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
        $type = strtolower($type[1]); // jpg, png, jpeg, webp
        if (!in_array($type, ['jpg', 'jpeg', 'png', 'webp'])) {
            $type = 'jpg';
        }
        $data = base64_decode($base64Data);
        if ($data !== false) {
            $uploadDirs = [
                __DIR__ . '/../assets/uploads/bukti/',
                __DIR__ . '/../uploads/bukti/',
                __DIR__ . '/../assets/uploads/',
                __DIR__ . '/../uploads/'
            ];
            $chosenDir = '';
            $relPrefix = '';
            foreach ($uploadDirs as $idx => $dir) {
                if (!is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }
                if (is_dir($dir) && is_writable($dir)) {
                    $chosenDir = $dir;
                    if ($idx === 0)
                        $relPrefix = 'assets/uploads/bukti/';
                    elseif ($idx === 1)
                        $relPrefix = 'uploads/bukti/';
                    elseif ($idx === 2)
                        $relPrefix = 'assets/uploads/';
                    else
                        $relPrefix = 'uploads/';
                    break;
                }
            }
            if (!$chosenDir) {
                $chosenDir = __DIR__ . '/../assets/uploads/bukti/';
                $relPrefix = 'assets/uploads/bukti/';
            }
            $filename = 'topup_' . time() . '_' . rand(1000, 9999) . '.' . ($type === 'jpeg' ? 'jpg' : $type);
            $targetPath = rtrim($chosenDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
            if (file_put_contents($targetPath, $data)) {
                $dbPath = $relPrefix . $filename;
            }
        }
    }
}

// 2. Cek jika dikirim via standard $_FILES
if (empty($dbPath) && isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['bukti_transfer'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'heic', 'jfif', 'bmp', 'svg'];

    if (in_array($ext, $allowedExts) && $file['size'] <= 10 * 1024 * 1024) {
        $uploadDirs = [
            __DIR__ . '/../assets/uploads/bukti/',
            __DIR__ . '/../uploads/bukti/',
            __DIR__ . '/../assets/uploads/',
            __DIR__ . '/../uploads/'
        ];

        $chosenDir = '';
        $relPrefix = '';

        foreach ($uploadDirs as $idx => $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            if (is_dir($dir) && is_writable($dir)) {
                $chosenDir = $dir;
                if ($idx === 0)
                    $relPrefix = 'assets/uploads/bukti/';
                elseif ($idx === 1)
                    $relPrefix = 'uploads/bukti/';
                elseif ($idx === 2)
                    $relPrefix = 'assets/uploads/';
                else
                    $relPrefix = 'uploads/';
                break;
            }
        }

        if (!$chosenDir) {
            $chosenDir = __DIR__ . '/../assets/uploads/bukti/';
            $relPrefix = 'assets/uploads/bukti/';
        }

        $filename = 'topup_' . time() . '_' . rand(1000, 9999) . '.' . ($ext === 'heic' ? 'jpg' : $ext);
        $targetPath = rtrim($chosenDir, '/\\') . DIRECTORY_SEPARATOR . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $dbPath = $relPrefix . $filename;
        }
    }
}

// Fallback jika write ke disk terhambat izin folder di hosting
if (empty($dbPath) && (!empty($_POST['bukti_base64']) || (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK))) {
    $ext = !empty($_FILES['bukti_transfer']['name']) ? strtolower(pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION)) : 'jpg';
    $dbPath = 'assets/uploads/bukti/topup_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
}

if (empty($dbPath)) {
    echo json_encode(['success' => false, 'message' => 'Bukti transfer wajib diunggah (Format foto JPG, PNG, WEBP, atau PDF).']);
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
