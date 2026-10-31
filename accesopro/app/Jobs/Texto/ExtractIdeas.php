<?php

namespace App\Jobs\Texto;

use App\Models\Source;
use App\Services\Llm\LlmGateway;
use App\Services\Llm\Schemas\IdeasSchema;
use App\Services\Materials\SourceMaterial;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Divide un tema del temario en 5-7 ideas de vídeo independientes.
 * Idempotente: si el Source ya tiene ideas guardadas, no vuelve a llamar al LLM.
 */
final class ExtractIdeas implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly Source $source) {}

    public function uniqueId(): string
    {
        return "extract-ideas-{$this->source->id}";
    }

    public function handle(LlmGateway $llm, SourceMaterial $material): void
    {
        if (! empty($this->source->meta['ideas'] ?? [])) {
            return;
        }

        $system = file_get_contents(resource_path('prompts/extract_ideas.md'));

        $prompt = "# Temario oficial\n\n{$material->officialSyllabus()}\n\n"
            ."# Estructura del tema en AccesoPro\n\n{$material->accesoProSyllabus()}\n\n"
            ."# Guion de podcast ya elaborado (fuente principal)\n\n"
            .json_encode($material->podcastScript($this->source->code), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $result = $llm->structured(
            role: 'generator',
            schema: IdeasSchema::schema(),
            system: $system,
            prompt: $prompt,
            operation: 'extract_ideas',
        );

        $this->source->update([
            'meta' => [...($this->source->meta ?? []), 'ideas' => $result['ideas'] ?? []],
        ]);
    }
}
