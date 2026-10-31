<?php

namespace App\Jobs\Clase;

use App\Enums\PieceStatus;
use App\Models\Piece;
use App\Models\Source;
use App\Services\Llm\LlmGateway;
use App\Services\Llm\Schemas\MomentsSchema;
use App\Services\Materials\SourceMaterial;
use App\Services\Transcription\TranscriptSegmenter;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * Detecta 5-8 momentos con valor propio en la transcripción de la clase (criterio
 * pedagógico, no de duración, ver docs/REVISOR.md) y crea una Piece (kind=clip) por cada
 * uno. Los tiempos que propone el LLM se ajustan a los límites de frase reales de la
 * transcripción, y el texto del clip se extrae de la transcripción, no de lo que "recuerde"
 * el LLM. Idempotente.
 */
final class DetectMoments implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly Source $source) {}

    public function uniqueId(): string
    {
        return "detect-moments-{$this->source->id}";
    }

    public function handle(LlmGateway $llm, SourceMaterial $material): void
    {
        if (! empty($this->source->meta['moments'] ?? [])) {
            return;
        }

        $transcriptPath = $this->source->meta['transcript_path'] ?? null;

        if (! $transcriptPath || ! is_file($transcriptPath)) {
            throw new RuntimeException("No hay transcripción para la fuente {$this->source->code}: ejecuta Transcribe primero.");
        }

        $transcript = json_decode(file_get_contents($transcriptPath), true);
        $words = $transcript['words'];
        $sentences = TranscriptSegmenter::toSentences($words);

        $system = file_get_contents(resource_path('prompts/detect_moments.md'));
        $prompt = "# Temario oficial\n\n{$material->officialSyllabus()}\n\n"
            ."# Transcripción de la clase, por frases\n\n"
            .collect($sentences)->map(fn (array $s) => sprintf('[%.2f - %.2f] %s', $s['start'], $s['end'], $s['text']))->implode("\n");

        $result = $llm->structured(
            role: 'generator',
            schema: MomentsSchema::schema(),
            system: $system,
            prompt: $prompt,
            operation: 'detect_moments',
        );

        $moments = collect($result['moments'] ?? [])
            ->map(fn (array $m) => $this->snapToSentences($m, $sentences))
            ->values()
            ->all();

        foreach ($moments as $moment) {
            $this->createPiece($moment, $words);
        }

        $this->source->update(['meta' => [...($this->source->meta ?? []), 'moments' => $moments]]);
    }

    /**
     * @param  array{start_seconds: float, end_seconds: float, type: string, title: string, why_standalone: string}  $moment
     * @param  list<array{text: string, start: float, end: float}>  $sentences
     */
    private function snapToSentences(array $moment, array $sentences): array
    {
        $start = collect($sentences)->sortBy(fn (array $s) => abs($s['start'] - $moment['start_seconds']))->first()['start'] ?? $moment['start_seconds'];
        $end = collect($sentences)->sortBy(fn (array $s) => abs($s['end'] - $moment['end_seconds']))->first()['end'] ?? $moment['end_seconds'];

        return [...$moment, 'start_seconds' => $start, 'end_seconds' => $end];
    }

    /** @param  list<array{word: string, start: float, end: float}>  $words */
    private function createPiece(array $moment, array $words): void
    {
        $key = sprintf('%s_%d', $moment['type'], (int) round($moment['start_seconds']));
        $uid = Piece::makeUid('clip', $this->source->code, $key);

        Piece::firstOrCreate(
            ['uid' => $uid],
            [
                'source_id' => $this->source->id,
                'kind' => 'clip',
                'idea_title' => $moment['title'],
                'status' => PieceStatus::Generada,
                'clip_start' => $moment['start_seconds'],
                'clip_end' => $moment['end_seconds'],
                'script' => [
                    'type' => $moment['type'],
                    'title' => $moment['title'],
                    'why_standalone' => $moment['why_standalone'],
                    'start' => $moment['start_seconds'],
                    'end' => $moment['end_seconds'],
                    'transcript' => TranscriptSegmenter::textBetween($words, $moment['start_seconds'], $moment['end_seconds']),
                ],
            ],
        );
    }
}
