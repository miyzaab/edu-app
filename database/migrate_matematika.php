<?php
require_once 'c:/Users/SOLIT/Desktop/edu-app/config/koneksi.php';
$db = getConnection();

$id = 9;
$semester = '1';
$topik = 'Bilangan Bulat & Aljabar';
$elemen = 'Bilangan & Aljabar';
$capaian_pembelajaran = "Pada akhir Fase D, peserta didik dapat membaca, menulis, dan membandingkan bilangan bulat, bilangan rasional dan irasional, bilangan desimal, bilangan berpangkat bulat dan ilmiah. Mereka dapat menerapkan operasi aritmetika pada bilangan real, dan memberikan estimasi/perkiraan dalam menyelesaikan masalah. Mereka dapat menyatakan suatu situasi ke dalam bentuk aljabar dan menggunakannya untuk menyelesaikan masalah.";

$tujuan_pembelajaran = "1. Peserta didik mampu memahami konsep bilangan bulat dan pecahan serta menyelesaikan operasi hitung campuran dalam kehidupan sehari-hari.\n2. Peserta didik mampu menyederhanakan bentuk aljabar dan menyelesaikan persamaan linear satu variabel.\n3. Peserta didik mampu menerapkan konsep perbandingan senilai dan berbalik nilai dalam memecahkan masalah skala dan rasio.\n4. Peserta didik mampu menyajikan dan menganalisis data dalam bentuk tabel, diagram batang, dan diagram lingkaran.";

$alur_tujuan_pembelajaran = "Tahap 1: Pemahaman konsep bilangan bulat, operasi hitung campuran, dan penerapannya (4 JP)\nTahap 2: Pengenalan bentuk aljabar, operasi aljabar, dan penyelesaian persamaan linear satu variabel (4 JP)\nTahap 3: Pemecahan masalah rasio, skala, dan perbandingan senilai/berbalik nilai (4 JP)\nTahap 4: Pengumpulan, penyajian, dan analisis data statistika dasar (4 JP)";

$poin_bab_raw = "Bab 1: Bilangan Bulat & Pecahan\r\nBab 2: Bentuk Aljabar & Persamaan Linear\r\nBab 3: Rasio & Perbandingan\r\nBab 4: Statistika & Penyajian Data";

$modul_ajar_json = json_encode([
    "nama_sekolah" => "Sekolah Anti Korupsi",
    "nama_guru" => "Administrator",
    "nip_guru" => "",
    "nama_kepsek" => "Kepala Sekolah, M.Pd",
    "nip_kepsek" => "",
    "model_pembelajaran" => "Problem-Based Learning (PBL) & Diskusi Kelompok Interaktif",
    "profil_pancasila" => ["Bernalar Kritis", "Gotong Royong", "Kreatif", "Mandiri"],
    "kompetensi_awal" => "Peserta didik memahami pengetahuan dasar materi prasyarat (aritmetika dasar) dan memiliki rasa ingin tahu terhadap fenomena pola bilangan.",
    "sarana_prasarana" => "Buku Pegangan Guru & Siswa, Slide Presentasi/PPT, Proyektor, Kartu Soal/LKPD.",
    "target_siswa" => "Peserta didik reguler / tipikal (28-32 siswa dalam rombel campuran)",
    "pemahaman_bermakna" => "Penguasaan konsep bilangan bulat dan aljabar melatih pola pikir logis-matematis untuk memecahkan masalah praktis yang dijumpai dalam kehidupan nyata.",
    "pertanyaan_pemantik" => "1. Di mana saja kita sering menjumpai penerapan bilangan negatif atau skala dalam kehidupan sehari-hari?\n2. Bagaimana menyederhanakan masalah nyata menjadi bentuk aljabar agar lebih mudah diselesaikan?",
    "kegiatan_pendahuluan" => "1. Guru mengucap salam hangat, mengajak berdoa bersama, dan menyapa presensi siswa.\n2. Apersepsi: Guru mengaitkan materi bilangan bulat dan aljabar dengan pengalaman nyata siswa.\n3. Guru menyampaikan tujuan pembelajaran dan gambaran aktivitas yang akan dilakukan.",
    "kegiatan_inti" => "1. Orientasi: Siswa mencermati tayangan visual / studi kasus terkait materi bilangan bulat dan persamaan aljabar.\n2. Mengorganisasi: Siswa dibagi dalam kelompok heterogen (4-5 orang) untuk mendiskusikan lembar kerja.\n3. Membimbing: Guru berkeliling memberikan scaffolding pada kelompok yang membutuhkan masukan.\n4. Mengembangkan: Setiap kelompok menyusun dan menyajikan hasil diskusinya di depan kelas.\n5. Evaluasi: Guru dan antar-siswa memberikan apresiasi serta klarifikasi pemahaman.",
    "kegiatan_penutup" => "1. Siswa merangkum poin-poin penting materi bilangan bulat dan aljabar dibimbing oleh guru.\n2. Refleksi singkat: Siswa mengungkapkan apa yang sudah dipahami dan apa yang masih perlu diperdalam.\n3. Guru menyampaikan penugasan latihan mandiri dan menutup kelas dengan doa.",
    "asesmen_diagnostik" => "Tanya jawab lisan apersepsi operasi penjumlahan dasar di awal pertemuan untuk memetakan pemahaman awal siswa.",
    "asesmen_formatif" => "Observasi keaktifan diskusi kelompok, penilaian kinerjanya pada LKPD, dan presentasi.",
    "asesmen_sumatif" => "Tes tertulis pilihan ganda beralasan & soal uraian berbasis pemecahan masalah di akhir topik.",
    "lkpd_content" => "LKPD Eksplorasi Bilangan Bulat & Aljabar:\n1. Kerjakan 3 soal tantangan berbasis kasus kontekstual bersama kelompokmu!\n2. Tuliskan langkah penyelesaian secara runtut dan jelaskan alasan di setiap langkahnya!",
    "bahan_bacaan" => "Buku Pegangan Siswa & Lembar Kerja Eksplorasi",
    "glosarium" => "Bilangan Bulat: Bilangan yang terdiri dari bilangan bulat positif, nol, dan bilangan negatif.\nAljabar: Cabang matematika yang menggunakan simbol untuk mewakili variabel dalam persamaan.",
    "daftar_pustaka" => "1. Kemendikbudristek. (2022). Buku Panduan Guru & Siswa Matematika Kelas VII. Jakarta.\n2. Referensi Pendukung Kurikulum Merdeka.",
    "poin_bab_raw" => $poin_bab_raw
]);

$stmt = $db->prepare("
    UPDATE perangkat_ajar 
    SET semester = :semester,
        topik = :topik,
        elemen = :elemen,
        capaian_pembelajaran = :capaian_pembelajaran,
        tujuan_pembelajaran = :tujuan_pembelajaran,
        alur_tujuan_pembelajaran = :alur_tujuan_pembelajaran,
        modul_ajar_json = :modul_ajar_json
    WHERE id = :id
");

$stmt->execute([
    ':semester' => $semester,
    ':topik' => $topik,
    ':elemen' => $elemen,
    ':capaian_pembelajaran' => $capaian_pembelajaran,
    ':tujuan_pembelajaran' => $tujuan_pembelajaran,
    ':alur_tujuan_pembelajaran' => $alur_tujuan_pembelajaran,
    ':modul_ajar_json' => $modul_ajar_json,
    ':id' => $id
]);

echo "Database updated successfully for Matematika ID 9.\n";
