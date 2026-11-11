<?php
/**
 * PERANGKAT AJAR KURIKULUM MERDEKA - DOKUMEN HUB & PREVIEW (VIEW.PHP)
 * Menampilkan ringkasan lengkap, pusat download PDF (Bundel, Semester, atau Per Bab),
 * serta fitur edit narasi langsung per bab sebelum cetak.
 */
$pageTitle  = 'Dokumen Perangkat Ajar';
$activePage = 'perangkat-ajar';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('perangkat_ajar');

$pdo = getConnection();
$userId = (int)$_SESSION['user_id'];
$userRole = strtolower($_SESSION['role'] ?? '');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT p.*, u.nama_lengkap AS nama_guru_user, gd.nip AS nip_guru_user 
    FROM perangkat_ajar p 
    LEFT JOIN users u ON p.user_id = u.id 
    LEFT JOIN guru_detail gd ON u.id = gd.user_id 
    WHERE p.id = :id LIMIT 1
");
$stmt->execute([':id' => $id]);
$doc = $stmt->fetch();

if (!$doc) {
    redirect('index.php', 'danger', 'Dokumen Perangkat Ajar tidak ditemukan.');
}

// Hak akses: admin atau pembuat dokumen
$canEdit = ($userRole === 'admin' || $doc['user_id'] == $userId);

$modulData = json_decode($doc['modul_ajar_json'] ?? '{}', true) ?: [];
$smt1 = $modulData['semester_1'] ?? ['bab_list' => []];
$smt2 = $modulData['semester_2'] ?? ['bab_list' => []];

// Nama Guru: Utamakan nama lengkap resmi dari tabel users (dengan gelar akademik)
$rawGuruName = !empty($doc['nama_guru_user']) ? $doc['nama_guru_user'] : (!empty($modulData['nama_guru']) ? $modulData['nama_guru'] : ($_SESSION['nama_lengkap'] ?? 'Guru Pengampu'));
$namaGuru    = preg_replace('/^(ustadz|ustadzah|ust|ustz)\.?\s+/i', '', trim($rawGuruName));

// NIP Guru: Ambil dari database guru_detail resmi
$nipGuru = !empty($doc['nip_guru_user']) && $doc['nip_guru_user'] !== '—' && $doc['nip_guru_user'] !== '-'
    ? $doc['nip_guru_user']
    : (!empty($modulData['nip_guru']) && $modulData['nip_guru'] !== '—' && $modulData['nip_guru'] !== '-' ? $modulData['nip_guru'] : '—');

// Auto-heal jika dokumen lama belum memiliki bab terstruktur
if (empty($smt1['bab_list']) && empty($smt2['bab_list'])) {
    require_once __DIR__ . '/../../includes/perangkat_generator.php';
    $rawBab = $modulData['poin_bab_raw'] ?? '';
    $rawS1 = '';
    $rawS2 = '';
    if (!empty($rawBab)) {
        $lines = preg_split('/[\r\n]+/', trim($rawBab));
        $lines = array_filter(array_map('trim', $lines));
        $half = max(1, (int)ceil(count($lines) / 2));
        $rawS1 = implode("\n", array_slice($lines, 0, $half));
        $rawS2 = implode("\n", array_slice($lines, $half));
    }
    $gen = PerangkatGenerator::generatePerangkatLengkap([
        'mapel'          => $doc['mapel'],
        'kelas'          => $doc['kelas'],
        'jp_per_pekan'   => (int)($doc['jp_per_pekan'] ?? 2),
        'tahun_ajaran'   => $doc['tahun_ajaran'] ?? (date('Y') . '/' . (date('Y') + 1)),
        'nama_sekolah'   => getSetting('nama_sekolah', SCHOOL_NAME),
        'nama_guru'      => $namaGuru,
        'nip_guru'       => $nipGuru,
        'nama_kepsek'    => getSetting('nama_kepsek', 'Kepala Sekolah'),
        'nip_kepsek'     => getSetting('nip_kepsek', '—'),
        'bab_semester_1' => $rawS1,
        'bab_semester_2' => $rawS2
    ]);
    try {
        $upd = $pdo->prepare("
            UPDATE perangkat_ajar 
            SET modul_ajar_json = :json,
                capaian_pembelajaran = :cp,
                tujuan_pembelajaran = :tp,
                alur_tujuan_pembelajaran = :atp
            WHERE id = :id
        ");
        $upd->execute([
            ':json' => json_encode($gen['modul_json'], JSON_UNESCAPED_UNICODE),
            ':cp'   => $gen['capaian_pembelajaran'],
            ':tp'   => $gen['tujuan_pembelajaran'],
            ':atp'  => $gen['alur_tujuan_pembelajaran'],
            ':id'   => $id
        ]);
        $modulData = $gen['modul_json'];
        $smt1 = $modulData['semester_1'] ?? ['bab_list' => []];
        $smt2 = $modulData['semester_2'] ?? ['bab_list' => []];
    } catch (Exception $e) {}
}

$allBabList = array_merge(
    $smt1['bab_list'] ?? [],
    $smt2['bab_list'] ?? []
);

$settingNamaKepsek = getSetting('nama_kepsek', '');
if (empty($settingNamaKepsek)) $settingNamaKepsek = getSetting('nama_kepala_sekolah', '');

if (!empty($settingNamaKepsek) && $settingNamaKepsek !== 'Kepala Sekolah, M.Pd') {
    $namaKepsek = $settingNamaKepsek;
} elseif (!empty($modulData['nama_kepsek']) && $modulData['nama_kepsek'] !== 'Kepala Sekolah, M.Pd') {
    $namaKepsek = $modulData['nama_kepsek'];
} elseif (!empty($settingNamaKepsek)) {
    $namaKepsek = $settingNamaKepsek;
} else {
    $namaKepsek = 'Kepala Sekolah';
}

$settingNipKepsek = getSetting('nip_kepsek', '');
if ($settingNipKepsek !== '' && $settingNipKepsek !== null && $settingNipKepsek !== '—' && $settingNipKepsek !== '-') {
    $nipKepsek = $settingNipKepsek;
} elseif (!empty($modulData['nip_kepsek']) && $modulData['nip_kepsek'] !== '—' && $modulData['nip_kepsek'] !== '-') {
    $nipKepsek = $modulData['nip_kepsek'];
} elseif ($settingNipKepsek !== '') {
    $nipKepsek = $settingNipKepsek;
} else {
    $nipKepsek = '—';
}
$jpPerPekan = (int)($doc['jp_per_pekan'] ?? ($modulData['jp_per_pekan'] ?? 4));

// PROSES UPDATE CEPAT BAB TERTENTU VIA MODAL EDIT
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_bab_detail' && $canEdit) {
    $semNum  = (int)($_POST['semester_num'] ?? 1);
    $babIdx  = (int)($_POST['bab_index'] ?? 0);

    $targetSemKey = ($semNum === 2) ? 'semester_2' : 'semester_1';

    if (isset($modulData[$targetSemKey]['bab_list'][$babIdx])) {
        $curBab = &$modulData[$targetSemKey]['bab_list'][$babIdx];

        // Update Field Pokok
        $curBab['judul_bersih'] = trim($_POST['judul_bab'] ?? $curBab['judul_bersih']);
        $curBab['judul_lengkap'] = "Bab " . $curBab['nomor'] . ": " . $curBab['judul_bersih'];
        $curBab['alokasi_jp'] = (int)($_POST['alokasi_jp'] ?? $curBab['alokasi_jp']);
        $curBab['jumlah_pertemuan'] = (int)($_POST['jumlah_pertemuan'] ?? $curBab['jumlah_pertemuan']);
        $curBab['cp_teks'] = trim($_POST['cp_teks'] ?? $curBab['cp_teks']);
        $curBab['atp_teks'] = trim($_POST['atp_teks'] ?? ($curBab['atp_teks'] ?? ''));

        // Update TP
        $rawTp = trim($_POST['tp_list_raw'] ?? '');
        if ($rawTp !== '') {
            $curBab['tp_list'] = array_values(array_filter(array_map('trim', explode("\n", $rawTp))));
        }

        // Update Modul Ajar Detail
        if (!isset($curBab['modul'])) $curBab['modul'] = [];
        $curBab['modul']['judul_bab']            = $curBab['judul_bersih'];
        $curBab['modul']['alokasi_jp']           = $curBab['alokasi_jp'];
        $curBab['modul']['jumlah_pertemuan']     = $curBab['jumlah_pertemuan'];
        $curBab['modul']['alokasi_waktu']        = "{$curBab['jumlah_pertemuan']} Pertemuan ({$curBab['alokasi_jp']} JP × 40 Menit)";
        $curBab['modul']['model_pembelajaran']   = trim($_POST['model_pembelajaran'] ?? ($curBab['modul']['model_pembelajaran'] ?? ''));
        $curBab['modul']['target_siswa']         = trim($_POST['target_siswa'] ?? ($curBab['modul']['target_siswa'] ?? ''));
        $curBab['modul']['sarana_prasarana']     = trim($_POST['sarana_prasarana'] ?? ($curBab['modul']['sarana_prasarana'] ?? ''));
        $curBab['modul']['daftar_pustaka']       = trim($_POST['daftar_pustaka'] ?? ($curBab['modul']['daftar_pustaka'] ?? ''));
        $curBab['modul']['pemahaman_bermakna']   = trim($_POST['pemahaman_bermakna'] ?? ($curBab['modul']['pemahaman_bermakna'] ?? ''));
        $curBab['modul']['pertanyaan_pemantik']  = trim($_POST['pertanyaan_pemantik'] ?? ($curBab['modul']['pertanyaan_pemantik'] ?? ''));
        $curBab['modul']['kegiatan_pendahuluan'] = trim($_POST['kegiatan_pendahuluan'] ?? ($curBab['modul']['kegiatan_pendahuluan'] ?? ''));
        $curBab['modul']['kegiatan_inti']        = trim($_POST['kegiatan_inti'] ?? ($curBab['modul']['kegiatan_inti'] ?? ''));
        $curBab['modul']['kegiatan_penutup']     = trim($_POST['kegiatan_penutup'] ?? ($curBab['modul']['kegiatan_penutup'] ?? ''));
        $curBab['modul']['asesmen_formatif']     = trim($_POST['asesmen_formatif'] ?? ($curBab['modul']['asesmen_formatif'] ?? ''));
        $curBab['modul']['asesmen_sumatif']      = trim($_POST['asesmen_sumatif'] ?? ($curBab['modul']['asesmen_sumatif'] ?? ''));
        $curBab['modul']['lkpd_content']         = trim($_POST['lkpd_content'] ?? ($curBab['modul']['lkpd_content'] ?? ''));
        $curBab['modul']['glosarium']            = trim($_POST['glosarium'] ?? ($curBab['modul']['glosarium'] ?? ''));

        // Sinkronkan juga kolom ringkasan CP, TP, ATP
        $cpLines = [];
        $tpLines = [];
        $atpLines = [];
        foreach (['semester_1' => 'Semester 1 (Ganjil)', 'semester_2' => 'Semester 2 (Genap)'] as $sk => $sLbl) {
            foreach (($modulData[$sk]['bab_list'] ?? []) as $bi) {
                $lbl = ($bi['judul_lengkap'] ?? ('Bab ' . ($bi['nomor'] ?? ''))) . " ({$sLbl})";
                if (!empty($bi['cp_teks'])) $cpLines[] = "{$lbl}:\n" . $bi['cp_teks'];
                if (!empty($bi['tp_list'])) $tpLines[] = "{$lbl}:\n- " . implode("\n- ", $bi['tp_list']);
                if (!empty($bi['atp_teks'])) $atpLines[] = "{$lbl} [" . ($bi['alokasi_jp'] ?? 0) . " JP]: " . $bi['atp_teks'];
            }
        }
        $cpGlobalText = trim($modulData['cp_global'] ?? '');
        $cpSummary  = ($cpGlobalText !== '' ? $cpGlobalText . "\n\n" : '') . implode("\n\n", $cpLines);
        $tpSummary  = implode("\n\n", $tpLines);
        $atpSummary = implode("\n", $atpLines);

        // Simpan JSON & kolom ringkasan kembali ke database
        $newJson = json_encode($modulData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $stmtUp = $pdo->prepare("UPDATE perangkat_ajar SET modul_ajar_json = :j, capaian_pembelajaran = :cp, tujuan_pembelajaran = :tp, alur_tujuan_pembelajaran = :atp WHERE id = :id");
        $stmtUp->execute([':j' => $newJson, ':cp' => $cpSummary, ':tp' => $tpSummary, ':atp' => $atpSummary, ':id' => $id]);

        redirect("view.php?id={$id}&tab=modul_sem{$semNum}", 'success', "Alhamdulillah, data Bab " . $curBab['nomor'] . " berhasil diperbarui!");
    }
}

// PROSES UPDATE INFO UMUM MODUL (MODEL PEMBELAJARAN, TARGET SISWA, DAFTAR PUSTAKA)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_info_modul' && $canEdit) {
    $modelPem = trim($_POST['model_pembelajaran'] ?? '');
    $targetS  = trim($_POST['target_siswa'] ?? '');
    $pustaka  = trim($_POST['daftar_pustaka'] ?? '');
    $applyAll = !empty($_POST['apply_all_babs']);

    if (!empty($modelPem)) $modulData['model_pembelajaran'] = $modelPem;
    if (!empty($targetS))  $modulData['target_siswa'] = $targetS;
    if (!empty($pustaka))  $modulData['daftar_pustaka'] = $pustaka;

    if ($applyAll) {
        foreach (['semester_1', 'semester_2'] as $semKey) {
            if (isset($modulData[$semKey]['bab_list']) && is_array($modulData[$semKey]['bab_list'])) {
                foreach ($modulData[$semKey]['bab_list'] as &$bItem) {
                    if (!isset($bItem['modul'])) $bItem['modul'] = [];
                    if (!empty($modelPem)) $bItem['modul']['model_pembelajaran'] = $modelPem;
                    if (!empty($targetS))  $bItem['modul']['target_siswa'] = $targetS;
                    if (!empty($pustaka))  $bItem['modul']['daftar_pustaka'] = $pustaka;
                }
                unset($bItem);
            }
        }
    }

    $newJson = json_encode($modulData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $stmtUp = $pdo->prepare("UPDATE perangkat_ajar SET modul_ajar_json = :j WHERE id = :id");
    $stmtUp->execute([':j' => $newJson, ':id' => $id]);

    redirect("view.php?id={$id}&tab=modul_sem1", 'success', 'Model Pembelajaran & Identitas Modul Ajar berhasil diperbarui!');
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/subnav.php';

$activeTab = $_GET['tab'] ?? 'cp';
?>

<div class="row justify-content-center">
    <div class="col-xl-11 col-12">

        <!-- HERO HEADER DOKUMEN -->
        <div class="card border-0 rounded-4 shadow-sm mb-4 overflow-hidden" 
             style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 70%, #0369a1 100%); color: #ffffff;">
            <div class="card-body p-4 p-md-5">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill fw-bold">
                            Kelas <?= htmlspecialchars($doc['kelas']) ?> (Fase <?= htmlspecialchars($doc['fase'] ?? 'D') ?>)
                        </span>
                        <span class="badge bg-white-subtle text-white border border-white-subtle px-3 py-1.5 rounded-pill fw-semibold">
                            Tahun Ajaran <?= htmlspecialchars($doc['tahun_ajaran']) ?>
                        </span>
                        <span class="badge bg-emerald-subtle text-emerald-300 border border-emerald-subtle px-3 py-1.5 rounded-pill fw-semibold" style="background: rgba(16, 185, 129, 0.2); color: #6ee7b7; border-color: rgba(16, 185, 129, 0.3);">
                            <?= $jpPerPekan ?> JP / Pekan
                        </span>
                    </div>

                    <?php if ($canEdit): ?>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#modalEditInfoModul">
                                <i class="bi bi-sliders me-1"></i> Edit Model Pembelajaran
                            </button>
                            <a href="edit.php?id=<?= $id ?>" class="btn btn-sm btn-light text-dark rounded-pill px-3 fw-bold">
                                <i class="bi bi-pencil-square me-1"></i> Edit Dokumen
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <h1 class="fw-extrabold mb-2" style="font-family: 'Outfit', sans-serif; font-size: 1.85rem;">
                    <?= htmlspecialchars($doc['mapel']) ?>
                </h1>
                <p class="text-white-50 mb-3 fs-6">
                    <?= htmlspecialchars($doc['topik'] ?: 'Perangkat Ajar Kurikulum Merdeka') ?>
                </p>

                <div class="d-flex flex-wrap align-items-center gap-4 pt-3 border-top border-secondary-subtle extra-small text-white-50">
                    <div>
                        <span class="d-block text-white fw-bold">Guru Pengampu:</span>
                        <span><?= htmlspecialchars($namaGuru) ?> (NIP: <?= htmlspecialchars($nipGuru) ?>)</span>
                    </div>
                    <div>
                        <span class="d-block text-white fw-bold">Kepala Sekolah:</span>
                        <span><?= htmlspecialchars($namaKepsek) ?> (NIP: <?= htmlspecialchars($nipKepsek) ?>)</span>
                    </div>
                    <div>
                        <span class="d-block text-white fw-bold">Beban Belajar:</span>
                        <span><?= htmlspecialchars($doc['alokasi_waktu']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- PUSAT DOWNLOAD PDF (PROMINENT ACTION BAR) -->
        <div class="card border-0 rounded-4 shadow-sm mb-4" style="background: #ffffff;">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <h6 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2" style="font-family: 'Outfit', sans-serif;">
                            <i class="bi bi-file-earmark-pdf-fill text-danger fs-5"></i>
                            Pusat Unduhan PDF Resmi
                        </h6>
                        <small class="text-muted extra-small">
                            Pilih unduh paket bundel 1 tahun utuh atau unduh per bagian sesuai kebutuhan administrasi sekolah.
                        </small>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <!-- Tombol Utama: Bundel 1 Tahun -->
                        <a href="print.php?id=<?= $id ?>&doc_type=all" target="_blank"
                           class="btn btn-emerald px-4 py-2.5 rounded-pill fw-extrabold text-white shadow-sm d-inline-flex align-items-center gap-2"
                           style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none;">
                            <i class="bi bi-box-arrow-down fs-5"></i>
                            <span>Unduh Bundel Lengkap 1 Tahun</span>
                        </a>

                        <?php if ($canEdit): ?>
                        <!-- Tombol Edit Perangkat Ajar di samping tombol unduh -->
                        <a href="edit.php?id=<?= $id ?>"
                           class="btn btn-primary px-4 py-2.5 rounded-pill fw-bold shadow-sm d-inline-flex align-items-center gap-2">
                            <i class="bi bi-pencil-square fs-5"></i>
                            <span>Edit Perangkat</span>
                        </a>
                        <?php endif; ?>

                        <!-- Dropdown Opsi Lainnya -->
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary px-3 py-2.5 rounded-pill fw-bold dropdown-toggle d-inline-flex align-items-center gap-1.5"
                                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-download"></i> Unduh Terpisah
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg rounded-3 border-0 py-2" style="min-width: 260px;">
                                <li><h6 class="dropdown-header extra-small text-uppercase fw-bold text-muted">Komponen Utama</h6></li>
                                <li>
                                    <a class="dropdown-item py-2 fw-semibold d-flex align-items-center gap-2" href="print.php?id=<?= $id ?>&doc_type=cp" target="_blank">
                                        <i class="bi bi-journal-text text-primary"></i> 1. Capaian Pembelajaran (CP)
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2 fw-semibold d-flex align-items-center gap-2" href="print.php?id=<?= $id ?>&doc_type=atp" target="_blank">
                                        <i class="bi bi-diagram-3-fill text-indigo" style="color: #6366f1;"></i> 2. Alur Tujuan Pembelajaran (ATP)
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2 fw-semibold d-flex align-items-center gap-2" href="print.php?id=<?= $id ?>&doc_type=tp" target="_blank">
                                        <i class="bi bi-bullseye text-warning"></i> 3. Tujuan Pembelajaran (TP)
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><h6 class="dropdown-header extra-small text-uppercase fw-bold text-muted">Modul Ajar Per Semester</h6></li>
                                <li>
                                    <a class="dropdown-item py-2 fw-semibold d-flex align-items-center gap-2" href="print.php?id=<?= $id ?>&doc_type=modul_sem1" target="_blank">
                                        <i class="bi bi-folder-fill text-success"></i> Modul Ajar Semester 1 (Ganjil)
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2 fw-semibold d-flex align-items-center gap-2" href="print.php?id=<?= $id ?>&doc_type=modul_sem2" target="_blank">
                                        <i class="bi bi-folder-fill text-info"></i> Modul Ajar Semester 2 (Genap)
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- NAV TABS KONTEN -->
        <ul class="nav nav-pills custom-pills-perangkat mb-4 gap-2" id="docTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-3 py-2 fw-bold <?= $activeTab === 'cp' ? 'active' : '' ?>" 
                        id="tab-cp-btn" data-bs-toggle="pill" data-bs-target="#tab-cp" type="button" role="tab">
                    <i class="bi bi-journal-text me-1.5"></i> Capaian Pembelajaran (CP)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-3 py-2 fw-bold <?= $activeTab === 'tp' ? 'active' : '' ?>" 
                        id="tab-tp-btn" data-bs-toggle="pill" data-bs-target="#tab-tp" type="button" role="tab">
                    <i class="bi bi-bullseye me-1.5"></i> Tujuan Pembelajaran (TP)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-3 py-2 fw-bold <?= $activeTab === 'atp' ? 'active' : '' ?>" 
                        id="tab-atp-btn" data-bs-toggle="pill" data-bs-target="#tab-atp" type="button" role="tab">
                    <i class="bi bi-diagram-3-fill me-1.5"></i> Alur Pembelajaran (ATP)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-3 py-2 fw-bold <?= $activeTab === 'modul_sem1' ? 'active' : '' ?>" 
                        id="tab-sem1-btn" data-bs-toggle="pill" data-bs-target="#tab-sem1" type="button" role="tab">
                    <i class="bi bi-folder-check me-1.5"></i> Modul Ajar Smt 1 (<?= count($smt1['bab_list'] ?? []) ?> Bab)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-3 py-2 fw-bold <?= $activeTab === 'modul_sem2' ? 'active' : '' ?>" 
                        id="tab-sem2-btn" data-bs-toggle="pill" data-bs-target="#tab-sem2" type="button" role="tab">
                    <i class="bi bi-folder-check me-1.5"></i> Modul Ajar Smt 2 (<?= count($smt2['bab_list'] ?? []) ?> Bab)
                </button>
            </li>
        </ul>

        <!-- TAB CONTENT -->
        <div class="tab-content" id="docTabsContent">

            <!-- TAB 1: CAPAIAN PEMBELAJARAN (CP) -->
            <div class="tab-pane fade <?= $activeTab === 'cp' ? 'show active' : '' ?>" id="tab-cp" role="tabpanel">
                <div class="card border-0 rounded-4 shadow-sm mb-4" style="background: #ffffff;">
                    <div class="card-body p-4 p-md-5">
                        <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
                            <div>
                                <h4 class="fw-bold mb-1" style="font-family: 'Outfit', sans-serif;">
                                    Capaian Pembelajaran (CP) Fase <?= htmlspecialchars($doc['fase']) ?>
                                </h4>
                                <p class="text-muted extra-small mb-0">
                                    Standar kompetensi akhir fase dan deskripsi capaian pembelajaran per bab pembahasan.
                                </p>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($canEdit): ?>
                                <a href="edit.php?id=<?= $id ?>#section-cp" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                                    <i class="bi bi-pencil-square me-1"></i> Edit CP
                                </a>
                                <?php endif; ?>
                                <a href="print.php?id=<?= $id ?>&doc_type=cp" target="_blank" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold">
                                    <i class="bi bi-file-earmark-pdf me-1"></i> Cetak PDF CP
                                </a>
                            </div>
                        </div>

                        <!-- Narasi CP Global -->
                        <div class="p-4 rounded-4 bg-light border mb-4">
                            <h6 class="fw-bold text-dark mb-2">Narasi CP Akhir Fase <?= htmlspecialchars($doc['fase']) ?>:</h6>
                            <p class="text-secondary small mb-0" style="line-height: 1.8; text-align: justify;">
                                <?= nl2br(htmlspecialchars($modulData['cp_global'] ?? $doc['capaian_pembelajaran'])) ?>
                            </p>
                        </div>

                        <!-- Matriks CP Per Bab -->
                        <h6 class="fw-bold text-dark mb-3">Distribusi Capaian Pembelajaran Per Bab:</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead class="table-light extra-small text-uppercase fw-bold">
                                    <tr>
                                        <th style="width: 28%;">Bab / Materi Pokok</th>
                                        <th style="width: 15%;">Semester</th>
                                        <th style="width: 57%;">Rumusan Capaian Pembelajaran</th>
                                    </tr>
                                </thead>
                                <tbody class="small">
                                    <?php 
                                    $allBabList = array_merge(
                                        $smt1['bab_list'] ?? [],
                                        $smt2['bab_list'] ?? []
                                    );
                                    ?>
                                    <?php if (!empty($allBabList)): ?>
                                        <?php foreach ($allBabList as $b): ?>
                                            <tr>
                                                <td class="fw-bold">
                                                    Bab <?= htmlspecialchars($b['nomor']) ?>: <?= htmlspecialchars($b['judul_bersih']) ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?= $b['semester'] === 1 ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-info-subtle text-info border border-info-subtle' ?> rounded-pill px-2.5 py-1 extra-small fw-bold">
                                                        Semester <?= $b['semester'] ?>
                                                    </span>
                                                </td>
                                                <td style="text-align: justify; line-height: 1.6;">
                                                    <?= htmlspecialchars($b['cp_teks']) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-4">Belum ada rincian bab.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: TUJUAN PEMBELAJARAN (TP) -->
            <div class="tab-pane fade <?= $activeTab === 'tp' ? 'show active' : '' ?>" id="tab-tp" role="tabpanel">
                <div class="card border-0 rounded-4 shadow-sm mb-4" style="background: #ffffff;">
                    <div class="card-body p-4 p-md-5">
                        <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
                            <div>
                                <h4 class="fw-bold mb-1" style="font-family: 'Outfit', sans-serif;">
                                    Tujuan Pembelajaran (TP) 1 Tahun Ajaran
                                </h4>
                                <p class="text-muted extra-small mb-0">
                                    Pemetaan indikator ketercapaian tujuan pembelajaran bertingkat Taksonomi Bloom per bab pembahasan.
                                </p>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($canEdit): ?>
                                <a href="edit.php?id=<?= $id ?>#section-bab" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                                    <i class="bi bi-pencil-square me-1"></i> Edit TP
                                </a>
                                <?php endif; ?>
                                <a href="print.php?id=<?= $id ?>&doc_type=tp" target="_blank" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold">
                                    <i class="bi bi-file-earmark-pdf me-1"></i> Cetak PDF TP
                                </a>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle border mb-0" style="border-radius: 12px; overflow: hidden;">
                                <thead class="table-light extra-small text-uppercase fw-bold">
                                    <tr>
                                        <th style="width: 22%;">Bab / Materi Pokok</th>
                                        <th style="width: 12%;" class="text-center">Semester</th>
                                        <th style="width: 52%;">Rumusan Tujuan Pembelajaran (TP)</th>
                                        <th style="width: 14%;" class="text-center">Alokasi Waktu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($allBabList)): ?>
                                        <?php foreach ($allBabList as $b): ?>
                                            <tr>
                                                <td class="fw-bold text-dark">
                                                    <span class="badge bg-secondary-subtle text-secondary border me-1">Bab <?= htmlspecialchars($b['nomor']) ?></span><br>
                                                    <?= htmlspecialchars($b['judul_bersih']) ?>
                                                </td>
                                                <td class="text-center small">
                                                    <span class="badge bg-light text-dark border">
                                                        Semester <?= $b['semester'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <ul class="mb-0 ps-3 small" style="line-height: 1.6;">
                                                        <?php foreach ($b['tp_list'] ?? [] as $tpItem): ?>
                                                            <li class="mb-1"><?= htmlspecialchars($tpItem) ?></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </td>
                                                <td class="text-center small fw-bold">
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1.5 rounded-pill">
                                                        <?= htmlspecialchars($b['alokasi_jp']) ?> JP
                                                    </span>
                                                    <div class="extra-small text-muted mt-1"><?= htmlspecialchars($b['jumlah_pertemuan']) ?> Pertemuan</div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">Belum ada rincian Tujuan Pembelajaran.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: ALUR TUJUAN PEMBELAJARAN (ATP) -->
            <div class="tab-pane fade <?= $activeTab === 'atp' ? 'show active' : '' ?>" id="tab-atp" role="tabpanel">
                <div class="card border-0 rounded-4 shadow-sm mb-4" style="background: #ffffff;">
                    <div class="card-body p-4 p-md-5">
                        <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
                            <div>
                                <h4 class="fw-bold mb-1" style="font-family: 'Outfit', sans-serif;">
                                    Alur Tujuan Pembelajaran (ATP) 1 Tahun Ajaran
                                </h4>
                                <p class="text-muted extra-small mb-0">
                                    Peta tahapan pembelajaran terstruktur lengkap dengan alokasi waktu JP dan indikator tujuan.
                                </p>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($canEdit): ?>
                                <a href="edit.php?id=<?= $id ?>#section-bab" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                                    <i class="bi bi-pencil-square me-1"></i> Edit ATP
                                </a>
                                <?php endif; ?>
                                <a href="print.php?id=<?= $id ?>&doc_type=atp" target="_blank" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold">
                                    <i class="bi bi-file-earmark-pdf me-1"></i> Cetak PDF ATP
                                </a>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead class="table-light extra-small text-uppercase fw-bold">
                                    <tr>
                                        <th style="width: 22%;">Bab &amp; Semester</th>
                                        <th style="width: 38%;">Tujuan Pembelajaran (TP)</th>
                                        <th style="width: 25%;">Alur Aktivitas Pembelajaran</th>
                                        <th style="width: 15%;" class="text-center">Alokasi Waktu</th>
                                    </tr>
                                </thead>
                                <tbody class="small">
                                    <?php foreach ($allBabList as $b): ?>
                                        <tr>
                                            <td>
                                                <strong class="d-block text-dark">Bab <?= htmlspecialchars($b['nomor']) ?>: <?= htmlspecialchars($b['judul_bersih']) ?></strong>
                                                <span class="badge <?= $b['semester'] === 1 ? 'bg-primary-subtle text-primary' : 'bg-indigo-subtle text-indigo' ?> rounded-pill extra-small mt-1">
                                                    Semester <?= $b['semester'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <ul class="mb-0 ps-3" style="line-height: 1.6;">
                                                    <?php foreach ($b['tp_list'] ?? [] as $tpItem): ?>
                                                        <li><?= htmlspecialchars($tpItem) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </td>
                                            <td style="text-align: justify; line-height: 1.5;">
                                                <?= htmlspecialchars($b['atp_teks']) ?>
                                            </td>
                                            <td class="text-center fw-bold text-primary">
                                                <?= htmlspecialchars($b['alokasi_jp']) ?> JP<br>
                                                <small class="text-muted fw-normal">(<?= htmlspecialchars($b['jumlah_pertemuan']) ?> Pertemuan)</small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: MODUL AJAR SEMESTER 1 -->
            <div class="tab-pane fade <?= $activeTab === 'modul_sem1' ? 'show active' : '' ?>" id="tab-sem1" role="tabpanel">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold mb-0 text-dark" style="font-family: 'Outfit', sans-serif;">
                        Daftar Modul Ajar Semester 1 (Ganjil)
                    </h5>
                    <a href="print.php?id=<?= $id ?>&doc_type=modul_sem1" target="_blank" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Cetak Seluruh Modul Semester 1
                    </a>
                </div>

                <?php if (!empty($smt1['bab_list'])): ?>
                    <div class="row g-4">
                        <?php foreach ($smt1['bab_list'] as $idx => $b): ?>
                            <?php $m = $b['modul'] ?? []; ?>
                            <div class="col-12">
                                <div class="card border-0 rounded-4 shadow-sm" style="background: #ffffff;">
                                    <div class="card-body p-4">
                                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-3 border-bottom">
                                            <div>
                                                <span class="badge bg-primary text-white px-2.5 py-1 rounded-pill extra-small fw-bold mb-1">
                                                    BAB <?= htmlspecialchars($b['nomor']) ?> • SEMESTER 1
                                                </span>
                                                <h4 class="fw-extrabold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">
                                                    <?= htmlspecialchars($b['judul_bersih']) ?>
                                                </h4>
                                                <div class="text-muted extra-small">
                                                    Alokasi: <strong class="text-primary"><?= htmlspecialchars($b['alokasi_jp']) ?> JP</strong> (<?= htmlspecialchars($b['jumlah_pertemuan']) ?> Pertemuan) • Model: <em><?= htmlspecialchars($m['model_pembelajaran'] ?? 'PBL') ?></em> • Target: <em><?= htmlspecialchars($m['target_siswa'] ?? '30 Siswa') ?></em>
                                                </div>
                                            </div>

                                            <div class="d-flex align-items-center gap-2">
                                                <a href="print.php?id=<?= $id ?>&doc_type=modul_bab&sem=1&bab_idx=<?= $idx ?>" target="_blank"
                                                   class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold">
                                                    <i class="bi bi-file-pdf me-1"></i> Unduh PDF Bab Ini
                                                </a>
                                                <?php if ($canEdit): ?>
                                                    <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 fw-bold"
                                                            onclick="openEditBabModal(1, <?= $idx ?>, <?= htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8') ?>)">
                                                        <i class="bi bi-pencil-square text-primary me-1"></i> Edit Bab
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row g-3">
                                            <div class="col-md-6 col-12">
                                                <div class="p-3 rounded-3 bg-light border h-100">
                                                    <h6 class="fw-bold text-dark extra-small text-uppercase mb-2">
                                                        <i class="bi bi-lightbulb-fill text-warning me-1"></i> Pemahaman Bermakna
                                                    </h6>
                                                    <p class="small text-secondary mb-0" style="line-height: 1.6;">
                                                        <?= nl2br(htmlspecialchars($m['pemahaman_bermakna'] ?? '—')) ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="col-md-6 col-12">
                                                <div class="p-3 rounded-3 bg-light border h-100">
                                                    <h6 class="fw-bold text-dark extra-small text-uppercase mb-2">
                                                        <i class="bi bi-question-circle-fill text-info me-1"></i> Pertanyaan Pemantik
                                                    </h6>
                                                    <p class="small text-secondary mb-0" style="line-height: 1.6;">
                                                        <?= nl2br(htmlspecialchars($m['pertanyaan_pemantik'] ?? '—')) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Sintaks Kegiatan Ringkas -->
                                        <div class="mt-3 pt-3 border-top">
                                            <h6 class="fw-bold text-dark extra-small text-uppercase mb-2">
                                                <i class="bi bi-play-circle-fill text-success me-1"></i> Sintaks Kegiatan Pembelajaran
                                            </h6>
                                            <div class="extra-small text-muted mb-1 fw-bold">1. Pendahuluan:</div>
                                            <p class="small text-secondary mb-2" style="line-height: 1.6;">
                                                <?php
                                                    $pendClean = $m['kegiatan_pendahuluan'] ?? '—';
                                                    $pendClean = str_replace('Guru mengucapkan salam hangat dan mengajak peserta didik berdoa bersama/membaca shalawat thibil qulub.', 'Guru mengucapkan salam hangat kepada peserta ajar.', $pendClean);
                                                    echo nl2br(htmlspecialchars($pendClean));
                                                ?>
                                            </p>
                                            <div class="extra-small text-muted mb-1 fw-bold">2. Kegiatan Inti:</div>
                                            <p class="small text-secondary mb-2" style="line-height: 1.6;">
                                                <?= nl2br(htmlspecialchars($m['kegiatan_inti'] ?? '—')) ?>
                                            </p>
                                            <div class="extra-small text-muted mb-1 fw-bold">3. Penutup:</div>
                                            <p class="small text-secondary mb-0" style="line-height: 1.6;">
                                                <?= nl2br(htmlspecialchars($m['kegiatan_penutup'] ?? '—')) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="card border-0 rounded-4 p-5 text-center bg-white shadow-sm">
                        <i class="bi bi-folder2-open text-muted fs-1 mb-2"></i>
                        <p class="text-muted mb-0">Belum ada bab untuk Semester 1.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB 4: MODUL AJAR SEMESTER 2 -->
            <div class="tab-pane fade <?= $activeTab === 'modul_sem2' ? 'show active' : '' ?>" id="tab-sem2" role="tabpanel">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold mb-0 text-dark" style="font-family: 'Outfit', sans-serif;">
                        Daftar Modul Ajar Semester 2 (Genap)
                    </h5>
                    <a href="print.php?id=<?= $id ?>&doc_type=modul_sem2" target="_blank" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Cetak Seluruh Modul Semester 2
                    </a>
                </div>

                <?php if (!empty($smt2['bab_list'])): ?>
                    <div class="row g-4">
                        <?php foreach ($smt2['bab_list'] as $idx => $b): ?>
                            <?php $m = $b['modul'] ?? []; ?>
                            <div class="col-12">
                                <div class="card border-0 rounded-4 shadow-sm" style="background: #ffffff;">
                                    <div class="card-body p-4">
                                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-3 border-bottom">
                                            <div>
                                                <span class="badge text-white px-2.5 py-1 rounded-pill extra-small fw-bold mb-1" style="background: #6366f1;">
                                                    BAB <?= htmlspecialchars($b['nomor']) ?> • SEMESTER 2
                                                </span>
                                                <h4 class="fw-extrabold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">
                                                    <?= htmlspecialchars($b['judul_bersih']) ?>
                                                </h4>
                                                <div class="text-muted extra-small">
                                                    Alokasi: <strong class="text-primary"><?= htmlspecialchars($b['alokasi_jp']) ?> JP</strong> (<?= htmlspecialchars($b['jumlah_pertemuan']) ?> Pertemuan) • Model: <em><?= htmlspecialchars($m['model_pembelajaran'] ?? 'PBL') ?></em> • Target: <em><?= htmlspecialchars($m['target_siswa'] ?? '30 Siswa') ?></em>
                                                </div>
                                            </div>

                                            <div class="d-flex align-items-center gap-2">
                                                <a href="print.php?id=<?= $id ?>&doc_type=modul_bab&sem=2&bab_idx=<?= $idx ?>" target="_blank"
                                                   class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold">
                                                    <i class="bi bi-file-pdf me-1"></i> Unduh PDF Bab Ini
                                                </a>
                                                <?php if ($canEdit): ?>
                                                    <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 fw-bold"
                                                            onclick="openEditBabModal(2, <?= $idx ?>, <?= htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8') ?>)">
                                                        <i class="bi bi-pencil-square text-primary me-1"></i> Edit Bab
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="row g-3">
                                            <div class="col-md-6 col-12">
                                                <div class="p-3 rounded-3 bg-light border h-100">
                                                    <h6 class="fw-bold text-dark extra-small text-uppercase mb-2">
                                                        <i class="bi bi-lightbulb-fill text-warning me-1"></i> Pemahaman Bermakna
                                                    </h6>
                                                    <p class="small text-secondary mb-0" style="line-height: 1.6;">
                                                        <?= nl2br(htmlspecialchars($m['pemahaman_bermakna'] ?? '—')) ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="col-md-6 col-12">
                                                <div class="p-3 rounded-3 bg-light border h-100">
                                                    <h6 class="fw-bold text-dark extra-small text-uppercase mb-2">
                                                        <i class="bi bi-question-circle-fill text-info me-1"></i> Pertanyaan Pemantik
                                                    </h6>
                                                    <p class="small text-secondary mb-0" style="line-height: 1.6;">
                                                        <?= nl2br(htmlspecialchars($m['pertanyaan_pemantik'] ?? '—')) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Sintaks Kegiatan Ringkas -->
                                        <div class="mt-3 pt-3 border-top">
                                            <h6 class="fw-bold text-dark extra-small text-uppercase mb-2">
                                                <i class="bi bi-play-circle-fill text-success me-1"></i> Sintaks Kegiatan Pembelajaran
                                            </h6>
                                            <div class="extra-small text-muted mb-1 fw-bold">1. Pendahuluan:</div>
                                            <p class="small text-secondary mb-2" style="line-height: 1.6;">
                                                <?php
                                                    $pendClean = $m['kegiatan_pendahuluan'] ?? '—';
                                                    $pendClean = str_replace('Guru mengucapkan salam hangat dan mengajak peserta didik berdoa bersama/membaca shalawat thibil qulub.', 'Guru mengucapkan salam hangat kepada peserta ajar.', $pendClean);
                                                    echo nl2br(htmlspecialchars($pendClean));
                                                ?>
                                            </p>
                                            <div class="extra-small text-muted mb-1 fw-bold">2. Kegiatan Inti:</div>
                                            <p class="small text-secondary mb-2" style="line-height: 1.6;">
                                                <?= nl2br(htmlspecialchars($m['kegiatan_inti'] ?? '—')) ?>
                                            </p>
                                            <div class="extra-small text-muted mb-1 fw-bold">3. Penutup:</div>
                                            <p class="small text-secondary mb-0" style="line-height: 1.6;">
                                                <?= nl2br(htmlspecialchars($m['kegiatan_penutup'] ?? '—')) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="card border-0 rounded-4 p-5 text-center bg-white shadow-sm">
                        <i class="bi bi-folder2-open text-muted fs-1 mb-2"></i>
                        <p class="text-muted mb-0">Belum ada bab untuk Semester 2.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </div>
</div>

<!-- MODAL QUICK EDIT BAB -->
<div class="modal fade" id="modalEditBab" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="fw-bold modal-title text-dark" id="modalEditBabTitle" style="font-family: 'Outfit', sans-serif;">
                        Edit Rincian Bab
                    </h5>
                    <small class="text-muted extra-small">Sesuaikan narasi atau alokasi jam sebelum dicetak ke PDF.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="view.php?id=<?= $id ?>">
                <input type="hidden" name="action" value="update_bab_detail">
                <input type="hidden" name="semester_num" id="editSemNum" value="1">
                <input type="hidden" name="bab_index" id="editBabIdx" value="0">

                <div class="modal-body py-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">JUDUL BAB</label>
                            <input type="text" name="judul_bab" id="editJudulBab" class="form-control rounded-3 fw-bold" required>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label extra-small fw-bold text-muted mb-1">ALOKASI JP</label>
                            <input type="number" name="alokasi_jp" id="editAlokasiJp" class="form-control rounded-3" min="1" max="100" required>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label extra-small fw-bold text-muted mb-1">JML PERTEMUAN</label>
                            <input type="number" name="jumlah_pertemuan" id="editJmlPertemuan" class="form-control rounded-3" min="1" max="50" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">MODEL PEMBELAJARAN</label>
                            <input type="text" name="model_pembelajaran" id="editModelPembelajaran" class="form-control rounded-3" placeholder="Contoh: Problem-Based Learning (PBL)">
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">TARGET PESERTA DIDIK (JUMLAH SISWA)</label>
                            <input type="text" name="target_siswa" id="editTargetSiswa" class="form-control rounded-3" placeholder="Contoh: 30 Siswa (Reguler)">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">SARANA &amp; PRASARANA</label>
                            <input type="text" name="sarana_prasarana" id="editSaranaPrasarana" class="form-control rounded-3" placeholder="Kitab Rujukan, Papan Tulis, dan alat tulis">
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">DAFTAR PUSTAKA (RUJUKAN / KITAB)</label>
                            <input type="text" name="daftar_pustaka" id="editDaftarPustaka" class="form-control rounded-3" placeholder="Contoh: Kitab Rujukan, Buku Paket Siswa">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label extra-small fw-bold text-muted mb-1">RUMUSAN CAPAIAN PEMBELAJARAN (CP) BAB</label>
                        <textarea name="cp_teks" id="editCpTeks" rows="2" class="form-control rounded-3 small"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label extra-small fw-bold text-muted mb-1">TUJUAN PEMBELAJARAN (TP) - 1 BARIS 1 TUJUAN</label>
                        <textarea name="tp_list_raw" id="editTpListRaw" rows="4" class="form-control rounded-3 small font-monospace"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label extra-small fw-bold text-muted mb-1">ALUR TUJUAN PEMBELAJARAN (ATP) BAB</label>
                        <textarea name="atp_teks" id="editAtpTeks" rows="2" class="form-control rounded-3 small"></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">PEMAHAMAN BERMAKNA</label>
                            <textarea name="pemahaman_bermakna" id="editPemahamanBermakna" rows="3" class="form-control rounded-3 small"></textarea>
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">PERTANYAAN PEMANTIK</label>
                            <textarea name="pertanyaan_pemantik" id="editPertanyaanPemantik" rows="3" class="form-control rounded-3 small"></textarea>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">KEGIATAN PENDAHULUAN</label>
                            <textarea name="kegiatan_pendahuluan" id="editKegiatanPendahuluan" rows="3" class="form-control rounded-3 small"></textarea>
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">KEGIATAN PENUTUP</label>
                            <textarea name="kegiatan_penutup" id="editKegiatanPenutup" rows="3" class="form-control rounded-3 small"></textarea>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label extra-small fw-bold text-muted mb-1">KEGIATAN INTI PEMBELAJARAN</label>
                        <textarea name="kegiatan_inti" id="editKegiatanInti" rows="4" class="form-control rounded-3 small"></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">LEMBAR KERJA PESERTA DIDIK (LKPD)</label>
                            <textarea name="lkpd_content" id="editLkpdContent" rows="3" class="form-control rounded-3 small font-monospace"></textarea>
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label extra-small fw-bold text-muted mb-1">GLOSARIUM / ISTILAH KUNCI</label>
                            <textarea name="glosarium" id="editGlosarium" rows="3" class="form-control rounded-3 small"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="bi bi-save me-1"></i> Simpan Perubahan Bab
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDIT CEPAT MODEL PEMBELAJARAN & INFO MODUL -->
<div class="modal fade" id="modalEditInfoModul" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="fw-bold modal-title text-dark" style="font-family: 'Outfit', sans-serif;">
                        <i class="bi bi-sliders text-primary me-2"></i> Edit Model Pembelajaran &amp; Modul
                    </h5>
                    <small class="text-muted extra-small">Sesuaikan model dan rincian modul ajar untuk seluruh bab.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="view.php?id=<?= $id ?>">
                <input type="hidden" name="action" value="update_info_modul">

                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label extra-small fw-bold text-muted mb-1">
                            MODEL PEMBELAJARAN
                        </label>
                        <input type="text" name="model_pembelajaran" id="modalModelPem" list="listModalModel" 
                               class="form-control rounded-3 py-2 fw-semibold" 
                               value="<?= htmlspecialchars($modulData['model_pembelajaran'] ?? ($modulData['semester_1']['bab_list'][0]['modul']['model_pembelajaran'] ?? 'Problem-Based Learning (PBL)')) ?>" required>
                        <datalist id="listModalModel">
                            <option value="Problem-Based Learning (PBL)">
                            <option value="Project-Based Learning (PjBL)">
                            <option value="Discovery Learning">
                            <option value="Inquiry Learning">
                            <option value="Metode Qiyasiyah (Deduktif-Drill), Latihan Tashrif Mandiri & Berpasangan">
                            <option value="Metode Istiqra'iyyah (Induktif) & Analisis Tarkib Kontekstual">
                            <option value="Direct Instruction, Simulasi Praktik Amaliah, & Kajian Masail">
                            <option value="Direct Method (Thariqah Mubasyarah) & Communicative Language Teaching (CLT)">
                            <option value="Metode Talaqqi, Musyafahah, & Pembelajaran Kontekstual">
                            <option value="Direct Instruction Praktik Gerak & Game-Based Learning">
                            <option value="Genre-Based Approach (Pembelajaran Berbasis Teks)">
                            <option value="Cooperative Learning">
                        </datalist>
                        <div class="form-text extra-small text-muted">
                            Pilih rekomendasi di atas atau ketik langsung model yang diinginkan.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label extra-small fw-bold text-muted mb-1">
                            TARGET PESERTA DIDIK
                        </label>
                        <input type="text" name="target_siswa" 
                               class="form-control rounded-3 py-2 fw-semibold" 
                               value="<?= htmlspecialchars($modulData['target_siswa'] ?? ($modulData['semester_1']['bab_list'][0]['modul']['target_siswa'] ?? '26 Siswa (Kelas VII)')) ?>" required>
                        <div class="form-text extra-small text-muted">
                            Jumlah peserta didik dalam kelas (sesuai data kelas di aplikasi).
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label extra-small fw-bold text-muted mb-1">
                            DAFTAR PUSTAKA
                        </label>
                        <input type="text" name="daftar_pustaka" 
                               class="form-control rounded-3 py-2 fw-semibold" 
                               value="<?= htmlspecialchars($modulData['daftar_pustaka'] ?? ($modulData['semester_1']['bab_list'][0]['modul']['daftar_pustaka'] ?? '')) ?>" 
                               placeholder="Kitab Rujukan / Buku Pegangan Siswa">
                    </div>

                    <div class="form-check form-switch mt-3 pt-2 border-top">
                        <input class="form-check-input" type="checkbox" name="apply_all_babs" id="checkApplyAll" value="1" checked>
                        <label class="form-check-label extra-small fw-bold text-dark" for="checkApplyAll">
                            Terapkan seragam ke seluruh Bab Semester 1 &amp; 2
                        </label>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="bi bi-save me-1"></i> Simpan Perubahan Modul
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openEditBabModal(semNum, babIdx, babData) {
        document.getElementById('editSemNum').value = semNum;
        document.getElementById('editBabIdx').value = babIdx;

        document.getElementById('modalEditBabTitle').textContent = `Edit Bab ${babData.nomor}: ${babData.judul_bersih}`;
        document.getElementById('editJudulBab').value = babData.judul_bersih || '';
        document.getElementById('editAlokasiJp').value = babData.alokasi_jp || 18;
        document.getElementById('editJmlPertemuan').value = babData.jumlah_pertemuan || 6;
        document.getElementById('editCpTeks').value = babData.cp_teks || '';
        document.getElementById('editAtpTeks').value = babData.atp_teks || '';
        
        const tpArr = babData.tp_list || [];
        document.getElementById('editTpListRaw').value = Array.isArray(tpArr) ? tpArr.join('\n') : tpArr;

        const m = babData.modul || {};
        document.getElementById('editModelPembelajaran').value = m.model_pembelajaran || '';
        document.getElementById('editTargetSiswa').value = m.target_siswa || '';
        document.getElementById('editSaranaPrasarana').value = m.sarana_prasarana || '';
        document.getElementById('editDaftarPustaka').value = m.daftar_pustaka || '';
        document.getElementById('editPemahamanBermakna').value = m.pemahaman_bermakna || '';
        document.getElementById('editPertanyaanPemantik').value = m.pertanyaan_pemantik || '';
        document.getElementById('editKegiatanPendahuluan').value = m.kegiatan_pendahuluan || '';
        document.getElementById('editKegiatanInti').value = m.kegiatan_inti || '';
        document.getElementById('editKegiatanPenutup').value = m.kegiatan_penutup || '';
        document.getElementById('editLkpdContent').value = m.lkpd_content || '';
        document.getElementById('editGlosarium').value = m.glosarium || '';

        const modal = new bootstrap.Modal(document.getElementById('modalEditBab'));
        modal.show();
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

