<?php
/**
 * SUB-NAVIGASI PERANGKAT AJAR KURIKULUM MERDEKA
 * Desain Modern Segmented Controller
 */
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$docId = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_GET['cp_id']) ? (int) $_GET['cp_id'] : 0);

$navItems = [
    'index.php'  => ['icon' => 'bi-speedometer2', 'label' => 'Dashboard'],
    'create.php' => ['icon' => 'bi-plus-circle-fill', 'label' => '✨ Buat Perangkat Ajar Baru (1 Pintu)'],
];

if ($docId > 0) {
    $navItems['view.php?id=' . $docId] = ['icon' => 'bi-file-earmark-check-fill', 'label' => '📄 Dokumen & Unduh PDF'];
    $navItems['edit.php?id=' . $docId] = ['icon' => 'bi-pencil-square', 'label' => '✏️ Edit Dokumen'];
}
?>

<style>
    .perangkat-nav-wrapper {
        background: #ffffff;
        border: 1px solid #f1f5f9;
        border-radius: 20px;
        box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.04);
        padding: 8px 12px;
        margin-bottom: 24px;
    }

    .perangkat-nav-container {
        display: inline-flex;
        align-items: center;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 4px;
        gap: 4px;
        overflow-x: auto;
        max-width: 100%;
        scrollbar-width: none;
    }

    .perangkat-nav-container::-webkit-scrollbar {
        display: none;
    }

    .perangkat-nav-link {
        padding: 8px 18px;
        border-radius: 10px;
        font-size: 0.84rem;
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
        background: rgba(255, 255, 255, 0.6);
    }

    .perangkat-nav-link.active {
        background: #ffffff;
        color: #0f172a;
        box-shadow: 0 2px 8px -1px rgba(15, 23, 42, 0.08), 0 1px 2px 0 rgba(15, 23, 42, 0.04);
    }
</style>

<div class="perangkat-nav-wrapper d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div class="perangkat-nav-container">
        <?php foreach ($navItems as $url => $item): 
            $isActive = ($currentPage === explode('?', $url)[0]);
        ?>
            <a href="<?= htmlspecialchars($url) ?>" class="perangkat-nav-link <?= $isActive ? 'active' : '' ?>">
                <i class="bi <?= $item['icon'] ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="d-none d-md-flex align-items-center gap-2">
        <span class="badge bg-light text-muted border px-3 py-1.5 rounded-pill extra-small fw-semibold">
            <i class="bi bi-shield-check text-success me-1"></i> Kurikulum Merdeka (Fase D: Kelas VII, VIII, IX)
        </span>
    </div>
</div>
