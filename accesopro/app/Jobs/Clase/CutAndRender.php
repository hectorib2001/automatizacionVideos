<?php

namespace App\Jobs\Clase;

use App\Models\Piece;
use App\Models\Render;
use App\Services\Video\FfmpegRenderer;
use App\Services\Video\SubtitleBuilder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Corta el clip del vídeo original de la clase y lo monta en 9:16 y 16:9 con subtítulos
 * quemados (alineados por palabra, igual que en la fuente de texto). Idempotente por formato.
 */
final class CutAndRender implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    /** @var array<string, array{0: int, 1: int}> */
    private const FORMATS = [
        '9x16' => [1080, 1920],
        '16x9' => [1920, 1080],
    ];

    public function __construct(public readonly Piece $piece) {}

    public function uniqueId(): string
    {
        return "cut-and-render-{$this->piece->id}";
    }

    public function handle(SubtitleBuilder $subtitleBuilder, FfmpegRenderer $ffmpeg): void
    {
        $pending = collect(self::FORMATS)->keys()
            ->reject(fn (string $format) => $this->piece->renders()->where('format', $format)->exists());

        if ($pending->isEmpty()) {
            $this->piece->markRendered();

            return;
        }

        $videoPath = $this->piece->source->classVideoPath();
        $source = $this->piece->source;
        $transcriptPath = $source->meta['transcript_path'] ?? null;

        if (! $transcriptPath || ! is_file($transcriptPath) || ! is_file($videoPath)) {
            throw new RuntimeException("Faltan el vídeo o la transcripción de la clase para cortar la pieza {$this->piece->uid}.");
        }

        $transcript = json_decode(file_get_contents($transcriptPath), true);
        $clipStart = (float) $this->piece->clip_start;
        $duration = (float) $this->piece->clip_end - $clipStart;

        $words = collect($transcript['words'])
            ->filter(fn (array $w) => $w['start'] >= $clipStart - 0.05 && $w['end'] <= $this->piece->clip_end + 0.05)
            ->map(fn (array $w) => ['text' => $w['word'], 'start' => $w['start'] - $clipStart, 'end' => $w['end'] - $clipStart])
            ->values()->all();

        $cues = $subtitleBuilder->buildCuesFromWords($words);
        $dir = $this->piece->runDir();
        File::ensureDirectoryExists($dir);

        foreach ($pending as $format) {
            [$width, $height] = self::FORMATS[$format];

            $ass = "{$dir}/subs_{$format}.ass";
            file_put_contents($ass, $subtitleBuilder->toAss($cues, $width, $height));

            $output = "{$dir}/video_{$format}.mp4";
            $ffmpeg->renderClip($videoPath, $clipStart, $duration, $ass, $width, $height, $output);

            Render::updateOrCreate(
                ['piece_id' => $this->piece->id, 'format' => $format],
                ['path' => $output, 'duration' => $duration],
            );
        }

        $this->piece->markRendered();
    }
}
