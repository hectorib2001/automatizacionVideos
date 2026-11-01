<?php

namespace App\Services\Publishing\YouTube;

use App\Models\CostEvent;
use App\Models\Piece;
use App\Services\Costs\CostRecorder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Cuota diaria de la YouTube Data API (Nivel 2: "respeto de la cuota"). Subir es gratis,
 * así que cada llamada se apunta en `cost_events` con 0 € y sus unidades de cuota: la
 * misma tabla de costes sirve de contador, sin tablas nuevas.
 */
final class YouTubeQuota
{
    public function __construct(private readonly CostRecorder $costs) {}

    /** Unidades gastadas desde la medianoche del Pacífico (cuando Google reinicia la cuota). */
    public function usedToday(): int
    {
        $since = Carbon::now('America/Los_Angeles')->startOfDay()->setTimezone(config('app.timezone'));

        return (int) CostEvent::where('provider', 'youtube')
            ->where('unit_type', 'quota_units')
            ->where('created_at', '>=', $since)
            ->sum('units');
    }

    public function remaining(): int
    {
        return max(0, (int) config('accesopro.youtube.daily_quota') - $this->usedToday());
    }

    public function assertAvailable(int $units): void
    {
        if ($this->remaining() < $units) {
            throw new RuntimeException("Cuota diaria de YouTube agotada: quedan {$this->remaining()} unidades y hacen falta {$units}. Se reinicia a medianoche hora del Pacífico (9:00 en España).");
        }
    }

    public function record(string $operation, int $units, Piece $piece, array $meta = []): void
    {
        $this->costs->record('youtube', $operation, $units, 'quota_units', 0.0, $piece, $meta);
    }
}
