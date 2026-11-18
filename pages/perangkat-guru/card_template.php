<?php
/**
 * TEMPLATE KARTU BUTIR SOAL (PG, ISIAN, ESSAY)
 * Terintegrasi:
 * - Blok Teks Cerita / Wacana / Stimulus Bersama (Konsep A)
 * - Tampilan Sederhana & Zen (Tidak Ruwet)
 * - 100% Mobile Friendly (Opsi 1 Kolom di HP & Touch-Friendly)
 */

if (!function_exists('isTeksArabCard')) {
    function isTeksArabCard($text): bool {
        if ($text === null || $text === '') return false;
        return (bool) preg_match('/[\p{Arabic}]/u', $text);
    }
}

if (!function_exists('renderSoalCard')) {
    function renderSoalCard(array $s, int $displayNo, int $naskahId): string
    {
        $sId = (int) $s['id'];
        $tipe = $s['tipe_soal'];
        $uploadDir = __DIR__ . '/../../uploads/soal';
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        
        $hasWacana = !empty(trim($s['wacana_teks'] ?? ''));
        $hasGambar = !empty($s['gambar']) && file_exists($uploadDir . '/' . $s['gambar']);

        $isArabPertanyaan = isTeksArabCard($s['pertanyaan'] ?? '');
        $isArabWacanaJudul = isTeksArabCard($s['wacana_judul'] ?? '');
        $isArabWacanaTeks = isTeksArabCard($s['wacana_teks'] ?? '');

        ob_start();
        ?>
        <div class="card border rounded-3 mb-3 p-3 bg-white shadow-xs position-relative soal-card" 
             id="soal_item_<?= $sId ?>" 
             data-id="<?= $sId ?>" 
             data-tipe="<?= htmlspecialchars($tipe) ?>"
             style="border-color: #e2e8f0 !important; transition: all 0.25s ease;">
            
            <!-- ============================================================ -->
            <!-- BLOK TEKS CERITA / WACANA (KONSEP A)                         -->
            <!-- ============================================================ -->
            <div id="wacana_wrapper_<?= $sId ?>" class="mb-3">
                <div id="wacana_box_<?= $sId ?>" class="card rounded-3 p-3 bg-primary bg-opacity-10 border border-primary border-opacity-25 <?= $hasWacana ? '' : 'd-none' ?>">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary rounded-pill px-2.5 py-1">
                                <i class="bi bi-book-half me-1"></i> Teks Cerita / Wacana
                            </span>
                            <span class="small text-muted d-none d-sm-inline" style="font-size: 0.76rem;">
                                (Teks stimulus bacaan ini akan dicetak tepat di atas butir soal)
                            </span>
                        </div>
                        <button type="button" class="btn btn-sm btn-link text-danger p-0 text-decoration-none fw-semibold" 
                            onclick="hapusWacana(<?= $sId ?>)" title="Tutup / Hapus Teks Cerita Ini">
                            <i class="bi bi-x-circle me-1"></i> Hapus Wacana
                        </button>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="soal[<?= $sId ?>][wacana_judul]" id="wacana_judul_<?= $sId ?>" 
                            class="form-control form-control-sm bg-white <?= $isArabWacanaJudul ? 'arabic-input' : '' ?>" 
                            <?= $isArabWacanaJudul ? 'dir="rtl"' : '' ?>
                            value="<?= htmlspecialchars($s['wacana_judul'] ?? '') ?>" 
                            placeholder="Petunjuk wacana (Contoh: Bacalah teks cerita berikut untuk menjawab soal nomor <?= $displayNo ?> – ... :)">
                    </div>
                    <div>
                        <textarea name="soal[<?= $sId ?>][wacana_teks]" id="wacana_teks_<?= $sId ?>" 
                            class="form-control form-control-sm bg-white <?= $isArabWacanaTeks ? 'arabic-input' : '' ?>" 
                            <?= $isArabWacanaTeks ? 'dir="rtl"' : '' ?>
                            rows="3" 
                            placeholder="Tuliskan isi cerita, dialog muhadatsah, ayat/hadits, atau narasi soal di sini..."><?= htmlspecialchars($s['wacana_teks'] ?? '') ?></textarea>
                    </div>
                </div>

                <div id="wacana_btn_open_<?= $sId ?>" class="<?= $hasWacana ? 'd-none' : '' ?> mb-2">
                    <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 py-1 text-muted text-decoration-none" 
                        style="font-size: 0.78rem; background: #f8fafc;" 
                        onclick="tampilWacana(<?= $sId ?>)">
                        <i class="bi bi-book text-primary me-1"></i> + Sisipkan Teks Cerita / Wacana di Atas Soal Ini
                    </button>
                </div>
            </div>

            <!-- HEADER BUTIR SOAL -->
            <div class="d-flex justify-content-between align-items-center mb-2.5 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-1.5 flex-wrap">
                    <?php if ($tipe === 'pg'): ?>
                        <span class="badge bg-dark rounded-pill px-2.5 py-1 soal-nomor-badge">Soal No. <?= $displayNo ?></span>
                        <input type="hidden" name="soal[<?= $sId ?>][nomor_urut]" class="input-nomor-urut" value="<?= $displayNo ?>">
                        <span class="badge bg-info-subtle text-info-emphasis border">Pilihan Ganda</span>
                    <?php elseif ($tipe === 'isian'): ?>
                        <span class="badge rounded-pill px-2.5 py-1 text-white soal-nomor-badge" style="background: #0d9488;">Isian No. <?= $displayNo ?></span>
                        <input type="hidden" name="soal[<?= $sId ?>][nomor_urut]" class="input-nomor-urut" value="<?= $displayNo ?>">
                        <span class="badge bg-teal-subtle text-teal-emphasis border" style="background: #ccfbf1; color: #0f766e;">Isian Singkat</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1 soal-nomor-badge">Essay No. <?= $displayNo ?></span>
                        <input type="hidden" name="soal[<?= $sId ?>][nomor_urut]" class="input-nomor-urut" value="<?= $displayNo ?>">
                        <span class="badge bg-warning-subtle text-warning-emphasis border" style="background: #fef3c7; color: #b45309;">Uraian / Essay</span>
                    <?php endif; ?>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <!-- TOGGLE GAMBAR -->
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-0.5 <?= $hasGambar ? 'active' : '' ?>" 
                        onclick="toggleImgBox(<?= $sId ?>)" title="Lampirkan Gambar" style="font-size: 0.76rem;">
                        <i class="bi bi-image"></i> <span class="d-none d-sm-inline">Gambar</span>
                    </button>

                    <!-- BOBOT POIN -->
                    <div class="d-flex align-items-center gap-1">
                        <span class="small text-muted fw-bold d-none d-sm-inline" style="font-size: 0.76rem;">Poin:</span>
                        <input type="number" step="0.5" min="0.5" max="50" name="soal[<?= $sId ?>][poin]" 
                            class="form-control form-control-sm font-monospace text-center poin-input px-1" 
                            style="width: 58px; font-size: 0.82rem;" value="<?= (float)$s['poin'] ?>" onchange="hitungLiveTotal()">
                    </div>

                    <!-- HAPUS SOAL -->
                    <button type="button" class="btn btn-sm btn-outline-danger rounded-circle p-1" title="Hapus Butir Soal"
                        onclick="hapusSoalAjax(<?= $sId ?>)">
                        <i class="bi bi-trash" style="font-size: 0.88rem;"></i>
                    </button>
                </div>
            </div>

            <!-- TEKS PERTANYAAN -->
            <div class="mb-2.5">
                <textarea name="soal[<?= $sId ?>][pertanyaan]" class="form-control question-textarea <?= $isArabPertanyaan ? 'arabic-input' : '' ?>" 
                    <?= $isArabPertanyaan ? 'dir="rtl"' : '' ?>
                    rows="<?= $tipe === 'isian' ? 2 : 3 ?>" required 
                    placeholder="<?= $tipe === 'isian' ? 'Tuliskan pertanyaan / kalimat rumpang di sini...' : ($tipe === 'essay' ? 'Tuliskan teks pertanyaan uraian / kasus di sini...' : 'Tuliskan pertanyaan soal di sini...') ?>" 
                    style="font-size: 0.95rem; line-height: 1.55;"><?= htmlspecialchars($s['pertanyaan']) ?></textarea>
            </div>

            <!-- GAMBAR PENDUKUNG (COLLAPSIBLE - HANYA MUNCUL JIKA DIPERLUKAN) -->
            <div id="img_box_<?= $sId ?>" class="mb-2.5 p-2.5 rounded-3 bg-light border <?= $hasGambar ? '' : 'd-none' ?>">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="small fw-bold text-muted mb-0">
                        <i class="bi bi-image me-1"></i> Gambar / Ilustrasi Pendukung Soal:
                    </label>
                    <button type="button" class="btn btn-sm btn-link text-muted p-0 text-decoration-none" onclick="toggleImgBox(<?= $sId ?>)">
                        <i class="bi bi-chevron-up"></i> Tutup
                    </button>
                </div>
                <?php if ($hasGambar): ?>
                    <div class="d-flex align-items-center gap-3 my-2">
                        <img src="<?= $baseUrl ?>/uploads/soal/<?= htmlspecialchars($s['gambar']) ?>" alt="Ilustrasi" class="rounded border" style="max-height: 80px; max-width: 140px; object-fit: contain;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="delete_gambar[<?= $sId ?>]" value="1" id="del_img_<?= $sId ?>">
                            <label class="form-check-label text-danger small fw-semibold" for="del_img_<?= $sId ?>">
                                Hapus gambar ini
                            </label>
                        </div>
                    </div>
                <?php endif; ?>
                <input type="file" name="gambar_soal[<?= $sId ?>]" class="form-control form-control-sm" accept="image/*">
            </div>

            <!-- BAGIAN KHUSUS PILIHAN GANDA (A - D) -->
            <?php if ($tipe === 'pg'): ?>
                <div class="row g-2">
                    <?php 
                    $opsiCols = [
                        'A' => $s['pilihan_a'],
                        'B' => $s['pilihan_b'],
                        'C' => $s['pilihan_c'],
                        'D' => $s['pilihan_d'],
                    ];
                    $currentKey = strtoupper(trim($s['kunci_jawaban'] ?? 'A'));
                    foreach ($opsiCols as $huruf => $val):
                        $isKey = ($currentKey === $huruf);
                        $isArabVal = isTeksArabCard($val ?? '');
                    ?>
                        <!-- 1 KOLOM DI MOBILE (col-12), 2 KOLOM DI TABLET/DESKTOP (col-md-6) -->
                        <div class="col-12 col-md-6">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white fw-bold opsi-letter-label <?= $isKey ? 'text-success border-success' : 'text-muted' ?>" style="width: 38px; justify-content: center;">
                                    <?= $huruf ?>.
                                </span>
                                <input type="text" name="soal[<?= $sId ?>][pilihan_<?= strtolower($huruf) ?>]" 
                                    class="form-control opsi-text-input <?= $isKey ? 'border-success bg-success-subtle' : '' ?> <?= $isArabVal ? 'arabic-input' : '' ?>" 
                                    <?= $isArabVal ? 'dir="rtl"' : '' ?>
                                    value="<?= htmlspecialchars($val ?? '') ?>" placeholder="Pilihan <?= $huruf ?>" required 
                                    style="font-size: 0.92rem;">
                                <div class="input-group-text bg-white px-2.5">
                                    <input class="form-check-input mt-0 opsi-key-radio" type="radio" 
                                        name="soal[<?= $sId ?>][kunci_jawaban]" value="<?= $huruf ?>" 
                                        <?= $isKey ? 'checked' : '' ?> 
                                        onchange="updateKeyVisual(this)"
                                        title="Tandai sebagai Kunci Jawaban Benar"
                                        style="cursor: pointer; width: 1.15em; height: 1.15em;">
                                    <span class="small text-muted ms-1 d-none d-sm-inline" style="font-size: 0.72rem;">Kunci</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php elseif ($tipe === 'isian'): 
                $isArabKunci = isTeksArabCard($s['kunci_jawaban'] ?? '');
            ?>
                <!-- KUNCI ISIAN -->
                <div>
                    <label class="form-label fw-bold text-muted" style="font-size: 0.78rem;">Kunci Jawaban Singkat (Pedoman Guru):</label>
                    <input type="text" name="soal[<?= $sId ?>][kunci_jawaban]" 
                        class="form-control form-control-sm bg-white <?= $isArabKunci ? 'arabic-input' : '' ?>" 
                        <?= $isArabKunci ? 'dir="rtl"' : '' ?>
                        value="<?= htmlspecialchars($s['kunci_jawaban'] ?? '') ?>" placeholder="Jawaban singkat yang benar...">
                </div>

            <?php else: 
                $isArabKunci = isTeksArabCard($s['kunci_jawaban'] ?? '');
            ?>
                <!-- RUBRIK ESSAY -->
                <div>
                    <label class="form-label fw-bold text-muted" style="font-size: 0.78rem;">Pedoman Penskoran / Rubrik Guru:</label>
                    <textarea name="soal[<?= $sId ?>][kunci_jawaban]" 
                        class="form-control form-control-sm bg-white <?= $isArabKunci ? 'arabic-input' : '' ?>" 
                        <?= $isArabKunci ? 'dir="rtl"' : '' ?>
                        rows="2" 
                        placeholder="Rubrik penilaian atau rincian poin jawaban lengkap..."><?= htmlspecialchars($s['kunci_jawaban'] ?? '') ?></textarea>
                </div>
            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }
}
