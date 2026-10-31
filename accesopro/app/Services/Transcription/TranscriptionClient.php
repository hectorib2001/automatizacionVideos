<?php

namespace App\Services\Transcription;

use App\Services\Costs\CostRecorder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Transcribe audio con Whisper vía Groq, con marcas de tiempo por palabra (necesarias para
 * detectar y cortar los momentos de la clase, ver docs/ARQUITECTURA.md). Llamada directa a
 * la API (no Prism): Prism\Groq no expone `timestamp_granularities`, que es justo lo que
 * necesitamos.
 */
class TranscriptionClient
{
    public function __construct(private readonly CostRecorder $costs) {}

    /**
     * @return array{text: string, words: list<array{word: string, start: float, end: float}>, segments: list<array{text: string, start: float, end: float}>}
     */
    public function transcribe(string $audioPath, string $language = 'es'): array
    {
        $this->costs->assertWithinBudget();

        $response = Http::baseUrl(config('accesopro.groq.base_url'))
            ->withToken(config('accesopro.groq.api_key'))
            ->timeout(600)
            ->attach('file', file_get_contents($audioPath), basename($audioPath))
            ->asMultipart()
            ->post('audio/transcriptions', [
                'model' => config('accesopro.groq.whisper_model'),
                'language' => $language,
                'response_format' => 'verbose_json',
                'timestamp_granularities[]' => 'word',
            ])
            ->throw();

        $data = $response->json();

        $durationSeconds = (float) ($data['duration'] ?? $this->probeDuration($audioPath));
        $hours = $durationSeconds / 3600;

        $this->costs->record(
            provider: 'groq',
            operation: 'whisper_transcribe',
            units: round($durationSeconds, 2),
            unitType: 'seconds',
            costUsd: $hours * (float) config('accesopro.groq.usd_per_hour'),
            meta: ['model' => config('accesopro.groq.whisper_model')],
        );

        return [
            'text' => $data['text'] ?? '',
            'words' => $data['words'] ?? [],
            'segments' => collect($data['segments'] ?? [])
                ->map(fn (array $s) => ['text' => trim($s['text']), 'start' => (float) $s['start'], 'end' => (float) $s['end']])
                ->all(),
        ];
    }

    private function probeDuration(string $audioPath): float
    {
        $result = Process::run([
            config('accesopro.ffprobe'), '-v', 'error',
            '-show_entries', 'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1',
            $audioPath,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException("No se pudo determinar la duración de {$audioPath}.");
        }

        return (float) trim($result->output());
    }
}
