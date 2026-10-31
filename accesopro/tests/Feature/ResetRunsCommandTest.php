<?php

use App\Jobs\Common\RunSourcePipeline;
use App\Models\Source;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => config(['queue.default' => 'database']));

it('unblocks a source killed mid-run: removes its job, releases the unique lock and marks it as error', function () {
    $stuck = Source::create(['type' => 'temario', 'code' => 'T1_S01', 'title' => 'x', 'run_status' => Source::RUN_PROCESSING]);
    $other = Source::create(['type' => 'temario', 'code' => 'T1_S02', 'title' => 'y', 'run_status' => Source::RUN_DONE]);

    // Estado tras un OOM: el trabajo sigue en la tabla `jobs` y el bloqueo único sigue tomado.
    RunSourcePipeline::dispatch($stuck, 1);
    $lockKey = UniqueLock::getKey(new RunSourcePipeline($stuck, 0));
    expect(DB::table('jobs')->count())->toBe(1)
        ->and(Cache::lock($lockKey, 10)->get())->toBeFalse(); // tomado por el dispatch

    $this->artisan('accesopro:reset-runs')->expectsOutputToContain('1 fuente(s) desbloqueada(s); 1 trabajo(s)')->assertSuccessful();

    expect(DB::table('jobs')->count())->toBe(0)
        ->and($stuck->fresh()->run_status)->toBe(Source::RUN_ERROR)
        ->and($stuck->fresh()->run_error)->toContain('Pulsa «Generar»')
        ->and($other->fresh()->run_status)->toBe(Source::RUN_DONE);

    // El bloqueo está libre: un nuevo "Generar" sí se encola.
    RunSourcePipeline::dispatch($stuck->fresh(), 1);
    expect(DB::table('jobs')->count())->toBe(1);
});

it('does nothing when no source is stuck', function () {
    Source::create(['type' => 'temario', 'code' => 'T1_S01', 'title' => 'x', 'run_status' => Source::RUN_DONE]);

    $this->artisan('accesopro:reset-runs')->expectsOutputToContain('No hay fuentes atascadas')->assertSuccessful();
});
