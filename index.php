<?php
/**
 * HALAMAN LOGIN - Edu-App
 */
require_once __DIR__ . '/config/koneksi.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

$namaSekolah = getSetting('nama_sekolah', SCHOOL_NAME);
$appName = getSetting('app_name', APP_NAME);
$logoPath = '';
try {
    $logoPath = getSetting('logo_path', '');
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= htmlspecialchars($appName) ?> | <?= htmlspecialchars($namaSekolah) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.08) 0%, transparent 45%), radial-gradient(circle at 90% 80%, rgba(6, 182, 212, 0.08) 0%, transparent 45%), #f4f6fc;
            padding: 20px;
            overflow-x: hidden;
            position: relative;
        }

        /* Background blur circles */
        .bg-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            z-index: 0;
            pointer-events: none;
        }

        .bg-orb-1 {
            width: 400px;
            height: 400px;
            background: rgba(99, 102, 241, 0.12);
            top: 10%;
            left: 15%;
        }

        .bg-orb-2 {
            width: 300px;
            height: 300px;
            background: rgba(6, 182, 212, 0.1);
            bottom: 10%;
            right: 15%;
        }

        /* ===== LOGIN CARD (GLASSMORPHISM) ===== */
        .login-container {
            width: 100%;
            max-width: 440px;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(24px) saturate(200%);
            -webkit-backdrop-filter: blur(24px) saturate(200%);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 28px;
            padding: 2.75rem 2.25rem;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.8);
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .school-logo {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(0, 0, 0, 0.06);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
            box-shadow: 0 8px 32px rgba(15, 23, 42, 0.05);
            padding: 12px;
            transition: transform 0.3s ease;
        }

        .school-logo:hover {
            transform: scale(1.05) rotate(-2deg);
        }

        .school-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .school-logo .emoji {
            font-size: 2.5rem;
        }

        .login-header h1 {
            font-size: 1.6rem;
            font-weight: 800;
            color: #1e1b4b;
            margin-bottom: 0.4rem;
        }

        .login-header p {
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 400;
            line-height: 1.4;
            max-width: 280px;
        }

        .login-form {
            width: 100%;
        }

        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 0.8rem 1rem;
            color: #dc2626;
            font-size: 0.8rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.05);
        }

        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.45rem;
        }

        .input-wrapper {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .input-wrapper>i:first-child {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1rem;
            pointer-events: none;
            z-index: 5;
        }

        .input-wrapper input {
            width: 100%;
            padding: 0.8rem 1rem 0.8rem 2.85rem;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.875rem;
            font-family: 'Inter', sans-serif;
            color: #0f172a;
            background: rgba(255, 255, 255, 0.8);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            outline: none;
        }

        .input-wrapper input:focus {
            border-color: #6366f1;
            background: #ffffff;
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.15);
        }

        .input-wrapper input::placeholder {
            color: #cbd5e1;
        }

        .btn-masuk {
            width: 100%;
            padding: 0.85rem;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
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
            box-shadow: 0 8px 24px -4px rgba(99, 102, 241, 0.35);
        }

        .btn-masuk:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px -4px rgba(99, 102, 241, 0.5);
        }

        .btn-masuk:active {
            transform: translateY(0);
        }

        .divider {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 1.75rem 0 1.25rem;
            width: 100%;
        }

        .login-footer {
            margin-top: 2rem;
            color: #94a3b8;
            font-size: 0.72rem;
            text-align: center;
            width: 100%;
        }

        .btn-toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            z-index: 10;
        }

        .btn-toggle-password:hover {
            color: #6366f1;
        }
    </style>
    <?= getDynamicThemeCss() ?>
</head>

<body>

    <!-- Background glowing orbs -->
    <div class="bg-orb bg-orb-1"></div>
    <div class="bg-orb bg-orb-2"></div>

    <!-- CENTERED LOGIN CARD -->
    <div class="login-container">
        <div class="login-header">
            <div class="school-logo">
                <?= getLogoHtml(60) ?>
            </div>
            <h1><?= htmlspecialchars($appName) ?></h1>
            <p><?= htmlspecialchars($namaSekolah) ?></p>
        </div>

        <?php if ($error): ?>
            <div class="error-box">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form class="login-form" action="<?= BASE_URL ?>/login_process.php" method="POST" autocomplete="off">
            <label class="form-label" for="username">Username</label>
            <div class="input-wrapper">
                <i class="bi bi-person"></i>
                <input type="text" id="username" name="username" placeholder="Masukkan username" required autofocus>
            </div>

            <label class="form-label" for="password">Password</label>
            <div class="input-wrapper">
                <i class="bi bi-lock"></i>
                <input type="password" id="password" name="password" placeholder="Masukkan password" required
                    style="padding-right: 3rem;">
                <button type="button" id="togglePassword" class="btn-toggle-password">
                    <i class="bi bi-eye"></i>
                </button>
            </div>

            <button type="submit" class="btn-masuk" id="btnLogin">
                <i class="bi bi-box-arrow-in-right"></i> Masuk ke Dashboard
            </button>
        </form>



        <div class="login-footer">
            <p>&copy; 2026 Developed by miyzaab.com | Zia Abdurrofi</p>
        </div>
    </div>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function (e) {
            // toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);

            // toggle the eye icon
            const icon = this.querySelector('i');
            icon.classList.toggle('bi-eye');
            icon.classList.toggle('bi-eye-slash');
        });
    </script>
</body>

</html>