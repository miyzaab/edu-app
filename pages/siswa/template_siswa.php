<?php
/**
 * Download Template CSV untuk import siswa
 */
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="template_import_siswa.csv"');

$output = fopen('php://output', 'w');

// BOM UTF-8 agar Excel bisa baca karakter Indonesia
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header
fputcsv($output, ['NIS', 'Nama Lengkap', 'Kelas', 'Jenis Kelamin (L/P)', 'Tahun Masuk']);

// Contoh data
fputcsv($output, ['2026001', 'Ahmad Fauzan', 'VII-A', 'L', '2026']);
fputcsv($output, ['2026002', 'Siti Aisyah', 'VII-A', 'P', '2026']);
fputcsv($output, ['2026003', 'Muhammad Rizki', 'VII-B', 'L', '2026']);

fclose($output);
exit;
