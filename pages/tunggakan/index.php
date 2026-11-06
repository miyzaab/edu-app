<?php
/**
 * REKAP TUNGGAKAN & TAGIHAN SANTRI TERPADU - Edu-App
 * Menampilkan rincian tunggakan SPP, Uang Pangkal, dan Pembayaran Lainnya seluruh santri
 * Dilengkapi integrasi WhatsApp Penagihan, Pengaturan Visibilitas Portal Ortu, dan Edit Kewajiban Siswa.
 */
$pageTitle = 'Rekap Tunggakan & Tagihan Santri';
$activePage = 'tunggakan';
$activeModule = 'transaksi';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('tunggakan');

$pdo = getConnection();

// Auto-sync database jika diperlukan
require_once __DIR__ . '/../../config/db_auto_sync.php';
runDatabaseAutoSync($pdo);

// Helper: Deteksi tingkat kelas akurat (angka maupun romawi)
if (!function_exists('parseTingkatKelas')) {
    function parseTingkatKelas($tingkat, $namaKelas)
    {
        $t = trim((string) $tingkat);
        if (!empty($t)) {
            if ($t === '7' || strcasecmp($t, 'VII') === 0)
                return '7';
            if ($t === '8' || strcasecmp($t, 'VIII') === 0)
                return '8';
            if ($t === '9' || strcasecmp($t, 'IX') === 0)
                return '9';
            if (preg_match('/(7|8|9)/', $t, $m))
                return $m[1];
        }
        $nk = strtoupper(trim((string) $namaKelas));
        // Cek Romawi & Angka
        if (preg_match('/\b(IX|9)\b/', $nk) || preg_match('/(^|\s|KELAS\s*|KLS\s*)9/i', $nk) || strpos($nk, 'IX') !== false || strpos($nk, '9') !== false) {
            return '9';
        }
        if (preg_match('/\b(VIII|8)\b/', $nk) || preg_match('/(^|\s|KELAS\s*|KLS\s*)8/i', $nk) || strpos($nk, 'VIII') !== false || strpos($nk, '8') !== false) {
            return '8';
        }
        if (preg_match('/\b(VII|7)\b/', $nk) || preg_match('/(^|\s|KELAS\s*|KLS\s*)7/i', $nk) || strpos($nk, 'VII') !== false || strpos($nk, '7') !== false) {
            return '7';
        }
        return '';
    }
}

// Handle POST: Update Visibilitas di Portal Ortu & Pengaturan Rekening
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_visibility_settings') {
    if (($_SESSION['role'] ?? '') !== 'admin' && ($_SESSION['role'] ?? '') !== 'bendahara') {
        redirect('index.php', 'danger', 'Anda tidak memiliki hak akses.');
    }

    $showSpp = isset($_POST['tunggakan_show_spp']) ? '1' : '0';
    $showUp = isset($_POST['tunggakan_show_uang_pangkal']) ? '1' : '0';
    $showLain = isset($_POST['tunggakan_show_pembayaran_lain']) ? '1' : '0';
    $defaultSppKls7 = max(1, min(12, (int) ($_POST['default_spp_mulai_kls7'] ?? 7)));
    $defaultSppKls89 = max(1, min(12, (int) ($_POST['default_spp_mulai_kls89'] ?? 1)));
    $rekeningInfo = trim($_POST['tunggakan_rekening_info'] ?? '');
    $pesanWa = trim($_POST['tunggakan_pesan_wa'] ?? '');

    updateSetting('tunggakan_show_spp', $showSpp);
    updateSetting('tunggakan_show_uang_pangkal', $showUp);
    updateSetting('tunggakan_show_pembayaran_lain', $showLain);
    updateSetting('tunggakan_default_spp_kls7', (string) $defaultSppKls7);
    updateSetting('tunggakan_default_spp_kls89', (string) $defaultSppKls89);
    updateSetting('tunggakan_rekening_info', $rekeningInfo);
    updateSetting('tunggakan_pesan_wa', $pesanWa);

    // Update status aktif & target sasaran tingkat/kelas jenis pembayaran jika dikirim
    if (isset($_POST['jenis_status']) && is_array($_POST['jenis_status'])) {
        $stmtJp = $pdo->prepare("UPDATE jenis_pembayaran SET status = :st, target_tingkat = :tt WHERE id = :id");
        foreach ($_POST['jenis_status'] as $jpId => $stVal) {
            $ttVal = trim($_POST['jenis_target_tingkat'][$jpId] ?? 'all');
            if (empty($ttVal)) {
                $ttVal = 'all';
            }
            $stmtJp->execute([
                ':st' => $stVal === 'aktif' ? 'aktif' : 'nonaktif',
                ':tt' => $ttVal,
                ':id' => (int) $jpId
            ]);
        }
    }

    // Jika admin memilih untuk menerapkan default bulan tagih ke seluruh santri
    if (!empty($_POST['apply_bulk_start_month'])) {
        try {
            $allSiswaKelas = $pdo->query("SELECT s.id, k.nama_kelas, k.tingkat FROM siswa s JOIN kelas k ON s.kelas_id = k.id")->fetchAll();
            $stmtSetBulan = $pdo->prepare("UPDATE siswa SET bulan_mulai_spp = :bms WHERE id = :id");
            foreach ($allSiswaKelas as $sk) {
                $tk = parseTingkatKelas($sk['tingkat'], $sk['nama_kelas']);
                $bmsVal = ($tk === '7') ? $defaultSppKls7 : $defaultSppKls89;
                $stmtSetBulan->execute([':bms' => $bmsVal, ':id' => $sk['id']]);
            }
        } catch (Exception $e) {
        }
    }

    redirect('index.php', 'success', 'Pengaturan visibilitas tagihan & penagihan berhasil disimpan.');
}

// Handle POST: Edit Cepat Kewajiban Santri (Nominal SPP, Bulan Mulai SPP, Target Uang Pangkal, Bebas Tagihan Lain)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_student_obligation') {
    $siswaId = (int) ($_POST['siswa_id'] ?? 0);
    $nominalSpp = (float) str_replace(['.', ','], ['', '.'], $_POST['nominal_spp'] ?? '0');
    $targetUp = (float) str_replace(['.', ','], ['', '.'], $_POST['target_uang_pangkal'] ?? '0');
    $bulanMulaiSpp = (int) ($_POST['bulan_mulai_spp'] ?? 7);

    if ($siswaId > 0) {
        try {
            $pdo->beginTransaction();
            $stmtUpd = $pdo->prepare("UPDATE siswa SET nominal_spp = :spp, target_uang_pangkal = :up, bulan_mulai_spp = :bms WHERE id = :id");
            $stmtUpd->execute([':spp' => $nominalSpp, ':up' => $targetUp, ':bms' => $bulanMulaiSpp, ':id' => $siswaId]);

            // Update pengecualian pembayaran lain (siswa_bebas_pembayaran)
            $pdo->prepare("DELETE FROM siswa_bebas_pembayaran WHERE siswa_id = :sid")->execute([':sid' => $siswaId]);
            if (isset($_POST['bebas_jp']) && is_array($_POST['bebas_jp'])) {
                $stmtInsBebas = $pdo->prepare("INSERT INTO siswa_bebas_pembayaran (siswa_id, jenis_pembayaran_id, keterangan) VALUES (:sid, :jid, 'Dikecualikan / Bukan Kewajiban')");
                foreach ($_POST['bebas_jp'] as $bebasJpId) {
                    $stmtInsBebas->execute([':sid' => $siswaId, ':jid' => (int) $bebasJpId]);
                }
            }

            $pdo->commit();
            redirect('index.php', 'success', 'Kewajiban tagihan santri berhasil diperbarui.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            redirect('index.php', 'danger', 'Gagal memperbarui: ' . $e->getMessage());
        }
    }
}

// Filter Options
$filterKelas = (int) ($_GET['kelas_id'] ?? 0);
$filterStatus = $_GET['status_tunggakan'] ?? 'menunggak'; // 'all', 'menunggak', 'lunas'
$filterKategori = $_GET['kategori'] ?? 'all'; // 'all', 'spp', 'uang_pangkal', 'lain'
$filterBulanSampai = (int) ($_GET['bulan_sampai'] ?? date('n'));
$filterTahun = (int) ($_GET['tahun'] ?? date('Y'));

// Ambil data pengaturan
$showSppSetting = getSetting('tunggakan_show_spp', '1') === '1';
$showUpSetting = getSetting('tunggakan_show_uang_pangkal', '1') === '1';
$showLainSetting = getSetting('tunggakan_show_pembayaran_lain', '1') === '1';
$defaultSppKls7Setting = (int) getSetting('tunggakan_default_spp_kls7', '7');
$defaultSppKls89Setting = (int) getSetting('tunggakan_default_spp_kls89', '1');
$rekeningInfoSetting = getSetting('tunggakan_rekening_info', 'BSI (Bank Syariah Indonesia) No. 7148123456 a.n. SMP Islamic School Minhaj Al-Ilmi');
$pesanWaSetting = getSetting('tunggakan_pesan_wa', "Assalamu'alaikum Wr. Wb.\n\nYth. Orang Tua / Wali dari *{nama_siswa}* (Kelas {nama_kelas})\nNIS: *{nis}*\n\nBerikut kami sampaikan rincian tagihan administrasi sekolah yang belum terselesaikan:\n{rincian_tunggakan}\n*Total Tagihan: {total_tunggakan}*\n\nPembayaran dapat ditransfer melalui:\n*{rekening_info}*\n\nMohon konfirmasi pembayaran dengan mengirimkan bukti transfer. Atas perhatian dan kerjasamanya kami ucapkan terima kasih.\n\nWassalamu'alaikum Wr. Wb.\n_Bendahara Keuangan {nama_sekolah}_");

// Ambil daftar kelas untuk dropdown filter
$kelasList = $pdo->query("SELECT id, nama_kelas, tingkat FROM kelas ORDER BY nama_kelas ASC")->fetchAll();

// Ambil seluruh jenis pembayaran aktif
$jenisPembayaranList = $pdo->query("SELECT * FROM jenis_pembayaran WHERE status = 'aktif' ORDER BY nama_pembayaran ASC")->fetchAll();
$allJenisPembayaran = $pdo->query("SELECT * FROM jenis_pembayaran ORDER BY nama_pembayaran ASC")->fetchAll();

// Ambil seluruh santri aktif
$siswaRows = [];
$paramsSiswa = [];
try {
    $sqlSiswa = "
        SELECT s.id, s.nis, s.nama, s.no_hp_ortu, s.foto, s.nominal_spp, s.target_uang_pangkal, s.bulan_mulai_spp,
               k.id AS kelas_id, k.nama_kelas, k.tingkat
        FROM siswa s
        JOIN kelas k ON s.kelas_id = k.id
        WHERE s.status = 'aktif'
    ";
    if ($filterKelas > 0) {
        $sqlSiswa .= " AND s.kelas_id = :kid";
        $paramsSiswa[':kid'] = $filterKelas;
    }
    $sqlSiswa .= " ORDER BY 
        CASE 
            WHEN k.tingkat = '9' OR k.tingkat = 'IX' OR k.nama_kelas LIKE '%IX%' OR k.nama_kelas LIKE '%9%' THEN 3
            WHEN k.tingkat = '8' OR k.tingkat = 'VIII' OR k.nama_kelas LIKE '%VIII%' OR k.nama_kelas LIKE '%8%' THEN 2
            WHEN k.tingkat = '7' OR k.tingkat = 'VII' OR k.nama_kelas LIKE '%VII%' OR k.nama_kelas LIKE '%7%' THEN 1
            ELSE 0
        END DESC,
        k.nama_kelas ASC, 
        s.nama ASC";
    $stmtSiswa = $pdo->prepare($sqlSiswa);
    $stmtSiswa->execute($paramsSiswa);
    $siswaRows = $stmtSiswa->fetchAll();
} catch (Exception $e) {
    $sqlSiswa = "
        SELECT s.id, s.nis, s.nama, k.id AS kelas_id, k.nama_kelas, k.tingkat
        FROM siswa s
        JOIN kelas k ON s.kelas_id = k.id
        WHERE s.status = 'aktif'
    ";
    if ($filterKelas > 0) {
        $sqlSiswa .= " AND s.kelas_id = :kid";
        $paramsSiswa = [':kid' => $filterKelas];
    } else {
        $paramsSiswa = [];
    }
    $sqlSiswa .= " ORDER BY 
        CASE 
            WHEN k.tingkat = '9' OR k.tingkat = 'IX' OR k.nama_kelas LIKE '%IX%' OR k.nama_kelas LIKE '%9%' THEN 3
            WHEN k.tingkat = '8' OR k.tingkat = 'VIII' OR k.nama_kelas LIKE '%VIII%' OR k.nama_kelas LIKE '%8%' THEN 2
            WHEN k.tingkat = '7' OR k.tingkat = 'VII' OR k.nama_kelas LIKE '%VII%' OR k.nama_kelas LIKE '%7%' THEN 1
            ELSE 0
        END DESC,
        k.nama_kelas ASC, 
        s.nama ASC";
    $stmtSiswa = $pdo->prepare($sqlSiswa);
    $stmtSiswa->execute($paramsSiswa);
    $siswaRows = $stmtSiswa->fetchAll();
}

// Preload seluruh data pembayaran untuk performa super cepat (O(1) Map Lookup)
$sppPaidMap = []; // [siswa_id][tahun][bulan] => true
$stmtAllSpp = $pdo->prepare("SELECT siswa_id, bulan, tahun FROM pembayaran_spp WHERE tahun = :thn");
$stmtAllSpp->execute([':thn' => $filterTahun]);
while ($r = $stmtAllSpp->fetch()) {
    $sppPaidMap[$r['siswa_id']][$r['tahun']][$r['bulan']] = true;
}

$upPaidMap = []; // [siswa_id] => total_bayar
$stmtAllUp = $pdo->query("SELECT siswa_id, SUM(nominal) as total FROM pembayaran_uang_pangkal GROUP BY siswa_id");
while ($r = $stmtAllUp->fetch()) {
    $upPaidMap[$r['siswa_id']] = (float) $r['total'];
}

$lainPaidMap = []; // [siswa_id][jenis_id] => total_bayar
$stmtAllLain = $pdo->query("SELECT siswa_id, jenis_pembayaran_id, SUM(nominal) as total FROM pembayaran_lain GROUP BY siswa_id, jenis_pembayaran_id");
while ($r = $stmtAllLain->fetch()) {
    $lainPaidMap[$r['siswa_id']][$r['jenis_pembayaran_id']] = (float) $r['total'];
}

// Preload daftar pembayaran yang dibebaskan per siswa
$bebasPaidMap = []; // [siswa_id][jenis_pembayaran_id] => true
try {
    $stmtAllBebas = $pdo->query("SELECT siswa_id, jenis_pembayaran_id FROM siswa_bebas_pembayaran");
    while ($r = $stmtAllBebas->fetch()) {
        $bebasPaidMap[$r['siswa_id']][$r['jenis_pembayaran_id']] = true;
    }
} catch (Exception $e) {
}

// Rekapitulasi Tunggakan per Santri
$dataTunggakan = [];
$grandTotalTunggakan = 0;
$grandTotalSpp = 0;
$grandTotalUp = 0;
$grandTotalLain = 0;
$totalSantriMenunggak = 0;

$bulanSampai = max(1, min(12, $filterBulanSampai));
$bulanIndoList = [
    1 => 'Jan',
    2 => 'Feb',
    3 => 'Mar',
    4 => 'Apr',
    5 => 'Mei',
    6 => 'Jun',
    7 => 'Jul',
    8 => 'Agu',
    9 => 'Sep',
    10 => 'Okt',
    11 => 'Nov',
    12 => 'Des'
];

foreach ($siswaRows as $s) {
    $sid = (int) $s['id'];
    $nominalSppPerBulan = (float) ($s['nominal_spp'] ?? 150000);
    if ($nominalSppPerBulan <= 0) {
        $nominalSppPerBulan = 150000;
    }

    // Ekstrak tingkat kelas (7, 8, atau 9) dengan parser akurat
    $siswaTingkat = parseTingkatKelas($s['tingkat'], $s['nama_kelas']);

    // 1. Hitung Tunggakan SPP berdasarkan Bulan Mulai Tagih
    $bulanMulai = (int) ($s['bulan_mulai_spp'] ?? 0);
    if ($bulanMulai <= 0) {
        // Santri kelas 7 mulai Juli ($defaultSppKls7Setting), kelas 8 & 9 mulai Jan ($defaultSppKls89Setting)
        if ($siswaTingkat === '7') {
            $bulanMulai = $defaultSppKls7Setting;
        } else {
            $bulanMulai = $defaultSppKls89Setting;
        }
    }

    $unpaidSppMonths = [];
    for ($m = $bulanMulai; $m <= $bulanSampai; $m++) {
        if (!isset($sppPaidMap[$sid][$filterTahun][$m])) {
            $unpaidSppMonths[] = $m;
        }
    }
    $countUnpaidSpp = count($unpaidSppMonths);
    $nominalTunggakanSpp = $showSppSetting ? ($countUnpaidSpp * $nominalSppPerBulan) : 0;
    $totalBulanSppDitagih = max(0, $bulanSampai - $bulanMulai + 1);
    $targetSppObligation = $showSppSetting ? ($totalBulanSppDitagih * $nominalSppPerBulan) : 0;
    $paidSppObligation = $showSppSetting ? max(0, $targetSppObligation - $nominalTunggakanSpp) : 0;

    // 2. Hitung Tunggakan Uang Pangkal
    $targetUp = (float) ($s['target_uang_pangkal'] ?? 0);
    $totalUpPaid = (float) ($upPaidMap[$sid] ?? 0);
    $nominalTunggakanUp = $showUpSetting ? max(0, $targetUp - $totalUpPaid) : 0;
    $targetUpObligation = $showUpSetting ? $targetUp : 0;
    $paidUpObligation = $showUpSetting ? min($targetUp, $totalUpPaid) : 0;

    // 3. Hitung Tunggakan Pembayaran Lain (Daftar Ulang, dll)
    $unpaidLainItems = [];
    $nominalTunggakanLain = 0;
    $targetLainObligation = 0;
    $paidLainObligation = 0;

    if ($showLainSetting) {
        foreach ($jenisPembayaranList as $jp) {
            $jpId = (int) $jp['id'];

            // Cek jika siswa dibebaskan secara spesifik
            if (!empty($bebasPaidMap[$sid][$jpId])) {
                continue;
            }

            // Cek target tingkat kelas jenis pembayaran
            $targetTingkat = $jp['target_tingkat'] ?? 'all';
            if ($targetTingkat !== 'all' && !empty($targetTingkat)) {
                $allowedTingkat = array_map('trim', explode(',', $targetTingkat));
                if (!empty($siswaTingkat) && !in_array($siswaTingkat, $allowedTingkat)) {
                    // Tidak wajib untuk tingkat kelas siswa ini (contoh: Daftar Ulang kelas 8 & 9 tidak ditagih ke kelas 7)
                    continue;
                }
            }

            $jpTarget = (float) $jp['nominal_default'];
            $jpPaid = (float) ($lainPaidMap[$sid][$jpId] ?? 0);
            $jpSisa = max(0, $jpTarget - $jpPaid);
            $targetLainObligation += $jpTarget;
            $paidLainObligation += min($jpTarget, $jpPaid);

            if ($jpSisa > 0) {
                $unpaidLainItems[] = [
                    'id' => $jpId,
                    'nama' => $jp['nama_pembayaran'],
                    'target' => $jpTarget,
                    'dibayar' => $jpPaid,
                    'sisa' => $jpSisa
                ];
                $nominalTunggakanLain += $jpSisa;
            }
        }
    }

    $totalTunggakanSantri = $nominalTunggakanSpp + $nominalTunggakanUp + $nominalTunggakanLain;
    $totalKewajibanSantri = $targetSppObligation + $targetUpObligation + $targetLainObligation;
    $totalTerbayarSantri = $paidSppObligation + $paidUpObligation + $paidLainObligation;

    if ($totalTunggakanSantri <= 0) {
        $persenLunas = 100;
    } elseif ($totalKewajibanSantri > 0) {
        $persenLunas = min(99, max(0, (int) round(($totalTerbayarSantri / $totalKewajibanSantri) * 100)));
    } else {
        $persenLunas = 0;
    }

    // Filter Status logic
    if ($filterStatus === 'menunggak' && $totalTunggakanSantri <= 0) {
        continue;
    }
    if ($filterStatus === 'lunas' && $totalTunggakanSantri > 0) {
        continue;
    }

    // Filter Kategori logic
    if ($filterKategori === 'spp' && $nominalTunggakanSpp <= 0) {
        continue;
    }
    if ($filterKategori === 'uang_pangkal' && $nominalTunggakanUp <= 0) {
        continue;
    }
    if ($filterKategori === 'lain' && $nominalTunggakanLain <= 0) {
        continue;
    }

    if ($totalTunggakanSantri > 0) {
        $totalSantriMenunggak++;
    }

    $grandTotalSpp += $nominalTunggakanSpp;
    $grandTotalUp += $nominalTunggakanUp;
    $grandTotalLain += $nominalTunggakanLain;
    $grandTotalTunggakan += $totalTunggakanSantri;

    // Rincian Bulan SPP Text
    $bulanTextArr = [];
    foreach ($unpaidSppMonths as $bm) {
        $bulanTextArr[] = $bulanIndoList[$bm] ?? $bm;
    }
    $bulanText = !empty($bulanTextArr) ? implode(', ', $bulanTextArr) . " $filterTahun" : '-';

    // Rincian untuk pesan WhatsApp
    $rincianWaLines = [];
    if ($nominalTunggakanSpp > 0) {
        $rincianWaLines[] = "• *SPP ($countUnpaidSpp Bln: $bulanText)*: Rp " . number_format((float) $nominalTunggakanSpp, 0, ',', '.');
    }
    if ($nominalTunggakanUp > 0) {
        $rincianWaLines[] = "• *Uang Pangkal (Sisa)*: Rp " . number_format((float) $nominalTunggakanUp, 0, ',', '.') . " (Sudah masuk: Rp " . number_format((float) $totalUpPaid, 0, ',', '.') . ")";
    }
    if ($nominalTunggakanLain > 0) {
        foreach ($unpaidLainItems as $uli) {
            $rincianWaLines[] = "• *" . ($uli['nama'] ?? 'Tagihan') . "*: Rp " . number_format((float) ($uli['sisa'] ?? 0), 0, ',', '.');
        }
    }
    if (empty($rincianWaLines)) {
        $rincianWaLines[] = "• Tidak ada tunggakan pembayaran (Lunas).";
    }

    $waMessage = str_replace(
        ['{nama_siswa}', '{nama_kelas}', '{nis}', '{rincian_tunggakan}', '{total_tunggakan}', '{rekening_info}', '{nama_sekolah}'],
        [
            (string) ($s['nama'] ?? ''),
            (string) ($s['nama_kelas'] ?? ''),
            (string) ($s['nis'] ?? ''),
            implode("\n", $rincianWaLines),
            'Rp ' . number_format((float) $totalTunggakanSantri, 0, ',', '.'),
            (string) ($rekeningInfoSetting ?? ''),
            (string) getSetting('nama_sekolah', SCHOOL_NAME)
        ],
        (string) ($pesanWaSetting ?? '')
    );

    // Format nomor WhatsApp
    $cleanPhone = preg_replace('/[^0-9]/', '', (string) ($s['no_hp_ortu'] ?? ''));
    if (!empty($cleanPhone)) {
        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = '62' . substr($cleanPhone, 1);
        }
    }

    $firstUnpaidMonth = !empty($unpaidSppMonths) ? (int) $unpaidSppMonths[0] : (int) $filterBulanSampai;

    $dataTunggakan[] = [
        'id' => $sid,
        'nis' => (string) ($s['nis'] ?? ''),
        'nama' => (string) ($s['nama'] ?? ''),
        'nama_kelas' => (string) ($s['nama_kelas'] ?? ''),
        'no_hp_ortu' => (string) ($s['no_hp_ortu'] ?? ''),
        'clean_phone' => $cleanPhone,
        'nominal_spp_per_bulan' => (float) $nominalSppPerBulan,
        'bulan_mulai_spp' => (int) $bulanMulai,
        'count_unpaid_spp' => (int) $countUnpaidSpp,
        'first_unpaid_bulan' => (int) $firstUnpaidMonth,
        'bulan_spp_text' => (string) $bulanText,
        'tunggakan_spp' => (float) $nominalTunggakanSpp,
        'target_up' => (float) $targetUp,
        'paid_up' => (float) $totalUpPaid,
        'tunggakan_up' => (float) $nominalTunggakanUp,
        'unpaid_lain_items' => $unpaidLainItems,
        'tunggakan_lain' => (float) $nominalTunggakanLain,
        'total_tunggakan' => (float) $totalTunggakanSantri,
        'total_kewajiban' => (float) $totalKewajibanSantri,
        'total_terbayar' => (float) $totalTerbayarSantri,
        'persen_lunas' => (int) $persenLunas,
        'bebas_jp_ids' => array_keys($bebasPaidMap[$sid] ?? []),
        'wa_message' => (string) $waMessage,
    ];
}

// Urutkan data tunggakan: Kelas IX (9) dulu, lalu VIII (8), lalu VII (7), kemudian alfabetis nama santri
usort($dataTunggakan, function ($a, $b) {
    $tkA = (int) parseTingkatKelas('', $a['nama_kelas']);
    $tkB = (int) parseTingkatKelas('', $b['nama_kelas']);

    if ($tkA !== $tkB) {
        return $tkB <=> $tkA; // Descending: 9 > 8 > 7
    }

    $cmpKelas = strcmp($a['nama_kelas'], $b['nama_kelas']);
    if ($cmpKelas !== 0) {
        return $cmpKelas;
    }

    return strcmp(strtolower($a['nama']), strtolower($b['nama']));
});

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .tunggakan-kpi-card {
        border-radius: 16px;
        padding: 20px;
        border: 0;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .tunggakan-kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    }

    .tunggakan-kpi-card.gradient-total {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: #ffffff;
    }

    .tunggakan-kpi-card.gradient-spp {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: #ffffff;
    }

    .tunggakan-kpi-card.gradient-up {
        background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
        color: #ffffff;
    }

    .tunggakan-kpi-card.gradient-lain {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: #ffffff;
    }

    .tunggakan-kpi-card .kpi-icon-bg {
        position: absolute;
        right: -10px;
        bottom: -10px;
        font-size: 5rem;
        opacity: 0.15;
        pointer-events: none;
    }

    .table-tunggakan thead th {
        background: #f8fafc;
        color: #475569;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 12px 14px;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
    }

    .table-tunggakan tbody td {
        padding: 12px 14px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }

    .table-tunggakan tbody tr:hover {
        background-color: #f8fafc;
    }

    .badge-tagihan-item {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.75rem;
        padding: 4px 8px;
        border-radius: 6px;
        font-weight: 600;
        background: #f1f5f9;
        color: #334155;
        border: 1px solid #e2e8f0;
    }

    .badge-tagihan-item.warning {
        background: #fffbeb;
        color: #b45309;
        border-color: #fde68a;
    }

    .badge-tagihan-item.danger {
        background: #fef2f2;
        color: #b91c1c;
        border-color: #fecaca;
    }

    .badge-tagihan-item.success {
        background: #f0fdf4;
        color: #15803d;
        border-color: #bbf7d0;
    }
</style>

<!-- SUB-TAB PILL NAVIGATION (Rekap SPP, Rekap Daftar Ulang, Rekap Uang Pangkal, Rekap Tunggakan) -->
<div class="rekap-tabs-bar mb-4 overflow-auto pb-1">
    <div class="rekap-tabs-pill-container">
        <?php if (hasPermission('rekap_spp')): ?>
            <a href="<?= BASE_URL ?>/pages/riwayat/rekap-spp.php" class="rekap-tab-pill">
                <i class="bi bi-grid-3x3-gap-fill"></i> Rekap SPP Bulanan
            </a>
        <?php endif; ?>
        <?php if (hasPermission('rekap_daftar_ulang')): ?>
            <a href="<?= BASE_URL ?>/pages/riwayat/rekap-daftar-ulang.php" class="rekap-tab-pill">
                <i class="bi bi-clipboard2-check-fill"></i> Rekap Daftar Ulang
            </a>
        <?php endif; ?>
        <?php if (hasPermission('rekap_uang_pangkal')): ?>
            <a href="<?= BASE_URL ?>/pages/riwayat/rekap-uang-pangkal.php" class="rekap-tab-pill">
                <i class="bi bi-wallet2"></i> Rekap Uang Pangkal
            </a>
        <?php endif; ?>
        <?php if (hasPermission('tunggakan')): ?>
            <a href="<?= BASE_URL ?>/pages/tunggakan/index.php" class="rekap-tab-pill active">
                <i class="bi bi-exclamation-triangle-fill text-warning"></i> Rekap Tunggakan
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- HEADER SECTION & ACTION BUTTONS -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
            <span>📋 Rekap Tunggakan & Tagihan Santri</span>
            <span
                class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2.5 py-1 rounded-pill small">
                <?= $totalSantriMenunggak ?> Santri Menunggak
            </span>
        </h4>
        <p class="text-muted small mb-0">
            Pusat monitoring tagihan santri terintegrasi (SPP, Uang Pangkal, Daftar Ulang & Lainnya) yang sinkron dengan
            Portal Orang Tua.
        </p>
    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap">
        <?php if (hasPermission('spp')): ?>
            <a href="../spp/create.php" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1 rounded-3">
                <i class="bi bi-plus-lg"></i> SPP
            </a>
        <?php endif; ?>
        <?php if (hasPermission('pembayaran_lain')): ?>
            <a href="../pembayaran-lain/create.php"
                class="btn btn-outline-warning btn-sm d-flex align-items-center gap-1 rounded-3 text-dark">
                <i class="bi bi-plus-lg"></i> Pemb. Lain
            </a>
        <?php endif; ?>
        <?php if (($_SESSION['role'] ?? '') === 'admin' || ($_SESSION['role'] ?? '') === 'bendahara'): ?>
            <button type="button"
                class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1.5 rounded-3 fw-semibold"
                data-bs-toggle="modal" data-bs-target="#modalVisibilitySettings">
                <i class="bi bi-sliders"></i> Atur Visibilitas
            </button>
        <?php endif; ?>

        <button type="button" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1.5 rounded-3"
            onclick="window.print()">
            <i class="bi bi-printer"></i> Cetak Rekap
        </button>
    </div>
</div>

<!-- KPI SUMMARY CARDS -->
<div class="row g-3 mb-4">
    <!-- Grand Total Tunggakan -->
    <div class="col-12 col-md-6 col-xl-3">
        <div class="tunggakan-kpi-card gradient-total">
            <i class="bi bi-cash-stack kpi-icon-bg"></i>
            <span class="text-white text-opacity-75 small fw-semibold text-uppercase tracking-wider">Total Tagihan
                Tertunggak</span>
            <h3 class="fw-bold text-white mt-2 mb-1">Rp <?= number_format($grandTotalTunggakan, 0, ',', '.') ?></h3>
            <div class="d-flex align-items-center gap-2 text-white text-opacity-90 small">
                <span><i class="bi bi-people-fill"></i> <strong><?= $totalSantriMenunggak ?></strong> dari
                    <?= count($siswaRows) ?> santri</span>
            </div>
        </div>
    </div>

    <!-- Tunggakan SPP -->
    <div class="col-12 col-md-6 col-xl-3">
        <div class="tunggakan-kpi-card gradient-spp">
            <i class="bi bi-calendar-check kpi-icon-bg"></i>
            <span class="text-white text-opacity-75 small fw-semibold text-uppercase tracking-wider">Tunggakan SPP (s/d
                <?= $bulanIndoList[$bulanSampai] ?>)</span>
            <h3 class="fw-bold text-white mt-2 mb-1">Rp <?= number_format($grandTotalSpp, 0, ',', '.') ?></h3>
            <span class="text-white text-opacity-90 small">Akumulasi SPP belum terbayar</span>
        </div>
    </div>

    <!-- Tunggakan Uang Pangkal -->
    <div class="col-12 col-md-6 col-xl-3">
        <div class="tunggakan-kpi-card gradient-up">
            <i class="bi bi-wallet2 kpi-icon-bg"></i>
            <span class="text-white text-opacity-75 small fw-semibold text-uppercase tracking-wider">Sisa Uang
                Pangkal</span>
            <h3 class="fw-bold text-white mt-2 mb-1">Rp <?= number_format($grandTotalUp, 0, ',', '.') ?></h3>
            <span class="text-white text-opacity-90 small">Sisa kewajiban uang pangkal</span>
        </div>
    </div>

    <!-- Tunggakan Pembayaran Lain -->
    <div class="col-12 col-md-6 col-xl-3">
        <div class="tunggakan-kpi-card gradient-lain">
            <i class="bi bi-receipt-cutoff kpi-icon-bg"></i>
            <span class="text-white text-opacity-75 small fw-semibold text-uppercase tracking-wider">Pembayaran Lain &
                D.U</span>
            <h3 class="fw-bold text-white mt-2 mb-1">Rp <?= number_format($grandTotalLain, 0, ',', '.') ?></h3>
            <span class="text-white text-opacity-90 small">Daftar Ulang, Buku, Seragam, dll</span>
        </div>
    </div>
</div>

<!-- FILTER & SEARCH BAR -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-center">
            <!-- Filter Kelas -->
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Kelas:</label>
                <select name="kelas_id" class="form-select form-select-sm rounded-3" onchange="this.form.submit()">
                    <option value="0">Semua Kelas</option>
                    <?php foreach ($kelasList as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filterKelas === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nama_kelas']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Status -->
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Status Tagihan:</label>
                <select name="status_tunggakan" class="form-select form-select-sm rounded-3"
                    onchange="this.form.submit()">
                    <option value="menunggak" <?= $filterStatus === 'menunggak' ? 'selected' : '' ?>>Hanya yang Menunggak
                        (&gt; Rp 0)</option>
                    <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Semua Santri (Menunggak & Lunas)
                    </option>
                    <option value="lunas" <?= $filterStatus === 'lunas' ? 'selected' : '' ?>>Hanya yang Lunas (Rp 0)
                    </option>
                </select>
            </div>

            <!-- Hitung SPP Sampai Bulan -->
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Hitung SPP Sampai:</label>
                <div class="input-group input-group-sm">
                    <select name="bulan_sampai" class="form-select rounded-start-3" onchange="this.form.submit()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= (int) $filterBulanSampai === $m ? 'selected' : '' ?>>
                                <?= namaBulan($m) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <input type="number" name="tahun" class="form-control rounded-end-3 font-monospace"
                        style="max-width: 80px;" value="<?= $filterTahun ?>" onchange="this.form.submit()">
                </div>
            </div>

            <!-- Search Box -->
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Cari Santri:</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="searchInput" class="form-control border-start-0"
                        placeholder="Nama / NIS / No. HP..." onkeyup="filterTable(this.value)">
                </div>
            </div>
        </form>
    </div>
</div>

<!-- TABEL REKAP TUNGGAKAN -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-tunggakan align-middle mb-0" id="tableTunggakan">
            <thead>
                <tr>
                    <th class="text-center" style="width: 45px;">NO</th>
                    <th style="min-width: 200px;">IDENTITAS SANTRI</th>
                    <th style="min-width: 150px;">KONTAK WALI (ORTU)</th>
                    <th style="min-width: 180px;">TUNGGAKAN SPP</th>
                    <th style="min-width: 170px;">UANG PANGKAL</th>
                    <th style="min-width: 190px;">PEMBAYARAN LAIN</th>
                    <th style="min-width: 160px;" class="text-end">TOTAL TAGIHAN</th>
                    <th class="text-center" style="width: 120px;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dataTunggakan)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-check2-circle fs-1 d-block mb-2 text-success"></i>
                            <div class="fw-bold">Tidak ada santri yang memiliki tunggakan dengan kriteria filter ini.</div>
                            <small class="text-muted">Seluruh pembayaran santri terpilih telah lunas atau filter terlalu
                                spesifik.</small>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($dataTunggakan as $idx => $row): ?>
                        <?php try { ?>
                            <tr class="tunggakan-row">
                                <td class="text-center text-muted font-monospace fw-semibold"><?= (int) ($idx + 1) ?></td>

                                <!-- Identitas Siswa -->
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                                            style="width: 36px; height: 36px; font-size: 0.9rem;">
                                            <?= strtoupper(substr((string) ($row['nama'] ?? 'S'), 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars((string) ($row['nama'] ?? '')) ?>
                                            </div>
                                            <div class="small text-muted d-flex align-items-center gap-1.5 font-monospace">
                                                <span>NIS:
                                                    <code><?= htmlspecialchars((string) ($row['nis'] ?? '-')) ?></code></span>
                                                <span>&bull;</span>
                                                <span
                                                    class="badge bg-light text-secondary border font-monospace"><?= htmlspecialchars((string) ($row['nama_kelas'] ?? '-')) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Kontak Wali Ortu -->
                                <td>
                                    <?php if (!empty($row['no_hp_ortu'])): ?>
                                        <div>
                                            <span class="small fw-semibold text-dark d-block font-monospace">
                                                <?= htmlspecialchars((string) $row['no_hp_ortu']) ?>
                                            </span>
                                            <?php if (!empty($row['clean_phone'])): ?>
                                                <a href="https://wa.me/<?= htmlspecialchars((string) $row['clean_phone']) ?>?text=<?= rawurlencode((string) ($row['wa_message'] ?? '')) ?>"
                                                    target="_blank"
                                                    class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 text-decoration-none d-inline-flex align-items-center gap-1 mt-0.5"
                                                    title="Buka Chat WhatsApp">
                                                    <i class="bi bi-whatsapp"></i> Chat WhatsApp
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small italic">- Belum ada No HP -</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Tunggakan SPP -->
                                <td>
                                    <?php if ((float) ($row['tunggakan_spp'] ?? 0) > 0): ?>
                                        <a href="../spp/create.php?siswa_id=<?= (int) ($row['id'] ?? 0) ?>&bulan=<?= (int) ($row['first_unpaid_bulan'] ?? 1) ?>&tahun=<?= (int) $filterTahun ?>"
                                            class="badge-tagihan-item danger mb-1 text-decoration-none d-inline-flex align-items-center gap-1"
                                            title="Klik untuk Input Bayar SPP">
                                            <i class="bi bi-exclamation-circle-fill"></i> <?= (int) ($row['count_unpaid_spp'] ?? 0) ?>
                                            Bulan (Rp
                                            <?= number_format((float) ($row['tunggakan_spp'] ?? 0), 0, ',', '.') ?>)
                                            <i class="bi bi-arrow-right-short" style="font-size: 1.1rem; line-height: 1;"></i>
                                        </a>
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">
                                            <?= htmlspecialchars((string) ($row['bulan_spp_text'] ?? '-')) ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="badge-tagihan-item success">
                                            <i class="bi bi-check-circle-fill"></i> SPP Lunas
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Tunggakan Uang Pangkal -->
                                <td>
                                    <?php if ((float) ($row['target_up'] ?? 0) > 0): ?>
                                        <?php if ((float) ($row['tunggakan_up'] ?? 0) > 0): ?>
                                            <a href="../uang-pangkal/create.php?siswa_id=<?= (int) ($row['id'] ?? 0) ?>"
                                                class="badge-tagihan-item warning mb-1 text-decoration-none d-inline-flex align-items-center gap-1"
                                                title="Klik untuk Input Bayar Uang Pangkal">
                                                <i class="bi bi-hourglass-split"></i> Sisa Rp
                                                <?= number_format((float) ($row['tunggakan_up'] ?? 0), 0, ',', '.') ?>
                                                <i class="bi bi-arrow-right-short" style="font-size: 1.1rem; line-height: 1;"></i>
                                            </a>
                                            <small class="text-muted d-block" style="font-size: 0.72rem;">
                                                Masuk Rp <?= number_format((float) ($row['paid_up'] ?? 0), 0, ',', '.') ?> / Rp
                                                <?= number_format((float) ($row['target_up'] ?? 0), 0, ',', '.') ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="badge-tagihan-item success">
                                                <i class="bi bi-check-circle-fill"></i> UP Lunas
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small" style="font-size: 0.75rem;">- Tidak Ditarget -</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Tunggakan Pembayaran Lain -->
                                <td>
                                    <?php if (!empty($row['unpaid_lain_items']) && is_array($row['unpaid_lain_items'])): ?>
                                        <div class="badge-tagihan-item warning mb-1">
                                            <i class="bi bi-receipt"></i> Rp
                                            <?= number_format((float) ($row['tunggakan_lain'] ?? 0), 0, ',', '.') ?>
                                        </div>
                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                            <?php foreach ($row['unpaid_lain_items'] as $uli): ?>
                                                <a href="../pembayaran-lain/create.php?siswa_id=<?= (int) ($row['id'] ?? 0) ?>&jenis_id=<?= (int) ($uli['id'] ?? 0) ?>"
                                                    class="badge bg-light text-dark border text-decoration-none py-1 px-1.5 d-inline-flex align-items-center gap-1"
                                                    style="font-size: 0.7rem;"
                                                    title="Klik untuk Bayar <?= htmlspecialchars((string) ($uli['nama'] ?? 'Tagihan')) ?>">
                                                    <span><?= htmlspecialchars((string) ($uli['nama'] ?? 'Tagihan')) ?>: <strong>Rp
                                                            <?= number_format((float) ($uli['sisa'] ?? 0), 0, ',', '.') ?></strong></span>
                                                    <i class="bi bi-plus-circle text-primary"></i>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge-tagihan-item success">
                                            <i class="bi bi-check-circle-fill"></i> Lunas
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Total Tunggakan & Persentase -->
                                <td class="text-end">
                                    <?php if ((float) ($row['total_tunggakan'] ?? 0) > 0): ?>
                                        <div class="fw-bold text-danger fs-6 font-monospace">
                                            Rp <?= number_format((float) ($row['total_tunggakan'] ?? 0), 0, ',', '.') ?>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-end gap-1 mt-0.5">
                                            <span
                                                class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25"
                                                style="font-size: 0.68rem;">
                                                Belum Lunas (<?= (int) ($row['persen_lunas'] ?? 0) ?>%)
                                            </span>
                                        </div>
                                        <div class="progress mt-1.5 ms-auto rounded-pill"
                                            style="height: 4.5px; width: 100px; background-color: #fee2e2;">
                                            <div class="progress-bar bg-success rounded-pill" role="progressbar"
                                                style="width: <?= (int) ($row['persen_lunas'] ?? 0) ?>%;"
                                                aria-valuenow="<?= (int) ($row['persen_lunas'] ?? 0) ?>" aria-valuemin="0"
                                                aria-valuemax="100">
                                            </div>
                                        </div>
                                        <small class="text-muted d-block mt-0.5" style="font-size: 0.68rem;">
                                            Terbayar <?= (int) ($row['persen_lunas'] ?? 0) ?>%
                                        </small>
                                    <?php else: ?>
                                        <div class="fw-bold text-success font-monospace">Rp 0</div>
                                        <div class="d-flex align-items-center justify-content-end gap-1 mt-0.5">
                                            <span
                                                class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"
                                                style="font-size: 0.68rem;">
                                                <i class="bi bi-check-circle-fill"></i> LUNAS (100%)
                                            </span>
                                        </div>
                                        <div class="progress mt-1.5 ms-auto rounded-pill"
                                            style="height: 4.5px; width: 100px; background-color: #d1fae5;">
                                            <div class="progress-bar bg-success rounded-pill" role="progressbar" style="width: 100%;">
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Aksi -->
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <!-- Tombol Edit Kewajiban Santri -->
                                        <button type="button"
                                            class="btn btn-sm btn-light border rounded-3 p-1.5 btn-edit-obligation"
                                            title="Sesuaikan Kewajiban, Bulan Mulai SPP & Bebas Pembayaran"
                                            data-id="<?= (int) ($row['id'] ?? 0) ?>"
                                            data-nama="<?= htmlspecialchars((string) ($row['nama'] ?? ''), ENT_QUOTES) ?>"
                                            data-spp="<?= (float) ($row['nominal_spp_per_bulan'] ?? 150000) ?>"
                                            data-up="<?= (float) ($row['target_up'] ?? 0) ?>"
                                            data-bulan="<?= (int) ($row['bulan_mulai_spp'] ?? 7) ?>"
                                            data-bebas='<?= htmlspecialchars(json_encode($row['bebas_jp_ids'] ?? []), ENT_QUOTES) ?>'>
                                            <i class="bi bi-pencil-square text-secondary"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php } catch (\Throwable $e) { ?>
                            <tr class="tunggakan-row">
                                <td class="text-center text-muted"><?= (int) ($idx + 1) ?></td>
                                <td colspan="7" class="text-danger small">
                                    <i class="bi bi-exclamation-triangle"></i> Gagal memuat data santri
                                    #<?= (int) ($row['id'] ?? 0) ?>: <?= htmlspecialchars($e->getMessage()) ?>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: ATUR VISIBILITAS DI PORTAL ORTU (ADMIN & BENDAHARA) -->
<div class="modal fade" id="modalVisibilitySettings" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="save_visibility_settings">

                <div class="modal-header border-bottom bg-light px-4 py-3">
                    <h6 class="modal-title fw-bold text-dark d-flex align-items-center gap-2 fs-6">
                        <i class="bi bi-sliders text-primary"></i> Pengaturan Visibilitas Tagihan di Portal Ortu
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 rounded-3 small mb-3">
                        <i class="bi bi-info-circle-fill me-1"></i>
                        Pilih jenis tagihan apa saja yang aktif dan ditampilkan pada beranda <strong>Portal Orang
                            Tua</strong>.
                    </div>

                    <div class="row g-3">
                        <!-- Toggle Kategori Utama -->
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-dark mb-2">Tagihan Utama di Portal Ortu:</label>
                            <div class="d-flex flex-column gap-2 p-3 rounded-3 bg-light border">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="tunggakan_show_spp"
                                        id="chkShowSpp" value="1" <?= $showSppSetting ? 'checked' : '' ?>>
                                    <label class="form-check-label small fw-semibold" for="chkShowSpp">
                                        💳 Tampilkan Tagihan SPP Bulanan
                                    </label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="tunggakan_show_uang_pangkal"
                                        id="chkShowUp" value="1" <?= $showUpSetting ? 'checked' : '' ?>>
                                    <label class="form-check-label small fw-semibold" for="chkShowUp">
                                        📁 Tampilkan Tagihan Uang Pangkal
                                    </label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                        name="tunggakan_show_pembayaran_lain" id="chkShowLain" value="1"
                                        <?= $showLainSetting ? 'checked' : '' ?>>
                                    <label class="form-check-label small fw-semibold" for="chkShowLain">
                                        🧾 Tampilkan Tagihan Pembayaran Lainnya
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Status & Sasaran Tingkat Kategori Pembayaran Lainnya -->
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-dark mb-2">Status & Sasaran Target Pembayaran
                                Lain:</label>
                            <div class="p-3 rounded-3 bg-light border" style="max-height: 240px; overflow-y: auto;">
                                <?php if (empty($allJenisPembayaran)): ?>
                                    <small class="text-muted">Belum ada kategori pembayaran lain.</small>
                                <?php else: ?>
                                    <div class="d-flex flex-column gap-2.5">
                                        <?php foreach ($allJenisPembayaran as $ajp): ?>
                                            <?php $curTarget = $ajp['target_tingkat'] ?? 'all'; ?>
                                            <div class="p-2 bg-white rounded-3 border shadow-none">
                                                <div class="d-flex justify-content-between align-items-center mb-1.5">
                                                    <span
                                                        class="small fw-bold text-dark"><?= htmlspecialchars($ajp['nama_pembayaran']) ?></span>
                                                    <select name="jenis_status[<?= $ajp['id'] ?>]"
                                                        class="form-select form-select-sm py-0 px-2 fw-semibold"
                                                        style="width: 105px; font-size: 0.78rem;">
                                                        <option value="aktif" <?= $ajp['status'] === 'aktif' ? 'selected' : '' ?>>
                                                            🟢 Aktif</option>
                                                        <option value="nonaktif" <?= $ajp['status'] === 'nonaktif' ? 'selected' : '' ?>>⚪ Nonaktif</option>
                                                    </select>
                                                </div>
                                                <div class="d-flex align-items-center gap-1.5">
                                                    <label class="extra-small text-muted mb-0 flex-shrink-0"
                                                        style="font-size: 0.72rem;">Sasaran Tagihan:</label>
                                                    <select name="jenis_target_tingkat[<?= $ajp['id'] ?>]"
                                                        class="form-select form-select-sm py-0 px-1.5"
                                                        style="font-size: 0.75rem;">
                                                        <option value="all" <?= $curTarget === 'all' ? 'selected' : '' ?>>Semua
                                                            Tingkat (Semua Santri)</option>
                                                        <option value="9" <?= $curTarget === '9' ? 'selected' : '' ?>>Khusus Kelas
                                                            IX (9)</option>
                                                        <option value="8" <?= $curTarget === '8' ? 'selected' : '' ?>>Khusus Kelas
                                                            VIII (8)</option>
                                                        <option value="7" <?= $curTarget === '7' ? 'selected' : '' ?>>Khusus Kelas
                                                            VII (7 - Santri Baru)</option>
                                                        <option value="8,9" <?= ($curTarget === '8,9' || $curTarget === '9,8') ? 'selected' : '' ?>>Kelas VIII &amp; IX (Santri Lama)</option>
                                                        <option value="7,8" <?= ($curTarget === '7,8' || $curTarget === '8,7') ? 'selected' : '' ?>>Kelas VII &amp; VIII</option>
                                                        <option value="7,9" <?= ($curTarget === '7,9' || $curTarget === '9,7') ? 'selected' : '' ?>>Kelas VII &amp; IX</option>
                                                    </select>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Pengaturan Default Bulan Mulai Tagih SPP per Tingkat -->
                        <div class="col-12">
                            <div class="p-3 rounded-3 bg-light border">
                                <label
                                    class="form-label small fw-bold text-dark mb-2 d-flex align-items-center gap-1.5">
                                    <i class="bi bi-calendar2-range text-primary"></i> Aturan Default Bulan Mulai Tagih
                                    SPP per Tingkat:
                                </label>
                                <div class="row g-2">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label extra-small text-muted mb-1">Santri Baru (Kelas 7 /
                                            VII):</label>
                                        <select name="default_spp_mulai_kls7" class="form-select form-select-sm">
                                            <?php
                                            for ($m = 1; $m <= 12; $m++) {
                                                $isSel7 = ($defaultSppKls7Setting === $m) ? ' selected' : '';
                                                $lbl7 = ($m === 7) ? ' - Default Santri Baru' : '';
                                                echo '<option value="' . $m . '"' . $isSel7 . '>Mulai Bulan ' . namaBulan($m) . ' (Bulan ' . $m . ')' . $lbl7 . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label extra-small text-muted mb-1">Santri Naik Kelas (Kelas 8
                                            &amp; 9 / VIII &amp; IX):</label>
                                        <select name="default_spp_mulai_kls89" class="form-select form-select-sm">
                                            <?php
                                            for ($m = 1; $m <= 12; $m++) {
                                                $isSel89 = ($defaultSppKls89Setting === $m) ? ' selected' : '';
                                                $lbl89 = ($m === 1) ? ' - Default dari Januari' : '';
                                                echo '<option value="' . $m . '"' . $isSel89 . '>Mulai Bulan ' . namaBulan($m) . ' (Bulan ' . $m . ')' . $lbl89 . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-check mt-2.5">
                                    <input class="form-check-input" type="checkbox" name="apply_bulk_start_month"
                                        id="chkApplyBulk" value="1">
                                    <label class="form-check-label extra-small fw-semibold text-primary"
                                        for="chkApplyBulk">
                                        🔄 Terapkan &amp; Sinkronkan aturan default ini ke seluruh santri aktif sekarang
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Informasi Nomor Rekening Sekolah -->
                        <div class="col-12">
                            <label class="form-label small fw-bold text-dark mb-1">Nomor Rekening Sekolah (Untuk
                                Penagihan WA):</label>
                            <input type="text" name="tunggakan_rekening_info" class="form-control"
                                value="<?= htmlspecialchars($rekeningInfoSetting) ?>"
                                placeholder="Misal: BSI 1234-5678-90 a.n. SMP Minhaj Al-Ilmi">
                        </div>

                        <!-- Template Pesan WhatsApp -->
                        <div class="col-12">
                            <label class="form-label small fw-bold text-dark mb-1">Format Template Pesan WhatsApp
                                Penagihan:</label>
                            <textarea name="tunggakan_pesan_wa" class="form-control font-monospace small"
                                rows="5"><?= htmlspecialchars($pesanWaSetting) ?></textarea>
                            <small class="text-muted" style="font-size: 0.72rem;">
                                Variabel yang didukung: <code>{nama_siswa}</code>, <code>{nama_kelas}</code>,
                                <code>{nis}</code>, <code>{rincian_tunggakan}</code>, <code>{total_tunggakan}</code>,
                                <code>{rekening_info}</code>, <code>{nama_sekolah}</code>
                            </small>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-2.5">
                    <button type="button" class="btn btn-sm btn-secondary rounded-3 px-3"
                        data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold rounded-3 px-4">
                        <i class="bi bi-check2"></i> Simpan Pengaturan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EDIT KEWAJIBAN SANTRI (NOMINAL SPP, BULAN MULAI, TARGET UP & BEBAS PEMBAYARAN) -->
<div class="modal fade" id="modalEditObligation" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="update_student_obligation">
                <input type="hidden" name="siswa_id" id="editSiswaId" value="0">

                <div class="modal-header border-bottom bg-light px-4 py-3">
                    <h6 class="modal-title fw-bold text-dark d-flex align-items-center gap-1.5 fs-6">
                        <i class="bi bi-pencil-square text-primary"></i> Sesuaikan Kewajiban Tagihan Santri
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <span class="small text-muted d-block">Nama Santri:</span>
                        <div class="fw-bold text-dark fs-6" id="editSiswaName">-</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-dark mb-1">Nominal SPP per Bulan (Rp):</label>
                            <input type="number" name="nominal_spp" id="editNominalSpp" class="form-control" required
                                min="0" step="1000">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-dark mb-1">Bulan Mulai Tagih SPP:</label>
                            <select name="bulan_mulai_spp" id="editBulanMulaiSpp" class="form-select" required>
                                <option value="1">Januari (Bulan 1)</option>
                                <option value="2">Februari (Bulan 2)</option>
                                <option value="3">Maret (Bulan 3)</option>
                                <option value="4">April (Bulan 4)</option>
                                <option value="5">Mei (Bulan 5)</option>
                                <option value="6">Juni (Bulan 6)</option>
                                <option value="7">Juli (Bulan 7 - Santri Baru Kelas 7)</option>
                                <option value="8">Agustus (Bulan 8)</option>
                                <option value="9">September (Bulan 9)</option>
                                <option value="10">Oktober (Bulan 10)</option>
                                <option value="11">November (Bulan 11)</option>
                                <option value="12">Desember (Bulan 12)</option>
                            </select>
                            <small class="text-muted" style="font-size: 0.72rem;">Santri baru kelas 7 otomatis mulai
                                Juli. Bulan sebelum ini tidak ditagihkan.</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold text-dark mb-1">Target Uang Pangkal (Rp):</label>
                            <input type="number" name="target_uang_pangkal" id="editTargetUp" class="form-control"
                                required min="0" step="1000">
                            <small class="text-muted" style="font-size: 0.72rem;">Isi 0 jika tidak memiliki kewajiban
                                uang pangkal.</small>
                        </div>

                        <!-- Checklist Pengecualian / Bebas Pembayaran Lain -->
                        <div class="col-12">
                            <label class="form-label small fw-bold text-dark mb-1">Pengecualian / Bebaskan Pembayaran
                                Lain:</label>
                            <div class="p-3 bg-light rounded-3 border" style="max-height: 180px; overflow-y: auto;">
                                <div class="text-muted small mb-2" style="font-size: 0.75rem;">
                                    Centang pembayaran di bawah jika santri ini <strong>DIBEBASKAN</strong> (tidak wajib
                                    membayar):
                                </div>
                                <?php if (empty($jenisPembayaranList)): ?>
                                    <small class="text-muted">Tidak ada data jenis pembayaran lain.</small>
                                <?php else: ?>
                                    <?php foreach ($jenisPembayaranList as $jpItem): ?>
                                        <div class="form-check mb-1.5">
                                            <input class="form-check-input chk-bebas-jp" type="checkbox" name="bebas_jp[]"
                                                value="<?= $jpItem['id'] ?>" id="chk_bebas_<?= $jpItem['id'] ?>">
                                            <label class="form-check-label small fw-semibold text-dark"
                                                for="chk_bebas_<?= $jpItem['id'] ?>">
                                                <?= htmlspecialchars($jpItem['nama_pembayaran']) ?>
                                                <span class="text-muted fw-normal font-monospace">(Rp
                                                    <?= number_format($jpItem['nominal_default'], 0, ',', '.') ?>)</span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-sm btn-secondary rounded-3 px-3"
                        data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold rounded-3 px-4">
                        <i class="bi bi-save"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Live Filter Table
    function filterTable(query) {
        const q = query.toLowerCase().trim();
        const rows = document.querySelectorAll('#tableTunggakan tbody tr.tunggakan-row');
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(q) ? '' : 'none';
        });
    }

    // Modal Edit Kewajiban Santri
    function openEditObligationModal(id, name, spp, up, bulanMulai, bebasIds) {
        document.getElementById('editSiswaId').value = id;
        document.getElementById('editSiswaName').innerText = name;
        document.getElementById('editNominalSpp').value = Math.round(spp);
        document.getElementById('editTargetUp').value = Math.round(up);

        if (bulanMulai) {
            document.getElementById('editBulanMulaiSpp').value = bulanMulai;
        } else {
            document.getElementById('editBulanMulaiSpp').value = 7;
        }

        // Reset and check bebas checkboxes
        const chkList = document.querySelectorAll('.chk-bebas-jp');
        chkList.forEach(chk => {
            const jpId = parseInt(chk.value);
            chk.checked = Array.isArray(bebasIds) && bebasIds.includes(jpId);
        });

        const modalEl = document.getElementById('modalEditObligation');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-edit-obligation');
        if (!btn) return;
        const id = parseInt(btn.dataset.id) || 0;
        const name = btn.dataset.nama || '';
        const spp = parseFloat(btn.dataset.spp) || 150000;
        const up = parseFloat(btn.dataset.up) || 0;
        const bulan = parseInt(btn.dataset.bulan) || 7;
        let bebasIds = [];
        try {
            bebasIds = JSON.parse(btn.dataset.bebas || '[]');
        } catch (err) { }

        openEditObligationModal(id, name, spp, up, bulan, bebasIds);
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>