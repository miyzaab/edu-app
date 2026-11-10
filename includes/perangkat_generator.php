<?php
/**
 * PERANGKAT AJAR KURIKULUM MERDEKA - GENERATOR ENGINE (HUMAN-LIKE PEDAGOGY)
 * Menghasilkan narasi otentik berstandar Kurikulum Merdeka Kemendikbudristek & Kemenag
 * Mendukung Mapel Umum, Eksakta, Bahasa, Sosial, dan Mapel Diniyah/Pesantren.
 */

class PerangkatGenerator
{
    /**
     * Deteksi Kategori Mapel
     */
    public static function detectKategoriMapel(string $mapel): string
    {
        $m = strtolower(trim($mapel));

        // Diniyah / Pesantren
        $diniyahKeywords = [
            'fiqih', 'fikih', 'aqidah', 'akidah', 'akhlak', 'tauhid', 'hadits', 'hadis',
            'al-qur', 'quran', 'tajwid', 'tahfidz', 'tahfiz', 'nahwu', 'sharaf', 'shorof',
            'muhadatsah', 'tarikh', 'ski', 'pai', 'agama islam', 'ushul fiqih', 'imrithi',
            'safinah', 'jurumiyah', 'bayan', 'tafsir'
        ];
        foreach ($diniyahKeywords as $k) {
            if (stripos($m, $k) !== false) return 'diniyah';
        }

        // Bahasa
        if (stripos($m, 'indonesia') !== false || stripos($m, 'inggris') !== false || stripos($m, 'english') !== false || stripos($m, 'arab') !== false || stripos($m, 'bahasa') !== false) {
            return 'bahasa';
        }

        // Eksakta & Sains
        if (stripos($m, 'matematika') !== false || stripos($m, 'ipa') !== false || stripos($m, 'sains') !== false || stripos($m, 'fisika') !== false || stripos($m, 'biologi') !== false || stripos($m, 'it') !== false || stripos($m, 'komputer') !== false || stripos($m, 'informatika') !== false) {
            return 'eksakta';
        }

        // Sosial & Humaniora
        if (stripos($m, 'ips') !== false || stripos($m, 'pkn') !== false || stripos($m, 'pancasila') !== false || stripos($m, 'sejarah') !== false || stripos($m, 'geografi') !== false || stripos($m, 'sosiologi') !== false) {
            return 'sosial';
        }

        // Olahraga / Jasmani
        if (stripos($m, 'pjok') !== false || stripos($m, 'olahraga') !== false || stripos($m, 'penjas') !== false) {
            return 'olahraga';
        }

        // Seni
        if (stripos($m, 'seni') !== false || stripos($m, 'prakarya') !== false || stripos($m, 'sbdp') !== false) {
            return 'seni';
        }

        return 'umum';
    }

    /**
     * Membersihkan nama bab dari prefix seperti "Bab 1:", "1.", dll.
     */
    public static function cleanBabName(string $rawBab): string
    {
        $clean = trim($rawBab);
        $clean = preg_replace('/^(bab\s*([0-9]+|[ivxlcdm]+)\s*[:\-\.]?\s*|\d+[\.\)]\s*)/iu', '', $clean);
        return trim($clean);
    }

    /**
     * Memecah string daftar bab menjadi array
     */
    public static function parseBabText(string $text): array
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
        $result = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $result[] = $line;
            }
        }
        return $result;
    }

    /**
     * Generator Utama Perangkat Ajar 1 Tahun Ajaran Lengkap
     */
    public static function generatePerangkatLengkap(array $params): array
    {
        $mapel        = trim($params['mapel'] ?? 'Mata Pelajaran');
        $kelas        = trim($params['kelas'] ?? 'VII');
        $fase         = 'D'; // SMP/MTs (Kelas VII, VIII, IX) selalu Fase D
        $jpPerPekan   = max(1, min(10, (int)($params['jp_per_pekan'] ?? 4)));
        $tahunAjaran  = trim($params['tahun_ajaran'] ?? (date('Y') . '/' . (date('Y') + 1)));
        $namaSekolah  = trim($params['nama_sekolah'] ?? 'Sekolah / Madrasah');
        $namaGuru     = trim($params['nama_guru'] ?? 'Guru Mata Pelajaran');
        $nipGuru      = trim($params['nip_guru'] ?? '—');
        $namaKepsek   = trim($params['nama_kepsek'] ?? 'Kepala Sekolah, M.Pd');
        $nipKepsek    = trim($params['nip_kepsek'] ?? '—');

        $babRawSmt1   = trim($params['bab_semester_1'] ?? '');
        $babRawSmt2   = trim($params['bab_semester_2'] ?? '');

        $kategori     = self::detectKategoriMapel($mapel);

        $babListSmt1  = self::parseBabText($babRawSmt1);
        $babListSmt2  = self::parseBabText($babRawSmt2);

        // Jika salah satu kosong, buat fallback bab yang relevan
        if (empty($babListSmt1)) {
            $babListSmt1 = ["Bab 1: Konsep Dasar " . $mapel, "Bab 2: Penerapan & Latihan " . $mapel];
        }
        if (empty($babListSmt2)) {
            $babListSmt2 = ["Bab 3: Pendalaman Materi " . $mapel, "Bab 4: Evaluasi & Proyek " . $mapel];
        }

        // Alokasi pekan standar
        $pekanSmt1 = 18;
        $pekanSmt2 = 16;
        $totalJpSmt1 = $pekanSmt1 * $jpPerPekan;
        $totalJpSmt2 = $pekanSmt2 * $jpPerPekan;

        // Proses Bab Semester 1
        $processedSmt1 = self::processSemesterBab($babListSmt1, 1, $pekanSmt1, $jpPerPekan, $mapel, $kelas, $fase, $kategori, $namaSekolah, $namaGuru, $namaKepsek, $nipGuru, $nipKepsek);

        // Proses Bab Semester 2
        $processedSmt2 = self::processSemesterBab($babListSmt2, 2, $pekanSmt2, $jpPerPekan, $mapel, $kelas, $fase, $kategori, $namaSekolah, $namaGuru, $namaKepsek, $nipGuru, $nipKepsek, count($babListSmt1));

        // Gabungkan CP, TP, dan ATP Teks Menyeluruh
        $allCpList  = array_merge($processedSmt1['cp_lines'], $processedSmt2['cp_lines']);
        $allTpList  = array_merge($processedSmt1['tp_lines'], $processedSmt2['tp_lines']);
        $allAtpList = array_merge($processedSmt1['atp_lines'], $processedSmt2['atp_lines']);

        $cpIntroduction = self::getCpNaration($mapel, $kelas, $fase, $kategori);
        $fullCpText  = $cpIntroduction . "\n\n" . implode("\n", $allCpList);
        $fullTpText  = implode("\n", $allTpList);
        $fullAtpText = implode("\n", $allAtpList);

        $topikUtama = "Perangkat Ajar {$mapel} Kelas {$kelas} Fase D (1 Tahun Ajaran)";
        $alokasiSummary = "{$jpPerPekan} JP/Pekan (Total " . ($totalJpSmt1 + $totalJpSmt2) . " JP/Tahun)";

        $modulJsonData = [
            'nama_sekolah'   => $namaSekolah,
            'nama_guru'      => $namaGuru,
            'nip_guru'       => $nipGuru,
            'nama_kepsek'    => $namaKepsek,
            'nip_kepsek'     => $nipKepsek,
            'mapel'          => $mapel,
            'kelas'          => $kelas,
            'fase'           => $fase,
            'jp_per_pekan'   => $jpPerPekan,
            'tahun_ajaran'   => $tahunAjaran,
            'kategori_mapel' => $kategori,
            'total_jp_tahun' => $totalJpSmt1 + $totalJpSmt2,
            'cp_global'      => $cpIntroduction,
            'semester_1'     => [
                'nama'        => 'Semester Ganjil (1)',
                'pekan'       => $pekanSmt1,
                'total_jp'    => $totalJpSmt1,
                'bab_list'    => $processedSmt1['bab_items']
            ],
            'semester_2'     => [
                'nama'        => 'Semester Genap (2)',
                'pekan'       => $pekanSmt2,
                'total_jp'    => $totalJpSmt2,
                'bab_list'    => $processedSmt2['bab_items']
            ]
        ];

        return [
            'topik'                    => $topikUtama,
            'mapel'                    => $mapel,
            'kelas'                    => $kelas,
            'fase'                     => $fase,
            'semester'                 => '1 Tahun (Ganjil & Genap)',
            'tahun_ajaran'             => $tahunAjaran,
            'elemen'                   => self::getElemenUtama($mapel, $kategori),
            'alokasi_waktu'            => $alokasiSummary,
            'jp_per_pekan'             => $jpPerPekan,
            'capaian_pembelajaran'     => $fullCpText,
            'tujuan_pembelajaran'      => $fullTpText,
            'alur_tujuan_pembelajaran' => $fullAtpText,
            'modul_ajar_json'          => json_encode($modulJsonData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'modul_array'              => $modulJsonData
        ];
    }

    /**
     * Memproses bab-bab dalam 1 semester menjadi paket modul dan ATP
     */
    private static function processSemesterBab(
        array $rawBabs,
        int $semesterNum,
        int $totalPekan,
        int $jpPerPekan,
        string $mapel,
        string $kelas,
        string $fase,
        string $kategori,
        string $namaSekolah,
        string $namaGuru,
        string $namaKepsek,
        string $nipGuru,
        string $nipKepsek,
        int $startIndex = 0
    ): array {
        $count = count($rawBabs);
        $totalJp = $totalPekan * $jpPerPekan;

        // Distribusi JP per bab secara berimbang
        $baseJp = intval(floor($totalJp / $count));
        $remainderJp = $totalJp % $count;

        $babItems = [];
        $cpLines  = [];
        $tpLines  = [];
        $atpLines = [];

        foreach ($rawBabs as $idx => $rawBab) {
            $globalNum = $startIndex + $idx + 1;
            $cleanBab = self::cleanBabName($rawBab);
            
            // Hitung alokasi JP untuk bab ini
            $babJp = $baseJp + ($idx === 0 ? $remainderJp : 0);
            $pertemuan = max(1, intval(round($babJp / $jpPerPekan)));

            // Buat CP per bab
            $cpBab = self::generateCpBab($cleanBab, $mapel, $kategori, $fase);
            $cpLines[] = "• Bab {$globalNum} ({$cleanBab}): {$cpBab}";

            // Buat TP per bab (3-4 butir taksonomi operasional)
            $tpItems = self::generateTpBab($cleanBab, $globalNum, $mapel, $kategori);
            foreach ($tpItems as $tp) {
                $tpLines[] = $tp;
            }

            // Buat ATP per bab
            $atpBab = self::generateAtpBab($cleanBab, $globalNum, $babJp, $pertemuan, $kategori);
            $atpLines[] = $atpBab;

            // Generate Modul Ajar spesifik untuk Bab ini
            $modulBab = self::generateModulBab(
                $cleanBab,
                $globalNum,
                $semesterNum,
                $babJp,
                $pertemuan,
                $jpPerPekan,
                $mapel,
                $kelas,
                $fase,
                $kategori,
                $namaSekolah,
                $namaGuru,
                $namaKepsek,
                $nipGuru,
                $nipKepsek,
                $tpItems
            );

            $babItems[] = [
                'nomor'            => $globalNum,
                'judul_lengkap'    => $rawBab,
                'judul_bersih'     => $cleanBab,
                'semester'         => $semesterNum,
                'alokasi_jp'       => $babJp,
                'jumlah_pertemuan' => $pertemuan,
                'cp_teks'          => $cpBab,
                'tp_list'          => $tpItems,
                'atp_teks'         => $atpBab,
                'modul'            => $modulBab
            ];
        }

        return [
            'bab_items' => $babItems,
            'cp_lines'  => $cpLines,
            'tp_lines'  => $tpLines,
            'atp_lines' => $atpLines
        ];
    }

    /**
     * Narasi Capaian Pembelajaran (CP) Global
     */
    private static function getCpNaration(string $mapel, string $kelas, string $fase, string $kategori): string
    {
        switch ($kategori) {
            case 'diniyah':
                return "Pada akhir Fase {$fase} (Kelas {$kelas}), peserta didik memiliki kemampuan komprehensif dalam mengkaji, memahami, dan meyakini prinsip-prinsip syariat pada mata pelajaran {$mapel}. Peserta didik mampu merujuk dalil naqli secara kontekstual, mempraktikkan tata cara peribadatan dan kaidah keilmuan secara tertib sesuai tuntunan syar'i, serta menumbuhkan adab dan keteladanan akhlakul karimah dalam kehidupan sehari-hari di lingkungan pesantren/madrasah dan masyarakat.";

            case 'bahasa':
                return "Pada akhir Fase {$fase} (Kelas {$kelas}), peserta didik memiliki kemampuan berbahasa yang santun, kritis, dan reflektif dalam mata pelajaran {$mapel}. Peserta didik mampu menyimak gagasan pokok, memirsa dan membaca teks multimodal secara analitis, menyampaikan gagasan secara lisan melalui diskusi dan presentasi yang terstruktur, serta memproduksi karya tulis fungsional maupun sastra yang komunikatif sesuai kaidah kebahasaan.";

            case 'eksakta':
                return "Pada akhir Fase {$fase} (Kelas {$kelas}), peserta didik memiliki keterampilan bernalar kritis, matematis, dan saintifik dalam mata pelajaran {$mapel}. Peserta didik mampu mengidentifikasi fakta dan prinsip, memodelkan permasalahan nyata ke dalam representasi logis, melakukan penyelidikan atau perhitungan secara teliti, serta menyimpulkan dan mengomunikasikan pemecahan masalah kontekstual dengan argumentasi yang runtut.";

            case 'sosial':
                return "Pada akhir Fase {$fase} (Kelas {$kelas}), peserta didik memahami hubungan dinamis antara individu, masyarakat, dan lingkungannya dalam mata pelajaran {$mapel}. Peserta didik mampu menelaah peristiwa sejarah, kondisi keruangan, serta pranata sosial dan norma hukum secara bijaksana, sekaligus mengaktualisasikan semangat kebinekaan, cinta tanah air, dan kepedulian sosial dalam kehidupan berbangsa.";

            case 'olahraga':
                return "Pada akhir Fase {$fase} (Kelas {$kelas}), peserta didik mampu mempraktikkan variasi dan kombinasi pola gerak dasar secara terampil pada mata pelajaran {$mapel}. Peserta didik memahami prinsip kebugaran jasmani, menjunjung tinggi sportivitas, disiplin, kerja sama tim, serta menanamkan pola hidup sehat secara konsisten dalam rutinitas harian.";

            default:
                return "Pada akhir Fase {$fase} (Kelas {$kelas}), peserta didik menguasai konsep-konsep esensial pada mata pelajaran {$mapel}, mampu menghubungkan teori dengan penerapan praktis, serta mengembangkan keterampilan bernalar kritis, mandiri, dan bergotong royong dalam menyelesaikan masalah kontekstual di lingkungan sekitar.";
        }
    }

    /**
     * Rumusan CP Khusus Per Bab
     */
    private static function generateCpBab(string $cleanBab, string $mapel, string $kategori, string $fase): string
    {
        switch ($kategori) {
            case 'diniyah':
                return "Peserta didik mampu memahami ketentuan syariat, dalil naqli, dan hikmah materi {$cleanBab}, serta mampu mendemonstrasikan penerapannya secara istiqamah dengan berlandaskan adab dan keteladanan islami.";
            case 'bahasa':
                return "Peserta didik mampu menganalisis struktur gagasan dan kaidah kebahasaan pada materi {$cleanBab}, mengevaluasi pesan tersurat maupun tersirat, serta memproduksi karya lisan atau tulisan yang orisinal dan komunikatif.";
            case 'eksakta':
                return "Peserta didik mampu memahami konsep dasar {$cleanBab}, mengidentifikasi hubungan antarvariabel, dan menerapkan strategi pemecahan masalah kontekstual secara sistematis dan terukur.";
            case 'sosial':
                return "Peserta didik mampu menelaah fakta, konsep, dan dampak sosial-lingkungan terkait {$cleanBab}, serta menunjukkan sikap empati, kepedulian, dan tanggung jawab sosial.";
            default:
                return "Peserta didik mampu menguasai prinsip mendasar {$cleanBab}, menganalisis contoh kasus nyata, dan mengaplikasikan pengetahuannya secara mandiri serta kolaboratif.";
        }
    }

    /**
     * Rumusan Tujuan Pembelajaran (TP) Taksonomi Bloom Bertingkat (Otentik Manusia)
     */
    private static function generateTpBab(string $cleanBab, int $babNum, string $mapel, string $kategori): array
    {
        $tp = [];

        switch ($kategori) {
            case 'diniyah':
                $tp[] = "{$babNum}.1 Peserta didik mampu menjelaskan pengertian, hukum, rukun, dan dalil pokok terkait materi {$cleanBab} dengan tepat.";
                $tp[] = "{$babNum}.2 Peserta didik mampu mengidentifikasi syarat sah, tata cara, dan hikmah pelaksanaan {$cleanBab} dalam bingkai syariat Islam.";
                $tp[] = "{$babNum}.3 Peserta didik mampu mendemonstrasikan / menerapkan kaidah {$cleanBab} secara benar melalui simulasi dan pembiasaan terarah.";
                $tp[] = "{$babNum}.4 Peserta didik mampu merefleksikan nilai-nilai keteladanan dari {$cleanBab} dalam menjaga adab santri dan ketundukan ibadah sehari-hari.";
                break;

            case 'bahasa':
                $tp[] = "{$babNum}.1 Peserta didik mampu mengidentifikasi informasi faktual, ide pokok, dan tujuan komunikasi dari teks materi {$cleanBab}.";
                $tp[] = "{$babNum}.2 Peserta didik mampu menganalisis struktur pembangun dan unsur kebahasaan khas pada materi {$cleanBab} secara kritis.";
                $tp[] = "{$babNum}.3 Peserta didik mampu menyusun draf karya tulis / lisan terkait {$cleanBab} dengan pilihan kata yang efektif dan santun.";
                $tp[] = "{$babNum}.4 Peserta didik mampu menyajikan hasil karya {$cleanBab} di hadapan rekan sejawat serta saling memberikan apresiasi dan tanggapan konstruktif.";
                break;

            case 'eksakta':
                $tp[] = "{$babNum}.1 Peserta didik mampu memahami definisi, sifat-sifat matematis/ilmiah, dan konsep dasar dari materi {$cleanBab}.";
                $tp[] = "{$babNum}.2 Peserta didik mampu melakukan operasi perhitungan / prosedur penyelidikan terkait materi {$cleanBab} secara teliti.";
                $tp[] = "{$babNum}.3 Peserta didik mampu memodelkan dan menyelesaikan persoalan kontekstual sehari-hari yang melibatkan materi {$cleanBab}.";
                $tp[] = "{$babNum}.4 Peserta didik mampu mengomunikasikan runtutan langkah penyelesaian dan argumen logis dari solusi {$cleanBab} yang diperoleh.";
                break;

            case 'sosial':
                $tp[] = "{$babNum}.1 Peserta didik mampu menjelaskan latar belakang, konsep utama, dan kronologi fenomena terkait materi {$cleanBab}.";
                $tp[] = "{$babNum}.2 Peserta didik mampu menganalisis faktor penyebab, dinamika, dan dampak materi {$cleanBab} bagi kehidupan bermasyarakat.";
                $tp[] = "{$babNum}.3 Peserta didik mampu mengaitkan materi {$cleanBab} dengan peristiwa kekinian di lingkungan sekitar secara objektif.";
                $tp[] = "{$babNum}.4 Peserta didik mampu merumuskan gagasan solusi atau aksi nyata yang mencerminkan sikap gotong royong dan kepedulian sosial.";
                break;

            default:
                $tp[] = "{$babNum}.1 Peserta didik mampu mengenali konsep dasar, istilah kunci, dan prinsip utama pada materi {$cleanBab}.";
                $tp[] = "{$babNum}.2 Peserta didik mampu menjelaskan hubungan antarkonsep serta prosedur pelaksanaan terkait materi {$cleanBab}.";
                $tp[] = "{$babNum}.3 Peserta didik mampu mengaplikasikan pemahaman {$cleanBab} dalam menyelesaikan studi kasus dan tugas kelompok.";
                $tp[] = "{$babNum}.4 Peserta didik mampu menyimpulkan poin pembelajaran penting materi {$cleanBab} dan melakukan evaluasi diri secara mandiri.";
                break;
        }

        return $tp;
    }

    /**
     * Rumusan Alur Tujuan Pembelajaran (ATP)
     */
    private static function generateAtpBab(string $cleanBab, int $babNum, int $jp, int $pertemuan, string $kategori): string
    {
        switch ($kategori) {
            case 'diniyah':
                return "Tahap {$babNum}: Kajian dalil naqli & makna materi {$cleanBab} -> Analisis kaidah dan hikmah ibadah -> Praktik simulasi & evaluasi keteladanan santri ({$jp} JP / {$pertemuan} Pertemuan)";
            case 'bahasa':
                return "Tahap {$babNum}: Dekonstruksi teks model {$cleanBab} -> Analisis struktur & kaidah kebahasaan -> Konstruksi terbimbing dan penyajian karya mandiri ({$jp} JP / {$pertemuan} Pertemuan)";
            case 'eksakta':
                return "Tahap {$babNum}: Orientasi fenomena kontekstual {$cleanBab} -> Eksplorasi konsep dan penemuan rumus -> Latihan pemecahan masalah & asesmen ({$jp} JP / {$pertemuan} Pertemuan)";
            default:
                return "Tahap {$babNum}: Pemahaman konsep dasar {$cleanBab} -> Diskusi studi kasus kontekstual -> Presentasi hasil diskusi & asesmen sumatif ({$jp} JP / {$pertemuan} Pertemuan)";
        }
    }

    /**
     * Generator Modul Ajar Manusiawi per Bab
     */
    private static function generateModulBab(
        string $cleanBab,
        int $babNum,
        int $semesterNum,
        int $babJp,
        int $pertemuan,
        int $jpPerPekan,
        string $mapel,
        string $kelas,
        string $fase,
        string $kategori,
        string $namaSekolah,
        string $namaGuru,
        string $namaKepsek,
        string $nipGuru,
        string $nipKepsek,
        array $tpItems
    ): array {
        // Model Pembelajaran yang Tepat Sesuai Karakteristik
        if ($kategori === 'diniyah') {
            $modelPembelajaran = 'Direct Instruction, Simulasi Praktik Amaliah, & Diskusi Halaqah';
            $profilPancasila   = ['Beriman & Bertakwa kepada Tuhan YME', 'Akhlak Mulia', 'Mandiri', 'Bernalar Kritis'];
            $kompetensiAwal    = "Peserta didik telah memiliki pembiasaan adab harian di madrasah/pesantren dan memahami dasar-dasar syariat Islam terkait thaharah dan ibadah.";
            $pemahamanBermakna = "Mempelajari {$cleanBab} menumbuhkan kesadaran bahwa ketaatan menjalankan syariat Allah secara benar mendatangkan ketenangan batin, keberkahan ilmu, dan keselamatan dunia-akhirat.";
            $pertanyaanPemantik= "1. Mengapa materi {$cleanBab} sangat menentukan kualitas ibadah dan adab kita sehari-hari?\n2. Bagaimana cara kita membentengi diri agar tidak keliru dalam mengamalkan {$cleanBab} di tengah kesibukan zaman modern?";
            $saranaPrasarana   = 'Kitab Rujukan/Buku Pegangan Siswa, Papan Tulis, Lembar Studi Kasus Amaliah, Alat Peraga Praktik Ibadah.';
            $targetSiswa       = 'Peserta didik reguler / santri (28-32 siswa dalam rombel campuran)';
            
            $pendahuluan       = "1. Guru mengucapkan salam hangat dan mengajak peserta didik berdoa bersama/membaca shalawat thibil qulub.\n2. Guru memeriksa presensi, kesiapan fisik, serta kerapian busana santri.\n3. Apersepsi: Guru mengaitkan materi {$cleanBab} dengan kebiasaan ibadah santri sehari-hari.\n4. Guru menyampaikan tujuan pembelajaran hari ini dan pentingnya menjaga niat ikhlas menuntut ilmu.";
            $kegiatanInti      = "1. Stimulasi: Guru membacakan dalil pokok / memaparkan materi kunci {$cleanBab} di papan tulis.\n2. Organisasi Belajar: Peserta didik membentuk halaqah kecil (4-5 santri) untuk mendiskusikan studi kasus amaliah pada lembar kerja.\n3. Bimbingan Penyelidikan: Guru berkeliling memberikan tas-hih (arahan dan koreksi kaidah) pada halaqah yang membutuhkan penjelasan tambahan.\n4. Simulasi & Penyajian: Perwakilan halaqah memperagakan tata cara / mempresentasikan intisari pembahasan {$cleanBab} di depan kelas.\n5. Refleksi & Penguatan: Guru memberikan ta'qid (penguatan konsep hukum syariat) serta mengapresiasi keaktifan seluruh santri.";
            $penutup           = "1. Peserta didik dibimbing guru menyimpulkan kaidah utama dari materi {$cleanBab}.\n2. Guru memberikan pesan mutaba'ah amaliah untuk dipraktikkan langsung di asrama/rumah.\n3. Guru menutup pembelajaran dengan membaca hamdalah dan doa kafaratul majelis.";

            $asesmenDiag       = 'Tanya jawab lisan tentang pengalaman santri mengamalkan materi pada hari-hari sebelumnya.';
            $asesmenFormat     = 'Observasi keaktifan diskusi halaqah, penilaian unjuk kerja praktik syariat, dan lembar kerja santri.';
            $asesmenSumat      = "Ujian tulis pemahaman kaidah syariat dan tes lisan penguasaan dalil pokok materi {$cleanBab}.";
            $lkpdContent       = "LEMBAR KERJA SANTRI (LKS) - " . strtoupper($cleanBab) . ":\n1. Tuliskan 1 dalil naqli utama yang mendasari materi {$cleanBab} beserta artinya!\n2. Selesaikan analisis kasus amaliah harian berikut bersama kelompok halaqahmu secara runtut!\n3. Tuliskan kesimpulan hukum dan hikmah yang kamu dapatkan!";
            $glosarium         = "{$cleanBab}: Pembahasan materi pokok dalam mata pelajaran {$mapel}.\nDalil Naqli: Landasan hukum syariat bersumber dari Al-Qur'an atau As-Sunnah ash-Shahihah.\nAdab: Sikap batin dan lahiriah yang mencerminkan keluhuran budi pekerti santri.";
            $daftarPustaka     = "1. Tim Kemenag RI. (2022). Buku Siswa & Guru {$mapel} Kelas {$kelas} MTs/SMP. Jakarta.\n2. Kitab Rujukan Fikih/Akidah Standar Madrasah & Pesantren.";

        } elseif ($kategori === 'bahasa') {
            $modelPembelajaran = 'Genre-Based Approach (Pembelajaran Berbasis Teks) & Cooperative Learning';
            $profilPancasila   = ['Bernalar Kritis', 'Kreatif', 'Gotong Royong', 'Mandiri'];
            $kompetensiAwal    = "Peserta didik mampu membaca lancar, memahami ide pokok paragraf, serta memiliki perbendaharaan kata dasar bahasa formal.";
            $pemahamanBermakna = "Penguasaan materi {$cleanBab} melatih kepekaan berbahasa yang santun, kemampuan berpikir kritis menyaring informasi, serta keterampilan berkomunikasi yang persuasif.";
            $pertanyaanPemantik= "1. Apa yang membedakan sebuah teks materi {$cleanBab} yang berkualitas dengan teks yang membosankan atau membingungkan pembaca?\n2. Mengapa kita perlu memilih kosa kata yang tepat saat menyampaikan gagasan pada topik ini?";
            $saranaPrasarana   = 'Buku Teks Siswa, Tayangan Contoh Teks / Media Audio-Visual, Proyektor/Papan Tulis, LKPD Analisis Teks.';
            $targetSiswa       = 'Peserta didik reguler / tipikal (28-32 siswa rombel campuran)';

            $pendahuluan       = "1. Guru membuka kelas dengan salam, menyapa siswa, berdoa bersama, dan mengecek kehadiran.\n2. Guru memantik rasa ingin tahu siswa dengan membacakan cuplikan teks menarik terkait {$cleanBab}.\n3. Guru menyampaikan tujuan pembelajaran, garis besar kegiatan, serta kriteria penilaian karya siswa.";
            $kegiatanInti      = "1. Dekonstruksi Teks: Siswa bersama guru membaca dan membedah teks model {$cleanBab} (mengamati struktur dan ciri kebahasaan).\n2. Membangun Konteks Kelompok: Siswa dibagi menjadi kelompok heterogen (4-5 orang) untuk menganalisis unsur teks pada lembar kerja.\n3. Pembimbingan: Guru memfasilitasi diskusi, memberi pancingan ide, dan meluruskan kekeliruan analisis kebahasaan siswa.\n4. Konstruksi Mandiri/Kelompok: Siswa menyusun draf karya tulis atau mempersiapkan presentasi lisan berbasis materi {$cleanBab}.\n5. Umpan Balik: Perwakilan kelompok menyajikan karyanya, kelompok lain memberikan tanggapan apresiatif berbantuan rubrik.";
            $penutup           = "1. Siswa merangkum poin kaidah kebahasaan dan struktur teks {$cleanBab} yang telah dipelajari.\n2. Refleksi: Siswa mengungkapkan hal baru yang mereka kuasai dan hal yang masih perlu latihan mandiri.\n3. Guru memberikan tugas membaca pengayaan dan menutup pertemuan dengan doa serta salam.";

            $asesmenDiag       = 'Kuis singkat membaca memirsa untuk memetakan kosakata dan pemahaman awal siswa.';
            $asesmenFormat     = 'Penilaian proses diskusi kelompok, lembar kerja analisis teks, dan unjuk kerja draf tulisan.';
            $asesmenSumat      = "Tes tertulis pemahaman struktur kebahasaan dan penilaian produk karya teks/lisan materi {$cleanBab}.";
            $lkpdContent       = "LEMBAR KERJA PESERTA DIDIK (LKPD) - TEKS " . strtoupper($cleanBab) . ":\n1. Bacalah kutipan teks model yang disediakan secara saksama!\n2. Temukan bagian struktur pembangun teks dan tandai kalimat yang memuat ciri kebahasaan khususnya!\n3. Susunlah sebuah paragraf gagasan mandiri dengan menerapkan kaidah ejaan yang tepat!";
            $glosarium         = "{$cleanBab}: Topik pembahasan teks dan kemampuan berbahasa.\nStruktur Teks: Susunan kerangka berpikir yang membangun keutuhan sebuah teks.\nKohesi & Koherensi: Kepaduan bentuk dan kepaduan makna antarbagian kalimat.";
            $daftarPustaka     = "1. Kemendikbudristek. (2022). Buku Panduan Guru & Siswa {$mapel} SMP Kelas {$kelas}. Jakarta: Pusat Perbukuan.\n2. Pedoman Umum Ejaan Bahasa Indonesia (PUEBI) / Tata Bahasa Baku.";

        } elseif ($kategori === 'eksakta') {
            $modelPembelajaran = 'Problem-Based Learning (PBL) & Discovery Learning';
            $profilPancasila   = ['Bernalar Kritis', 'Kreatif', 'Mandiri', 'Gotong Royong'];
            $kompetensiAwal    = "Peserta didik memahami konsep prasyarat perhitungan dasar dan memiliki ketertarikan mengeksplorasi fenomena di sekitar.";
            $pemahamanBermakna = "Mempelajari {$cleanBab} melatih pola pikir logis, ketelitian menyelesaikan masalah tahap demi tahap, serta kemampuan mengaplikasikan perhitungan dalam kehidupan sehari-hari.";
            $pertanyaanPemantik= "1. Di mana saja kita sering menjumpai penerapan nyata konsep {$cleanBab} dalam aktivitas kita setiap hari?\n2. Bagaimana cara paling logis dan efisien untuk menyelesaikan teka-teki permasalahan pada materi ini?";
            $saranaPrasarana   = 'Buku Paket Siswa, Papan Tulis, Slide Presentasi/Video Pengantar Masalah, Lembar Kerja Eksplorasi (LKPD).';
            $targetSiswa       = 'Peserta didik reguler / tipikal (28-32 siswa)';

            $pendahuluan       = "1. Guru mengucap salam, menyapa ramah siswa, berdoa bersama, dan mengecek presensi.\n2. Apersepsi: Guru menyajikan sebuah masalah nyata yang paradoks atau menarik terkait {$cleanBab}.\n3. Guru mengutarakan tujuan pembelajaran dan memotivasi siswa tentang manfaat materi ini dalam karier dan kehidupan.";
            $kegiatanInti      = "1. Orientasi Masalah: Siswa mencermati tayangan kasus kontekstual yang disajikan guru di depan kelas.\n2. Organisasi Belajar: Siswa membentuk kelompok kerja (4-5 anak) menerima lembar aktivitas eksplorasi rumus/konsep.\n3. Penyelidikan Mandiri & Kelompok: Siswa mengumpulkan data, mencoba berbagai alternatif perhitungan, dan merumuskan pola.\n4. Mengembangkan Hasil: Setiap kelompok menuliskan langkah penyelesaian masalah secara sistematis di papan tulis / plano.\n5. Evaluasi & Verifikasi: Guru memandu verifikasi hasil kerja siswa, memberikan klarifikasi rumus cepat, dan apresiasi pemecahan terbaik.";
            $penutup           = "1. Siswa bersama guru merangkum rumus/konsep esensial yang berhasil ditemukan pada hari ini.\n2. Guru memberikan 1 butir soal 'tiket keluar' (exit ticket) sebagai cek pemahaman cepat.\n3. Guru memberikan arahan latihan mandiri dan menutup kelas dengan doa bersama.";

            $asesmenDiag       = 'Tanya jawab apersepsi perhitungan prasyarat sebelum memulai materi.';
            $asesmenFormat     = 'Observasi keaktifan bernalar kritis dalam kelompok, lembar eksplorasi LKPD, dan presentasi langkah kerja.';
            $asesmenSumat      = "Tes tertulis pemecahan masalah kontekstual materi {$cleanBab} berbasis soal pilihan ganda beralasan dan uraian.";
            $lkpdContent       = "LEMBAR KERJA PESERTA DIDIK (LKPD) EKSPLORASI " . strtoupper($cleanBab) . ":\n1. Bacalah permasalahan kontekstual nomor 1 dan ubahlah menjadi bentuk representasi matematis/saintifik!\n2. Kerjakan langkah perhitungan secara runtut bersama kelompokmu!\n3. Tuliskan kesimpulan akhir beserta alasan logis dari jawaban yang kamu dapatkan!";
            $glosarium         = "{$cleanBab}: Konsep materi esensial dalam mata pelajaran {$mapel}.\nRepresentasi Logis: Pengubahan masalah bahasa sehari-hari menjadi model matematis/ilmiah.\nVariabel: Simbol penanda nilai yang belum diketahui secara pasti.";
            $daftarPustaka     = "1. Kemendikbudristek. (2022). Buku Siswa {$mapel} Kelas {$kelas} SMP/MTs. Jakarta: Balai Pustaka.\n2. Buku Pengayaan Pemecahan Masalah Matematika & Sains Terapan.";

        } else {
            // Mapel Sosial, PJOK, Seni, Umum
            $modelPembelajaran = 'Project-Based Learning (PjBL) & Pembelajaran Kontekstual (CTL)';
            $profilPancasila   = ['Berkebinekaan Global', 'Gotong Royong', 'Bernalar Kritis', 'Mandiri'];
            $kompetensiAwal    = "Peserta didik memahami norma sosial dasar dan memiliki rasa ingin tahu terhadap dinamika lingkungan sekitar.";
            $pemahamanBermakna = "Mempelajari {$cleanBab} membentuk karakter warga negara yang bertanggung jawab, menghargai keberagaman, serta berdaya cipta dalam memecahkan masalah kemasyarakatan.";
            $pertanyaanPemantik= "1. Mengapa isu {$cleanBab} menjadi topik yang penting untuk kita amati dan kita pedulikan bersama?\n2. Kontribusi positif apa yang bisa kita berikan sebagai pelajar dalam menghadapi persoalan terkait topik ini?";
            $saranaPrasarana   = 'Buku Teks Siswa, Artikel Kasus Kontekstual, Peta/Gambar Pendukung, Proyektor, LKPD Analisis Sosial.';
            $targetSiswa       = 'Peserta didik reguler / tipikal (28-32 siswa)';

            $pendahuluan       = "1. Guru mengucapkan salam, menyapa siswa, berdoa bersama, dan memeriksa presensi.\n2. Apersepsi: Guru mengaitkan materi {$cleanBab} dengan berita terkini atau peristiwa di lingkungan sekitar siswa.\n3. Guru menyampaikan tujuan pembelajaran dan gambaran proyek/aktivitas yang akan dikerjakan bersama.";
            $kegiatanInti      = "1. Eksplorasi Fenomena: Siswa mengamati tayangan video / membaca artikel studi kasus materi {$cleanBab}.\n2. Kolaborasi Tim: Siswa berdiskusi dalam kelompok menganalisis faktor penyebab, dinamika, dan alternatif solusi.\n3. Bimbingan Fasilitator: Guru mendampingi jalannya diskusi kelompok dan mendorong partisipasi aktif setiap anggota.\n4. Penyajian Gagasan: Masing-masing kelompok mempresentasikan resume hasil telaah kasus di hadapan kelas.\n5. Refleksi Bersama: Guru dan siswa menyimpulkan nilai moral, hikmah sosial, dan langkah aksi nyata yang dapat diambil.";
            $penutup           = "1. Siswa merangkum poin-poin refleksi materi {$cleanBab} yang diperoleh hari ini.\n2. Guru memberikan penguatan karakter kebinekaan dan kepedulian lingkungan.\n3. Kelas diakhiri dengan doa bersama dan salam penutup.";

            $asesmenDiag       = 'Pertanyaan pemantik lisan tentang wawasan awal siswa mengenai topik terkait.';
            $asesmenFormat     = 'Penilaian keaktifan diskusi, kualitas argumen lembar kerja kelompok, dan sikap gotong royong.';
            $asesmenSumat      = "Penilaian portofolio laporan telaah kasus atau tes tertulis pemahaman konsep materi {$cleanBab}.";
            $lkpdContent       = "LEMBAR KERJA PESERTA DIDIK (LKPD) TELAAH " . strtoupper($cleanBab) . ":\n1. Bacalah rangkuman artikel kasus yang tersedia!\n2. Identifikasi 3 faktor utama yang melatarbelakangi kasus tersebut dan diskusikan dampaknya!\n3. Rumuskan 2 rekomendasi solusi yang realistis dan dapat diterapkan!";
            $glosarium         = "{$cleanBab}: Topik kajian utama dalam mata pelajaran {$mapel}.\nDinamika Sosial: Perubahan dan interaksi yang terus berkembang di tengah masyarakat.\nGotong Royong: Sikap kerja bersama untuk mencapai tujuan kemaslahatan bersama.";
            $daftarPustaka     = "1. Kemendikbudristek. (2022). Buku Panduan Pendidik & Siswa {$mapel} Kelas {$kelas}. Jakarta.\n2. Sumber Belajar Lingkungan & Ensiklopedia Pendukung.";
        }

        return [
            'bab_nomor'            => $babNum,
            'judul_bab'            => $cleanBab,
            'semester'             => $semesterNum,
            'alokasi_waktu'        => "{$pertemuan} Pertemuan ({$babJp} JP × 40 Menit)",
            'alokasi_jp'           => $babJp,
            'jumlah_pertemuan'     => $pertemuan,
            'model_pembelajaran'   => $modelPembelajaran,
            'profil_pancasila'     => $profilPancasila,
            'kompetensi_awal'      => $kompetensiAwal,
            'sarana_prasarana'     => $saranaPrasarana,
            'target_siswa'         => $targetSiswa,
            'pemahaman_bermakna'   => $pemahamanBermakna,
            'pertanyaan_pemantik'  => $pertanyaanPemantik,
            'kegiatan_pendahuluan' => $pendahuluan,
            'kegiatan_inti'        => $kegiatanInti,
            'kegiatan_penutup'     => $penutup,
            'asesmen_diagnostik'   => $asesmenDiag,
            'asesmen_formatif'     => $asesmenFormat,
            'asesmen_sumatif'      => $asesmenSumat,
            'lkpd_content'         => $lkpdContent,
            'bahan_bacaan'         => "Buku Teks Resmi {$mapel} Kelas {$kelas} dan Modul Rujukan Terverifikasi",
            'glosarium'            => $glosarium,
            'daftar_pustaka'       => $daftarPustaka
        ];
    }

    /**
     * Elemen Kurikulum Utama Sesuai Kategori
     */
    private static function getElemenUtama(string $mapel, string $kategori): string
    {
        switch ($kategori) {
            case 'diniyah':
                return 'Syariat, Aqidah & Akhlakul Karimah';
            case 'bahasa':
                return 'Menyimak, Membaca-Memirsa, Berbicara, & Menulis';
            case 'eksakta':
                return 'Pemahaman Konsep & Penalaran Pemecahan Masalah';
            case 'sosial':
                return 'Pemahaman Sosial, Analisis Spasial, & Karakter Kebangsaan';
            case 'olahraga':
                return 'Keterampilan Gerak & Pola Hidup Sehat';
            default:
                return 'Pemahaman Konsep & Penerapan Kontekstual';
        }
    }
}

