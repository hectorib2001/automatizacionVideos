<?php

namespace App\Jobs\Texto;

use App\Enums\PieceStatus;
use App\Models\Piece;
use App\Services\Llm\LlmGateway;
use App\Services\Llm\Schemas\ScriptSchema;
use App\Services\Materials\SourceMaterial;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Corrige un guion rechazado usando los motivos de su última revisión, sin reescribirlo
 * desde cero. Deja la pieza en `corregida`, lista para que ReviewScript la revise de nuevo.
 * Ver docs/REVISOR.md ("Bucle").
 */
final class ReviseScript implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly Piece $piece) {}

    public function uniqueId(): string
    {
        return "revise-script-{$this->piece->id}-attempt-{$this->piece->attempts}";
    }

    public function handle(LlmGateway $llm, SourceMaterial $material): void
    {
        if ($this->piece->status !== PieceStatus::Rechazada) {
            return;
        }

        $review = $this->piece->reviews()->latest('id')->first();

        if (! $review) {
            return;
        }

        $system = file_get_contents(resource_path('prompts/revise_script.md'));

        $motivos = collect($review->reasons)->map(fn (string $r) => "- {$r}")->implode("\n");

        $prompt = "# Guion actual\n\n".json_encode($this->piece->script, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n\n"
            ."# Motivos exactos del rechazo (intento {$review->attempt})\n\n{$motivos}\n\n"
            ."# Temario oficial\n\n{$material->officialSyllabus()}";

        $revised = $llm->structured(
            role: 'generator',
            schema: ScriptSchema::schema(),
            system: $system,
            prompt: $prompt,
            piece: $this->piece,
            operation: 'revise_script',
        );

        $this->piece->update(['script' => $revised, 'status' => PieceStatus::Corregida]);
    }
}
