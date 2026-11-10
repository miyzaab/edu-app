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
     * Deteksi Sub-Tipe Mapel Spesifik untuk Narasi Pedagogik Alami
     */
    public static function detectSubMapel(string $mapel): string
    {
        $m = strtolower(trim($mapel));
        if (stripos($m, 'shorof') !== false || stripos($m, 'sharaf') !== false || stripos($m, 'tashrif') !== false) return 'shorof';
        if (stripos($m, 'nahwu') !== false || stripos($m, 'jurumiyah') !== false || stripos($m, 'imrithi') !== false) return 'nahwu';
        if (stripos($m, 'fiqih') !== false || stripos($m, 'fikih') !== false || stripos($m, 'safinah') !== false || stripos($m, 'ushul fiqih') !== false) return 'fiqih';
        if (stripos($m, 'aqidah') !== false || stripos($m, 'akidah') !== false || stripos($m, 'tauhid') !== false || stripos($m, 'akhlak') !== false) return 'aqidah';
        if (stripos($m, 'qur') !== false || stripos($m, 'tajwid') !== false || stripos($m, 'hadits') !== false || stripos($m, 'hadis') !== false || stripos($m, 'tahfidz') !== false || stripos($m, 'tafsir') !== false) return 'quran_hadits';
        if (stripos($m, 'tarikh') !== false || stripos($m, 'ski') !== false || stripos($m, 'sejarah islam') !== false) return 'tarikh';
        if (stripos($m, 'arab') !== false || stripos($m, 'muhadatsah') !== false || stripos($m, 'insya') !== false || stripos($m, 'bayan') !== false) return 'bahasa_arab';
        if (stripos($m, 'pjok') !== false || stripos($m, 'olahraga') !== false || stripos($m, 'penjas') !== false) return 'pjok';
        if (stripos($m, 'matematika') !== false || stripos($m, 'math') !== false) return 'matematika';
        if (stripos($m, 'ipa') !== false || stripos($m, 'sains') !== false || stripos($m, 'fisika') !== false || stripos($m, 'biologi') !== false || stripos($m, 'kimia') !== false) return 'ipa';
        if (stripos($m, 'it') !== false || stripos($m, 'informatika') !== false || stripos($m, 'komputer') !== false) return 'informatika';
        if (stripos($m, 'indonesia') !== false) return 'bahasa_indonesia';
        if (stripos($m, 'inggris') !== false || stripos($m, 'english') !== false) return 'bahasa_inggris';
        if (stripos($m, 'ips') !== false || stripos($m, 'sejarah') !== false || stripos($m, 'geografi') !== false || stripos($m, 'sosiologi') !== false || stripos($m, 'ekonomi') !== false) return 'ips';
        if (stripos($m, 'pkn') !== false || stripos($m, 'pancasila') !== false) return 'pkn';
        if (stripos($m, 'seni') !== false || stripos($m, 'prakarya') !== false || stripos($m, 'sbdp') !== false) return 'seni';
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

        // Parameter Kustom Identitas Modul (Model Pembelajaran, Target Peserta Didik, Daftar Pustaka)
        $userModel    = trim($params['model_pembelajaran'] ?? '');
        $userTarget   = trim($params['target_siswa'] ?? '');
        $userPustaka  = trim($params['daftar_pustaka'] ?? '');

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
        $processedSmt1 = self::processSemesterBab($babListSmt1, 1, $pekanSmt1, $jpPerPekan, $mapel, $kelas, $fase, $kategori, $namaSekolah, $namaGuru, $namaKepsek, $nipGuru, $nipKepsek, 0, $userModel, $userTarget, $userPustaka);

        // Proses Bab Semester 2
        $processedSmt2 = self::processSemesterBab($babListSmt2, 2, $pekanSmt2, $jpPerPekan, $mapel, $kelas, $fase, $kategori, $namaSekolah, $namaGuru, $namaKepsek, $nipGuru, $nipKepsek, count($babListSmt1), $userModel, $userTarget, $userPustaka);

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

        $firstBabModul = $processedSmt1['bab_items'][0]['modul'] ?? ($processedSmt2['bab_items'][0]['modul'] ?? []);

        $modulJsonData = [
            'nama_sekolah'       => $namaSekolah,
            'nama_guru'          => $namaGuru,
            'nip_guru'           => $nipGuru,
            'nama_kepsek'        => $namaKepsek,
            'nip_kepsek'         => $nipKepsek,
            'mapel'              => $mapel,
            'kelas'              => $kelas,
            'fase'               => $fase,
            'jp_per_pekan'       => $jpPerPekan,
            'tahun_ajaran'       => $tahunAjaran,
            'kategori_mapel'     => $kategori,
            'total_jp_tahun'     => $totalJpSmt1 + $totalJpSmt2,
            'model_pembelajaran' => !empty($userModel) ? $userModel : ($firstBabModul['model_pembelajaran'] ?? 'Problem-Based Learning (PBL)'),
            'target_siswa'       => !empty($userTarget) ? $userTarget : ($firstBabModul['target_siswa'] ?? 'Peserta didik reguler / tipikal (30 siswa)'),
            'daftar_pustaka'     => !empty($userPustaka) ? $userPustaka : ($firstBabModul['daftar_pustaka'] ?? ''),
            'cp_global'          => $cpIntroduction,
            'semester_1'         => [
                'nama'        => 'Semester Ganjil (1)',
                'pekan'       => $pekanSmt1,
                'total_jp'    => $totalJpSmt1,
                'bab_list'    => $processedSmt1['bab_items']
            ],
            'semester_2'         => [
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
        int $startIndex = 0,
        string $userModel = '',
        string $userTarget = '',
        string $userPustaka = ''
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

            // Buat CP per bab (dengan variasi otentik berotasi berdasarkan nomor bab dan mapel spesifik)
            $cpBab = self::generateCpBab($cleanBab, $mapel, $kategori, $fase, $globalNum);
            $cpLines[] = "• Bab {$globalNum} ({$cleanBab}): {$cpBab}";

            // Buat TP per bab (3-4 butir taksonomi operasional otentik)
            $tpItems = self::generateTpBab($cleanBab, $globalNum, $mapel, $kategori);
            foreach ($tpItems as $tp) {
                $tpLines[] = $tp;
            }

            // Buat ATP per bab
            $atpBab = self::generateAtpBab($cleanBab, $globalNum, $babJp, $pertemuan, $kategori, $mapel);
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
                $tpItems,
                $userModel,
                $userTarget,
                $userPustaka
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
     * Narasi Capaian Pembelajaran (CP) Global Otentik Sesuai Karakteristik Mata Pelajaran
     */
    private static function getCpNaration(string $mapel, string $kelas, string $fase, string $kategori): string
    {
        $sub = self::detectSubMapel($mapel);

        if ($sub === 'shorof') {
            return "Pada akhir Fase {$fase} (Kelas {$kelas}), peserta didik memiliki kemahiran dalam menguasai kaidah morfologi bahasa Arab (Ilmu Shorof). Peserta didik mampu mengidentifikasi wazan, mawzun, shighah, dan pola perubahan kata (tashrif istilahi & lughawi), menganalisis bina' dan karakteristik perubahan bentuk kata kerja maupun kata benda, serta menerapkannya secara tepat dalam membaca, memahami, dan membedah literatur Arab serta teks-teks keislaman.";
        }

        if ($sub === 'nahwu') {
            return "Pada akhir Fase {$fase} (Kelas {$kelas}), peserta didik memiliki kemahiran dalam menguasai kaidah sintaksis bahasa Arab (Ilmu Nahwu). Peserta didik mampu menganalisis susunan kalimat (tarkib), mengenali jabatan kata dan pengaruh amil, menentukan harakat akhir dan tanda i'rab secara tepat, serta menerapkannya dalam membaca dan memahami literatur kitab berbahasa Arab.";
        }

        if ($sub === 'bahasa_arab') {
            return "Pada akhir Fase {$fase} (Kelas {$kelas}), peserta didik memiliki kemahiran berkomunikasi menggunakan bahasa Arab dalam empat keterampilan berbahasa (istima', kalam, qira'ah, dan kitabah). Peserta didik mampu memahami teks lisan dan tulis, menyusun kalimat fungsional, serta mengekspresikan gagasan dan dialog keseharian secara komunikatif.";
        }

        if ($sub === 'tarikh') {
            return "Pada akhir Fase {$fase} (Kelas {$kelas}), peserta didik memiliki wawasan historis mengenai Sejarah Kebudayaan Islam. Peserta didik mampu menelaah kronologi peristiwa penting, menganalisis faktor perkembangan dan kemajuan peradaban Islam, serta mengambil ibrah dan keteladanan dari kepemimpinan Rasulullah SAW, Khulafaur Rasyidin, dan tokoh-tokoh pembaharu Islam.";
        }

        if ($sub === 'fiqih') {
            return "Pada akhir Fase {$fase} (Kelas {$kelas}), peserta didik memiliki pemahaman mendalam mengenai prinsip-prinsip syariat Islam pada mata pelajaran {$mapel}. Peserta didik mampu menelaah dalil naqli secara kontekstual, memahami ketentuan hukum, syarat dan rukun, serta mempraktikkan tata cara peribadatan dan muamalah secara tertib dan benar dalam kehidupan sehari-hari.";
        }

        if ($sub === 'aqidah') {
            return "Pada akhir Fase {$fase} (Kelas {$kelas}), peserta didik memiliki keyakinan tauhid yang kokoh dan berakhlakul karimah. Peserta didik mampu mengkaji dalil aqli dan naqli mengenai rukun iman, menghindarkan diri dari penyimpangan akidah, serta menginternalisasikan nilai-nilai kesalehan moral dalam interaksi sosial sehari-hari.";
        }

        if ($sub === 'quran_hadits') {
            return "Pada akhir Fase {$fase} (Kelas {$kelas}), peserta didik memiliki keterampilan membaca Al-Qur'an dan Hadits secara tartil sesuai kaidah tajwid yang benar. Peserta didik mampu menghafal surah/hadits pilihan, memahami kandungan makna dan asbabun nuzul/wurud, serta mengamalkan nilai-nilai Al-Qur'an dan Hadits dalam keseharian.";
        }

        switch ($kategori) {
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
     * Rumusan CP Khusus Per Bab (Variasi Otentik & Menghindari Pola Template Kaku)
     */
    private static function generateCpBab(string $cleanBab, string $mapel, string $kategori, string $fase, int $babNum = 1): string
    {
        $sub = self::detectSubMapel($mapel);

        // Khusus Shorof (Ilmu Tashrif & Morfologi Bahasa Arab)
        if ($sub === 'shorof') {
            $shorofStyles = [
                1 => "Peserta didik mampu memahami hakikat timbangan kata (wazan) dan huruf asal pada kaidah mizan shorfi, mengidentifikasi huruf zaidah, serta terampil menimbang ragam kosakata bahasa Arab secara presisi.",
                2 => "Peserta didik terampil menguraikan perubahan bentuk kata (shighah) dari fi'il madhi hingga isim alat, serta menganalisis pergeseran makna leksikal dan gramatikal pada materi {$cleanBab}.",
                3 => "Peserta didik mampu mengonjugasikan bentuk kata {$cleanBab} berdasarkan 14 dhomir secara fasih dan sistematis, baik dalam pelafalan lisan maupun penulisan teks bertanda baca.",
                4 => "Peserta didik menunjukkan kecakapan dalam mentransformasikan bentuk fi'il serta menerapkan aturan pembentukan {$cleanBab} ke dalam struktur kalimat pernyataan, perintah, atau larangan secara tepat.",
                5 => "Peserta didik mampu membedah karakteristik dan faedah penambahan huruf pada materi {$cleanBab}, serta membedakan pola bina' dengan tepat sesuai kaidah ahli shorof.",
                6 => "Peserta didik mampu menganalisis proses perpindahan huruf, pembuangan, dan penggantian harakat pada materi {$cleanBab}, serta mengaplikasikannya untuk menelaah kejanggalan lafadz dalam teks Arab.",
                7 => "Peserta didik mampu merekonstruksi kaidah morfologi komprehensif terkait {$cleanBab}, menghubungkan konsep gramatika dengan keterampilan membaca kitab gundul, serta memvalidasi akurasi makna kata secara mandiri.",
                8 => "Peserta didik memiliki kemahiran dalam mengurai struktur tashrif pada materi {$cleanBab}, memverifikasi perubahan wazan pada ayat Al-Qur'an dan hadits nabawi, serta menginternalisasikan kecintaan terhadap keindahan bahasa Arab."
            ];
            $key = (($babNum - 1) % count($shorofStyles)) + 1;
            return $shorofStyles[$key];
        }

        // Khusus Nahwu (Sintaksis Arab & I'rab)
        if ($sub === 'nahwu') {
            $nahwuStyles = [
                1 => "Peserta didik mampu mengenali pembagian kalam, jenis-jenis kata, serta membedakan ciri-ciri khas materi {$cleanBab} dalam rangkaian struktur kalimat bahasa Arab.",
                2 => "Peserta didik terampil menganalisis jabatan kata (tarkib), menentukan fungsi subjek dan predikat pada materi {$cleanBab}, serta membaca harakat akhirnya secara tepat.",
                3 => "Peserta didik mampu mengidentifikasi pengaruh amil terhadap tanda-tanda i'rab (rafa', nashab, jar, jazm) pada materi {$cleanBab} secara teliti dan kritis.",
                4 => "Peserta didik menunjukkan kemahiran dalam membedah relasi gramatikal pada pembahasan {$cleanBab}, serta mampu menyusun pola kalimat fungsional sesuai kaidah nahwu standar.",
                5 => "Peserta didik mampu menerapkan analisis i'rab komprehensif pada materi {$cleanBab} untuk mengoreksi kekeliruan struktur kalimat dan meningkatkan kelancaran membaca literatur Arab."
            ];
            $key = (($babNum - 1) % count($nahwuStyles)) + 1;
            return $nahwuStyles[$key];
        }

        // Khusus Fiqih & Syariah
        if ($sub === 'fiqih') {
            $fiqihStyles = [
                1 => "Peserta didik mampu menelaah dalil naqli, hakikat hukum, dan rukun pokok terkait materi {$cleanBab}, serta memahami hikmah pensyariatannya dalam kehidupan pribadi dan sosial.",
                2 => "Peserta didik mampu mengklasifikasikan syarat sah, rukun, dan perkara yang membatalkan pada materi {$cleanBab}, serta terampil mendemonstrasikan tata caranya sesuai sunnah Rasulullah SAW.",
                3 => "Peserta didik memiliki kepekaan dalam menganalisis problematika fiqih kontemporer seputar {$cleanBab}, serta mampu merumuskan sikap amaliah yang bijak berlandaskan tuntunan syar'i.",
                4 => "Peserta didik mampu menginternalisasikan nilai-nilai kepatuhan, kebersihan lahir-batin, dan keikhlasan ibadah melalui pemahaman mendalam tentang materi {$cleanBab} dalam rutinitas keseharian."
            ];
            $key = (($babNum - 1) % count($fiqihStyles)) + 1;
            return $fiqihStyles[$key];
        }

        // Khusus Aqidah / Akhlak / Tauhid
        if ($sub === 'aqidah') {
            $aqidahStyles = [
                1 => "Peserta didik mampu mengkaji dalil aqli dan naqli mengenai materi {$cleanBab}, memperkokoh keyakinan tauhid, serta menghindarkan diri dari berbagai bentuk keraguan dan penyimpangan akidah.",
                2 => "Peserta didik mampu menguraikan nilai-nilai keluhuran budi pekerti pada materi {$cleanBab}, merefleksikan keteladanan salafus shalih, dan mengamalkannya dalam interaksi sosial sehari-hari.",
                3 => "Peserta didik menunjukkan integritas moral, keteguhan prinsip keimanan, dan kemuliaan akhlakul karimah yang terinspirasi dari pendalaman materi {$cleanBab} di lingkungan madrasah dan masyarakat."
            ];
            $key = (($babNum - 1) % count($aqidahStyles)) + 1;
            return $aqidahStyles[$key];
        }

        // Khusus PJOK / Penjas
        if ($sub === 'pjok') {
            $pjokStyles = [
                1 => "Peserta didik mampu menganalisis konsep gerak, teknik dasar, dan prinsip mekanika tubuh pada aktivitas {$cleanBab}, serta mempraktikkannya dengan koordinasi motorik yang baik.",
                2 => "Peserta didik terampil memvariasikan dan mengombinasikan keterampilan gerak spesifik {$cleanBab}, mengedepankan sportivitas, disiplin, keselamatan, serta kerja sama tim dalam aktivitas jasmani.",
                3 => "Peserta didik memahami pentingnya kebugaran jasmani melalui latihan materi {$cleanBab}, serta mampu merancang pola pembiasaan hidup aktif dan sehat secara berkala dan berkelanjutan."
            ];
            $key = (($babNum - 1) % count($pjokStyles)) + 1;
            return $pjokStyles[$key];
        }

        // Khusus Eksakta & Sains (Matematika & IPA)
        if ($kategori === 'eksakta') {
            $eksaktaStyles = [
                1 => "Peserta didik mampu menguasai konsep dasar, definisi, dan notasi ilmiah terkait materi {$cleanBab}, serta mengidentifikasi pola keteraturan kuantitatif yang melandasinya.",
                2 => "Peserta didik terampil melakukan perhitungan operasional, memodelkan hubungan matematis/saintifik, dan memecahkan soal kontekstual berbasis pemecahan masalah pada topik {$cleanBab}.",
                3 => "Peserta didik mampu merancang prosedur penyelidikan, mengumpulkan dan mengolah data empiris, serta menarik kesimpulan logis yang valid mengenai materi {$cleanBab}.",
                4 => "Peserta didik memiliki daya nalar kritis dalam mengevaluasi efektivitas solusi pemecahan masalah serta mengomunikasikan argumentasi saintifik materi {$cleanBab} secara sistematis."
            ];
            $key = (($babNum - 1) % count($eksaktaStyles)) + 1;
            return $eksaktaStyles[$key];
        }

        // Khusus Bahasa (Indonesia & Asing)
        if ($kategori === 'bahasa') {
            $bahasaStyles = [
                1 => "Peserta didik mampu menyimak dan mengekstraksi informasi faktual, ide pokok, serta sudut pandang penulis dalam teks wacana materi {$cleanBab} secara mendalam.",
                2 => "Peserta didik terampil menganalisis struktur retorika, kohesi kalimat, dan karakteristik diksi pada materi {$cleanBab} secara cermat dan kritis.",
                3 => "Peserta didik mampu mengonstruksi gagasan orisinal ke dalam karya tulis fungsional maupun sastra bertema {$cleanBab} dengan kaidah kebahasaan yang baku dan komunikatif.",
                4 => "Peserta didik menunjukkan kemahiran dalam mempresentasikan dan mendiskusikan ulasan kritis materi {$cleanBab} di hadapan audiens dengan santun dan percaya diri."
            ];
            $key = (($babNum - 1) % count($bahasaStyles)) + 1;
            return $bahasaStyles[$key];
        }

        // Variasi Alami Berotasi untuk Mapel Sosial & Umum Lainnya (8 Pola Beragam)
        $umumStyles = [
            1 => "Peserta didik mampu menelaah hakikat dan landasan konsep dasar {$cleanBab}, mengidentifikasi unsur-unsur esensial di dalamnya, serta menjelaskan keterhubungannya secara sistematis.",
            2 => "Peserta didik terampil mengaplikasikan prinsip operasional dan aturan prosedural pada materi {$cleanBab} dalam menyelesaikan tugas dan studi kasus kontekstual secara mandiri.",
            3 => "Peserta didik menunjukkan kemampuan bernalar kritis dalam membedah karakteristik {$cleanBab}, membandingkan berbagai alternatif perspektif, dan menyimpulkan intisari materi secara logis.",
            4 => "Peserta didik mampu mengonstruksi pemahaman aplikatif mengenai {$cleanBab}, merancang langkah penyelidikan atau karya nyata, serta mengomunikasikan gagasannya secara efektif.",
            5 => "Peserta didik memiliki kecakapan dalam mengevaluasi efektivitas penerapan materi {$cleanBab}, merefleksikan dinamika yang terjadi, serta merumuskan strategi tindak lanjut yang konstruktif.",
            6 => "Peserta didik mampu menghubungkan materi {$cleanBab} dengan realitas kehidupan bermasyarakat, serta menginternalisasikan nilai-nilai ketekunan, integritas, dan tanggung jawab belajar.",
            7 => "Peserta didik terampil menganalisis relasi antarkonsep pada pembahasan {$cleanBab}, serta mampu menyajikan sintesis materi dalam bentuk pemaparan yang terstruktur dan mudah dipahami.",
            8 => "Peserta didik mampu menunjukkan penguasaan komprehensif terhadap capaian materi {$cleanBab} melalui unjuk kerja, portofolio pemecahan masalah, dan asesmen berkala yang berbobot."
        ];
        $key = (($babNum - 1) % count($umumStyles)) + 1;
        return $umumStyles[$key];
    }

    /**
     * Rumusan Tujuan Pembelajaran (TP) Taksonomi Bloom Bertingkat (Otentik Manusia)
     */
    private static function generateTpBab(string $cleanBab, int $babNum, string $mapel, string $kategori): array
    {
        $sub = self::detectSubMapel($mapel);
        $tp = [];

        if ($sub === 'shorof') {
            $tp[] = "{$babNum}.1 Peserta didik mampu menjelaskan pengertian, konsep wazan, dan fungsi gramatikal dari materi {$cleanBab} secara tepat.";
            $tp[] = "{$babNum}.2 Peserta didik mampu mengidentifikasi karakteristik shighah dan pola perubahan bentuk kata pada {$cleanBab} secara cermat.";
            $tp[] = "{$babNum}.3 Peserta didik mampu mempraktikkan tashrif lughawi dan istilahi terkait {$cleanBab} secara fasih dalam lisan maupun tulisan.";
            $tp[] = "{$babNum}.4 Peserta didik mampu menganalisis contoh kosakata materi {$cleanBab} yang terdapat dalam ayat Al-Qur'an, hadits, atau teks bacaan Arab.";
            return $tp;
        }

        if ($sub === 'nahwu') {
            $tp[] = "{$babNum}.1 Peserta didik mampu mengidentifikasi kedudukan kalimat (tarkib) dan fungsi kata pada materi {$cleanBab}.";
            $tp[] = "{$babNum}.2 Peserta didik mampu menganalisis pengaruh amil terhadap perubahan tanda i'rab pada materi {$cleanBab}.";
            $tp[] = "{$babNum}.3 Peserta didik mampu menyusun contoh kalimat berbahasa Arab yang menerapkan kaidah {$cleanBab} secara benar.";
            $tp[] = "{$babNum}.4 Peserta didik mampu mengoreksi kekeliruan struktur sintaksis terkait {$cleanBab} pada wacana literatur Arab.";
            return $tp;
        }

        if ($sub === 'fiqih') {
            $tp[] = "{$babNum}.1 Peserta didik mampu menelaah dalil naqli, ketentuan hukum, dan rukun pokok terkait materi {$cleanBab} secara mendalam.";
            $tp[] = "{$babNum}.2 Peserta didik mampu mengklasifikasikan syarat sah, perkara yang membatalkan, dan sunnah-sunnah dalam materi {$cleanBab}.";
            $tp[] = "{$babNum}.3 Peserta didik mampu mendemonstrasikan simulasi amaliah ibadah/muamalah pada materi {$cleanBab} secara tertib dan benar.";
            $tp[] = "{$babNum}.4 Peserta didik mampu menyimpulkan hikmah syariat dan merefleksikan nilai ketundukan ibadah dari materi {$cleanBab} dalam rutinitas harian.";
            return $tp;
        }

        if ($sub === 'aqidah') {
            $tp[] = "{$babNum}.1 Peserta didik mampu mengkaji dalil aqli dan naqli yang melandasi pokok materi {$cleanBab} secara terstruktur.";
            $tp[] = "{$babNum}.2 Peserta didik mampu mengidentifikasi nilai-nilai tauhid dan keutamaan akhlak yang terkandung dalam materi {$cleanBab}.";
            $tp[] = "{$babNum}.3 Peserta didik mampu membedakan pemahaman yang lurus dari kekeliruan pemikiran seputar materi {$cleanBab}.";
            $tp[] = "{$babNum}.4 Peserta didik mampu mengaktualisasikan keteladanan akhlakul karimah berbasis materi {$cleanBab} dalam pergaulan sehari-hari.";
            return $tp;
        }

        if ($sub === 'pjok') {
            $tp[] = "{$babNum}.1 Peserta didik mampu menganalisis konsep gerak, teknik dasar, dan prinsip biomekanika sederhana pada aktivitas {$cleanBab}.";
            $tp[] = "{$babNum}.2 Peserta didik mampu mempraktikkan variasi dan kombinasi gerak spesifik {$cleanBab} dengan koordinasi dan kontrol tubuh yang baik.";
            $tp[] = "{$babNum}.3 Peserta didik mampu menerapkan taktik bermain, kepatuhan aturan, serta sportivitas dalam simulasi aktivitas {$cleanBab}.";
            $tp[] = "{$babNum}.4 Peserta didik mampu menunjukkan perilaku bertanggung jawab, kerja sama tim, dan kepedulian terhadap keselamatan diri serta teman.";
            return $tp;
        }

        if ($kategori === 'eksakta') {
            $tp[] = "{$babNum}.1 Peserta didik mampu memahami definisi, sifat-sifat fisis/matematis, dan formula kunci dari materi {$cleanBab}.";
            $tp[] = "{$babNum}.2 Peserta didik mampu melakukan prosedur perhitungan atau investigasi ilmiah terkait {$cleanBab} secara teliti dan sistematis.";
            $tp[] = "{$babNum}.3 Peserta didik mampu memodelkan dan menyelesaikan persoalan kontekstual yang melibatkan materi {$cleanBab}.";
            $tp[] = "{$babNum}.4 Peserta didik mampu memvalidasi keakuratan hasil pemecahan masalah serta mengomunikasikan argumentasi logisnya secara tertulis.";
            return $tp;
        }

        if ($kategori === 'bahasa') {
            $tp[] = "{$babNum}.1 Peserta didik mampu mengidentifikasi informasi penting, ide pokok, dan tujuan komunikatif dari teks materi {$cleanBab}.";
            $tp[] = "{$babNum}.2 Peserta didik mampu membedah unsur kebahasaan, struktur teks, dan pilihan diksi pada materi {$cleanBab} secara analitis.";
            $tp[] = "{$babNum}.3 Peserta didik mampu memproduksi teks fungsional atau sastra terkait {$cleanBab} dengan alur gagasan yang runtut dan efektif.";
            $tp[] = "{$babNum}.4 Peserta didik mampu menyajikan hasil karyanya secara lisan dengan artikulasi jelas serta saling bertukar apresiasi konstruktif.";
            return $tp;
        }

        // Variasi Dinamis Taksonomi Bloom untuk Mapel Umum
        $tp[] = "{$babNum}.1 Peserta didik mampu mengenali konsep dasar, istilah kunci, dan prinsip utama materi {$cleanBab} secara tepat.";
        $tp[] = "{$babNum}.2 Peserta didik mampu menjelaskan keterkaitan antarkonsep serta dinamika fenomena yang terjadi pada materi {$cleanBab}.";
        $tp[] = "{$babNum}.3 Peserta didik mampu mengaplikasikan wawasan materi {$cleanBab} untuk menganalisis studi kasus nyata di lingkungan sekitar.";
        $tp[] = "{$babNum}.4 Peserta didik mampu merumuskan kesimpulan terpadu dan merefleksikan nilai-nilai positif materi {$cleanBab} dalam sikap mandiri.";
        return $tp;
    }

    /**
     * Rumusan Alur Tujuan Pembelajaran (ATP)
     */
    private static function generateAtpBab(string $cleanBab, int $babNum, int $jp, int $pertemuan, string $kategori, string $mapel = ''): string
    {
        $sub = self::detectSubMapel($mapel);
        if ($sub === 'shorof') {
            return "Tahap {$babNum}: Pemahaman kaidah wazan & rumus dasar {$cleanBab} -> Praktik tashrif lisan-tulisan terbimbing -> Analisis aplikasi wazan pada teks Arab/Al-Qur'an ({$jp} JP / {$pertemuan} Pertemuan)";
        }
        if ($sub === 'nahwu') {
            return "Tahap {$babNum}: Penjelasan jabatan kata & kaidah {$cleanBab} -> Analisis tanda i'rab & amil pembentuk -> Latihan tarkib dan pembedahan teks kitab ({$jp} JP / {$pertemuan} Pertemuan)";
        }
        if ($sub === 'fiqih') {
            return "Tahap {$babNum}: Kajian dalil naqli & syarat rukun {$cleanBab} -> Analisis perkara sah-batal -> Simulasi amaliah praktis & pembiasaan adab ({$jp} JP / {$pertemuan} Pertemuan)";
        }
        if ($sub === 'pjok') {
            return "Tahap {$babNum}: Pengenalan gerak spesifik {$cleanBab} -> Latihan repetisi teknik & koordinasi motorik -> Simulasi permainan kompetitif & evaluasi kebugaran ({$jp} JP / {$pertemuan} Pertemuan)";
        }
        if ($kategori === 'bahasa') {
            return "Tahap {$babNum}: Dekonstruksi teks model {$cleanBab} -> Bedah kaidah kebahasaan & struktur -> Konstruksi karya mandiri dan presentasi apresiatif ({$jp} JP / {$pertemuan} Pertemuan)";
        }
        if ($kategori === 'eksakta') {
            return "Tahap {$babNum}: Pengenalan fenomena kontekstual {$cleanBab} -> Eksplorasi konsep dan penemuan rumus -> Latihan pemodelan soal & validasi solusi ({$jp} JP / {$pertemuan} Pertemuan)";
        }
        return "Tahap {$babNum}: Orientasi konsep esensial {$cleanBab} -> Diskusi studi kasus & eksplorasi kelompok -> Presentasi temuan & asesmen sumatif ({$jp} JP / {$pertemuan} Pertemuan)";
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
        array $tpItems,
        string $userModel = '',
        string $userTarget = '',
        string $userPustaka = ''
    ): array {
        $sub = self::detectSubMapel($mapel);

        if ($sub === 'shorof') {
            // Khusus Morfologi Bahasa Arab (Shorof / Tashrif) - Tanpa Dalil Naqli Syariat
            $modelPembelajaran = 'Metode Qiyasiyah (Deduktif-Drill), Latihan Tashrif Mandiri & Berpasangan';
            $profilPancasila   = ['Mandiri', 'Bernalar Kritis', 'Beriman & Bertakwa kepada Tuhan YME'];
            $kompetensiAwal    = "Peserta didik telah mampu membaca tulisan Arab berharakat dengan lancar dan mengenal jenis kata dasar (fi'il, isim, huruf).";
            $pemahamanBermakna = "Menguasai kaidah {$cleanBab} menjadi pondasi utama untuk membongkar susunan bentuk kata bahasa Arab, memahami perubahan makna kata, dan ketelitian menelaah literatur Islam.";
            $pertanyaanPemantik= "1. Bagaimana pola wazan pada materi {$cleanBab} memandu kita merangkai berbagai variasi bentuk kata?\n2. Mengapa perubahan harakat dan huruf pada materi {$cleanBab} dapat melahirkan arti kata yang berbeda?";
            $saranaPrasarana   = 'Kitab Matan Al-Amtsilah At-Tashrifiyyah/Modul Shorof, Papan Tulis, Tabel Wazan, dan alat tulis';
            $targetSiswa       = 'Peserta didik reguler / santri (30 siswa)';

            $pendahuluan       = "1. Guru mengucapkan salam hangat kepada peserta ajar.\n2. Guru memeriksa presensi, kesiapan fisik, dan perlengkapan kitab santri.\n3. Apersepsi: Guru mengaitkan wazan dasar materi {$cleanBab} dengan kosakata yang telah dipelajari sebelumnya.\n4. Guru menyampaikan tujuan pembelajaran hari ini.";
            $kegiatanInti      = "1. Pengenalan Pola: Guru menuliskan rumus wazan pokok dan mawzun materi {$cleanBab} di papan tulis.\n2. Latihan Repetisi (Drill): Guru membimbing santri melafalkan tashrif secara bersama-sama hingga fasih dan berirama.\n3. Praktik Mandiri: Peserta didik menyusun bentuk tashrif untuk kata-kata baru berdasarkan timbangan {$cleanBab} pada lembar kerja.\n4. Analisis Kata: Peserta didik mengidentifikasi kemunculan pola {$cleanBab} dari teks bacaan Arab sederhana.\n5. Ta'kid & Umpan Balik: Guru memeriksa ketepatan pola pembentukan kata dan mengoreksi kekeliruan secara interaktif.";
            $penutup           = "1. Peserta didik bersama guru menyimpulkan rumus wazan materi {$cleanBab}.\n2. Guru memberikan latihan mandiri pembiasaan tashrif.\n3. Kelas diakhiri dengan doa kafaratul majelis dan salam penutup.";

            $asesmenDiag       = "Pengecekan kelancaran membaca kosakata Arab dan pengenalan huruf asal tiga huruf (fa'-'ain-lam).";
            $asesmenFormat     = 'Kuis lisan kelancaran tashrifan berirama dan lembar latihan pembentukan kata.';
            $asesmenSumat      = "Ujian tulis pembentukan kata sesuai wazan dan tes lisan tashrif materi {$cleanBab}.";
            $lkpdContent       = "LEMBAR KERJA SANTRI (LKS) - MORFOLOGI " . strtoupper($cleanBab) . ":\n1. Tuliskan wazan pokok materi {$cleanBab} beserta pola tashrifannya secara lengkap!\n2. Ubahlah 3 kata dasar (fi'il tsulatsi) berikut mengikuti rumus {$cleanBab}!\n3. Tentukan bentuk shighah dan arti perubahan kata yang terbentuk!";
            $glosarium         = "Wazan: Rumus atau timbangan baku perubahan bentuk kata dalam morfologi bahasa Arab.\nMawzun: Kata bahasa Arab yang ditimbang atau dibentuk mengikuti wazan tertentu.\nShighah: Bentuk kata yang menunjukkan kategori gramatikal (misal: fi'il madhi, fi'il mudhari', isim fa'il).\nTashrif: Proses pengubahan suatu bentuk kata menjadi berbagai ragam bentuk turunan untuk melahirkan variasi makna.";
            $daftarPustaka     = "1. Kitab Matan Al-Amtsilah At-Tashrifiyyah karya KH. Muhammad Ma'shum bin Ali.\n2. Kitab Qawa'id Ash-Shorf / Modul Morfologi Bahasa Arab Standar Madrasah.";

        } elseif ($sub === 'nahwu') {
            // Khusus Sintaksis Bahasa Arab (Nahwu / I'rab) - Tanpa Dalil Naqli Syariat
            $modelPembelajaran = 'Metode Istiqra\'iyyah (Induktif) & Analisis Tarkib Kontekstual';
            $profilPancasila   = ['Bernalar Kritis', 'Mandiri', 'Beriman & Bertakwa kepada Tuhan YME'];
            $kompetensiAwal    = "Peserta didik memahami pembagian kalam (isim, fi'il, huruf) dan harakat dasar bahasa Arab.";
            $pemahamanBermakna = "Memahami kaidah {$cleanBab} melatih ketelitian logika berpikir dalam membedah susunan kalimat Arab serta menjaga lisan dari kesalahan tata bahasa.";
            $pertanyaanPemantik= "1. Mengapa tanda akhir kata pada materi {$cleanBab} bisa berubah ketika menduduki posisi tertentu dalam kalimat?\n2. Bagaimana cara membedakan peran kata pada topik ini dalam sebuah kalimat utuh?";
            $saranaPrasarana   = 'Kitab Al-Ajurrumiyyah/Buku Nahwu, Papan Tulis, Lembar Kerja Tarkib, dan alat tulis';
            $targetSiswa       = 'Peserta didik reguler / santri (30 siswa)';

            $pendahuluan       = "1. Guru mengucapkan salam hangat kepada peserta ajar.\n2. Guru memeriksa presensi dan kerapian kelas.\n3. Apersepsi: Guru menyajikan contoh kalimat sederhana terkait topik {$cleanBab}.\n4. Guru menyampaikan tujuan pembelajaran hari ini.";
            $kegiatanInti      = "1. Eksplorasi Contoh: Guru menuliskan beberapa kalimat model materi {$cleanBab} di papan tulis.\n2. Analisis Induktif: Peserta didik mengamati tanda akhir kata dan faktor penyebab perubahan i'rabnya.\n3. Perumusan Kaidah: Guru bersama santri menyimpulkan kaidah sintaksis dari materi {$cleanBab}.\n4. Latihan I'rab: Santri berlatih menentukan kedudukan kata (jabatan kalimat) pada contoh kalimat baru.\n5. Verifikasi & Penguatan: Guru memberikan bimbingan pada contoh tarkib yang membutuhkan penjelasan lebih mendalam.";
            $penutup           = "1. Santri merangkum kaidah nahwu yang telah dibahas bersama guru.\n2. Guru memberikan tugas pembiasaan membaca kalimat berharakat secara mandiri.\n3. Pembelajaran ditutup dengan doa bersama dan salam.";

            $asesmenDiag       = "Tanya jawab lisan penentuan jenis kata (isim/fi'il/huruf) pada kalimat acak.";
            $asesmenFormat     = "Penilaian unjuk kerja analisis i'rab lisan dan pengerjaan lembar kerja tarkib.";
            $asesmenSumat      = "Ujian tulis analisis struktur kalimat (i'rab) dan kaidah materi {$cleanBab}.";
            $lkpdContent       = "LEMBAR KERJA SANTRI (LKS) TARKIB - " . strtoupper($cleanBab) . ":\n1. Bacalah kalimat-kalimat berikut dengan harakat yang tepat!\n2. Tentukan kedudukan (tarkib) dari kata yang digarisbawahi beserta tanda i'rabnya!\n3. Jelaskan amil yang memengaruhi perubahan tanda i'rab tersebut!";
            $glosarium         = "Tarkib: Susunan hubungan antarkata dalam membentuk kalimat sempurna (kalam).\nAmil: Unsur kata yang memengaruhi dan mengubah tanda harakat akhir kata lainnya.\nI'rab: Perubahan tanda harakat akhir kata karena pengaruh perbedaan amil yang masuk.";
            $daftarPustaka     = "1. Kitab Matan Al-Ajurrumiyyah karya Abu Abdillah Muhammad bin Dawud ash-Shanhaji.\n2. Buku Panduan Ilmu Nahwu Tingkat Menengah.";

        } elseif ($sub === 'bahasa_arab') {
            // Khusus Kemahiran Berbahasa Arab (Muhadatsah / Bahasa Arab)
            $modelPembelajaran = 'Direct Method (Thariqah Mubasyarah) & Communicative Language Teaching (CLT)';
            $profilPancasila   = ['Mandiri', 'Bernalar Kritis', 'Berkebinekaan Global'];
            $kompetensiAwal    = "Peserta didik menguasai kosakata dasar (mufradat) harian bahasa Arab.";
            $pemahamanBermakna = "Menguasai materi {$cleanBab} melatih kepercayaan diri dalam berkomunikasi aktif berbahasa Arab untuk keperluan ibadah dan pergaulan internasional.";
            $pertanyaanPemantik= "1. Ungkapan apa yang paling sering kita gunakan saat berinteraksi pada tema {$cleanBab}?\n2. Bagaimana cara menyusun kalimat percakapan yang lugas dan tepat pada topik ini?";
            $saranaPrasarana   = 'Buku Bahasa Arab Siswa, Kartu Mufradat/Flashcard, Audio Percakapan, Papan Tulis, dan alat tulis';
            $targetSiswa       = 'Peserta didik reguler / santri (30 siswa)';

            $pendahuluan       = "1. Guru mengucapkan salam hangat kepada peserta ajar.\n2. Guru memeriksa kehadiran dan kesiapan belajar siswa.\n3. Apersepsi: Guru menyapa menggunakan beberapa kosakata kunci materi {$cleanBab}.\n4. Guru menyampaikan tujuan pembelajaran hari ini.";
            $kegiatanInti      = "1. Thariqah Mubasyarah: Guru memperdengarkan pelafalan kosakata dan contoh kalimat materi {$cleanBab}.\n2. Repetisi & Drill: Peserta didik menirukan intonasi dan pengucapan ungkapan Arab secara berkelompok.\n3. Praktik Percakapan (Hiwar): Peserta didik berpasangan mempraktikkan dialog bertema {$cleanBab}.\n4. Produksi Teks Sederhana: Peserta didik menulis kalimat ungkapan mandiri pada lembar kerja.\n5. Umpan Balik: Guru mengoreksi makhraj huruf dan kelayakan susunan bahasa.";
            $penutup           = "1. Peserta didik merangkum mufradat esensial yang dipelajari hari ini.\n2. Guru memberikan tugas pembiasaan menyimak audio bahasa Arab.\n3. Kelas diakhiri dengan doa kafaratul majelis dan salam.";

            $asesmenDiag       = 'Kuis lisan pemetaan mufradat dasar terkait topik.';
            $asesmenFormat     = 'Observasi keaktifan percakapan lisan dan ketepatan pelafalan makhraj.';
            $asesmenSumat      = "Tes unjuk kerja hiwar (percakapan lisan) dan tes tulis mufradat materi {$cleanBab}.";
            $lkpdContent       = "LEMBAR KERJA BAHASA ARAB - TEMA " . strtoupper($cleanBab) . ":\n1. Pasangkan kosakata berikut dengan maknanya yang tepat!\n2. Lengkapilah rumpang dialog percakapan berikut bersama teman sebangkumu!\n3. Susunlah 2 kalimat sempurna menggunakan kosakata materi {$cleanBab}!";
            $glosarium         = "Mufradat: Perbendaharaan kosakata bahasa Arab.\nHiwar: Percakapan atau dialog interaktif dua arah.\nFahmul Masmu': Keterampilan menyimak dan memahami pesan lisan bahasa Arab.";
            $daftarPustaka     = "1. Buku Siswa Bahasa Arab MTs/SMP Islam Kemendikbudristek/Kemenag.\n2. Kamus Praktis Bahasa Arab Kontemporer.";

        } elseif ($sub === 'tarikh') {
            // Khusus Sejarah Kebudayaan Islam / SKI / Tarikh
            $modelPembelajaran = 'Inquiry-Based Learning & Telaah Narasi Sejarah';
            $profilPancasila   = ['Berkebinekaan Global', 'Akhlak Mulia', 'Bernalar Kritis'];
            $kompetensiAwal    = "Peserta didik mengenal garis besar periodisasi sejarah kenabian.";
            $pemahamanBermakna = "Mempelajari sejarah {$cleanBab} memberikan inspirasi keteladanan, memperluas wawasan peradaban, dan menumbuhkan kecintaan terhadap perjuangan dakwah Islam.";
            $pertanyaanPemantik= "1. Nilai keteladanan apa yang dapat kita petik dari peristiwa {$cleanBab} untuk diterapkan di masa kini?\n2. Bagaimana latar belakang peristiwa tersebut membentuk arah perkembangan peradaban manusia?";
            $saranaPrasarana   = 'Buku Siswa Sejarah Kebudayaan Islam, Garis Waktu/Peta Peradaban, Proyektor/Papan Tulis, dan alat tulis';
            $targetSiswa       = 'Peserta didik reguler / santri (30 siswa)';

            $pendahuluan       = "1. Guru mengucapkan salam hangat kepada peserta ajar.\n2. Guru memeriksa presensi dan suasana kelas.\n3. Apersepsi: Guru menampilkan gambar/peta peristiwa terkait materi {$cleanBab}.\n4. Guru menyampaikan tujuan pembelajaran dan alur aktivitas.";
            $kegiatanInti      = "1. Telaah Sumber: Peserta didik mencermati uraian peristiwa materi {$cleanBab} dari buku referensi.\n2. Diskusi Kelompok: Peserta didik mendiskusikan sebab-akibat, tokoh kunci, dan dinamika peristiwa.\n3. Pemetaan Alur: Setiap kelompok menyusun garis waktu (timeline) perjalanan peristiwa {$cleanBab}.\n4. Presentasi Ibrah: Perwakilan kelompok memaparkan hikmah keteladanan di depan kelas.\n5. Ta'kid & Refleksi: Guru meluruskan fakta sejarah dan menegaskan pesan moral keteladanan.";
            $penutup           = "1. Siswa menyimpulkan poin kronologis dan ibrah materi {$cleanBab}.\n2. Guru memberikan pesan refleksi keteladanan.\n3. Kelas ditutup dengan doa bersama dan salam.";

            $asesmenDiag       = 'Tanya jawab pemantik wawasan awal siswa seputar latar belakang sejarah.';
            $asesmenFormat     = 'Observasi keaktifan diskusi telaah sejarah dan lembar kerja garis waktu.';
            $asesmenSumat      = "Tes tertulis pemahaman kronologi peristiwa dan analisis ibrah materi {$cleanBab}.";
            $lkpdContent       = "LEMBAR KERJA TELAAH SEJARAH - " . strtoupper($cleanBab) . ":\n1. Susunlah urutan kronologis 3 peristiwa penting pada materi {$cleanBab}!\n2. Sebutkan tokoh kunci yang berperan serta jelaskan keteladanan sikapnya!\n3. Tuliskan 2 ibrah (hikmah berharga) yang relevan untuk kehidupan generasi muda saat ini!";
            $glosarium         = "Ibrah: Pelajaran atau hikmah mendalam yang dipetik dari suatu peristiwa masa lalu.\nKronologi: Urutan runtut kejadian sejarah berdasarkan waktu terjadinya.\nPeradaban: Kemajuan kebudayaan lahiriah dan batiniah suatu umat.";
            $daftarPustaka     = "1. Buku Guru & Siswa SKI / Sejarah Kebudayaan Islam MTs/SMP Kemenag.\n2. Kitab Tarikh Peradaban Islam Standar Madrasah.";

        } elseif ($sub === 'quran_hadits') {
            // Khusus Al-Qur'an Hadits / Tajwid / Tahfidz
            $modelPembelajaran = 'Metode Talaqqi, Musyafahah, & Pembelajaran Kontekstual';
            $profilPancasila   = ['Beriman & Bertakwa kepada Tuhan YME', 'Akhlak Mulia', 'Mandiri'];
            $kompetensiAwal    = "Peserta didik mampu membaca ayat Al-Qur'an dan memahami huruf hijaiyah.";
            $pemahamanBermakna = "Mempelajari {$cleanBab} menumbuhkan rasa cinta kepada kitab suci Al-Qur'an dan As-Sunnah, memperbaiki kualitas tilawah, serta menuntun perilaku berakhlak mulia.";
            $pertanyaanPemantik= "1. Mengapa penerapan kaidah tajwid pada materi {$cleanBab} sangat menentukan ketepatan makna ayat?\n2. Bagaimana cara kita mengamalkan pesan moral dari materi ini dalam keseharian?";
            $saranaPrasarana   = 'Mushaf Al-Qur\'an, Buku Teks Siswa, Papan Tulis, dan alat tulis';
            $targetSiswa       = 'Peserta didik reguler / santri (30 siswa)';

            $pendahuluan       = "1. Guru mengucapkan salam hangat kepada peserta ajar.\n2. Guru memeriksa presensi, kesiapan fisik, dan mushaf siswa.\n3. Apersepsi: Guru melantunkan cuplikan ayat/hadits terkait materi {$cleanBab}.\n4. Guru menyampaikan tujuan pembelajaran hari ini.";
            $kegiatanInti      = "1. Talaqqi: Guru mencontohkan bacaan tartil sesuai kaidah tajwid materi {$cleanBab}.\n2. Musyafahah: Peserta didik membaca bergantian dengan bimbingan dan koreksi langsung dari guru.\n3. Telaah Kaidah: Peserta didik menandai contoh hukum bacaan dan mendiskusikan artinya pada lembar kerja.\n4. Pendalaman Makna: Peserta didik mengidentifikasi kandungan pesan utama dari ayat/hadits terkait.\n5. Penguatan: Guru memberikan apresiasi atas ketepatan makhraj dan penjelasan hikmah ayat.";
            $penutup           = "1. Siswa bersama guru menyimpulkan kaidah dan pesan pokok materi {$cleanBab}.\n2. Guru berpesan untuk melatih tilawah secara istiqamah di rumah.\n3. Kelas diakhiri dengan doa kafaratul majelis dan salam.";

            $asesmenDiag       = 'Pengecekan awal kelancaran tilawah dan makharijul huruf santri.';
            $asesmenFormat     = 'Penilaian unjuk kerja tilawah berpasangan dan lembar identifikasi tajwid.';
            $asesmenSumat      = "Ujian lisan membaca tartil dan tes tertulis pemahaman hukum kaidah materi {$cleanBab}.";
            $lkpdContent       = "LEMBAR KERJA AL-QUR'AN HADITS - " . strtoupper($cleanBab) . ":\n1. Tuliskan kembali ayat/hadits pilihan terkait materi {$cleanBab} dengan khat yang rapi!\n2. Identifikasi 3 hukum kaidah yang terdapat pada ayat tersebut beserta alasannya!\n3. Tuliskan pesan kandungan utama dari ayat/hadits tersebut!";
            $glosarium         = "Tartil: Membaca Al-Qur'an secara perlahan, tenang, dan tepat sesuai kaidah tajwid.\nTalaqqi: Metode belajar langsung berhadapan dengan guru yang ahli.\nMusyafahah: Belajar membaca secara lisan dengan memperhatikan gerakan bibir guru.";
            $daftarPustaka     = "1. Mushaf Al-Qur'an Standar Indonesia.\n2. Buku Guru & Siswa Al-Qur'an Hadits MTs/SMP Kemendikbudristek/Kemenag.";

        } elseif ($sub === 'fiqih') {
            // Khusus Fiqih & Ibadah (Di sinilah Dalil Naqli, Rukun & Syarat Sah relevan)
            $modelPembelajaran = 'Direct Instruction, Simulasi Praktik Amaliah, & Kajian Masail';
            $profilPancasila   = ['Beriman & Bertakwa kepada Tuhan YME', 'Akhlak Mulia', 'Mandiri', 'Bernalar Kritis'];
            $kompetensiAwal    = "Peserta didik memahami dasar-dasar syariat Islam terkait thaharah dan ibadah harian.";
            $pemahamanBermakna = "Mempelajari {$cleanBab} menumbuhkan kesadaran bahwa ketaatan menjalankan syariat Allah secara benar mendatangkan ketenangan batin, keberkahan ilmu, dan keselamatan dunia-akhirat.";
            $pertanyaanPemantik= "1. Mengapa materi {$cleanBab} sangat menentukan keabsahan dan kualitas ibadah kita sehari-hari?\n2. Bagaimana cara kita membentengi diri agar tidak keliru dalam mengamalkan {$cleanBab} di era modern?";
            $saranaPrasarana   = 'Kitab Rujukan Fiqih/Buku Pegangan Siswa, Papan Tulis, dan alat tulis';
            $targetSiswa       = 'Peserta didik reguler / santri (30 siswa)';

            $pendahuluan       = "1. Guru mengucapkan salam hangat kepada peserta ajar.\n2. Guru memeriksa presensi, kesiapan fisik, serta kerapian busana santri.\n3. Apersepsi: Guru mengaitkan materi {$cleanBab} dengan kebiasaan ibadah santri sehari-hari.\n4. Guru menyampaikan tujuan pembelajaran hari ini dan pentingnya menjaga niat ikhlas menuntut ilmu.";
            $kegiatanInti      = "1. Stimulasi: Guru membacakan dalil naqli pokok dan memaparkan rukun materi {$cleanBab} di papan tulis.\n2. Telaah Kaidah: Peserta didik menelaah syarat sah, perkara yang membatalkan, dan sunnah-sunnah pada lembar kerja.\n3. Bimbingan Penyelidikan: Guru berkeliling memberikan tas-hih (koreksi kaidah) pada kelompok yang berdiskusi.\n4. Simulasi Amaliah: Perwakilan santri mendemonstrasikan tata cara amaliah materi {$cleanBab} di depan kelas.\n5. Refleksi & Penguatan: Guru memberikan ta'qid (penguatan konsep hukum syariat) serta mengapresiasi keaktifan seluruh santri.";
            $penutup           = "1. Peserta didik dibimbing guru menyimpulkan kaidah utama dari materi {$cleanBab}.\n2. Guru memberikan pesan mutaba'ah amaliah untuk dipraktikkan langsung di asrama/rumah.\n3. Guru menutup pembelajaran dengan membaca hamdalah dan doa kafaratul majelis.";

            $asesmenDiag       = 'Tanya jawab lisan tentang pengalaman santri mengamalkan materi pada hari-hari sebelumnya.';
            $asesmenFormat     = 'Observasi keaktifan diskusi, penilaian unjuk kerja simulasi praktik, dan lembar kerja santri.';
            $asesmenSumat      = "Ujian tulis pemahaman kaidah syariat dan tes lisan penguasaan dalil pokok materi {$cleanBab}.";
            $lkpdContent       = "LEMBAR KERJA SANTRI (LKS) FIQIH - " . strtoupper($cleanBab) . ":\n1. Tuliskan 1 dalil naqli utama yang mendasari materi {$cleanBab} beserta artinya!\n2. Uraikan syarat sah dan rukun-rukun yang harus dipenuhi dalam materi {$cleanBab}!\n3. Selesaikan analisis kasus amaliah harian berikut bersama teman sekelompokmu!";
            $glosarium         = "Dalil Naqli: Landasan hukum syariat yang bersumber dari Al-Qur'an atau As-Sunnah ash-Shahihah.\nRukun: Bagian pokok dari suatu ibadah yang wajib dikerjakan dan menentukan keabsahannya.\nSyarat Sah: Hal-hal yang harus terpenuhi sebelum atau saat ibadah dilakukan agar ibadah tersebut diterima secara syar'i.";
            $daftarPustaka     = "1. Tim Kemenag RI. (2022). Buku Siswa & Guru Fikih Kelas {$kelas} MTs/SMP. Jakarta.\n2. Kitab Fikih Standar Madrasah & Pesantren (Safinatun Naja / Fathul Qarib).";

        } elseif ($sub === 'aqidah') {
            // Khusus Aqidah Akhlak
            $modelPembelajaran = 'Discovery Learning & Pembiasaan Reflektif';
            $profilPancasila   = ['Beriman & Bertakwa kepada Tuhan YME', 'Akhlak Mulia', 'Mandiri', 'Bernalar Kritis'];
            $kompetensiAwal    = "Peserta didik mengenal rukun iman dan nilai-nilai akhlak terpuji dalam keluarga.";
            $pemahamanBermakna = "Memahami materi {$cleanBab} memperkokoh keyakinan tauhid, membentengi diri dari keraguan akidah, dan membentuk pribadi yang berbudi pekerti luhur.";
            $pertanyaanPemantik= "1. Mengapa keyakinan terhadap materi {$cleanBab} menjadi landasan utama dari semua perbuatan baik kita?\n2. Bagaimana keteladanan pada materi ini dapat kita wujudkan dalam interaksi bersama sesama teman?";
            $saranaPrasarana   = 'Buku Siswa Aqidah Akhlak, Papan Tulis, Lembar Studi Kasus Kasih Sayang/Keimanan, dan alat tulis';
            $targetSiswa       = 'Peserta didik reguler / santri (30 siswa)';

            $pendahuluan       = "1. Guru mengucapkan salam hangat kepada peserta ajar.\n2. Guru memeriksa presensi dan kenyamanan ruang kelas.\n3. Apersepsi: Guru mengaitkan pokok materi {$cleanBab} dengan fenomena kebesaran ciptaan Allah SWT.\n4. Guru menyampaikan tujuan pembelajaran hari ini.";
            $kegiatanInti      = "1. Eksplorasi Dalil: Guru menyajikan dalil pokok dan intisari materi {$cleanBab} di hadapan santri.\n2. Telaah Kelompok: Santri mendiskusikan bukti-bukti keimanan dan contoh sikap mulia pada lembar aktivitas.\n3. Dialog Reflektif: Guru memandu dialog interaktif tentang bagaimana menangkal keraguan akidah.\n4. Presentasi: Perwakilan santri menyampaikan komitmen keteladanan akhlak di depan kelas.\n5. Penguatan: Guru memberikan ta'kid nilai keimanan dan nasihat keluhuran budi pekerti.";
            $penutup           = "1. Santri merumuskan intisari keyakinan dan sikap terpuji dari materi {$cleanBab}.\n2. Guru memberikan pesan pembiasaan akhlakul karimah di lingkungan asrama/rumah.\n3. Kelas ditutup dengan doa kafaratul majelis dan salam.";

            $asesmenDiag       = 'Tanya jawab pemantik wawasan dasar tauhid dan adab santri.';
            $asesmenFormat     = 'Observasi sikap santun dalam diskusi, lembar refleksi diri, dan keaktifan kelas.';
            $asesmenSumat      = "Ujian tulis pemahaman dalil aqidah dan tes uraian keteladanan akhlak materi {$cleanBab}.";
            $lkpdContent       = "LEMBAR KERJA SANTRI (LKS) AQIDAH - " . strtoupper($cleanBab) . ":\n1. Tuliskan dalil naqli/aqli yang melandasi materi {$cleanBab} beserta intisari artinya!\n2. Jelaskan 2 hikmah menanamkan keyakinan materi ini dalam kehidupan sehari-hari!\n3. Rumuskan 3 aksi nyata akhlak mulia yang akan kamu amalkan mulai pekan ini!";
            $glosarium         = "Tauhid: Pengesaan Allah SWT dalam segala sifat ketuhanan dan peribadatan.\nAkhlakul Karimah: Perilaku terpuji yang bersumber dari dorongan jiwa yang bersih sesuai syariat.\nDalil Aqli: Landasan pemikiran rasional yang logis dan tidak bertentangan dengan wahyu.";
            $daftarPustaka     = "1. Buku Siswa & Guru Akidah Akhlak MTs/SMP Kemendikbudristek/Kemenag.\n2. Kitab Rujukan Aqidah Standar Madrasah & Pesantren.";

        } elseif ($sub === 'pjok' || $kategori === 'olahraga') {
            // Khusus Olahraga / Penjas / PJOK
            $modelPembelajaran = 'Direct Instruction Praktik Gerak & Game-Based Learning';
            $profilPancasila   = ['Mandiri', 'Gotong Royong', 'Bernalar Kritis'];
            $kompetensiAwal    = "Peserta didik memiliki kemampuan motorik dasar dan kondisi fisik yang siap beraktivitas jasmani.";
            $pemahamanBermakna = "Mempraktikkan aktivitas {$cleanBab} melatih koordinasi fisik, disiplin mental, sportivitas, serta menanamkan pola hidup bugar sepanjang hayat.";
            $pertanyaanPemantik= "1. Mengapa teknik dasar pada materi {$cleanBab} perlu dilakukan secara benar dan teratur?\n2. Bagaimana sikap sportivitas dan kerja sama menentukan keberhasilan aktivitas ini?";
            $saranaPrasarana   = 'Lapangan Olahraga/Halaman Sekolah, Peluit, Stopwatch, Bola/Alat Praktik Terkait, Lembar Observasi Gerak';
            $targetSiswa       = 'Peserta didik reguler / tipikal (30 siswa)';

            $pendahuluan       = "1. Guru mengucapkan salam hangat kepada peserta ajar.\n2. Guru memeriksa kesiapan fisik, pakaian olahraga, dan presensi siswa.\n3. Apersepsi: Guru menjelaskan manfaat materi {$cleanBab} bagi kebugaran tubuh.\n4. Guru memimpin pemanasan statis dan dinamis bersama seluruh siswa.";
            $kegiatanInti      = "1. Demonstrasi Gerak: Guru mencontohkan teknik gerak spesifik materi {$cleanBab} di hadapan siswa.\n2. Latihan Berulang: Siswa melakukan pengulangan teknik dasar secara bertahap dan teratur.\n3. Praktik Berpasangan/Kelompok: Siswa saling mengamati dan mengoreksi keterampilan gerak teman.\n4. Simulasi Permainan: Siswa menerapkan teknik yang dipelajari ke dalam situasi permainan sederhana bernuansa sportif.\n5. Penguatan: Guru memberikan apresiasi atas kedisiplinan dan perbaikan teknik gerak siswa.";
            $penutup           = "1. Guru memimpin pendinginan (cooling down) bersama siswa.\n2. Refleksi: Siswa menyampaikan kendala gerak yang dirasakan saat latihan.\n3. Guru berpesan untuk menjaga hidrasi dan istirahat yang cukup, ditutup dengan doa bersama.";

            $asesmenDiag       = 'Pengecekan kesiapan kondisi fisik dan riwayat kesehatan siswa sebelum praktik.';
            $asesmenFormat     = 'Observasi rubrik ketepatan gerak dasar dan penilaian sikap sportivitas.';
            $asesmenSumat      = "Tes unjuk kerja keterampilan gerak spesifik materi {$cleanBab}.";
            $lkpdContent       = "LEMBAR OBSERVASI GERAK PJOK - " . strtoupper($cleanBab) . ":\n1. Amatilah teknik gerak yang dilakukan oleh pasangan latihanmu!\n2. Berikan tanda centang pada rubrik indikator gerak yang berhasil dieksekusi secara benar!\n3. Tuliskan 1 saran perbaikan untuk menyempurnakan teknik gerak temanmu!";
            $glosarium         = "Biomekanika: Prinsip ilmu gerak tubuh manusia yang efisien dan aman dari cedera.\nKoordinasi Motorik: Keterpaduan kerja otot dan sistem saraf dalam menghasilkan gerakan yang presisi.\nSportivitas: Sikap ksatria, jujur, menghormati lawan, dan patuh pada aturan permainan.";
            $daftarPustaka     = "1. Kemendikbudristek. (2022). Buku Panduan Guru PJOK SMP Kelas {$kelas}. Jakarta.\n2. Buku Standar Pelatihan Kebugaran Jasmani Remaja.";

        } elseif ($kategori === 'bahasa') {
            $modelPembelajaran = 'Genre-Based Approach (Pembelajaran Berbasis Teks) & Cooperative Learning';
            $profilPancasila   = ['Bernalar Kritis', 'Kreatif', 'Gotong Royong', 'Mandiri'];
            $kompetensiAwal    = "Peserta didik mampu membaca lancar, memahami ide pokok paragraf, serta memiliki perbendaharaan kata dasar bahasa formal.";
            $pemahamanBermakna = "Penguasaan materi {$cleanBab} melatih kepekaan berbahasa yang santun, kemampuan berpikir kritis menyaring informasi, serta keterampilan berkomunikasi yang persuasif.";
            $pertanyaanPemantik= "1. Apa yang membedakan sebuah teks materi {$cleanBab} yang berkualitas dengan teks yang membosankan atau membingungkan pembaca?\n2. Mengapa kita perlu memilih kosa kata yang tepat saat menyampaikan gagasan pada topik ini?";
            $saranaPrasarana   = 'Buku Teks Siswa, Tayangan Contoh Teks / Media Audio-Visual, Proyektor/Papan Tulis, LKPD Analisis Teks.';
            $targetSiswa       = 'Peserta didik reguler / tipikal (30 siswa rombel campuran)';

            $pendahuluan       = "1. Guru mengucapkan salam hangat kepada peserta ajar.\n2. Guru memantik rasa ingin tahu siswa dengan membacakan cuplikan teks menarik terkait {$cleanBab}.\n3. Guru menyampaikan tujuan pembelajaran, garis besar kegiatan, serta kriteria penilaian karya siswa.";
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
            $targetSiswa       = 'Peserta didik reguler / tipikal (30 siswa)';

            $pendahuluan       = "1. Guru mengucapkan salam hangat kepada peserta ajar.\n2. Apersepsi: Guru menyajikan sebuah masalah nyata yang paradoks atau menarik terkait {$cleanBab}.\n3. Guru mengutarakan tujuan pembelajaran dan memotivasi siswa tentang manfaat materi ini dalam karier dan kehidupan.";
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
            $targetSiswa       = 'Peserta didik reguler / tipikal (30 siswa)';

            $pendahuluan       = "1. Guru mengucapkan salam hangat kepada peserta ajar.\n2. Apersepsi: Guru mengaitkan materi {$cleanBab} dengan berita terkini atau peristiwa di lingkungan sekitar siswa.\n3. Guru menyampaikan tujuan pembelajaran dan gambaran proyek/aktivitas yang akan dikerjakan bersama.";
            $kegiatanInti      = "1. Eksplorasi Fenomena: Siswa mengamati tayangan video / membaca artikel studi kasus materi {$cleanBab}.\n2. Kolaborasi Tim: Siswa berdiskusi dalam kelompok menganalisis faktor penyebab, dinamika, dan alternatif solusi.\n3. Bimbingan Fasilitator: Guru mendampingi jalannya diskusi kelompok dan mendorong partisipasi aktif setiap anggota.\n4. Penyajian Gagasan: Masing-masing kelompok mempresentasikan resume hasil telaah kasus di hadapan kelas.\n5. Refleksi Bersama: Guru dan siswa menyimpulkan nilai moral, hikmah sosial, dan langkah aksi nyata yang dapat diambil.";
            $penutup           = "1. Siswa merangkum poin-poin refleksi materi {$cleanBab} yang diperoleh hari ini.\n2. Guru memberikan penguatan karakter kebinekaan dan kepedulian lingkungan.\n3. Kelas diakhiri dengan doa bersama dan salam penutup.";

            $asesmenDiag       = 'Pertanyaan pemantik lisan tentang wawasan awal siswa mengenai topik terkait.';
            $asesmenFormat     = 'Penilaian keaktifan diskusi, kualitas argumen lembar kerja kelompok, dan sikap gotong royong.';
            $asesmenSumat      = "Penilaian portofolio laporan telaah kasus atau tes tertulis pemahaman konsep materi {$cleanBab}.";
            $lkpdContent       = "LEMBAR KERJA PESERTA DIDIK (LKPD) TELAAH " . strtoupper($cleanBab) . ":\n1. Bacalah rangkuman artikel kasus yang tersedia!\n2. Identifikasi 3 faktor utama yang melatarbelakangi kasus tersebut dan diskusikan dampaknya!\n3. Rumuskan 2 rekomendasi solusi yang realistis dan dapat diterapkan!";
            $glosarium         = "{$cleanBab}: Topik kajian utama dalam mata pelajaran {$mapel}.\nDinamika Sosial: Perubahan dan interaksi yang terus berkembang di tengah masyarakat.\nGotong Royong: Sikap kerja bersama untuk mencapai tujuan kemaslahatan bersama.";
            $daftarPustaka     = "1. Kemendikbudristek. (2022). Buku Panduan Pendidik & Siswa {$mapel} Kelas {$kelas}. Jakarta.\n2. Sumber Belajar Lingkungan & Ensiklopedia Pendukung.";
        }

        // Terapkan Kustomisasi Pengguna jika Diisi
        if (!empty($userModel)) {
            $modelPembelajaran = $userModel;
        }
        if (!empty($userTarget)) {
            $targetSiswa = $userTarget;
        }
        if (!empty($userPustaka)) {
            $daftarPustaka = $userPustaka;
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

