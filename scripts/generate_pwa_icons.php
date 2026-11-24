<?php

$sizes = [
    512 => 'public/icons/icon-512x512.png',
    192 => 'public/icons/icon-192x192.png',
    180 => 'public/icons/apple-touch-icon.png',
    144 => 'public/icons/icon-144x144.png',
    96  => 'public/icons/icon-96x96.png',
    48  => 'public/icons/icon-48x48.png',
];

$fontPath = 'C:\\Windows\\Fonts\\arialbd.ttf';
if (!file_exists($fontPath)) {
    $fontPath = 'C:\\Windows\\Fonts\\arial.ttf';
}

foreach ($sizes as $size => $destPath) {
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    // Transparent initial
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);

    // Deep dark background with subtle gradient simulation
    $bgDark = imagecolorallocate($img, 10, 10, 10);
    $bgCard = imagecolorallocate($img, 18, 18, 20);
    $borderCol = imagecolorallocate($img, 45, 45, 50);
    $accentCol = imagecolorallocate($img, 59, 130, 246); // Electric blue / primary
    $white = imagecolorallocate($img, 255, 255, 255);
    $gray = imagecolorallocate($img, 156, 163, 175);

    // Draw rounded background squircle
    $radius = (int)($size * 0.22);
    // Fill full size or slightly inset
    imagefilledrectangle($img, 0, 0, $size, $size, $bgDark);

    // Inner card badge
    $pad = (int)($size * 0.08);
    $innerW = $size - ($pad * 2);
    $innerH = $size - ($pad * 2);
    $innerRadius = (int)($size * 0.16);

    // Draw inner rounded badge
    $x1 = $pad;
    $y1 = $pad;
    $x2 = $size - $pad;
    $y2 = $size - $pad;
    
    // Draw background rect with border
    imagefilledrectangle($img, $x1, $y1, $x2, $y2, $bgCard);
    imagerectangle($img, $x1, $y1, $x2, $y2, $borderCol);
    imagerectangle($img, $x1 + 1, $y1 + 1, $x2 - 1, $y2 - 1, $borderCol);

    // Accent slash/corner detail
    $accentThickness = max(2, (int)($size * 0.015));
    for ($i = 0; $i < $accentThickness; $i++) {
        imageline($img, $x1 + 10, $y1 + 10 + $i, $x1 + 40, $y1 + 10 + $i, $accentCol);
    }

    // Monogram "LP" text
    $text = "LP";
    $fontSize = (int)($size * 0.38);

    if (file_exists($fontPath)) {
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
        $textWidth = abs($bbox[4] - $bbox[0]);
        $textHeight = abs($bbox[5] - $bbox[1]);
        
        $x = (int)(($size - $textWidth) / 2) - (int)($size * 0.02);
        // Vertical center with slight offset if we have subtext
        $yOffset = ($size >= 180) ? - (int)($size * 0.04) : 0;
        $y = (int)(($size + $textHeight) / 2) + $yOffset;

        // Shadow
        $shadow = imagecolorallocatealpha($img, 0, 0, 0, 70);
        imagettftext($img, $fontSize, 0, $x + 2, $y + 3, $shadow, $fontPath, $text);

        // Main text
        imagettftext($img, $fontSize, 0, $x, $y, $white, $fontPath, $text);

        // Subtext "LEAD PANTHER" for large icons (180+)
        if ($size >= 180) {
            $subText = "LEAD PANTHER";
            $subSize = max(8, (int)($size * 0.065));
            $subBbox = imagettfbbox($subSize, 0, $fontPath, $subText);
            $subWidth = abs($subBbox[4] - $subBbox[0]);
            $subX = (int)(($size - $subWidth) / 2);
            $subY = (int)($size * 0.82);
            imagettftext($img, $subSize, 0, $subX, $subY, $gray, $fontPath, $subText);
        }
    } else {
        // Fallback to built-in font
        $font = 5;
        $fw = imagefontwidth($font) * strlen($text);
        $fh = imagefontheight($font);
        imagestring($img, $font, ($size - $fw) / 2, ($size - $fh) / 2, $text, $white);
    }

    // Save PNG
    imagepng($img, $destPath);
    imagedestroy($img);
    echo "Generated {$destPath} ({$size}x{$size})\n";
}

echo "All PWA PNG icons generated successfully.\n";
