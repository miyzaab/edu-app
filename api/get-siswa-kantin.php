<?php
/**
 * API Endpoint: Cari & Ambil Data Siswa + Saldo Kantin (Untuk Live Scanner Barcode POS)
 */
require_once __DIR__ . '/../config/koneksi.php';

header('Content-Type: application/json');

$nis = trim($_GET['nis'] ?? $_POST['nis'] ?? '');
$siswaId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

// Ekstrak NIS jika hasil scan QR mengandung prefix NIS:xxxxx
if (!empty($nis) && preg_match('/NIS:([^|;\s]+)/i', $nis, $m)) {
    $nis = trim($m[1]);
}

if (empty($nis) && $siswaId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'NIS atau ID Siswa tidak boleh kosong.'
    ]);
    exit;
}

try {
    $pdo = getConnection();

    if (!empty($nis)) {
        $stmt = $pdo->prepare("
            SELECT s.id, s.nis, s.nama, s.status, k.nama_kelas, COALESCE(ss.saldo, 0) AS saldo,
                   COALESCE(ss.limit_tipe, 'none') AS limit_tipe,
                   COALESCE(ss.limit_nominal, 0) AS limit_nominal,
                   COALESCE(ss.limit_aktif, 0) AS limit_aktif
            FROM siswa s
            JOIN kelas k ON s.kelas_id = k.id
            LEFT JOIN saldo_siswa ss ON s.id = ss.siswa_id
            WHERE (s.nis = :nis OR s.nama = :nis_nama) AND s.status = 'aktif'
            LIMIT 1
        ");
        $stmt->execute([':nis' => $nis, ':nis_nama' => $nis]);
    } else {
        $stmt = $pdo->prepare("
            SELECT s.id, s.nis, s.nama, s.status, k.nama_kelas, COALESCE(ss.saldo, 0) AS saldo,
                   COALESCE(ss.limit_tipe, 'none') AS limit_tipe,
                   COALESCE(ss.limit_nominal, 0) AS limit_nominal,
                   COALESCE(ss.limit_aktif, 0) AS limit_aktif
            FROM siswa s
            JOIN kelas k ON s.kelas_id = k.id
            LEFT JOIN saldo_siswa ss ON s.id = ss.siswa_id
            WHERE s.id = :sid AND s.status = 'aktif'
            LIMIT 1
        ");
        $stmt->execute([':sid' => $siswaId]);
    }

    $siswa = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($siswa) {
        $siswa['saldo'] = (float) $siswa['saldo'];
        $siswa['formatted_saldo'] = 'Rp ' . number_format($siswa['saldo'], 0, ',', '.');

        // Cek Pembatasan Uang Jajan
        $limitTipe = $siswa['limit_tipe'] ?? 'none';
        $limitNominal = (float) ($siswa['limit_nominal'] ?? 0);
        $limitAktif = (int) ($siswa['limit_aktif'] ?? 0);
        $limitSpent = 0.00;
        $isLimitReached = false;
        $limitLabel = 'Tanpa Batasan';

        if ($limitAktif && $limitNominal > 0 && $limitTipe !== 'none') {
            if ($limitTipe === 'harian') {
                $limitLabel = 'Harian (Rp ' . number_format($limitNominal, 0, ',', '.') . '/hari)';
                $stmtSpent = $pdo->prepare("SELECT COALESCE(SUM(total_harga), 0) FROM kantin_transaksi WHERE siswa_id = :sid AND metode_bayar = 'saldo' AND DATE(created_at) = CURDATE()");
                $stmtSpent->execute([':sid' => $siswa['id']]);
                $limitSpent = (float) $stmtSpent->fetchColumn();
            } elseif ($limitTipe === 'pekanan') {
                $limitLabel = 'Pekanan (Rp ' . number_format($limitNominal, 0, ',', '.') . '/pekan)';
                $stmtSpent = $pdo->prepare("SELECT COALESCE(SUM(total_harga), 0) FROM kantin_transaksi WHERE siswa_id = :sid AND metode_bayar = 'saldo' AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)");
                $stmtSpent->execute([':sid' => $siswa['id']]);
                $limitSpent = (float) $stmtSpent->fetchColumn();
            } elseif ($limitTipe === 'bulanan') {
                $limitLabel = 'Bulanan (Rp ' . number_format($limitNominal, 0, ',', '.') . '/bulan)';
                $stmtSpent = $pdo->prepare("SELECT COALESCE(SUM(total_harga), 0) FROM kantin_transaksi WHERE siswa_id = :sid AND metode_bayar = 'saldo' AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
                $stmtSpent->execute([':sid' => $siswa['id']]);
                $limitSpent = (float) $stmtSpent->fetchColumn();
            }
            $isLimitReached = ($limitSpent >= $limitNominal);
        }

        $siswa['limit_aktif'] = $limitAktif;
        $siswa['limit_tipe'] = $limitTipe;
        $siswa['limit_label'] = $limitLabel;
        $siswa['limit_nominal'] = $limitNominal;
        $siswa['limit_spent'] = $limitSpent;
        $siswa['limit_remaining'] = max(0, $limitNominal - $limitSpent);
        $siswa['is_limit_reached'] = $isLimitReached;
        $siswa['limit_warning'] = $isLimitReached ? "Maaf uang jajan sudah sampai maksimal pembatasan yang dibatasi oleh orang tua." : "";

        echo json_encode([
            'success' => true,
            'siswa' => $siswa
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Siswa dengan NIS / Barcode [' . htmlspecialchars($nis) . '] tidak ditemukan atau status tidak aktif.'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error Server: ' . $e->getMessage()
    ]);
}
