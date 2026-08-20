<?php
/**
 * SUB-NAVIGASI PERANGKAT AJAR KURIKULUM MERDEKA
 * Dipanggil di bagian atas halaman-halaman Perangkat Ajar
 */
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$docId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['cp_id']) ? (int)$_GET['cp_id'] : 0);

$navItems = [
    'index.php'     => ['icon' => 'bi-speedometer2', 'label' => 'Dashboard'],
    'identitas.php' => ['icon' => 'bi-person-badge', 'label' => 'Identitas Modul'],
    'cp.php'        => ['icon' => 'bi-journal-text', 'label' => '1. CP (Capaian)'],
    'tp.php'        => ['icon' => 'bi-bullseye', 'label' => '2. TP (Tujuan)'],
    'atp.php'       => ['icon' => 'bi-diagram-3-fill', 'label' => '3. ATP (Alur)'],
    'modul.php'     => ['icon' => 'bi-box-seam-fill', 'label' => '4. Modul Ajar'],
];
?>

<div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
    <div class="card-body p-2 p-md-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <!-- MENU TABS PERANGKAT AJAR -->
        <div class="d-flex flex-wrap align-items-center gap-1.5 overflow-auto pb-1 pb-md-0" style="max-width: 100%;">
            <?php foreach ($navItems as $url => $item): 
                $isActive = ($currentPage === $url);
                $targetUrl = $url;
                if ($docId > 0 && in_array($url, ['tp.php', 'atp.php', 'modul.php', 'edit.php'])) {
                    $paramName = ($url === 'tp.php' || $url === 'atp.php' || $url === 'modul.php') ? 'cp_id' : 'id';
                    $targetUrl .= '?' . $paramName . '=' . $docId;
                }
            ?>
                <a href="<?= $targetUrl ?>" class="btn btn-sm <?= $isActive ? 'btn-primary fw-bold shadow-sm' : 'btn-light text-secondary fw-semibold' ?> px-3 py-2 rounded-pill text-nowrap d-flex align-items-center gap-1.5 transition-all">
                    <i class="bi <?= $item['icon'] ?> <?= $isActive ? 'text-white' : 'text-primary' ?>"></i>
                    <span><?= $item['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- QUICK ACTION JIKA ADA DOKUMEN AKTIF -->
        <?php if ($docId > 0): ?>
            <div class="d-flex align-items-center gap-1.5 ms-auto">
                <a href="print.php?doc_type=all&id=<?= $docId ?>" target="_blank" class="btn btn-sm btn-outline-danger fw-bold rounded-pill px-3" title="Cetak PDF Paket Lengkap (CP+TP+ATP+Modul)">
                    <i class="bi bi-file-earmark-pdf-fill me-1"></i> Cetak Paket Lengkap
                </a>
                <a href="edit.php?id=<?= $docId ?>" class="btn btn-sm btn-outline-warning text-dark fw-bold rounded-circle p-2" style="width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center;" title="Edit Dokumen Ini">
                    <i class="bi bi-pencil-fill fs-7"></i>
                </a>
                <a href="delete.php?id=<?= $docId ?>" class="btn btn-sm btn-outline-danger rounded-circle p-2" onclick="return confirm('Apakah Anda yakin ingin menghapus dokumen Perangkat Ajar ini secara permanen? Seluruh data CP, TP, ATP, dan Modul Ajar terkait akan terhapus.');" style="width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center;" title="Hapus Dokumen Ini">
                    <i class="bi bi-trash-fill fs-7"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function confirmDeletePerangkat(id) {
    if (confirm('Apakah Anda yakin ingin menghapus dokumen Perangkat Ajar ini secara permanen? Seluruh data CP, TP, ATP, dan Modul Ajar terkait akan terhapus.')) {
        window.location.href = 'delete.php?id=' + id;
    }
}
</script>
