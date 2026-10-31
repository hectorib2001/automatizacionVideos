<?php

namespace App\Jobs\Clase;

use App\Models\Source;
use App\Services\Transcription\TranscriptionClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * Transcribe el audio de la clase con marcas de tiempo por palabra (Groq Whisper). Guarda
 * la transcripción en un JSON junto al audio y solo la ruta en `source.meta` (puede ser
 * grande). Idempotente.
 */
final class Transcribe implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly Source $source) {}

    public function uniqueId(): string
    {
        return "transcribe-{$this->source->id}";
    }

    public function handle(TranscriptionClient $client): void
    {
        // Hecho = el JSON sigue en disco (D33): en Cloud un redeploy lo borra aunque la ruta
        // siga en meta, y DetectMoments/CutAndRender lo necesitan.
        $existing = $this->source->meta['transcript_path'] ?? null;

        if ($existing !== null && is_file($existing)) {
            return;
        }

        $audioPath = (new ExtractAudio($this->source))->audioPath();

        if (! is_file($audioPath)) {
            throw new RuntimeException("No existe el audio de la clase en {$audioPath}: ejecuta ExtractAudio primero.");
        }

        $result = $client->transcribe($audioPath);
        $transcriptPath = dirname($audioPath).'/transcript.json';
        file_put_contents($transcriptPath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->source->update([
            'meta' => [
                ...($this->source->meta ?? []),
                'transcript_path' => $transcriptPath,
                'transcript_words' => count($result['words']),
            ],
        ]);
    }
}
