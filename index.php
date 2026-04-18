<?php
/**
 * HALAMAN LOGIN - E-Pembayaran
 */
session_start();
require_once __DIR__ . '/config/koneksi.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

$namaSekolah = SCHOOL_NAME;
$logoPath = '';
try { $logoPath = getSetting('logo_path', ''); } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= htmlspecialchars(APP_NAME) ?> | <?= htmlspecialchars($namaSekolah) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            background: #0f0c29;
        }

        /* ===== LEFT PANEL ===== */
        .login-left {
            flex: 1;
            background: linear-gradient(145deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            width: 500px; height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(99,102,241,0.3) 0%, transparent 70%);
            top: -100px; right: -100px;
        }

        .login-left::after {
            content: '';
            position: absolute;
            width: 350px; height: 350px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(6,182,212,0.25) 0%, transparent 70%);
            bottom: -80px; left: -80px;
        }

        .left-content {
            position: relative;
            z-index: 1;
            text-align: center;
            color: #fff;
        }

        .school-logo {
            width: 90px; height: 90px;
            background: linear-gradient(135deg, #6366f1 0%, #06b6d4 100%);
            border-radius: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            box-shadow: 0 20px 60px rgba(99,102,241,0.5);
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 20px 60px rgba(99,102,241,0.5); }
            50% { box-shadow: 0 20px 80px rgba(99,102,241,0.8); }
        }

        .school-logo img { width: 60px; height: 60px; object-fit: contain; }
        .school-logo .emoji { font-size: 2.5rem; }

        .left-content h1 {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, #c7d2fe 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .left-content p {
            color: rgba(255,255,255,0.5);
            font-size: 0.875rem;
            font-weight: 400;
            max-width: 280px;
            line-height: 1.6;
        }

        .feature-pills {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-top: 2.5rem;
            align-items: flex-start;
        }

        .pill {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 50px;
            padding: 0.5rem 1rem 0.5rem 0.5rem;
            backdrop-filter: blur(10px);
        }

        .pill-icon {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #06b6d4);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem;
            color: #fff;
            flex-shrink: 0;
        }

        .pill span { color: rgba(255,255,255,0.75); font-size: 0.8rem; font-weight: 500; }

        /* ===== RIGHT PANEL ===== */
        .login-right {
            width: 460px;
            background: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 2.5rem;
            position: relative;
        }

        .right-content { width: 100%; max-width: 360px; }

        .right-content .greeting {
            font-size: 1.6rem;
            font-weight: 800;
            color: #1e1b4b;
            margin-bottom: 0.35rem;
        }

        .right-content .sub-greeting {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 2rem;
        }

        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            color: #dc2626;
            font-size: 0.8rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.4rem;
        }

        .input-wrapper {
            position: relative;
            margin-bottom: 1.25rem;
        }

        .input-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 1rem;
        }

        .input-wrapper input {
            width: 100%;
            padding: 0.8rem 1rem 0.8rem 2.75rem;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.875rem;
            font-family: 'Inter', sans-serif;
            color: #1f2937;
            background: #f9fafb;
            transition: all 0.2s;
            outline: none;
        }

        .input-wrapper input:focus {
            border-color: #6366f1;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
        }

        .input-wrapper input::placeholder { color: #d1d5db; }

        .btn-masuk {
            width: 100%;
            padding: 0.85rem;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.25s;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-masuk:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99,102,241,0.45);
        }

        .btn-masuk:active { transform: translateY(0); }

        .divider {
            border: none;
            border-top: 1px solid #f3f4f6;
            margin: 1.75rem 0 1rem;
        }

        .portal-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.2s;
        }

        .portal-link:hover {
            background: #dcfce7;
            transform: translateX(4px);
        }

        .portal-link .pl-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: #fff;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .portal-link .pl-text strong { display: block; font-size: 0.8rem; font-weight: 700; color: #065f46; }
        .portal-link .pl-text span { font-size: 0.72rem; color: #6b7280; }
        .portal-link .pl-arrow { margin-left: auto; color: #10b981; }

        .login-footer-right {
            position: absolute;
            bottom: 1.5rem;
            color: #d1d5db;
            font-size: 0.72rem;
            text-align: center;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .login-left { display: none; }
            .login-right { width: 100%; }
        }
    </style>
</head>
<body>

<!-- LEFT PANEL -->
<div class="login-left">
    <div class="left-content">
        <div class="school-logo">
            <?php if ($logoPath): ?>
                <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo">
            <?php else: ?>
                <span class="emoji">🏫</span>
            <?php endif; ?>
        </div>
        <h1><?= htmlspecialchars(APP_NAME) ?></h1>
        <p><?= htmlspecialchars($namaSekolah) ?></p>

        <div class="feature-pills">
            <div class="pill">
                <div class="pill-icon"><i class="bi bi-shield-check"></i></div>
                <span>Verifikasi Pembayaran Online</span>
            </div>
            <div class="pill">
                <div class="pill-icon"><i class="bi bi-grid-3x3-gap"></i></div>
                <span>Rekap SPP Otomatis per Kelas</span>
            </div>
            <div class="pill">
                <div class="pill-icon"><i class="bi bi-wallet2"></i></div>
                <span>Laporan Buku Kas (Petty Cash)</span>
            </div>
            <div class="pill">
                <div class="pill-icon"><i class="bi bi-file-earmark-pdf"></i></div>
                <span>Cetak PDF & Ekspor Excel</span>
            </div>
        </div>
    </div>
</div>

<!-- RIGHT PANEL -->
<div class="login-right">
    <div class="right-content">
        <p class="greeting">Selamat Datang 👋</p>
        <p class="sub-greeting">Masuk ke dasbor bendahara Anda</p>

        <?php if ($error): ?>
            <div class="error-box">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/login_process.php" method="POST" autocomplete="off">
            <label class="form-label" for="username">Username</label>
            <div class="input-wrapper">
                <i class="bi bi-person"></i>
                <input type="text" id="username" name="username" placeholder="Masukkan username" required autofocus>
            </div>

            <label class="form-label" for="password">Password</label>
            <div class="input-wrapper">
                <i class="bi bi-lock"></i>
                <input type="password" id="password" name="password" placeholder="Masukkan password" required>
            </div>

            <button type="submit" class="btn-masuk" id="btnLogin">
                <i class="bi bi-box-arrow-in-right"></i> Masuk ke Dashboard
            </button>
        </form>

        <hr class="divider">

        <a href="<?= BASE_URL ?>/portal-ortu.php" class="portal-link">
            <div class="pl-icon"><i class="bi bi-people-fill"></i></div>
            <div class="pl-text">
                <strong>Portal Orang Tua / Wali Murid</strong>
                <span>Konfirmasi pembayaran tanpa login</span>
            </div>
            <i class="bi bi-chevron-right pl-arrow"></i>
        </a>
    </div>

    <p class="login-footer-right">&copy; <?= date('Y') ?> <?= htmlspecialchars($namaSekolah) ?></p>
</div>

</body>
</html>