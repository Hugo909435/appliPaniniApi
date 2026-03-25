<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImageService
{
    /**
     * Redimensionne, convertit en WebP et stocke l'image uploadée sous public_path($subdir).
     * Retourne le chemin web relatif (ex: /assets/players/uuid.webp).
     *
     * @param int $maxWidth  largeur max en px
     * @param int $maxHeight hauteur max en px
     * @param int $quality   qualité WebP 0-100
     */
    public function store(
        UploadedFile $file,
        string $subdir,
        int $maxWidth  = 600,
        int $maxHeight = 900,
        int $quality   = 85
    ): string {
        $directory = public_path(trim($subdir, '/'));
        File::ensureDirectoryExists($directory, 0755, true);

        $gd = $this->toGdImage($file);

        // Fallback : GD indisponible ou format non supporté → stockage brut
        if ($gd === null) {
            $name = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $file->move($directory, $name);
            return '/' . trim($subdir, '/') . '/' . $name;
        }

        $srcW = imagesx($gd);
        $srcH = imagesy($gd);

        // Redimensionnement si l'image dépasse les dimensions max
        if ($srcW > $maxWidth || $srcH > $maxHeight) {
            $ratio  = min($maxWidth / $srcW, $maxHeight / $srcH);
            $newW   = (int) round($srcW * $ratio);
            $newH   = (int) round($srcH * $ratio);
            $canvas = imagecreatetruecolor($newW, $newH);
            // Préserver la transparence (PNG / WebP avec alpha)
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagecopyresampled($canvas, $gd, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
            imagedestroy($gd);
            $gd = $canvas;
        }

        $filename = Str::uuid() . '.webp';
        imagewebp($gd, $directory . '/' . $filename, $quality);
        imagedestroy($gd);

        return '/' . trim($subdir, '/') . '/' . $filename;
    }

    private function toGdImage(UploadedFile $file): ?\GdImage
    {
        $mime = $file->getMimeType() ?? '';
        $path = $file->getRealPath();

        $can = fn(string $fn) => function_exists($fn);

        return match (true) {
            str_contains($mime, 'jpeg') && $can('imagecreatefromjpeg') => @imagecreatefromjpeg($path) ?: null,
            str_contains($mime, 'png')  && $can('imagecreatefrompng')  => @imagecreatefrompng($path)  ?: null,
            str_contains($mime, 'webp') && $can('imagecreatefromwebp') => @imagecreatefromwebp($path) ?: null,
            str_contains($mime, 'gif')  && $can('imagecreatefromgif')  => @imagecreatefromgif($path)  ?: null,
            default => null,
        };
    }
}
