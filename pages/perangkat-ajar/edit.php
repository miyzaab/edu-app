<?php
/**
 * PERANGKAT AJAR KURIKULUM MERDEKA - EDITOR LENGKAP DOKUMEN (IDENTITAS, CP, TP, ATP & MODUL PER BAB)
 */
$pageTitle  = 'Edit Dokumen Perangkat Ajar';
$activePage = 'perangkat-ajar';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_ajar');

$pdo = getConnection();
$userId = (int)$_SESSION['user_id'];
$userRole = strtolower($_SESSION['role'] ?? 'guru');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM perangkat_ajar WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$doc = $stmt->fetch();

if (!$doc) {
    redirect('index.php', 'danger', 'Dokumen Perangkat Ajar tidak ditemukan.');
}

// Cek hak edit: hanya pembuat atau admin
if ($userRole !== 'admin' && $doc['user_id'] != $userId) {
    redirect('index.php', 'danger', 'Anda tidak memiliki hak untuk mengedit dokumen ini.');
}

$modulData = json_decode($doc['modul_ajar_json'] ?? '{}', true) ?: [];

// Helper rebuild flat text columns from structured bab lists
function rebuildSummaryColumns(string $cpGlobal, array $modulData): array
{
    $cpLines  = [];
    $tpLines  = [];
    $atpLines = [];

    foreach (['semester_1', 'semester_2'] as $semKey) {
        foreach ($modulData[$semKey]['bab_list'] ?? [] as $b) {
            $num   = $b['nomor'] ?? 1;
            $judul = $b['judul_bersih'] ?? ($b['judul_lengkap'] ?? '');
            if (!empty($b['cp_teks'])) {
                $cpLines[] = "• Bab {$num} ({$judul}): " . $b['cp_teks'];
            }
            foreach ($b['tp_list'] ?? [] as $tpItem) {
                $tpLines[] = $tpItem;
            }
            if (!empty($b['atp_teks'])) {
                $atpLines[] = $b['atp_teks'];
            }
        }
    }

    return [
        'cp'  => trim($cpGlobal . (!empty($cpLines) ? "\n\n" . implode("\n", $cpLines) : '')),
        'tp'  => implode("\n", $tpLines),
        'atp' => implode("\n", $atpLines),
    ];
}

// Submit Edit Lengkap
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $mapel       = trim($_POST['mapel'] ?? $doc['mapel']);
    $kelas       = trim($_POST['kelas'] ?? $doc['kelas']);
    $fase        = trim($_POST['fase'] ?? ($doc['fase'] ?? 'D'));
    $tahun       = trim($_POST['tahun_ajaran'] ?? $doc['tahun_ajaran']);
    $topik       = trim($_POST['topik'] ?? $doc['topik']);
    $elemen      = trim($_POST['elemen'] ?? $doc['elemen']);
    $jpPerPekan  = max(1, min(10, (int)($_POST['jp_per_pekan'] ?? ($doc['jp_per_pekan'] ?? 4))));
    $cpGlobal    = trim($_POST['cp_global'] ?? ($modulData['cp_global'] ?? $doc['capaian_pembelajaran']));

    // Update data umum di JSON
    $modulData['nama_sekolah'] = trim($_POST['nama_sekolah'] ?? ($modulData['nama_sekolah'] ?? getSetting('nama_sekolah', SCHOOL_NAME)));
    $modulData['nama_guru']    = trim($_POST['nama_guru'] ?? ($modulData['nama_guru'] ?? ''));
    $modulData['nip_guru']     = trim($_POST['nip_guru'] ?? ($modulData['nip_guru'] ?? ''));
    $modulData['nama_kepsek']  = trim($_POST['nama_kepsek'] ?? ($modulData['nama_kepsek'] ?? ''));
    $modulData['nip_kepsek']   = trim($_POST['nip_kepsek'] ?? ($modulData['nip_kepsek'] ?? ''));
    $modulData['jp_per_pekan'] = $jpPerPekan;
    $modulData['mapel']        = $mapel;
    $modulData['kelas']        = $kelas;
    $modulData['fase']         = $fase;
    $modulData['tahun_ajaran'] = $tahun;
    $modulData['cp_global']    = $cpGlobal;

    $modelPem    = trim($_POST['model_pembelajaran'] ?? '');
    $targetS     = trim($_POST['target_siswa'] ?? '');
    $pustaka     = trim($_POST['daftar_pustaka'] ?? '');
    $applyGlobal = !empty($_POST['apply_global_to_babs']);

    if ($modelPem !== '') $modulData['model_pembelajaran'] = $modelPem;
    if ($targetS !== '')  $modulData['target_siswa'] = $targetS;
    if ($pustaka !== '')  $modulData['daftar_pustaka'] = $pustaka;

    // Update rincian per bab Semester 1 & 2 jika dikirim dari form
    $postedBabs = $_POST['babs'] ?? [];
    $totalJpAll = 0;

    foreach (['semester_1', 'semester_2'] as $semKey) {
        if (!isset($modulData[$semKey]['bab_list']) || !is_array($modulData[$semKey]['bab_list'])) {
            continue;
        }
        $semJp = 0;
        foreach ($modulData[$semKey]['bab_list'] as $idx => &$bItem) {
            $bPost = $postedBabs[$semKey][$idx] ?? null;
            if (is_array($bPost)) {
                $judulBaru = trim($bPost['judul_bersih'] ?? ($bItem['judul_bersih'] ?? ''));
                $jpBaru    = max(1, (int)($bPost['alokasi_jp'] ?? ($bItem['alokasi_jp'] ?? 12)));
                $pertBaru  = max(1, (int)($bPost['jumlah_pertemuan'] ?? ($bItem['jumlah_pertemuan'] ?? 3)));

                $bItem['judul_bersih']     = $judulBaru;
                $bItem['judul_lengkap']    = 'Bab ' . ($bItem['nomor'] ?? ($idx + 1)) . ': ' . $judulBaru;
                $bItem['alokasi_jp']       = $jpBaru;
                $bItem['jumlah_pertemuan'] = $pertBaru;
                $bItem['cp_teks']          = trim($bPost['cp_teks'] ?? ($bItem['cp_teks'] ?? ''));
                $bItem['atp_teks']         = trim($bPost['atp_teks'] ?? ($bItem['atp_teks'] ?? ''));

                $rawTp = trim($bPost['tp_list_raw'] ?? '');
                if ($rawTp !== '') {
                    $bItem['tp_list'] = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', $rawTp)))));
                }

                if (!isset($bItem['modul']) || !is_array($bItem['modul'])) {
                    $bItem['modul'] = [];
                }
                $bItem['modul']['judul_bab']            = $judulBaru;
                $bItem['modul']['alokasi_jp']           = $jpBaru;
                $bItem['modul']['jumlah_pertemuan']     = $pertBaru;
                $bItem['modul']['alokasi_waktu']        = "{$pertBaru} Pertemuan ({$jpBaru} JP × 40 Menit)";
                $bItem['modul']['model_pembelajaran']   = $applyGlobal && $modelPem !== '' ? $modelPem : trim($bPost['model_pembelajaran'] ?? ($bItem['modul']['model_pembelajaran'] ?? ''));
                $bItem['modul']['target_siswa']         = $applyGlobal && $targetS !== '' ? $targetS : trim($bPost['target_siswa'] ?? ($bItem['modul']['target_siswa'] ?? ''));
                $bItem['modul']['daftar_pustaka']       = $applyGlobal && $pustaka !== '' ? $pustaka : trim($bPost['daftar_pustaka'] ?? ($bItem['modul']['daftar_pustaka'] ?? ''));
                $bItem['modul']['sarana_prasarana']     = trim($bPost['sarana_prasarana'] ?? ($bItem['modul']['sarana_prasarana'] ?? ''));
                $bItem['modul']['pemahaman_bermakna']   = trim($bPost['pemahaman_bermakna'] ?? ($bItem['modul']['pemahaman_bermakna'] ?? ''));
                $bItem['modul']['pertanyaan_pemantik']  = trim($bPost['pertanyaan_pemantik'] ?? ($bItem['modul']['pertanyaan_pemantik'] ?? ''));
                $bItem['modul']['kegiatan_pendahuluan'] = trim($bPost['kegiatan_pendahuluan'] ?? ($bItem['modul']['kegiatan_pendahuluan'] ?? ''));
                $bItem['modul']['kegiatan_inti']        = trim($bPost['kegiatan_inti'] ?? ($bItem['modul']['kegiatan_inti'] ?? ''));
                $bItem['modul']['kegiatan_penutup']     = trim($bPost['kegiatan_penutup'] ?? ($bItem['modul']['kegiatan_penutup'] ?? ''));
                $bItem['modul']['asesmen_diagnostik']   = trim($bPost['asesmen_diagnostik'] ?? ($bItem['modul']['asesmen_diagnostik'] ?? ''));
                $bItem['modul']['asesmen_formatif']     = trim($bPost['asesmen_formatif'] ?? ($bItem['modul']['asesmen_formatif'] ?? ''));
                $bItem['modul']['asesmen_sumatif']      = trim($bPost['asesmen_sumatif'] ?? ($bItem['modul']['asesmen_sumatif'] ?? ''));
                $bItem['modul']['lkpd_content']         = trim($bPost['lkpd_content'] ?? ($bItem['modul']['lkpd_content'] ?? ''));
                $bItem['modul']['glosarium']            = trim($bPost['glosarium'] ?? ($bItem['modul']['glosarium'] ?? ''));
            }
            $semJp += (int)($bItem['alokasi_jp'] ?? 0);
        }
        unset($bItem);
        $modulData[$semKey]['total_jp'] = $semJp;
        $totalJpAll += $semJp;
    }

    if ($totalJpAll > 0) {
        $modulData['total_jp_tahun'] = $totalJpAll;
        $alokasi = "{$jpPerPekan} JP/Pekan (Total {$totalJpAll} JP/Tahun)";
    } else {
        $alokasi = "{$jpPerPekan} JP/Pekan";
    }

    $rebuilt = rebuildSummaryColumns($cpGlobal, $modulData);
    $cp      = $rebuilt['cp'] !== '' ? $rebuilt['cp'] : $cpGlobal;
    $tp      = $rebuilt['tp'] !== '' ? $rebuilt['tp'] : ($doc['tujuan_pembelajaran'] ?? '');
    $atp     = $rebuilt['atp'] !== '' ? $rebuilt['atp'] : ($doc['alur_tujuan_pembelajaran'] ?? '');

    try {
        $stmtUpdate = $pdo->prepare("
            UPDATE perangkat_ajar SET 
            mapel = :mapel, kelas = :kelas, fase = :fase, tahun_ajaran = :th,
            topik = :topik, elemen = :elem, alokasi_waktu = :alokasi, jp_per_pekan = :jp,
            capaian_pembelajaran = :cp, tujuan_pembelajaran = :tp, alur_tujuan_pembelajaran = :atp,
            modul_ajar_json = :json
            WHERE id = :id
        ");
        $stmtUpdate->execute([
            ':mapel'   => $mapel,
            ':kelas'   => $kelas,
            ':fase'    => $fase,
            ':th'      => $tahun,
            ':topik'   => $topik,
            ':elem'    => $elemen,
            ':alokasi' => $alokasi,
            ':jp'      => $jpPerPekan,
            ':cp'      => $cp,
            ':tp'      => $tp,
            ':atp'     => $atp,
            ':json'    => json_encode($modulData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ':id'      => $id
        ]);

        redirect("view.php?id=$id", 'success', 'Perubahan dokumen Perangkat Ajar berhasil disimpan & disinkronkan ke PDF!');
    } catch (PDOException $e) {
        $errorMsg = 'Gagal memperbarui dokumen: ' . $e->getMessage();
    }
}

$cpGlobalDisplay = $modulData['cp_global'] ?? $doc['capaian_pembelajaran'];
$smt1List = $modulData['semester_1']['bab_list'] ?? [];
$smt2List = $modulData['semester_2']['bab_list'] ?? [];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/subnav.php';
?>

<div class="row justify-content-center">
    <div class="col-xl-10 col-12">

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="fw-bold mb-1" style="font-family: 'Outfit', sans-serif;">
                    <i class="bi bi-pencil-square text-primary me-2"></i> Edit Lengkap Perangkat Ajar
                </h4>
                <p class="text-muted extra-small mb-0">
                    Ubah identitas mapel, model pembelajaran, narasi CP Global, hingga rincian CP, TP, ATP, dan Modul Ajar per bab sebelum mencetak PDF.
                </p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="print.php?id=<?= $id ?>&doc_type=all" target="_blank" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Pratinjau PDF Bundel
                </a>
                <a href="view.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Dokumen
                </a>
            </div>
        </div>

        <?php if (isset($errorMsg)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($errorMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="nama_sekolah" value="<?= htmlspecialchars($modulData['nama_sekolah'] ?? getSetting('nama_sekolah', SCHOOL_NAME)) ?>">
            <input type="hidden" name="fase" value="<?= htmlspecialchars($doc['fase'] ?? 'D') ?>">
            <input type="hidden" name="elemen" value="<?= htmlspecialchars($doc['elemen'] ?? '') ?>">

            <!-- 1. KARTU IDENTITAS -->
            <div class="card border-0 rounded-4 shadow-sm mb-4" style="background: #ffffff;">
                <div class="card-body p-4 p-md-4">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-card-heading text-primary me-1"></i> 1. Identitas Pembelajaran &amp; Pengaturan Umum Modul
                    </h6>

                    <div class="row g-3 mb-3">
                        <div class="col-md-5 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">MATA PELAJARAN</label>
                            <input type="text" name="mapel" class="form-control rounded-3 fw-semibold" value="<?= htmlspecialchars($doc['mapel']) ?>" required>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label extra-small fw-bold text-muted mb-1">KELAS</label>
                            <select name="kelas" class="form-select rounded-3 fw-semibold">
                                <option value="VII" <?= $doc['kelas'] === 'VII' ? 'selected' : '' ?>>Kelas VII</option>
                                <option value="VIII" <?= $doc['kelas'] === 'VIII' ? 'selected' : '' ?>>Kelas VIII</option>
                                <option value="IX" <?= $doc['kelas'] === 'IX' ? 'selected' : '' ?>>Kelas IX</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-6">
                            <label class="form-label extra-small fw-bold text-muted mb-1">JP PER PEKAN</label>
                            <input type="number" name="jp_per_pekan" class="form-control rounded-3 fw-semibold" min="1" max="10" value="<?= htmlspecialchars($doc['jp_per_pekan'] ?? 4) ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">TOPIK / JUDUL PERANGKAT</label>
                            <input type="text" name="topik" class="form-control rounded-3" value="<?= htmlspecialchars($doc['topik']) ?>" required>
                        </div>
                        <div class="col-md-4 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">TAHUN AJARAN</label>
                            <input type="text" name="tahun_ajaran" class="form-control rounded-3" value="<?= htmlspecialchars($doc['tahun_ajaran']) ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 pt-3 border-top">
                        <div class="col-md-6 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">GURU PENGAMPU</label>
                            <input type="text" name="nama_guru" class="form-control rounded-3" value="<?= htmlspecialchars($modulData['nama_guru'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">NIP GURU</label>
                            <input type="text" name="nip_guru" class="form-control rounded-3" value="<?= htmlspecialchars($modulData['nip_guru'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">KEPALA SEKOLAH</label>
                            <input type="text" name="nama_kepsek" class="form-control rounded-3" value="<?= htmlspecialchars((!empty($modulData['nama_kepsek']) && $modulData['nama_kepsek'] !== 'Kepala Sekolah, M.Pd') ? $modulData['nama_kepsek'] : getSetting('nama_kepsek', 'Kepala Sekolah')) ?>" required>
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">NIP KEPALA SEKOLAH</label>
                            <input type="text" name="nip_kepsek" class="form-control rounded-3" value="<?= htmlspecialchars((!empty($modulData['nip_kepsek']) && $modulData['nip_kepsek'] !== '—' && $modulData['nip_kepsek'] !== '-') ? $modulData['nip_kepsek'] : getSetting('nip_kepsek', '—')) ?>">
                        </div>
                    </div>

                    <div class="row g-3 pt-3 mt-1 border-top">
                        <div class="col-md-4 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">MODEL PEMBELAJARAN UMUM</label>
                            <input type="text" name="model_pembelajaran" class="form-control rounded-3"
                                   value="<?= htmlspecialchars($modulData['model_pembelajaran'] ?? ($smt1List[0]['modul']['model_pembelajaran'] ?? '')) ?>"
                                   placeholder="Contoh: Problem-Based Learning (PBL)">
                        </div>
                        <div class="col-md-4 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">TARGET PESERTA DIDIK</label>
                            <input type="text" name="target_siswa" class="form-control rounded-3"
                                   value="<?= htmlspecialchars($modulData['target_siswa'] ?? ($smt1List[0]['modul']['target_siswa'] ?? '')) ?>"
                                   placeholder="Contoh: 30 Siswa (Peserta Didik Reguler)">
                        </div>
                        <div class="col-md-4 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">DAFTAR PUSTAKA UMUM</label>
                            <input type="text" name="daftar_pustaka" class="form-control rounded-3"
                                   value="<?= htmlspecialchars($modulData['daftar_pustaka'] ?? ($smt1List[0]['modul']['daftar_pustaka'] ?? '')) ?>"
                                   placeholder="Contoh: Kitab Rujukan, Buku Siswa Resmi">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="apply_global_to_babs" id="checkApplyGlobalBabs" value="1">
                                <label class="form-check-label extra-small fw-semibold text-secondary" for="checkApplyGlobalBabs">
                                    Timpa Model Pembelajaran, Target Peserta Didik, dan Daftar Pustaka di semua bab di bawah dengan nilai umum di atas
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. KARTU CP GLOBAL -->
            <div class="card border-0 rounded-4 shadow-sm mb-4" id="section-cp" style="background: #ffffff;">
                <div class="card-body p-4 p-md-4">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-journal-text text-primary me-1"></i> 2. Narasi Capaian Pembelajaran (CP) Global Fase <?= htmlspecialchars($doc['fase'] ?? 'D') ?>
                    </h6>
                    <div class="mb-0">
                        <label class="form-label extra-small fw-bold text-muted mb-1">NARASI CP AKHIR FASE</label>
                        <textarea name="cp_global" rows="4" class="form-control rounded-3 small" style="line-height: 1.6;"><?= htmlspecialchars($cpGlobalDisplay) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- 3. RINCIAN PER BAB (CP, TP, ATP & MODUL AJAR) -->
            <div class="card border-0 rounded-4 shadow-sm mb-4" id="section-bab" style="background: #ffffff;">
                <div class="card-body p-4 p-md-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom pb-2 mb-3">
                        <div>
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="bi bi-layers-fill text-success me-1"></i> 3. Edit Konten Per Bab (CP, TP, ATP &amp; Modul Ajar)
                            </h6>
                            <small class="text-muted extra-small">Klik judul bab di bawah untuk membuka dan mengedit seluruh isi CP, TP, ATP, kegiatan belajar, asesmen, hingga LKPD.</small>
                        </div>
                    </div>

                    <?php
                    $semGroups = [
                        'semester_1' => ['label' => 'Semester 1 (Ganjil)', 'badge' => 'bg-primary', 'items' => $smt1List],
                        'semester_2' => ['label' => 'Semester 2 (Genap)',  'badge' => 'bg-indigo',  'items' => $smt2List],
                    ];
                    ?>

                    <?php foreach ($semGroups as $semKey => $group): ?>
                        <?php if (!empty($group['items'])): ?>
                            <div class="mb-4">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="badge <?= $group['badge'] ?> rounded-pill px-3 py-1 fw-bold" style="<?= $semKey === 'semester_2' ? 'background-color: #6366f1;' : '' ?>">
                                        <?= htmlspecialchars($group['label']) ?> (<?= count($group['items']) ?> Bab)
                                    </span>
                                </div>

                                <div class="accordion" id="accordion_<?= $semKey ?>">
                                    <?php foreach ($group['items'] as $idx => $b): ?>
                                        <?php
                                        $m = $b['modul'] ?? [];
                                        $accId = "{$semKey}_bab_{$idx}";
                                        $tpRaw = implode("\n", $b['tp_list'] ?? []);
                                        ?>
                                        <div class="accordion-item border rounded-3 mb-2 overflow-hidden">
                                            <h2 class="accordion-header">
                                                <button class="accordion-button <?= ($semKey === 'semester_1' && $idx === 0) ? '' : 'collapsed' ?> fw-bold text-dark bg-light py-3"
                                                        type="button" data-bs-toggle="collapse" data-bs-target="#collapse_<?= $accId ?>">
                                                    <span class="me-2 badge bg-white text-dark border">Bab <?= htmlspecialchars($b['nomor'] ?? ($idx + 1)) ?></span>
                                                    <span class="me-auto"><?= htmlspecialchars($b['judul_bersih'] ?? '') ?></span>
                                                    <small class="text-muted fw-semibold ms-2 me-2"><?= htmlspecialchars($b['alokasi_jp'] ?? 0) ?> JP • <?= htmlspecialchars($b['jumlah_pertemuan'] ?? 0) ?> Pertemuan</small>
                                                </button>
                                            </h2>
                                            <div id="collapse_<?= $accId ?>" class="accordion-collapse collapse <?= ($semKey === 'semester_1' && $idx === 0) ? 'show' : '' ?>" data-bs-parent="#accordion_<?= $semKey ?>">
                                                <div class="accordion-body bg-white p-3 p-md-4">
                                                    <div class="row g-3 mb-3">
                                                        <div class="col-md-6 col-12">
                                                            <label class="form-label extra-small fw-bold text-muted mb-1">JUDUL BAB</label>
                                                            <input type="text" name="babs[<?= $semKey ?>][<?= $idx ?>][judul_bersih]" class="form-control rounded-3 fw-semibold" value="<?= htmlspecialchars($b['judul_bersih'] ?? '') ?>" required>
                                                        </div>
                                                        <div class="col-md-3 col-6">
                                                            <label class="form-label extra-small fw-bold text-muted mb-1">ALOKASI JP</label>
                                                            <input type="number" name="babs[<?= $semKey ?>][<?= $idx ?>][alokasi_jp]" class="form-control rounded-3" min="1" max="150" value="<?= (int)($b['alokasi_jp'] ?? 12) ?>" required>
                                                        </div>
                                                        <div class="col-md-3 col-6">
                                                            <label class="form-label extra-small fw-bold text-muted mb-1">JML PERTEMUAN</label>
                                                            <input type="number" name="babs[<?= $semKey ?>][<?= $idx ?>][jumlah_pertemuan]" class="form-control rounded-3" min="1" max="60" value="<?= (int)($b['jumlah_pertemuan'] ?? 3) ?>" required>
                                                        </div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label extra-small fw-bold text-muted mb-1">CAPAIAN PEMBELAJARAN (CP) BAB</label>
                                                        <textarea name="babs[<?= $semKey ?>][<?= $idx ?>][cp_teks]" rows="2" class="form-control rounded-3 small"><?= htmlspecialchars($b['cp_teks'] ?? '') ?></textarea>
                                                    </div>

                                                    <div class="row g-3 mb-3">
                                                        <div class="col-md-6 col-12">
                                                            <label class="form-label extra-small fw-bold text-muted mb-1">TUJUAN PEMBELAJARAN (TP) — 1 BARIS 1 TUJUAN</label>
                                                            <textarea name="babs[<?= $semKey ?>][<?= $idx ?>][tp_list_raw]" rows="4" class="form-control rounded-3 small"><?= htmlspecialchars($tpRaw) ?></textarea>
                                                        </div>
                                                        <div class="col-md-6 col-12">
                                                            <label class="form-label extra-small fw-bold text-muted mb-1">ALUR TUJUAN PEMBELAJARAN (ATP)</label>
                                                            <textarea name="babs[<?= $semKey ?>][<?= $idx ?>][atp_teks]" rows="4" class="form-control rounded-3 small"><?= htmlspecialchars($b['atp_teks'] ?? '') ?></textarea>
                                                        </div>
                                                    </div>

                                                    <div class="row g-3 mb-3 pt-2 border-top">
                                                        <div class="col-md-6 col-12">
                                                            <label class="form-label extra-small fw-bold text-muted mb-1">MODEL PEMBELAJARAN BAB</label>
                                                            <input type="text" name="babs[<?= $semKey ?>][<?= $idx ?>][model_pembelajaran]" class="form-control rounded-3 small" value="<?= htmlspecialchars($m['model_pembelajaran'] ?? '') ?>">
                                                        </div>
                                                        <div class="col-md-6 col-12">
                                                            <label class="form-label extra-small fw-bold text-muted mb-1">TARGET PESERTA DIDIK</label>
                                                            <input type="text" name="babs[<?= $semKey ?>][<?= $idx ?>][target_siswa]" class="form-control rounded-3 small" value="<?= htmlspecialchars($m['target_siswa'] ?? '') ?>">
                                                        </div>
                                                        <div class="col-md-6 col-12">
                                                            <label class="form-label extra-small fw-bold text-muted mb-1">SARANA &amp; PRASARANA</label>
                                                            <input type="text" name="babs[<?= $semKey ?>][<?= $idx ?>][sarana_prasarana]" class="form-control rounded-3 small" value="<?= htmlspecialchars($m['sarana_prasarana'] ?? '') ?>">
                                                        </div>
                                                        <div class="col-md-6 col-12">
                                                            <label class="form-label extra-small fw-bold text-muted mb-1">DAFTAR PUSTAKA</label>
                                                            <input type="text" name="babs[<?= $semKey ?>][<?= $idx ?>][daftar_pustaka]" class="form-control rounded-3 small" value="<?= htmlspecialchars($m['daftar_pustaka'] ?? '') ?>">
                                                        </div>
                                                    </div>

                                                    <div class="row g-3 mb-3">
                                                        <div class="col-md-6 col-12">
                                                            <label class="form-label extra-small fw-bold text-muted mb-1">PEMAHAMAN BERMAKNA</label>
                                                            <textarea name="babs[<?= $semKey ?>][<?= $idx ?>][pemahaman_bermakna]" rows="3" class="form-control rounded-3 small"><?= htmlspecialchars($m['pemahaman_bermakna'] ?? '') ?></textarea>
                                                        </div>
                                                        <div class="col-md-6 col-12">
                                                            <label class="form-label extra-small fw-bold text-muted mb-1">PERTANYAAN PEMANTIK</label>
                                                            <textarea name="babs[<?= $semKey ?>][<?= $idx ?>][pertanyaan_pemantik]" rows="3" class="form-control rounded-3 small"><?= htmlspecialchars($m['pertanyaan_pemantik'] ?? '') ?></textarea>
                                                        </div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label extra-small fw-bold text-muted mb-1">1. KEGIATAN PENDAHULUAN</label>
                                                        <textarea name="babs[<?= $semKey ?>][<?= $idx ?>][kegiatan_pendahuluan]" rows="3" class="form-control rounded-3 small"><?= htmlspecialchars($m['kegiatan_pendahuluan'] ?? '') ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label extra-small fw-bold text-muted mb-1">2. KEGIATAN INTI</label>
                                                        <textarea name="babs[<?= $semKey ?>][<?= $idx ?>][kegiatan_inti]" rows="4" class="form-control rounded-3 small"><?= htmlspecialchars($m['kegiatan_inti'] ?? '') ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label extra-small fw-bold text-muted mb-1">3. KEGIATAN PENUTUP</label>
                                                        <textarea name="babs[<?= $semKey ?>][<?= $idx ?>][kegiatan_penutup]" rows="3" class="form-control rounded-3 small"><?= htmlspecialchars($m['kegiatan_penutup'] ?? '') ?></textarea>
                                                    </div>

                                                    <div class="row g-3 mb-3">
                                                        <div class="col-md-4 col-12">
                                                            <label class="form-label extra-small fw-bold text-muted mb-1">ASESMEN DIAGNOSTIK</label>
                                                            <textarea name="babs[<?= $semKey ?>][<?= $idx ?>][asesmen_diagnostik]" rows="2" class="form-control rounded-3 small"><?= htmlspecialchars($m['asesmen_diagnostik'] ?? '') ?></textarea>
                                                        </div>
                                                        <div class="col-md-4 col-12">
                                                            <label class="form-label extra-small fw-bold text-muted mb-1">ASESMEN FORMATIF</label>
                                                            <textarea name="babs[<?= $semKey ?>][<?= $idx ?>][asesmen_formatif]" rows="2" class="form-control rounded-3 small"><?= htmlspecialchars($m['asesmen_formatif'] ?? '') ?></textarea>
                                                        </div>
                                                        <div class="col-md-4 col-12">
                                                            <label class="form-label extra-small fw-bold text-muted mb-1">ASESMEN SUMATIF</label>
                                                            <textarea name="babs[<?= $semKey ?>][<?= $idx ?>][asesmen_sumatif]" rows="2" class="form-control rounded-3 small"><?= htmlspecialchars($m['asesmen_sumatif'] ?? '') ?></textarea>
                                                        </div>
                                                    </div>

                                                    <div class="row g-3">
                                                        <div class="col-md-6 col-12">
                                                            <label class="form-label extra-small fw-bold text-muted mb-1">LEMBAR KERJA PESERTA DIDIK (LKPD)</label>
                                                            <textarea name="babs[<?= $semKey ?>][<?= $idx ?>][lkpd_content]" rows="3" class="form-control rounded-3 small"><?= htmlspecialchars($m['lkpd_content'] ?? '') ?></textarea>
                                                        </div>
                                                        <div class="col-md-6 col-12">
                                                            <label class="form-label extra-small fw-bold text-muted mb-1">GLOSARIUM</label>
                                                            <textarea name="babs[<?= $semKey ?>][<?= $idx ?>][glosarium]" rows="3" class="form-control rounded-3 small"><?= htmlspecialchars($m['glosarium'] ?? '') ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- TOMBOL SIMPAN -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-5">
                <a href="view.php?id=<?= $id ?>" class="btn btn-light border rounded-pill px-4 fw-bold text-secondary">
                    Batal
                </a>
                <button type="submit" class="btn btn-primary rounded-pill px-5 py-2.5 fw-bold shadow-sm">
                    <i class="bi bi-save me-1"></i> Simpan Semua Perubahan
                </button>
            </div>
        </form>

    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
