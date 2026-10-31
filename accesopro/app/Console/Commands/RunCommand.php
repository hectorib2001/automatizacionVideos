<?php

namespace App\Console\Commands;

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
use Illuminate\Console\Command;

/**
 * Disparador único del pipeline (ver docs/ARQUITECTURA.md): fuente de texto (ideas → guion →
 * revisión → voz → vídeo → metadata/miniatura → Drive) y, con --class, la clase grabada
 * (descarga → transcripción → momentos → revisión → corte → metadata/miniatura → Drive).
 */
final class RunCommand extends Command
{
    protected $signature = 'accesopro:run
        {code : Código del tema, p. ej. T1_S01}
        {--class= : Enlace o ruta de la clase grabada. Si se indica, procesa también los clips.}
        {--limit=1 : Cuántas ideas procesar (guion + revisión) en esta ejecución. Cada una es una llamada de pago al generador y al revisor.}
        {--clip-limit=1 : Cuántos clips detectados revisar y montar en esta ejecución.}';

    protected $description = 'Ejecuta el pipeline de AccesoPro para un tema (y, con --class, la clase grabada).';

    public function handle(): int
    {
        $code = $this->argument('code');

        $this->runTexto($code);

        if ($this->option('class')) {
            $this->runClase($this->option('class'));
        }

        return self::SUCCESS;
    }

    private function runTexto(string $code): void
    {
        $source = Source::firstOrCreate(
            ['type' => 'temario', 'code' => $code],
            ['title' => $code],
        );

        $this->info("Extrayendo ideas de {$code}...");
        ExtractIdeas::dispatchSync($source);
        $source->refresh();

        $ideas = $source->meta['ideas'] ?? [];

        if ($ideas === []) {
            $this->error('No se extrajo ninguna idea. Revisa el material de origen en materiales/.');

            return;
        }

        $this->info(count($ideas)." ideas disponibles. Procesando ".$this->limit($ideas).".");

        $rows = [];

        foreach (array_slice($ideas, 0, $this->limit($ideas)) as $idea) {
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

            $video = '';
            $published = '';

            if ($piece?->approve()) {
                SynthesizeVoice::dispatchSync($piece);
                RenderVideo::dispatchSync($piece);
                GenerateMetadata::dispatchSync($piece);
                GenerateThumbnail::dispatchSync($piece);
                Publish::dispatchSync($piece);
                $piece->refresh();

                $video = $piece->renders()->whereIn('format', ['9x16', '16x9'])->count().'/2 formatos';
                $published = $piece->publications()->where('status', 'publicada')->count().'/2 en Drive';
            }

            $rows[] = [
                $idea['idea_key'],
                $piece?->status->value ?? '?',
                number_format($piece?->cost_eur ?? 0, 5),
                $video,
                $published,
                $piece?->discard_reason ?? '',
            ];
        }

        $this->table(['idea', 'estado', 'coste (€)', 'vídeo', 'publicado', 'motivo de descarte'], $rows);

        if (count($ideas) > $this->limit($ideas)) {
            $restantes = count($ideas) - $this->limit($ideas);
            $this->line("Quedan {$restantes} ideas sin procesar. Vuelve a ejecutar con --limit mayor cuando quieras continuar.");
        }
    }

    private function runClase(string $classUrl): void
    {
        $code = $this->classCode($classUrl);

        $source = Source::firstOrCreate(
            ['type' => 'clase', 'code' => $code],
            ['title' => "Clase {$code}", 'ref' => $classUrl],
        );

        $this->info('Descargando/comprobando el vídeo de la clase...');
        FetchClassVideo::dispatchSync($source);

        $this->info('Extrayendo audio...');
        ExtractAudio::dispatchSync($source);

        $this->info('Transcribiendo (Groq Whisper)...');
        Transcribe::dispatchSync($source);
        $source->refresh();

        $this->info('Detectando momentos con valor propio...');
        DetectMoments::dispatchSync($source);
        $source->refresh();

        $moments = $source->meta['moments'] ?? [];

        if ($moments === []) {
            $this->error('No se detectó ningún momento en la clase.');

            return;
        }

        $this->info(count($moments)." momentos detectados. Procesando ".$this->clipLimit(count($moments)).".");

        $pieces = Piece::where('source_id', $source->id)->orderBy('clip_start')->get();
        $rows = [];

        foreach ($pieces->take($this->clipLimit(count($moments))) as $piece) {
            ReviewClip::dispatchSync($piece);
            $piece->refresh();

            $video = '';
            $published = '';

            if ($piece->approve()) {
                CutAndRender::dispatchSync($piece);
                GenerateMetadata::dispatchSync($piece);
                GenerateThumbnail::dispatchSync($piece);
                Publish::dispatchSync($piece);
                $piece->refresh();

                $video = $piece->renders()->whereIn('format', ['9x16', '16x9'])->count().'/2 formatos';
                $published = $piece->publications()->where('status', 'publicada')->count().'/2 en Drive';
            }

            $rows[] = [
                $piece->idea_title,
                $piece->status->value,
                number_format($piece->cost_eur, 5),
                $video,
                $published,
                $piece->discard_reason ?? '',
            ];
        }

        $this->table(['clip', 'estado', 'coste (€)', 'vídeo', 'publicado', 'motivo de descarte'], $rows);

        if ($pieces->count() > $this->clipLimit(count($moments))) {
            $restantes = $pieces->count() - $this->clipLimit(count($moments));
            $this->line("Quedan {$restantes} clips sin procesar. Vuelve a ejecutar con --clip-limit mayor cuando quieras continuar.");
        }
    }

    private function classCode(string $classUrl): string
    {
        if (preg_match('/[?&]v=([a-zA-Z0-9_-]{6,})/', $classUrl, $m) || preg_match('#youtu\.be/([a-zA-Z0-9_-]{6,})#', $classUrl, $m)) {
            return $m[1];
        }

        return substr(hash('sha256', $classUrl), 0, 16);
    }

    private function clipLimit(int $available): int
    {
        $limit = (int) $this->option('clip-limit');

        return $limit > 0 ? min($limit, $available) : $available;
    }

    /** @param  list<array<string, mixed>>  $ideas */
    private function limit(array $ideas): int
    {
        $limit = (int) $this->option('limit');

        return $limit > 0 ? min($limit, count($ideas)) : count($ideas);
    }
}
