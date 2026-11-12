<?php
/**
 * Helper Bersama: Validasi & Penyimpanan Aman Upload Bukti Transfer
 * Mencegah file kosong (0 KB), PNG/JPG terpotong (korup), dan path DB fiktif.
 */

if (!function_exists('validateProofBinary')) {
    /**
     * Memvalidasi binary file bukti transfer (gambar atau PDF) dan mengembalikan ekstensi resmi.
     */
    function validateProofBinary(string $bin): ?string
    {
        $len = strlen($bin);
        if ($len < 256) {
            return null;
        }

        // Cek apakah file berupa dokumen PDF valid
        if (strpos(ltrim($bin), '%PDF-') === 0) {
            return 'pdf';
        }

        // Validasi metadata gambar
        $imgInfo = @getimagesizefromstring($bin);
        if (empty($imgInfo['mime']) || empty($imgInfo[0]) || empty($imgInfo[1])) {
            return null;
        }

        $mimeMap = [
            'image/jpeg'      => 'jpg',
            'image/pjpeg'     => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
            'image/gif'       => 'gif',
            'image/bmp'       => 'bmp',
            'image/x-ms-bmp'  => 'bmp',
        ];
        $mime = strtolower((string) $imgInfo['mime']);
        if (!isset($mimeMap[$mime])) {
            return null;
        }
        $ext = $mimeMap[$mime];

        // Deteksi PNG terpotong (harus punya penutup IEND)
        if ($ext === 'png' && strpos($bin, 'IEND') === false) {
            return null;
        }

        // Uji decode GD untuk file <= 8 MB agar file terpotong (mis. hanya header) langsung ditolak
        if (in_array($ext, ['jpg', 'png', 'gif'], true) && $len <= 8 * 1024 * 1024 && function_exists('imagecreatefromstring')) {
            $im = @imagecreatefromstring($bin);
            if ($im === false) {
                return null;
            }
            if (is_object($im) || is_resource($im)) {
                @imagedestroy($im);
            }
        }

        return $ext;
    }
}

if (!function_exists('resolveProofUploadTarget')) {
    /**
     * Memilih direktori penyimpanan bukti transfer yang valid dan writable.
     *
     * @return array{0: string, 1: string} [absoluteDirPath, relativeUrlPrefix]
     */
    function resolveProofUploadTarget(string $baseRoot): array
    {
        $root = rtrim($baseRoot, '/\\');
        $candidates = [
            'assets/uploads/bukti/',
            'uploads/bukti/',
            'assets/uploads/',
            'uploads/',
        ];

        foreach ($candidates as $relPrefix) {
            $dir = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPrefix);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            if (is_dir($dir) && is_writable($dir)) {
                return [$dir, $relPrefix];
            }
        }

        $fallbackDir = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'bukti' . DIRECTORY_SEPARATOR;
        if (!is_dir($fallbackDir)) {
            @mkdir($fallbackDir, 0755, true);
        }
        return [$fallbackDir, 'assets/uploads/bukti/'];
    }
}

if (!function_exists('saveValidatedProofUpload')) {
    /**
     * Menyimpan bukti transfer tervalidasi dari base64 atau $_FILES. Mengembalikan path relatif atau null jika korup/gagal.
     */
    function saveValidatedProofUpload(string $baseRoot, ?array $fileData, ?string $base64Data, string $prefix = 'bukti'): ?string
    {
        [$chosenDir, $relPrefix] = resolveProofUploadTarget($baseRoot);
        if (!is_dir($chosenDir) || !is_writable($chosenDir)) {
            return null;
        }

        // 1. Prioritaskan Base64 yang dikirim dari memori klien
        if (!empty($base64Data) && is_string($base64Data)) {
            $rawB64 = $base64Data;
            if (strpos($rawB64, ',') !== false && preg_match('/^data:[a-zA-Z0-9\/\+\.-]+;base64,/i', $rawB64)) {
                $rawB64 = substr($rawB64, strpos($rawB64, ',') + 1);
            }
            $bin = base64_decode($rawB64);
            if (is_string($bin)) {
                $ext = validateProofBinary($bin);
                if ($ext !== null) {
                    $filename   = $prefix . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $targetPath = rtrim($chosenDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
                    if (@file_put_contents($targetPath, $bin) !== false && @filesize($targetPath) >= 256) {
                        return $relPrefix . $filename;
                    }
                    if (file_exists($targetPath)) {
                        @unlink($targetPath);
                    }
                }
            }
        }

        // 2. Fallback ke $_FILES standar jika tersedia dan valid
        if (!empty($fileData) && isset($fileData['error']) && $fileData['error'] === UPLOAD_ERR_OK) {
            $tmpName = (string) ($fileData['tmp_name'] ?? '');
            $size    = (int) ($fileData['size'] ?? 0);
            if ($tmpName !== '' && file_exists($tmpName) && $size >= 256 && $size <= 15 * 1024 * 1024) {
                $bin = @file_get_contents($tmpName);
                if (is_string($bin)) {
                    $ext = validateProofBinary($bin);
                    if ($ext !== null) {
                        $filename   = $prefix . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                        $targetPath = rtrim($chosenDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
                        $moved = is_uploaded_file($tmpName)
                            ? @move_uploaded_file($tmpName, $targetPath)
                            : (@file_put_contents($targetPath, $bin) !== false);
                        if ($moved && @filesize($targetPath) >= 256) {
                            return $relPrefix . $filename;
                        }
                        if (file_exists($targetPath)) {
                            @unlink($targetPath);
                        }
                    }
                }
            }
        }

        return null;
    }
}
