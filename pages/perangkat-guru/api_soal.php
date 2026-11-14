<?php
/**
 * API HANDLER FOR ASYNCHRONOUS EXAM QUESTIONS MANAGEMENT
 * Islamic School of Minhaj Al-Ilmi
 * - Instant Add (1 or multiple questions without reload)
 * - Instant Delete with point auto-recalculation
 * - Text Auto-Parser for Quick Import (Word / Notepad Paste)
 * - Fast AJAX Save
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_guru');

$pdo = getConnection();
require_once __DIR__ . '/init_soal.php';
require_once __DIR__ . '/card_template.php';

$userId = (int) $_SESSION['user_id'];
$userRole = strtolower($_SESSION['role'] ?? 'guru');

$action = $_REQUEST['action'] ?? '';
$naskahId = (int) ($_REQUEST['naskah_id'] ?? 0);

if ($naskahId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID Naskah tidak valid.']);
    exit;
}

// Validasi Hak Akses Naskah
$stmt = $pdo->prepare("SELECT * FROM soal_naskah WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $naskahId]);
$naskah = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$naskah) {
    echo json_encode(['success' => false, 'message' => 'Naskah soal tidak ditemukan.']);
    exit;
}

if ($userRole !== 'admin' && $naskah['guru_id'] != $userId) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak akses untuk naskah ini.']);
    exit;
}

// =========================================================================
// ACTION: ADD QUESTION (SINGLE OR BATCH)
// =========================================================================
if ($action === 'add') {
    $tipe = trim($_POST['tipe'] ?? 'pg');
    if (!in_array($tipe, ['pg', 'isian', 'essay'])) {
        $tipe = 'pg';
    }

    $count = max(1, min(50, (int) ($_POST['count'] ?? 1)));
    $defaultPoin = ($tipe === 'pg') ? 2.0 : (($tipe === 'isian') ? 3.0 : 5.0);

    // Ambil nomor urut terakhir
    $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(nomor_urut), 0) FROM soal_butir WHERE naskah_id = :nid");
    $stmtMax->execute([':nid' => $naskahId]);
    $lastNo = (int) $stmtMax->fetchColumn();

    // Hitung count existing per tipe untuk display nomor
    $stmtCountTipe = $pdo->prepare("SELECT COUNT(*) FROM soal_butir WHERE naskah_id = :nid AND tipe_soal = :tipe");
    $stmtCountTipe->execute([':nid' => $naskahId, ':tipe' => $tipe]);
    $currentTipeCount = (int) $stmtCountTipe->fetchColumn();

    $stmtIns = $pdo->prepare("
        INSERT INTO soal_butir (naskah_id, nomor_urut, tipe_soal, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, kunci_jawaban, poin)
        VALUES (:nid, :no, :tipe, :tanya, :pa, :pb, :pc, :pd, :kunci, :poin)
    ");

    $insertedItems = [];
    for ($i = 0; $i < $count; $i++) {
        $lastNo++;
        $currentTipeCount++;

        $defaultTanya = ($tipe === 'pg') ? 'Tuliskan teks pertanyaan di sini...' : 
                        (($tipe === 'isian') ? 'Tuliskan kalimat rumpang di sini...' : 'Tuliskan instruksi atau pertanyaan uraian di sini...');
        $pa = ($tipe === 'pg') ? 'Pilihan A' : null;
        $pb = ($tipe === 'pg') ? 'Pilihan B' : null;
        $pc = ($tipe === 'pg') ? 'Pilihan C' : null;
        $pd = ($tipe === 'pg') ? 'Pilihan D' : null;
        $kunci = ($tipe === 'pg') ? 'A' : '';

        $stmtIns->execute([
            ':nid' => $naskahId,
            ':no' => $lastNo,
            ':tipe' => $tipe,
            ':tanya' => $defaultTanya,
            ':pa' => $pa,
            ':pb' => $pb,
            ':pc' => $pc,
            ':pd' => $pd,
            ':kunci' => $kunci,
            ':poin' => $defaultPoin
        ]);
        $newId = (int) $pdo->lastInsertId();

        $itemData = [
            'id' => $newId,
            'naskah_id' => $naskahId,
            'nomor_urut' => $lastNo,
            'tipe_soal' => $tipe,
            'pertanyaan' => $defaultTanya,
            'gambar' => null,
            'pilihan_a' => $pa,
            'pilihan_b' => $pb,
            'pilihan_c' => $pc,
            'pilihan_d' => $pd,
            'kunci_jawaban' => $kunci,
            'poin' => $defaultPoin
        ];

        $html = renderSoalCard($itemData, $currentTipeCount, $naskahId);
        $insertedItems[] = [
            'id' => $newId,
            'display_no' => $currentTipeCount,
            'tipe' => $tipe,
            'html' => $html
        ];
    }

    $stats = rehitungPoinNaskah($naskahId, $pdo);

    echo json_encode([
        'success' => true,
        'message' => 'Berhasil menambahkan ' . $count . ' butir soal.',
        'items' => $insertedItems,
        'stats' => $stats
    ]);
    exit;
}

// =========================================================================
// ACTION: DELETE QUESTION (AJAX)
// =========================================================================
if ($action === 'delete') {
    $soalId = (int) ($_POST['id'] ?? 0);
    if ($soalId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID butir soal tidak valid.']);
        exit;
    }

    // Ambil gambar jika ada
    $stmtG = $pdo->prepare("SELECT gambar, tipe_soal FROM soal_butir WHERE id = :id AND naskah_id = :nid");
    $stmtG->execute([':id' => $soalId, ':nid' => $naskahId]);
    $row = $stmtG->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        if (!empty($row['gambar'])) {
            $imgPath = __DIR__ . '/../../uploads/soal/' . $row['gambar'];
            if (file_exists($imgPath)) {
                @unlink($imgPath);
            }
        }

        $stmtDel = $pdo->prepare("DELETE FROM soal_butir WHERE id = :id AND naskah_id = :nid");
        $stmtDel->execute([':id' => $soalId, ':nid' => $naskahId]);

        // Rekalkulasi urutan nomor_urut
        $stmtAll = $pdo->prepare("SELECT id FROM soal_butir WHERE naskah_id = :nid ORDER BY FIELD(tipe_soal, 'pg', 'isian', 'essay'), nomor_urut ASC");
        $stmtAll->execute([':nid' => $naskahId]);
        $allIds = $stmtAll->fetchAll(PDO::FETCH_COLUMN);

        $stmtUpdNo = $pdo->prepare("UPDATE soal_butir SET nomor_urut = :no WHERE id = :id");
        foreach ($allIds as $nIdx => $aid) {
            $stmtUpdNo->execute([':no' => $nIdx + 1, ':id' => $aid]);
        }

        $stats = rehitungPoinNaskah($naskahId, $pdo);

        echo json_encode([
            'success' => true,
            'message' => 'Butir soal berhasil dihapus.',
            'tipe' => $row['tipe_soal'],
            'stats' => $stats
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Butir soal tidak ditemukan.']);
    exit;
}

// =========================================================================
// ACTION: PARSE & IMPORT TEXT (PASTE SOAL DARI WORD / CATATAN)
// =========================================================================
if ($action === 'parse_paste') {
    $rawText = trim($_POST['raw_text'] ?? '');
    $tipe = trim($_POST['tipe'] ?? 'pg');
    if (!in_array($tipe, ['pg', 'isian', 'essay'])) {
        $tipe = 'pg';
    }

    if (empty($rawText)) {
        echo json_encode(['success' => false, 'message' => 'Teks soal masih kosong. Silakan paste teks soal terlebih dahulu.']);
        exit;
    }

    // Nomor urut awal
    $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(nomor_urut), 0) FROM soal_butir WHERE naskah_id = :nid");
    $stmtMax->execute([':nid' => $naskahId]);
    $lastNo = (int) $stmtMax->fetchColumn();

    $stmtCountTipe = $pdo->prepare("SELECT COUNT(*) FROM soal_butir WHERE naskah_id = :nid AND tipe_soal = :tipe");
    $stmtCountTipe->execute([':nid' => $naskahId, ':tipe' => $tipe]);
    $currentTipeCount = (int) $stmtCountTipe->fetchColumn();

    $defaultPoin = ($tipe === 'pg') ? 2.0 : (($tipe === 'isian') ? 3.0 : 5.0);

    $insertedItems = [];

    if ($tipe === 'pg') {
        // Normalisasi line breaks
        $text = str_replace(["\r\n", "\r"], "\n", $rawText);

        // Split berdasarkan pola nomor: e.g. "1.", "1)", "[1]" pada awal baris
        $chunks = preg_split('/(?:\n|^)\s*(?:\d+[\.\)]|\[\d+\])\s*/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $stmtIns = $pdo->prepare("
            INSERT INTO soal_butir (naskah_id, nomor_urut, tipe_soal, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, kunci_jawaban, poin)
            VALUES (:nid, :no, 'pg', :tanya, :pa, :pb, :pc, :pd, :kunci, :poin)
        ");

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if (empty($chunk)) continue;

            $questionText = '';
            $optA = '';
            $optB = '';
            $optC = '';
            $optD = '';
            $key = 'A';

            // Ekstrak Kunci jika ada (e.g. "Kunci: A" atau "Kunci Jawaban: B" atau "Jawaban: C")
            if (preg_match('/(?:kunci(?:\s+jawaban)?|jawaban|key)\s*[:=]\s*([a-dA-D])/i', $chunk, $km)) {
                $key = strtoupper($km[1]);
                $chunk = preg_replace('/(?:kunci(?:\s+jawaban)?|jawaban|key)\s*[:=]\s*[a-dA-D].*/i', '', $chunk);
            }

            // Ekstrak opsi A, B, C, D
            $optPattern = '/(?:\n|^)\s*([A-Da-d])[\.\)]\s*(.*?)(?=(?:\n\s*[A-Da-d][\.\)]|$))/s';
            if (preg_match_all($optPattern, $chunk, $matches, PREG_SET_ORDER)) {
                // Pertanyaan adalah teks sebelum opsi pertama
                $firstOptPos = strpos($chunk, $matches[0][0]);
                $questionText = trim(substr($chunk, 0, $firstOptPos));

                foreach ($matches as $m) {
                    $letter = strtoupper($m[1]);
                    $val = trim($m[2]);
                    if ($letter === 'A') $optA = $val;
                    elseif ($letter === 'B') $optB = $val;
                    elseif ($letter === 'C') $optC = $val;
                    elseif ($letter === 'D') $optD = $val;
                }
            } else {
                // Jika tidak terdeteksi opsi A-D, masukkan chunk sebagai pertanyaan utuh
                $questionText = $chunk;
                $optA = 'Pilihan A';
                $optB = 'Pilihan B';
                $optC = 'Pilihan C';
                $optD = 'Pilihan D';
            }

            if (empty($questionText)) {
                $questionText = 'Pertanyaan Soal...';
            }

            $lastNo++;
            $currentTipeCount++;

            $stmtIns->execute([
                ':nid' => $naskahId,
                ':no' => $lastNo,
                ':tanya' => $questionText,
                ':pa' => $optA ?: 'Pilihan A',
                ':pb' => $optB ?: 'Pilihan B',
                ':pc' => $optC ?: 'Pilihan C',
                ':pd' => $optD ?: 'Pilihan D',
                ':kunci' => $key,
                ':poin' => $defaultPoin
            ]);
            $newId = (int) $pdo->lastInsertId();

            $itemData = [
                'id' => $newId,
                'naskah_id' => $naskahId,
                'nomor_urut' => $lastNo,
                'tipe_soal' => 'pg',
                'pertanyaan' => $questionText,
                'gambar' => null,
                'pilihan_a' => $optA ?: 'Pilihan A',
                'pilihan_b' => $optB ?: 'Pilihan B',
                'pilihan_c' => $optC ?: 'Pilihan C',
                'pilihan_d' => $optD ?: 'Pilihan D',
                'kunci_jawaban' => $key,
                'poin' => $defaultPoin
            ];

            $insertedItems[] = [
                'id' => $newId,
                'display_no' => $currentTipeCount,
                'tipe' => 'pg',
                'html' => renderSoalCard($itemData, $currentTipeCount, $naskahId)
            ];
        }
    } else {
        // Tipe Isian atau Essay
        $text = str_replace(["\r\n", "\r"], "\n", $rawText);
        $chunks = preg_split('/(?:\n|^)\s*(?:\d+[\.\)]|\[\d+\])\s*/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $stmtIns = $pdo->prepare("
            INSERT INTO soal_butir (naskah_id, nomor_urut, tipe_soal, pertanyaan, kunci_jawaban, poin)
            VALUES (:nid, :no, :tipe, :tanya, :kunci, :poin)
        ");

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if (empty($chunk)) continue;

            $kunci = '';
            if (preg_match('/(?:kunci(?:\s+jawaban)?|jawaban|rubrik|pedoman)\s*[:=]\s*(.*)$/im', $chunk, $km)) {
                $kunci = trim($km[1]);
                $chunk = preg_replace('/(?:kunci(?:\s+jawaban)?|jawaban|rubrik|pedoman)\s*[:=]\s*.*$/im', '', $chunk);
            }

            $questionText = trim($chunk);
            if (empty($questionText)) continue;

            $lastNo++;
            $currentTipeCount++;

            $stmtIns->execute([
                ':nid' => $naskahId,
                ':no' => $lastNo,
                ':tipe' => $tipe,
                ':tanya' => $questionText,
                ':kunci' => $kunci,
                ':poin' => $defaultPoin
            ]);
            $newId = (int) $pdo->lastInsertId();

            $itemData = [
                'id' => $newId,
                'naskah_id' => $naskahId,
                'nomor_urut' => $lastNo,
                'tipe_soal' => $tipe,
                'pertanyaan' => $questionText,
                'gambar' => null,
                'pilihan_a' => null,
                'pilihan_b' => null,
                'pilihan_c' => null,
                'pilihan_d' => null,
                'kunci_jawaban' => $kunci,
                'poin' => $defaultPoin
            ];

            $insertedItems[] = [
                'id' => $newId,
                'display_no' => $currentTipeCount,
                'tipe' => $tipe,
                'html' => renderSoalCard($itemData, $currentTipeCount, $naskahId)
            ];
        }
    }

    $stats = rehitungPoinNaskah($naskahId, $pdo);

    echo json_encode([
        'success' => true,
        'message' => 'Berhasil mem-parsing dan menambahkan ' . count($insertedItems) . ' butir soal.',
        'items' => $insertedItems,
        'stats' => $stats
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
