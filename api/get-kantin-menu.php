<?php
/**
 * API Endpoint: Get Kantin Menu Products
 * Mendukung pencatatan client-side array & fallback server-side search
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';
requirePermission('kantin');

try {
    $pdo = getConnection();
    $search = trim($_GET['q'] ?? '');

    if (!empty($search)) {
        // Fallback Server-side Search (LIKE prefix / Fulltext)
        $stmt = $pdo->prepare("
            SELECT id, kode_produk, nama_item, kategori, harga, stok, satuan, foto, status
            FROM kantin_menu
            WHERE (nama_item LIKE :q OR kode_produk LIKE :q OR kategori LIKE :q)
            ORDER BY status ASC, nama_item ASC
            LIMIT 50
        ");
        $stmt->execute([':q' => '%' . $search . '%']);
    } else {
        // Load seluruh produk aktif untuk client-side array filter (cepat < 200 item)
        $stmt = $pdo->query("
            SELECT id, kode_produk, nama_item, kategori, harga, stok, satuan, foto, status
            FROM kantin_menu
            WHERE status = 'tersedia'
            ORDER BY kategori ASC, nama_item ASC
        ");
    }

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'  => true,
        'count'    => count($products),
        'products' => $products
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
