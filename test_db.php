<?php
$databases = ['miyf8713_pembayaran', 'edu-app'];
$users = ['root', 'miyf8713_user'];
$passwords = ['', '@ZiaRika1215'];

foreach ($databases as $db) {
    foreach ($users as $user) {
        foreach ($passwords as $pass) {
            try {
                $dsn = "mysql:host=localhost;dbname=$db;charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $pass);
                echo "SUCCESS: db=$db, user=$user, pass=$pass\n";
                exit;
            } catch (Exception $e) {
                // echo "FAIL: db=$db, user=$user, pass=$pass - " . $e->getMessage() . "\n";
            }
        }
    }
}
echo "ALL FAILED\n";
