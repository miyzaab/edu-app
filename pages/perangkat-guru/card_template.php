<?php
/**
 * TEMPLATE KARTU BUTIR SOAL (PG, ISIAN, ESSAY)
 * Digunakan bersama oleh edit.php dan api_soal.php (AJAX)
 */

if (!function_exists('renderSoalCard')) {
    function renderSoalCard(array $s, int $displayNo, int $naskahId): string
    {
        $sId = (int) $s['id'];
        $tipe = $s['tipe_soal'];
        $uploadDir = __DIR__ . '/../../uploads/soal';
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';

        ob_start();
        ?>
        <div class="card border rounded-3 mb-4 p-3 bg-light-subtle shadow-xs position-relative soal-card" 
             id="soal_item_<?= $sId ?>" 
             data-id="<?= $sId ?>" 
             data-tipe="<?= htmlspecialchars($tipe) ?>"
             style="transition: all 0.25s ease;">
            
            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-2">
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

                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center gap-1.5">
                        <label class="small fw-bold text-muted mb-0">Bobot Poin:</label>
                        <input type="number" step="0.5" min="0.5" max="50" name="soal[<?= $sId ?>][poin]" 
                            class="form-control form-control-sm font-monospace text-center poin-input" 
                            style="width: 70px;" value="<?= (float)$s['poin'] ?>" onchange="hitungLiveTotal()">
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger rounded-circle p-1" title="Hapus Butir Soal"
                        onclick="hapusSoalAjax(<?= $sId ?>)">
                        <i class="bi bi-trash" style="font-size: 0.9rem;"></i>
                    </button>
                </div>
            </div>

            <!-- Teks Pertanyaan -->
            <div class="mb-3">
                <label class="form-label fw-bold small text-muted">
                    <?= $tipe === 'isian' ? 'Pertanyaan / Kalimat Rumpang:' : ($tipe === 'essay' ? 'Teks Pertanyaan Uraian / Kasus:' : 'Teks Pertanyaan Soal:') ?>
                </label>
                <textarea name="soal[<?= $sId ?>][pertanyaan]" class="form-control question-textarea" 
                    rows="<?= $tipe === 'isian' ? 2 : 3 ?>" required 
                    placeholder="<?= $tipe === 'isian' ? 'Contoh: Ibukota negara Indonesia adalah ...' : ($tipe === 'essay' ? 'Jelaskan secara mendalam tentang...' : 'Tuliskan pertanyaan soal...') ?>"><?= htmlspecialchars($s['pertanyaan']) ?></textarea>
            </div>

            <!-- Bagian Khusus PG (Gambar + Pilihan Jawaban A-D) -->
            <?php if ($tipe === 'pg'): ?>
                <!-- Gambar Pendukung -->
                <div class="mb-3 p-2.5 rounded-3 bg-white border">
                    <label class="form-label fw-bold small text-muted d-block mb-1">
                        <i class="bi bi-image me-1"></i> Gambar / Ilustrasi Pendukung Soal (Opsional):
                    </label>
                    <?php if (!empty($s['gambar']) && file_exists($uploadDir . '/' . $s['gambar'])): ?>
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <img src="<?= $baseUrl ?>/uploads/soal/<?= htmlspecialchars($s['gambar']) ?>" alt="Ilustrasi" class="rounded border" style="max-height: 90px; max-width: 160px; object-fit: contain;">
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

                <!-- Opsi Jawaban A, B, C, D & Kunci -->
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
                    ?>
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text bg-white fw-bold opsi-letter-label <?= $isKey ? 'text-success border-success' : 'text-muted' ?>" style="width: 42px; justify-content: center;">
                                    <?= $huruf ?>.
                                </span>
                                <input type="text" name="soal[<?= $sId ?>][pilihan_<?= strtolower($huruf) ?>]" 
                                    class="form-control opsi-text-input <?= $isKey ? 'border-success bg-success-subtle' : '' ?>" 
                                    value="<?= htmlspecialchars($val ?? '') ?>" placeholder="Pilihan Jawaban <?= $huruf ?>" required>
                                <div class="input-group-text bg-white">
                                    <input class="form-check-input mt-0 opsi-key-radio" type="radio" 
                                        name="soal[<?= $sId ?>][kunci_jawaban]" value="<?= $huruf ?>" 
                                        <?= $isKey ? 'checked' : '' ?> 
                                        onchange="updateKeyVisual(this)"
                                        title="Tandai sebagai Kunci Jawaban Benar">
                                    <span class="small text-muted ms-1" style="font-size: 0.72rem;">Kunci</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php elseif ($tipe === 'isian'): ?>
                <!-- Kunci Jawaban Isian Singkat -->
                <div class="mb-1">
                    <label class="form-label fw-bold small text-muted">Kunci Jawaban Singkat (Untuk Pedoman Guru):</label>
                    <input type="text" name="soal[<?= $sId ?>][kunci_jawaban]" class="form-control bg-white" 
                        value="<?= htmlspecialchars($s['kunci_jawaban'] ?? '') ?>" placeholder="Jawaban singkat yang benar...">
                </div>

            <?php else: ?>
                <!-- Pedoman Penskoran Essay -->
                <div class="mb-1">
                    <label class="form-label fw-bold small text-muted">Pedoman Penskoran / Jawaban Model (Untuk Kunci Guru):</label>
                    <textarea name="soal[<?= $sId ?>][kunci_jawaban]" class="form-control bg-white" rows="2" 
                        placeholder="Rubrik penilaian atau rincian poin jawaban lengkap..."><?= htmlspecialchars($s['kunci_jawaban'] ?? '') ?></textarea>
                </div>
            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }
}
