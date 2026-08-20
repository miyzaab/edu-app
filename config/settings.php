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

function getLogoHtml(int $size = 48, string $class = ''): string
{
    $logoPath = getSetting('logo_path', '');
    $finalPath = '';

    if ($logoPath) {
        // Jika path lengkap (URL)
        if (strpos($logoPath, 'http') === 0) {
            $finalPath = $logoPath;
        } else {
            // Cek fisik file (hilangkan BASE_URL jika ada di awal path setting)
            $cleanPath = $logoPath;
            if (defined('BASE_URL') && BASE_URL !== '' && strpos($logoPath, BASE_URL) === 0) {
                $cleanPath = substr($logoPath, strlen(BASE_URL));
            }
            
            $fullPath = $_SERVER['DOCUMENT_ROOT'] . $cleanPath;
            if (file_exists($fullPath)) {
                $finalPath = $logoPath;
            }
        }
    }

    if ($finalPath) {
        return '<img src="' . htmlspecialchars($finalPath) . '" alt="Logo" class="' . $class . '" style="width:' . $size . 'px;height:' . $size . 'px;object-fit:contain;">';
    }
    
    // Fallback Emoji dengan styling yang lebih bagus
    return '<div class="logo-fallback" style="width:' . $size . 'px;height:' . $size . 'px;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg, #6366f1, #06b6d4);border-radius:20%;color:#fff;font-size:' . ($size * 0.55) . 'px;box-shadow:0 4px 12px rgba(99,102,241,0.3);">🏫</div>';
}

/**
 * Helper: Generate CSS variables berdasarkan warna tema yang di-set
 */
function getDynamicThemeCss(): string
{
    $primary = getSetting('theme_color', '#0dcaf0');
    $loginBg = getSetting('login_bg_color', '#0f172a');
    
    return "
    <style>
    :root {
        --primary: {$primary};
        --primary-dark: {$primary}; 
        --gradient-primary: linear-gradient(135deg, {$primary} 0%, #0d6efd 100%);
        --dark: {$loginBg};
    }
    .sidebar-nav a.active {
        background: var(--gradient-primary) !important;
    }
    .btn-primary-custom, .stat-icon, .step-dot.active, .btn-masuk {
        background: {$primary} !important;
    }
    .input-wrapper input:focus {
        border-color: {$primary} !important;
    }
    /* Login Page Specific */
    .login-left {
        background: {$loginBg} !important;
    }
    /* Sidebar Brand Section */
    .sidebar-brand {
        background: rgba(255,255,255, 0.03) !important;
        border-bottom: 1px solid rgba(255,255,255,0.05) !important;
    }
    .sidebar-brand .brand-text h2 {
        color: #ffffff !important;
    }
    .sidebar-brand .brand-text small {
        color: rgba(255,255,255, 0.4) !important;
    }
    .sidebar-brand .brand-icon {
        background: #ffffff !important;
        border-radius: 12px !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2) !important;
    }
    .pill-icon {
        background: linear-gradient(135deg, {$primary}, #06b6d4) !important;
    }
    </style>";
}
