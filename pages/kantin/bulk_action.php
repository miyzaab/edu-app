<?php
/**
 * BULK ACTION KANTIN - Hapus Menu Massal, Ubah Status Stok (Tersedia/Habis), Ubah Kategori
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('kantin');

$pdo = getConnection();
$action = $_POST['bulk_action_type'] ?? '';
$ids = $_POST['ids'] ?? [];

// Sanitize IDs
$ids = array_filter(array_map('intval', (array) $ids));

if (empty($ids)) {
    redirect('menu.php', 'warning', 'Tidak ada item menu yang dipilih.');
}

$count = count($ids);
$placeholders = implode(',', array_fill(0, $count, '?'));

try {
    $pdo->beginTransaction();

    if ($action === 'delete') {
        // Hapus foto jika ada
        $stmtFoto = $pdo->prepare("SELECT foto FROM kantin_menu WHERE id IN ($placeholders) AND foto IS NOT NULL");
        $stmtFoto->execute($ids);
        $fotos = $stmtFoto->fetchAll(PDO::FETCH_COLUMN);
        foreach ($fotos as $f) {
            $fPath = __DIR__ . '/../../uploads/kantin/' . $f;
            if (file_exists($fPath))
                @unlink($fPath);
        }

        // Hapus detail transaksi yang terkait dengan menu ini (atau set null jika schema mengizinkan)
        $pdo->prepare("DELETE FROM kantin_transaksi_detail WHERE menu_id IN ($placeholders)")->execute($ids);

        // Hapus menu
        $stmt = $pdo->prepare("DELETE FROM kantin_menu WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        $pdo->commit();
        redirect('menu.php', 'success', "🎉 Berhasil menghapus <b>$count</b> item menu secara massal.");
    } elseif ($action === 'status_tersedia') {
        $stmt = $pdo->prepare("UPDATE kantin_menu SET status = 'tersedia' WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        $pdo->commit();
        redirect('menu.php', 'success', "🎉 Status <b>$count</b> menu berhasil diubah menjadi <b>TERSEDIA</b>.");
    } elseif ($action === 'status_habis') {
        $stmt = $pdo->prepare("UPDATE kantin_menu SET status = 'habis' WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        $pdo->commit();
        redirect('menu.php', 'success', "🎉 Status <b>$count</b> menu berhasil diubah menjadi <b>HABIS</b>.");
    } elseif ($action === 'edit_kategori') {
        $kategori = $_POST['target_kategori'] ?? 'makanan';
        if (!in_array($kategori, ['makanan', 'minuman', 'snack'])) {
            redirect('menu.php', 'danger', 'Kategori tidak valid.');
        }

        $params = array_merge([$kategori], $ids);
        $stmt = $pdo->prepare("UPDATE kantin_menu SET kategori = ? WHERE id IN ($placeholders)");
        $stmt->execute($params);

        $pdo->commit();
        redirect('menu.php', 'success', "🎉 Kategori <b>$count</b> menu berhasil diubah menjadi <b>" . ucfirst($kategori) . "</b>.");
    } else {
        $pdo->rollBack();
        redirect('menu.php', 'warning', 'Aksi massal tidak dikenali.');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirect('menu.php', 'danger', 'Terjadi kesalahan: ' . $e->getMessage());
}
