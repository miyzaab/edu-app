<?php
/**
 * SUB-NAVIGASI PERANGKAT GURU - BANK SOAL & ASESMEN
 */
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$soalId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$userRole = strtolower($_SESSION['role'] ?? 'guru');

$navItems = [
    'index.php' => ['icon' => 'bi-collection-fill', 'label' => 'Bank Soal & Naskah Ujian'],
    'create.php' => ['icon' => 'bi-plus-circle-fill', 'label' => 'Buat Naskah Soal Baru'],
    'kop.php' => ['icon' => 'bi-image', 'label' => 'Upload KOP Soal (PNG)'],
];

if ($soalId > 0) {
    $navItems['edit.php?id=' . $soalId] = ['icon' => 'bi-pencil-square', 'label' => '✏️ Susun Butir Soal'];
    $navItems['print.php?id=' . $soalId] = ['icon' => 'bi-printer-fill', 'label' => '🖨️ Cetak / PDF'];
    $navItems['export_word.php?id=' . $soalId] = ['icon' => 'bi-file-earmark-word-fill', 'label' => '📥 Unduh Word (.doc)'];
    $navItems['print_kunci.php?id=' . $soalId] = ['icon' => 'bi-key-fill', 'label' => '🔑 Kunci & Penskoran'];
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
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 10px;
        font-size: 0.86rem;
        font-weight: 600;
        color: #475569;
        text-decoration: none;
        transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        white-space: nowrap;
    }

    .perangkat-nav-link:hover {
        color: #1e293b;
        background: rgba(255, 255, 255, 0.6);
    }

    .perangkat-nav-link.active {
        color: #ffffff;
        background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
    }
</style>

<div class="perangkat-nav-wrapper">
    <div class="perangkat-nav-container">
        <?php foreach ($navItems as $url => $item):
            $isActive = ($currentPage === explode('?', $url)[0]);
            if (strpos($url, '?') !== false && $currentPage === explode('?', $url)[0]) {
                $isActive = (isset($_GET['id']) && (int) $_GET['id'] === $soalId);
            }
            ?>
            <a href="<?= $url ?>" class="perangkat-nav-link <?= $isActive ? 'active' : '' ?>">
                <i class="bi <?= $item['icon'] ?>"></i>
                <span><?= $item['label'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
