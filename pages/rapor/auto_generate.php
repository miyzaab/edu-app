<?php
/**
 * AUTO-GENERATE DESKRIPSI RAPOR MASSAL 1 KELAS
 * Membantu wali kelas mengisi deskripsi capaian kompetensi seluruh siswa secara otomatis
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('rapor');

$pdo = getConnection();
require_once __DIR__ . '/init_rapor.php';
require_once __DIR__ . '/../../includes/rapor_helpers.php';
$kelasId = (int) ($_GET['kelas_id'] ?? 0);
$rawTipe = strtolower($_GET['tipe'] ?? 'ats');
$isAts = in_array($rawTipe, ['ats', 'pts', 'tengah']);
$tipeRapor = $isAts ? 'ats' : 'pas';
$semester = isset($_GET['semester']) && in_array($_GET['semester'], ['Ganjil', 'Genap']) ? $_GET['semester'] : 'Ganjil';
$tahunAjaran = isset($_GET['tahun_ajaran']) ? trim($_GET['tahun_ajaran']) : (date('Y') . '/' . (date('Y') + 1));

if ($kelasId <= 0) {
    redirect('index.php', 'danger', 'ID Kelas tidak valid.');
}

// Ambil Data Kelas & Wali
$stmtK = $pdo->prepare("
    SELECT k.*, u.nama_lengkap AS nama_wali_kelas, gd.nip AS nip_wali_kelas
    FROM kelas k
    LEFT JOIN users u ON k.wali_kelas_id = u.id
    LEFT JOIN guru_detail gd ON (u.id = gd.user_id OR k.wali_kelas_id = gd.user_id)
    WHERE k.id = :id
");
$stmtK->execute([':id' => $kelasId]);
$kelas = $stmtK->fetch();

if (!$kelas) {
    redirect('index.php', 'danger', 'Data kelas tidak ditemukan.');
}

// Ambil Siswa Aktif
$stmtS = $pdo->prepare("SELECT id, nama FROM siswa WHERE kelas_id = :kid AND status = 'aktif'");
$stmtS->execute([':kid' => $kelasId]);
$siswaList = $stmtS->fetchAll();

if (empty($siswaList)) {
    redirect("index.php?kelas_id={$kelasId}&tipe={$tipeRapor}&semester={$semester}&tahun_ajaran=" . urlencode($tahunAjaran), 'warning', 'Tidak ada siswa aktif di kelas ini.');
}

// Ambil Settings
$settingsRaw = $pdo->query("SELECT setting_key, setting_value FROM rapor_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$defaultKepsekNama = $settingsRaw['kepsek_nama'] ?? 'Ustadz Ibrahim, M.Pd.';
$defaultKepsekNip = $settingsRaw['kepsek_nip'] ?? '-';
$defaultTempat = $settingsRaw['tempat_terbit'] ?? 'Bogor';
$defaultFase = $settingsRaw['fase_default'] ?? 'D';
$defaultKokurikuler = $settingsRaw['kokurikuler_default'] ?? 'Peserta didik aktif berpartisipasi dalam Projek Penguatan Profil Pelajar Pancasila (P5) dengan menunjukkan sikap mandiri, gotong royong, dan bernalar kritis.';
$defaultTanggalKey = 'tanggal_' . $tipeRapor . '_' . strtolower($semester);
$defaultTanggal = $settingsRaw[$defaultTanggalKey] ?? date('Y-m-d');

$waliNama = $kelas['nama_wali_kelas'] ?? 'Wali Kelas';
$waliNip = $kelas['nip_wali_kelas'] ?? '-';

// Ambil Seluruh Mapel Aktif untuk kelas ini (mendukung plotting per kelas)
$stmtMapels = $pdo->prepare("
    SELECT DISTINCT m.nama_mapel 
    FROM guru_mapel_kelas gmk
    JOIN mata_pelajaran m ON gmk.mapel_id = m.id 
    WHERE gmk.kelas_id = :kid AND m.status = 'aktif' 
    ORDER BY m.kelompok ASC, m.kode_mapel ASC
");
$stmtMapels->execute([':kid' => $kelasId]);
$mapelList = $stmtMapels->fetchAll(PDO::FETCH_COLUMN);

// Fallback jika belum di-plotting sama sekali
if (empty($mapelList)) {
    $mapelList = $pdo->query("SELECT nama_mapel FROM mata_pelajaran WHERE status = 'aktif' ORDER BY kelompok ASC, kode_mapel ASC")->fetchAll(PDO::FETCH_COLUMN);
}

try {
    $pdo->beginTransaction();

    $stmtCheck = $pdo->prepare("SELECT * FROM rapor_siswa WHERE siswa_id = :sid AND tipe_rapor = :tipe AND semester = :sem AND tahun_ajaran = :th LIMIT 1");
    $stmtUpsert = $pdo->prepare("
        INSERT INTO rapor_siswa 
        (siswa_id, kelas_id, tipe_rapor, semester, tahun_ajaran, fase, sakit, izin, tanpa_keterangan, 
         catatan_wali_kelas, tanggapan_ortu, kokurikuler_deskripsi, ekskul_json, deskripsi_mapel_json, 
         wali_kelas_nama, wali_kelas_nip, kepsek_nama, kepsek_nip, tanggal_rapor, tempat_terbit)
        VALUES 
        (:sid, :kid, :tipe, :sem, :th, :fase, :sakit, :izin, :alpa, 
         :cat, :tang, :koku, :ekskul, :desk, 
         :wnama, :wnip, :knama, :knip, :tgl, :tempat)
        ON DUPLICATE KEY UPDATE
        fase = VALUES(fase),
        kokurikuler_deskripsi = IF(kokurikuler_deskripsi IS NULL OR kokurikuler_deskripsi='', VALUES(kokurikuler_deskripsi), kokurikuler_deskripsi),
        deskripsi_mapel_json = VALUES(deskripsi_mapel_json),
        wali_kelas_nama = VALUES(wali_kelas_nama),
        wali_kelas_nip = VALUES(wali_kelas_nip),
        kepsek_nama = VALUES(kepsek_nama),
        kepsek_nip = VALUES(kepsek_nip),
        tanggal_rapor = VALUES(tanggal_rapor),
        tempat_terbit = VALUES(tempat_terbit)
    ");

// Helper Kamus Materi / Tujuan Pembelajaran Mapel untuk Rapor AAS
function getMateriMapelAas(string $mapelName, PDO $pdo, int $kelasId, string $semester): string {
    // 1. Coba cari dari perangkat ajar
    try {
        $stmtPa = $pdo->prepare("
            SELECT tujuan_pembelajaran, alur_tujuan_pembelajaran 
            FROM perangkat_ajar 
            WHERE mapel LIKE :m 
            ORDER BY id DESC LIMIT 1
        ");
        $stmtPa->execute([':m' => '%' . trim($mapelName) . '%']);
        $pa = $stmtPa->fetch(PDO::FETCH_ASSOC);
        if ($pa && !empty($pa['tujuan_pembelajaran'])) {
            $lines = array_filter(array_map('trim', explode("\n", $pa['tujuan_pembelajaran'])));
            $cleanLines = [];
            foreach ($lines as $l) {
                $l = preg_replace('/^[\d\.\-\*\•\s]+/', '', $l);
                if (strlen($l) > 10) {
                    $cleanLines[] = rtrim($l, ".,;");
                }
                if (count($cleanLines) >= 3) break;
            }
            if (!empty($cleanLines)) {
                return implode(", ", $cleanLines);
            }
        }
    } catch (Exception $e) {}

    // 2. Fallback kamus kompetensi Kurikulum Merdeka SMP (Fase D)
    $lower = strtolower($mapelName);
    if (strpos($lower, 'agama') !== false || strpos($lower, 'pai') !== false) {
        return "makna iman kepada Allah dan rukun iman, Meneladani akhlak terpuji dalam kehidupan sehari-hari, Mempraktikkan ibadah wajib dengan benar, Menunjukkan sikap jujur, disiplin, dan tanggung jawab";
    } elseif (strpos($lower, 'pancasila') !== false || strpos($lower, 'pkn') !== false) {
        return "Mengidentifikasi norma dan aturan dalam kehidupan, Menunjukkan sikap bertanggung jawab sebagai warga sekolah dan masyarakat, Menjelaskan hak dan kewajiban sebagai warga sekolah, Menunjukkan sikap toleransi dan tanggung jawab";
    } elseif (strpos($lower, 'indonesia') !== false) {
        return "Mengidentifikasi informasi dalam teks deskripsi dan narasi, Menyusun teks deskripsi sesuai struktur dan kaidah, Menyampaikan gagasan secara lisan dengan runtut, Menulis teks sederhana dengan ejaan yang tepat";
    } elseif (strpos($lower, 'matematika') !== false) {
        return "konsep bilangan bulat dan operasinya, Menyelesaikan masalah pecahan dan desimal, Menggunakan bentuk aljabar sederhana, Menyelesaikan persamaan linear satu variabel";
    } elseif (strpos($lower, 'ipa') !== false || strpos($lower, 'alam') !== false) {
        return "hakikat ilmu IPA dan metode ilmiah, Mengidentifikasi klasifikasi makhluk hidup, Memahami ciri-ciri makhluk hidup, Menggunakan alat laboratorium sederhana dengan benar";
    } elseif (strpos($lower, 'ips') !== false || strpos($lower, 'sosial') !== false) {
        return "konsep ruang dan interaksi antar ruang, Mengidentifikasi kondisi geografis Indonesia, Menganalisis aktivitas sosial dan ekonomi masyarakat, Menunjukkan kepedulian terhadap lingkungan sosial";
    } elseif (strpos($lower, 'inggris') !== false) {
        return "ungkapan sapaan dan perkenalan diri, Menggunakan kosakata tentang identitas dan lingkungan sekitar, Menulis teks deskriptif sederhana, Memahami instruksi dan pesan pendek";
    } elseif (strpos($lower, 'arab') !== false) {
        return "tata bahasa dasar, ungkapan ta'aruf harian, penguasaan mufrodat tematik, serta pelafalan dan pemahaman teks sederhana";
    } elseif (strpos($lower, 'tahfidz') !== false || strpos($lower, 'qur') !== false) {
        return "kelancaran makharijul huruf, kaidah tajwid, hafalan ayat pilihan secara mutqin, serta adab tilawah Al-Qur'an";
    } elseif (strpos($lower, 'fiqih') !== false) {
        return "ketentuan bersuci (thaharah), shalat fardhu dan sunnah, adab ibadah harian, serta muamalah dasar";
    } elseif (strpos($lower, 'akidah') !== false || strpos($lower, 'akhlak') !== false) {
        return "pemahaman rukun iman, penghayatan asmaul husna, adab pergaulan islami, serta pencegahan akhlak tercela";
    } elseif (strpos($lower, 'pjok') !== false || strpos($lower, 'olahraga') !== false) {
        return "keterampilan gerak dasar olahraga, kebugaran jasmani, sportivitas, serta pembiasaan pola hidup sehat";
    } elseif (strpos($lower, 'informatika') !== false || strpos($lower, 'komputer') !== false) {
        return "berpikir komputasional, literasi digital, pengolahan data sederhana, serta etika penggunaan teknologi informasi";
    } elseif (strpos($lower, 'seni') !== false || strpos($lower, 'prakarya') !== false) {
        return "eksplorasi unsur seni budaya nusantara, apresiasi karya seni, serta kreasi produk fungsional secara mandiri";
    }
    return "materi pokok dan kompetensi capaian pembelajaran " . $mapelName;
}

// Helper perakit kalimat deskripsi AAS sesuai rentang nilai yang ditentukan user:
// 88 - 100 : Sangat Baik dalam memahami...
// 74 - 87  : Baik dalam memahami...
// 60 - 73  : Cukup baik dalam memahami...
// 0 - 59   : Butuh bimbingan dalam memahami...
function buildDeskripsiAas(float $na, string $materi): string {
    if ($na >= 88) {
        return "Ananda menunjukkan pemahaman yang Sangat Baik dalam memahami " . $materi . ".";
    } elseif ($na >= 74) {
        return "Ananda menunjukkan pemahaman yang Baik dalam memahami " . $materi . ".";
    } elseif ($na >= 60) {
        return "Ananda Cukup baik dalam memahami " . $materi . ", namun membutuhkan penguatan lebih lanjut.";
    } else {
        return "Ananda Butuh bimbingan dalam memahami " . $materi . ".";
    }
}

$defaultEkskulJson = json_encode([
    ['nama' => 'Bela Diri', 'predikat' => 'A', 'keterangan' => 'Ananda mampu dan aktif mengikuti kegiatan bela diri serta menunjukkan disiplin dan sportivitas yang baik.'],
    ['nama' => 'Tahfidz Al-Qur\'an', 'predikat' => 'A', 'keterangan' => 'Menunjukkan kelancaran hafalan dan adab tilawah yang baik.']
], JSON_UNESCAPED_UNICODE);

    $countProcessed = 0;
    $countGuruSynced = 0;
    foreach ($siswaList as $s) {
        $sid = (int) $s['id'];

        // Ambil seluruh nilai siswa ini untuk semester & tahun ajaran
        $stmtNilai = $pdo->prepare("SELECT * FROM nilai_siswa WHERE siswa_id = :sid AND semester = :sem AND tahun_ajaran = :th");
        $stmtNilai->execute([':sid' => $sid, ':sem' => $semester, ':th' => $tahunAjaran]);
        $nilaiRows = $stmtNilai->fetchAll(PDO::FETCH_ASSOC);

        // Ambil existing rapor jika ada
        $stmtCheck->execute([':sid' => $sid, ':tipe' => $tipeRapor, ':sem' => $semester, ':th' => $tahunAjaran]);
        $existingRapor = $stmtCheck->fetch();

        $existingDeskripsi = !empty($existingRapor['deskripsi_mapel_json']) ? json_decode($existingRapor['deskripsi_mapel_json'], true) : [];
        if (!is_array($existingDeskripsi))
            $existingDeskripsi = [];

        $deskripsiMapel = $existingDeskripsi;
        foreach ($mapelList as $mName) {
            $matched = matchMapelNilai($nilaiRows, $mName);
            $guruD = '';
            if ($matched) {
                $guruD = $isAts ? trim($matched['deskripsi_ats'] ?? '') : trim($matched['deskripsi_aas'] ?? '');
            }

            // Jika guru sudah menginput deskripsi di Input Nilai, SELALU prioritaskan deskripsi guru pengampu
            if (!empty($guruD)) {
                $deskripsiMapel[$mName] = $guruD;
                $countGuruSynced++;
            } elseif (empty($deskripsiMapel[$mName]) || isset($_GET['overwrite'])) {
                $scoreColumn = $isAts ? 'ats' : 'nilai_akhir';
                $na = ($matched && isset($matched[$scoreColumn]) && $matched[$scoreColumn] !== null && (float) $matched[$scoreColumn] > 0)
                    ? (float) $matched[$scoreColumn]
                    : 75;
                if ($isAts) {
                    if ($na >= 85) {
                        $deskripsiMapel[$mName] = "Menunjukkan penguasaan yang sangat baik dalam memahami materi pokok " . $mName . ", mampu menerapkan konsep secara mandiri dan bernalar kritis.";
                    } elseif ($na >= 75) {
                        $deskripsiMapel[$mName] = "Menunjukkan penguasaan yang baik dalam memahami konsep dasar " . $mName . ", perlu pemantapan pada beberapa indikator capaian.";
                    } else {
                        $deskripsiMapel[$mName] = "Cukup memahami materi pokok " . $mName . ", membutuhkan bimbingan dan pendampingan lebih lanjut untuk penguatan kompetensi dasar.";
                    }
                } else {
                    // Rapor AAS (Akhir Semester) Kurikulum Merdeka Sesuai Rentang Nilai
                    $materi = getMateriMapelAas($mName, $pdo, $kelasId, $semester);
                    $deskripsiMapel[$mName] = buildDeskripsiAas($na, $materi);
                }
            }
        }

        $deskJson = json_encode($deskripsiMapel, JSON_UNESCAPED_UNICODE);
        $sakit = (int) ($existingRapor['sakit'] ?? 0);
        $izin = (int) ($existingRapor['izin'] ?? 0);
        $alpa = (int) ($existingRapor['tanpa_keterangan'] ?? 0);
        $catatan = !empty($existingRapor['catatan_wali_kelas']) ? $existingRapor['catatan_wali_kelas'] : "Alhamdulillah, ananda menunjukkan akhlak yang mulia, kedisiplinan belajar yang baik, serta keaktifan dalam program tahfidz. Pertahankan dan terus tingkatkan prestasimu!";
        $tanggapan = $existingRapor['tanggapan_ortu'] ?? '';
        $koku = !empty($existingRapor['kokurikuler_deskripsi']) ? $existingRapor['kokurikuler_deskripsi'] : $defaultKokurikuler;
        $ekskul = !empty($existingRapor['ekskul_json']) ? $existingRapor['ekskul_json'] : $defaultEkskulJson;

        $stmtUpsert->execute([
            ':sid' => $sid,
            ':kid' => $kelasId,
            ':tipe' => $tipeRapor,
            ':sem' => $semester,
            ':th' => $tahunAjaran,
            ':fase' => $defaultFase,
            ':sakit' => $sakit,
            ':izin' => $izin,
            ':alpa' => $alpa,
            ':cat' => $catatan,
            ':tang' => $tanggapan,
            ':koku' => $koku,
            ':ekskul' => $ekskul,
            ':desk' => $deskJson,
            ':wnama' => $waliNama,
            ':wnip' => $waliNip,
            ':knama' => $defaultKepsekNama,
            ':knip' => $defaultKepsekNip,
            ':tgl' => $defaultTanggal,
            ':tempat' => $defaultTempat
        ]);

        $countProcessed++;
    }

    $pdo->commit();
    redirect("index.php?kelas_id={$kelasId}&tipe={$tipeRapor}&semester={$semester}&tahun_ajaran=" . urlencode($tahunAjaran), 'success', "🎉 Berhasil menyinkronkan & men-generate deskripsi rapor untuk <b>{$countProcessed}</b> santri di Kelas {$kelas['nama_kelas']}! (Deskripsi guru pengampu otomatis disinkronkan).");
} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    redirect("index.php?kelas_id={$kelasId}&tipe={$tipeRapor}&semester={$semester}&tahun_ajaran=" . urlencode($tahunAjaran), 'danger', 'Gagal memproses auto-generate: ' . $e->getMessage());
}
