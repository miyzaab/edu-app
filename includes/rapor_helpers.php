<?php
/**
 * HELPER DESKRIPSI & KOMPETENSI RAPOR KURIKULUM MERDEKA
 * SMP Islamic School of Minhaj Al-Ilmi
 */

if (!function_exists('getMateriMapelAas')) {
    /**
     * Mengambil daftar materi / tujuan pembelajaran (TP) mapel untuk Rapor AAS
     */
    function getMateriMapelAas(string $mapelName, PDO $pdo, int $kelasId = 0, string $semester = 'Ganjil'): string
    {
        // 1. Coba cari dari data Perangkat Ajar yang sudah ada
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

        // 2. Fallback kamus kompetensi baku Kurikulum Merdeka SMP (Fase D)
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
            return "tata bahasa dasar, ungkapan ta'aruf harian, penguasaan mufrodat tematik, serta pelafalan dan pemahaman bacaan sederhana";
        } elseif (strpos($lower, 'tahfidz') !== false || strpos($lower, 'qur') !== false) {
            return "kelancaran makharijul huruf, kaidah hukum tajwid, hafalan ayat pilihan secara mutqin, serta pembiasaan tilawah Al-Qur'an";
        } elseif (strpos($lower, 'fiqih') !== false) {
            return "ketentuan bersuci (thaharah), shalat fardhu dan sunnah, adab ibadah harian, serta muamalah dasar";
        } elseif (strpos($lower, 'akidah') !== false || strpos($lower, 'akhlak') !== false) {
            return "pemahaman rukun iman, penghayatan asmaul husna, adab pergaulan islami, serta pencegahan akhlak tercela";
        } elseif (strpos($lower, 'pjok') !== false || strpos($lower, 'olahraga') !== false) {
            return "keterampilan gerak spesifik olahraga, kebugaran jasmani, sportivitas, serta pembiasaan pola hidup sehat";
        } elseif (strpos($lower, 'informatika') !== false || strpos($lower, 'komputer') !== false) {
            return "berpikir komputasional, literasi digital, pengolahan data sederhana, serta etika penggunaan teknologi informasi";
        } elseif (strpos($lower, 'seni') !== false || strpos($lower, 'prakarya') !== false) {
            return "eksplorasi unsur seni budaya nusantara, apresiasi karya seni, serta kreasi produk fungsional secara mandiri";
        }
        return "materi pokok dan kompetensi capaian pembelajaran " . $mapelName;
    }
}

if (!function_exists('buildDeskripsiAas')) {
    /**
     * Merangkai kalimat deskripsi AAS berdasarkan rentang nilai akhir:
     * 88 - 100 : Sangat Baik dalam memahami...
     * 74 - 87  : Baik dalam memahami...
     * 60 - 73  : Cukup baik dalam memahami...
     * 0 - 59   : Butuh bimbingan dalam memahami...
     */
    function buildDeskripsiAas(float $na, string $materi): string
    {
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
}

