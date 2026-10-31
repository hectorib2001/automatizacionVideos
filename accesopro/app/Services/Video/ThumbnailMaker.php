<?php

namespace App\Services\Video;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Genera la miniatura (thumbnail) de una pieza: fondo de marca + título grande, como una
 * imagen estática en formato YouTube (1280×720). Reutiliza SceneBuilder para el fondo y el
 * mismo motor de texto que FfmpegRenderer (drawtext, ver docs/DECISIONES.md D18).
 */
final class ThumbnailMaker
{
    private const CHARS_PER_LINE = 20;

    public function __construct(private readonly SceneBuilder $sceneBuilder) {}

    public function make(string $title, string $outputPath, string $workDir): void
    {
        File::ensureDirectoryExists($workDir);

        $width = 1280;
        $height = 720;

        $background = "{$workDir}/thumb_bg.png";
        $this->sceneBuilder->renderBackground($width, $height, $background);

        $lines = TextWrapper::wrap($title, self::CHARS_PER_LINE);
        $fontSize = (int) round($width * 0.07);
        $lineHeight = (int) round($fontSize * 1.3);
        $textFile = "{$workDir}/thumb_title.txt";
        file_put_contents($textFile, implode("\n", $lines));

        $font = config('accesopro.brand.font_path');
        $y = (int) round(($height - count($lines) * $lineHeight) / 2);

        $filter = sprintf(
            "drawtext=fontfile=%s:textfile=%s:fontsize=%d:fontcolor=white:line_spacing=%d:text_align=center:x=(w-text_w)/2:y=%d",
            $this->escapePath($font), $this->escapePath($textFile), $fontSize, (int) round($fontSize * 0.3), $y,
        );

        $result = Process::run([
            config('accesopro.ffmpeg'), '-y',
            '-i', $background,
            '-vf', $filter,
            '-frames:v', '1',
            $outputPath,
        ]);

        if (! $result->successful()) {
            throw FfmpegFailure::from('generar la miniatura', $result);
        }
    }

    private function escapePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = str_replace(':', '\\:', $path);

        return "'{$path}'";
    }
}
