<?php
/**
 * BACKUP DATABASE - Eksport data ke file .sql
 */
require_once __DIR__ . '/../../config/auth.php';
requirePermission('pengaturan');

// Hanya admin/bendahara yang bisa backup
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'bendahara') {
    die("Akses ditolak.");
}

$pdo = getConnection();
$tables = [];
$result = $pdo->query("SHOW TABLES");
while ($row = $result->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

$sql = "-- ====================================================\n";
$sql .= "-- Database Backup - Edu-App System\n";
$sql .= "-- Generated at: " . date('Y-m-d H:i:s') . "\n";
$sql .= "-- ====================================================\n\n";
$sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
$sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n\n";

foreach ($tables as $table) {
    // Drop table if exists
    $sql .= "DROP TABLE IF EXISTS `$table`;\n";

    // Create table structure
    $res = $pdo->query("SHOW CREATE TABLE `$table`");
    $row = $res->fetch(PDO::FETCH_NUM);
    $sql .= "\n" . $row[1] . ";\n\n";

    // Get data
    $res = $pdo->query("SELECT * FROM `$table`");
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $keys = array_map(function ($k) {
            return "`$k`"; }, array_keys($row));
        $values = array_values($row);

        $sql .= "INSERT INTO `$table` (" . implode(", ", $keys) . ") VALUES (";

        $valArr = [];
        foreach ($values as $v) {
            if ($v === null) {
                $valArr[] = "NULL";
            } else {
                $valArr[] = $pdo->quote($v);
            }
        }
        $sql .= implode(", ", $valArr) . ");\n";
    }
    $sql .= "\n";
}

$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

// Download file
$filename = "Backup_Edu-App_" . date('Y-m-d_His') . ".sql";
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $sql;
exit;

