<?php
/**
 * PROSES VERIFIKASI PEMBAYARAN
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('verifikasi');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$pdo = getConnection();
$id = (int) $_POST['id'];
$action = $_POST['action'] ?? '';
$userId = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    if ($action === 'delete') {
        $stmtCekDel = $pdo->prepare("SELECT * FROM pembayaran_pending WHERE id = :id FOR UPDATE");
        $stmtCekDel->execute([':id' => $id]);
        $rowDel = $stmtCekDel->fetch();
        if (!$rowDel) {
            throw new Exception("Data verifikasi pembayaran tidak ditemukan.");
        }

        // Hapus file bukti fisik jika ada
        if (!empty($rowDel['bukti_transfer'])) {
            $filePath = __DIR__ . '/../../' . ltrim($rowDel['bukti_transfer'], '/\\');
            if (file_exists($filePath) && is_file($filePath)) {
                @unlink($filePath);
            }
        }

        $stmtDel = $pdo->prepare("DELETE FROM pembayaran_pending WHERE id = :id");
        $stmtDel->execute([':id' => $id]);

        $pdo->commit();
        $redirectStatus = $_POST['redirect_status'] ?? ($rowDel['status'] ?? 'pending');
        redirect('index.php?status=' . urlencode($redirectStatus), 'success', 'Data verifikasi pembayaran berhasil dihapus.');

    } elseif ($action === 'approve') {
        // Ambil data pending
        $stmt = $pdo->prepare("SELECT * FROM pembayaran_pending WHERE id = :id AND status = 'pending' FOR UPDATE");
        $stmt->execute([':id' => $id]);
        $pending = $stmt->fetch();

        if (!$pending) {
            throw new Exception("Data pembayaran tidak ditemukan atau sudah diproses.");
        }

        $tanggalBayar = $_POST['tanggal_bayar'] ?? date('Y-m-d');
        $metodeBayar = $_POST['metode_bayar'] ?? 'transfer';

        // 1. Insert ke tabel pembayaran utama
        if ($pending['jenis'] === 'spp') {
            $stmtInsert = $pdo->prepare("INSERT INTO pembayaran_spp (siswa_id, user_id, bulan, tahun, nominal, metode_bayar, tanggal_bayar, keterangan, bukti_transfer) VALUES (:s, :u, :b, :t, :n, :metode, :tanggal, 'Via Portal Ortu', :bukti)");
            $stmtInsert->execute([
                ':s' => $pending['siswa_id'],
                ':u' => $userId,
                ':b' => $pending['bulan'],
                ':t' => $pending['tahun'],
                ':n' => $pending['nominal'],
                ':metode' => $metodeBayar,
                ':tanggal' => $tanggalBayar,
                ':bukti' => $pending['bukti_transfer']
            ]);
        } elseif ($pending['jenis'] === 'uang_pangkal') {
            $stmtInsert = $pdo->prepare("INSERT INTO pembayaran_uang_pangkal (siswa_id, user_id, nominal, metode_bayar, tanggal_bayar, keterangan, bukti_transfer) VALUES (:s, :u, :n, :metode, :tanggal, 'Via Portal Ortu', :bukti)");
            $stmtInsert->execute([
                ':s' => $pending['siswa_id'],
                ':u' => $userId,
                ':n' => $pending['nominal'],
                ':metode' => $metodeBayar,
                ':tanggal' => $tanggalBayar,
                ':bukti' => $pending['bukti_transfer']
            ]);
        } elseif ($pending['jenis'] === 'lainnya') {
            $stmtInsert = $pdo->prepare("INSERT INTO pembayaran_lain (siswa_id, jenis_pembayaran_id, user_id, nominal, metode_bayar, tanggal_bayar, keterangan, bukti_transfer) VALUES (:s, :j, :u, :n, :metode, :tanggal, 'Via Portal Ortu', :bukti)");
            $stmtInsert->execute([
                ':s' => $pending['siswa_id'],
                ':j' => $pending['jenis_pembayaran_id'],
                ':u' => $userId,
                ':n' => $pending['nominal'],
                ':metode' => $metodeBayar,
                ':tanggal' => $tanggalBayar,
                ':bukti' => $pending['bukti_transfer']
            ]);
        } elseif ($pending['jenis'] === 'topup_kantin' || $pending['jenis'] === 'topup' || $pending['jenis'] === 'top-up' || strpos($pending['jenis'], 'topup') === 0) {
            // Cek saldo sebelum
            $stmtCek = $pdo->prepare("SELECT saldo FROM saldo_siswa WHERE siswa_id = :sid");
            $stmtCek->execute([':sid' => $pending['siswa_id']]);
            $saldoSebelum = (float) $stmtCek->fetchColumn();
            $saldoSesudah = $saldoSebelum + (float) $pending['nominal'];

            // Record log topup
            $stmtIns = $pdo->prepare("INSERT INTO kantin_topup (siswa_id, nominal, metode_bayar, user_id) VALUES (:s, :n, 'transfer_portal', :u)");
            $stmtIns->execute([':s' => $pending['siswa_id'], ':n' => $pending['nominal'], ':u' => $userId]);

            // Update Saldo Siswa
            $stmtSaldo = $pdo->prepare("INSERT INTO saldo_siswa (siswa_id, saldo) VALUES (:s, :n) ON DUPLICATE KEY UPDATE saldo = saldo + :n2");
            $stmtSaldo->execute([':s' => $pending['siswa_id'], ':n' => $pending['nominal'], ':n2' => $pending['nominal']]);

            // Catat ke Mutasi Saldo
            try {
                $stmtMutasi = $pdo->prepare("INSERT INTO mutasi_saldo (siswa_id, jenis, jumlah, saldo_sebelum, saldo_sesudah, keterangan, created_by) VALUES (:sid, 'topup', :nom, :seb, :ses, 'Top-up E-Kantin via Portal Ortu (Disetujui Admin)', :uid)");
                $stmtMutasi->execute([
                    ':sid' => $pending['siswa_id'],
                    ':nom' => $pending['nominal'],
                    ':seb' => $saldoSebelum,
                    ':ses' => $saldoSesudah,
                    ':uid' => $userId
                ]);
            } catch (Exception $exM) {
            }

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
        // Ambil data pending
        $stmt = $pdo->prepare("SELECT * FROM pembayaran_pending WHERE id = :id AND status = 'pending' FOR UPDATE");
        $stmt->execute([':id' => $id]);
        $pending = $stmt->fetch();

        if (!$pending) {
            throw new Exception("Data pembayaran tidak ditemukan atau sudah diproses.");
        }

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
