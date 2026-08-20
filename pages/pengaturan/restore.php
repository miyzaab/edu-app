<?php
/**
 * RESTORE DATABASE - Import data dari file .sql
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('pengaturan');

if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'bendahara') {
    die("Akses ditolak.");
}

$pdo = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file'])) {
    $file = $_FILES['backup_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (strtolower($ext) !== 'sql') {
            redirect('index.php', 'danger', 'Hanya file ber-ekstensi .sql yang diperbolehkan.');
        }

        try {
            $sqlContent = file_get_contents($file['tmp_name']);
            if (empty(trim($sqlContent))) {
                redirect('index.php', 'warning', 'File SQL yang diupload kosong.');
            }

            // Set timeout & memory limit untuk file besar
            @set_time_limit(300);
            @ini_set('memory_limit', '256M');

            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            $pdo->exec("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");

            // Standardize line breaks
            $sqlContent = str_replace(["\r\n", "\r"], "\n", $sqlContent);
            $lines = explode("\n", $sqlContent);

            $query = '';
            $executedCount = 0;
            $errorCount = 0;

            foreach ($lines as $line) {
                $trimmed = trim($line);

                // Skip lines that are comments or empty
                if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#') || (str_starts_with($trimmed, '/*') && str_ends_with($trimmed, '*/'))) {
                    continue;
                }

                $query .= $line . "\n";

                // Execute when query ends with semicolon
                if (str_ends_with($trimmed, ';')) {
                    try {
                        $pdo->exec($query);
                        $executedCount++;
                    } catch (PDOException $e) {
                        $errorCount++;
                    }
                    $query = '';
                }
            }

            // Execute any remaining query
            if (!empty(trim($query))) {
                try {
                    $pdo->exec($query);
                    $executedCount++;
                } catch (PDOException $e) {
                    $errorCount++;
                }
            }

            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

            // Update session permissions after restore just in case
            if (isset($_SESSION['user_id'])) {
                require_once __DIR__ . '/../../config/auth_functions.php';
                $_SESSION['permissions'] = loadUserPermissions((int) $_SESSION['user_id']);
            }

            redirect('index.php', 'success', "🎉 Database berhasil direstore dari <b>" . htmlspecialchars($file['name']) . "</b>! Total $executedCount perintah SQL dijalankan.");
        } catch (Exception $e) {
            redirect('index.php', 'danger', 'Gagal merestore database: ' . $e->getMessage());
        }
    } else {
        redirect('index.php', 'danger', 'Gagal mengupload file backup (Error Code: ' . $file['error'] . ').');
    }
} else {
    redirect('index.php', 'warning', 'Permintaan restore tidak valid.');
}

