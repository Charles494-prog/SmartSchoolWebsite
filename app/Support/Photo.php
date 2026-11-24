<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class Photo
{
    /** Center-crop to a square, resize to 600×600 JPEG, save on the private disk. Returns the stored path. */
    public static function storeSquare(string $source, string $path): string
    {
        $img = @imagecreatefromstring((string) file_get_contents($source));
        if (! $img) {
            Storage::disk('local')->put($path, file_get_contents($source));

            return $path;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $side = min($w, $h);
        $out = imagecreatetruecolor(600, 600);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), 600, 600, $side, $side);
        ob_start();
        imagejpeg($out, null, 88);
        Storage::disk('local')->put($path, ob_get_clean());

        return $path;
    }

    public static function dataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode(Storage::disk('local')->get($path));
    }
}
