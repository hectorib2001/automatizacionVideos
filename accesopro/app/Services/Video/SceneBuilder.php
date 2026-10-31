<?php

namespace App\Services\Video;

use GdImage;
use RuntimeException;

/**
 * Dibuja el fondo de marca de AccesoPro (color corporativo + logo) para un formato de
 * vídeo. Usa GD (no ImageMagick/Chromium, ver docs/ARQUITECTURA.md regla "Sin Chromium").
 *
 * El texto de cada escena y los subtítulos NO se dibujan aquí: el GD de este entorno no
 * tiene FreeType (ver docs/DECISIONES.md), así que todo el texto lo pone FFmpeg encima de
 * este fondo (`drawtext` para las escenas, `ass` para los subtítulos quemados), que sí
 * tiene FreeType/libass. `FfmpegRenderer` usa este fondo como imagen de entrada en bucle.
 */
final class SceneBuilder
{
    public function renderBackground(int $width, int $height, string $outputPath): void
    {
        $image = imagecreatetruecolor($width, $height);

        $this->fillBackground($image, $width, $height);
        $this->drawLogo($image, $width, $height);

        imagepng($image, $outputPath);
        imagedestroy($image);
    }

    private function fillBackground(GdImage $image, int $width, int $height): void
    {
        $bg = $this->color($image, config('accesopro.brand.colors.dark_blue'));
        imagefilledrectangle($image, 0, 0, $width, $height, $bg);
    }

    private function drawLogo(GdImage $image, int $width, int $height): void
    {
        $path = config('accesopro.brand.logo_path');

        if (! is_file($path)) {
            return; // Sin materiales/ (p. ej. clon limpio del repo) seguimos sin logo.
        }

        $logo = imagecreatefrompng($path);
        $logoWidth = (int) round($width * 0.4);
        $logoHeight = (int) round(imagesy($logo) * ($logoWidth / imagesx($logo)));

        imagealphablending($image, true);
        imagecopyresampled(
            $image, $logo,
            (int) round(($width - $logoWidth) / 2), (int) round($height * 0.06),
            0, 0,
            $logoWidth, $logoHeight,
            imagesx($logo), imagesy($logo),
        );

        imagedestroy($logo);
    }

    private function color(GdImage $image, string $hex): int
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6) {
            throw new RuntimeException("Color de marca inválido: #{$hex}");
        }

        return imagecolorallocate(
            $image,
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        );
    }
}
