<?php

namespace App\Jobs\Clase;

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
 * Revisa un clip contra el temario oficial. A diferencia de un guion, un clip no se puede
 * corregir ("es lo que dijo el profesor", ver docs/REVISOR.md): si se rechaza, se descarta
 * directamente con el motivo registrado. Idempotente.
 */
final class ReviewClip implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly Piece $piece) {}

    public function uniqueId(): string
    {
        return "review-clip-{$this->piece->id}";
    }

    public function handle(ReviewerAgent $agent, LlmGateway $llm): void
    {
        if ($this->isAlreadyResolved()) {
            return;
        }

        $result = $agent->review([...$this->piece->script, 'kind' => 'clip'], $this->piece);

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

        $this->piece->update([
            'status' => PieceStatus::Descartada,
            'discard_reason' => implode('; ', $result['reasons']) ?: 'Rechazado sin motivo detallado.',
        ]);
    }

    private function isAlreadyResolved(): bool
    {
        return in_array($this->piece->status, [
            PieceStatus::Revisada,
            PieceStatus::Aprobada,
            PieceStatus::Renderizada,
            PieceStatus::Publicada,
            PieceStatus::Descartada,
        ], true);
    }
}
