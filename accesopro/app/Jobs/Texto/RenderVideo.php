<?php

namespace App\Jobs\Texto;

use App\Models\Piece;
use App\Models\Render;
use App\Services\Video\FfmpegRenderer;
use App\Services\Video\SceneBuilder;
use App\Services\Video\SubtitleBuilder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * Monta el vídeo vertical (9:16) y horizontal (16:9) de una pieza: fondo de marca, texto
 * de cada escena, subtítulos quemados a partir del alineamiento de la voz, y el audio
 * narrado. Requiere que SynthesizeVoice ya haya corrido. Idempotente por formato.
 */
final class RenderVideo implements ShouldQueue, ShouldBeUnique
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
        return "render-video-{$this->piece->id}";
    }

    public function handle(SceneBuilder $sceneBuilder, SubtitleBuilder $subtitleBuilder, FfmpegRenderer $ffmpeg): void
    {
        $pending = collect(self::FORMATS)->keys()
            ->reject(fn (string $format) => $this->piece->renders()->where('format', $format)->exists());

        if ($pending->isEmpty()) {
            $this->piece->markRendered();

            return;
        }

        $audio = $this->piece->renders()->where('format', 'audio')->first();

        if (! $audio) {
            throw new RuntimeException("La pieza {$this->piece->uid} no tiene audio: ejecuta SynthesizeVoice primero.");
        }

        $alignment = json_decode(file_get_contents(dirname($audio->path).'/alignment.json'), true);
        $cues = $subtitleBuilder->buildCues($alignment);
        $dir = $this->piece->runDir();

        foreach ($pending as $format) {
            [$width, $height] = self::FORMATS[$format];

            $background = "{$dir}/bg_{$format}.png";
            $sceneBuilder->renderBackground($width, $height, $background);

            $ass = "{$dir}/subs_{$format}.ass";
            file_put_contents($ass, $subtitleBuilder->toAss($cues, $width, $height));

            $timedScenes = $ffmpeg->scaleSceneTimings($this->piece->script['scenes'], $audio->duration);
            $output = "{$dir}/video_{$format}.mp4";

            $ffmpeg->render($background, $audio->path, $timedScenes, $ass, $audio->duration, $width, $height, $output, "{$dir}/work_{$format}");

            Render::updateOrCreate(
                ['piece_id' => $this->piece->id, 'format' => $format],
                ['path' => $output, 'duration' => $audio->duration],
            );
        }

        $this->piece->markRendered();
    }
}
