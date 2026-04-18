<?php
/**
 * Helper: Ambil setting dari database
 */
function getSetting(string $key, string $default = ''): string
{
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];
    
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1");
        $stmt->execute([':key' => $key]);
        $val = $stmt->fetchColumn();
        $cache[$key] = ($val !== false) ? $val : $default;
    } catch (PDOException $e) {
        $cache[$key] = $default;
    }
    return $cache[$key];
}

/**
 * Helper: Update setting
 */
function updateSetting(string $key, string $value): bool
{
    $pdo = getConnection();
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :val) ON DUPLICATE KEY UPDATE setting_value = :val2");
    return $stmt->execute([':key' => $key, ':val' => $value, ':val2' => $value]);
}

/**
 * Helper: Mendapatkan path logo untuk ditampilkan di HTML
 * Mengembalikan tag <img> jika logo ada, atau emoji default
 */
function getLogoHtml(int $size = 48): string
{
    $logoPath = getSetting('logo_path', '');
    if ($logoPath && file_exists($_SERVER['DOCUMENT_ROOT'] . $logoPath)) {
        return '<img src="' . htmlspecialchars($logoPath) . '" alt="Logo" style="width:' . $size . 'px;height:' . $size . 'px;object-fit:contain;">';
    }
    return '<span style="font-size:' . ($size * 0.6) . 'px">🏫</span>';
}
