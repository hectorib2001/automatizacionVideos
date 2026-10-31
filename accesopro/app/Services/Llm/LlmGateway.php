<?php

namespace App\Services\Llm;

use App\Models\Piece;
use App\Services\Costs\CostRecorder;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;   // En versiones antiguas de Prism: use Prism\Prism\Prism;
use Prism\Prism\Schema\ObjectSchema;

/**
 * Único punto de acceso a los LLM. Los jobs piden un "rol" (generator | reviewer)
 * y aquí se resuelve proveedor y modelo desde config/accesopro.php.
 */
class LlmGateway
{
    public function __construct(private CostRecorder $costs) {}

    public function text(string $role, string $system, string $prompt, ?Piece $piece = null, string $operation = 'text'): string
    {
        [$provider, $model] = $this->resolve($role);
        $this->costs->assertWithinBudget();

        $response = Prism::text()
            ->using($provider, $model)
            ->withSystemPrompt($system)
            ->withPrompt($prompt)
            ->withClientOptions(['timeout' => 120])
            ->withClientRetry(3, 2000)
            ->asText();

        $this->logUsage($provider, $model, $response->usage, $piece, "{$role}:{$operation}");

        return $response->text;
    }

    public function structured(string $role, ObjectSchema $schema, string $system, string $prompt, ?Piece $piece = null, string $operation = 'structured'): array
    {
        [$provider, $model] = $this->resolve($role);
        $this->costs->assertWithinBudget();

        $response = Prism::structured()
            ->using($provider, $model)
            ->withSchema($schema)
            ->withSystemPrompt($system)
            ->withPrompt($prompt)
            ->withClientOptions(['timeout' => 180])
            ->withClientRetry(3, 2000)
            ->asStructured();

        $this->logUsage($provider, $model, $response->usage, $piece, "{$role}:{$operation}");

        return $response->structured ?? [];
    }

    public function modelFor(string $role): string
    {
        return $this->resolve($role)[1];
    }

    private function resolve(string $role): array
    {
        $cfg = config("accesopro.llm.{$role}") ?? throw new \InvalidArgumentException("Rol LLM desconocido: {$role}");

        return [Provider::from($cfg['provider']), $cfg['model']];
    }

    private function logUsage(Provider $provider, string $model, $usage, ?Piece $piece, string $operation): void
    {
        $in = (int) ($usage->promptTokens ?? 0);
        $out = (int) ($usage->completionTokens ?? 0);
        [$pIn, $pOut] = config("accesopro.llm.prices_usd_per_mtok.{$model}", [0, 0]);

        $this->costs->record(
            provider: $provider->value,
            operation: $operation,
            units: $in + $out,
            unitType: 'tokens',
            costUsd: ($in * $pIn + $out * $pOut) / 1_000_000,
            piece: $piece,
            meta: ['model' => $model, 'input_tokens' => $in, 'output_tokens' => $out],
        );
    }
}
