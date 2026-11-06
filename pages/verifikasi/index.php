<?php
/**
 * VERIFIKASI PEMBAYARAN - Halaman untuk Bendahara memverifikasi bukti transfer
 */
$pageTitle = 'Verifikasi Pembayaran';
$activePage = 'verifikasi';
require_once __DIR__ . '/../../config/auth.php';
requirePermission('verifikasi');

$pdo = getConnection();

// Ambil status filter (default: pending)
$filterStatus = $_GET['status'] ?? 'pending';
$statusCondition = "";
if (in_array($filterStatus, ['pending', 'disetujui', 'ditolak'])) {
    $statusCondition = " AND p.status = " . $pdo->quote($filterStatus);
}

// Query data pending (HANYA untuk SPP, Uang Pangkal, dan Pembayaran Lainnya - Topup Kantin masuk ke Modul Kantin)
$query = "
    SELECT p.*, s.nis, s.nama, k.nama_kelas, jp.nama_pembayaran,
        (CASE 
            WHEN p.jenis = 'spp' THEN (SELECT ps.id FROM pembayaran_spp ps WHERE ps.siswa_id = p.siswa_id AND ps.bulan = p.bulan AND ps.tahun = p.tahun ORDER BY ps.id DESC LIMIT 1)
            WHEN p.jenis = 'uang_pangkal' THEN (SELECT pup.id FROM pembayaran_uang_pangkal pup WHERE pup.siswa_id = p.siswa_id ORDER BY pup.id DESC LIMIT 1)
            WHEN p.jenis = 'lainnya' THEN (SELECT pl.id FROM pembayaran_lain pl WHERE pl.siswa_id = p.siswa_id AND pl.jenis_pembayaran_id = p.jenis_pembayaran_id ORDER BY pl.id DESC LIMIT 1)
            ELSE NULL 
        END) AS kwitansi_id
    FROM pembayaran_pending p
    LEFT JOIN siswa s ON p.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN jenis_pembayaran jp ON p.jenis_pembayaran_id = jp.id
    WHERE p.jenis IN ('spp', 'uang_pangkal', 'lainnya') $statusCondition
    ORDER BY p.created_at DESC
";
$pendingList = $pdo->query($query)->fetchAll();

// Hitung badge verifikasi pembayaran utama
$countPending = $pdo->query("SELECT COUNT(id) FROM pembayaran_pending WHERE status='pending' AND jenis IN ('spp', 'uang_pangkal', 'lainnya')")->fetchColumn();

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- SUB-TAB PILL NAVIGATION (Verifikasi & Riwayat Transaksi) -->
<div class="rekap-tabs-bar mb-4 overflow-auto pb-1">
    <div class="rekap-tabs-pill-container">
        <?php if (hasPermission('verifikasi')): ?>
            <a href="<?= BASE_URL ?>/pages/verifikasi/index.php"
                class="rekap-tab-pill <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/pages/verifikasi/') !== false ? 'active' : '' ?>">
                <i class="bi bi-shield-check"></i> Verifikasi Transfer
                <?php if (($countPending ?? 0) > 0): ?>
                    <span class="badge bg-danger ms-1 px-2 py-0.5 rounded-pill"><?= $countPending ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>
        <?php if (hasPermission('riwayat')): ?>
            <a href="<?= BASE_URL ?>/pages/riwayat/index.php"
                class="rekap-tab-pill <?= (strpos($_SERVER['REQUEST_URI'] ?? '', '/pages/riwayat/') !== false) ? 'active' : '' ?>">
                <i class="bi bi-clock-history"></i> Riwayat Pembayaran
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div class="modern-filter-nav">
        <a class="modern-filter-btn <?= $filterStatus === 'pending' ? 'active' : '' ?>" href="?status=pending">
            <i class="bi bi-hourglass-split me-1 text-warning"></i> Pending
            <?php if ($countPending > 0): ?>
                <span class="modern-filter-badge bg-warning text-dark ms-1"><?= $countPending ?></span>
            <?php endif; ?>
        </a>
        <a class="modern-filter-btn <?= $filterStatus === 'disetujui' ? 'active' : '' ?>" href="?status=disetujui">
            <i class="bi bi-check-circle-fill me-1 text-success"></i> Disetujui
        </a>
        <a class="modern-filter-btn <?= $filterStatus === 'ditolak' ? 'active' : '' ?>" href="?status=ditolak">
            <i class="bi bi-x-circle-fill me-1 text-danger"></i> Ditolak
        </a>
    </div>
    <div class="text-muted small">
        Total Filtered: <strong class="text-dark fw-bold"><?= count($pendingList) ?></strong> data
    </div>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Waktu Submit</th>
                    <th>Siswa</th>
                    <th>Jenis</th>
                    <th>Nominal</th>
                    <th>Catatan</th>
                    <th class="text-center">Bukti</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingList as $p): ?>
                    <tr>
                        <td class="text-muted small font-monospace"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?>
                        </td>
                        <td>
                            <div class="table-avatar-item">
                                <div class="table-avatar-circle"><?= strtoupper(substr($p['nama'], 0, 1)) ?></div>
                                <div>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($p['nama']) ?></div>
                                    <small class="text-muted font-monospace"><i
                                            class="bi bi-person-badge opacity-50 me-1"></i><?= htmlspecialchars($p['nama_kelas']) ?>
                                        • <?= htmlspecialchars($p['nis']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php
                            if ($p['jenis'] === 'spp')
                                echo '<span class="badge rounded-pill bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2.5 py-1 font-monospace fw-bold"><i class="bi bi-calendar-check me-1"></i> SPP (' . namaBulan($p['bulan']) . ' ' . $p['tahun'] . ')</span>';
                            elseif ($p['jenis'] === 'uang_pangkal')
                                echo '<span class="badge rounded-pill bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2.5 py-1 font-monospace fw-bold"><i class="bi bi-bank me-1"></i> Uang Pangkal</span>';
                            elseif ($p['jenis'] === 'topup_kantin')
                                echo '<span class="badge rounded-pill bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 px-2.5 py-1 font-monospace fw-bold"><i class="bi bi-wallet2 me-1"></i> Top-Up Kantin</span>';
                            elseif ($p['jenis'] === 'lainnya')
                                echo '<span class="badge rounded-pill bg-purple bg-opacity-10 text-purple border border-purple border-opacity-25 px-2.5 py-1 font-monospace fw-bold" style="background: rgba(168, 85, 247, 0.1); color: #9333ea; border: 1px solid rgba(168, 85, 247, 0.2);"><i class="bi bi-tags me-1"></i> ' . htmlspecialchars($p['nama_pembayaran']) . '</span>';
                            ?>
                        </td>
                        <td><span class="nominal-pill"><?= formatRupiah($p['nominal']) ?></span></td>
                        <td style="max-width:160px;white-space:normal;font-size:.82rem" class="text-muted">
                            <?= htmlspecialchars($p['catatan']) ?: '<span class="opacity-50">-</span>' ?>
                        </td>
                        <td class="text-center">
                            <?php
                            $buktiUrl = getBuktiUrl($p['bukti_transfer']);

                            // Normalisasi nomor WA Bendahara/Sekolah jika ada di pengaturan
                            $waBendaharaRaw = getSetting('telepon_sekolah', '');
                            $waBendaharaClean = preg_replace('/[^0-9]/', '', $waBendaharaRaw);
                            if (!empty($waBendaharaClean) && substr($waBendaharaClean, 0, 1) === '0') {
                                $waBendaharaClean = '62' . substr($waBendaharaClean, 1);
                            }

                            // Teks pesan konfirmasi pembayaran untuk Bendahara
                            $namaSekolahVal = getSetting('nama_sekolah', SCHOOL_NAME);
                            $jenisTeks = ($p['jenis'] === 'spp')
                                ? 'SPP (' . namaBulan($p['bulan']) . ' ' . $p['tahun'] . ')'
                                : (($p['jenis'] === 'uang_pangkal')
                                    ? 'Uang Pangkal'
                                    : (($p['jenis'] === 'topup_kantin') ? 'Top-Up Kantin' : ($p['nama_pembayaran'] ?? 'Pembayaran Lain')));

                            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
                            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                            $fullBuktiUrl = (strpos($buktiUrl, 'http') === 0) ? $buktiUrl : ($scheme . $host . $buktiUrl);

                            $msgWaBendahara = "Bismillah\n"
                                . "*KONFIRMASI PEMBAYARAN SISWA*\n\n"
                                . "• *Siswa:* " . $p['nama'] . " (" . $p['nama_kelas'] . " - " . $p['nis'] . ")\n"
                                . "• *Jenis:* " . $jenisTeks . "\n"
                                . "• *Nominal:* " . formatRupiah($p['nominal']) . "\n"
                                . "• *Waktu Submit:* " . date('d/m/Y H:i', strtotime($p['created_at'])) . "\n"
                                . ($p['catatan'] ? "• *Catatan:* " . $p['catatan'] . "\n" : "")
                                . "\n*Bukti Transfer:* " . $fullBuktiUrl;

                            $linkWaBendahara = "https://wa.me/" . ($waBendaharaClean ?: "") . "?text=" . urlencode($msgWaBendahara);

                            $kwitansiUrl = '';
                            if (!empty($p['kwitansi_id'])) {
                                if ($p['jenis'] === 'spp') {
                                    $kwitansiUrl = BASE_URL . '/pages/spp/kwitansi.php?id=' . $p['kwitansi_id'];
                                } elseif ($p['jenis'] === 'uang_pangkal') {
                                    $kwitansiUrl = BASE_URL . '/pages/uang-pangkal/kwitansi.php?id=' . $p['kwitansi_id'];
                                } elseif ($p['jenis'] === 'lainnya') {
                                    $kwitansiUrl = BASE_URL . '/pages/pembayaran-lain/kwitansi.php?id=' . $p['kwitansi_id'];
                                }
                            }
                            ?>
                            <div class="d-inline-flex flex-column align-items-center gap-1">
                                <button type="button"
                                    class="btn btn-sm btn-light border rounded-pill px-3 py-1 fw-bold text-dark shadow-sm d-inline-flex align-items-center justify-content-center"
                                    style="font-size: 0.78rem;"
                                    onclick="lihatBukti('<?= htmlspecialchars($buktiUrl) ?>', '<?= htmlspecialchars(addslashes($p['nama'])) ?>', '<?= htmlspecialchars(addslashes($jenisTeks)) ?>', '<?= htmlspecialchars(formatRupiah($p['nominal'])) ?>')">
                                    <i class="bi bi-image text-primary me-1"></i> Lihat
                                </button>
                                <div class="d-flex gap-1 justify-content-center">
                                    <a href="<?= htmlspecialchars($buktiUrl) ?>"
                                        download="Bukti_<?= htmlspecialchars($p['nis']) ?>_<?= time() ?>"
                                        class="btn btn-sm btn-light border text-muted px-2 py-0.5 rounded shadow-xs"
                                        title="Download Bukti Transfer" style="font-size: 0.75rem;">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <?php if ($p['status'] === 'pending'): ?>
                                        <a href="<?= $linkWaBendahara ?>" target="_blank"
                                            class="btn btn-sm btn-outline-success px-2 py-0.5 rounded shadow-xs"
                                            title="WhatsApp kirim ke bendahara untuk konfirmasi" style="font-size: 0.75rem;">
                                            <i class="bi bi-whatsapp"></i>
                                        </a>
                                    <?php elseif (!empty($kwitansiUrl)): ?>
                                        <a href="<?= $kwitansiUrl ?>" target="_blank"
                                            class="btn btn-sm btn-outline-primary px-2 py-0.5 rounded shadow-xs"
                                            title="Cetak Kwitansi Pembayaran" style="font-size: 0.75rem;">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                    <?php else: ?>
                                        <button type="button"
                                            class="btn btn-sm btn-light border text-muted px-2 py-0.5 rounded shadow-xs opacity-50"
                                            title="Kwitansi belum tersedia" style="font-size: 0.75rem;" disabled>
                                            <i class="bi bi-printer"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <?php if ($p['status'] === 'pending'): ?>
                                <div class="d-flex gap-1 justify-content-center">
                                    <button class="btn btn-sm btn-success"
                                        onclick="konfirmasiSetuju(<?= $p['id'] ?>, '<?= date('Y-m-d', strtotime($p['created_at'])) ?>')"
                                        title="Setujui"><i class="bi bi-check-lg"></i></button>
                                    <button class="btn btn-sm btn-warning text-dark" onclick="tolakPembayaran(<?= $p['id'] ?>)"
                                        title="Tolak"><i class="bi bi-x-lg"></i></button>
                                    <button class="btn btn-sm btn-outline-danger"
                                        onclick="hapusPembayaran(<?= $p['id'] ?>, 'pending', '<?= htmlspecialchars(addslashes($p['nama'])) ?>', '<?= htmlspecialchars(formatRupiah($p['nominal'])) ?>')"
                                        title="Hapus Data"><i class="bi bi-trash3"></i></button>
                                </div>
                            <?php elseif ($p['status'] === 'disetujui'): ?>
                                <div class="d-flex flex-column gap-1 align-items-center">
                                    <span class="badge bg-success">Disetujui</span>
                                    <div class="d-flex align-items-center gap-1 mt-0.5">
                                        <button class="btn btn-sm btn-outline-danger" style="font-size:.7rem;padding:2px 6px"
                                            onclick="hapusPembayaran(<?= $p['id'] ?>, 'disetujui', '<?= htmlspecialchars(addslashes($p['nama'])) ?>', '<?= htmlspecialchars(formatRupiah($p['nominal'])) ?>')"
                                            title="Hapus Data">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-1 align-items-center">
                                    <span class="badge bg-danger">Ditolak</span>
                                    <?php if (!empty($p['alasan_tolak'])): ?>
                                        <div style="font-size:.7rem;margin-top:2px" class="text-danger">
                                            <?= htmlspecialchars($p['alasan_tolak']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <button
                                        class="btn btn-sm btn-outline-danger rounded-pill px-2 py-0.5 mt-0.5 d-inline-flex align-items-center gap-1"
                                        style="font-size: 0.7rem; background: rgba(220,38,38,0.08);"
                                        onclick="hapusPembayaran(<?= $p['id'] ?>, 'ditolak', '<?= htmlspecialchars(addslashes($p['nama'])) ?>', '<?= htmlspecialchars(formatRupiah($p['nominal'])) ?>')"
                                        title="Hapus Data">
                                        <i class="bi bi-trash3"></i> Hapus
                                    </button>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($pendingList)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">Tidak ada data pembayaran <?= $filterStatus ?>.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Lihat Bukti -->
<div class="modal fade" id="modalBukti" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="modalBuktiTitle"><i class="bi bi-image text-primary me-2"></i>Bukti
                    Transfer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center bg-light p-3 position-relative">
                <img src="" id="imgBukti"
                    style="max-width:100%; max-height:70vh; object-fit:contain; display: block; margin: 0 auto; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08);"
                    onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'300\' height=\'200\' viewBox=\'0 0 300 200\'><rect fill=\'%23f8f9fa\' width=\'300\' height=\'200\'/><text fill=\'%23dc3545\' font-family=\'sans-serif\' font-size=\'14\' dy=\'10.5\' font-weight=\'bold\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\'>Gambar tidak dapat dimuat</text></svg>'">
                <div id="copyFeedback"
                    class="position-absolute top-50 start-50 translate-middle badge bg-dark opacity-90 p-3 shadow"
                    style="display:none; font-size: 1rem; z-index: 10;">
                    <i class="bi bi-check-lg text-success me-1"></i> Gambar Tersalin!
                </div>
            </div>
            <div class="modal-footer justify-content-center border-0 py-3 gap-2 flex-wrap">
                <button type="button" id="btnCopyBukti" class="btn btn-dark px-3 rounded-pill"
                    onclick="copyImageToClipboard()">
                    <i class="bi bi-clipboard me-1"></i> Salin Gambar
                </button>
                <a href="" id="btnDownloadBukti" download="Bukti_Transfer" class="btn btn-primary px-3 rounded-pill">
                    <i class="bi bi-download me-1"></i> Simpan
                </a>
                <a href="" id="btnShareWABukti" target="_blank" class="btn btn-success text-white px-3 rounded-pill">
                    <i class="bi bi-whatsapp me-1"></i> Kirim WhatsApp
                </a>
            </div>
            <div class="text-center pb-3 px-3">
                <small class="text-muted">Klik <strong>Salin Gambar</strong>, lalu <strong>Paste (Ctrl+V)</strong> di
                    WhatsApp untuk mengirim gambarnya langsung.</small>
            </div>
        </div>
    </div>
</div>

<!-- Modal Setujui -->
<div class="modal fade" id="modalSetujui" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="proses.php" class="modal-content">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="id" id="setuju_id">

            <div class="modal-header">
                <h5 class="modal-title fw-bold text-success"><i class="bi bi-check-circle-fill"></i> Setujui Pembayaran
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-4">Konfirmasi data pembayaran sebelum dimasukkan ke laporan keuangan.</p>

                <div class="mb-3">
                    <label class="form-label fw-bold small">Tanggal Bayar (Sesuai Bukti)</label>
                    <input type="date" name="tanggal_bayar" id="setuju_tanggal" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small">Metode Bayar</label>
                    <select name="metode_bayar" class="form-select" required>
                        <option value="transfer" selected>Transfer</option>
                        <option value="tunai">Tunai</option>
                    </select>
                </div>

                <div class="alert alert-warning py-2 mb-0" style="font-size: .8rem;">
                    <i class="bi bi-info-circle"></i> Data akan otomatis dicatat ke Laporan Keuangan setelah disetujui.
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success px-4 fw-bold">Setujui Sekarang</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Tolak -->
<div class="modal fade" id="modalTolak" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="proses.php" class="modal-content">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="id" id="tolak_id">

            <div class="modal-header">
                <h5 class="modal-title fw-bold text-danger">Tolak Pembayaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Alasan Penolakan</label>
                    <textarea name="alasan" class="form-control" rows="3" required
                        placeholder="Misal: Bukti transfer tidak valid/buram..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger px-4">Tolak Pembayaran</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Hapus Verifikasi -->
<div class="modal fade" id="modalHapus" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="proses.php" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="hapus_id">
            <input type="hidden" name="redirect_status" id="hapus_redirect_status"
                value="<?= htmlspecialchars($filterStatus) ?>">

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-trash3 me-2"></i>Hapus Data Verifikasi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <p class="text-muted mb-2">Apakah Anda yakin ingin menghapus data permohonan verifikasi pembayaran ini
                    secara permanen?</p>
                <div class="p-3 rounded-3 bg-light border">
                    <div class="fw-bold text-dark" id="hapus_nama_siswa">-</div>
                    <div class="text-danger fw-extrabold fs-6" id="hapus_nominal_teks">-</div>
                </div>
                <small class="text-muted mt-2 d-block">*Data yang dihapus tidak dapat dipulihkan kembali.</small>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger px-4 fw-bold"><i class="bi bi-trash3 me-1"></i> Ya, Hapus
                    Permanen</button>
            </div>
        </form>
    </div>
</div>

<script>
    function lihatBukti(url, nama = '', jenis = '', nominal = '') {
        if (!url) {
            alert('File bukti transfer tidak ditemukan.');
            return;
        }
        const fullUrl = (url.startsWith('http')) ? url : window.location.origin + url;
        document.getElementById('imgBukti').src = fullUrl;
        document.getElementById('btnDownloadBukti').href = fullUrl;

        let waMsg = 'Berikut adalah bukti transfer pembayaran';
        if (nama) waMsg += ' an. ' + nama;
        if (jenis) waMsg += ' untuk ' + jenis;
        if (nominal) waMsg += ' sebesar ' + nominal;
        waMsg += ':\n' + fullUrl;

        document.getElementById('btnShareWABukti').href = 'https://wa.me/?text=' + encodeURIComponent(waMsg);
        if (nama) {
            document.getElementById('modalBuktiTitle').innerHTML = '<i class="bi bi-image text-primary me-2"></i>Bukti Transfer - ' + nama;
        }
        new bootstrap.Modal(document.getElementById('modalBukti')).show();
    }

    async function copyImageToClipboard() {
        const imgElement = document.getElementById('imgBukti');
        const src = imgElement.src;

        try {
            const response = await fetch(src);
            const blob = await response.blob();

            if (blob.type !== 'image/png') {
                const img = new Image();
                img.crossOrigin = 'Anonymous';
                img.src = src;
                img.onload = function () {
                    const canvas = document.createElement('canvas');
                    canvas.width = img.width;
                    canvas.height = img.height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0);
                    canvas.toBlob(async (pngBlob) => {
                        await navigator.clipboard.write([
                            new ClipboardItem({ 'image/png': pngBlob })
                        ]);
                        showCopyFeedback();
                    }, 'image/png');
                };
            } else {
                await navigator.clipboard.write([
                    new ClipboardItem({ 'image/png': blob })
                ]);
                showCopyFeedback();
            }
        } catch (err) {
            console.error('Gagal menyalin gambar: ', err);
            alert('Gagal menyalin gambar secara otomatis. Klik kanan pada gambar lalu pilih "Copy Image".');
        }
    }

    function showCopyFeedback() {
        const feedback = document.getElementById('copyFeedback');
        feedback.style.display = 'block';
        setTimeout(() => {
            feedback.style.display = 'none';
        }, 2000);
    }

    function konfirmasiSetuju(id, tgl) {
        document.getElementById('setuju_id').value = id;
        document.getElementById('setuju_tanggal').value = tgl;
        new bootstrap.Modal(document.getElementById('modalSetujui')).show();
    }

    function tolakPembayaran(id) {
        document.getElementById('tolak_id').value = id;
        new bootstrap.Modal(document.getElementById('modalTolak')).show();
    }

    function hapusPembayaran(id, status, nama, nominal) {
        document.getElementById('hapus_id').value = id;
        document.getElementById('hapus_redirect_status').value = status || 'pending';
        document.getElementById('hapus_nama_siswa').innerText = nama || 'Siswa';
        document.getElementById('hapus_nominal_teks').innerText = nominal || '';
        new bootstrap.Modal(document.getElementById('modalHapus')).show();
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>