<?php

namespace App\Jobs\Texto;

use App\Enums\PieceStatus;
use App\Models\Piece;
use App\Models\Review;
use App\Services\Llm\LlmGateway;
use App\Services\Review\ReviewerAgent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Revisa un guion contra el temario oficial. Máximo 2 correcciones (ver docs/REVISOR.md):
 * al tercer intento fallido, la pieza se descarta con el motivo en discard_reason.
 * Idempotente: una pieza ya resuelta (aprobada en adelante, o descartada) no se revisa de nuevo.
 */
final class ReviewScript implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    private const MAX_ATTEMPTS = 3;

    public int $tries = 3;

    public function __construct(public readonly Piece $piece) {}

    public function uniqueId(): string
    {
        return "review-script-{$this->piece->id}-attempt-{$this->piece->attempts}";
    }

    public function handle(ReviewerAgent $agent, LlmGateway $llm): void
    {
        if ($this->isAlreadyResolved()) {
            return;
        }

        $result = $agent->review([...$this->piece->script, 'kind' => 'texto'], $this->piece);

        Review::create([
            'piece_id' => $this->piece->id,
            'attempt' => $this->piece->attempts + 1,
            'model' => $llm->modelFor('reviewer'),
            'verdict' => $result['verdict'],
            'reasons' => $result['reasons'],
            'checks' => $result['checks'],
        ]);

        $this->piece->increment('attempts');

        if ($result['verdict'] === 'aprobado') {
            $this->piece->update(['status' => PieceStatus::Revisada]);

            return;
        }

        if ($this->piece->attempts >= self::MAX_ATTEMPTS) {
            $this->piece->update([
                'status' => PieceStatus::Descartada,
                'discard_reason' => implode('; ', $result['reasons']) ?: 'Rechazada sin motivo detallado.',
            ]);

            return;
        }

        $this->piece->update(['status' => PieceStatus::Rechazada]);
        // El siguiente paso de Fase 1 es ReviseScript, que usará reasons/fix_suggestion
        // de este Review para corregir el guion y volver a encolar ReviewScript.
    }

    private function isAlreadyResolved(): bool
    {
        // Revisada solo se alcanza con verdict=aprobado (ver handle()): ya tiene veredicto
        // y no hace falta revisar de nuevo, aunque todavía no haya pasado a Aprobada.
        return in_array($this->piece->status, [
            PieceStatus::Revisada,
            PieceStatus::Aprobada,
            PieceStatus::Renderizada,
            PieceStatus::Publicada,
            PieceStatus::Descartada,
        ], true);
    }
}
