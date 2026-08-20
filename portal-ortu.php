<?php
/**
 * PORTAL ORANG TUA (100% PRESISE REAL-DATABASE DRIVEN WITH PAGE-ENTER ANIMATIONS) - Edu-App
 */
require_once __DIR__ . '/config/koneksi.php';

// Proteksi Halaman: Wajib Login Orang Tua
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'ortu') {
    $_SESSION['login_tab'] = 'ortu';
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = getConnection();

// Fetch Data Ortu User yang Login
$userOrtu = null;
if (!empty($_SESSION['user_id'])) {
    $stmtU = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmtU->execute([':id' => $_SESSION['user_id']]);
    $userOrtu = $stmtU->fetch();
}

// Auto-fetch data siswa terkait user ortu yang sedang login
$loggedSiswa = null;
$siswaId = (int) ($_SESSION['siswa_id'] ?? 0);

if ($siswaId) {
    $stmtS = $pdo->prepare("SELECT s.*, k.nama_kelas, k.tingkat FROM siswa s JOIN kelas k ON s.kelas_id = k.id WHERE s.id = :id LIMIT 1");
    $stmtS->execute([':id' => $siswaId]);
    $loggedSiswa = $stmtS->fetch();
}
if (!$loggedSiswa && !empty($_SESSION['username'])) {
    $stmtS = $pdo->prepare("SELECT s.*, k.nama_kelas, k.tingkat FROM siswa s JOIN kelas k ON s.kelas_id = k.id WHERE s.nis = :nis LIMIT 1");
    $stmtS->execute([':nis' => $_SESSION['username']]);
    $loggedSiswa = $stmtS->fetch();
}
if (!$loggedSiswa && !empty($_SESSION['nama_lengkap'])) {
    $cleanName = trim(str_ireplace(['Orang Tua', 'Ortu'], '', $_SESSION['nama_lengkap']));
    $stmtS = $pdo->prepare("SELECT s.*, k.nama_kelas, k.tingkat FROM siswa s JOIN kelas k ON s.kelas_id = k.id WHERE LOWER(s.nama) LIKE LOWER(:n) LIMIT 1");
    $stmtS->execute([':n' => '%' . $cleanName . '%']);
    $loggedSiswa = $stmtS->fetch();
}
if (!$loggedSiswa) {
    // Fallback to first active student in database if still not matched
    $stmtS = $pdo->query("SELECT s.*, k.nama_kelas, k.tingkat FROM siswa s JOIN kelas k ON s.kelas_id = k.id LIMIT 1");
    $loggedSiswa = $stmtS->fetch();
}

if ($loggedSiswa) {
    $siswaId = (int) $loggedSiswa['id'];
    $_SESSION['siswa_id'] = $siswaId;
}

$fotoSiswaUrl = '';
if (!empty($loggedSiswa['foto'])) {
    if (file_exists(__DIR__ . '/uploads/siswa/' . $loggedSiswa['foto'])) {
        $fotoSiswaUrl = BASE_URL . '/uploads/siswa/' . htmlspecialchars($loggedSiswa['foto']);
    } elseif (file_exists(__DIR__ . '/' . $loggedSiswa['foto'])) {
        $fotoSiswaUrl = BASE_URL . '/' . htmlspecialchars($loggedSiswa['foto']);
    }
}

$namaSekolah = getSetting('nama_sekolah', SCHOOL_NAME);
$appName = getSetting('app_name', APP_NAME);
$logoPath = getSetting('logo_path', '');

// 1. DATA KEUANGAN SPP (Bulan Lunas & Pending)
$sppLunasRows = [];
$sppPendingRows = [];
if ($siswaId) {
    // Bulan Lunas
    $stmtSL = $pdo->prepare("SELECT bulan, tahun FROM pembayaran_spp WHERE siswa_id = :sid");
    $stmtSL->execute([':sid' => $siswaId]);
    $sppLunasRows = $stmtSL->fetchAll();

    // Bulan Pending
    $stmtSP = $pdo->prepare("SELECT bulan, tahun FROM pembayaran_pending WHERE siswa_id = :sid AND jenis = 'spp' AND status = 'pending'");
    $stmtSP->execute([':sid' => $siswaId]);
    $sppPendingRows = $stmtSP->fetchAll();
}

$lunasMonths = array_map(fn($r) => (int) $r['bulan'], $sppLunasRows);
$pendingMonths = array_map(fn($r) => (int) $r['bulan'], $sppPendingRows);
$nominalSppDefault = (float) ($loggedSiswa['nominal_spp'] ?? 150000);

$namaBulanArr = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember'
];

$currentMonth = (int) date('n');
$sppCurrentStatus = in_array($currentMonth, $lunasMonths) ? 'lunas' : (in_array($currentMonth, $pendingMonths) ? 'pending' : 'unpaid');

// 2. DATA UANG PANGKAL
$totalUPBayar = 0;
$totalUPPending = 0;
if ($siswaId) {
    $stmtUP = $pdo->prepare("SELECT SUM(nominal) FROM pembayaran_uang_pangkal WHERE siswa_id = :sid");
    $stmtUP->execute([':sid' => $siswaId]);
    $totalUPBayar = (float) $stmtUP->fetchColumn();

    $stmtUPP = $pdo->prepare("SELECT SUM(nominal) FROM pembayaran_pending WHERE siswa_id = :sid AND jenis = 'uang_pangkal' AND status = 'pending'");
    $stmtUPP->execute([':sid' => $siswaId]);
    $totalUPPending = (float) $stmtUPP->fetchColumn();
}

$targetUP = (float) ($loggedSiswa['target_uang_pangkal'] ?? 0);
$sisaUP = max(0, $targetUP - $totalUPBayar);
$isUPLunas = ($targetUP > 0 && $sisaUP <= 0);
$percentUP = $targetUP > 0 ? min(100, round(($totalUPBayar / $targetUP) * 100)) : 100;

// 3. DATA PEMBAYARAN LAINNYA (Daftar Ulang, Seragam, Buku, Kegiatan)
$jenisLainnya = $pdo->query("SELECT * FROM jenis_pembayaran WHERE status='aktif' ORDER BY nama_pembayaran")->fetchAll();
$pembayaranLainBayarMap = [];
$pembayaranLainPendingMap = [];

if ($siswaId) {
    try {
        $stmtPl = $pdo->prepare("SELECT jenis_pembayaran_id, SUM(nominal) as total FROM pembayaran_lain WHERE siswa_id = :sid GROUP BY jenis_pembayaran_id");
        $stmtPl->execute([':sid' => $siswaId]);
        $rowsPl = $stmtPl->fetchAll();
        foreach ($rowsPl as $r) {
            $pembayaranLainBayarMap[$r['jenis_pembayaran_id']] = (float) $r['total'];
        }

        $stmtPlP = $pdo->prepare("SELECT jenis_pembayaran_id, SUM(nominal) as total FROM pembayaran_pending WHERE siswa_id = :sid AND jenis = 'lainnya' AND status = 'pending' GROUP BY jenis_pembayaran_id");
        $stmtPlP->execute([':sid' => $siswaId]);
        $rowsPlP = $stmtPlP->fetchAll();
        foreach ($rowsPlP as $r) {
            $pembayaranLainPendingMap[$r['jenis_pembayaran_id']] = (float) $r['total'];
        }
    } catch (Exception $e) {
    }
}

// 4. DATA NILAI SISWA (BREAKDOWN SUMATIF 1-4, UTS, UAS)
$nilaiSiswaList = [];
$avgNilai = 0;
$totalMapelTuntas = 0;
if ($siswaId) {
    try {
        $stmtN = $pdo->prepare("
            SELECT n.*, m.kkm
            FROM nilai_siswa n
            LEFT JOIN mata_pelajaran m ON n.mapel = m.nama_mapel
            WHERE n.siswa_id = :sid
            ORDER BY n.tahun_ajaran DESC, n.semester DESC, n.mapel ASC
        ");
        $stmtN->execute([':sid' => $siswaId]);
        $nilaiSiswaList = $stmtN->fetchAll();
    } catch (Exception $e) {
        $nilaiSiswaList = [];
    }

    if (!empty($nilaiSiswaList)) {
        $sumNilai = 0;
        foreach ($nilaiSiswaList as $ns) {
            $nAk = (float) ($ns['nilai_akhir'] ?? 0);
            $sumNilai += $nAk;
            $kkmVal = (float) ($ns['kkm'] ?? 75);
            if ($nAk >= $kkmVal)
                $totalMapelTuntas++;
        }
        $avgNilai = round($sumNilai / count($nilaiSiswaList), 1);
    }
}

// 5. RIWAYAT PEMBAYARAN PENDING & REKAP
$riwayatPending = [];
if ($siswaId) {
    $stmtR = $pdo->prepare("
        SELECT p.*, j.nama_pembayaran
        FROM pembayaran_pending p
        LEFT JOIN jenis_pembayaran j ON p.jenis_pembayaran_id = j.id
        WHERE p.siswa_id = :sid
        ORDER BY p.created_at DESC
        LIMIT 25
    ");
    $stmtR->execute([':sid' => $siswaId]);
    $riwayatPending = $stmtR->fetchAll();
}

// 5b. PERMOHONAN TOPUP SALDO KANTIN
$topupStatusList = [];
if ($siswaId) {
    $stmtTS = $pdo->prepare("
        SELECT * FROM pembayaran_pending 
        WHERE siswa_id = :sid AND jenis = 'topup_kantin'
        ORDER BY id DESC
        LIMIT 10
    ");
    $stmtTS->execute([':sid' => $siswaId]);
    $topupStatusList = $stmtTS->fetchAll();
}

// 6. SALDO & RIWAYAT KANTIN + AGREGASI PENGELUARAN MINGGU INI REAL DB
$saldoKantin = 0;
$riwayatKantin = [];
$weeklyKantinSpend = 0;
$dailyKantinMap = ['Sen' => 0, 'Sel' => 0, 'Rab' => 0, 'Kam' => 0, 'Jum' => 0];

if ($siswaId) {
    try {
        $stmtSk = $pdo->prepare("SELECT saldo FROM saldo_siswa WHERE siswa_id = :sid");
        $stmtSk->execute([':sid' => $siswaId]);
        $saldoKantin = (float) $stmtSk->fetchColumn();

        $stmtRk = $pdo->prepare("
            SELECT t.*, GROUP_CONCAT(CONCAT(m.nama_item, ' (', d.jumlah, 'x)') SEPARATOR ', ') AS item_summary
            FROM kantin_transaksi t
            JOIN kantin_transaksi_detail d ON t.id = d.transaksi_id
            JOIN kantin_menu m ON d.menu_id = m.id
            WHERE t.siswa_id = :sid
            GROUP BY t.id
            ORDER BY t.created_at DESC
            LIMIT 40
        ");
        $stmtRk->execute([':sid' => $siswaId]);
        $riwayatKantin = $stmtRk->fetchAll();

        // Hitung pengeluaran minggu ini
        foreach ($riwayatKantin as $rk) {
            $tTime = strtotime($rk['created_at']);
            // Jika dalam 7 hari terakhir
            if ($tTime >= strtotime('-7 days')) {
                $weeklyKantinSpend += (float) $rk['total_harga'];
                $dayCode = date('D', $tTime);
                $mapDay = ['Mon' => 'Sen', 'Tue' => 'Sel', 'Wed' => 'Rab', 'Thu' => 'Kam', 'Fri' => 'Jum'][$dayCode] ?? '';
                if ($mapDay && isset($dailyKantinMap[$mapDay])) {
                    $dailyKantinMap[$mapDay] += (float) $rk['total_harga'];
                }
            }
        }
    } catch (Exception $e) {
        $saldoKantin = 0;
        $riwayatKantin = [];
    }
}

// 7. NOTIFIKASI PORTAL ORTU
$notifList = [];
$unreadNotifCount = 0;
if ($siswaId) {
    try {
        $stmtNotif = $pdo->prepare("
            SELECT * FROM notifikasi_ortu
            WHERE (siswa_id = :sid OR siswa_id IS NULL) AND is_dismissed = 0
            ORDER BY created_at DESC
            LIMIT 40
        ");
        $stmtNotif->execute([':sid' => $siswaId]);
        $notifList = $stmtNotif->fetchAll();

        $stmtUnread = $pdo->prepare("
            SELECT COUNT(id) FROM notifikasi_ortu
            WHERE (siswa_id = :sid OR siswa_id IS NULL) AND is_read = 0 AND is_dismissed = 0
        ");
        $stmtUnread->execute([':sid' => $siswaId]);
        $unreadNotifCount = (int) $stmtUnread->fetchColumn();
    } catch (Exception $e) {
        $notifList = [];
    }
}

// DATA MUTASI SALDO KANTIN SANTRI REAL DB
$mutasiSaldoList = [];
if ($siswaId) {
    try {
        $stmtMutasi = $pdo->prepare("
            SELECT m.*, u.nama_lengkap AS nama_petugas
            FROM mutasi_saldo m
            LEFT JOIN users u ON m.created_by = u.id
            WHERE m.siswa_id = :sid
            ORDER BY m.created_at DESC
            LIMIT 40
        ");
        $stmtMutasi->execute([':sid' => $siswaId]);
        $mutasiSaldoList = $stmtMutasi->fetchAll();
    } catch (Exception $e) {
        $mutasiSaldoList = [];
    }
}

// 8. DATA SETORAN HALAQAH & TAHFIDZ SISWA REAL DB
$halaqahSetoranList = [];
$tampilkanTargetOrtu = 1;
$targetHafalanText = "Juz 30 (Juz 'Amma) & Hadits Arba'in";
if ($siswaId) {
    try {
        $stmtHs = $pdo->prepare("
            SELECT hs.*, hk.nama_halaqah, hkat.nama_kategori, u.nama_lengkap AS nama_musyrif
            FROM halaqah_setoran hs
            LEFT JOIN halaqah_kelompok hk ON hs.kelompok_id = hk.id
            LEFT JOIN halaqah_kategori hkat ON hs.kategori_id = hkat.id
            LEFT JOIN users u ON hs.musyrif_id = u.id
            WHERE hs.siswa_id = :sid
            ORDER BY hs.tanggal DESC, hs.id DESC
            LIMIT 30
        ");
        $stmtHs->execute([':sid' => $siswaId]);
        $halaqahSetoranList = $stmtHs->fetchAll();

        $tVal = $pdo->query("SELECT setting_value FROM halaqah_settings WHERE setting_key = 'tampilkan_target_ortu'")->fetchColumn();
        if ($tVal !== false && $tVal !== null)
            $tampilkanTargetOrtu = (int) $tVal;
        $txtVal = $pdo->query("SELECT setting_value FROM halaqah_settings WHERE setting_key = 'target_hafalan_text'")->fetchColumn();
        if (!empty($txtVal))
            $targetHafalanText = $txtVal;
    } catch (Exception $e) {
        $halaqahSetoranList = [];
    }
}

// PROSES MARK ALL NOTIF AS READ
if (isset($_GET['action']) && $_GET['action'] === 'mark_notif_read' && $siswaId) {
    try {
        $stmtRead = $pdo->prepare("UPDATE notifikasi_ortu SET is_read = 1 WHERE siswa_id = :sid OR siswa_id IS NULL");
        $stmtRead->execute([':sid' => $siswaId]);
    } catch (Exception $e) {
    }
    header("Location: portal-ortu.php?tab=notifikasi");
    exit;
}

// PROSES DISMISS / HAPUS NOTIFIKASI INDIVIDUAL (PERMANENT - is_dismissed = 1)
if (isset($_GET['action']) && $_GET['action'] === 'dismiss_notif' && isset($_GET['id']) && $siswaId) {
    $notifId = (int) $_GET['id'];
    try {
        $stmtReadOne = $pdo->prepare("UPDATE notifikasi_ortu SET is_read = 1, is_dismissed = 1 WHERE id = :id AND (siswa_id = :sid OR siswa_id IS NULL)");
        $stmtReadOne->execute([':id' => $notifId, ':sid' => $siswaId]);
    } catch (Exception $e) {
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'id' => $notifId]);
        exit;
    }
    header("Location: portal-ortu.php?tab=notifikasi");
    exit;
}

// PROSES DISMISS PEMBAYARAN DITOLAK (PERMANENT - is_dismissed = 1)
if (isset($_GET['action']) && $_GET['action'] === 'dismiss_ditolak' && isset($_GET['id']) && $siswaId) {
    $ditolakId = (int) $_GET['id'];
    try {
        $stmtDismiss = $pdo->prepare("UPDATE pembayaran_pending SET is_dismissed = 1 WHERE id = :id AND siswa_id = :sid AND status = 'ditolak'");
        $stmtDismiss->execute([':id' => $ditolakId, ':sid' => $siswaId]);
    } catch (Exception $e) {
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'id' => $ditolakId]);
        exit;
    }
    header("Location: portal-ortu.php?tab=pembayaran");
    exit;
}

// PROSES GANTI PASSWORD USER ORTU
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ganti_password') {
    $passLama = $_POST['pass_lama'] ?? '';
    $passBaru = $_POST['pass_baru'] ?? '';
    $passKonf = $_POST['pass_konfirm'] ?? '';

    if ($userOrtu && password_verify($passLama, $userOrtu['password'])) {
        if ($passBaru === $passKonf && strlen($passBaru) >= 4) {
            $newHash = password_hash($passBaru, PASSWORD_DEFAULT);
            $stmtP = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id");
            $stmtP->execute([':p' => $newHash, ':id' => $userOrtu['id']]);
            $_SESSION['flash_ortu'] = ['type' => 'success', 'message' => 'Alhamdulillah, kata sandi berhasil diperbarui!'];
        } else {
            $_SESSION['flash_ortu'] = ['type' => 'danger', 'message' => 'Konfirmasi kata sandi baru tidak cocok atau kurang dari 4 karakter.'];
        }
    } else {
        $_SESSION['flash_ortu'] = ['type' => 'danger', 'message' => 'Kata sandi lama Anda tidak sesuai.'];
    }
    header("Location: portal-ortu.php?tab=profil");
    exit;
}

// PROSES GANTI FOTO PROFIL SISWA
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_foto' && $siswaId) {
    if (isset($_FILES['foto_siswa']) && $_FILES['foto_siswa']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['foto_siswa']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['foto_siswa']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ext, $allowed) && $_FILES['foto_siswa']['size'] <= 5 * 1024 * 1024) {
            $uploadDir = __DIR__ . '/uploads/siswa/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }

            // Hapus foto lama jika ada
            if (!empty($loggedSiswa['foto'])) {
                $old1 = $uploadDir . $loggedSiswa['foto'];
                $old2 = __DIR__ . '/' . $loggedSiswa['foto'];
                if (file_exists($old1))
                    @unlink($old1);
                if (file_exists($old2))
                    @unlink($old2);
            }

            $newFileName = 'siswa_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            if (move_uploaded_file($tmpName, $uploadDir . $newFileName)) {
                $stmtUp = $pdo->prepare("UPDATE siswa SET foto = :f WHERE id = :id");
                $stmtUp->execute([':f' => $newFileName, ':id' => $siswaId]);
                $_SESSION['flash_ortu'] = ['type' => 'success', 'message' => 'Alhamdulillah, foto profil siswa berhasil diperbarui!'];
            } else {
                $_SESSION['flash_ortu'] = ['type' => 'danger', 'message' => 'Gagal menyimpan foto. Silakan coba lagi.'];
            }
        } else {
            $_SESSION['flash_ortu'] = ['type' => 'warning', 'message' => 'Format foto harus JPG, PNG, atau WEBP (Maksimal 5MB).'];
        }
    }
    header("Location: portal-ortu.php");
    exit;
}

// PROSES SUBMIT TOP-UP KANTIN
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'topup_kantin' && $siswaId) {
    $nominal = (float) str_replace(['.', ','], ['', '.'], $_POST['nominal_topup'] ?? '0');
    $catatan = trim($_POST['catatan_topup'] ?? '');

    if ($nominal < 5000) {
        $_SESSION['flash_ortu'] = ['type' => 'warning', 'message' => 'Nominal top-up minimal Rp 5.000.'];
        header("Location: portal-ortu.php?tab=kantin");
        exit;
    }

    $dbPath = '';
    if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['bukti_transfer'];
        $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf', 'image/webp'];

        if (in_array($file['type'], $allowed) && $file['size'] <= 5 * 1024 * 1024) {
            $uploadDir = __DIR__ . '/assets/uploads/bukti/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'topup_' . time() . '_' . rand(1000, 9999) . '.' . $ext;

            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                $dbPath = 'assets/uploads/bukti/' . $filename;
            }
        }
    }

    if (empty($dbPath)) {
        $_SESSION['flash_ortu'] = ['type' => 'danger', 'message' => 'Bukti transfer wajib diunggah (Format: JPG, PNG, WEBP, PDF, maks 5MB).'];
        header("Location: portal-ortu.php?tab=kantin");
        exit;
    }

    $stmtIns = $pdo->prepare("INSERT INTO pembayaran_pending (siswa_id, jenis, jenis_pembayaran_id, bulan, tahun, nominal, bukti_transfer, catatan, status) VALUES (:s, 'topup_kantin', NULL, NULL, NULL, :n, :b, :c, 'pending')");
    $stmtIns->execute([
        ':s' => $siswaId,
        ':n' => $nominal,
        ':b' => $dbPath,
        ':c' => $catatan
    ]);

    $_SESSION['flash_ortu'] = ['type' => 'success', 'message' => 'Alhamdulillah, permohonan Top-Up Saldo E-Kantin sebesar Rp ' . number_format($nominal, 0, ',', '.') . ' telah terkirim! Silakan tunggu verifikasi admin kantin.'];
    header("Location: portal-ortu.php?tab=kantin");
    exit;
}

// PROSES SUBMIT PEMBAYARAN ONLINE
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_pembayaran') {
    $jenis_raw = $_POST['jenis'] ?? '';
    $nominal = (float) str_replace(['.', ','], ['', '.'], $_POST['nominal'] ?? '0');
    $catatan = trim($_POST['catatan'] ?? '');

    $fileOk = false;
    $dbPath = '';
    if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['bukti_transfer'];
        $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf', 'image/webp'];
        if (in_array($file['type'], $allowed) && $file['size'] <= 5 * 1024 * 1024) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'bukti_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $uploadDir = __DIR__ . '/assets/uploads/bukti/';
            if (!is_dir($uploadDir))
                @mkdir($uploadDir, 0755, true);
            $uploadPath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $dbPath = 'assets/uploads/bukti/' . $filename;
                $fileOk = true;
            }
        }
    }

    if ($siswaId && $nominal > 0 && $fileOk) {
        try {
            if ($jenis_raw === 'spp') {
                $bulanDipilih = $_POST['bulan_spp'] ?? [];
                $tahunSpp = (int) ($_POST['tahun_spp'] ?? date('Y'));
                if (!empty($bulanDipilih)) {
                    $nominalPerBulan = $nominal / count($bulanDipilih);
                    $pdo->beginTransaction();
                    $stmtIns = $pdo->prepare("INSERT INTO pembayaran_pending (siswa_id, jenis, jenis_pembayaran_id, bulan, tahun, nominal, bukti_transfer, catatan) VALUES (:s, 'spp', NULL, :b, :t, :n, :bukti, :c)");
                    foreach ($bulanDipilih as $bln) {
                        $stmtIns->execute([':s' => $siswaId, ':b' => (int) $bln, ':t' => $tahunSpp, ':n' => $nominalPerBulan, ':bukti' => $dbPath, ':c' => $catatan]);
                    }
                    $pdo->commit();
                    $_SESSION['flash_ortu'] = ['type' => 'success', 'message' => 'Alhamdulillah, konfirmasi pembayaran SPP berhasil dikirim!'];
                }
            } elseif ($jenis_raw === 'uang_pangkal') {
                $stmtIns = $pdo->prepare("INSERT INTO pembayaran_pending (siswa_id, jenis, jenis_pembayaran_id, bulan, tahun, nominal, bukti_transfer, catatan) VALUES (:s, 'uang_pangkal', NULL, NULL, NULL, :n, :bukti, :c)");
                $stmtIns->execute([':s' => $siswaId, ':n' => $nominal, ':bukti' => $dbPath, ':c' => $catatan]);
                $_SESSION['flash_ortu'] = ['type' => 'success', 'message' => 'Alhamdulillah, konfirmasi pembayaran Uang Pangkal berhasil dikirim!'];
            } elseif ($jenis_raw === 'topup_kantin') {
                $stmtIns = $pdo->prepare("INSERT INTO pembayaran_pending (siswa_id, jenis, jenis_pembayaran_id, bulan, tahun, nominal, bukti_transfer, catatan) VALUES (:s, 'topup_kantin', NULL, NULL, NULL, :n, :bukti, :c)");
                $stmtIns->execute([':s' => $siswaId, ':n' => $nominal, ':bukti' => $dbPath, ':c' => $catatan]);
                $_SESSION['flash_ortu'] = ['type' => 'success', 'message' => 'Alhamdulillah, pengajuan Top Up Uang Jajan Kantin berhasil dikirim! Silakan tunggu verifikasi admin.'];
            } elseif (strpos($jenis_raw, 'lainnya_') === 0) {
                $jpId = (int) str_replace('lainnya_', '', $jenis_raw);
                $stmtIns = $pdo->prepare("INSERT INTO pembayaran_pending (siswa_id, jenis, jenis_pembayaran_id, bulan, tahun, nominal, bukti_transfer, catatan) VALUES (:s, 'lainnya', :jp, NULL, NULL, :n, :bukti, :c)");
                $stmtIns->execute([':s' => $siswaId, ':jp' => $jpId, ':n' => $nominal, ':bukti' => $dbPath, ':c' => $catatan]);
                $_SESSION['flash_ortu'] = ['type' => 'success', 'message' => 'Alhamdulillah, konfirmasi pembayaran berhasil dikirim!'];
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction())
                $pdo->rollBack();
            $_SESSION['flash_ortu'] = ['type' => 'danger', 'message' => 'Gagal mengirim pembayaran: ' . $e->getMessage()];
        }
    } else {
        $_SESSION['flash_ortu'] = ['type' => 'warning', 'message' => 'Mohon lengkapi nominal dan upload foto bukti transfer.'];
    }
    header("Location: portal-ortu.php?tab=pembayaran");
    exit;
}

$activeTab = $_GET['tab'] ?? 'beranda';
$flash = $_SESSION['flash_ortu'] ?? null;
unset($_SESSION['flash_ortu']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($appName) ?> — Portal Orang Tua</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --app-bg: #f4f7fb;
            --brand-blue: #003b87;
            --brand-blue-light: #0a4b9c;
            --app-text: #0f172a;
            --app-text-muted: #64748b;
            --app-red: #d97706;
            --app-green: #22c55e;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--app-bg);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            color: var(--app-text);
            -webkit-font-smoothing: antialiased;
        }

        /* FLUID MOBILE CANVAS */
        .app-canvas {
            width: 100%;
            max-width: 480px;
            min-height: 100vh;
            background: #f4f7fb;
            position: relative;
            box-shadow: 0 15px 45px rgba(0, 59, 135, 0.08);
            overflow-x: hidden;
            padding-bottom: 95px;
            display: flex;
            flex-direction: column;
        }

        @media (min-width: 500px) {
            .app-canvas {
                margin: 20px auto;
                border-radius: 36px;
                min-height: 870px;
                border: 1px solid #e2e8f0;
            }
        }

        /* TOP NAVBAR HEADER */
        .top-navbar-header {
            padding: 1.25rem 1.25rem 0.75rem 1.25rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: transparent;
        }

        .brand-header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand-badge-logo {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--brand-blue);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            box-shadow: 0 4px 12px rgba(0, 59, 135, 0.25);
        }

        .brand-header-title {
            font-size: 1.12rem;
            font-weight: 800;
            color: var(--brand-blue);
            margin: 0;
            font-family: 'Outfit', sans-serif;
            line-height: 1.2;
        }

        .btn-bell-notif {
            background: transparent;
            border: none;
            color: var(--brand-blue);
            font-size: 1.25rem;
            position: relative;
            cursor: pointer;
            padding: 4px;
            transition: transform 0.2s ease;
        }

        .btn-bell-notif:hover {
            transform: scale(1.1);
        }

        .notif-dot-red {
            position: absolute;
            top: 2px;
            right: 2px;
            width: 10px;
            height: 10px;
            background: #ef4444;
            border-radius: 50%;
            border: 2px solid #f4f7fb;
        }

        /* MAIN CONTENT AREA */
        .app-main-content {
            padding: 0 1.25rem;
        }

        /* TAB VIEWS & SMOOTH PAGE SWITCHING ENTER ANIMATION */
        .tab-view {
            display: none;
        }

        .tab-view.active {
            display: block;
        }

        @keyframes pageEnter {
            0% {
                opacity: 0;
                transform: translateY(18px) scale(0.985);
            }

            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .tab-view.animate-page-enter {
            animation: pageEnter 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        /* HERO CARD PROFILE - REMOVED (Using .home-hero instead) */

        .avatar-container-hero {
            position: relative;
            display: inline-block;
            margin-bottom: 0.5rem;
        }

        .avatar-hero-img {
            width: 64px;
            height: 64px;
            border-radius: 20px;
            object-fit: cover;
            border: 3px solid #ffffff;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            background: linear-gradient(135deg, #bbdefb, #90caf9);
            color: #0d47a1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            font-weight: 800;
            position: relative;
        }

        .avatar-hero-img img {
            width: 100%;
            height: 100%;
            border-radius: 17px;
            object-fit: cover;
        }

        .btn-camera-badge {
            position: absolute;
            bottom: -4px;
            right: -4px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0d47a1, #1565c0);
            color: #ffffff;
            border: 3px solid #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            box-shadow: 0 4px 12px rgba(13, 71, 161, 0.35);
            transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
            cursor: pointer;
        }

        .btn-camera-badge:hover {
            transform: scale(1.2);
            box-shadow: 0 6px 16px rgba(13, 71, 161, 0.4);
        }

        .avatar-hero-img:hover .btn-camera-badge {
            transform: scale(1.2);
        }

        .online-status-dot {
            width: 18px;
            height: 18px;
            background: var(--app-green);
            border: 3px solid #ffffff;
            border-radius: 50%;
            position: absolute;
            top: -3px;
            right: -3px;
            animation: pulseGreen 2s infinite;
            box-shadow: 0 2px 8px rgba(34, 197, 94, 0.4);
            z-index: 2;
        }

        @keyframes pulseGreen {
            0% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.6), 0 2px 8px rgba(34, 197, 94, 0.4);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(34, 197, 94, 0), 0 2px 8px rgba(34, 197, 94, 0.4);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0), 0 2px 8px rgba(34, 197, 94, 0.4);
            }
        }

        .student-hero-name {
            font-size: 1.35rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 2px 0;
            font-family: 'Outfit', sans-serif;
        }

        .student-hero-sub {
            font-size: 0.82rem;
            color: var(--app-text-muted);
            margin: 0;
            font-weight: 600;
        }

        /* INNER SALDO KANTIN CARD - REMOVED (Using .home-wallet instead) */
        .inner-saldo-card {
            background: rgba(255, 255, 255, 0.12);
            border-radius: 18px;
            padding: 1.1rem 1.25rem;
            margin-top: 1.2rem;
            text-align: left;
            border: 1.5px solid rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        .saldo-card-label {
            font-size: 0.67rem;
            font-weight: 800;
            color: rgba(255, 255, 255, 0.82);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 0.25rem;
        }

        .saldo-card-value {
            font-size: 1.35rem;
            font-weight: 800;
            color: #ffffff;
            margin: 0.4rem 0 0 0;
            font-family: 'Outfit', sans-serif;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: -0.3px;
        }

        .btn-toggle-balance {
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            color: #ffffff;
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            cursor: pointer;
            padding: 0;
            transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }

        .btn-toggle-balance:hover {
            background: rgba(255, 255, 255, 0.28);
            transform: scale(1.06);
            color: #ffffff;
        }

        .btn-isi-saldo {
            background: rgba(255, 255, 255, 0.95);
            color: #0d47a1;
            border: none;
            border-radius: 12px;
            width: auto;
            padding: 0.65rem 1rem;
            font-size: 0.72rem;
            font-weight: 800;
            transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            float: right;
            margin-top: -2.8rem;
        }

        .btn-isi-saldo:hover,
        .btn-isi-saldo:active {
            background: #ffffff;
            transform: scale(1.05);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
        }

        /* SECTION TITLES */
        .section-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 0.5rem;
            margin-bottom: 1rem;
        }

        .section-title-text {
            font-size: 1.05rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            font-family: 'Outfit', sans-serif;
        }

        .section-link-blue {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--brand-blue-light);
            text-decoration: none;
        }

        /* 4 QUICK ACCESS ICONS GRID */
        .quick-access-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.75rem;
        }

        .quick-access-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            text-decoration: none;
            color: #0f172a;
        }

        .quick-icon-box {
            width: 58px;
            height: 58px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .quick-access-item:hover .quick-icon-box {
            transform: scale(1.08);
        }

        .bg-box-blue {
            background: #eff6ff;
            color: var(--brand-blue);
        }

        .bg-box-grey {
            background: #f1f5f9;
            color: #475569;
        }

        .bg-box-pink {
            background: #fef2f2;
            color: #ef4444;
        }

        .quick-icon-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-align: center;
            line-height: 1.25;
            color: #1e293b;
        }

        /* TAGIHAN CARDS */
        .tagihan-list-container {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1.75rem;
        }

        .card-tagihan-item {
            background: #ffffff;
            border-radius: 22px;
            padding: 1.15rem 1.35rem;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.03);
            border: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            transition: transform 0.2s ease;
        }

        .card-tagihan-item:hover {
            transform: translateY(-2px);
        }

        .tagihan-item-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .icon-box-tagihan {
            width: 44px;
            height: 44px;
            border-radius: 16px;
            background: #fef2f2;
            color: #ef4444;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .tagihan-item-title {
            font-size: 0.95rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 2px 0;
            font-family: 'Outfit', sans-serif;
        }

        .tagihan-item-sub {
            font-size: 0.75rem;
            color: #64748b;
            margin: 0;
            font-weight: 600;
        }

        .tagihan-item-right {
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
        }

        .tagihan-amount-text {
            font-size: 0.98rem;
            font-weight: 800;
            color: #c2410c;
            font-family: 'Outfit', sans-serif;
        }

        .btn-bayar-pill {
            background: var(--brand-blue);
            color: #ffffff;
            border: none;
            border-radius: 20px;
            padding: 5px 18px;
            font-size: 0.78rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0, 59, 135, 0.2);
        }

        .btn-bayar-pill:hover {
            background: var(--brand-blue-light);
            transform: scale(1.04);
        }

        /* TRANSAKSI KANTIN TERAKHIR CARD LIST */
        .kantin-list-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 1.15rem 1.35rem;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.03);
            border: 1px solid #f1f5f9;
            margin-bottom: 1.75rem;
        }

        .kantin-row-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .kantin-row-item:last-child {
            border-bottom: none;
        }

        .kantin-item-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .icon-box-kantin {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: #f8fafc;
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .kantin-item-name {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 2px 0;
        }

        .kantin-item-time {
            font-size: 0.74rem;
            color: #94a3b8;
            margin: 0;
        }

        .kantin-item-price {
            font-size: 0.9rem;
            font-weight: 800;
            color: #0f172a;
            font-family: 'Outfit', sans-serif;
        }

        /* HERO PENGELUARAN KANTIN MINGGU INI (MATCHING BERANDA SIGNATURE BLUE GRADIENT) */
        .hero-kantin-spend-card {
            background: linear-gradient(135deg, #092040 0%, #0d47a1 45%, #1565c0 80%, #1e88e5 100%);
            border-radius: 28px;
            padding: 1.5rem 1.5rem;
            color: #ffffff;
            margin-bottom: 1.5rem;
            box-shadow: 0 20px 48px -10px rgba(13, 71, 161, 0.32), 0 0 0 1px rgba(255, 255, 255, 0.16) inset;
            border: 1px solid rgba(255, 255, 255, 0.18);
            position: relative;
            overflow: hidden;
        }

        .spend-card-label {
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #e0f2fe;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .spend-card-amount {
            font-size: 1.95rem;
            font-weight: 800;
            margin: 6px 0 14px 0;
            font-family: 'Outfit', sans-serif;
            letter-spacing: -0.02em;
            color: #ffffff;
        }

        .daily-spend-pills {
            display: flex;
            gap: 10px;
            justify-content: space-between;
        }

        .daily-pill-item {
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.22);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 8px 6px;
            text-align: center;
            flex: 1;
            transition: all 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .daily-pill-day {
            font-size: 0.68rem;
            opacity: 0.85;
            display: block;
        }

        .daily-pill-val {
            font-size: 0.78rem;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
        }

        /* FLOATING PILL BOTTOM NAVIGATION BAR (FIXED STICKY ON HP SCREEN) */
        .floating-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 480px;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-top: 1px solid rgba(226, 232, 240, 0.9);
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 8px 10px 10px 10px;
            z-index: 1030;
            box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.08);
            transition: all 0.25s ease;
        }

        body.modal-open .floating-bottom-nav {
            transform: translate(-50%, 100%) !important;
            opacity: 0;
            pointer-events: none;
        }

        @media (min-width: 500px) {
            .floating-bottom-nav {
                border-radius: 0 0 34px 34px;
            }
        }

        .nav-tab-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex: 1;
            max-width: 80px;
            color: #64748b;
            text-decoration: none;
            cursor: pointer;
            border: none;
            background: transparent;
            padding: 0;
            margin: 0;
            transition: transform 0.15s ease;
        }

        .nav-tab-item:active {
            transform: scale(0.92);
        }

        .nav-tab-icon-wrapper {
            width: 52px;
            height: 32px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            color: #64748b;
            background: transparent;
        }

        .nav-tab-icon-wrapper i {
            font-size: 1.25rem;
            line-height: 1;
            transition: all 0.2s ease;
        }

        .nav-tab-label {
            font-size: 0.72rem;
            font-weight: 600;
            color: #64748b;
            margin-top: 4px;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            transition: all 0.2s ease;
        }

        .nav-tab-item.active .nav-tab-icon-wrapper {
            color: #0284c7;
            background: #e0f2fe;
            box-shadow: 0 2px 8px rgba(2, 132, 199, 0.15);
        }

        .nav-tab-item.active .nav-tab-icon-wrapper i {
            transform: scale(1.1);
            color: #0284c7;
        }

        .nav-tab-item.active .nav-tab-label {
            color: #0284c7;
            font-weight: 800;
        }

        .widget-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 1.35rem 1.45rem;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.03);
            border: 1px solid #f1f5f9;
            margin-bottom: 1.5rem;
        }

        .notif-item-card {
            user-select: none;
        }

        .notif-item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 59, 135, 0.08);
        }

        .btn-close-notif-item {
            border: none;
            background: transparent;
            color: #94a3b8;
            font-size: 0.85rem;
            padding: 2px 6px;
            border-radius: 50%;
            transition: all 0.2s ease;
            position: absolute;
            top: 10px;
            right: 10px;
            line-height: 1;
            cursor: pointer;
        }

        .btn-close-notif-item:hover {
            color: #ef4444;
            background: #fee2e2;
        }

        /* TAHFIDZ CATEGORY FILTER PILLS */
        .tahfidz-filter-pill {
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #475569;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 5px 14px;
            border-radius: 50px;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .tahfidz-filter-pill:hover {
            background: #f1f5f9;
            color: #0f172a;
            border-color: #94a3b8;
        }

        .tahfidz-filter-pill.active {
            background: #003b87;
            color: #ffffff;
            border-color: #003b87;
            box-shadow: 0 3px 10px rgba(0, 59, 135, 0.22);
        }

        /* MAPEL ACCORDION DROPDOWN STYLES */
        .mapel-accordion-item {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 18px rgba(0, 59, 135, 0.03);
            margin-bottom: 0.85rem;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .mapel-accordion-item:hover {
            border-color: #cbd5e1;
            box-shadow: 0 8px 25px rgba(0, 59, 135, 0.07);
        }

        .mapel-accordion-header {
            padding: 1.1rem 1.25rem;
            cursor: pointer;
            user-select: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            transition: background-color 0.2s ease;
        }

        .mapel-accordion-header:hover {
            background: #f8fafc;
        }

        .mapel-accordion-header .chevron-icon {
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            color: var(--brand-blue);
            font-size: 1.1rem;
        }

        .mapel-accordion-item.open .mapel-accordion-header .chevron-icon {
            transform: rotate(180deg);
        }

        .mapel-accordion-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s cubic-bezier(0.16, 1, 0.3, 1), padding 0.3s ease;
            padding: 0 1.25rem;
            background: #fafcff;
            border-top: 1px solid transparent;
        }

        .mapel-accordion-item.open .mapel-accordion-body {
            max-height: 500px;
            padding: 1rem 1.25rem 1.25rem 1.25rem;
            border-top-color: #f1f5f9;
        }

        /* TAHFIDZ STATS GRID */
        .tahfidz-stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 1rem;
        }

        .tahfidz-stat-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 0.85rem 0.5rem;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
        }

        .tahfidz-stat-val {
            font-size: 1.25rem;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            color: var(--brand-blue);
            line-height: 1;
            margin-bottom: 3px;
        }

        .tahfidz-stat-lbl {
            font-size: 0.65rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
        }

        /* KESANTRIAN & TAHFIDZ STYLING */
        .kesantrian-shell {
            padding-bottom: 1.5rem;
        }

        .kesantrian-segmented {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            padding: 6px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            margin-bottom: 1.5rem;
        }

        .kesantrian-seg {
            border: 0 !important;
            border-radius: 13px !important;
            color: #64748b !important;
            background: transparent !important;
            font-size: 0.82rem;
            font-weight: 800;
            padding: 0.72rem 0.5rem;
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .kesantrian-seg.active {
            color: #1d4ed8 !important;
            background: #ffffff !important;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.14);
        }

        .poin-summary-card {
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 28px;
            padding: 1.5rem 1.5rem;
            color: #fff;
            background: linear-gradient(135deg, #092040 0%, #0d47a1 45%, #1565c0 80%, #1e88e5 100%);
            box-shadow: 0 20px 48px -10px rgba(13, 71, 161, 0.32), 0 0 0 1px rgba(255, 255, 255, 0.16) inset;
            position: relative;
            overflow: hidden;
            transform: translateZ(0);
            margin-bottom: 1.5rem;
        }

        .tahfidz-hero-card {
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 28px;
            padding: 1.5rem 1.5rem;
            color: #fff;
            background: linear-gradient(135deg, #092040 0%, #0d47a1 45%, #1565c0 80%, #1e88e5 100%);
            box-shadow: 0 20px 48px -10px rgba(13, 71, 161, 0.32), 0 0 0 1px rgba(255, 255, 255, 0.16) inset;
            position: relative;
            overflow: hidden;
            transform: translateZ(0);
            margin-bottom: 1.5rem;
        }

        .tahfidz-filter-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 50px;
            font-size: 0.74rem;
            font-weight: 800;
            background: #ffffff;
            color: #475569;
            border: 1.5px solid #e2e8f0;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
        }

        .tahfidz-filter-pill:hover {
            border-color: #93c5fd;
            color: #1d4ed8;
            background: #f8fbff;
        }

        .tahfidz-filter-pill.active {
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #3b82f6;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.12);
        }

        .poin-summary-card .summary-label {
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #e0f2fe;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .poin-summary-card .summary-score {
            font-family: 'Outfit', sans-serif;
            font-size: 2.75rem;
            font-weight: 800;
            line-height: 1;
            margin: 0.45rem 0 0.25rem 0;
            color: #ffffff;
            letter-spacing: -0.02em;
        }

        .poin-breakdown {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 1.25rem;
        }

        .poin-breakdown-item {
            padding: 0.85rem 1rem;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.22);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: all 0.2s ease;
        }

        .poin-breakdown-item span {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.66rem;
            font-weight: 800;
            color: #bae6fd;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .poin-breakdown-item strong {
            display: block;
            font-size: 1.25rem;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            color: #ffffff;
            margin-top: 4px;
        }

        .poin-history-card {
            border: 1.5px solid #e2e8f0;
            border-radius: 24px;
            padding: 1.4rem;
            background: #fff;
            box-shadow: 0 4px 18px rgba(13, 71, 161, 0.04);
            margin-bottom: 1.5rem;
            transition: all 0.28s ease;
        }

        .poin-history-card:hover {
            border-color: #93c5fd;
            box-shadow: 0 10px 28px -4px rgba(13, 71, 161, 0.1);
        }

        .poin-history-title {
            font-family: 'Outfit', sans-serif;
            font-size: 0.98rem;
            font-weight: 800;
            margin: 0;
            color: #1e293b;
        }

        .poin-history-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.95rem 0;
            border-bottom: 1px solid #eef2f7;
        }

        .poin-history-item:last-child {
            border-bottom: 0;
        }

        .poin-history-icon {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 14px;
            background: #f1f5f9;
            color: #475569;
        }

        .poin-history-name {
            font-size: .82rem;
            font-weight: 800;
            color: #1e293b;
        }

        .poin-history-detail {
            display: block;
            margin-top: .2rem;
            font-size: .7rem;
            color: #64748b;
        }

        .poin-value {
            flex: 0 0 auto;
            border-radius: 999px;
            padding: .35rem .65rem;
            font-size: .74rem;
            font-weight: 800;
        }

        .poin-value.reward {
            color: #047857;
            background: #d1fae5;
        }

        .poin-value.violation {
            color: #b91c1c;
            background: #fee2e2;
        }

        .poin-state {
            padding: 1.5rem .5rem;
            text-align: center;
            color: #64748b;
            font-size: .78rem;
        }

        .poin-state .btn {
            font-size: .72rem;
            font-weight: 700;
            border-radius: 10px;
        }

        .poin-ranking-card {
            margin-top: 1.5rem;
            padding: 1.35rem;
            border: 1.5px solid #dbeafe;
            border-radius: 24px;
            background: linear-gradient(135deg, #fff 0%, #f0f7ff 100%);
            box-shadow: 0 4px 18px rgba(13, 71, 161, 0.04);
        }

        .poin-ranking-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .poin-ranking-title {
            font-family: 'Outfit', sans-serif;
            font-size: .98rem;
            font-weight: 800;
            margin: 0;
            color: #12315d;
        }

        .poin-rank-spot {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .8rem 0;
            border-bottom: 1px solid #dbeafe;
        }

        .poin-rank-badge {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            color: #0758b8;
            background: #dbeafe;
            font-size: .8rem;
            font-weight: 800;
        }

        .poin-rank-badge.top {
            color: #8a5500;
            background: #fef3c7;
        }

        .poin-rank-name {
            font-size: .78rem;
            font-weight: 800;
            color: #1e3a5f;
        }

        .poin-rank-meta {
            font-size: .67rem;
            color: #64748b;
        }

        .poin-rank-score {
            margin-left: auto;
            color: #0758b8;
            font-size: .82rem;
            font-weight: 800;
        }

        .kesantrian-tahfidz-mount {
            margin-top: .9rem;
        }

        .kesantrian-tahfidz-mount .widget-card {
            border: 1px solid #e8edf7;
            border-radius: 22px;
            box-shadow: 0 10px 25px rgba(71, 85, 105, .07);
        }

        .kesantrian-tahfidz-mount .tahfidz-filter-pill {
            border: 0;
            border-radius: 999px;
            padding: .46rem .75rem;
            background: #f1f5f9;
            color: #475569;
            font-size: .68rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .kesantrian-tahfidz-mount .tahfidz-filter-pill.active {
            background: #ede9fe;
            color: #6d28d9;
        }

        #tab-nilai #tahfidzProgressPanel {
            display: none;
        }

        /* BERANDA — ringkasan yang lebih aplikatif dan mudah dipindai */
        /* MODERN BLUE HERO CARD (PRECISE, PREMIUM & WOW) */
        .home-hero {
            position: relative;
            overflow: hidden;
            padding: 1.5rem;
            border-radius: 28px;
            color: #ffffff;
            background: linear-gradient(135deg, #092040 0%, #0d47a1 45%, #1565c0 80%, #1e88e5 100%);
            box-shadow: 0 20px 48px -10px rgba(13, 71, 161, 0.32), 0 0 0 1px rgba(255, 255, 255, 0.16) inset;
            border: 1px solid rgba(255, 255, 255, 0.18);
            min-height: 230px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .home-hero::before {
            content: '';
            position: absolute;
            width: 320px;
            height: 320px;
            right: -100px;
            top: -100px;
            background: radial-gradient(circle, rgba(56, 189, 248, 0.22) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .home-hero::after {
            content: '';
            position: absolute;
            width: 220px;
            height: 220px;
            left: -60px;
            bottom: -60px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.18) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .home-hero>* {
            position: relative;
            z-index: 1;
        }

        .home-greeting {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: 30px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.22);
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #e0f2fe;
            width: fit-content;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
        }

        .home-profile {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 0.9rem;
            margin-bottom: 1.1rem;
        }

        .home-avatar {
            position: relative;
            flex: 0 0 auto;
            width: 64px;
            height: 64px;
            overflow: visible;
            border: 3px solid rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.25);
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
        }

        .home-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 17px;
        }

        .home-avatar-fallback {
            display: grid;
            width: 100%;
            height: 100%;
            place-items: center;
            border-radius: 17px;
            background: linear-gradient(135deg, #bbdefb 0%, #90caf9 100%);
            color: #0d47a1;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.6rem;
        }

        .home-avatar .online-status-dot {
            top: -4px;
            right: -4px;
            width: 18px;
            height: 18px;
            border-width: 3px;
            z-index: 2;
        }

        .home-avatar .btn-camera-badge {
            position: absolute;
            bottom: -4px;
            right: -4px;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0d47a1, #1565c0);
            color: #ffffff;
            border: 2px solid #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            box-shadow: 0 4px 12px rgba(13, 71, 161, 0.4);
            transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
            cursor: pointer;
            z-index: 2;
        }

        .home-avatar .btn-camera-badge:hover {
            transform: scale(1.2);
        }

        .home-student-name {
            margin: 0;
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.4px;
            line-height: 1.25;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
        }

        .home-student-meta {
            margin-top: 0.35rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            color: #f1f5f9;
            font-size: 0.76rem;
            font-weight: 600;
        }

        .home-wallet {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            padding: 0.85rem 1.15rem;
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        .home-wallet-info {
            min-width: 0;
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .home-wallet-label {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: rgba(241, 245, 249, 0.88);
            font-size: 0.64rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            line-height: 1;
            margin-bottom: 2px;
        }

        .home-wallet-value {
            display: flex;
            align-items: center;
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-size: clamp(1.12rem, 4vw, 1.35rem);
            font-weight: 800;
            letter-spacing: -0.3px;
            line-height: 1.15;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
            white-space: nowrap;
        }

        .home-wallet-tools {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .home-wallet .btn-toggle-balance {
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 12px;
            color: #ffffff;
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            cursor: pointer;
            padding: 0;
            transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }

        .home-wallet .btn-toggle-balance:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.06);
            color: #ffffff;
        }

        .home-topup-btn {
            border: none;
            border-radius: 12px;
            padding: 0.52rem 0.85rem;
            background: #ffffff;
            color: #0d47a1;
            font-size: 0.75rem;
            font-weight: 800;
            white-space: nowrap;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
            transition: all 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .home-topup-btn:hover {
            background: #ffffff;
            color: #1d4ed8;
            transform: translateY(-2px) scale(1.04);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.2);
        }

        /* =========================================================
           ULTRA-MODERN ALL-BLUE QUICK ACCESS TILES (AKSES CEPAT)
           ========================================================= */
        .home-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 1.5rem 0 0.85rem;
        }

        .home-section-title-wrap {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
        }

        .home-section-indicator {
            width: 5px;
            height: 18px;
            border-radius: 99px;
            background: linear-gradient(180deg, #38bdf8 0%, #0d47a1 100%);
            box-shadow: 0 2px 8px rgba(13, 71, 161, 0.35);
        }

        .home-section-title {
            margin: 0;
            font-family: 'Outfit', sans-serif;
            font-size: 1.08rem;
            font-weight: 800;
            color: #0a2540;
            letter-spacing: -0.3px;
        }

        .home-section-pill-tag {
            font-size: 0.66rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 3px 10px;
            border-radius: 99px;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .home-quick-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.75rem;
        }

        @keyframes quickTileEnter {
            0% {
                opacity: 0;
                transform: translateY(14px) scale(0.96);
            }

            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .home-quick-action {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            padding: 1.15rem 0.75rem;
            min-height: 108px;
            border-radius: 20px;
            background: linear-gradient(145deg, #ffffff 0%, #f8faff 100%);
            border: 1.5px solid #e2e8f0;
            color: #0f2744;
            text-align: center;
            text-decoration: none;
            box-shadow: 0 4px 14px -2px rgba(13, 71, 161, 0.05), inset 0 1px 0 #ffffff;
            transition: all 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
            cursor: pointer;
            overflow: hidden;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
            animation: quickTileEnter 0.4s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        .home-quick-action:nth-child(1) {
            animation-delay: 0.03s;
        }

        .home-quick-action:nth-child(2) {
            animation-delay: 0.06s;
        }

        .home-quick-action:nth-child(3) {
            animation-delay: 0.09s;
        }

        .home-quick-action:nth-child(4) {
            animation-delay: 0.12s;
        }

        .home-quick-action:hover {
            border-color: rgba(37, 99, 235, 0.4);
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 12px 28px -4px rgba(13, 71, 161, 0.14), inset 0 1px 0 #ffffff;
            background: linear-gradient(145deg, #ffffff 0%, #f0f7ff 100%);
        }

        .home-quick-action:active {
            transform: scale(0.97);
            box-shadow: 0 2px 8px rgba(13, 71, 161, 0.08);
            transition-duration: 0.1s;
        }

        .home-quick-icon {
            position: relative;
            z-index: 2;
            display: grid;
            width: 44px;
            height: 44px;
            place-items: center;
            border-radius: 14px;
            font-size: 1.25rem;
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            color: #1d4ed8;
            border: 1px solid rgba(59, 130, 246, 0.22);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            margin: 0 auto;
        }

        .home-quick-action:hover .home-quick-icon {
            background: linear-gradient(135deg, #0d47a1 0%, #1d4ed8 50%, #2563eb 100%);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.4);
            transform: scale(1.1) rotate(4deg);
            box-shadow: 0 8px 20px rgba(29, 78, 216, 0.38);
        }

        .home-quick-label {
            position: relative;
            z-index: 2;
            font-size: 0.88rem;
            font-weight: 800;
            color: #0a2540;
            letter-spacing: -0.2px;
            font-family: 'Outfit', sans-serif;
            line-height: 1.25;
            margin: 0;
            text-align: center;
            transition: color 0.2s ease;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
        }

        .home-quick-action:hover .home-quick-label {
            color: #0d47a1;
        }

        /* =========================================================
           PORTAL-WIDE UNIFIED MODERN ANIMATIONS & MICRO-INTERACTIONS
           ========================================================= */
        @keyframes pageTabEnter {
            0% {
                opacity: 0;
                transform: translateY(12px) scale(0.985);
            }

            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .tab-view.active {
            animation: pageTabEnter 0.32s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        /* Tagihan Aktif Interactive Card Enhancements */
        .card-tagihan-item {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            padding: 1.15rem 1.25rem;
            background: #ffffff;
            border-radius: 22px;
            border: 1.5px solid #f1f5f9;
            box-shadow: 0 4px 18px -2px rgba(13, 71, 161, 0.05), 0 2px 6px rgba(0, 0, 0, 0.02);
            transition: all 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
            margin-bottom: 0.85rem;
        }

        .card-tagihan-item:hover {
            transform: translateY(-3px) scale(1.01);
            border-color: rgba(37, 99, 235, 0.3) !important;
            box-shadow: 0 12px 28px -4px rgba(13, 71, 161, 0.12);
        }

        .tagihan-card-main {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            width: 100%;
        }

        .tagihan-item-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .tagihan-info-wrap {
            min-width: 0;
        }

        .tagihan-item-title {
            margin: 0;
            font-size: 0.94rem;
            font-weight: 800;
            color: #0f172a;
            font-family: 'Outfit', sans-serif;
            letter-spacing: -0.2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .tagihan-item-sub {
            margin: 2px 0 0;
            font-size: 0.72rem;
            color: #64748b;
            font-weight: 600;
        }

        .tagihan-item-right {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
            text-align: right;
        }

        .tagihan-amount-wrap {
            text-align: right;
        }

        .tagihan-sisa-label {
            display: block;
            font-size: 0.62rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 1px;
        }

        .tagihan-amount-text {
            font-size: 0.98rem;
            font-weight: 800;
            color: #c2410c;
            font-family: 'Outfit', sans-serif;
            letter-spacing: -0.3px;
            white-space: nowrap;
        }

        .badge-cicilan-pill {
            display: inline-flex;
            align-items: center;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 99px;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            white-space: nowrap;
        }

        .tagihan-progress-box {
            width: 100%;
            padding-top: 0.75rem;
            border-top: 1px dashed #e2e8f0;
        }

        .progress-meta-text {
            font-size: 0.72rem;
            color: #475569;
            font-weight: 600;
        }

        .progress-meta-percent {
            font-size: 0.76rem;
            font-weight: 800;
            color: #1d4ed8;
            font-family: 'Outfit', sans-serif;
        }

        .tagihan-progress-track {
            width: 100%;
            height: 7px;
            background: #e2e8f0;
            border-radius: 99px;
            overflow: hidden;
            margin-top: 5px;
        }

        .tagihan-progress-fill {
            height: 100%;
            border-radius: 99px;
            background: linear-gradient(90deg, #38bdf8 0%, #2563eb 100%);
            transition: width 0.6s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 1px 4px rgba(37, 99, 235, 0.35);
        }

        .icon-box-tagihan {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
            transition: all 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .card-tagihan-item:hover .icon-box-tagihan {
            background: linear-gradient(135deg, #0d47a1 0%, #1d4ed8 100%);
            color: #ffffff;
            transform: scale(1.1) rotate(-4deg);
        }

        .btn-bayar-pill {
            position: relative;
            overflow: hidden;
            background: var(--brand-blue);
            color: #ffffff;
            border: none;
            border-radius: 20px;
            padding: 5px 18px;
            font-size: 0.78rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 4px 12px rgba(0, 59, 135, 0.2);
        }

        .btn-bayar-pill:hover {
            background: linear-gradient(135deg, #0d47a1 0%, #1d4ed8 100%);
            transform: translateY(-2px) scale(1.06);
            box-shadow: 0 6px 18px rgba(13, 71, 161, 0.35);
        }

        .btn-bayar-pill:active {
            transform: scale(0.95);
        }

        /* Kantin List Card & Items */
        .kantin-row-item {
            margin: 0 -0.5rem;
            padding: 0.75rem 0.5rem;
            border-radius: 16px;
            transition: all 0.22s ease;
        }

        .kantin-row-item:hover {
            background: #f8faff;
            transform: translateX(4px);
        }

        .icon-box-kantin {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #dbeafe;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
            transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .kantin-row-item:hover .icon-box-kantin {
            background: linear-gradient(135deg, #0d47a1 0%, #1d4ed8 100%);
            color: #ffffff;
            transform: scale(1.1) rotate(4deg);
        }

        /* Hero Kantin Spend Card Animation */
        .hero-kantin-spend-card {
            transition: all 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .hero-kantin-spend-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 44px -8px rgba(13, 71, 161, 0.4);
        }

        .daily-pill-item {
            transition: all 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .daily-pill-item:hover {
            background: rgba(255, 255, 255, 0.28);
            transform: translateY(-3px) scale(1.06);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }

        /* ACADEMIC SUMMARY HERO CARD */
        .academic-summary-card {
            position: relative;
            overflow: hidden;
            padding: 1.25rem 1.35rem;
            border-radius: 24px;
            color: #ffffff;
            background: linear-gradient(135deg, #092040 0%, #0d47a1 50%, #1d4ed8 100%);
            box-shadow: 0 12px 30px -4px rgba(13, 71, 161, 0.28);
            margin-bottom: 1.25rem;
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .academic-summary-card::before {
            content: '';
            position: absolute;
            width: 220px;
            height: 220px;
            right: -60px;
            top: -60px;
            background: radial-gradient(circle, rgba(56, 189, 248, 0.25) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .academic-stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 1rem;
        }

        .academic-stat-pod {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 0.75rem 0.5rem;
            text-align: center;
            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .academic-stat-pod:hover {
            transform: translateY(-2px) scale(1.03);
            background: rgba(255, 255, 255, 0.18);
        }

        .academic-stat-val {
            font-size: 1.3rem;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            line-height: 1;
            margin-bottom: 3px;
            color: #ffffff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .academic-stat-lbl {
            font-size: 0.62rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: rgba(224, 242, 254, 0.9);
        }

        /* MAPEL ACCORDION & SCORE BOXES - SPACIOUS & MODERN BLUE */
        .mapel-accordion-item {
            background: #ffffff;
            border-radius: 22px;
            border: 1.5px solid #e2e8f0;
            box-shadow: 0 4px 18px rgba(13, 71, 161, 0.04);
            margin-bottom: 1rem;
            overflow: hidden;
            transition: all 0.28s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .mapel-accordion-item:hover {
            border-color: #93c5fd;
            box-shadow: 0 10px 28px -4px rgba(13, 71, 161, 0.1);
            transform: translateY(-2px);
        }

        .mapel-accordion-item.open {
            border-color: #60a5fa;
            box-shadow: 0 10px 28px -2px rgba(37, 99, 235, 0.12);
        }

        .mapel-accordion-header {
            padding: 1.1rem 1.25rem;
            cursor: pointer;
            user-select: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            transition: background-color 0.2s ease;
        }

        .mapel-accordion-header:hover {
            background: #f8fbff;
        }

        .mapel-accordion-header .chevron-icon {
            transition: transform 0.32s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .mapel-accordion-item.open .mapel-accordion-header .chevron-icon {
            transform: rotate(180deg);
        }

        .mapel-icon-pod {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            font-size: 1.25rem;
            flex-shrink: 0;
            transition: all 0.25s ease;
        }

        .mapel-accordion-item:hover .mapel-icon-pod {
            transform: scale(1.08) rotate(3deg);
        }

        .mapel-title {
            font-size: 0.96rem;
            font-weight: 800;
            color: #0f172a;
            font-family: 'Outfit', sans-serif;
            line-height: 1.25;
            margin: 0;
        }

        .mapel-meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 8px;
            font-size: 0.68rem;
            font-weight: 700;
        }

        .mapel-meta-kkm {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .mapel-meta-avg {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            font-weight: 800;
        }

        .mapel-accordion-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.35s cubic-bezier(0.16, 1, 0.3, 1), padding 0.28s ease;
            padding: 0 1.25rem;
            background: #f8fbff;
            border-top: 1px solid transparent;
        }

        .mapel-accordion-item.open .mapel-accordion-body {
            max-height: 700px;
            padding: 1.2rem 1.25rem 1.4rem 1.25rem;
            border-top-color: #e2e8f0;
        }

        @media (max-width: 480px) {
            .mapel-accordion-body {
                padding: 0 0.85rem;
            }

            .mapel-accordion-item.open .mapel-accordion-body {
                padding: 1rem 0.85rem 1.2rem 0.85rem;
            }
        }

        .akademik-subhead {
            font-size: 0.68rem;
            font-weight: 800;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 8px;
            margin-left: 2px;
        }

        /* SUMATIF 4-BOX GRID */
        .sumatif-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 1.15rem;
        }

        .sumatif-card {
            background: #ffffff;
            border: 1.5px solid #dbeafe;
            border-radius: 14px;
            padding: 0.65rem 0.35rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(13, 71, 161, 0.03);
            transition: all 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .sumatif-card:hover {
            border-color: #3b82f6;
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(37, 99, 235, 0.12);
        }

        .sumatif-label {
            display: block;
            font-size: 0.62rem;
            font-weight: 800;
            color: #1d4ed8;
            background: #eff6ff;
            padding: 2px 4px;
            border-radius: 6px;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .sumatif-score {
            font-size: 1.15rem;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            color: #0f172a;
            line-height: 1.1;
        }

        /* ATS & AAS EXAM CARDS */
        .exam-score-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        @media (max-width: 480px) {
            .exam-score-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }
        }

        .exam-score-card {
            border-radius: 16px;
            padding: 0.85rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            transition: all 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .exam-score-card:hover {
            transform: translateY(-2px);
        }

        .exam-score-card.ats {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border: 1.5px solid #bfdbfe;
        }

        .exam-score-card.aas {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 1.5px solid #bbf7d0;
        }

        /* Semantic empty state styling */
        .sumatif-card.empty {
            background: #f8fafc;
            border-color: #e2e8f0;
            opacity: 0.65;
            box-shadow: none;
        }

        .sumatif-card.empty .sumatif-label {
            background: #e2e8f0;
            color: #475569;
        }

        .sumatif-card.empty .sumatif-score {
            color: #94a3b8;
        }

        .sumatif-card.filled {
            background: #ffffff;
            border-color: #bfdbfe;
        }

        .exam-score-card.empty {
            background: #f8fafc !important;
            border: 1.5px solid #e2e8f0 !important;
            opacity: 0.65;
            box-shadow: none !important;
        }

        .exam-score-card.empty .exam-icon-circle {
            color: #94a3b8 !important;
            background: #e2e8f0 !important;
        }

        .exam-score-card.empty .exam-label {
            color: #475569 !important;
        }

        .exam-score-card.empty .exam-value {
            color: #94a3b8 !important;
        }

        .exam-score-card.ats:hover {
            box-shadow: 0 6px 16px rgba(29, 78, 216, 0.14);
        }

        .exam-score-card.aas:hover {
            box-shadow: 0 6px 16px rgba(22, 163, 74, 0.14);
        }

        .exam-icon-circle {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #ffffff;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
            font-size: 0.95rem;
        }

        .exam-score-card.ats .exam-icon-circle {
            color: #1d4ed8;
        }

        .exam-score-card.aas .exam-icon-circle {
            color: #166534;
        }

        .exam-label {
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.02em;
            margin-bottom: 2px;
        }

        .exam-score-card.ats .exam-label {
            color: #1d4ed8;
        }

        .exam-score-card.aas .exam-label {
            color: #15803d;
        }

        .exam-sub {
            font-size: 0.62rem;
            color: #64748b;
            font-weight: 600;
        }

        .exam-value {
            font-size: 1.35rem;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            line-height: 1;
        }

        .exam-score-card.ats .exam-value {
            color: #0d47a1;
        }

        .exam-score-card.aas .exam-value {
            color: #166534;
        }

        /* ACADEMIC HERO CARD (MATCHING BERANDA SIGNATURE BLUE GRADIENT) */
        .hero-akademik-card {
            background: linear-gradient(135deg, #092040 0%, #0d47a1 45%, #1565c0 80%, #1e88e5 100%);
            border-radius: 28px;
            padding: 1.5rem;
            color: #ffffff;
            box-shadow: 0 20px 48px -10px rgba(13, 71, 161, 0.32), 0 0 0 1px rgba(255, 255, 255, 0.16) inset;
            border: 1px solid rgba(255, 255, 255, 0.18);
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
            transform: translateZ(0);
        }

        .hero-akademik-kicker {
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 50px;
            font-weight: 800;
            font-size: 0.68rem;
            padding: 4px 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            letter-spacing: 0.4px;
            margin-bottom: 0.65rem;
            color: #e0f2fe;
        }

        .hero-akademik-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.3rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 0.25rem;
            letter-spacing: -0.02em;
            color: #ffffff;
        }

        .hero-akademik-sub {
            font-size: 0.76rem;
            color: rgba(255, 255, 255, 0.82);
            margin-bottom: 1.15rem;
        }

        .hero-akademik-stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }

        .hero-akademik-stat-item {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 16px;
            padding: 0.75rem 0.5rem;
            text-align: center;
            backdrop-filter: blur(4px);
            transition: transform 0.2s ease, background 0.2s ease;
        }

        .hero-akademik-stat-item:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.2);
        }

        .hero-akademik-stat-val {
            font-family: 'Outfit', sans-serif;
            font-size: 1.25rem;
            font-weight: 800;
            line-height: 1;
            color: #ffffff;
        }

        .hero-akademik-stat-lbl {
            font-size: 0.62rem;
            font-weight: 700;
            color: #bae6fd;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            margin-top: 5px;
        }

        /* Widget Card Hover Elevation */
        .widget-card {
            transition: all 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .widget-card:hover {
            border-color: rgba(37, 99, 235, 0.2);
            box-shadow: 0 10px 28px -4px rgba(13, 71, 161, 0.1);
        }

        /* Tahfidz Stats & Kesantrian Cards */
        .tahfidz-stat-box {
            transition: all 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .tahfidz-stat-box:hover {
            transform: translateY(-3px) scale(1.03);
            border-color: #93c5fd;
            box-shadow: 0 8px 22px rgba(13, 71, 161, 0.12);
        }

        .poin-summary-card {
            transition: all 0.28s ease;
        }

        .poin-summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 36px rgba(0, 91, 190, 0.28);
        }

        .poin-breakdown-item {
            transition: all 0.22s ease;
        }

        .poin-breakdown-item:hover {
            background: rgba(255, 255, 255, 0.24);
            transform: scale(1.04);
        }

        .poin-history-card,
        .poin-ranking-card {
            transition: all 0.28s ease;
        }

        .poin-history-card:hover,
        .poin-ranking-card:hover {
            border-color: #bfdbfe;
            box-shadow: 0 10px 28px -4px rgba(13, 71, 161, 0.08);
        }

        /* Form Pembayaran Card */
        #form-pembayaran-card {
            transition: all 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        #form-pembayaran-card:hover {
            border-color: #93c5fd;
            box-shadow: 0 12px 36px rgba(37, 99, 235, 0.12);
        }

        /* Bottom Navigation Tab Interactive Animation */
        .nav-tab-item {
            transition: all 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .nav-tab-item:active {
            transform: scale(0.9);
        }

        .nav-tab-item.active .nav-tab-icon-wrapper {
            animation: navIconPop 0.32s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes navIconPop {
            0% {
                transform: scale(0.8);
            }

            50% {
                transform: scale(1.2);
            }

            100% {
                transform: scale(1.08);
            }
        }

        .section-title-row .home-section-title {
            margin: 0;
        }
    </style>
</head>

<body>

    <!-- FLUID MOBILE APP CANVAS -->
    <div class="app-canvas">

        <!-- TOP NAVBAR HEADER -->
        <header class="top-navbar-header">
            <div class="brand-header-left">
                <div class="brand-badge-logo">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <h1 class="brand-header-title">Portal Orang Tua<br><small class="fw-semibold text-muted fs-6"
                        style="font-size: 0.7rem; font-family: sans-serif;"><?= htmlspecialchars($namaSekolah) ?></small>
                </h1>
            </div>

            <button class="btn-bell-notif" onclick="switchTab('notifikasi')" title="Notifikasi">
                <i class="bi bi-bell"></i>
                <?php if ($unreadNotifCount > 0): ?>
                    <span class="notif-dot-red"></span>
                <?php endif; ?>
            </button>
        </header>

        <!-- MAIN APP CONTENT -->
        <main class="app-main-content">

            <!-- FLASH MESSAGES -->
            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show rounded-4 shadow-sm mb-3"
                    role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i> <?= htmlspecialchars($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- ========================================== -->
            <!-- TAB 1: BERANDA OVERVIEW -->
            <!-- ========================================== -->
            <div class="tab-view <?= $activeTab === 'beranda' ? 'active animate-page-enter' : '' ?>" id="tab-beranda">

                <!-- TOP STUDENT PROFILE & SALDO KANTIN HERO CARD (HANYA MUNCUL DI BERANDA) -->
                <div class="card-profile-hero home-hero">
                    <div class="home-greeting"><i class="bi bi-sun-fill me-1"></i> Ahlan wa Sahlan</div>

                    <div class="home-profile">
                        <div class="home-avatar">
                            <div class="avatar-hero-img position-relative" style="cursor: pointer;"
                                onclick="showUploadFotoModal()" title="Klik untuk ubah foto profil">
                                <?php if (!empty($fotoSiswaUrl)): ?>
                                    <img src="<?= $fotoSiswaUrl ?>" alt="Foto Siswa">
                                <?php else: ?>
                                    <div class="home-avatar-fallback">
                                        <?= strtoupper(substr($loggedSiswa['nama'] ?? 'A', 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="btn-camera-badge" title="Ganti Foto">
                                    <i class="bi bi-camera-fill"></i>
                                </div>
                            </div>
                            <div class="online-status-dot" title="Siswa Terdaftar"></div>
                        </div>

                        <div>
                            <h2 class="home-student-name"><?= htmlspecialchars($loggedSiswa['nama'] ?? 'Siswa') ?></h2>
                            <p class="home-student-meta">
                                NIS <strong><?= htmlspecialchars($loggedSiswa['nis'] ?? '-') ?></strong> •
                                <?= htmlspecialchars($loggedSiswa['nama_kelas'] ?? '-') ?>
                            </p>
                        </div>
                    </div>

                    <!-- SALDO KANTIN CARD -->
                    <div class="home-wallet">
                        <div class="home-wallet-info">
                            <span class="home-wallet-label"><i class="bi bi-wallet2"></i> Saldo Kantin</span>
                            <div class="home-wallet-value">
                                <span id="txtBalanceValue">Rp <?= number_format($saldoKantin, 0, ',', '.') ?></span>
                                <span id="txtBalanceHidden" class="d-none">Rp •••••••</span>
                            </div>
                        </div>

                        <div class="home-wallet-tools">
                            <button type="button" class="btn-toggle-balance" onclick="toggleBalanceVisibility()"
                                title="Sembunyikan/Tampilkan Saldo">
                                <i class="bi bi-eye-fill" id="iconEyeBalance"></i>
                            </button>
                            <button type="button" class="home-topup-btn" onclick="showTopupModal()"
                                title="Isi Saldo Kantin">
                                <i class="bi bi-plus-circle-fill"></i> <span>Isi Saldo</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SECTION 1: AKSES CEPAT -->
                <div class="home-section-header">
                    <div class="home-section-title-wrap">
                        <span class="home-section-indicator"></span>
                        <h3 class="home-section-title">Akses Cepat</h3>
                    </div>
                    <span class="home-section-pill-tag">Menu Utama</span>
                </div>

                <div class="quick-access-grid home-quick-grid">
                    <div class="quick-access-item home-quick-action" onclick="switchTab('pembayaran')">
                        <div class="home-quick-icon">
                            <i class="bi bi-wallet2"></i>
                        </div>
                        <span class="home-quick-label">Bayar Tagihan</span>
                    </div>

                    <div class="quick-access-item home-quick-action" onclick="switchTab('nilai')">
                        <div class="home-quick-icon">
                            <i class="bi bi-mortarboard-fill"></i>
                        </div>
                        <span class="home-quick-label">Lihat Nilai</span>
                    </div>

                    <div class="quick-access-item home-quick-action" onclick="switchTab('kantin')">
                        <div class="home-quick-icon">
                            <i class="bi bi-cup-hot-fill"></i>
                        </div>
                        <span class="home-quick-label">Riwayat Kantin</span>
                    </div>

                    <div class="quick-access-item home-quick-action" onclick="switchTab('kesantrian')">
                        <div class="home-quick-icon">
                            <i class="bi bi-moon-stars-fill"></i>
                        </div>
                        <span class="home-quick-label">Kesantrian</span>
                    </div>
                </div>

                <!-- SECTION 2: TAGIHAN AKTIF -->
                <div class="section-title-row">
                    <h3 class="section-title-text">Tagihan Aktif</h3>
                    <a href="javascript:void(0)" onclick="switchTab('pembayaran')" class="section-link-blue">Lihat
                        Semua</a>
                </div>

                <div class="tagihan-list-container">
                    <?php
                    $hasAnyBillBeranda = false;
                    ?>

                    <!-- TAGIHAN 1: SPP (Otomatis Hilang jika Lunas) -->
                    <?php if ($sppCurrentStatus !== 'lunas'): ?>
                        <?php $hasAnyBillBeranda = true; ?>
                        <div class="card-tagihan-item">
                            <div class="tagihan-card-main">
                                <div class="tagihan-item-left">
                                    <div class="icon-box-tagihan">
                                        <i class="bi bi-calendar-event"></i>
                                    </div>
                                    <div class="tagihan-info-wrap">
                                        <h4 class="tagihan-item-title">SPP Bulan
                                            <?= $namaBulanArr[$currentMonth] ?? 'Ini' ?>
                                        </h4>
                                        <p class="tagihan-item-sub">Jatuh tempo: 10
                                            <?= $namaBulanArr[$currentMonth] ?? '' ?>     <?= date('Y') ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="tagihan-item-right">
                                    <div class="tagihan-amount-wrap">
                                        <span class="tagihan-sisa-label">Nominal</span>
                                        <span class="tagihan-amount-text">Rp
                                            <?= number_format($nominalSppDefault, 0, ',', '.') ?></span>
                                    </div>
                                    <button class="btn-bayar-pill" onclick="paySpecificBill('spp')">Bayar</button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- TAGIHAN 2: UANG PANGKAL -->
                    <?php if ($targetUP > 0 && !$isUPLunas): ?>
                        <?php $hasAnyBillBeranda = true; ?>
                        <div class="card-tagihan-item">
                            <div class="tagihan-card-main">
                                <div class="tagihan-item-left">
                                    <div class="icon-box-tagihan">
                                        <i class="bi bi-bank2"></i>
                                    </div>
                                    <div class="tagihan-info-wrap">
                                        <div class="d-flex align-items-center gap-2">
                                            <h4 class="tagihan-item-title">Uang Pangkal</h4>
                                            <?php if ($totalUPBayar > 0): ?>
                                                <span class="badge-cicilan-pill">Cicilan (<?= $percentUP ?>%)</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="tagihan-item-sub">Jatuh tempo: 30
                                            <?= $namaBulanArr[$currentMonth] ?? '' ?>     <?= date('Y') ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="tagihan-item-right">
                                    <div class="tagihan-amount-wrap">
                                        <span
                                            class="tagihan-sisa-label"><?= $totalUPBayar > 0 ? 'Sisa Tagihan' : 'Nominal' ?></span>
                                        <span class="tagihan-amount-text">Rp
                                            <?= number_format($sisaUP, 0, ',', '.') ?></span>
                                    </div>
                                    <button class="btn-bayar-pill" onclick="paySpecificBill('uang_pangkal')">Bayar</button>
                                </div>
                            </div>

                            <?php if ($totalUPBayar > 0): ?>
                                <div class="tagihan-progress-box">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="progress-meta-text">Terbayar: <strong>Rp
                                                <?= number_format($totalUPBayar, 0, ',', '.') ?></strong> / Rp
                                            <?= number_format($targetUP, 0, ',', '.') ?></span>
                                        <span class="progress-meta-percent"><?= $percentUP ?>%</span>
                                    </div>
                                    <div class="tagihan-progress-track">
                                        <div class="tagihan-progress-fill" style="width: <?= $percentUP ?>%;"></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($totalUPPending > 0): ?>
                                <div
                                    class="mt-2.5 p-2 rounded-3 bg-warning-subtle text-warning-emphasis d-flex align-items-center justify-content-between extra-small border border-warning-subtle">
                                    <span><i class="bi bi-clock-history me-1.5"></i> Sedang diverifikasi admin:</span>
                                    <strong class="font-monospace">Rp
                                        <?= number_format($totalUPPending, 0, ',', '.') ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- TAGIHAN 3+: BIAYA LAINNYA (Daftar Ulang, Seragam, dll) -->
                    <?php foreach ($jenisLainnya as $jl): ?>
                        <?php
                        $sudahB = (float) ($pembayaranLainBayarMap[$jl['id']] ?? 0);
                        $pendingB = (float) ($pembayaranLainPendingMap[$jl['id']] ?? 0);
                        $nomDef = (float) $jl['nominal_default'];
                        $sisaLain = max(0, $nomDef - $sudahB);
                        $isLainLunas = ($nomDef > 0 && $sisaLain <= 0);
                        if ($isLainLunas)
                            continue;

                        $hasAnyBillBeranda = true;
                        $percentLain = $nomDef > 0 ? min(100, round(($sudahB / $nomDef) * 100)) : ($sudahB > 0 ? 100 : 0);
                        ?>
                        <div class="card-tagihan-item">
                            <div class="tagihan-card-main">
                                <div class="tagihan-item-left">
                                    <div class="icon-box-tagihan">
                                        <i class="bi bi-card-checklist"></i>
                                    </div>
                                    <div class="tagihan-info-wrap">
                                        <div class="d-flex align-items-center gap-2">
                                            <h4 class="tagihan-item-title"><?= htmlspecialchars($jl['nama_pembayaran']) ?>
                                            </h4>
                                            <?php if ($sudahB > 0): ?>
                                                <span class="badge-cicilan-pill">Cicilan (<?= $percentLain ?>%)</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="tagihan-item-sub">Jatuh tempo: 30
                                            <?= $namaBulanArr[$currentMonth] ?? '' ?>     <?= date('Y') ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="tagihan-item-right">
                                    <div class="tagihan-amount-wrap">
                                        <span
                                            class="tagihan-sisa-label"><?= $sudahB > 0 ? 'Sisa Tagihan' : 'Nominal' ?></span>
                                        <span class="tagihan-amount-text">Rp
                                            <?= number_format($sisaLain, 0, ',', '.') ?></span>
                                    </div>
                                    <button class="btn-bayar-pill"
                                        onclick="paySpecificBill('lainnya_<?= $jl['id'] ?>')">Bayar</button>
                                </div>
                            </div>

                            <?php if ($sudahB > 0): ?>
                                <div class="tagihan-progress-box">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="progress-meta-text">Terbayar: <strong>Rp
                                                <?= number_format($sudahB, 0, ',', '.') ?></strong> / Rp
                                            <?= number_format($nomDef, 0, ',', '.') ?></span>
                                        <span class="progress-meta-percent"><?= $percentLain ?>%</span>
                                    </div>
                                    <div class="tagihan-progress-track">
                                        <div class="tagihan-progress-fill" style="width: <?= $percentLain ?>%;"></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($pendingB > 0): ?>
                                <div
                                    class="mt-2.5 p-2 rounded-3 bg-warning-subtle text-warning-emphasis d-flex align-items-center justify-content-between extra-small border border-warning-subtle">
                                    <span><i class="bi bi-clock-history me-1.5"></i> Sedang diverifikasi admin:</span>
                                    <strong class="font-monospace">Rp <?= number_format($pendingB, 0, ',', '.') ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!$hasAnyBillBeranda): ?>
                        <div class="text-center py-4 text-muted bg-white rounded-4 border p-3">
                            <div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center p-3 mb-2"
                                style="width: 48px; height: 48px;">
                                <i class="bi bi-check-circle-fill fs-4"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1 extra-small">Semua Tagihan Lunas</h6>
                            <small class="text-muted d-block extra-small">Tidak ada tagihan yang tertunggak saat
                                ini.</small>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- SECTION 3: TRANSAKSI KANTIN TERAKHIR -->
                <div class="section-title-row">
                    <h3 class="section-title-text">Transaksi Kantin Terakhir</h3>
                    <a href="javascript:void(0)" onclick="switchTab('kantin')" class="section-link-blue">Riwayat
                        Lengkap</a>
                </div>

                <div class="kantin-list-card">
                    <?php if (!empty($riwayatKantin)): ?>
                        <?php foreach (array_slice($riwayatKantin, 0, 3) as $rk): ?>
                            <div class="kantin-row-item">
                                <div class="kantin-item-left">
                                    <div class="icon-box-kantin">
                                        <i class="bi bi-egg-fried"></i>
                                    </div>
                                    <div>
                                        <h5 class="kantin-item-name">
                                            <?= htmlspecialchars($rk['item_summary'] ?? 'Jajanan Kantin') ?>
                                        </h5>
                                        <p class="kantin-item-time"><?= date('H:i', strtotime($rk['created_at'])) ?></p>
                                    </div>
                                </div>
                                <span class="kantin-item-price">- Rp
                                    <?= number_format($rk['total_harga'] ?? 0, 0, ',', '.') ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-muted py-3 small mb-0">Belum ada riwayat transaksi jajan di kantin.</p>
                    <?php endif; ?>
                </div>

                <!-- SEKSI SETORAN HALAQAH & TAHFIDZ TERBARU REAL DB -->
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h3 class="section-title-text"><i class="bi bi-moon-stars-fill me-1 text-primary"></i> Setoran
                        Tahfidz &amp; Kesantrian</h3>
                    <span
                        class="badge border rounded-pill px-3 bg-primary-subtle text-primary border-primary-subtle">Realtime</span>
                </div>

                <div class="widget-card mb-4">
                    <?php if (empty($halaqahSetoranList)): ?>
                        <div class="text-center py-4 text-muted">
                            <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center p-3 mb-2"
                                style="width: 50px; height: 50px;">
                                <i class="bi bi-journal-album fs-3 text-primary opacity-50"></i>
                            </div>
                            <p class="small mb-0">Belum ada catatan setoran halaqah untuk anak Anda.</p>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach (array_slice($halaqahSetoranList, 0, 5) as $hs): ?>
                                <?php
                                $nBadge = [
                                    'mumtaz' => 'bg-success-subtle text-success border-success',
                                    'jayyid_jiddan' => 'bg-info-subtle text-info border-info',
                                    'jayyid' => 'bg-warning-subtle text-warning border-warning',
                                    'rasib' => 'bg-danger-subtle text-danger border-danger'
                                ][$hs['penilaian']] ?? 'bg-light';
                                ?>
                                <?php
                                $materiClean = preg_replace('/^(Surah\s+)+/i', 'Surah ', htmlspecialchars($hs['materi_setoran'] ?? ''));
                                ?>
                                <div class="p-3 rounded-4 border bg-white shadow-sm mb-2.5" style="transition: all 0.22s ease;">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <?php
                                        $tRaw = strtolower(trim($hs['tipe_setoran'] ?? 'ziyadah'));
                                        if (strpos($tRaw, 'muroj') !== false) {
                                            $catStyle = 'background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff;';
                                            $catIcon = 'bi-arrow-repeat';
                                            $catLabel = "Muroja'ah";
                                        } elseif (strpos($tRaw, 'tahsin') !== false) {
                                            $catStyle = 'background: #ccfbf1; color: #0f766e; border: 1px solid #99f6e4;';
                                            $catIcon = 'bi-mic-fill';
                                            $catLabel = "Tahsin";
                                        } elseif (strpos($tRaw, 'ujian') !== false) {
                                            $catStyle = 'background: #ffe4e6; color: #be123c; border: 1px solid #fecdd3;';
                                            $catIcon = 'bi-award-fill';
                                            $catLabel = "Ujian Tahfidz";
                                        } else {
                                            $catStyle = 'background: #e0edff; color: #0284c7; border: 1px solid #bae6fd;';
                                            $catIcon = 'bi-bookmark-check-fill';
                                            $catLabel = "Ziyadah (Hafalan Baru)";
                                        }
                                        ?>
                                        <span
                                            style="<?= $catStyle ?> font-weight: 800; font-size: 0.68rem; padding: 4px 12px; border-radius: 50px; display: inline-flex; align-items: center; gap: 6px;">
                                            <i class="bi <?= $catIcon ?>"></i> <?= $catLabel ?>
                                        </span>
                                        <small class="text-muted extra-small"><i
                                                class="bi bi-calendar3 me-1.5"></i><?= date('d M Y', strtotime($hs['tanggal'])) ?></small>
                                    </div>
                                    <h6 class="fw-extrabold text-dark mb-2" style="font-size: 0.95rem; line-height: 1.35;">
                                        <?= $materiClean ?>
                                        <?php if (!empty($hs['metode_input'])): ?>
                                            <span class="badge bg-light text-secondary border rounded-pill ms-1 font-monospace"
                                                style="font-size: 0.65rem; font-weight: normal; vertical-align: middle;">Metode:
                                                <?= ucfirst(htmlspecialchars($hs['metode_input'])) ?></span>
                                        <?php endif; ?>
                                    </h6>

                                    <div
                                        class="d-flex align-items-center justify-content-between pt-2.5 border-top flex-wrap gap-2">
                                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                            <span
                                                class="badge <?= $nBadge ?> border extra-small px-2 py-1 font-monospace fw-bold"
                                                style="font-size: 0.68rem;">
                                                NILAI: <?= strtoupper(str_replace('_', ' ', $hs['penilaian'])) ?>
                                            </span>
                                            <span
                                                class="badge <?= $hs['status_setoran'] === 'lulus' ? 'bg-success text-white' : 'bg-danger text-white' ?> extra-small rounded-pill px-2.5 py-1"
                                                style="font-size: 0.68rem; font-weight: 800;">
                                                <?= $hs['status_setoran'] === 'lulus' ? '✓ Lulus' : '🔄 Mengulang' ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($hs['nama_musyrif'])): ?>
                                            <small class="text-muted extra-small d-inline-flex align-items-center gap-1">
                                                <i class="bi bi-person-circle text-secondary"></i> Musyrif:
                                                <strong><?= htmlspecialchars($hs['nama_musyrif']) ?></strong>
                                            </small>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($hs['catatan_ortu'])): ?>
                                        <div
                                            class="mt-2.5 p-2.5 rounded-3 bg-light extra-small text-secondary border-start border-3 border-primary">
                                            <i class="bi bi-chat-left-quote-fill me-1.5 text-primary"></i>
                                            <em>"<?= htmlspecialchars($hs['catatan_ortu']) ?>"</em>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- ========================================== -->
            <!-- TAB 2: KANTIN (UANG SAKU & TRANSAKSI) -->
            <!-- ========================================== -->
            <div class="tab-view <?= $activeTab === 'kantin' ? 'active animate-page-enter' : '' ?>" id="tab-kantin">

                <!-- KANTIN TAB HEADER -->
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <div
                            style="background: #e0edff; color: #1d4ed8; border: 1px solid #bfdbfe; border-radius: 50px; font-weight: 800; font-size: 0.7rem; padding: 5px 14px; display: inline-flex; align-items: center; gap: 8px; letter-spacing: 0.3px; margin-bottom: 0.65rem;">
                            <i class="bi bi-cup-hot-fill"></i> E-KANTIN SANTRI
                        </div>
                        <h2 class="h4 fw-extrabold text-dark"
                            style="font-family: 'Outfit', sans-serif; letter-spacing: -0.02em; margin-bottom: 0.35rem; line-height: 1.3;">
                            Kantin &amp; Saldo E-Wallet
                        </h2>
                        <small class="text-muted"
                            style="display: block; line-height: 1.45; font-size: 0.8rem;">Pengeluaran, saldo, mutasi
                            &amp; riwayat transaksi kantin</small>
                    </div>
                </div>

                <!-- PREMIUM BLUE HERO CARD PENGELUARAN MINGGU INI -->
                <div class="hero-kantin-spend-card position-relative overflow-hidden mb-4">
                    <div class="position-relative z-1">
                        <span class="spend-card-label"><i class="bi bi-bar-chart-fill me-2"></i> Pengeluaran Minggu
                            Ini</span>
                        <h2 class="spend-card-amount">Rp <?= number_format($weeklyKantinSpend, 0, ',', '.') ?></h2>

                        <div class="daily-spend-pills">
                            <?php foreach ($dailyKantinMap as $dDay => $dVal): ?>
                                <div class="daily-pill-item">
                                    <span class="daily-pill-day"><?= $dDay ?></span>
                                    <span
                                        class="daily-pill-val"><?= $dVal > 0 ? (round($dVal / 1000) . 'rb') : '0' ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <!-- Decorative backdrop -->
                    <div class="position-absolute end-0 top-0 text-white pointer-events-none"
                        style="font-size: 5.5rem; transform: translate(10%, -10%); opacity: 0.08;">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                </div>

                <!-- MODERN MINI STUDENT WALLET CARD -->
                <div class="d-flex align-items-center justify-content-between mb-4"
                    style="padding: 1.25rem 1.4rem; background: #ffffff; border-radius: 24px; border: 1.5px solid #f1f5f9; box-shadow: 0 4px 18px rgba(13,71,161,0.04);">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-hero-img" style="width: 48px; height: 48px; font-size: 1.2rem;">
                            <?php if (!empty($loggedSiswa['foto']) && file_exists(__DIR__ . '/' . $loggedSiswa['foto'])): ?>
                                <img src="<?= BASE_URL . '/' . htmlspecialchars($loggedSiswa['foto']) ?>" alt="Foto">
                            <?php else: ?>
                                <?= strtoupper(substr($loggedSiswa['nama'] ?? 'S', 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <strong class="d-block text-dark small fw-extrabold"
                                style="font-family: 'Outfit', sans-serif; margin-bottom: 4px;"><?= htmlspecialchars($loggedSiswa['nama'] ?? 'Siswa') ?></strong>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill"
                                style="padding: 4px 12px; font-size: 0.72rem; font-weight: 700;">Saldo Kantin: Rp
                                <?= number_format($saldoKantin, 0, ',', '.') ?></span>
                        </div>
                    </div>
                    <button
                        class="btn btn-sm btn-primary rounded-pill px-3 py-2 fw-bold d-inline-flex align-items-center gap-2"
                        onclick="showTopupModal()" style="font-size: 0.82rem;"><i class="bi bi-plus-circle-fill"></i>
                        <span>Top-Up</span></button>
                </div>

                <!-- STATUS & PERMOHONAN TOP-UP SALDO KANTIN -->
                <div class="widget-card mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary-subtle text-primary flex-shrink-0"
                                style="width: 38px; height: 38px;">
                                <i class="bi bi-clock-history fs-5"></i>
                            </div>
                            <h6 class="fw-extrabold text-dark m-0" style="font-family: 'Outfit', sans-serif;">Status
                                Permohonan Top-Up</h6>
                        </div>
                    </div>

                    <?php if (empty($topupStatusList)): ?>
                        <p class="text-muted extra-small text-center py-3 mb-0">Belum ada riwayat permohonan top-up. Klik
                            tombol <strong>Top-Up Saldo</strong> untuk menambah saldo E-Kantin.</p>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-2.5">
                            <?php foreach ($topupStatusList as $ts): ?>
                                <?php
                                $st = $ts['status'] ?? 'pending';
                                $isOK = ($st === 'setuju' || $st === 'disetujui');
                                $isFail = ($st === 'ditolak');
                                $badgeClass = $isOK ? 'bg-success text-white' : ($isFail ? 'bg-danger text-white' : 'bg-warning text-dark');
                                $stLabel = $isOK ? 'SUKSES' : ($isFail ? 'DITOLAK' : 'PENDING');
                                ?>
                                <div
                                    class="p-3 rounded-4 border bg-white d-flex align-items-center justify-content-between gap-2 shadow-sm">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-circle p-2 <?= $isFail ? 'bg-danger-subtle text-danger' : ($isOK ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis') ?> d-flex align-items-center justify-content-center flex-shrink-0"
                                            style="width: 40px; height: 40px;">
                                            <i
                                                class="bi <?= $isFail ? 'bi-x-circle-fill' : ($isOK ? 'bi-check-circle-fill' : 'bi-hourglass-split') ?> fs-5"></i>
                                        </div>
                                        <div>
                                            <strong class="d-block text-dark small mb-0.5">Top-Up Rp
                                                <?= number_format($ts['nominal'], 0, ',', '.') ?></strong>
                                            <small class="text-muted extra-small"><i
                                                    class="bi bi-clock me-1.5"></i><?= date('d M Y H:i', strtotime($ts['created_at'])) ?></small>
                                            <?php if (!empty($ts['catatan']) && $isFail): ?>
                                                <div class="text-danger extra-small mt-1">Catatan:
                                                    <?= htmlspecialchars($ts['catatan']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span
                                            class="badge <?= $badgeClass ?> rounded-pill px-2.5 py-1 extra-small fw-bold"><?= $stLabel ?></span>
                                        <?php if (!empty($ts['bukti_transfer'])): ?>
                                            <a href="<?= htmlspecialchars(getBuktiUrl($ts['bukti_transfer'])) ?>" target="_blank"
                                                class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 text-decoration-none mt-1 d-inline-flex align-items-center gap-1.5"
                                                style="font-size: 0.65rem; font-weight: 700;">
                                                <i class="bi bi-image" style="font-size: 0.68rem;"></i> Bukti Transfer
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- MUTASI SALDO E-WALLET SANTRI (TRANSPARANSI SALDO) -->
                <div class="widget-card mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary-subtle text-primary flex-shrink-0"
                                style="width: 38px; height: 38px;">
                                <i class="bi bi-arrow-left-right fs-5"></i>
                            </div>
                            <h6 class="fw-extrabold text-dark m-0" style="font-family: 'Outfit', sans-serif;">Mutasi
                                Saldo E-Wallet Santri</h6>
                        </div>
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1 fw-bold"
                            style="font-size: 0.72rem;"><?= count($mutasiSaldoList) ?> Catatan</span>
                    </div>

                    <?php if (empty($mutasiSaldoList)): ?>
                        <p class="text-center text-muted py-4 small mb-0">Belum ada riwayat mutasi saldo E-Wallet santri.
                        </p>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-2.5" style="max-height: 380px; overflow-y: auto;">
                            <?php foreach ($mutasiSaldoList as $ms): ?>
                                <?php
                                $j = $ms['jenis'];
                                $isPlus = ($j === 'topup' || $j === 'refund');
                                $colorClass = $isPlus ? 'text-success' : 'text-danger';
                                $bgIconClass = $isPlus ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger';
                                $iconName = ($j === 'topup') ? 'bi-plus-circle-fill' : (($j === 'pembelian') ? 'bi-bag-check-fill' : 'bi-arrow-repeat');
                                $sign = $isPlus ? '+' : '-';
                                ?>
                                <div
                                    class="p-3 rounded-4 border bg-white d-flex align-items-center justify-content-between gap-2 shadow-sm">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-circle p-2 <?= $bgIconClass ?> d-flex align-items-center justify-content-center flex-shrink-0"
                                            style="width: 40px; height: 40px;">
                                            <i class="bi <?= $iconName ?> fs-5"></i>
                                        </div>
                                        <div>
                                            <strong
                                                class="d-block text-dark small fw-bold mb-0.5"><?= htmlspecialchars($ms['keterangan'] ?? ucfirst($j)) ?></strong>
                                            <small class="text-muted extra-small" style="font-size: 0.7rem;">
                                                <i
                                                    class="bi bi-clock me-1.5"></i><?= date('d M Y H:i', strtotime($ms['created_at'])) ?>
                                                <?php if (!empty($ms['nama_petugas'])): ?> &bull; Petugas:
                                                    <?= htmlspecialchars($ms['nama_petugas']) ?>         <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="text-end flex-shrink-0">
                                        <span class="fw-extrabold extra-small <?= $colorClass ?> d-block"
                                            style="font-family: 'Outfit', sans-serif;">
                                            <?= $sign ?> Rp <?= number_format($ms['jumlah'], 0, ',', '.') ?>
                                        </span>
                                        <small class="text-muted extra-small" style="font-size: 0.65rem;">
                                            Sisa: Rp <?= number_format($ms['saldo_sesudah'], 0, ',', '.') ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- RIWAYAT TRANSAKSI KANTIN FILTERABLE REAL DB -->
                <div class="widget-card mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary-subtle text-primary flex-shrink-0"
                                style="width: 38px; height: 38px;">
                                <i class="bi bi-receipt fs-5"></i>
                            </div>
                            <h6 class="fw-extrabold text-dark m-0" style="font-family: 'Outfit', sans-serif;">Riwayat
                                Transaksi</h6>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 rounded-start-pill"><i
                                    class="bi bi-search text-muted"></i></span>
                            <input type="text" id="inputSearchKantin" onkeyup="filterKantinItems()"
                                class="form-control border-start-0 rounded-end-pill" placeholder="Cari menu...">
                        </div>
                    </div>

                    <?php if (empty($riwayatKantin)): ?>
                        <p class="text-center text-muted py-4 small">Belum ada riwayat transaksi jajan di kantin.</p>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-2" id="kantinTransactionsList">
                            <?php foreach ($riwayatKantin as $rk): ?>
                                <div
                                    class="p-3 rounded-4 border bg-white d-flex align-items-center justify-content-between gap-2 shadow-sm kantin-tx-item">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="icon-box-kantin">
                                            <i class="bi bi-egg-fried"></i>
                                        </div>
                                        <div>
                                            <strong
                                                class="d-block text-dark small item-title-txt"><?= htmlspecialchars($rk['item_summary'] ?? 'Jajanan Kantin') ?></strong>
                                            <small class="text-muted extra-small"><i
                                                    class="bi bi-calendar3 me-1"></i><?= date('d M Y H:i', strtotime($rk['created_at'])) ?>
                                                &bull; Kantin</small>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="fw-bold text-danger small d-block"
                                            style="font-family: 'Outfit', sans-serif;">- Rp
                                            <?= number_format($rk['total_harga'] ?? 0, 0, ',', '.') ?></span>
                                        <span
                                            class="badge bg-success-subtle text-success extra-small rounded-pill">SUKSES</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- ========================================== -->
            <!-- TAB 3: TAGIHAN & FORM PEMBAYARAN ONLINE -->
            <!-- ========================================== -->
            <div class="tab-view <?= $activeTab === 'pembayaran' ? 'active animate-page-enter' : '' ?>"
                id="tab-pembayaran">

                <!-- DAFTAR SELURUH TAGIHAN AKTIF REAL DB -->
                <div class="section-title-row mt-2 mb-3">
                    <h3 class="section-title-text"><i class="bi bi-wallet2 text-primary me-2"></i> Daftar Tagihan Aktif
                    </h3>
                </div>

                <div class="tagihan-list-container mb-4">
                    <?php
                    $hasAnyBillTab = false;
                    ?>

                    <!-- TAGIHAN 1: SPP (Hanya tampil jika belum lunas) -->
                    <?php if ($sppCurrentStatus !== 'lunas'): ?>
                        <?php $hasAnyBillTab = true; ?>
                        <div class="card-tagihan-item">
                            <div class="tagihan-card-main">
                                <div class="tagihan-item-left">
                                    <div class="icon-box-tagihan">
                                        <i class="bi bi-calendar-event"></i>
                                    </div>
                                    <div class="tagihan-info-wrap">
                                        <h4 class="tagihan-item-title">SPP Bulan
                                            <?= $namaBulanArr[$currentMonth] ?? 'Ini' ?>
                                        </h4>
                                        <p class="tagihan-item-sub">Jatuh tempo: 10
                                            <?= $namaBulanArr[$currentMonth] ?? '' ?>
                                            <?= date('Y') ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="tagihan-item-right">
                                    <div class="tagihan-amount-wrap">
                                        <span class="tagihan-sisa-label">Nominal</span>
                                        <span class="tagihan-amount-text">Rp
                                            <?= number_format($nominalSppDefault, 0, ',', '.') ?></span>
                                    </div>
                                    <?php if ($sppCurrentStatus === 'pending'): ?>
                                        <span
                                            class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-1 fw-bold extra-small">Pending</span>
                                    <?php else: ?>
                                        <button class="btn-bayar-pill" onclick="paySpecificBill('spp')">Bayar</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- TAGIHAN 2: UANG PANGKAL -->
                    <?php if ($targetUP > 0 && !$isUPLunas): ?>
                        <?php $hasAnyBillTab = true; ?>
                        <div class="card-tagihan-item">
                            <div class="tagihan-card-main">
                                <div class="tagihan-item-left">
                                    <div class="icon-box-tagihan">
                                        <i class="bi bi-bank2"></i>
                                    </div>
                                    <div class="tagihan-info-wrap">
                                        <div class="d-flex align-items-center gap-2">
                                            <h4 class="tagihan-item-title">Uang Pangkal</h4>
                                            <?php if ($totalUPBayar > 0): ?>
                                                <span class="badge-cicilan-pill">Cicilan (<?= $percentUP ?>%)</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="tagihan-item-sub">Jatuh tempo: 30
                                            <?= $namaBulanArr[$currentMonth] ?? '' ?>
                                            <?= date('Y') ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="tagihan-item-right">
                                    <div class="tagihan-amount-wrap">
                                        <span
                                            class="tagihan-sisa-label"><?= $totalUPBayar > 0 ? 'Sisa Tagihan' : 'Nominal' ?></span>
                                        <span class="tagihan-amount-text">Rp
                                            <?= number_format($sisaUP, 0, ',', '.') ?></span>
                                    </div>
                                    <button class="btn-bayar-pill" onclick="paySpecificBill('uang_pangkal')">Bayar</button>
                                </div>
                            </div>

                            <?php if ($totalUPBayar > 0): ?>
                                <div class="tagihan-progress-box">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="progress-meta-text">Terbayar: <strong>Rp
                                                <?= number_format($totalUPBayar, 0, ',', '.') ?></strong> / Rp
                                            <?= number_format($targetUP, 0, ',', '.') ?></span>
                                        <span class="progress-meta-percent"><?= $percentUP ?>%</span>
                                    </div>
                                    <div class="tagihan-progress-track">
                                        <div class="tagihan-progress-fill" style="width: <?= $percentUP ?>%;"></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($totalUPPending > 0): ?>
                                <div
                                    class="mt-2.5 p-2 rounded-3 bg-warning-subtle text-warning-emphasis d-flex align-items-center justify-content-between extra-small border border-warning-subtle">
                                    <span><i class="bi bi-clock-history me-1.5"></i> Sedang diverifikasi admin:</span>
                                    <strong class="font-monospace">Rp
                                        <?= number_format($totalUPPending, 0, ',', '.') ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- TAGIHAN 3+: BIAYA LAINNYA -->
                    <?php foreach ($jenisLainnya as $jl): ?>
                        <?php
                        $sudahB = (float) ($pembayaranLainBayarMap[$jl['id']] ?? 0);
                        $pendingB = (float) ($pembayaranLainPendingMap[$jl['id']] ?? 0);
                        $nomDef = (float) $jl['nominal_default'];
                        $sisaLain = max(0, $nomDef - $sudahB);
                        $isLainLunas = ($nomDef > 0 && $sisaLain <= 0);
                        if ($isLainLunas)
                            continue;

                        $hasAnyBillTab = true;
                        $percentLain = $nomDef > 0 ? min(100, round(($sudahB / $nomDef) * 100)) : ($sudahB > 0 ? 100 : 0);
                        ?>
                        <div class="card-tagihan-item">
                            <div class="tagihan-card-main">
                                <div class="tagihan-item-left">
                                    <div class="icon-box-tagihan">
                                        <i class="bi bi-card-checklist"></i>
                                    </div>
                                    <div class="tagihan-info-wrap">
                                        <div class="d-flex align-items-center gap-2">
                                            <h4 class="tagihan-item-title"><?= htmlspecialchars($jl['nama_pembayaran']) ?>
                                            </h4>
                                            <?php if ($sudahB > 0): ?>
                                                <span class="badge-cicilan-pill">Cicilan (<?= $percentLain ?>%)</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="tagihan-item-sub">Jatuh tempo: 30
                                            <?= $namaBulanArr[$currentMonth] ?? '' ?>
                                            <?= date('Y') ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="tagihan-item-right">
                                    <div class="tagihan-amount-wrap">
                                        <span
                                            class="tagihan-sisa-label"><?= $sudahB > 0 ? 'Sisa Tagihan' : 'Nominal' ?></span>
                                        <span class="tagihan-amount-text">Rp
                                            <?= number_format($sisaLain, 0, ',', '.') ?></span>
                                    </div>
                                    <button class="btn-bayar-pill"
                                        onclick="paySpecificBill('lainnya_<?= $jl['id'] ?>')">Bayar</button>
                                </div>
                            </div>

                            <?php if ($sudahB > 0): ?>
                                <div class="tagihan-progress-box">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="progress-meta-text">Terbayar: <strong>Rp
                                                <?= number_format($sudahB, 0, ',', '.') ?></strong> / Rp
                                            <?= number_format($nomDef, 0, ',', '.') ?></span>
                                        <span class="progress-meta-percent"><?= $percentLain ?>%</span>
                                    </div>
                                    <div class="tagihan-progress-track">
                                        <div class="tagihan-progress-fill" style="width: <?= $percentLain ?>%;"></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($pendingB > 0): ?>
                                <div
                                    class="mt-2.5 p-2 rounded-3 bg-warning-subtle text-warning-emphasis d-flex align-items-center justify-content-between extra-small border border-warning-subtle">
                                    <span><i class="bi bi-clock-history me-1.5"></i> Sedang diverifikasi admin:</span>
                                    <strong class="font-monospace">Rp <?= number_format($pendingB, 0, ',', '.') ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!$hasAnyBillTab): ?>
                        <div class="text-center py-4 text-muted bg-white rounded-4 border p-3">
                            <div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center p-3 mb-2"
                                style="width: 48px; height: 48px;">
                                <i class="bi bi-check-circle-fill fs-4"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1 extra-small">Semua Tagihan Lunas</h6>
                            <small class="text-muted d-block extra-small">Tidak ada tagihan yang tertunggak saat
                                ini.</small>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- RIWAYAT PEMBAYARAN MENUNGGU VERIFIKASI / PENDING -->
                <?php
                $pendingItems = array_filter($riwayatPending, fn($p) => ($p['status'] ?? 'pending') === 'pending');
                $ditolakItems = array_filter($riwayatPending, fn($p) => ($p['status'] ?? '') === 'ditolak' && empty($p['is_dismissed']));
                ?>

                <?php if (!empty($pendingItems) || !empty($ditolakItems)): ?>
                    <div class="section-title-row mb-2">
                        <h3 class="section-title-text"><i class="bi bi-clock-history text-warning me-2"></i> Pembayaran
                            Menunggu Verifikasi (Pending)</h3>
                    </div>

                    <div class="tagihan-list-container mb-4">
                        <?php foreach ($pendingItems as $pi): ?>
                            <?php
                            $namaJenis = $pi['jenis'] === 'spp' ? ('SPP Bulan ' . ($namaBulanArr[$pi['bulan']] ?? '') . ' ' . $pi['tahun']) : ($pi['jenis'] === 'uang_pangkal' ? 'Uang Pangkal' : ($pi['nama_pembayaran'] ?? 'Pembayaran Lainnya'));
                            ?>
                            <div class="card-tagihan-item border-warning-subtle bg-warning-subtle" style="border-radius: 20px;">
                                <div class="tagihan-item-left">
                                    <div class="icon-box-tagihan bg-warning text-white">
                                        <i class="bi bi-hourglass-split"></i>
                                    </div>
                                    <div>
                                        <h4 class="tagihan-item-title"><?= htmlspecialchars($namaJenis) ?></h4>
                                        <p class="tagihan-item-sub text-muted">Dikirim:
                                            <?= date('d M Y H:i', strtotime($pi['created_at'])) ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="tagihan-item-right">
                                    <span class="tagihan-amount-text text-warning-emphasis">Rp
                                        <?= number_format($pi['nominal'], 0, ',', '.') ?></span>
                                    <span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-bold extra-small"><i
                                            class="bi bi-hourglass me-1"></i> PENDING</span>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php foreach ($ditolakItems as $di): ?>
                            <?php
                            $namaJenis = $di['jenis'] === 'spp' ? ('SPP Bulan ' . ($namaBulanArr[$di['bulan']] ?? '') . ' ' . $di['tahun']) : ($di['jenis'] === 'uang_pangkal' ? 'Uang Pangkal' : ($di['nama_pembayaran'] ?? 'Pembayaran Lainnya'));
                            ?>
                            <div class="card-tagihan-item border-danger-subtle bg-danger-subtle ditolak-item-card"
                                id="ditolak-item-<?= (int) $di['id'] ?>"
                                style="border-radius: 20px; position: relative; transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);">
                                <div class="tagihan-item-left">
                                    <div class="icon-box-tagihan bg-danger text-white">
                                        <i class="bi bi-x-circle-fill"></i>
                                    </div>
                                    <div>
                                        <h4 class="tagihan-item-title text-danger"><?= htmlspecialchars($namaJenis) ?></h4>
                                        <p class="tagihan-item-sub text-danger opacity-75">Ditolak:
                                            <?= htmlspecialchars($di['alasan_tolak'] ?? $di['catatan'] ?? 'Bukti tidak sesuai') ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="tagihan-item-right d-flex flex-column align-items-end gap-1">
                                    <span class="tagihan-amount-text text-danger">Rp
                                        <?= number_format($di['nominal'], 0, ',', '.') ?></span>
                                    <div class="d-flex align-items-center gap-1.5">
                                        <span class="badge bg-danger rounded-pill px-3 py-1 fw-bold extra-small">DITOLAK</span>
                                        <button type="button"
                                            class="btn btn-sm btn-outline-danger rounded-pill px-2.5 py-0.5 extra-small fw-bold border-0"
                                            onclick="event.stopPropagation(); dismissDitolakCard(<?= (int) $di['id'] ?>)"
                                            title="Hilangkan dari daftar"
                                            style="font-size: 0.7rem; background: rgba(220,38,38,0.1);">
                                            <i class="bi bi-x-lg" style="font-size: 0.65rem;"></i> Hapus
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- PEMBAYARAN ONLINE - PREMIUM BLUE ACCENT CARD -->
                <div class="position-relative" id="form-pembayaran-card"
                    style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 50%, #e0f2fe 100%); border-radius: 28px; padding: 2rem 1.75rem; border: 1.5px solid #bfdbfe; box-shadow: 0 8px 32px rgba(37, 99, 235, 0.08);">

                    <div class="position-relative z-1">
                        <!-- Header with blue accent -->
                        <div class="d-flex align-items-center gap-3.5 mb-4">
                            <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #2563eb, #1d4ed8); box-shadow: 0 6px 18px rgba(37, 99, 235, 0.25);">
                                <i class="bi bi-credit-card-fill text-white fs-5"></i>
                            </div>
                            <div>
                                <h3 class="fw-extrabold mb-1"
                                    style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; color: #1e3a8a; letter-spacing: -0.02em;">
                                    Pembayaran Online</h3>
                                <p class="text-muted mb-0" style="font-size: 0.78rem;">Upload bukti transfer untuk
                                    verifikasi admin</p>
                            </div>
                        </div>

                        <form method="POST" action="portal-ortu.php" enctype="multipart/form-data" id="formBayarOrtu">
                            <input type="hidden" name="action" value="submit_pembayaran">

                            <div class="mb-4">
                                <label class="form-label fw-extrabold small mb-2"
                                    style="color: #1e40af; font-size: 0.82rem;"><i class="bi bi-tag-fill me-1.5"
                                        style="color: #3b82f6;"></i> Pilih Jenis Pembayaran *</label>
                                <select name="jenis" id="selectJenisBayar" class="form-select fw-bold"
                                    onchange="toggleBayarOptions()" required
                                    style="border-radius: 16px; border: 1.5px solid #93c5fd; padding: 12px 16px; font-size: 0.9rem; background-color: #ffffff; box-shadow: 0 2px 8px rgba(37, 99, 235, 0.05); transition: all 0.2s ease;">
                                    <option value="">-- Pilih Jenis Pembayaran --</option>
                                    <option value="spp" data-nominal="<?= $nominalSppDefault ?>">SPP Bulanan (Rp
                                        <?= number_format($nominalSppDefault, 0, ',', '.') ?> / Bulan)
                                    </option>
                                    <?php if ($sisaUP > 0): ?>
                                        <option value="uang_pangkal" data-nominal="<?= $sisaUP ?>"
                                            data-sudah="<?= $totalUPBayar ?>" data-target="<?= $targetUP ?>">
                                            Uang Pangkal / Biaya Masuk (Sisa: Rp
                                            <?= number_format($sisaUP, 0, ',', '.') ?>
                                            <?= $totalUPBayar > 0 ? ' / Terbayar: Rp ' . number_format($totalUPBayar, 0, ',', '.') : '' ?>)
                                        </option>
                                    <?php endif; ?>
                                    <?php foreach ($jenisLainnya as $jl): ?>
                                        <?php
                                        $sudahB = (float) ($pembayaranLainBayarMap[$jl['id']] ?? 0);
                                        $nomDef = (float) $jl['nominal_default'];
                                        $sisaLain = max(0, $nomDef - $sudahB);
                                        if ($nomDef > 0 && $sisaLain <= 0)
                                            continue;
                                        ?>
                                        <option value="lainnya_<?= $jl['id'] ?>" data-nominal="<?= $sisaLain ?>"
                                            data-sudah="<?= $sudahB ?>" data-target="<?= $nomDef ?>">
                                            <?= htmlspecialchars($jl['nama_pembayaran']) ?> (Sisa: Rp
                                            <?= number_format($sisaLain, 0, ',', '.') ?>
                                            <?= $sudahB > 0 ? ' / Terbayar: Rp ' . number_format($sudahB, 0, ',', '.') : '' ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div id="wrapperSppBulan" class="mb-4 p-4 bg-white border d-none"
                                style="border-radius: 22px; border-color: #93c5fd !important; box-shadow: 0 4px 16px rgba(37, 99, 235, 0.04);">
                                <label class="form-label fw-extrabold small mb-3 d-flex align-items-center gap-1.5"
                                    style="color: #1e40af; font-size: 0.84rem;">
                                    <i class="bi bi-calendar-check-fill text-primary"></i> Pilih Bulan SPP yang Dibayar:
                                </label>
                                <div class="row g-2.5">
                                    <?php foreach ($namaBulanArr as $mNum => $mName): ?>
                                        <?php
                                        $isL = in_array($mNum, $lunasMonths);
                                        $isP = in_array($mNum, $pendingMonths);
                                        ?>
                                        <div class="col-4">
                                            <?php if ($isL): ?>
                                                <div class="d-flex flex-column align-items-center justify-content-center p-2 text-center rounded-3 bg-success-subtle border border-success-subtle text-success h-100"
                                                    style="min-height: 56px;">
                                                    <span
                                                        class="fw-bold extra-small text-truncate w-100 mb-0.5"><?= $mName ?></span>
                                                    <span class="badge bg-success rounded-pill px-2 py-0.5"
                                                        style="font-size: 0.6rem;">Lunas</span>
                                                </div>
                                            <?php elseif ($isP): ?>
                                                <div class="d-flex flex-column align-items-center justify-content-center p-2 text-center rounded-3 bg-warning-subtle border border-warning-subtle text-warning-emphasis h-100"
                                                    style="min-height: 56px;">
                                                    <span
                                                        class="fw-bold extra-small text-truncate w-100 mb-0.5"><?= $mName ?></span>
                                                    <span class="badge bg-warning text-dark rounded-pill px-2 py-0.5"
                                                        style="font-size: 0.6rem;">Pending</span>
                                                </div>
                                            <?php else: ?>
                                                <label
                                                    class="d-flex flex-column align-items-center justify-content-center p-2 text-center rounded-3 bg-white border border-secondary-subtle cursor-pointer h-100"
                                                    for="chkM_<?= $mNum ?>"
                                                    style="min-height: 56px; transition: all 0.2s ease;">
                                                    <div class="d-flex align-items-center gap-1.5 mb-1">
                                                        <input class="form-check-input chk-spp-month m-0" type="checkbox"
                                                            name="bulan_spp[]" value="<?= $mNum ?>" id="chkM_<?= $mNum ?>"
                                                            onchange="calcSppTotal()">
                                                    </div>
                                                    <span class="fw-extrabold extra-small text-dark text-truncate w-100"
                                                        style="font-size: 0.78rem;"><?= $mName ?></span>
                                                </label>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-extrabold small mb-2"
                                    style="color: #1e40af; font-size: 0.82rem;"><i class="bi bi-cash-stack me-1.5"
                                        style="color: #3b82f6;"></i> Nominal Transfer (Rp) *</label>
                                <input type="text" name="nominal" id="inputNominal"
                                    class="form-control fw-extrabold fs-5"
                                    style="border-radius: 16px; border: 1.5px solid #93c5fd; padding: 12px 16px; font-family: 'Outfit', sans-serif; color: #1e40af; background: #ffffff; box-shadow: 0 2px 8px rgba(37, 99, 235, 0.05);"
                                    placeholder="Contoh: 150000" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-extrabold small mb-2"
                                    style="color: #1e40af; font-size: 0.82rem;"><i class="bi bi-image me-1.5"
                                        style="color: #3b82f6;"></i> Foto / PDF Bukti Transfer *</label>
                                <input type="file" name="bukti_transfer" class="form-control"
                                    accept="image/*,application/pdf" required
                                    style="border-radius: 16px; border: 1.5px solid #93c5fd; padding: 10px 16px; background: #ffffff; box-shadow: 0 2px 8px rgba(37, 99, 235, 0.05);">
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-extrabold small mb-2"
                                    style="color: #1e40af; font-size: 0.82rem;"><i class="bi bi-chat-text me-1.5"
                                        style="color: #3b82f6;"></i> Catatan (Opsional)</label>
                                <textarea name="catatan" class="form-control" rows="2"
                                    placeholder="Keterangan tambahan..."
                                    style="border-radius: 16px; border: 1.5px solid #93c5fd; padding: 12px 16px; background: #ffffff; box-shadow: 0 2px 8px rgba(37, 99, 235, 0.05);"></textarea>
                            </div>

                            <button type="submit"
                                class="btn w-100 fw-extrabold shadow-sm d-flex align-items-center justify-content-center gap-2 mt-2"
                                style="background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #ffffff; border: none; border-radius: 18px; padding: 14px; font-size: 0.95rem; box-shadow: 0 8px 24px rgba(37, 99, 235, 0.28); transition: all 0.25s ease;">
                                <i class="bi bi-send-fill"></i> Kirim Bukti Pembayaran
                            </button>
                        </form>
                    </div>
                </div>

            </div>

            <!-- ========================================== -->
            <!-- TAB 4: AKADEMIK -->
            <!-- ========================================== -->
            <div class="tab-view <?= $activeTab === 'nilai' ? 'active animate-page-enter' : '' ?>" id="tab-nilai">

                <!-- HEADER SECTION -->
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <div
                            style="background: #e0edff; color: #1d4ed8; border: 1px solid #bfdbfe; border-radius: 50px; font-weight: 800; font-size: 0.7rem; padding: 5px 14px; display: inline-flex; align-items: center; gap: 8px; letter-spacing: 0.3px; margin-bottom: 0.65rem;">
                            <i class="bi bi-mortarboard-fill"></i> AKADEMIK
                        </div>
                        <h2 class="h4 fw-extrabold text-dark"
                            style="font-family: 'Outfit', sans-serif; letter-spacing: -0.02em; margin-bottom: 0.35rem; line-height: 1.3;">
                            Portal Akademik</h2>
                        <small class="text-muted" style="display: block; line-height: 1.45; font-size: 0.8rem;">Hasil
                            evaluasi pembelajaran dan nilai setiap mata pelajaran</small>
                    </div>
                </div>

                <?php
                // Kalkulasi Ringkasan Akademik
                $totalMapelCount = count($nilaiSiswaList);
                $allValidScores = [];
                $tuntasMapelCount = 0;
                foreach ($nilaiSiswaList as $nCheck) {
                    $kkmCheck = (float) ($nCheck['kkm'] ?? 75);
                    $itemScores = array_filter([$nCheck['sumatif_1'], $nCheck['sumatif_2'], $nCheck['sumatif_3'], $nCheck['sumatif_4'], $nCheck['ats'], $nCheck['aas']], fn($val) => is_numeric($val) && (float) $val > 0);
                    if (!empty($itemScores)) {
                        $itemAvg = array_sum($itemScores) / count($itemScores);
                        $allValidScores[] = $itemAvg;
                        if ($itemAvg >= $kkmCheck) {
                            $tuntasMapelCount++;
                        }
                    }
                }
                $overallAvgScore = !empty($allValidScores) ? (array_sum($allValidScores) / count($allValidScores)) : 0;
                ?>

                <!-- HERO SUMMARY AKADEMIK (MATCHING BERANDA SIGNATURE BLUE GRADIENT) -->
                <div class="hero-akademik-card mb-4">
                    <div class="position-relative z-1">
                        <div class="hero-akademik-kicker">
                            <i class="bi bi-mortarboard-fill"></i> RINGKASAN AKADEMIK
                        </div>
                        <h3 class="hero-akademik-title">Evaluasi Hasil Belajar</h3>
                        <p class="hero-akademik-sub">
                            Kelas <?= htmlspecialchars($loggedSiswa['nama_kelas'] ?? '-') ?> &bull; NIS:
                            <?= htmlspecialchars($loggedSiswa['nis'] ?? '-') ?>
                        </p>

                        <div class="hero-akademik-stats-grid">
                            <div class="hero-akademik-stat-item">
                                <div class="hero-akademik-stat-val">
                                    <?= $overallAvgScore > 0 ? number_format($overallAvgScore, 1) : '—' ?>
                                </div>
                                <div class="hero-akademik-stat-lbl">Rata-Rata</div>
                            </div>
                            <div class="hero-akademik-stat-item">
                                <div class="hero-akademik-stat-val"><?= $totalMapelCount ?></div>
                                <div class="hero-akademik-stat-lbl">Total Mapel</div>
                            </div>
                            <div class="hero-akademik-stat-item">
                                <div class="hero-akademik-stat-val"><?= $tuntasMapelCount ?>/<?= $totalMapelCount ?>
                                </div>
                                <div class="hero-akademik-stat-lbl">Tuntas KKM</div>
                            </div>
                        </div>
                    </div>
                    <!-- DECORATIVE BACKDROP ICON -->
                    <div class="position-absolute end-0 bottom-0 text-white pointer-events-none"
                        style="padding-right: 1.25rem; padding-bottom: 0.5rem; opacity: 0.08;">
                        <i class="bi bi-mortarboard" style="font-size: 5.5rem;"></i>
                    </div>
                </div>

                <!-- SEKSI NILAI PER MATA PELAJARAN (PERFECT ALIGNED ACCORDION DROPDOWN) -->
                <div class="widget-card mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center"
                                style="width: 36px; height: 36px;">
                                <i class="bi bi-journal-text fs-6"></i>
                            </div>
                            <h6 class="fw-extrabold text-dark m-0" style="font-family: 'Outfit', sans-serif;">Daftar
                                Mata Pelajaran</h6>
                        </div>
                        <span
                            style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; border-radius: 50px; padding: 3px 12px; font-size: 0.72rem; font-weight: 800;">
                            <?= count($nilaiSiswaList) ?> Mapel
                        </span>
                    </div>

                    <?php if (empty($nilaiSiswaList)): ?>
                        <div class="text-center py-4 text-muted">
                            <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center p-3 mb-2"
                                style="width: 50px; height: 50px;">
                                <i class="bi bi-file-earmark-x fs-3 text-secondary opacity-50"></i>
                            </div>
                            <p class="small mb-0">Belum ada data nilai akademik dari guru pengampu.</p>
                        </div>
                    <?php else: ?>

                        <!-- QUICK MAPEL FILTER DROPDOWN SELECTOR -->
                        <div class="mb-3.5">
                            <div class="input-group rounded-3 overflow-hidden" style="border: 1px solid #cbd5e1;">
                                <span class="input-group-text bg-light border-0 ps-3 text-primary"><i
                                        class="bi bi-funnel-fill"></i></span>
                                <select class="form-select bg-light border-0 fw-bold py-2.5 pe-3" id="selectMapelFilter"
                                    onchange="filterMapelDropdown(this.value)" style="font-size: 0.82rem; color: #1e293b;">
                                    <option value="all">Tampilkan Semua Mata Pelajaran (<?= count($nilaiSiswaList) ?> Mapel)
                                    </option>
                                    <?php foreach ($nilaiSiswaList as $idx => $m): ?>
                                        <option value="<?= $idx ?>"><?= htmlspecialchars($m['mapel']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- ACCORDION LIST MAPEL PERFECTLY ALIGNED -->
                        <div class="mapel-accordion-container" id="mapelAccordionContainer">
                            <?php foreach ($nilaiSiswaList as $idx => $n): ?>
                                <?php
                                $kkm = (float) ($n['kkm'] ?? 75);

                                // Calculate valid scores average
                                $scores = array_filter([$n['sumatif_1'], $n['sumatif_2'], $n['sumatif_3'], $n['sumatif_4'], $n['ats'], $n['aas']], fn($val) => is_numeric($val) && (float) $val > 0);
                                $avgScore = !empty($scores) ? array_sum($scores) / count($scores) : 0;

                                // Contextual icon
                                $mLower = strtolower($n['mapel'] ?? '');
                                $iconMapel = 'bi-journal-bookmark-fill';
                                if (strpos($mLower, 'matematika') !== false || strpos($mLower, 'mtk') !== false || strpos($mLower, 'hitung') !== false) {
                                    $iconMapel = 'bi-calculator-fill';
                                } elseif (strpos($mLower, 'quran') !== false || strpos($mLower, 'hadits') !== false || strpos($mLower, 'pai') !== false || strpos($mLower, 'agama') !== false || strpos($mLower, 'fiqih') !== false || strpos($mLower, 'akidah') !== false) {
                                    $iconMapel = 'bi-book-half';
                                } elseif (strpos($mLower, 'inggris') !== false || strpos($mLower, 'arab') !== false || strpos($mLower, 'bahasa') !== false || strpos($mLower, 'indonesia') !== false) {
                                    $iconMapel = 'bi-translate';
                                } elseif (strpos($mLower, 'ipa') !== false || strpos($mLower, 'biologi') !== false || strpos($mLower, 'fisika') !== false || strpos($mLower, 'kimia') !== false || strpos($mLower, 'sains') !== false) {
                                    $iconMapel = 'bi-lightning-charge-fill';
                                } elseif (strpos($mLower, 'ips') !== false || strpos($mLower, 'sejarah') !== false || strpos($mLower, 'geografi') !== false || strpos($mLower, 'sosiologi') !== false) {
                                    $iconMapel = 'bi-globe-americas';
                                } elseif (strpos($mLower, 'penjas') !== false || strpos($mLower, 'pjok') !== false || strpos($mLower, 'olahraga') !== false) {
                                    $iconMapel = 'bi-trophy-fill';
                                } elseif (strpos($mLower, 'seni') !== false || strpos($mLower, 'budaya') !== false || strpos($mLower, 'prakarya') !== false) {
                                    $iconMapel = 'bi-palette-fill';
                                } elseif (strpos($mLower, 'informatika') !== false || strpos($mLower, 'tik') !== false || strpos($mLower, 'komputer') !== false) {
                                    $iconMapel = 'bi-laptop-fill';
                                }
                                ?>
                                <div class="mapel-accordion-item <?= $idx === 0 ? 'open' : '' ?>" id="mapel-item-<?= $idx ?>">
                                    <!-- HEADER TOGGLE (KLIK UNTUK BUKA DROPDOWN) -->
                                    <div class="mapel-accordion-header" onclick="toggleMapelAccordion(<?= $idx ?>)">
                                        <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1">
                                            <div class="mapel-icon-pod">
                                                <i class="bi <?= $iconMapel ?>"></i>
                                            </div>
                                            <div class="min-w-0 flex-grow-1">
                                                <h6 class="mapel-title text-truncate">
                                                    <?= htmlspecialchars($n['mapel']) ?>
                                                </h6>
                                                <div class="d-flex align-items-center gap-1.5 mt-1.5 flex-wrap">
                                                    <span class="mapel-meta-pill mapel-meta-kkm">
                                                        <i class="bi bi-shield-check"></i> KKM: <?= $kkm ?>
                                                    </span>
                                                    <?php if ($avgScore > 0): ?>
                                                        <span class="mapel-meta-pill mapel-meta-avg">
                                                            <i class="bi bi-award-fill"></i> Rata-rata:
                                                            <?= number_format($avgScore, 1) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-center flex-shrink-0 rounded-circle bg-light ms-2"
                                            style="width: 32px; height: 32px;">
                                            <i class="bi bi-chevron-down chevron-icon text-primary fs-6"></i>
                                        </div>
                                    </div>

                                    <!-- BODY DROPDOWN RINCIAN NILAI -->
                                    <div class="mapel-accordion-body">
                                        <?php
                                        $s1_val = (float) ($n['sumatif_1'] ?? 0);
                                        $s2_val = (float) ($n['sumatif_2'] ?? 0);
                                        $s3_val = (float) ($n['sumatif_3'] ?? 0);
                                        $s4_val = (float) ($n['sumatif_4'] ?? 0);
                                        $ats_val = (float) ($n['ats'] ?? 0);
                                        $aas_val = (float) ($n['aas'] ?? 0);

                                        $formatGrade = function ($val) {
                                            if ($val <= 0)
                                                return '—';
                                            return $val == (int) $val ? (int) $val : number_format($val, 1);
                                        };
                                        ?>
                                        <!-- SUMATIF 1 - 4 -->
                                        <div class="akademik-subhead">
                                            <i class="bi bi-ui-checks"></i> Nilai Sumatif Formatif
                                        </div>
                                        <div class="sumatif-grid">
                                            <div class="sumatif-card <?= $s1_val > 0 ? 'filled' : 'empty' ?>">
                                                <span class="sumatif-label">Sum 1</span>
                                                <div class="sumatif-score"><?= $formatGrade($s1_val) ?></div>
                                            </div>
                                            <div class="sumatif-card <?= $s2_val > 0 ? 'filled' : 'empty' ?>">
                                                <span class="sumatif-label">Sum 2</span>
                                                <div class="sumatif-score"><?= $formatGrade($s2_val) ?></div>
                                            </div>
                                            <div class="sumatif-card <?= $s3_val > 0 ? 'filled' : 'empty' ?>">
                                                <span class="sumatif-label">Sum 3</span>
                                                <div class="sumatif-score"><?= $formatGrade($s3_val) ?></div>
                                            </div>
                                            <div class="sumatif-card <?= $s4_val > 0 ? 'filled' : 'empty' ?>">
                                                <span class="sumatif-label">Sum 4</span>
                                                <div class="sumatif-score"><?= $formatGrade($s4_val) ?></div>
                                            </div>
                                        </div>

                                        <!-- ATS (UTS) & AAS (UAS) -->
                                        <div class="akademik-subhead">
                                            <i class="bi bi-award-fill"></i> Nilai Ujian Semester
                                        </div>
                                        <div class="exam-score-grid">
                                            <div class="exam-score-card ats <?= $ats_val > 0 ? '' : 'empty' ?>">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="exam-icon-circle">
                                                        <i class="bi bi-journal-check"></i>
                                                    </div>
                                                    <div>
                                                        <div class="exam-label">ATS (UTS)</div>
                                                        <div class="exam-sub">Tengah Semester</div>
                                                    </div>
                                                </div>
                                                <div class="exam-value"><?= $formatGrade($ats_val) ?></div>
                                            </div>
                                            <div class="exam-score-card aas <?= $aas_val > 0 ? '' : 'empty' ?>">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="exam-icon-circle">
                                                        <i class="bi bi-trophy-fill"></i>
                                                    </div>
                                                    <div>
                                                        <div class="exam-label">AAS (UAS)</div>
                                                        <div class="exam-sub">Akhir Semester</div>
                                                    </div>
                                                </div>
                                                <div class="exam-value"><?= $formatGrade($aas_val) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- HELP BLUE HERO BOX MATCHING REF IMAGE 1 -->
                <div class="p-4 rounded-4 text-white"
                    style="background: linear-gradient(135deg, #003b87 0%, #0a4b9c 100%); border-radius: 26px;">
                    <h4 class="fw-bold mb-2">Punya Pertanyaan Mengenai Nilai?</h4>
                    <p class="small text-white-50 mb-3">Jika terdapat ketidaksesuaian nilai atau butuh konsultasi
                        akademik, silakan hubungi Wali Kelas
                        (<?= htmlspecialchars($loggedSiswa['wali_kelas'] ?? 'Wali Kelas') ?>) atau Bagian Kurikulum.</p>
                    <div class="d-flex gap-2">
                        <a href="https://wa.me/?text=Halo%20Wali%20Kelas%20saya%20orang%20tua%20dari%20<?= urlencode($loggedSiswa['nama'] ?? '') ?>"
                            target="_blank" class="btn btn-light btn-sm fw-bold rounded-pill px-3 text-primary">Hubungi
                            Wali Kelas</a>
                        <button class="btn btn-outline-light btn-sm fw-bold rounded-pill px-3"
                            onclick="alert('Skala penilaian: A (>=85), B (75-84), C (<75)')">Panduan Penilaian</button>
                    </div>
                </div>

            </div>

            <!-- ========================================== -->
            <!-- TAB NOTIFIKASI -->
            <!-- ========================================== -->
            <div class="tab-view <?= $activeTab === 'notifikasi' ? 'active animate-page-enter' : '' ?>"
                id="tab-notifikasi">

                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h4 class="fw-bold text-dark m-0" style="font-family: 'Outfit', sans-serif;"><i
                                class="bi bi-bell-fill text-primary me-2"></i> Notifikasi &amp; Informasi</h4>
                        <p class="text-muted small mb-0">Riwayat pemberitahuan sistem &amp; transaksi siswa</p>
                    </div>
                    <?php if (!empty($notifList) && $unreadNotifCount > 0): ?>
                        <a href="portal-ortu.php?action=mark_notif_read"
                            class="btn btn-sm btn-outline-primary rounded-pill extra-small fw-bold px-3"
                            id="btnMarkAllRead">
                            <i class="bi bi-check2-all me-1"></i> Tandai Semua Dibaca
                        </a>
                    <?php endif; ?>
                </div>

                <div id="notifEmptyState"
                    class="widget-card text-center py-4 mb-3 <?= !empty($notifList) ? 'd-none' : '' ?>">
                    <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center p-3 text-muted mb-2"
                        style="width: 60px; height: 60px;">
                        <i class="bi bi-bell-slash fs-2 opacity-50"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">Belum Ada Notifikasi</h6>
                    <p class="text-muted small mb-0">Pemberitahuan belanja kantin, tagihan, &amp; nilai siswa akan
                        muncul di sini.</p>
                </div>

                <?php if (!empty($notifList)): ?>
                    <div class="d-flex flex-column gap-2 mb-4" id="notifListContainer">
                        <?php foreach ($notifList as $n): ?>
                            <?php
                            $isUnread = ((int) ($n['is_read'] ?? 0) === 0);
                            $iconClass = !empty($n['icon']) ? $n['icon'] : 'bi-bell-fill';
                            ?>
                            <div class="widget-card p-3 d-flex align-items-start gap-3 position-relative notif-item-card <?= $isUnread ? 'border-primary bg-primary-subtle' : 'bg-white' ?>"
                                onclick="showNotifDetail(<?= (int) $n['id'] ?>, <?= htmlspecialchars(json_encode($n['judul'])) ?>, <?= htmlspecialchars(json_encode($n['pesan'])) ?>, '<?= date('d M Y H:i', strtotime($n['created_at'])) ?>', '<?= htmlspecialchars($iconClass) ?>', <?= $isUnread ? 'true' : 'false' ?>, this)"
                                title="Klik untuk melihat detail pemberitahuan"
                                style="cursor: pointer; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);">

                                <div class="rounded-circle p-2 bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                                    style="width: 40px; height: 40px;">
                                    <i class="bi <?= htmlspecialchars($iconClass) ?> fs-5"></i>
                                </div>

                                <div class="flex-grow-1 pe-3">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <strong
                                            class="text-dark small me-2"><?= htmlspecialchars($n['judul'] ?? 'Pemberitahuan') ?></strong>
                                        <span
                                            class="text-muted extra-small"><?= date('d M Y H:i', strtotime($n['created_at'])) ?></span>
                                    </div>
                                    <p class="text-secondary small mb-0" style="line-height: 1.4;">
                                        <?= nl2br(htmlspecialchars($n['pesan'] ?? '')) ?>
                                    </p>
                                </div>

                                <button type="button" class="btn-close-notif-item"
                                    onclick="event.stopPropagation(); dismissNotifCard(<?= (int) $n['id'] ?>, this.closest('.notif-item-card'))"
                                    title="Hapus Notifikasi">
                                    <i class="bi bi-x-lg"></i>
                                </button>

                                <?php if ($isUnread): ?>
                                    <span
                                        class="position-absolute top-0 end-0 m-2 p-1 bg-primary border border-light rounded-circle notif-dot-unread"
                                        title="Belum dibaca"></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>

            <!-- ========================================== -->
            <!-- TAB 5: PROFIL (MATCHING REF IMAGE 2) -->
            <!-- ========================================== -->
            <div class="tab-view <?= $activeTab === 'profil' ? 'active animate-page-enter' : '' ?>" id="tab-profil">

                <!-- TOP PROFILE BANNER MATCHING REF IMAGE 2 -->
                <div class="text-center mb-4">
                    <div class="avatar-container-hero mb-2">
                        <div class="avatar-hero-img position-relative mx-auto"
                            style="width: 90px; height: 90px; font-size: 2.2rem; cursor: pointer;"
                            onclick="showUploadFotoModal()" title="Klik untuk ganti foto profil">
                            <?php if (!empty($fotoSiswaUrl)): ?>
                                <img src="<?= $fotoSiswaUrl ?>" alt="Foto">
                            <?php else: ?>
                                <?= strtoupper(substr($loggedSiswa['nama'] ?? 'S', 0, 1)) ?>
                            <?php endif; ?>
                            <div class="btn-camera-badge" style="width: 30px; height: 30px; font-size: 0.85rem;"
                                title="Ganti Foto">
                                <i class="bi bi-camera-fill"></i>
                            </div>
                        </div>
                    </div>

                    <h3 class="fw-extrabold text-dark m-0" style="font-family: 'Outfit', sans-serif;">
                        <?= htmlspecialchars($loggedSiswa['nama'] ?? 'Siswa') ?>
                    </h3>
                    <p class="text-muted small mb-2">Siswa Kelas
                        <?= htmlspecialchars($loggedSiswa['nama_kelas'] ?? '-') ?> &bull; NIS:
                        <?= htmlspecialchars($loggedSiswa['nis'] ?? '-') ?>
                    </p>
                    <button type="button"
                        class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold extra-small mt-1"
                        onclick="showUploadFotoModal()">
                        <i class="bi bi-camera-fill me-1"></i> Ubah Foto Profil
                    </button>
                </div>

                <!-- CARD 1: DATA LENGKAP SISWA REAL DB -->
                <div class="widget-card mb-3">
                    <h4 class="section-title-text mb-3"><i class="bi bi-person-vcard text-primary me-2"></i> Data
                        Lengkap Siswa</h4>

                    <div class="d-flex flex-column gap-2 small">
                        <div>
                            <span class="text-muted d-block extra-small fw-bold">NOMOR INDUK SISWA (NIS)</span>
                            <strong class="text-dark"><?= htmlspecialchars($loggedSiswa['nis'] ?? '-') ?></strong>
                        </div>
                        <div class="border-top pt-2">
                            <span class="text-muted d-block extra-small fw-bold">KELAS & TINGKAT</span>
                            <strong class="text-dark">Kelas <?= htmlspecialchars($loggedSiswa['nama_kelas'] ?? '-') ?>
                                (Tingkat <?= htmlspecialchars($loggedSiswa['tingkat'] ?? '-') ?>)</strong>
                        </div>
                        <div class="border-top pt-2">
                            <span class="text-muted d-block extra-small fw-bold">JENIS KELAMIN</span>
                            <strong
                                class="text-dark"><?= ($loggedSiswa['jenis_kelamin'] ?? 'L') === 'L' ? 'Laki-laki' : 'Perempuan' ?></strong>
                        </div>
                        <div class="border-top pt-2">
                            <span class="text-muted d-block extra-small fw-bold">TAHUN ANGKATAN / MASUK</span>
                            <strong
                                class="text-dark"><?= htmlspecialchars($loggedSiswa['tahun_masuk'] ?? date('Y')) ?></strong>
                        </div>
                    </div>
                </div>

                <!-- CARD 2: DATA ORANG TUA / WALI REAL DB -->
                <div class="widget-card mb-3">
                    <h4 class="section-title-text mb-3"><i class="bi bi-people-fill text-primary me-2"></i> Data Orang
                        Tua / Wali</h4>

                    <div class="d-flex flex-column gap-2 small">
                        <div>
                            <span class="text-muted d-block extra-small fw-bold">NAMA WALI / ORANG TUA</span>
                            <strong
                                class="text-dark"><?= htmlspecialchars($userOrtu['nama_lengkap'] ?? $loggedSiswa['nama'] ?? 'Orang Tua') ?></strong>
                        </div>
                        <div class="border-top pt-2">
                            <span class="text-muted d-block extra-small fw-bold">USERNAME AKUN LOGIN</span>
                            <strong
                                class="text-dark"><?= htmlspecialchars($userOrtu['username'] ?? $loggedSiswa['nis'] ?? '-') ?></strong>
                        </div>
                        <div class="border-top pt-2">
                            <span class="text-muted d-block extra-small fw-bold">STATUS AKUN</span>
                            <span
                                class="badge bg-success-subtle text-success border border-success fw-bold">TERVERIFIKASI
                                (AKTIF)</span>
                        </div>
                    </div>
                </div>

                <!-- KELUAR LOGOUT BUTTON -->
                <div class="mb-4">
                    <a href="logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold py-2"><i
                            class="bi bi-box-arrow-right me-1"></i> Keluar dari Akun</a>
                </div>

            </div>


            <!-- TAB: KESANTRIAN (Orang Tua) -->
            <div class="tab-view kesantrian-shell <?= $activeTab === 'kesantrian' ? 'active animate-page-enter' : '' ?>"
                id="tab-kesantrian">

                <!-- HEADER SECTION (MATCHING KANTIN & AKADEMIK) -->
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <div
                            style="background: #e0edff; color: #1d4ed8; border: 1px solid #bfdbfe; border-radius: 50px; font-weight: 800; font-size: 0.7rem; padding: 5px 14px; display: inline-flex; align-items: center; gap: 8px; letter-spacing: 0.3px; margin-bottom: 0.65rem;">
                            <i class="bi bi-moon-stars-fill"></i> KESANTRIAN
                        </div>
                        <h2 class="h4 fw-extrabold text-dark"
                            style="font-family: 'Outfit', sans-serif; letter-spacing: -0.02em; margin-bottom: 0.35rem; line-height: 1.3;">
                            Perjalanan Santri</h2>
                        <small class="text-muted" style="display: block; line-height: 1.45; font-size: 0.8rem;">Pantau
                            kedisiplinan, poin santri, &amp; perkembangan hafalan Al-Qur'an</small>
                    </div>
                </div>

                <?php
                // Rekalkulasi ringkas statistik Tahfidz untuk tampil di tab ini (isolated scope)
                $mumtazCount = 0;
                $lulusCount = 0;
                $cntZiyadah = 0;
                $cntMurojaah = 0;
                $cntTahsin = 0;
                $cntUjian = 0;
                foreach ($halaqahSetoranList ?? [] as $hsItem) {
                    if ((($hsItem['penilaian'] ?? '') === 'mumtaz'))
                        $mumtazCount++;
                    if ((($hsItem['status_setoran'] ?? '') === 'lulus'))
                        $lulusCount++;
                    $tKey = strtolower(trim($hsItem['tipe_setoran'] ?? 'ziyadah'));
                    if (strpos($tKey, 'ziyadah') !== false || strpos($tKey, 'nambah') !== false) {
                        $cntZiyadah++;
                    } elseif (strpos($tKey, 'muroj') !== false) {
                        $cntMurojaah++;
                    } elseif (strpos($tKey, 'tahsin') !== false) {
                        $cntTahsin++;
                    } elseif (strpos($tKey, 'ujian') !== false) {
                        $cntUjian++;
                    } else {
                        $cntZiyadah++;
                    }
                }
                ?>

                <!-- SEGMENT SWITCHER -->
                <div class="kesantrian-segmented mb-4">
                    <button type="button" class="kesantrian-seg active" onclick="switchKesantrianSeg('poin', this)"><i
                            class="bi bi-trophy-fill"></i>Poin Santri</button>
                    <button type="button" class="kesantrian-seg" onclick="switchKesantrianSeg('tahfidz', this)"><i
                            class="bi bi-book-half"></i>Tahfidz</button>
                </div>

                <!-- POIN SECTION -->
                <div id="kesantrian-poin-section">
                    <div class="poin-summary-card mb-4">
                        <div class="position-relative z-1">
                            <span class="summary-label"><i class="bi bi-trophy-fill"></i> SKOR KESELURUHAN</span>
                            <div class="summary-score" id="poin-neto">--</div>
                            <span class="summary-label" style="opacity: 0.82;">Akumulasi penghargaan &amp;
                                pelanggaran</span>
                            <div class="poin-breakdown">
                                <div class="poin-breakdown-item">
                                    <span><i class="bi bi-arrow-up-circle-fill text-success"></i>Penghargaan</span>
                                    <strong id="poin-penghargaan">--</strong>
                                </div>
                                <div class="poin-breakdown-item">
                                    <span><i class="bi bi-arrow-down-circle-fill text-danger"></i>Pelanggaran</span>
                                    <strong id="poin-pelanggaran">--</strong>
                                </div>
                            </div>
                        </div>
                        <!-- DECORATIVE BACKDROP ICON -->
                        <div class="position-absolute end-0 bottom-0 text-white pointer-events-none"
                            style="padding-right: 1.25rem; padding-bottom: 0.5rem; opacity: 0.08;">
                            <i class="bi bi-trophy" style="font-size: 5.5rem;"></i>
                        </div>
                    </div>

                    <div class="poin-history-card mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="poin-history-title">Riwayat Terbaru</h6>
                            <i class="bi bi-clock-history text-muted"></i>
                        </div>
                        <div id="riwayat-poin-list">
                            <div class="poin-state"><span class="spinner-border spinner-border-sm me-2"
                                    role="status"></span>Memuat riwayat poin…</div>
                        </div>
                    </div>

                    <div class="poin-ranking-card mb-4" id="poin-ranking-card">
                        <div class="poin-ranking-head">
                            <h6 class="poin-ranking-title"><i class="bi bi-bar-chart-fill text-primary me-2"></i>
                                Peringkat Santri</h6>
                            <span class="small text-muted fw-bold">Periode Ini</span>
                        </div>
                        <div id="poin-ranking-list" class="poin-state"><span
                                class="spinner-border spinner-border-sm me-2" role="status"></span>Memuat peringkat…
                        </div>
                    </div>
                </div>

                <!-- TAHFIDZ SECTION -->
                <div id="kesantrian-tahfidz-section" style="display:none;">
                    <!-- HERO TAHFIDZ CARD (MATCHING BERANDA SIGNATURE BLUE GRADIENT) -->
                    <div class="tahfidz-hero-card mb-4">
                        <div class="position-relative z-1">
                            <div
                                style="background: rgba(255, 255, 255, 0.16); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 50px; font-weight: 800; font-size: 0.68rem; padding: 5px 14px; display: inline-flex; align-items: center; gap: 7px; letter-spacing: 0.5px; backdrop-filter: blur(8px); margin-bottom: 0.65rem;">
                                <i class="bi bi-book-half" style="color: #38bdf8;"></i>
                                <?= $tampilkanTargetOrtu ? 'TARGET HAFALAN TAHFIDZ' : 'PROGRES TAHFIDZ SANTRI' ?>
                            </div>
                            <h4 class="fw-extrabold text-white mb-3 text-break"
                                style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; line-height: 1.4;">
                                <?= htmlspecialchars($targetHafalanText ?: "Perkembangan & Riwayat Setoran Al-Qur'an") ?>
                            </h4>

                            <!-- STATS PODS GRID -->
                            <div class="hero-akademik-stats-grid mt-3">
                                <div class="hero-akademik-stat-item">
                                    <div class="hero-akademik-stat-val"><?= count($halaqahSetoranList ?? []) ?></div>
                                    <div class="hero-akademik-stat-lbl">Setoran</div>
                                </div>
                                <div class="hero-akademik-stat-item">
                                    <div class="hero-akademik-stat-val"><?= $mumtazCount ?></div>
                                    <div class="hero-akademik-stat-lbl">Mumtaz</div>
                                </div>
                                <div class="hero-akademik-stat-item">
                                    <div class="hero-akademik-stat-val"><?= $lulusCount ?></div>
                                    <div class="hero-akademik-stat-lbl">Lulus</div>
                                </div>
                            </div>
                        </div>
                        <!-- DECORATIVE BACKDROP ICON -->
                        <div class="position-absolute end-0 bottom-0 text-white pointer-events-none"
                            style="padding-right: 1.25rem; padding-bottom: 0.5rem; opacity: 0.08;">
                            <i class="bi bi-book-half" style="font-size: 5.5rem;"></i>
                        </div>
                    </div>

                    <!-- RIWAYAT SETORAN HALAQAH WIDGET CARD -->
                    <div class="widget-card mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center flex-shrink-0"
                                    style="width: 40px; height: 40px;">
                                    <i class="bi bi-journal-text fs-5"></i>
                                </div>
                                <h6 class="fw-extrabold text-dark m-0" style="font-family: 'Outfit', sans-serif;">
                                    Riwayat Setoran</h6>
                            </div>
                            <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1 fw-bold"
                                style="font-size: 0.72rem;">
                                <?= count($halaqahSetoranList ?? []) ?> Setoran
                            </span>
                        </div>

                        <!-- TAHFIDZ CATEGORY FILTER PILLS -->
                        <div class="d-flex align-items-center gap-2 overflow-x-auto pb-2 mb-3 scrollbar-none"
                            id="tahfidzCategoryPills">
                            <button class="tahfidz-filter-pill active" onclick="filterTahfidzCat('all', this)">
                                Semua (<?= count($halaqahSetoranList ?? []) ?>)
                            </button>
                            <button class="tahfidz-filter-pill" onclick="filterTahfidzCat('ziyadah', this)">
                                📖 Ziyadah (<?= $cntZiyadah ?>)
                            </button>
                            <button class="tahfidz-filter-pill" onclick="filterTahfidzCat('murojaah', this)">
                                🔄 Muroja'ah (<?= $cntMurojaah ?>)
                            </button>
                            <button class="tahfidz-filter-pill" onclick="filterTahfidzCat('tahsin', this)">
                                🎙️ Tahsin (<?= $cntTahsin ?>)
                            </button>
                            <button class="tahfidz-filter-pill" onclick="filterTahfidzCat('ujian', this)">
                                🎓 Ujian (<?= $cntUjian ?>)
                            </button>
                        </div>

                        <?php if (empty($halaqahSetoranList)): ?>
                            <div class="text-center py-4 text-muted">
                                <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center p-3 mb-2"
                                    style="width: 54px; height: 54px;">
                                    <i class="bi bi-journal-album fs-3 text-primary opacity-50"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1 extra-small">Belum Ada Setoran</h6>
                                <small class="text-muted d-block extra-small">Catatan setoran halaqah dari Musyrif akan
                                    tampil di sini.</small>
                            </div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-2.5" id="tahfidzSetoranContainer">
                                <?php foreach ($halaqahSetoranList as $hs): ?>
                                    <?php
                                    $nBadge = [
                                        'mumtaz' => 'bg-success-subtle text-success border-success',
                                        'jayyid_jiddan' => 'bg-info-subtle text-info border-info',
                                        'jayyid' => 'bg-warning-subtle text-warning border-warning',
                                        'rasib' => 'bg-danger-subtle text-danger border-danger'
                                    ][$hs['penilaian']] ?? 'bg-light';

                                    $catKey = 'ziyadah';
                                    $catStyle = 'background: #e0edff; color: #0284c7; border: 1px solid #bae6fd;';
                                    $catIcon = 'bi-bookmark-check-fill';
                                    $catLabel = "Ziyadah (Hafalan Baru)";

                                    $tRaw = strtolower(trim($hs['tipe_setoran'] ?? 'ziyadah'));
                                    if (strpos($tRaw, 'muroj') !== false) {
                                        $catKey = 'murojaah';
                                        $catStyle = 'background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff;';
                                        $catIcon = 'bi-arrow-repeat';
                                        $catLabel = "Muroja'ah";
                                    } elseif (strpos($tRaw, 'tahsin') !== false) {
                                        $catKey = 'tahsin';
                                        $catStyle = 'background: #ccfbf1; color: #0f766e; border: 1px solid #99f6e4;';
                                        $catIcon = 'bi-mic-fill';
                                        $catLabel = "Tahsin";
                                    } elseif (strpos($tRaw, 'ujian') !== false) {
                                        $catKey = 'ujian';
                                        $catStyle = 'background: #ffe4e6; color: #be123c; border: 1px solid #fecdd3;';
                                        $catIcon = 'bi-award-fill';
                                        $catLabel = "Ujian Tahfidz";
                                    }

                                    $materiClean = preg_replace('/^(Surah\s+)+/i', 'Surah ', htmlspecialchars($hs['materi_setoran'] ?? ''));
                                    ?>
                                    <div class="p-3 rounded-4 border bg-white shadow-sm tahfidz-setoran-item mb-2.5"
                                        data-category="<?= $catKey ?>" style="transition: all 0.22s ease;">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span
                                                style="<?= $catStyle ?> font-weight: 800; font-size: 0.68rem; padding: 4px 12px; border-radius: 50px; display: inline-flex; align-items: center; gap: 6px;">
                                                <i class="bi <?= $catIcon ?>"></i> <?= $catLabel ?>
                                            </span>
                                            <small class="text-muted extra-small"><i
                                                    class="bi bi-calendar3 me-1.5"></i><?= date('d M Y', strtotime($hs['tanggal'])) ?></small>
                                        </div>

                                        <h6 class="fw-extrabold text-dark mb-2" style="font-size: 0.95rem; line-height: 1.35;">
                                            <?= $materiClean ?>
                                            <?php if (!empty($hs['metode_input'])): ?>
                                                <span class="badge bg-light text-secondary border rounded-pill ms-1 font-monospace"
                                                    style="font-size: 0.65rem; font-weight: normal; vertical-align: middle;">Metode:
                                                    <?= ucfirst(htmlspecialchars($hs['metode_input'])) ?></span>
                                            <?php endif; ?>
                                        </h6>

                                        <div
                                            class="d-flex align-items-center justify-content-between pt-2.5 border-top flex-wrap gap-2">
                                            <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                                <span
                                                    class="badge <?= $nBadge ?> border extra-small px-2 py-1 font-monospace fw-bold"
                                                    style="font-size: 0.68rem;">
                                                    NILAI: <?= strtoupper(str_replace('_', ' ', $hs['penilaian'])) ?>
                                                </span>
                                                <span
                                                    class="badge <?= $hs['status_setoran'] === 'lulus' ? 'bg-success text-white' : 'bg-danger text-white' ?> extra-small rounded-pill px-2.5 py-1"
                                                    style="font-size: 0.68rem; font-weight: 800;">
                                                    <?= $hs['status_setoran'] === 'lulus' ? '✓ Lulus' : '🔄 Mengulang' ?>
                                                </span>
                                            </div>
                                            <?php if (!empty($hs['nama_musyrif'])): ?>
                                                <small class="text-muted extra-small d-inline-flex align-items-center gap-1">
                                                    <i class="bi bi-person-circle text-secondary"></i> Musyrif:
                                                    <strong><?= htmlspecialchars($hs['nama_musyrif']) ?></strong>
                                                </small>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($hs['catatan_ortu'])): ?>
                                            <div
                                                class="mt-2.5 p-2.5 rounded-3 bg-light extra-small text-secondary border-start border-3 border-primary">
                                                <i class="bi bi-chat-left-quote-fill me-1.5 text-primary"></i>
                                                <em>"<?= htmlspecialchars($hs['catatan_ortu']) ?>"</em>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <!-- EMPTY STATE FILTER FOR TAHFIDZ CATEGORY -->
                                <div id="tahfidzEmptyFilterState" class="d-none text-center py-4 text-muted">
                                    <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center p-3 mb-2"
                                        style="width: 50px; height: 50px;">
                                        <i class="bi bi-journal-x fs-3 text-secondary opacity-50"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1 extra-small">Belum Ada Setoran <span
                                            id="tahfidzEmptyCatName"></span></h6>
                                    <small class="text-muted d-block extra-small">Belum ada riwayat setoran untuk kategori
                                        ini dari Musyrif.</small>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <script>
                    async function loadPoinOrtu() {
                        const siswaId = <?= (int) ($loggedSiswa['id'] ?? 0) ?>;
                        if (!siswaId) return;
                        const container = document.getElementById('riwayat-poin-list');
                        const setScore = (neto = '--', penghargaan = '--', pelanggaran = '--') => {
                            document.getElementById('poin-neto').textContent = neto;
                            document.getElementById('poin-penghargaan').textContent = penghargaan;
                            document.getElementById('poin-pelanggaran').textContent = pelanggaran;
                        };
                        const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, char => ({
                            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
                        })[char]);
                        const readJson = async (response) => {
                            const body = await response.text();
                            try { return JSON.parse(body); }
                            catch (_) { throw new Error('Respons layanan tidak valid'); }
                        };
                        try {
                            const res = await fetch('<?= BASE_URL ?>/pages/halaqah/poin_api.php?action=get_total_poin&siswa_id=' + siswaId);
                            const data = await readJson(res);
                            if (data && data.success) {
                                const d = data.data;
                                setScore(d.total_poin ?? 0, d.poin_penghargaan ?? 0, d.poin_pelanggaran ?? 0);
                            } else {
                                throw new Error(data?.error || 'Poin tidak dapat dimuat');
                            }

                            const res2 = await fetch('<?= BASE_URL ?>/pages/halaqah/poin_api.php?action=get_riwayat_poin&siswa_id=' + siswaId + '&limit=12');
                            const list = await readJson(res2);
                            if (!list?.success) throw new Error(list?.error || 'Riwayat tidak dapat dimuat');

                            if (!list.data?.length) {
                                container.innerHTML = '<div class="poin-state"><i class="bi bi-stars d-block fs-4 mb-2 text-warning"></i>Belum ada riwayat poin untuk santri ini.</div>';
                                return;
                            }
                            container.innerHTML = list.data.map(it => {
                                const reward = it.tipe_poin === 'penghargaan';
                                const icon = escapeHtml(it.icon || (reward ? 'bi-trophy-fill' : 'bi-exclamation-circle-fill'));
                                const color = escapeHtml(it.color || (reward ? '#059669' : '#dc2626'));
                                return `<div class="poin-history-item">
                            <div class="d-flex align-items-center gap-3 overflow-hidden">
                                <div class="poin-history-icon" style="color:${color}"><i class="bi ${icon}"></i></div>
                                <div class="text-truncate"><span class="poin-history-name d-block text-truncate">${escapeHtml(it.nama_kategori)}</span><span class="poin-history-detail text-truncate">${escapeHtml(it.deskripsi || 'Catatan kedisiplinan santri')}</span></div>
                            </div>
                            <span class="poin-value ${reward ? 'reward' : 'violation'}">${reward ? '+' : '−'}${escapeHtml(it.nilai_poin)}</span>
                        </div>`;
                            }).join('');
                        } catch (e) {
                            console.error('Gagal memuat poin:', e);
                            setScore();
                            if (container) container.innerHTML = `<div class="poin-state"><i class="bi bi-wifi-off d-block fs-4 mb-2 text-danger"></i>Data poin belum dapat dimuat.<br><button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="loadPoinOrtu()">Coba lagi</button></div>`;
                        }
                    }

                    async function loadRankingOrtu() {
                        const siswaId = <?= (int) ($loggedSiswa['id'] ?? 0) ?>;
                        const container = document.getElementById('poin-ranking-list');
                        if (!siswaId || !container) return;
                        const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[char]);
                        try {
                            const response = await fetch('<?= BASE_URL ?>/pages/halaqah/poin_api.php?action=get_leaderboard&tipe=total&limit=100&bulan=<?= date('n') ?>&tahun=<?= date('Y') ?>');
                            const data = await response.json();
                            const ranking = data?.success ? (data.data || []) : [];
                            if (!ranking.length) {
                                container.innerHTML = '<div class="poin-state"><i class="bi bi-stars d-block fs-4 mb-2 text-primary"></i>Belum ada peringkat poin pada periode ini.</div>';
                                return;
                            }
                            const ownIndex = ranking.findIndex(item => Number(item.id) === siswaId);
                            const renderRow = (item, index, isOwn = false) => {
                                const score = (Number(item.poin_penghargaan) || 0) - (Number(item.poin_pelanggaran) || 0);
                                return `<div class="poin-rank-spot ${isOwn ? 'rounded-3 px-2 bg-white' : ''}">
                            <span class="poin-rank-badge ${index === 0 ? 'top' : ''}">${index + 1}</span>
                            <div class="min-w-0"><span class="poin-rank-name d-block text-truncate">${escapeHtml(item.nama)}${isOwn ? ' <span class="text-primary fw-bold">(Santri Anda)</span>' : ''}</span><span class="poin-rank-meta">${escapeHtml(item.nama_kelas)}</span></div>
                            <span class="poin-rank-score">${score > 0 ? '+' : ''}${score}</span>
                        </div>`;
                            };
                            const ownRow = ownIndex >= 0 ? renderRow(ranking[ownIndex], ownIndex, true) : '<div class="poin-state">Santri Anda belum memiliki poin pada periode ini.</div>';
                            const topRows = ranking.slice(0, 3).map((item, index) => Number(item.id) !== siswaId ? renderRow(item, index) : '').join('');
                            container.innerHTML = `${ownRow}${topRows ? '<div class="small text-muted fw-bold mt-3 mb-1">PERINGKAT TERATAS</div>' + topRows : ''}`;
                        } catch (error) {
                            console.error('Gagal memuat peringkat:', error);
                            container.innerHTML = '<div class="poin-state">Peringkat belum dapat dimuat.</div>';
                        }
                    }

                    function switchKesantrianSeg(seg, btn) {
                        document.querySelectorAll('.kesantrian-seg').forEach(b => b.classList.remove('active'));
                        if (btn) btn.classList.add('active');
                        const poinSec = document.getElementById('kesantrian-poin-section');
                        const tahfidzSec = document.getElementById('kesantrian-tahfidz-section');
                        if (poinSec) poinSec.style.display = seg === 'poin' ? '' : 'none';
                        if (tahfidzSec) tahfidzSec.style.display = seg === 'tahfidz' ? '' : 'none';
                    }

                    document.addEventListener('DOMContentLoaded', () => {
                        loadPoinOrtu();
                        loadRankingOrtu();
                    });
                </script>
            </div>

        </main>

        <!-- FLOATING PILL BOTTOM NAVIGATION BAR (FIXED PINNED ON HP SCREEN) -->
        <nav class="floating-bottom-nav">
            <button class="nav-tab-item <?= $activeTab === 'beranda' ? 'active' : '' ?>" id="nav-beranda"
                onclick="switchTab('beranda')">
                <div class="nav-tab-icon-wrapper"><i class="bi bi-house-door-fill"></i></div>
                <span class="nav-tab-label">Beranda</span>
            </button>

            <button class="nav-tab-item <?= $activeTab === 'kesantrian' ? 'active' : '' ?>" id="nav-kesantrian"
                onclick="switchTab('kesantrian')">
                <div class="nav-tab-icon-wrapper"><i class="bi bi-moon-stars-fill"></i></div>
                <span class="nav-tab-label">Kesantrian</span>
            </button>

            <button class="nav-tab-item <?= $activeTab === 'kantin' ? 'active' : '' ?>" id="nav-kantin"
                onclick="switchTab('kantin')">
                <div class="nav-tab-icon-wrapper"><i class="bi bi-cup-hot-fill"></i></div>
                <span class="nav-tab-label">Kantin</span>
            </button>

            <button class="nav-tab-item <?= $activeTab === 'nilai' ? 'active' : '' ?>" id="nav-nilai"
                onclick="switchTab('nilai')">
                <div class="nav-tab-icon-wrapper"><i class="bi bi-journal-bookmark-fill"></i></div>
                <span class="nav-tab-label">Akademik</span>
            </button>

            <button class="nav-tab-item <?= $activeTab === 'profil' || $activeTab === 'notifikasi' ? 'active' : '' ?>"
                id="nav-profil" onclick="switchTab('profil')">
                <div class="nav-tab-icon-wrapper"><i class="bi bi-person-fill"></i></div>
                <span class="nav-tab-label">Profil</span>
            </button>
        </nav>
    </div>

    <!-- MODAL BARCODE / QR NIS -->
    <div class="modal fade" id="modalQrNis" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-3 text-center rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-bold modal-title" style="font-family: 'Outfit', sans-serif;">Barcode NIS Siswa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="p-3 bg-white border rounded-4 d-inline-block shadow-sm mb-3">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($loggedSiswa['nis'] ?? '') ?>"
                            alt="QR Code" width="180" height="180">
                    </div>
                    <h4 class="fw-extrabold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">
                        <?= htmlspecialchars($loggedSiswa['nama'] ?? '') ?>
                    </h4>
                    <p class="text-muted small mb-0">NIS:
                        <strong><?= htmlspecialchars($loggedSiswa['nis'] ?? '') ?></strong> &bull; Kelas
                        <?= htmlspecialchars($loggedSiswa['nama_kelas'] ?? '') ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL GANTI FOTO PROFIL -->
    <div class="modal fade" id="modalGantiFoto" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-3 rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-bold modal-title" style="font-family: 'Outfit', sans-serif;"><i
                            class="bi bi-camera-fill text-primary me-2"></i> Ganti Foto Profil Siswa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="portal-ortu.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_foto">
                    <div class="modal-body text-center py-4">
                        <div class="avatar-hero-img mx-auto mb-3"
                            style="width: 100px; height: 100px; font-size: 2.5rem;">
                            <?php if (!empty($fotoSiswaUrl)): ?>
                                <img src="<?= $fotoSiswaUrl ?>" alt="Foto" id="previewFotoProfile">
                            <?php else: ?>
                                <span
                                    id="previewFotoProfileText"><?= strtoupper(substr($loggedSiswa['nama'] ?? 'S', 0, 1)) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3 text-start">
                            <label class="form-label extra-small fw-bold text-muted">Pilih File Foto Baru (JPG, PNG,
                                WEBP)</label>
                            <input type="file" name="foto_siswa" id="inputFotoSiswa"
                                class="form-control form-control-sm rounded-3"
                                accept="image/png, image/jpeg, image/jpg, image/webp" onchange="previewImageOrtu(this)"
                                required>
                            <div class="form-text extra-small">Maksimal 5MB. Foto otomatis tersambung ke Kartu Siswa
                                &amp; Admin Sekolah.</div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-sm btn-light rounded-pill px-3"
                            data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 fw-bold"><i
                                class="bi bi-upload me-1"></i> Simpan Foto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL TOP-UP SALDO E-KANTIN -->
    <div class="modal fade" id="modalTopupKantin" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-3 rounded-4 border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-bold modal-title text-primary" style="font-family: 'Outfit', sans-serif;"><i
                            class="bi bi-wallet2 me-2"></i> Top-Up Saldo E-Kantin Siswa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="portal-ortu.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="topup_kantin">
                    <div class="modal-body py-3">

                        <!-- Saldo Saat Ini Banner -->
                        <div
                            class="p-3 bg-primary-subtle rounded-3 d-flex align-items-center justify-content-between mb-3 border border-primary-subtle">
                            <div>
                                <small class="text-primary-emphasis d-block extra-small fw-bold">SALDO E-KANTIN SAAT
                                    INI</small>
                                <strong class="fs-5 text-primary" style="font-family: 'Outfit', sans-serif;">Rp
                                    <?= number_format($saldoKantin, 0, ',', '.') ?></strong>
                            </div>
                            <div class="p-2 bg-primary text-white rounded-circle">
                                <i class="bi bi-shop fs-4"></i>
                            </div>
                        </div>

                        <!-- Quick Preset Nominal -->
                        <div class="mb-3">
                            <label class="form-label extra-small fw-bold text-muted mb-1">PILIH NOMINAL CEPAT</label>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button"
                                    class="btn btn-outline-primary btn-sm rounded-pill flex-fill fw-bold"
                                    onclick="setTopupNominal(20000)">+ Rp 20rb</button>
                                <button type="button"
                                    class="btn btn-outline-primary btn-sm rounded-pill flex-fill fw-bold"
                                    onclick="setTopupNominal(50000)">+ Rp 50rb</button>
                                <button type="button"
                                    class="btn btn-outline-primary btn-sm rounded-pill flex-fill fw-bold"
                                    onclick="setTopupNominal(100000)">+ Rp 100rb</button>
                                <button type="button"
                                    class="btn btn-outline-primary btn-sm rounded-pill flex-fill fw-bold"
                                    onclick="setTopupNominal(200000)">+ Rp 200rb</button>
                            </div>
                        </div>

                        <!-- Input Nominal -->
                        <div class="mb-3">
                            <label class="form-label extra-small fw-bold text-muted mb-1">NOMINAL ISI SALDO (RP)
                                *</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0 font-monospace fw-bold">Rp</span>
                                <input type="number" name="nominal_topup" id="inputNominalTopup"
                                    class="form-control border-start-0 fs-6 fw-bold text-primary" placeholder="50000"
                                    min="5000" step="5000" required>
                            </div>
                        </div>

                        <!-- Bank Account Info -->
                        <div class="p-3 bg-light rounded-3 mb-3 border">
                            <small class="fw-bold d-block text-dark mb-1"><i class="bi bi-bank me-1 text-primary"></i>
                                REKENING TRANSFER E-KANTIN</small>
                            <div class="d-flex align-items-center justify-content-between small">
                                <span class="text-muted">BSI (Bank Syariah)</span>
                                <strong class="font-monospace text-dark">7700-8899-001</strong>
                            </div>
                            <div class="d-flex align-items-center justify-content-between small">
                                <span class="text-muted">A.N. Rekening</span>
                                <strong class="text-dark">E-Kantin Sekolah</strong>
                            </div>
                        </div>

                        <!-- Upload Bukti Transfer -->
                        <div class="mb-2">
                            <label class="form-label extra-small fw-bold text-muted mb-1">UNGGAH BUKTI TRANSFER
                                *</label>
                            <input type="file" name="bukti_transfer" class="form-control form-control-sm rounded-3"
                                accept="image/png, image/jpeg, image/jpg, image/webp, application/pdf" required>
                            <div class="form-text extra-small">Format: Foto JPG, PNG, WEBP, atau PDF. Maksimal 5MB.
                            </div>
                        </div>

                        <!-- Catatan Tambahan -->
                        <div class="mb-2">
                            <label class="form-label extra-small fw-bold text-muted mb-1">CATATAN / KETERANGAN
                                (OPSIONAL)</label>
                            <input type="text" name="catatan_topup" class="form-control form-control-sm rounded-3"
                                placeholder="Contoh: Transfer via BSI Mobile jam 10 pagi">
                        </div>

                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-sm btn-light rounded-pill px-3"
                            data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 fw-bold">
                            <i class="bi bi-send-fill me-1"></i> Kirim Permohonan Top-Up
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let isBalanceHidden = false;

        function showUploadFotoModal() {
            const modal = new bootstrap.Modal(document.getElementById('modalGantiFoto'));
            modal.show();
        }

        function previewImageOrtu(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const img = document.getElementById('previewFotoProfile');
                    const txt = document.getElementById('previewFotoProfileText');
                    if (img) {
                        img.src = e.target.result;
                    } else if (txt) {
                        txt.parentNode.innerHTML = `<img src="${e.target.result}" alt="Preview" id="previewFotoProfile">`;
                    }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function toggleBalanceVisibility() {
            const txtVal = document.getElementById('txtBalanceValue');
            const txtHid = document.getElementById('txtBalanceHidden');
            const iconEye = document.getElementById('iconEyeBalance');

            isBalanceHidden = !isBalanceHidden;

            if (isBalanceHidden) {
                txtVal.classList.add('d-none');
                txtHid.classList.remove('d-none');
                iconEye.className = 'bi bi-eye-slash-fill';
            } else {
                txtVal.classList.remove('d-none');
                txtHid.classList.add('d-none');
                iconEye.className = 'bi bi-eye-fill';
            }
        }

        function switchTab(tabName) {
            document.querySelectorAll('.tab-view').forEach(el => {
                el.classList.remove('active', 'animate-page-enter');
            });
            document.querySelectorAll('.nav-tab-item').forEach(el => el.classList.remove('active'));

            const targetTab = document.getElementById('tab-' + tabName);
            const targetNav = document.getElementById('nav-' + tabName);

            if (targetTab) {
                targetTab.classList.add('active');
                // Force DOM reflow to re-trigger pageEnter CSS animation
                void targetTab.offsetWidth;
                targetTab.classList.add('animate-page-enter');
            } else {
                // If the tab DOM is not present (error cases), fallback to server-rendered tab via query param
                const url = new URL(window.location.href);
                url.searchParams.set('tab', tabName);
                window.location.href = url.toString();
                return;
            }

            if (targetNav) {
                targetNav.classList.add('active');
            }

            window.scrollTo({ top: 0, behavior: 'instant' });
        }

        function filterKantinItems() {
            const query = document.getElementById('inputSearchKantin').value.toLowerCase();
            const items = document.querySelectorAll('.kantin-tx-item');

            items.forEach(item => {
                const title = item.querySelector('.item-title-txt').innerText.toLowerCase();
                if (title.includes(query)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function showNisQrModal() {
            const modal = new bootstrap.Modal(document.getElementById('modalQrNis'));
            modal.show();
        }

        function showTopupModal() {
            const modal = new bootstrap.Modal(document.getElementById('modalTopupKantin'));
            modal.show();
        }

        function setTopupNominal(val) {
            const input = document.getElementById('inputNominalTopup');
            if (input) input.value = val;
        }

        function paySpecificBill(jenisVal) {
            switchTab('pembayaran');
            const select = document.getElementById('selectJenisBayar');
            if (select) {
                select.value = jenisVal;
                toggleBayarOptions();
                const formCard = document.getElementById('form-pembayaran-card');
                if (formCard) {
                    setTimeout(() => {
                        formCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, 50);
                }
            }
        }

        function toggleBayarOptions() {
            const select = document.getElementById('selectJenisBayar');
            const sppWrapper = document.getElementById('wrapperSppBulan');
            const inputNominal = document.getElementById('inputNominal');

            if (!select || !inputNominal) return;

            const val = select.value;
            if (val === 'spp') {
                sppWrapper.classList.remove('d-none');
                calcSppTotal();
            } else {
                sppWrapper.classList.add('d-none');
                const selectedOpt = select.options[select.selectedIndex];
                const dataNominal = selectedOpt.getAttribute('data-nominal');
                if (dataNominal) {
                    inputNominal.value = dataNominal;
                } else {
                    inputNominal.value = '';
                }
            }
        }

        function calcSppTotal() {
            const checkboxes = document.querySelectorAll('.chk-spp-month:checked');
            const inputNominal = document.getElementById('inputNominal');
            const ratePerMonth = <?= (float) $nominalSppDefault ?>;

            const total = checkboxes.length * ratePerMonth;
            inputNominal.value = total > 0 ? total : '';
        }

        function dismissNotifCard(notifId, el) {
            if (!el) return;

            // Animasi slide out & fade out
            el.style.opacity = '0';
            el.style.transform = 'translateX(40px) scale(0.95)';

            setTimeout(() => {
                el.style.maxHeight = '0';
                el.style.paddingTop = '0';
                el.style.paddingBottom = '0';
                el.style.marginTop = '0';
                el.style.marginBottom = '0';
                el.style.border = 'none';
                el.style.overflow = 'hidden';
            }, 180);

            setTimeout(() => {
                el.remove();

                // Cek jika seluruh kartu notifikasi sudah habis
                const container = document.getElementById('notifListContainer');
                if (container && container.querySelectorAll('.notif-item-card').length === 0) {
                    const emptyState = document.getElementById('notifEmptyState');
                    if (emptyState) emptyState.classList.remove('d-none');
                    const btnMark = document.getElementById('btnMarkAllRead');
                    if (btnMark) btnMark.remove();
                }

                // Update indikator titik notifikasi belum dibaca di header
                const headerDot = document.querySelector('.top-navbar-header .notif-dot-red');
                if (headerDot) headerDot.remove();
            }, 380);

            // Kirim request AJAX untuk update is_dismissed = 1 di DB (permanent)
            fetch('portal-ortu.php?action=dismiss_notif&id=' + notifId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).catch(err => console.log(err));
        }

        let activeNotifEl = null;
        let activeNotifId = null;

        function showNotifDetail(notifId, title, message, dateStr, iconClass, isUnread, cardEl) {
            activeNotifId = notifId;
            activeNotifEl = cardEl;

            document.getElementById('detailNotifSub').textContent = title;
            document.getElementById('detailNotifTime').textContent = dateStr;
            document.getElementById('detailNotifMessage').innerHTML = message.replace(/\n/g, '<br>');

            // Set Icon
            const iconEl = document.getElementById('detailNotifIcon');
            iconEl.className = 'bi ' + iconClass + ' fs-4';

            // Configure Mark Read Button
            const btnMark = document.getElementById('btnMarkReadFromModal');
            if (isUnread) {
                btnMark.style.display = 'inline-block';
                btnMark.onclick = function () {
                    dismissNotifCard(activeNotifId, activeNotifEl);
                    bootstrap.Modal.getInstance(document.getElementById('modalDetailNotif')).hide();
                };
            } else {
                btnMark.style.display = 'none';
            }

            const modal = new bootstrap.Modal(document.getElementById('modalDetailNotif'));
            modal.show();
        }

        // DISMISS PEMBAYARAN DITOLAK (PERMANENT - is_dismissed = 1)
        function dismissDitolakCard(ditolakId) {
            const el = document.getElementById('ditolak-item-' + ditolakId);
            if (!el) return;

            // Smooth slide-out animation
            el.style.opacity = '0';
            el.style.transform = 'translateX(40px) scale(0.95)';

            setTimeout(() => {
                el.style.maxHeight = '0';
                el.style.paddingTop = '0';
                el.style.paddingBottom = '0';
                el.style.marginTop = '0';
                el.style.marginBottom = '0';
                el.style.border = 'none';
                el.style.overflow = 'hidden';
            }, 200);

            setTimeout(() => {
                el.remove();

                // Check if the entire pending/ditolak section is now empty
                const container = el.closest('.tagihan-list-container');
                if (container && container.querySelectorAll('.card-tagihan-item').length === 0) {
                    // Remove the section title and container
                    const sectionTitle = container.previousElementSibling;
                    if (sectionTitle) sectionTitle.remove();
                    container.remove();
                }
            }, 400);

            // Send AJAX request to permanently dismiss in DB
            fetch('portal-ortu.php?action=dismiss_ditolak&id=' + ditolakId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).catch(err => console.log(err));
        }

        // ACCORDION DROPDOWN MAPEL & FILTER
        function toggleMapelAccordion(idx) {
            const item = document.getElementById('mapel-item-' + idx);
            if (!item) return;
            if (item.classList.contains('open')) {
                item.classList.remove('open');
            } else {
                item.classList.add('open');
            }
        }

        function filterMapelDropdown(val) {
            const items = document.querySelectorAll('.mapel-accordion-item');
            if (val === 'all') {
                items.forEach(el => {
                    el.style.display = 'block';
                });
            } else {
                items.forEach((el, idx) => {
                    if (idx == val) {
                        el.style.display = 'block';
                        el.classList.add('open');
                    } else {
                        el.style.display = 'none';
                    }
                });
            }
        }

        // TAHFIDZ CATEGORY FILTER FUNCTION
        function filterTahfidzCat(cat, btn) {
            if (btn) {
                document.querySelectorAll('#tahfidzCategoryPills .tahfidz-filter-pill').forEach(el => el.classList.remove('active'));
                btn.classList.add('active');
            }

            const items = document.querySelectorAll('.tahfidz-setoran-item');
            let visibleCount = 0;

            items.forEach(el => {
                const itemCat = el.getAttribute('data-category');
                if (cat === 'all' || itemCat === cat) {
                    el.style.display = 'block';
                    visibleCount++;
                } else {
                    el.style.display = 'none';
                }
            });

            const emptyFilterState = document.getElementById('tahfidzEmptyFilterState');
            if (emptyFilterState) {
                if (visibleCount === 0) {
                    emptyFilterState.classList.remove('d-none');
                    const catNameMap = {
                        'ziyadah': 'Ziyadah (Nambah Hafalan)',
                        'murojaah': "Muroja'ah (Ulang Hafalan)",
                        'tahsin': 'Tahsin (Perbaikan Bacaan)',
                        'ujian': 'Ujian Tahfidz'
                    };
                    const nameLabel = document.getElementById('tahfidzEmptyCatName');
                    if (nameLabel) nameLabel.innerText = catNameMap[cat] || cat;
                } else {
                    emptyFilterState.classList.add('d-none');
                }
            }
        }
    </script>

    <!-- MODAL DETAIL NOTIFIKASI -->
    <div class="modal fade" id="modalDetailNotif" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-lg" style="background: #ffffff; color: #1f2937;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark" id="detailNotifTitle"><i
                            class="bi bi-bell-fill text-primary"></i> Detail Notifikasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div id="detailNotifIconBox"
                            class="rounded-3 d-flex align-items-center justify-content-center text-white"
                            style="width: 48px; height: 48px; background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
                            <i id="detailNotifIcon" class="bi bi-bell-fill fs-4"></i>
                        </div>
                        <div>
                            <h6 id="detailNotifSub" class="fw-bold mb-0 text-dark" style="font-size: 1rem;">Judul</h6>
                            <small id="detailNotifTime" class="text-muted">Tanggal</small>
                        </div>
                    </div>
                    <p id="detailNotifMessage" class="text-secondary mb-0"
                        style="line-height: 1.6; font-size: 0.9rem; white-space: pre-wrap;">Isi pesan notifikasi...</p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-3 py-2 btn-sm fw-bold border"
                        data-bs-dismiss="modal">Tutup</button>
                    <button type="button" id="btnMarkReadFromModal"
                        class="btn btn-primary rounded-3 px-3 py-2 btn-sm fw-bold shadow-sm"><i
                            class="bi bi-check2-circle"></i> Tandai Telah Dibaca</button>
                </div>
            </div>
        </div>
    </div>
</body>

</html>