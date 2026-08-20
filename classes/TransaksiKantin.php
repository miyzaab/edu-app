<?php
require_once __DIR__ . '/Database.php';

/**
 * TransaksiKantin Class
 * Membungkus seluruh logika bisnis transaksi kasir, potong saldo atomic,
 * pencatatan mutasi_saldo, stok_log, dan top-up e-wallet santri.
 */
class TransaksiKantin {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Memproses Transaksi Belanja Kasir POS (Atomic dengan PDO Transaction)
     * 
     * @param int|null $siswaId
     * @param int $kasirUserId
     * @param array $cartItems List item: [['id' => 1, 'nama' => '...', 'harga' => 10000, 'qty' => 2], ...]
     * @param string $metodeBayar 'saldo' | 'tunai'
     * @return array Array berisi info transaksi header & status
     * @throws Exception Jika validasi gagal atau stok/saldo tidak mencukupi
     */
    public function prosesTransaksi(?int $siswaId, int $kasirUserId, array $cartItems, string $metodeBayar = 'saldo'): array {
        if (empty($cartItems)) {
            throw new Exception("Keranjang belanja kasir kosong!");
        }

        // 1. Hitung total belanja
        $totalHarga = 0;
        foreach ($cartItems as $item) {
            $totalHarga += ($item['harga'] * $item['qty']);
        }

        /*
         * CATATAN KRITIS TRANSAKSI:
         * Kita menggunakan PDO Transaction (beginTransaction, commit, rollback)
         * agar operasi pemotongan saldo santri, insert header, detail, pemotongan stok produk,
         * dan pencatatan mutasi saldo berjalan secara ATOMIC (semua sukses atau rollback sepenuhnya).
         */
        try {
            $this->db->beginTransaction();

            $saldoSebelum = 0.00;
            $saldoSesudah = 0.00;

            // 2. Jika metode bayar Saldo, lakukan penguncian baris (FOR UPDATE) & validasi kecukupan saldo
            if ($metodeBayar === 'saldo') {
                if (!$siswaId) {
                    throw new Exception("Metode pembayaran Saldo memerlukan identitas santri (NIS).");
                }

                // Lock row saldo santri
                $stmtCek = $this->db->prepare("SELECT saldo FROM saldo_siswa WHERE siswa_id = :sid FOR UPDATE");
                $stmtCek->execute([':sid' => $siswaId]);
                $rowSaldo = $stmtCek->fetch();

                if (!$rowSaldo) {
                    // Auto-initialize row jika belum ada
                    $stmtInit = $this->db->prepare("INSERT INTO saldo_siswa (siswa_id, saldo, updated_at) VALUES (:sid, 0.00, NOW())");
                    $stmtInit->execute([':sid' => $siswaId]);
                    $saldoSebelum = 0.00;
                } else {
                    $saldoSebelum = (float)$rowSaldo['saldo'];
                }

                if ($saldoSebelum < $totalHarga) {
                    throw new Exception("Saldo E-Wallet santri tidak mencukupi! Sisa Saldo: Rp " . number_format($saldoSebelum, 0, ',', '.') . ", Total Belanja: Rp " . number_format($totalHarga, 0, ',', '.'));
                }

                $saldoSesudah = $saldoSebelum - $totalHarga;

                // Potong saldo santri
                $stmtDeduct = $this->db->prepare("UPDATE saldo_siswa SET saldo = :new_saldo, updated_at = NOW() WHERE siswa_id = :sid");
                $stmtDeduct->execute([':new_saldo' => $saldoSesudah, ':sid' => $siswaId]);
            }

            // 3. Generate No Transaksi Unik
            $noTransaksi = 'KTN-' . date('Ymd') . '-' . rand(10000, 99999);

            // 4. Insert Header Transaksi (Sesuaikan dengan kolom tabel real)
            $stmtTrx = $this->db->prepare("
                INSERT INTO kantin_transaksi (no_transaksi, siswa_id, kasir_user_id, total_harga, metode_bayar, created_at)
                VALUES (:no, :sid, :uid, :tot, :metode, NOW())
            ");
            $stmtTrx->execute([
                ':no'     => $noTransaksi,
                ':sid'    => $siswaId,
                ':uid'    => $kasirUserId,
                ':tot'    => $totalHarga,
                ':metode' => $metodeBayar
            ]);
            $trxId = (int)$this->db->lastInsertId();

            // 5. Insert Mutasi Saldo jika bayar Saldo
            if ($metodeBayar === 'saldo' && $siswaId) {
                $stmtMutasi = $this->db->prepare("
                    INSERT INTO mutasi_saldo (siswa_id, jenis, jumlah, saldo_sebelum, saldo_sesudah, keterangan, ref_transaksi_id, created_by)
                    VALUES (:sid, 'pembelian', :jumlah, :sebelum, :sesudah, :ket, :ref, :uid)
                ");
                $stmtMutasi->execute([
                    ':sid'     => $siswaId,
                    ':jumlah'  => $totalHarga,
                    ':sebelum' => $saldoSebelum,
                    ':sesudah' => $saldoSesudah,
                    ':ket'     => "Pembelian Kantin (" . count($cartItems) . " item) - No: " . $noTransaksi,
                    ':ref'     => $trxId,
                    ':uid'     => $kasirUserId
                ]);
            }

            // 6. Insert Detail & Potong Stok Produk
            $stmtDetail = $this->db->prepare("
                INSERT INTO kantin_transaksi_detail (transaksi_id, menu_id, jumlah, harga_satuan, subtotal)
                VALUES (:tid, :mid, :qty, :harga, :sub)
            ");

            $stmtLockStok = $this->db->prepare("SELECT stok, nama_item FROM kantin_menu WHERE id = :mid FOR UPDATE");
            $stmtStokDeduct = $this->db->prepare("UPDATE kantin_menu SET stok = GREATEST(0, stok - :qty) WHERE id = :mid");
            $stmtStokLog = $this->db->prepare("
                INSERT INTO stok_log (menu_id, jenis, jumlah, keterangan, user_id)
                VALUES (:mid, 'keluar', :qty, :ket, :uid)
            ");

            $itemSummaryNames = [];
            foreach ($cartItems as $item) {
                $mid = (int)$item['id'];
                $qty = (int)$item['qty'];
                $harga = (float)$item['harga'];
                $subtotal = $harga * $qty;

                // Lock & cek stok produk
                $stmtLockStok->execute([':mid' => $mid]);
                $prod = $stmtLockStok->fetch();
                if (!$prod) {
                    throw new Exception("Produk kantin dengan ID {$mid} tidak ditemukan.");
                }

                if ((int)$prod['stok'] < $qty) {
                    throw new Exception("Stok produk '" . $prod['nama_item'] . "' tidak mencukupi! Sisa stok: " . $prod['stok']);
                }

                // Insert detail
                $stmtDetail->execute([
                    ':tid'   => $trxId,
                    ':mid'   => $mid,
                    ':qty'   => $qty,
                    ':harga' => $harga,
                    ':sub'   => $subtotal
                ]);

                // Potong stok
                $stmtStokDeduct->execute([':qty' => $qty, ':mid' => $mid]);

                // Record stok log
                $stmtStokLog->execute([
                    ':mid' => $mid,
                    ':qty' => $qty,
                    ':ket' => "Penjualan Kasir POS No: " . $noTransaksi,
                    ':uid' => $kasirUserId
                ]);

                $itemSummaryNames[] = $item['nama'] . ' (' . $qty . 'x)';
            }

            // 7. Notifikasi ke Ortu jika transaksi santri
            if ($siswaId) {
                try {
                    $stmtNotif = $this->db->prepare("INSERT INTO notifikasi_ortu (siswa_id, judul, pesan, tipe, icon) VALUES (:s, :j, :p, 'pembayaran', 'bi-cart-check-fill')");
                    $stmtNotif->execute([
                        ':s' => $siswaId,
                        ':j' => 'Transaksi Kantin Sekolah',
                        ':p' => 'Anak Anda telah berbelanja di Kantin sebesar Rp ' . number_format($totalHarga, 0, ',', '.') . ' (' . implode(', ', $itemSummaryNames) . '). Sisa Saldo: Rp ' . number_format($saldoSesudah, 0, ',', '.')
                    ]);
                } catch (Exception $eNotif) {}
            }

            $this->db->commit();

            return [
                'success'      => true,
                'transaksi_id' => $trxId,
                'no_transaksi' => $noTransaksi,
                'total_harga'  => $totalHarga,
                'saldo_sisa'   => $saldoSesudah,
                'message'      => 'Transaksi kasir berhasil diproses! No: ' . $noTransaksi
            ];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Memproses Top-Up Saldo Santri (Atomic Transaction)
     */
    public function topupSaldo(int $siswaId, float $nominal, int $userId, string $keterangan = 'Top-Up Saldo Manual Kasir/Bendahara'): float {
        if ($nominal <= 0) {
            throw new Exception("Nominal top-up harus lebih dari 0!");
        }

        try {
            $this->db->beginTransaction();

            // Lock current balance
            $stmtCek = $this->db->prepare("SELECT saldo FROM saldo_siswa WHERE siswa_id = :sid FOR UPDATE");
            $stmtCek->execute([':sid' => $siswaId]);
            $row = $stmtCek->fetch();

            $saldoSebelum = $row ? (float)$row['saldo'] : 0.00;
            $saldoSesudah = $saldoSebelum + $nominal;

            // Upsert saldo_siswa
            $stmtUp = $this->db->prepare("
                INSERT INTO saldo_siswa (siswa_id, saldo) VALUES (:sid, :n)
                ON DUPLICATE KEY UPDATE saldo = saldo + :n2
            ");
            $stmtUp->execute([':sid' => $siswaId, ':n' => $nominal, ':n2' => $nominal]);

            // Record mutasi_saldo
            $stmtMutasi = $this->db->prepare("
                INSERT INTO mutasi_saldo (siswa_id, jenis, jumlah, saldo_sebelum, saldo_sesudah, keterangan, created_by)
                VALUES (:sid, 'topup', :nom, :seb, :ses, :ket, :uid)
            ");
            $stmtMutasi->execute([
                ':sid' => $siswaId,
                ':nom' => $nominal,
                ':seb' => $saldoSebelum,
                ':ses' => $saldoSesudah,
                ':ket' => $keterangan,
                ':uid' => $userId
            ]);

            // Record kantin_topup
            $stmtTopup = $this->db->prepare("
                INSERT INTO kantin_topup (siswa_id, nominal, metode_bayar, user_id)
                VALUES (:sid, :nom, 'cash', :uid)
            ");
            $stmtTopup->execute([':sid' => $siswaId, ':nom' => $nominal, ':uid' => $userId]);

            // Notifikasi ortu
            try {
                $stmtNotif = $this->db->prepare("INSERT INTO notifikasi_ortu (siswa_id, judul, pesan, tipe, icon) VALUES (:s, :j, :p, 'pembayaran', 'bi-wallet2')");
                $stmtNotif->execute([
                    ':s' => $siswaId,
                    ':j' => 'Top-Up Saldo E-Wallet',
                    ':p' => 'Saldo E-Wallet Kantin anak Anda berhasil di-topup sebesar Rp ' . number_format($nominal, 0, ',', '.') . '. Total Saldo Sekarang: Rp ' . number_format($saldoSesudah, 0, ',', '.')
                ]);
            } catch (Exception $eNotif) {}

            $this->db->commit();
            return $saldoSesudah;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Memperbarui stok produk secara manual + stok_log
     */
    public function updateStokManual(int $menuId, string $jenis, int $jumlah, int $userId, string $keterangan = ''): int {
        try {
            $this->db->beginTransaction();

            $stmtLock = $this->db->prepare("SELECT stok, nama_item FROM kantin_menu WHERE id = :mid FOR UPDATE");
            $stmtLock->execute([':mid' => $menuId]);
            $prod = $stmtLock->fetch();
            if (!$prod) throw new Exception("Produk tidak ditemukan.");

            $stokLama = (int)$prod['stok'];
            if ($jenis === 'masuk') {
                $stokBaru = $stokLama + $jumlah;
            } elseif ($jenis === 'keluar') {
                $stokBaru = max(0, $stokLama - $jumlah);
            } else { // koreksi
                $stokBaru = max(0, $jumlah);
            }

            $stmtUpdate = $this->db->prepare("UPDATE kantin_menu SET stok = :stok, status = IF(:stok2 > 0, 'tersedia', 'habis') WHERE id = :mid");
            $stmtUpdate->execute([':stok' => $stokBaru, ':stok2' => $stokBaru, ':mid' => $menuId]);

            $stmtLog = $this->db->prepare("
                INSERT INTO stok_log (menu_id, jenis, jumlah, keterangan, user_id)
                VALUES (:mid, :jenis, :jumlah, :ket, :uid)
            ");
            $stmtLog->execute([
                ':mid'    => $menuId,
                ':jenis'  => $jenis,
                ':jumlah' => $jumlah,
                ':ket'    => $keterangan ?: "Update stok manual ({$jenis})",
                ':uid'    => $userId
            ]);

            $this->db->commit();
            return $stokBaru;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
