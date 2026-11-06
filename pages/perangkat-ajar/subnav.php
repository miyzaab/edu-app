<?php
/**
 * SUB-NAVIGASI PERANGKAT AJAR KURIKULUM MERDEKA
 * Desain Modern Segmented Controller (Seperti Modul Kantin)
 */
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$docId = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_GET['cp_id']) ? (int) $_GET['cp_id'] : 0);

$navItems = [
    'index.php' => ['icon' => 'bi-speedometer2', 'label' => 'Dashboard'],
    'identitas.php' => ['icon' => 'bi-person-badge-fill', 'label' => 'Identitas Modul'],
    'cp.php' => ['icon' => 'bi-journal-text', 'label' => '1. CP (Capaian)'],
    'tp.php' => ['icon' => 'bi-bullseye', 'label' => '2. TP (Tujuan)'],
    'atp.php' => ['icon' => 'bi-diagram-3-fill', 'label' => '3. ATP (Alur)'],
    'modul.php' => ['icon' => 'bi-box-seam-fill', 'label' => '4. Modul Ajar'],
];
?>

<style>
    /* SEGMENTED NAVIGATION CONTROLLER (STYLE SEPERTI MODUL KANTIN) */
    .perangkat-nav-wrapper {
        background: #ffffff;
        border: 1px solid #f1f5f9;
        border-radius: 20px;
        box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.04);
        padding: 8px 12px;
    }

    .perangkat-nav-container {
        display: inline-flex;
        align-items: center;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 4px;
        gap: 3px;
        overflow-x: auto;
        max-width: 100%;
        scrollbar-width: none;
        /* Firefox */
    }

    .perangkat-nav-container::-webkit-scrollbar {
        display: none;
        /* Chrome, Safari */
    }

    .perangkat-nav-link {
        padding: 8px 16px;
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 700;
        color: #64748b;
        text-decoration: none !important;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        display: inline-flex;
        align-items: center;
        gap: 7px;
        white-space: nowrap;
    }

    .perangkat-nav-link:hover {
        color: #0f172a;
        background: rgba(255, 255, 255, 0.85);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
    }

    .perangkat-nav-link.active {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: #ffffff !important;
        font-weight: 800;
        box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
    }

    .perangkat-nav-link.active i {
        color: #ffffff !important;
    }

    .perangkat-nav-link i {
        font-size: 0.95rem;
        color: #2563eb;
        transition: all 0.2s ease;
    }

    .btn-quick-pdf {
        background: #fef2f2;
        color: #dc2626 !important;
        border: 1px solid #fecaca;
        font-weight: 700;
        font-size: 0.8rem;
        padding: 7px 16px;
        border-radius: 50px;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-quick-pdf:hover {
        background: #dc2626;
        color: #ffffff !important;
        border-color: #dc2626;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
    }
</style>

<div class="perangkat-nav-wrapper mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <!-- SEGMENTED NAVIGATION CONTROLLER -->
        <div class="perangkat-nav-container">
            <?php foreach ($navItems as $url => $item):
                $isActive = ($currentPage === $url);
                $targetUrl = $url;
                if ($docId > 0 && in_array($url, ['tp.php', 'atp.php', 'modul.php', 'edit.php'])) {
                    $paramName = ($url === 'tp.php' || $url === 'atp.php' || $url === 'modul.php') ? 'cp_id' : 'id';
                    $targetUrl .= '?' . $paramName . '=' . $docId;
                }
                ?>
                <a href="<?= $targetUrl ?>" class="perangkat-nav-link <?= $isActive ? 'active' : '' ?>">
                    <i class="bi <?= $item['icon'] ?>"></i>
                    <span><?= $item['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- QUICK ACTION JIKA ADA DOKUMEN AKTIF -->
        <?php if ($docId > 0): ?>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <a href="print.php?doc_type=all&id=<?= $docId ?>" target="_blank" class="btn-quick-pdf"
                    title="Cetak PDF Paket Lengkap (CP+TP+ATP+Modul)">
                    <i class="bi bi-file-earmark-pdf-fill"></i> Cetak Paket Lengkap
                </a>
                <a href="edit.php?id=<?= $docId ?>" class="btn btn-sm btn-outline-secondary rounded-circle"
                    style="width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center;"
                    title="Edit Dokumen Ini">
                    <i class="bi bi-pencil-fill fs-7"></i>
                </a>
                <a href="delete.php?id=<?= $docId ?>" class="btn btn-sm btn-outline-danger rounded-circle"
                    onclick="return confirm('Apakah Anda yakin ingin menghapus dokumen Perangkat Ajar ini secara permanen? Seluruh data CP, TP, ATP, dan Modul Ajar terkait akan terhapus.');"
                    style="width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center;"
                    title="Hapus Dokumen Ini">
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