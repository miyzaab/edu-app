<?php
require 'config/koneksi.php';
$pdo = getConnection();
$tables = ['kantin_menu', 'kantin_transaksi', 'kantin_transaksi_detail', 'kantin_topup'];
$sql = "";
foreach ($tables as $t) {
    try {
        $stmt = $pdo->query("SHOW CREATE TABLE $t");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $sql .= $row[1] . ";\n\n";
    } catch (Exception $e) {
        // Table might not exist locally either?
    }
}
file_put_contents('kantin_tables.sql', $sql);
echo "Done.";
