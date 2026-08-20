<?php
/**
 * AJAX ENDPOINT - Generate Perangkat Ajar via Google Gemini AI
 */
require_once __DIR__ . '/../config/auth.php';
requirePermission('perangkat_ajar');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$pdo = getConnection();
$apiKey = getSetting('gemini_api_key', '');

if (empty($apiKey)) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'API Key Gemini belum diatur. Silakan masukkan API Key di menu Pengaturan Sistem terlebih dahulu.'
    ]);
    exit;
}

$action = $_POST['action'] ?? '';
$mapel = trim($_POST['mapel'] ?? '');
$kelas = trim($_POST['kelas'] ?? '');
$topik = trim($_POST['topik'] ?? '');
$cp    = trim($_POST['cp'] ?? '');
$tp    = trim($_POST['tp'] ?? '');
$atp   = trim($_POST['atp'] ?? '');

if (empty($action) || empty($mapel) || empty($kelas) || empty($topik) || empty($cp)) {
    echo json_encode(['status' => 'error', 'message' => 'Data Mata Pelajaran, Kelas, Topik, CP, dan Action wajib diisi.']);
    exit;
}

// System Prompt Dinamis Berdasarkan Action
$prompt = "";

if ($action === 'generate_tp') {
    $prompt = "Anda adalah seorang Guru Senior Penggerak Kurikulum Merdeka yang berpengalaman di Indonesia. Rumuskan Tujuan Pembelajaran (TP) yang otentik, terukur, operasional, dan sangat spesifik sesuai dengan kekhasan materi {$mapel}.

Data Pembelajaran:
- Mata Pelajaran: {$mapel}
- Kelas / Fase: {$kelas}
- Topik / Materi: {$topik}
- Capaian Pembelajaran (CP): {$cp}

INSTRUKSI KHUSUS:
1. Tuliskan 3-4 Tujuan Pembelajaran yang konkret dan beralur logis.
2. Gunakan Kata Kerja Operasional (KKO) Taksonomi Bloom yang bervariasi (misal: menganalisis, mensimulasikan, menguraikan, mengaitkan, membandingkan).
3. HINDARI bahasa robotik klise seperti 'secara tepat, mandiri, dan bertanggung jawab' di setiap poin. Buatlah bahasa yang alami seakan-akan ditulis tangan oleh guru berpengalaman.

KEMBALIKAN HANYA FORMAT JSON SANGAT BERSIH (tanpa markdown backtick):
{
    \"tp\": \"1. ...\\n2. ...\\n3. ...\"
}";
} elseif ($action === 'generate_atp') {
    if(empty($tp)) {
        echo json_encode(['status' => 'error', 'message' => 'Data Tujuan Pembelajaran (TP) wajib diisi untuk membuat ATP.']);
        exit;
    }
    $prompt = "Anda adalah Guru Senior dan Pengembang Kurikulum Merdeka di Indonesia. Susunlah Alur Tujuan Pembelajaran (ATP) yang sangat kontekstual, spesifik pada topik {$topik}, dan mengarahkan pada pembelajaran aktif.

Data Pembelajaran:
- Mata Pelajaran: {$mapel}
- Kelas / Fase: {$kelas}
- Topik / Materi: {$topik}
- CP: {$cp}
- TP: {$tp}

INSTRUKSI KHUSUS:
1. Susun alur pembelajaran per tahap/pertemuan (misal: Tahap 1, Tahap 2, Tahap 3) beserta estimasi alokasi Jam Pelajaran (JP).
2. Uraikan aktivitas siswa, fokus materi, dan strategi pembelajaran secara detail dan luwes (tidak sekadar template umum).

KEMBALIKAN HANYA FORMAT JSON SANGAT BERSIH (tanpa markdown backtick):
{
    \"atp\": \"Tahap 1 (2 JP): ...\\n\\nTahap 2 (4 JP): ...\\n\\nTahap 3 (2 JP): ...\"
}";
} elseif ($action === 'generate_modul') {
    if(empty($tp) || empty($atp)) {
        echo json_encode(['status' => 'error', 'message' => 'Data TP dan ATP wajib diisi untuk membuat Modul Ajar.']);
        exit;
    }
    $prompt = "Anda adalah Guru Penggerak profesional yang berdedikasi. Buatlah rancangan Modul Ajar / RPP Kurikulum Merdeka yang komprehensif, hidup, kontekstual, dan sepenuhnya disesuaikan dengan topik {$topik} untuk mata pelajaran {$mapel}.

Data Pembelajaran:
- Mata Pelajaran: {$mapel}
- Kelas: {$kelas}
- Topik Utama: {$topik}
- CP: {$cp}
- TP: {$tp}
- ATP: {$atp}

CRITICAL REQUIREMENT (PENTING):
- JANGAN GUNAKAN TEMPLATE UMUM/KLISE SEPERTI 'mencapai pemahaman dasar' ATAU 'peserta didik mampu memahami konsep'.
- TULISKAN NARASI YANG SPESIFIK MATERI {$mapel} & TOPIK {$topik}. Sebutkan contoh kasus nyata, istilah khusus, atau simulasi yang relevan dengan topik ini.
- Tuliskan narasi seakan-akan merupakan karya tulis asli seorang guru kelas yang mengenal karakter muridnya di Indonesia.

KEMBALIKAN HANYA FORMAT JSON SANGAT BERSIH (tanpa markdown backtick):
{
    \"kompetensi_awal\": \"<Pengetahuan prasyarat siswa yang spesifik terkait topik {$topik}>\",
    \"sarana_prasarana\": \"<Fasilitas, media visual/konkret, dan bahan ajar yang realistis>\",
    \"target_siswa\": \"<Deskripsi target peserta didik inklusif (reguler & butuh pendampingan)>\",
    \"pemahaman_bermakna\": \"<Manfaat materi {$topik} dalam kehidupan nyata harian siswa yang menginspirasi>\",
    \"pertanyaan_pemantik\": \"<2-3 pertanyaan pemantik diskusi yang spesifik, hangat, dan menggugah nalar>\",
    \"kegiatan_pendahuluan\": \"<Langkah apersepsi hangat, pemutaran stimulus/cerita kontekstual, dan pengondisian kelas>\",
    \"kegiatan_inti\": \"<Langkah interaktif berorientasi masalah/proyek khusus topik {$topik} (orientasi kasus, kerja kelompok, pembimbingan, presentasi)>\",
    \"kegiatan_penutup\": \"<Refleksi diri yang menyentuh, simpulan bersama, dan apresiasi>\",
    \"asesmen_diagnostik\": \"<Bentuk pertanyaan pemetaan awal spesifik topik {$topik}>\",
    \"asesmen_formatif\": \"<Asesmen keaktifan, rubrik partisipasi LKPD, dan unjuk kerja>\",
    \"asesmen_sumatif\": \"<Bentuk evaluasi akhir topik yang menguji penalaran>\",
    \"lkpd_content\": \"<Instruksi tugas LKPD kelompok yang praktis, aplikatif, dan tidak monoton>\",
    \"glosarium\": \"<Kata-kata penting khas materi {$topik} beserta maknanya>\",
    \"daftar_pustaka\": \"<Referensi buku dan sumber belajar Kurikulum Merdeka yang relevan>\"
}";
} else {
    echo json_encode(['status' => 'error', 'message' => 'Action tidak dikenali.']);
    exit;
}

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent?key=' . $apiKey;

$data = [
    'contents' => [
        [
            'parts' => [
                ['text' => $prompt]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.75, // sedikit ditingkatkan agar lebih luwes dan natural
        'response_mime_type' => 'application/json'
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
$response = curl_exec($ch);

if(curl_errno($ch)){
    echo json_encode(['status' => 'error', 'message' => 'Gagal terhubung ke server AI: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}
curl_close($ch);

$responseData = json_decode($response, true);

if (isset($responseData['error'])) {
    echo json_encode(['status' => 'error', 'message' => 'API Error: ' . ($responseData['error']['message'] ?? 'Unknown Error')]);
    exit;
}

$textOutput = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
$textOutput = str_replace(['```json', '```'], '', $textOutput);
$textOutput = trim($textOutput);

if (preg_match('/\{.*\}/s', $textOutput, $matches)) {
    $textOutput = $matches[0];
}

$parsedJson = json_decode($textOutput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['status' => 'error', 'message' => 'Respon AI gagal diproses (Invalid JSON).']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'data' => $parsedJson
]);
