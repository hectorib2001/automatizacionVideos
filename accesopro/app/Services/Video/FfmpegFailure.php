<?php

namespace App\Services\Video;

use Illuminate\Contracts\Process\ProcessResult;
use RuntimeException;

/**
 * Error de FFmpeg legible: código de salida + las últimas líneas de stderr (el error real
 * está al final; el principio es solo el banner de versión) y un aviso si el proceso murió
 * por falta de memoria, que en Laravel Cloud con 512 MiB es la causa más probable (D31).
 */
final class FfmpegFailure extends RuntimeException
{
    private const TAIL_LINES = 12;

    public static function from(string $what, ProcessResult $result): self
    {
        $code = $result->exitCode();
        $lines = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', trim($result->errorOutput())) ?: [],
            fn (string $l) => trim($l) !== '',
        ));
        $tail = implode("\n", array_slice($lines, -self::TAIL_LINES));

        $hint = in_array($code, [137, -9, 9], true) || $code === null
            ? ' Probable falta de memoria (el sistema mató FFmpeg): sube la memoria del worker o baja FFMPEG_THREADS.'
            : '';

        return new self("FFmpeg falló al {$what} (código {$code}).{$hint}\n{$tail}");
    }
}
