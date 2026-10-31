<?php

namespace App\Services\Pipeline;

use App\Enums\PieceStatus;
use App\Jobs\Clase\CutAndRender;
use App\Jobs\Clase\DetectMoments;
use App\Jobs\Clase\ExtractAudio;
use App\Jobs\Clase\FetchClassVideo;
use App\Jobs\Clase\ReviewClip;
use App\Jobs\Clase\Transcribe;
use App\Jobs\Common\GenerateMetadata;
use App\Jobs\Common\GenerateThumbnail;
use App\Jobs\Common\Publish;
use App\Jobs\Texto\ExtractIdeas;
use App\Jobs\Texto\GenerateScript;
use App\Jobs\Texto\RenderVideo;
use App\Jobs\Texto\ReviseScript;
use App\Jobs\Texto\ReviewScript;
use App\Jobs\Texto\SynthesizeVoice;
use App\Models\Piece;
use App\Models\Source;
use Closure;
use RuntimeException;

/**
 * Pipeline completo de una fuente, de principio a fin. Lo usan el comando `accesopro:run` y
 * el botón "Generar contenido" del panel (vía el job RunSourcePipeline), para que haya una
 * única implementación del disparador. Cada paso es un job idempotente: relanzar no repite
 * llamadas de pago ni crea duplicados.
 */
final class PipelineRunner
{
    /**
     * Fuente de texto: ideas → guion → revisión (⇄ corrección) → voz → vídeo → metadata/miniatura → publicación.
     *
     * @param  int  $limit  Cuántas ideas procesar (0 = todas).
     * @param  (Closure(string): void)|null  $log
     * @return array{rows: list<list<string>>, total: int, processed: int}
     */
    public function runTexto(Source $source, int $limit, ?Closure $log = null): array
    {
        $log ??= fn (string $line) => null;

        $log("Extrayendo ideas de {$source->code}...");
        ExtractIdeas::dispatchSync($source);
        $source->refresh();

        $ideas = $source->meta['ideas'] ?? [];

        if ($ideas === []) {
            throw new RuntimeException("No se extrajo ninguna idea de {$source->code}. Revisa el material de origen.");
        }

        $take = $this->take($limit, count($ideas));
        $log(count($ideas)." ideas disponibles. Procesando {$take}.");

        $rows = [];

        foreach (array_slice($ideas, 0, $take) as $idea) {
            GenerateScript::dispatchSync($source, $idea);

            $piece = Piece::where('uid', Piece::makeUid('texto', $source->code, $idea['idea_key']))->first();

            if ($piece?->script !== null) {
                ReviewScript::dispatchSync($piece);
                $piece->refresh();

                // Bucle de corrección (máx. 2 correcciones, ver docs/REVISOR.md): ReviewScript
                // ya limita los intentos a 3 en total y descarta al llegar al límite, así que
                // este bucle siempre termina en Revisada o Descartada.
                for ($guard = 0; $piece->status === PieceStatus::Rechazada && $guard < 5; $guard++) {
                    ReviseScript::dispatchSync($piece);
                    $piece->refresh();
                    ReviewScript::dispatchSync($piece);
                    $piece->refresh();
                }
            }

            if ($piece?->approve()) {
                SynthesizeVoice::dispatchSync($piece);
                RenderVideo::dispatchSync($piece);
                $this->finish($piece);
            }

            $rows[] = $this->row($idea['idea_key'], $piece);
        }

        return ['rows' => $rows, 'total' => count($ideas), 'processed' => $take];
    }

    /**
     * Clase grabada: descarga → audio → transcripción → momentos → revisión → corte → metadata/miniatura → publicación.
     *
     * @param  int  $limit  Cuántos clips revisar y montar (0 = todos).
     * @param  (Closure(string): void)|null  $log
     * @return array{rows: list<list<string>>, total: int, processed: int}
     */
    public function runClase(Source $source, int $limit, ?Closure $log = null): array
    {
        $log ??= fn (string $line) => null;

        $log('Descargando/comprobando el vídeo de la clase...');
        FetchClassVideo::dispatchSync($source);

        $log('Extrayendo audio...');
        ExtractAudio::dispatchSync($source);

        $log('Transcribiendo (Groq Whisper)...');
        Transcribe::dispatchSync($source);
        $source->refresh();

        $log('Detectando momentos con valor propio...');
        DetectMoments::dispatchSync($source);
        $source->refresh();

        $moments = $source->meta['moments'] ?? [];

        if ($moments === []) {
            throw new RuntimeException('No se detectó ningún momento con valor propio en la clase.');
        }

        $pieces = Piece::where('source_id', $source->id)->orderBy('clip_start')->get();
        $take = $this->take($limit, $pieces->count());
        $log(count($moments)." momentos detectados. Procesando {$take}.");

        $rows = [];

        foreach ($pieces->take($take) as $piece) {
            ReviewClip::dispatchSync($piece);
            $piece->refresh();

            if ($piece->approve()) {
                CutAndRender::dispatchSync($piece);
                $this->finish($piece);
            }

            $rows[] = $this->row($piece->idea_title, $piece);
        }

        return ['rows' => $rows, 'total' => $pieces->count(), 'processed' => $take];
    }

    /** Pasos comunes a ambas fuentes una vez montado el vídeo. */
    private function finish(Piece $piece): void
    {
        GenerateMetadata::dispatchSync($piece);
        GenerateThumbnail::dispatchSync($piece);
        Publish::dispatchSync($piece);
        $piece->refresh();
    }

    /** @return list<string> */
    private function row(string $label, ?Piece $piece): array
    {
        return [
            $label,
            $piece?->status->value ?? '?',
            number_format($piece?->cost_eur ?? 0, 5),
            $piece ? $piece->renders()->whereIn('format', Piece::VIDEO_FORMATS)->count().'/2 formatos' : '',
            $piece ? $piece->publications()->where('status', 'publicada')->count().'/2 publicados' : '',
            $piece?->discard_reason ?? '',
        ];
    }

    private function take(int $limit, int $available): int
    {
        return $limit > 0 ? min($limit, $available) : $available;
    }

    /** Código estable de una clase a partir de su enlace de YouTube. */
    public static function classCode(string $classUrl): string
    {
        if (preg_match('/[?&]v=([a-zA-Z0-9_-]{6,})/', $classUrl, $m)
            || preg_match('#youtu\.be/([a-zA-Z0-9_-]{6,})#', $classUrl, $m)
            || preg_match('#youtube\.com/(?:live|shorts|embed)/([a-zA-Z0-9_-]{6,})#', $classUrl, $m)) {
            return $m[1];
        }

        return substr(hash('sha256', $classUrl), 0, 16);
    }
}
