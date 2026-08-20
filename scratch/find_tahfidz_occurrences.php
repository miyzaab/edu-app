<?php
$lines = file('portal-ortu.php');
foreach ($lines as $num => $line) {
    if (stripos($line, 'Ziyadah') !== false || stripos($line, 'Musyrif') !== false || stripos($line, 'MUMTAZ') !== false) {
        $cleanLine = trim($line);
        if (strlen($cleanLine) > 120) {
            $cleanLine = substr($cleanLine, 0, 120) . '...';
        }
        echo ($num + 1) . ': ' . $cleanLine . PHP_EOL;
    }
}
?>
