<?php

namespace App\Console\Commands;

use App\Jobs\Common\RunSourcePipeline;
use App\Models\Source;
use Illuminate\Bus\UniqueLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Desbloquea fuentes que se quedaron en "en cola"/"procesando" porque el sistema mató el
 * trabajo a mitad (p. ej. falta de memoria en Laravel Cloud: el proceso muere sin pasar por
 * failed()). Sin esto, el botón "Generar" sigue bloqueado hasta que la cola da el trabajo por
 * perdido (DB_QUEUE_RETRY_AFTER, ~1 h). Ver D34.
 *
 * Hace tres cosas por cada fuente atascada: borra su trabajo de la cola, libera el bloqueo de
 * trabajo único (ShouldBeUnique) y marca la fuente como "error" para poder relanzarla.
 * Relanzar es seguro: cada paso del pipeline es idempotente.
 */
final class ResetRunsCommand extends Command
{
    protected $signature = 'accesopro:reset-runs
        {code? : Código de una fuente concreta (por defecto, todas las atascadas)}';

    protected $description = 'Desbloquea fuentes atascadas en "en cola"/"procesando" (p. ej. tras quedarse sin memoria)';

    public function handle(): int
    {
        $sources = Source::query()
            ->whereIn('run_status', [Source::RUN_QUEUED, Source::RUN_PROCESSING])
            ->when($this->argument('code'), fn ($q, $code) => $q->where('code', $code))
            ->get();

        if ($sources->isEmpty()) {
            $this->info('No hay fuentes atascadas.');

            return self::SUCCESS;
        }

        $deletedJobs = $this->deleteQueuedJobs($sources->modelKeys());

        foreach ($sources as $source) {
            Cache::lock(UniqueLock::getKey(new RunSourcePipeline($source, 0)))->forceRelease();

            $source->update([
                'run_status' => Source::RUN_ERROR,
                'run_error' => 'Interrumpido: el servidor reinició el trabajo a mitad (por ejemplo, por falta de memoria). '
                    .'Pulsa «Generar» para relanzarlo; los pasos ya hechos no se repiten.',
                'run_finished_at' => now(),
            ]);

            $this->line("✔ {$source->code} desbloqueada.");
        }

        $this->info("{$sources->count()} fuente(s) desbloqueada(s); {$deletedJobs} trabajo(s) retirado(s) de la cola.");

        return self::SUCCESS;
    }

    /** @param  list<int>  $sourceIds */
    private function deleteQueuedJobs(array $sourceIds): int
    {
        if (config('queue.default') !== 'database') {
            return 0;
        }

        $table = config('queue.connections.database.table', 'jobs');
        $deleted = 0;

        DB::table($table)->where('payload', 'like', '%RunSourcePipeline%')->get()
            ->each(function (object $row) use ($sourceIds, $table, &$deleted): void {
                try {
                    $job = unserialize(json_decode($row->payload, true)['data']['command']);
                } catch (Throwable) {
                    return;
                }

                if ($job instanceof RunSourcePipeline && in_array($job->source->getKey(), $sourceIds, true)) {
                    $deleted += DB::table($table)->where('id', $row->id)->delete();
                }
            });

        return $deleted;
    }
}
