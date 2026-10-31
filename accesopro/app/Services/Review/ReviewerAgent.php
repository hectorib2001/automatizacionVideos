<?php

namespace App\Services\Review;

use App\Models\Piece;
use App\Services\Llm\LlmGateway;
use App\Services\Llm\Schemas\ReviewSchema;
use App\Services\Materials\SourceMaterial;

/**
 * Agente revisor: comprueba una pieza (guion o clip) contra el temario oficial con un
 * modelo distinto del generador, y corrige matemáticamente su veredicto con MathVerifier.
 * Ver docs/REVISOR.md.
 */
final class ReviewerAgent
{
    public function __construct(
        private readonly LlmGateway $llm,
        private readonly MathVerifier $mathVerifier,
        private readonly SourceMaterial $material,
    ) {}

    /**
     * @param  array<string, mixed>  $piece  Guion (kind=texto) o clip (kind=clip), ya en forma de array.
     * @return array{
     *     verdict: 'aprobado'|'rechazado',
     *     checks: array<string, array{status: string, detail: string, saber_oficial?: ?string}>,
     *     claims: list<array{expresion: string, resultado: string, texto_original: string, status: string, detail: string}>,
     *     reasons: list<string>,
     *     fix_suggestion: string,
     * }
     */
    public function review(array $piece, Piece $pieceModel): array
    {
        $system = file_get_contents(resource_path('prompts/review_script.md'));

        $kind = $piece['kind'] ?? 'texto';
        $prompt = "# Temario oficial\n\n{$this->material->officialSyllabus()}\n\n"
            ."# Pieza a revisar ({$kind})\n\n"
            .json_encode($piece, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $result = $this->llm->structured(
            role: 'reviewer',
            schema: ReviewSchema::schema(),
            system: $system,
            prompt: $prompt,
            piece: $pieceModel,
            operation: 'review_script',
        );

        $approx = str_contains(strtolower(json_encode($piece)), 'aproximad');
        $verifiedClaims = $this->mathVerifier->verifyAll($result['claims'] ?? [], $approx);

        $checks = $result['checks'] ?? [];
        if ($this->mathVerifier->anyFailed($verifiedClaims)) {
            $checks['matematica'] = [
                'status' => 'fail',
                'detail' => 'MathVerifier encontró al menos una discrepancia: '.$this->summarizeFailures($verifiedClaims),
            ];
        }

        $verdict = $this->allApplicableChecksPass($checks) ? 'aprobado' : 'rechazado';

        return [
            'verdict' => $verdict,
            'checks' => $checks,
            'claims' => $verifiedClaims,
            'reasons' => $verdict === 'aprobado' ? [] : ($result['reasons'] ?? []),
            'fix_suggestion' => $verdict === 'aprobado' ? '' : ($result['fix_suggestion'] ?? ''),
        ];
    }

    /** @param  array<string, array{status: string}>  $checks */
    private function allApplicableChecksPass(array $checks): bool
    {
        foreach (ReviewSchema::CHECK_NAMES as $name) {
            $status = $checks[$name]['status'] ?? 'fail';

            if ($status === 'fail') {
                return false;
            }
        }

        return true;
    }

    /** @param  list<array{status: string, expresion: string, resultado: string}>  $verifiedClaims */
    private function summarizeFailures(array $verifiedClaims): string
    {
        return collect($verifiedClaims)
            ->filter(fn (array $c) => $c['status'] === 'fail')
            ->map(fn (array $c) => "{$c['expresion']} != {$c['resultado']}")
            ->implode('; ');
    }
}
