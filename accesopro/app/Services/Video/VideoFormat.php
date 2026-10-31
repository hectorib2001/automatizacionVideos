<?php

namespace App\Services\Video;

/**
 * Dimensiones de cada formato de vídeo. `VIDEO_SCALE` las reduce para máquinas pequeñas
 * (Laravel Cloud con 512 MiB: 0.5 → 540×960 / 960×540, ¼ de píxeles y mucha menos memoria
 * en FFmpeg). Textos, subtítulos y logo se calculan en proporción al ancho, así que la
 * composición es la misma a cualquier escala (D34).
 */
final class VideoFormat
{
    /** @var array<string, array{0: int, 1: int}> */
    private const BASE = [
        '9x16' => [1080, 1920],
        '16x9' => [1920, 1080],
    ];

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::BASE);
    }

    /** @return array{0: int, 1: int} ancho y alto, siempre pares (requisito de yuv420p/x264) */
    public static function dimensions(string $format): array
    {
        $scale = (float) config('accesopro.video_scale', 1.0);
        $scale = $scale > 0 && $scale <= 1 ? $scale : 1.0;

        return array_map(
            fn (int $px) => max(2, (int) (round($px * $scale / 2) * 2)),
            self::BASE[$format],
        );
    }
}
