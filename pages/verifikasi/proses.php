<?php
/**
 * PROSES VERIFIKASI PEMBAYARAN
 */
require_once __DIR__ . '/../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$pdo = getConnection();
$id     = (int)$_POST['id'];
$action = $_POST['action'] ?? '';
$userId = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    // Ambil data pending
    $stmt = $pdo->prepare("SELECT * FROM pembayaran_pending WHERE id = :id AND status = 'pending' FOR UPDATE");
    $stmt->execute([':id' => $id]);
    $pending = $stmt->fetch();

    if (!$pending) {
        throw new Exception("Data pembayaran tidak ditemukan atau sudah diproses.");
    }

    if ($action === 'approve') {
        // 1. Insert ke tabel pembayaran utama
        if ($pending['jenis'] === 'spp') {
            $stmtInsert = $pdo->prepare("INSERT INTO pembayaran_spp (siswa_id, user_id, bulan, tahun, nominal, metode_bayar, tanggal_bayar, keterangan, bukti_transfer) VALUES (:s, :u, :b, :t, :n, 'transfer', CURDATE(), 'Via Portal Ortu', :bukti)");
            $stmtInsert->execute([
                ':s' => $pending['siswa_id'],
                ':u' => $userId,
                ':b' => $pending['bulan'],
                ':t' => $pending['tahun'],
                ':n' => $pending['nominal'],
                ':bukti' => $pending['bukti_transfer']
            ]);
        } elseif ($pending['jenis'] === 'uang_pangkal') {
            $stmtInsert = $pdo->prepare("INSERT INTO pembayaran_uang_pangkal (siswa_id, user_id, nominal, metode_bayar, tanggal_bayar, keterangan, bukti_transfer) VALUES (:s, :u, :n, 'transfer', CURDATE(), 'Via Portal Ortu', :bukti)");
            $stmtInsert->execute([
                ':s' => $pending['siswa_id'],
                ':u' => $userId,
                ':n' => $pending['nominal'],
                ':bukti' => $pending['bukti_transfer']
            ]);
        } elseif ($pending['jenis'] === 'lainnya') {
            $stmtInsert = $pdo->prepare("INSERT INTO pembayaran_lain (siswa_id, jenis_pembayaran_id, user_id, nominal, metode_bayar, tanggal_bayar, keterangan, bukti_transfer) VALUES (:s, :j, :u, :n, 'transfer', CURDATE(), 'Via Portal Ortu', :bukti)");
            $stmtInsert->execute([
                ':s' => $pending['siswa_id'],
                ':j' => $pending['jenis_pembayaran_id'],
                ':u' => $userId,
                ':n' => $pending['nominal'],
                ':bukti' => $pending['bukti_transfer']
            ]);
        } elseif ($pending['jenis'] === 'topup_kantin') {
            // Record log topup
            $stmtIns = $pdo->prepare("INSERT INTO kantin_topup (siswa_id, nominal, metode_bayar, user_id) VALUES (:s, :n, 'transfer_portal', :u)");
            $stmtIns->execute([':s' => $pending['siswa_id'], ':n' => $pending['nominal'], ':u' => $userId]);
            
            // Update Saldo Siswa
            $stmtSaldo = $pdo->prepare("INSERT INTO saldo_siswa (siswa_id, saldo) VALUES (:s, :n) ON DUPLICATE KEY UPDATE saldo = saldo + :n2");
            $stmtSaldo->execute([':s' => $pending['siswa_id'], ':n' => $pending['nominal'], ':n2' => $pending['nominal']]);
            
            // Kirim Notifikasi Ortu
            $stmtNotif = $pdo->prepare("INSERT INTO notifikasi_ortu (siswa_id, judul, pesan, tipe, icon) VALUES (:s, 'Top-Up Saldo Berhasil', :p, 'kantin', 'bi-wallet2')");
            $stmtNotif->execute([
                ':s' => $pending['siswa_id'],
                ':p' => 'Alhamdulillah, permohonan Top-Up Saldo E-Kantin sebesar Rp ' . number_format($pending['nominal'], 0, ',', '.') . ' telah disetujui dan saldo sudah otomatis bertambah!'
            ]);
        }

        // 2. Update status pending
        $stmtUpdate = $pdo->prepare("UPDATE pembayaran_pending SET status = 'disetujui', verified_by = :v, verified_at = NOW() WHERE id = :id");
        $stmtUpdate->execute([':v' => $userId, ':id' => $id]);

        $pdo->commit();
        redirect('index.php', 'success', 'Pembayaran berhasil disetujui dan masuk ke Laporan Keuangan.');

    } elseif ($action === 'reject') {
        $alasan = trim($_POST['alasan'] ?? '');
        if (empty($alasan)) {
            throw new Exception("Alasan penolakan wajib diisi.");
        }

        $stmtUpdate = $pdo->prepare("UPDATE pembayaran_pending SET status = 'ditolak', alasan_tolak = :a, verified_by = :v, verified_at = NOW() WHERE id = :id");
        $stmtUpdate->execute([':a' => $alasan, ':v' => $userId, ':id' => $id]);

        $pdo->commit();
        redirect('index.php', 'info', 'Pembayaran ditolak.');
    } else {
        throw new Exception("Aksi tidak valid.");
    }

} catch (Exception $e) {
    $pdo->rollBack();
    redirect('index.php', 'danger', $e->getMessage());
}
