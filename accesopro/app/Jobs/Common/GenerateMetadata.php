<?php

namespace App\Jobs\Common;

use App\Models\Piece;
use App\Services\Llm\LlmGateway;
use App\Services\Llm\Schemas\MetadataSchema;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Genera título, descripción y etiquetas para publicar el vídeo. Se guarda dentro de
 * `script.metadata` (no hay columna propia en `pieces`). Idempotente.
 */
final class GenerateMetadata implements ShouldQueue, ShouldBeUnique
{
    public int $tries = 3;

    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(public readonly Piece $piece) {}

    public function uniqueId(): string
    {
        return "generate-metadata-{$this->piece->id}";
    }

    public function handle(LlmGateway $llm): void
    {
        if (! empty($this->piece->script['metadata'] ?? null)) {
            return;
        }

        $system = file_get_contents(resource_path('prompts/generate_metadata.md'));
        $prompt = "# Guion aprobado\n\n".json_encode($this->piece->script, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $metadata = $llm->structured(
            role: 'generator',
            schema: MetadataSchema::schema(),
            system: $system,
            prompt: $prompt,
            piece: $this->piece,
            operation: 'generate_metadata',
        );

        $this->piece->update(['script' => [...$this->piece->script, 'metadata' => $metadata]]);
    }
}
