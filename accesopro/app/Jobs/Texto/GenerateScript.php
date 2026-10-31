<?php

namespace App\Jobs\Texto;

use App\Enums\PieceStatus;
use App\Models\Piece;
use App\Models\Source;
use App\Services\Llm\LlmGateway;
use App\Services\Llm\Schemas\ScriptSchema;
use App\Services\Materials\SourceMaterial;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Genera el guion (30-60s) de una idea concreta. Idempotente: si la pieza ya tiene
 * guion guardado, no vuelve a llamar al LLM (evita duplicar coste al relanzar).
 *
 * @phpstan-type Idea array{idea_key: string, idea_title: string, resumen: string, saberes: list<string>}
 */
final class GenerateScript implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    /** @param  array{idea_key: string, idea_title: string, resumen: string, saberes: list<string>}  $idea */
    public function __construct(
        public readonly Source $source,
        public readonly array $idea,
    ) {}

    public function uniqueId(): string
    {
        return 'generate-script-'.Piece::makeUid('texto', $this->source->code, $this->idea['idea_key']);
    }

    public function handle(LlmGateway $llm, SourceMaterial $material): void
    {
        $piece = Piece::firstOrCreate(
            ['uid' => Piece::makeUid('texto', $this->source->code, $this->idea['idea_key'])],
            [
                'source_id' => $this->source->id,
                'kind' => 'texto',
                'idea_title' => $this->idea['idea_title'],
                'status' => PieceStatus::Generada,
            ],
        );

        if ($piece->script !== null) {
            return;
        }

        $system = file_get_contents(resource_path('prompts/generate_script.md'));

        $prompt = "# Idea a desarrollar\n\n".json_encode($this->idea, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n\n"
            ."# Temario oficial\n\n{$material->officialSyllabus()}\n\n"
            .$material->scriptContext($this->source);

        $script = $llm->structured(
            role: 'generator',
            schema: ScriptSchema::schema(),
            system: $system,
            prompt: $prompt,
            piece: $piece,
            operation: 'generate_script',
        );

        $piece->update(['script' => $script, 'status' => PieceStatus::Generada]);
    }
}
