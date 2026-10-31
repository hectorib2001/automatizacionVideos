<?php

namespace App\Services\Video;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Monta el vídeo final con FFmpeg: fondo de marca en bucle + texto de cada escena
 * (`drawtext`) + subtítulos quemados (`ass`) + audio narrado. H.264 + AAC, 30 fps.
 * Ver docs/ARQUITECTURA.md ("Montaje") y docs/DECISIONES.md (GD sin FreeType → todo el
 * texto lo pone FFmpeg, que sí tiene libfreetype/libass).
 */
final class FfmpegRenderer
{
    private const CHARS_PER_LINE = 24;

    /**
     * Reparte la duración real del audio entre las escenas, proporcional a la duración
     * que estimó el generador, para que el vídeo dure exactamente lo que dura la voz.
     *
     * @param  list<array{texto: string, formula?: ?string, duracion_seg: float}>  $scenes
     * @return list<array{texto: string, formula: ?string, start: float, end: float}>
     */
    public function scaleSceneTimings(array $scenes, float $targetDuration): array
    {
        $estimatedTotal = array_sum(array_column($scenes, 'duracion_seg'));
        $scale = $estimatedTotal > 0 ? $targetDuration / $estimatedTotal : 1.0;

        $t = 0.0;
        $timed = [];

        foreach ($scenes as $scene) {
            $duration = $scene['duracion_seg'] * $scale;
            $timed[] = [
                'texto' => $scene['texto'],
                'formula' => $scene['formula'] ?? null,
                'start' => $t,
                'end' => $t + $duration,
            ];
            $t += $duration;
        }

        return $timed;
    }

    /**
     * @param  list<array{texto: string, formula: ?string, start: float, end: float}>  $scenes
     */
    public function render(
        string $backgroundPath,
        string $audioPath,
        array $scenes,
        string $assPath,
        float $duration,
        int $width,
        int $height,
        string $outputPath,
        string $workDir,
    ): void {
        File::ensureDirectoryExists($workDir);

        $filterGraph = $this->buildFilterGraph($scenes, $assPath, $width, $height, $workDir);

        $result = Process::timeout((int) config('accesopro.ffmpeg_timeout'))->run([
            config('accesopro.ffmpeg'),
            '-y',
            '-loop', '1',
            '-framerate', '30',
            '-i', $backgroundPath,
            '-i', $audioPath,
            '-vf', $filterGraph,
            '-map', '0:v',
            '-map', '1:a',
            '-c:v', 'libx264',
            ...$this->encoderOptions(),
            '-pix_fmt', 'yuv420p',
            '-r', '30',
            '-c:a', 'aac',
            '-b:a', '192k',
            '-t', number_format($duration, 3, '.', ''),
            '-shortest',
            $outputPath,
        ]);

        if (! $result->successful()) {
            throw FfmpegFailure::from('renderizar '.basename($outputPath), $result);
        }
    }

    /**
     * Corta un fragmento [start, start+duration) de la clase y lo monta con subtítulos
     * quemados. En 16:9 escala directo; en 9:16 centra el vídeo original sobre un fondo
     * difuminado del mismo vídeo (ver docs/ARQUITECTURA.md "Montaje").
     */
    public function renderClip(
        string $sourceVideo,
        float $start,
        float $duration,
        string $assPath,
        int $width,
        int $height,
        string $outputPath,
    ): void {
        $isVertical = $height > $width;

        $filter = $isVertical
            ? "split=2[bg][fg];".
              "[bg]scale={$width}:{$height}:force_original_aspect_ratio=increase,crop={$width}:{$height},gblur=sigma=20[bg2];".
              "[fg]scale={$width}:-2[fg2];".
              '[bg2][fg2]overlay=(W-w)/2:(H-h)/2,'.$this->assFilter($assPath)
            : "scale={$width}:{$height}:force_original_aspect_ratio=decrease,pad={$width}:{$height}:(ow-iw)/2:(oh-ih)/2,".$this->assFilter($assPath);

        $result = Process::timeout((int) config('accesopro.ffmpeg_timeout'))->run([
            config('accesopro.ffmpeg'),
            '-y',
            '-ss', $this->seconds($start),
            '-i', $sourceVideo,
            '-t', $this->seconds($duration),
            '-vf', $filter,
            '-c:v', 'libx264',
            ...$this->encoderOptions(),
            '-pix_fmt', 'yuv420p',
            '-r', '30',
            '-c:a', 'aac',
            '-b:a', '192k',
            $outputPath,
        ]);

        if (! $result->successful()) {
            throw FfmpegFailure::from('cortar el clip '.basename($outputPath), $result);
        }
    }

    /** @param  list<array{texto: string, formula: ?string, start: float, end: float}>  $scenes */
    private function buildFilterGraph(array $scenes, string $assPath, int $width, int $height, string $workDir): string
    {
        $font = config('accesopro.brand.font_path');
        $textoSize = (int) round($width * 0.05);
        $formulaSize = (int) round($width * 0.065);
        $green = $this->ffmpegColor(config('accesopro.brand.colors.primary_green'));
        $lineHeight = fn (int $size) => (int) round($size * 1.35);
        $gap = (int) round($textoSize * 0.7);

        $filters = [];

        foreach ($scenes as $i => $scene) {
            $textoLines = TextWrapper::wrap($scene['texto'], self::CHARS_PER_LINE);
            $textoHeight = count($textoLines) * $lineHeight($textoSize);

            $formulaLines = empty($scene['formula']) ? [] : TextWrapper::wrap($scene['formula'], self::CHARS_PER_LINE);
            $formulaHeight = $formulaLines === [] ? 0 : count($formulaLines) * $lineHeight($formulaSize);

            // Calculamos las posiciones en PHP (no con expresiones "y=(h-text_h)/2" de
            // FFmpeg): cada drawtext solo conoce su propio text_h, así que dos bloques
            // "centrados" de forma independiente se solapan en cuanto uno tiene varias
            // líneas. Aquí apilamos texto y fórmula como un único bloque centrado.
            $blockHeight = $textoHeight + ($formulaHeight > 0 ? $gap + $formulaHeight : 0);
            $textoY = (int) round(($height - $blockHeight) / 2);
            $formulaY = $textoY + $textoHeight + $gap;

            $textoFile = $this->writeTextFile($workDir, "texto_{$i}", $textoLines);
            $filters[] = sprintf(
                "drawtext=fontfile=%s:textfile=%s:fontsize=%d:fontcolor=white:line_spacing=%d:text_align=center:x=(w-text_w)/2:y=%d:enable='between(t,%s,%s)'",
                $this->escapePath($font), $this->escapePath($textoFile), $textoSize, (int) round($textoSize * 0.35), $textoY,
                $this->seconds($scene['start']), $this->seconds($scene['end']),
            );

            if ($formulaLines !== []) {
                $formulaFile = $this->writeTextFile($workDir, "formula_{$i}", $formulaLines);
                $filters[] = sprintf(
                    "drawtext=fontfile=%s:textfile=%s:fontsize=%d:fontcolor=%s:line_spacing=%d:text_align=center:x=(w-text_w)/2:y=%d:enable='between(t,%s,%s)'",
                    $this->escapePath($font), $this->escapePath($formulaFile), $formulaSize, $green, (int) round($formulaSize * 0.35), $formulaY,
                    $this->seconds($scene['start']), $this->seconds($scene['end']),
                );
            }
        }

        $filters[] = $this->assFilter($assPath);

        return implode(',', $filters);
    }

    /** @param  list<string>  $lines */
    private function writeTextFile(string $workDir, string $name, array $lines): string
    {
        $path = "{$workDir}/{$name}.txt";
        file_put_contents($path, implode("\n", $lines));

        return $path;
    }

    private function seconds(float $value): string
    {
        return number_format($value, 3, '.', '');
    }

    /**
     * Opciones de x264 configurables (FFMPEG_THREADS, FFMPEG_PRESET). Sin configurar no se
     * añade nada y FFmpeg usa sus valores por defecto.
     *
     * @return list<string>
     */
    private function encoderOptions(): array
    {
        $options = [];

        if ($preset = config('accesopro.ffmpeg_preset')) {
            array_push($options, '-preset', (string) $preset);
        }

        if ($threads = config('accesopro.ffmpeg_threads')) {
            // threads limita el codificador; lookahead corto reduce los fotogramas en memoria.
            array_push($options, '-threads', (string) (int) $threads, '-x264-params', 'rc-lookahead=10');
        }

        return $options;
    }

    /**
     * Subtítulos quemados con libass. `fontsdir` apunta a la carpeta de fuentes del repo para
     * que "Inter" se encuentre aunque el sistema no tenga fuentes instaladas (servidores de
     * Laravel Cloud): sin ella, libass puede no dibujar nada y FFmpeg no da error (D32).
     */
    private function assFilter(string $assPath): string
    {
        return 'ass='.$this->escapePath($assPath)
            .':fontsdir='.$this->escapePath(dirname((string) config('accesopro.brand.font_path')));
    }

    private function escapePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = str_replace(':', '\\:', $path);

        return "'{$path}'";
    }

    private function ffmpegColor(string $hex): string
    {
        return '0x'.ltrim($hex, '#');
    }
}
