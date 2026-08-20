<?php
/**
 * CAPAIAN PEMBELAJARAN (CP) - High Quality Modern UI/UX
 * Modul Perangkat Ajar Kurikulum Merdeka
 */
$pageTitle  = 'Capaian Pembelajaran (CP)';
$activePage = 'perangkat-ajar';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_ajar');

$pdo = getConnection();
$userId = (int)$_SESSION['user_id'];
$userRole = strtolower($_SESSION['role'] ?? '');
$isGuruRole = ($userRole === 'guru');

// Restriksi pilihan Mapel untuk Guru Pengampu
if ($isGuruRole) {
    $stmtPlot = $pdo->prepare("
        SELECT DISTINCT m.nama_mapel
        FROM guru_mapel_kelas gmk
        JOIN mata_pelajaran m ON gmk.mapel_id = m.id
        WHERE gmk.user_id = :uid
    ");
    $stmtPlot->execute([':uid' => $userId]);
    $mapelOptions = $stmtPlot->fetchAll(PDO::FETCH_COLUMN);
    if (empty($mapelOptions) && !empty($_SESSION['mapel'])) {
        $mapelOptions = [$_SESSION['mapel']];
    }
} else {
    try {
        $mapelOptions = $pdo->query("SELECT nama_mapel FROM mata_pelajaran WHERE status = 'aktif' ORDER BY kelompok ASC, kode_mapel ASC")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $mapelOptions = [];
    }
}

$defaultNamaSekolah = getSetting('nama_sekolah', SCHOOL_NAME);
$defaultTahun       = date('Y') . '/' . (date('Y') + 1);

// Handle Simpan / Auto-Generate CP Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_cp') {
    $mapel    = trim($_POST['mapel'] ?? '');
    $kelas    = trim($_POST['kelas'] ?? 'VII');
    $fase     = trim($_POST['fase'] ?? 'D');
    $semester = $_POST['semester'] ?? 'Ganjil & Genap';
    $tahun    = trim($_POST['tahun_ajaran'] ?? $defaultTahun);
    $poinBab  = trim($_POST['poin_bab'] ?? '');

    if ($isGuruRole && !empty($mapelOptions) && !in_array($mapel, $mapelOptions)) {
        redirect('cp.php', 'danger', 'Anda hanya berhak menginput Perangkat Ajar untuk Mata Pelajaran yang Anda ampu.');
    }

    if ($mapel && $kelas && $poinBab) {
        try {
            $lines = array_filter(array_map('trim', explode("\n", $poinBab)));
            $topikUtama = !empty($lines) ? preg_replace('/^(bab\s*\d+\s*:?\s*|\d+\.\s*)/i', '', $lines[0]) : $mapel;
            $isAgama = (stripos($mapel, 'fiqih') !== false || stripos($mapel, 'agama') !== false || stripos($mapel, 'pai') !== false || stripos($mapel, 'al-qur') !== false);
            $isBahasa = (stripos($mapel, 'bahasa') !== false || stripos($mapel, 'indonesia') !== false || stripos($mapel, 'inggris') !== false || stripos($mapel, 'arab') !== false);

            $cpList = [];
            $tpList = [];
            $atpList = [];

            $num = 1;
            $tpVerbs = ['menganalisis dan memahami', 'menerapkan dan mendemonstrasikan', 'mengidentifikasi serta memecahkan masalah terkait', 'merefleksikan hikmah dan nilai kebaikan dari'];

            foreach ($lines as $idx => $line) {
                $cleanBab = preg_replace('/^(bab\s*\d+\s*:?\s*|\d+\.\s*)/i', '', $line);
                $verb = $tpVerbs[$idx % count($tpVerbs)];

                if ($isAgama) {
                    $cpList[] = "• " . $line . ": Memahami ketentuan syariat " . $cleanBab . ", meyakini landasan dalilnya, serta mampu mengamalkannya dalam ibadah dan perilaku akhlakul karimah sehari-hari.";
                    $tpList[] = "$num. Peserta didik mampu $verb " . $cleanBab . " secara tepat serta mengaitkannya dengan keteladanan sikap sehari-hari.";
                    $atpList[] = "Tahap $num: Mengkaji dalil, tata cara, dan hikmah " . $cleanBab . " melalui eksplorasi & simulasi praktis (4 JP)";
                } elseif ($isBahasa) {
                    $cpList[] = "• " . $line . ": Menganalisis struktur dan kebahasaan " . $cleanBab . ", menyerap gagasan secara kritis, serta menyajikan karya lisan maupun tulisan yang komunikatif.";
                    $tpList[] = "$num. Peserta didik mampu $verb " . $cleanBab . " serta menyusun produk teks/karya secara sistematis.";
                    $atpList[] = "Tahap $num: Membaca teks model, menganalisis struktur kebahasaan, dan memproduksi karya " . $cleanBab . " (4 JP)";
                } else {
                    $cpList[] = "• " . $line . ": Memahami prinsip mendasar " . $cleanBab . ", menganalisis permasalahan kontekstual, serta menerapkan solusinya secara kritis dan mandiri.";
                    $tpList[] = "$num. Peserta didik mampu $verb " . $cleanBab . " dalam menyelesaikan masalah kontekstual.";
                    $atpList[] = "Tahap $num: Pemahaman konsep dasar, studi kasus nyata, dan penyelesaian latihan " . $cleanBab . " (4 JP)";
                }
                $num++;
            }

            $cpTeks = "Pada akhir Fase $fase, peserta didik pada mata pelajaran $mapel memiliki kemampuan komprehensif untuk menguasai cakupan materi berikut:\n" . implode("\n", $cpList);
            $tpText = implode("\n", $tpList);
            $atpText = implode("\n", $atpList);

            // Generasi Narasi Modul Ajar Otentik (Human-like)
            if ($isAgama) {
                $kompetensiAwal = "Peserta didik telah memiliki pembiasaan ibadah harian dan pemahaman dasar mengenai norma keagamaan di rumah.";
                $pemahamanBermakna = "Mempelajari $topikUtama menanamkan kesadaran bahwa ketaatan dan kebersihan hati memberikan ketenangan hidup serta menyempurnakan kualitas ibadah harian.";
                $pertanyaanPemantik = "1. Mengapa materi $topikUtama sangat erat kaitannya dengan ibadah harian kita?\n2. Bagaimana perbedaan kualitas ibadah seseorang yang memahami tata cara materi ini secara benar dibandingkan yang asal-asalan?";
                $lkpdContent = "LKPD Pembiasaan & Praktik $topikUtama:\n1. Tuliskan 2 dalil atau aturan utama terkait $topikUtama.\n2. Diskusikan bersama kelompokmu solusi atas studi kasus harian yang disajikan di lembar kerja!";
                $glosarium = "$topikUtama: Pembahasan pokok dalam fikih/PAI.\nHikmah: Manfaat terdalam dari pelaksanaan aturan syariat.";
            } elseif ($isBahasa) {
                $kompetensiAwal = "Peserta didik mampu membaca lancar dan membedakan informasi fakta serta opini dalam kehidupan sehari-hari.";
                $pemahamanBermakna = "Mempelajari $topikUtama mengasah ketelitian bernalar kritis, kejujuran dalam menyampaikan fakta, serta keterampilan berkomunikasi secara santun.";
                $pertanyaanPemantik = "1. Apa yang membuat sebuah tulisan $topikUtama terasa menarik dan tepercaya bagi pembaca?\n2. Bagaimana cara kita menyampaikan informasi faktual tanpa mencampurkannya dengan asumsi pribadi?";
                $lkpdContent = "LKPD Analisis Teks $topikUtama:\n1. Bacalah kutipan teks yang tersedia, lalu identifikasi bagian struktur utamanya!\n2. Temukan 3 kata kunci dan buatlah kalimat efektif darinya!";
                $glosarium = "$topikUtama: Teks/karya yang fokus pada penyampaian gagasan terstruktur.\nStruktur Teks: Susunan pembangun teks secara utuh.";
            } else {
                $kompetensiAwal = "Peserta didik memahami pengetahuan dasar materi prasyarat dan memiliki rasa ingin tahu terhadap fenomena sekitar.";
                $pemahamanBermakna = "Penguasaan materi $topikUtama melatih pola pikir matematis/ilmiah untuk memecahkan masalah praktis yang dijumpai dalam kehidupan nyata.";
                $pertanyaanPemantik = "1. Di mana saja kita sering menjumpai penerapan $topikUtama dalam kehidupan sehari-hari?\n2. Strategi apa yang paling efisien untuk memecahkan soal/kasus terkait $topikUtama?";
                $lkpdContent = "LKPD Eksplorasi $topikUtama:\n1. Kerjakan 3 soal tantangan berbasis kasus kontekstual bersama kelompokmu!\n2. Tuliskan langkah penyelesaian secara runtut dan jelaskan alasan di setiap langkahnya!";
                $glosarium = "$topikUtama: Topik pembahasan utama.\nKonsep Kunci: Istilah penting yang melandasi pemecahan masalah.";
            }

            $modulData = [
                'nama_sekolah'         => $defaultNamaSekolah,
                'nama_guru'            => $_SESSION['nama_lengkap'],
                'nip_guru'             => '',
                'nama_kepsek'          => getSetting('nama_kepsek', 'Kepala Sekolah, M.Pd'),
                'nip_kepsek'           => getSetting('nip_kepsek', ''),
                'model_pembelajaran'   => 'Problem-Based Learning (PBL) & Diskusi Kelompok Interaktif',
                'profil_pancasila'     => ['Bernalar Kritis', 'Gotong Royong', 'Kreatif', 'Mandiri'],
                'kompetensi_awal'      => $kompetensiAwal,
                'sarana_prasarana'     => 'Buku Pegangan Guru & Siswa, Slide Presentasi/PPT, Proyektor, Kartu Kasus/LKPD.',
                'target_siswa'         => 'Peserta didik reguler / tipikal (28-32 siswa dalam rombel campuran)',
                'pemahaman_bermakna'   => $pemahamanBermakna,
                'pertanyaan_pemantik'  => $pertanyaanPemantik,
                'kegiatan_pendahuluan' => "1. Guru mengucap salam hangat, mengajak berdoa bersama, dan menyapa presensi siswa.\n2. Apersepsi: Guru mengaitkan materi $topikUtama dengan pengalaman nyata siswa.\n3. Guru menyampaikan tujuan pembelajaran dan gambaran aktivitas yang akan dilakukan.",
                'kegiatan_inti'        => "1. Orientasi: Siswa mencermati tayangan visual / studi kasus terkait materi $topikUtama.\n2. Mengorganisasi: Siswa dibagi dalam kelompok heterogen (4-5 orang) untuk mendiskusikan lembar kerja.\n3. Membimbing: Guru berkeliling memberikan scafolding pada kelompok yang membutuhkan masukan.\n4. Mengembangkan: Setiap kelompok menyusun dan menyajikan hasil diskusinya di depan kelas.\n5. Evaluasi: Guru dan antar-siswa memberikan apresiasi serta klarifikasi pemahaman.",
                'kegiatan_penutup'     => "1. Siswa merangkum poin-poin penting materi $topikUtama dibimbing oleh guru.\n2. Refleksi singkat: Siswa mengungkapkan apa yang sudah dipahami dan apa yang masih perlu diperdalam.\n3. Guru menyampaikan penugasan pembiasaan mandiri dan menutup kelas dengan doa.",
                'asesmen_diagnostik'   => 'Tanya jawab lisan apersepsi di awal pertemuan untuk memetakan pemahaman awal siswa.',
                'asesmen_formatif'     => 'Observasi keaktifan diskusi kelompok, penilaian kinerjanya pada LKPD, dan presentasi.',
                'asesmen_sumatif'      => 'Tes tertulis pilihan ganda beralasan & soal uraian berbasis pemecahan masalah di akhir topik.',
                'lkpd_content'         => $lkpdContent,
                'bahan_bacaan'         => 'Buku Pegangan Siswa & Artikel Pendukung yang Relevan',
                'glosarium'            => $glosarium,
                'daftar_pustaka'       => "1. Kemendikbudristek. (2022). Buku Panduan Guru & Siswa $mapel Kelas $kelas. Jakarta.\n2. Referensi Pendukung Kurikulum Merdeka.",
                'poin_bab_raw'         => $poinBab
            ];

            $stmtInsert = $pdo->prepare("
                INSERT INTO perangkat_ajar 
                (user_id, mapel, kelas, fase, semester, tahun_ajaran, topik, elemen, alokasi_waktu, capaian_pembelajaran, tujuan_pembelajaran, alur_tujuan_pembelajaran, modul_ajar_json)
                VALUES 
                (:uid, :mapel, :kelas, :fase, :sem, :th, :topik, :elem, :alokasi, :cp, :tp, :atp, :json)
            ");
            $stmtInsert->execute([
                ':uid'     => $userId,
                ':mapel'   => $mapel,
                ':kelas'   => $kelas,
                ':fase'    => $fase,
                ':sem'     => $semester,
                ':th'      => $tahun,
                ':topik'   => $topikUtama,
                ':elem'    => 'Pemahaman & Penerapan ' . $mapel,
                ':alokasi' => '2 JP x 40 Menit',
                ':cp'      => $cpTeks,
                ':tp'      => $tpText,
                ':atp'     => $atpText,
                ':json'    => json_encode($modulData, JSON_UNESCAPED_UNICODE)
            ]);

            redirect('cp.php', 'success', '✨ Berhasil! Dokumen CP, TP, ATP & Modul Ajar telah di-generate secara otomatis.');
        } catch (PDOException $e) {
            redirect('cp.php', 'danger', 'Gagal menyimpan: ' . $e->getMessage());
        }
    } else {
        redirect('cp.php', 'warning', 'Mohon lengkapi Mata Pelajaran, Kelas, dan Poin-Poin Bab Pembahasan.');
    }
}

// Fetch list CP
if ($userRole === 'admin') {
    $stmtCP = $pdo->query("
        SELECT p.*, u.nama_lengkap AS nama_guru 
        FROM perangkat_ajar p 
        LEFT JOIN users u ON p.user_id = u.id 
        ORDER BY p.updated_at DESC
    ");
} else {
    $stmtCP = $pdo->prepare("
        SELECT p.*, u.nama_lengkap AS nama_guru 
        FROM perangkat_ajar p 
        LEFT JOIN users u ON p.user_id = u.id 
        WHERE p.user_id = :uid OR p.user_id IS NOT NULL
        ORDER BY (p.user_id = :uid2) DESC, p.updated_at DESC
    ");
    $stmtCP->execute([':uid' => $userId, ':uid2' => $userId]);
}
$listCP = $stmtCP->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/subnav.php';
?>

<style>
    .cp-hero {
        background: linear-gradient(135deg, #06b6d4 0%, #0891b2 50%, #0e7490 100%);
        border-radius: 1.25rem;
        color: #fff;
        position: relative;
        overflow: hidden;
    }
    .cp-hero::before {
        content: '';
        position: absolute;
        top: -40%; right: -20%;
        width: 350px; height: 350px;
        background: rgba(255,255,255,0.12);
        border-radius: 50%;
        filter: blur(40px);
        pointer-events: none;
    }
    .preset-chip {
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid rgba(16, 185, 129, 0.3);
        background: #f0fdf4;
        color: #166534;
        font-weight: 600;
        font-size: 0.8rem;
    }
    .preset-chip:hover {
        background: #10b981;
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
    }
    .cp-card-preview {
        border-radius: 1rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .cp-card-preview:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 28px rgba(0,0,0,0.08)!important;
    }
</style>

<!-- HERO HEADER -->
<div class="cp-hero p-4 p-md-5 mb-4 shadow-sm">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 position-relative" style="z-index: 2;">
        <div>
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px);">
                <i class="bi bi-journal-text fs-6"></i>
                <span class="small fw-bold text-uppercase" style="letter-spacing: 1px;">MODUL CAPAIAN PEMBELAJARAN (CP)</span>
            </div>
            <h3 class="fw-extrabold mb-1">Capaian Pembelajaran (CP) Kurikulum Merdeka</h3>
            <p class="opacity-90 small mb-0 fs-6">Guru cukup memasukkan poin-poin bab pembahasan. AI akan meng-generate CP, TP, ATP, dan Modul Ajar secara otomatis.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="identitas.php" class="btn btn-light fw-bold text-dark px-3 py-2.5 rounded-3 shadow-sm">
                <i class="bi bi-person-badge me-1"></i> Identitas Modul
            </a>
            <a href="tp.php" class="btn btn-outline-light fw-bold px-3 py-2.5 rounded-3">
                <i class="bi bi-bullseye me-1"></i> Modul TP & ATP
            </a>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- FORM SUPER SIMPEL INPUT BAB -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header p-3.5 rounded-top-4 border-0" style="background: linear-gradient(135deg, #059669, #047857) !important; color: #ffffff !important;">
                <h5 class="fw-bold m-0 text-white"><i class="bi bi-magic me-2"></i> Form Simpel Guru (Poin Bab)</h5>
                <small class="text-white opacity-90 d-block mt-1">Ketikkan judul bab / poin pembahasan materi yang diampu.</small>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="cp.php">
                    <input type="hidden" name="action" value="save_cp">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-7">Mata Pelajaran <span class="text-danger">*</span></label>
                        <select name="mapel" id="selectMapel" class="form-select border-2" required>
                            <?php if (empty($mapelOptions)): ?>
                                <option value="Fiqih">Fiqih</option>
                                <option value="Pendidikan Agama Islam">Pendidikan Agama Islam</option>
                                <option value="Bahasa Indonesia">Bahasa Indonesia</option>
                            <?php else: ?>
                                <?php foreach ($mapelOptions as $mOpt): ?>
                                    <option value="<?= htmlspecialchars($mOpt) ?>"><?= htmlspecialchars($mOpt) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php if ($isGuruRole): ?>
                            <small class="text-muted"><i class="bi bi-shield-lock me-1"></i>Mapel terkunci sesuai penugasan Anda.</small>
                        <?php endif; ?>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold text-dark fs-7">Kelas <span class="text-danger">*</span></label>
                            <select name="kelas" class="form-select border-2" required>
                                <option value="VII">Kelas VII (Tujuh)</option>
                                <option value="VIII">Kelas VIII (Delapan)</option>
                                <option value="IX">Kelas IX (Sembilan)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold text-dark fs-7">Fase Kurikulum</label>
                            <input type="text" name="fase" class="form-control border-2" value="D" readonly>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold text-dark fs-7">Semester</label>
                            <select name="semester" class="form-select border-2">
                                <option value="Ganjil & Genap">Ganjil & Genap</option>
                                <option value="Ganjil">Ganjil saja</option>
                                <option value="Genap">Genap saja</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold text-dark fs-7">Tahun Ajaran</label>
                            <input type="text" name="tahun_ajaran" class="form-control border-2" value="<?= htmlspecialchars($defaultTahun) ?>">
                        </div>
                    </div>

                    <!-- CHIP PRESET MATERI INTEGRATED -->
                    <div class="mb-2">
                        <label class="form-label fw-bold text-dark fs-7 mb-1"><i class="bi bi-lightning-charge-fill text-warning me-1"></i> Klik Contoh Preset Bab (Opsional):</label>
                        <div class="d-flex flex-wrap gap-1.5 mb-2">
                            <span class="badge rounded-pill preset-chip px-2.5 py-1.5" onclick="setPreset('Fiqih', 'Bab 1: Thoharoh (Bersuci dari Hadats dan Najasah)\nBab 2: Haidh dan Najasah\nBab 3: Sholat Fardhu dan Sunnah\nBab 4: Zakat dan Puasa')">
                                🕌 Fiqih VII
                            </span>
                            <span class="badge rounded-pill preset-chip px-2.5 py-1.5" onclick="setPreset('Pendidikan Agama Islam', 'Bab 1: Al-Qur\'an dan Sunah Pedoman Hidup\nBab 2: Meneladan Nama dan Sifat Allah\nBab 3: Menghadirkan Salat dan Zikir\nBab 4: Sujud Sahwi dan Tilawah')">
                                📖 PAI VII
                            </span>
                            <span class="badge rounded-pill preset-chip px-2.5 py-1.5" onclick="setPreset('Bahasa Indonesia', 'Bab 1: Teks Laporan Hasil Observasi (LHO)\nBab 2: Menyajikan Puisi Rakyat & Pantun\nBab 3: Menggali Nilai Teks Prosedur\nBab 4: Mengulas Cerita Fantasi')">
                                📚 Bahasa Indonesia VII
                            </span>
                        </div>
                    </div>

                    <div class="mb-3 p-3 rounded-3" style="background: #f8fafc; border: 1.5px dashed #10b981;">
                        <label class="form-label fw-bold text-dark fs-7 mb-1"><i class="bi bi-pencil-square text-success me-1"></i> Poin-Poin Bab Pembahasan Materi <span class="text-danger">*</span></label>
                        <textarea name="poin_bab" id="textareaBab" class="form-control border-2 font-monospace fs-7" rows="6" placeholder="Bab 1: Thoharoh (Bersuci)&#10;Bab 2: Haidh dan Najasah&#10;Bab 3: Sholat Fardhu dan Sunnah&#10;Bab 4: Zakat dan Puasa" required></textarea>
                    </div>

                    <button type="submit" class="btn btn-emerald w-100 py-3 fw-bold shadow-sm text-white" style="background: #10b981; border: none; font-size: 1rem;">
                        <i class="bi bi-magic me-2"></i> ✨ Auto-Generate CP, TP, ATP & Modul Ajar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- TABEL & PRATINJAU DOKUMEN CP -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white p-3.5 border-bottom d-flex align-items-center justify-content-between">
                <h5 class="fw-bold m-0 text-dark"><i class="bi bi-collection-fill text-primary me-2"></i> Daftar Perangkat Capaian Pembelajaran (CP)</h5>
                <span class="badge bg-primary rounded-pill px-3 py-2"><?= count($listCP) ?> Dokumen</span>
            </div>
            <div class="card-body p-3">
                <?php if (empty($listCP)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-journal-x display-4 d-block mb-3 opacity-50"></i>
                        <h6>Belum ada Dokumen Perangkat Ajar.</h6>
                        <p class="small">Ketikkan daftar Bab di samping atau klik preset untuk meng-generate dokumen pertama Anda.</p>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($listCP as $cpItem): ?>
                            <div class="card cp-card-preview border border-light-subtle shadow-sm p-3">
                                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
                                    <div>
                                        <span class="badge bg-primary bg-opacity-10 text-primary fw-bold px-2.5 py-1 rounded-pill mb-1">
                                            <?= htmlspecialchars($cpItem['mapel']) ?>
                                        </span>
                                        <span class="badge bg-secondary bg-opacity-10 text-dark fw-bold px-2 py-1 rounded-pill">
                                            Kelas <?= htmlspecialchars($cpItem['kelas']) ?> (Fase <?= htmlspecialchars($cpItem['fase']) ?>)
                                        </span>
                                        <h5 class="fw-bold text-dark mt-1 mb-0"><?= htmlspecialchars($cpItem['topik']) ?></h5>
                                    </div>
                                    <div class="btn-group">
                                        <a href="print.php?doc_type=cp&id=<?= $cpItem['id'] ?>" target="_blank" class="btn btn-sm btn-danger fw-bold px-2.5">
                                            <i class="bi bi-file-earmark-pdf-fill me-1"></i> PDF
                                        </a>
                                        <a href="tp.php?cp_id=<?= $cpItem['id'] ?>" class="btn btn-sm btn-outline-primary fw-bold px-2.5">
                                            <i class="bi bi-arrow-right-circle me-1"></i> TP & ATP
                                        </a>
                                        <a href="edit.php?id=<?= $cpItem['id'] ?>" class="btn btn-sm btn-outline-warning text-dark" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="delete.php?id=<?= $cpItem['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus dokumen Perangkat Ajar ini secara permanen?');" title="Hapus">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="small text-muted p-2.5 rounded-3 bg-light border text-truncate" style="max-height: 70px;">
                                    <i class="bi bi-text-paragraph text-primary me-1"></i>
                                    <?= htmlspecialchars(mb_strimwidth(str_replace("\n", " • ", $cpItem['capaian_pembelajaran']), 0, 140, '...')) ?>
                                </div>
                                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top fs-7 text-muted">
                                    <span><i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($cpItem['nama_guru'] ?? '-') ?></span>
                                    <span><i class="bi bi-clock-history me-1"></i><?= date('d M Y H:i', strtotime($cpItem['updated_at'])) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function setPreset(mapel, textBab) {
    var selectMapel = document.getElementById('selectMapel');
    var textareaBab = document.getElementById('textareaBab');
    
    // Set option mapel jika ada
    for (var i = 0; i < selectMapel.options.length; i++) {
        if (selectMapel.options[i].value.toLowerCase().includes(mapel.toLowerCase())) {
            selectMapel.selectedIndex = i;
            break;
        }
    }
    textareaBab.value = textBab;
}

function confirmDelete(id) {
    if (confirm('Apakah Anda yakin ingin menghapus dokumen Perangkat Ajar ini secara permanen? Seluruh data CP, TP, ATP, dan Modul Ajar terkait akan hilang.')) {
        window.location.href = 'delete.php?id=' + id;
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
