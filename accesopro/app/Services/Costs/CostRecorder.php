<?php

namespace App\Services\Costs;

use App\Models\CostEvent;
use App\Models\Piece;
use RuntimeException;

class CostRecorder
{
    public function record(
        string $provider,
        string $operation,
        float $units,
        string $unitType,
        float $costUsd,
        ?Piece $piece = null,
        array $meta = [],
    ): CostEvent {
        $eur = round($costUsd * (float) config('accesopro.usd_to_eur'), 6);

        $event = CostEvent::create([
            'piece_id' => $piece?->id,
            'provider' => $provider,
            'operation' => $operation,
            'units' => $units,
            'unit_type' => $unitType,
            'cost_eur' => $eur,
            'meta' => $meta,
        ]);

        $piece?->increment('cost_eur', $eur);

        return $event;
    }

    public function spentTodayEur(): float
    {
        return (float) CostEvent::whereDate('created_at', today())->sum('cost_eur');
    }

    /** Límite de gasto diario (Nivel 2): se llama antes de cada operación de pago. */
    public function assertWithinBudget(): void
    {
        $budget = (float) config('accesopro.daily_budget_eur');

        if ($this->spentTodayEur() >= $budget) {
            throw new RuntimeException("Límite de gasto diario alcanzado ({$budget} €).");
        }
    }
}
