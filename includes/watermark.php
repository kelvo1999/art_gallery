<?php
// includes/watermark.php - GD Library watermarking

/**
 * Apply a watermark to an image and save the preview.
 * Original is stored as-is (protected path).
 *
 * @param string $sourcePath   Full path to the original uploaded image
 * @param string $destPath     Full path where the watermarked preview will be saved
 * @param string $text         Watermark text (default: site name)
 * @return bool
 */
function applyWatermark($sourcePath, $destPath, $text = 'ArtVault — Preview Only') {
    // Determine image type
    $info = getimagesize($sourcePath);
    if (!$info) return false;

    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg':
            $src = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $src = imagecreatefrompng($sourcePath);
            break;
        case 'image/webp':
            $src = imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }

    $w = imagesx($src);
    $h = imagesy($src);

    // Create a copy to draw on
    $watermarked = imagecreatetruecolor($w, $h);

    // Preserve transparency for PNG
    if ($mime === 'image/png') {
        imagealphablending($watermarked, false);
        imagesavealpha($watermarked, true);
        $transparent = imagecolorallocatealpha($watermarked, 0, 0, 0, 127);
        imagefill($watermarked, 0, 0, $transparent);
        imagealphablending($watermarked, true);
    }

    imagecopy($watermarked, $src, 0, 0, 0, 0, $w, $h);

    // Semi-transparent overlay
    $overlay = imagecolorallocatealpha($watermarked, 0, 0, 0, 60);

    // --- diagonal tiled text watermark ---
    $fontSize  = max(14, (int)($w / 25));
    $fontFile  = __DIR__ . '/fonts/arial.ttf'; // place arial.ttf in includes/fonts/
    $angle     = -30;
    $color     = imagecolorallocatealpha($watermarked, 255, 255, 255, 60);
    $colorDark = imagecolorallocatealpha($watermarked, 0, 0, 0, 80);

    // Tile the watermark text across the image
    if (file_exists($fontFile)) {
        $stepX = (int)($w / 3);
        $stepY = (int)($h / 4);
        for ($x = -$stepX; $x < $w + $stepX; $x += $stepX) {
            for ($y = 0; $y < $h + $stepY; $y += $stepY) {
                // Shadow
                imagettftext($watermarked, $fontSize, $angle, $x + 2, $y + 2, $colorDark, $fontFile, $text);
                // Main text
                imagettftext($watermarked, $fontSize, $angle, $x, $y, $color, $fontFile, $text);
            }
        }
    } else {
        // Fallback: built-in font if TTF not available
        $builtinColor = imagecolorallocatealpha($watermarked, 255, 255, 255, 50);
        $stepX = (int)($w / 4);
        $stepY = (int)($h / 5);
        for ($x = 0; $x < $w; $x += $stepX) {
            for ($y = 20; $y < $h; $y += $stepY) {
                imagestring($watermarked, 5, $x, $y, 'ArtVault Preview', $builtinColor);
            }
        }
    }

    // Bottom banner
    $bannerH = max(40, (int)($h * 0.06));
    $bannerColor = imagecolorallocatealpha($watermarked, 0, 0, 0, 40);
    imagefilledrectangle($watermarked, 0, $h - $bannerH, $w, $h, $bannerColor);

    $bannerText = 'Purchase at ArtVault to download the original';
    if (file_exists($fontFile)) {
        $bannerFontSize = max(10, (int)($bannerH * 0.4));
        imagettftext($watermarked, $bannerFontSize, 0, 10, $h - (int)($bannerH * 0.25),
            imagecolorallocate($watermarked, 255, 255, 255), $fontFile, $bannerText);
    } else {
        imagestring($watermarked, 3, 10, $h - $bannerH + 10,
            $bannerText, imagecolorallocate($watermarked, 255, 255, 255));
    }

    // Save preview
    $ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'png':
            imagepng($watermarked, $destPath, 8);
            break;
        case 'webp':
            imagewebp($watermarked, $destPath, 85);
            break;
        default:
            imagejpeg($watermarked, $destPath, 85);
    }

    imagedestroy($src);
    imagedestroy($watermarked);

    return true;
}

/**
 * Upload artwork: save original in protected folder, generate watermarked preview.
 *
 * @param array  $file       $_FILES entry
 * @param int    $artistId
 * @param string $title
 * @return array ['original' => path, 'preview' => path] or ['error' => msg]
 */
function uploadArtwork($file, $artistId, $title) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize = 20 * 1024 * 1024; // 20MB

    if (!in_array($file['type'], $allowedTypes)) {
        return ['error' => 'Only JPEG, PNG, and WebP images are allowed.'];
    }
    if ($file['size'] > $maxSize) {
        return ['error' => 'Image must be under 20MB.'];
    }

    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'art_' . $artistId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    // Paths — originals OUTSIDE webroot ideally; here we use a protected folder with .htaccess
    $originalDir = __DIR__ . '/../uploads/originals/';
    $previewDir  = __DIR__ . '/../uploads/previews/';

    if (!is_dir($originalDir)) mkdir($originalDir, 0750, true);
    if (!is_dir($previewDir))  mkdir($previewDir,  0750, true);

    $originalPath = $originalDir . $filename;
    $previewPath  = $previewDir  . $filename;

    if (!move_uploaded_file($file['tmp_name'], $originalPath)) {
        return ['error' => 'Failed to save the uploaded file.'];
    }

    if (!applyWatermark($originalPath, $previewPath)) {
        return ['error' => 'Failed to generate watermarked preview.'];
    }

    return [
        'original' => 'uploads/originals/' . $filename,
        'preview'  => 'uploads/previews/'  . $filename,
    ];
}
