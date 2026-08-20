<?php
/**
 * API HANDLER - Manajemen Poin Siswa (Pelanggaran & Penghargaan)
 * Endpoint untuk CRUD poin dengan AJAX
 */

// API ini juga dipakai Portal Orang Tua. Jangan gunakan auth.php di sini,
// karena middleware tersebut mengalihkan akun orang tua kembali ke portal dan
// membuat fetch() menerima HTML alih-alih JSON.
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/auth_functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sesi Anda telah berakhir. Silakan masuk kembali.']);
    exit;
}

$allowedRoles = ['admin', 'bendahara', 'guru', 'operator', 'ortu'];
if (!in_array($_SESSION['role'], $allowedRoles, true) || (isset($_SESSION['is_active']) && (int) $_SESSION['is_active'] === 0)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Akses tidak diizinkan.']);
    exit;
}

$pdo = getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Authorization helpers: staff (has 'halaqah' permission) can manage; parents can read their own siswa data
$currentUserId = $_SESSION['user_id'] ?? null;
$currentRole = $_SESSION['role'] ?? null;
$canManage = function_exists('hasPermission') && hasPermission('halaqah');

try {
    // 1. GET - Ambil Total Poin Siswa
    if ($action === 'get_total_poin' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $siswaId = (int)($_GET['siswa_id'] ?? 0);
        if ($siswaId <= 0) throw new Exception("Siswa ID tidak valid");

        // Authorization: staff can view any; parents can view only their linked siswa
        $allowed = false;
        if ($canManage) $allowed = true;
        if ($currentRole === 'ortu') {
            if (isset($_SESSION['siswa_id']) && (int)$_SESSION['siswa_id'] === $siswaId) $allowed = true;
            // fallback: allow if session username equals siswa.nis
            if (!$allowed && !empty($_SESSION['username'])) {
                $stmtChk = $pdo->prepare("SELECT nis FROM siswa WHERE id = :id LIMIT 1");
                $stmtChk->execute([':id' => $siswaId]);
                $nisRow = $stmtChk->fetchColumn();
                if ($nisRow && $nisRow === $_SESSION['username']) $allowed = true;
            }
        }
        if (!$allowed) throw new Exception('Akses ditolak');

        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN tipe_poin = 'penghargaan' THEN nilai_poin ELSE 0 END), 0) as poin_penghargaan,
                COALESCE(SUM(CASE WHEN tipe_poin = 'pelanggaran' THEN nilai_poin ELSE 0 END), 0) as poin_pelanggaran,
                COALESCE(SUM(CASE WHEN tipe_poin = 'penghargaan' THEN nilai_poin ELSE -nilai_poin END), 0) as total_poin,
                COUNT(CASE WHEN tipe_poin = 'penghargaan' THEN 1 END) as count_penghargaan,
                COUNT(CASE WHEN tipe_poin = 'pelanggaran' THEN 1 END) as count_pelanggaran
            FROM siswa_poin_riwayat
            WHERE siswa_id = :sid
        ");
        $stmt->execute([':sid' => $siswaId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $result]);
        exit;
    }

    // 2. GET - Ambil Riwayat Poin Siswa
    if ($action === 'get_riwayat_poin' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $siswaId = (int)($_GET['siswa_id'] ?? 0);
        $limit = (int)($_GET['limit'] ?? 20);
        if ($siswaId <= 0) throw new Exception("Siswa ID tidak valid");

        // Authorization
        $allowed = false;
        if ($canManage) $allowed = true;
        if ($currentRole === 'ortu') {
            if (isset($_SESSION['siswa_id']) && (int)$_SESSION['siswa_id'] === $siswaId) $allowed = true;
            if (!$allowed && !empty($_SESSION['username'])) {
                $stmtChk = $pdo->prepare("SELECT nis FROM siswa WHERE id = :id LIMIT 1");
                $stmtChk->execute([':id' => $siswaId]);
                $nisRow = $stmtChk->fetchColumn();
                if ($nisRow && $nisRow === $_SESSION['username']) $allowed = true;
            }
        }
        if (!$allowed) throw new Exception('Akses ditolak');

        $stmt = $pdo->prepare("
            SELECT 
                spr.id, spr.nilai_poin, spr.tipe_poin, spr.deskripsi,
                spc.nama_kategori, spc.icon, spc.color,
                COALESCE(u.nama_lengkap, 'Sistem') as input_by_name,
                spr.tanggal, spr.jam
            FROM siswa_poin_riwayat spr
            JOIN siswa_poin_kategori spc ON spr.kategori_poin_id = spc.id
            LEFT JOIN users u ON spr.input_by = u.id
            WHERE spr.siswa_id = :sid
            ORDER BY spr.tanggal DESC, spr.jam DESC, spr.id DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':sid', $siswaId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $result, 'count' => count($result)]);
        exit;
    }

    // 3. GET - Daftar Kategori Poin
    if ($action === 'get_kategori' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!$canManage) throw new Exception('Akses ditolak');

        $tipe = $_GET['tipe'] ?? ''; // 'pelanggaran' atau 'penghargaan'
        
        $query = "SELECT id, nama_kategori, tipe_poin, nilai_poin, icon, color, deskripsi FROM siswa_poin_kategori WHERE status = 'aktif'";
        if ($tipe) {
            $query .= " AND tipe_poin = '" . ($tipe === 'pelanggaran' ? 'pelanggaran' : 'penghargaan') . "'";
        }
        $query .= " ORDER BY tipe_poin ASC, nilai_poin DESC, nama_kategori ASC";
        
        $result = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $result]);
        exit;
    }

    // 4. POST - Tambah Poin Siswa
    if ($action === 'tambah_poin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$canManage) throw new Exception('Akses ditolak');

        $siswaId = (int)($_POST['siswa_id'] ?? 0);
        $kategoriId = (int)($_POST['kategori_id'] ?? 0);
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $tanggal = !empty($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d');
        $jam = !empty($_POST['jam']) ? $_POST['jam'] : date('H:i:s');

        if ($siswaId <= 0) throw new Exception("Silakan pilih siswa terlebih dahulu");
        if ($kategoriId <= 0) throw new Exception("Silakan pilih kategori poin");

        // Ambil detail kategori
        $stmt = $pdo->prepare("SELECT nama_kategori, nilai_poin, tipe_poin FROM siswa_poin_kategori WHERE id = :id");
        $stmt->execute([':id' => $kategoriId]);
        $kategori = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$kategori) throw new Exception("Kategori poin tidak ditemukan");

        // Insert poin
        $stmt = $pdo->prepare("
            INSERT INTO siswa_poin_riwayat 
            (siswa_id, kategori_poin_id, nilai_poin, tipe_poin, deskripsi, input_by, tanggal, jam)
            VALUES (:sid, :kid, :nilai, :tipe, :desk, :ib, :tgl, :jam)
        ");
        $siswaId = (int)($_POST['siswa_id'] ?? 0);
        $kategoriId = (int)($_POST['kategori_id'] ?? 0);
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $tanggal = !empty($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d');
        $jam = !empty($_POST['jam']) ? $_POST['jam'] : date('H:i:s');

        if ($siswaId <= 0) throw new Exception("Silakan pilih siswa terlebih dahulu");
        if ($kategoriId <= 0) throw new Exception("Silakan pilih kategori poin");

        // Ambil detail kategori
        $stmt = $pdo->prepare("SELECT nama_kategori, nilai_poin, tipe_poin FROM siswa_poin_kategori WHERE id = :id");
        $stmt->execute([':id' => $kategoriId]);
        $kategori = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$kategori) throw new Exception("Kategori poin tidak ditemukan");

        // Insert poin
        $stmt = $pdo->prepare("
            INSERT INTO siswa_poin_riwayat 
            (siswa_id, kategori_poin_id, nilai_poin, tipe_poin, deskripsi, input_by, tanggal, jam)
            VALUES (:sid, :kid, :nilai, :tipe, :desk, :ib, :tgl, :jam)
        ");
        $stmt->execute([
            ':sid'   => $siswaId,
            ':kid'   => $kategoriId,
            ':nilai' => $kategori['nilai_poin'],
            ':tipe'  => $kategori['tipe_poin'],
            ':desk'  => $deskripsi,
            ':ib'    => $_SESSION['user_id'] ?? null,
            ':tgl'   => $tanggal,
            ':jam'   => $jam
        ]);
        $insertId = $pdo->lastInsertId();

        // Send notification to parent
        try {
            $stmtS = $pdo->prepare("SELECT nama FROM siswa WHERE id = :id");
            $stmtS->execute([':id' => $siswaId]);
            $namaSiswa = $stmtS->fetchColumn();

            $tipeLabel = $kategori['tipe_poin'] === 'penghargaan' ? '✨ Poin Penghargaan' : '⚠️ Poin Pelanggaran';
            $poinPrefix = $kategori['tipe_poin'] === 'penghargaan' ? '+' : '-';
            $pesanNotif = "{$tipeLabel} untuk {$namaSiswa}: {$kategori['nama_kategori']} ({$poinPrefix}{$kategori['nilai_poin']} poin)";
            if (!empty($deskripsi)) $pesanNotif .= " — {$deskripsi}";

            $stmtN = $pdo->prepare("INSERT INTO notifikasi_ortu (siswa_id, judul, pesan, tipe, icon) VALUES (:s, 'Poin Kesantrian & Disiplin', :p, 'kesantrian', 'bi-trophy-fill')");
            $stmtN->execute([':s' => $siswaId, ':p' => $pesanNotif]);
        } catch (Exception $e) {}

        echo json_encode([
            'success' => true,
            'message' => 'Poin berhasil dicatat & notifikasi dikirim ke wali murid!',
            'id' => $insertId
        ]);
        exit;
    }

    // 5. DELETE - Hapus Poin
    if ($action === 'hapus_poin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$canManage) throw new Exception('Akses ditolak');

        $poinId = (int)($_POST['id'] ?? 0);
        if ($poinId <= 0) throw new Exception("ID poin tidak valid");

        $stmt = $pdo->prepare("DELETE FROM siswa_poin_riwayat WHERE id = :id");
        $stmt->execute([':id' => $poinId]);

        echo json_encode(['success' => true, 'message' => 'Catatan poin berhasil dihapus']);
        exit;
    }

    // 6. GET - Leaderboard Poin
    if ($action === 'get_dashboard_summary' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!$canManage) throw new Exception('Akses ditolak');

        // Rentang minggu berjalan: Senin 00:00 hingga saat ini.
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $summaryStmt = $pdo->prepare("\n            SELECT\n                COALESCE(SUM(CASE WHEN tipe_poin = 'penghargaan' THEN nilai_poin ELSE 0 END), 0) AS total_penghargaan,\n                COALESCE(SUM(CASE WHEN tipe_poin = 'pelanggaran' THEN nilai_poin ELSE 0 END), 0) AS total_pelanggaran\n            FROM siswa_poin_riwayat\n            WHERE tanggal >= :week_start AND tanggal <= CURDATE()\n        ");
        $summaryStmt->execute([':week_start' => $weekStart]);
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $activeStudents = (int) $pdo->query("SELECT COUNT(*) FROM siswa WHERE status = 'aktif'")->fetchColumn();
        $topStmt = $pdo->prepare("\n            SELECT s.id, s.nama, s.nis, COALESCE(k.nama_kelas, 'Tanpa Kelas') AS nama_kelas,\n                COALESCE(SUM(CASE WHEN spr.tipe_poin = 'penghargaan' THEN spr.nilai_poin ELSE 0 END), 0) AS poin_penghargaan,\n                COALESCE(SUM(CASE WHEN spr.tipe_poin = 'pelanggaran' THEN spr.nilai_poin ELSE 0 END), 0) AS poin_pelanggaran,\n                COALESCE(SUM(CASE WHEN spr.tipe_poin = 'penghargaan' THEN spr.nilai_poin ELSE -spr.nilai_poin END), 0) AS total_poin\n            FROM siswa s\n            LEFT JOIN kelas k ON k.id = s.kelas_id\n            INNER JOIN siswa_poin_riwayat spr ON spr.siswa_id = s.id AND spr.tanggal >= :week_start AND spr.tanggal <= CURDATE()\n            WHERE s.status = 'aktif'\n            GROUP BY s.id, s.nama, s.nis, k.nama_kelas\n            HAVING total_poin > 0\n            ORDER BY total_poin DESC, poin_penghargaan DESC, s.nama ASC\n            LIMIT 5\n        ");
        $topStmt->execute([':week_start' => $weekStart]);

        echo json_encode([
            'success' => true,
            'data' => [
                'total_penghargaan' => (int) ($summary['total_penghargaan'] ?? 0),
                'total_pelanggaran' => (int) ($summary['total_pelanggaran'] ?? 0),
                'total_siswa' => $activeStudents,
                'top_performers' => $topStmt->fetchAll(PDO::FETCH_ASSOC),
                'week_start' => $weekStart,
            ],
        ]);
        exit;
    }

    // 7. GET - Leaderboard Poin
    if ($action === 'get_leaderboard' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        // Leaderboard bersifat transparan untuk wali murid, sementara aksi
        // perubahan poin tetap hanya tersedia untuk petugas berwenang.
        if (!$canManage && $currentRole !== 'ortu') throw new Exception('Akses ditolak');
        $limit   = (int)($_GET['limit'] ?? 50);
        $tipe    = $_GET['tipe'] ?? 'total';
        $bulan   = isset($_GET['bulan']) ? (int)$_GET['bulan'] : 0;
        $tahun   = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
        $kelasId = (int)($_GET['kelas_id'] ?? 0);

        $params = [];
        $monthFilter = "";
        if ($bulan > 0 && $tahun > 0) {
            $monthFilter = " AND MONTH(spr.tanggal) = :bulan AND YEAR(spr.tanggal) = :tahun";
            $params[':bulan'] = $bulan;
            $params[':tahun'] = $tahun;
        } elseif ($tahun > 0) {
            $monthFilter = " AND YEAR(spr.tanggal) = :tahun";
            $params[':tahun'] = $tahun;
        }

        $kelasFilter = "";
        if ($kelasId > 0) {
            $kelasFilter = " AND s.kelas_id = :kid";
            $params[':kid'] = $kelasId;
        }

        $orderBy = match ($tipe) {
            'penghargaan' => 'poin_penghargaan DESC, total_poin DESC, s.nama ASC',
            'pelanggaran' => 'poin_pelanggaran DESC, total_poin ASC, s.nama ASC',
            default => 'total_poin DESC, poin_penghargaan DESC, s.nama ASC',
        };

        $sql = "
            SELECT 
                s.id, s.nama, s.nis, s.foto, COALESCE(k.nama_kelas, 'Tanpa Kelas') as nama_kelas,
                COALESCE(SUM(CASE WHEN spr.tipe_poin = 'penghargaan' THEN spr.nilai_poin ELSE -spr.nilai_poin END), 0) as total_poin,
                COALESCE(SUM(CASE WHEN spr.tipe_poin = 'penghargaan' THEN spr.nilai_poin ELSE 0 END), 0) as poin_penghargaan,
                COALESCE(SUM(CASE WHEN spr.tipe_poin = 'pelanggaran' THEN spr.nilai_poin ELSE 0 END), 0) as poin_pelanggaran,
                COUNT(CASE WHEN spr.tipe_poin = 'penghargaan' THEN 1 END) as jml_penghargaan,
                COUNT(CASE WHEN spr.tipe_poin = 'pelanggaran' THEN 1 END) as jml_pelanggaran
            FROM siswa s
            LEFT JOIN kelas k ON s.kelas_id = k.id
            LEFT JOIN siswa_poin_riwayat spr ON s.id = spr.siswa_id {$monthFilter}
            WHERE s.status = 'aktif' {$kelasFilter}
            GROUP BY s.id, s.nama, s.nis, s.foto, k.nama_kelas
            HAVING poin_penghargaan > 0 OR poin_pelanggaran > 0
            ORDER BY {$orderBy}
            LIMIT {$limit}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $result, 'count' => count($result)]);
        exit;
    }

    throw new Exception("Action tidak dikenali: {$action}");

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
