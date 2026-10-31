<?php

namespace App\Jobs\Common;

use App\Models\Source;
use App\Services\Pipeline\PipelineRunner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Ejecuta el pipeline completo de una fuente desde el panel ("Generar contenido"). Va por la
 * cola porque una clase puede tardar decenas de minutos (descarga, transcripción, render) y
 * una petición web no puede esperar tanto. Necesita un worker: `php artisan queue:work`.
 *
 * Un solo intento: si falla, el error queda en la fuente y se relanza con el botón. Relanzar
 * es seguro porque cada paso interno es idempotente (no repite llamadas de pago).
 */
final class RunSourcePipeline implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 1;

    /** Una clase de una hora: descarga + transcripción + varios renders. */
    public int $timeout = 3600;

    public function __construct(public readonly Source $source, public readonly int $limit) {}

    public function uniqueId(): string
    {
        return "run-source-{$this->source->id}";
    }

    public function handle(PipelineRunner $runner): void
    {
        $this->source->update([
            'run_status' => Source::RUN_PROCESSING,
            'run_error' => null,
            'run_started_at' => now(),
            'run_finished_at' => null,
        ]);

        try {
            $this->source->type === 'clase'
                ? $runner->runClase($this->source, $this->limit)
                : $runner->runTexto($this->source, $this->limit);
        } catch (Throwable $e) {
            // También aquí (no solo en failed()): failed() solo lo llama un worker real, no
            // una ejecución síncrona (QUEUE_CONNECTION=sync).
            $this->failed($e);

            throw $e;
        }

        $this->source->update(['run_status' => Source::RUN_DONE, 'run_finished_at' => now()]);
    }

    /** También cubre timeouts del worker, que matan el job sin pasar por el catch de handle(). */
    public function failed(?Throwable $e): void
    {
        $this->source->update([
            'run_status' => Source::RUN_ERROR,
            'run_error' => $e?->getMessage(),
            'run_finished_at' => now(),
        ]);
    }
}
