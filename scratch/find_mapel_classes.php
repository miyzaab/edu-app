<?php
$lines = file('portal-ortu.php');
foreach ($lines as $num => $line) {
    if (strpos($line, 'mapel-accordion') !== false || strpos($line, 'mapel-icon-pod') !== false) {
        echo ($num + 1) . ': ' . trim($line) . PHP_EOL;
    }
}
?>
