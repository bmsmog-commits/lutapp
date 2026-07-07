<?php

$iconDirectory = __DIR__.'/../public/icons';

if (! is_dir($iconDirectory)) {
    mkdir($iconDirectory, 0777, true);
}

foreach ([192, 512] as $size) {
    $image = imagecreatetruecolor($size, $size);

    $brand = imagecolorallocate($image, 251, 188, 4);
    $paper = imagecolorallocate($image, 255, 247, 178);
    $ink = imagecolorallocate($image, 32, 33, 36);

    imagefilledrectangle($image, 0, 0, $size, $size, $brand);

    $pad = (int) ($size * 0.16);
    imagefilledrectangle($image, $pad, $pad, $size - $pad, $size - $pad, $paper);

    imagesetthickness($image, max(4, (int) ($size * 0.025)));
    imagerectangle($image, $pad, $pad, $size - $pad, $size - $pad, $ink);

    $font = 5;
    $text = 'L';
    $textWidth = imagefontwidth($font) * strlen($text);
    $textHeight = imagefontheight($font);
    imagestring($image, $font, (int) (($size - $textWidth) / 2), (int) (($size - $textHeight) / 2), $text, $ink);

    imagepng($image, "{$iconDirectory}/icon-{$size}.png");
    imagedestroy($image);
}
