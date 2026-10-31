<?php

namespace App\Services\Voice;

use App\Models\Piece;
use App\Services\Costs\CostRecorder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ElevenLabsClient
{
    public function __construct(private CostRecorder $costs) {}

    /** Crea una voz clonada (Instant Voice Cloning) y devuelve su voice_id. */
    public function cloneVoice(string $name, array $paths, string $description = ''): string
    {
        $request = $this->http()->asMultipart();

        foreach ($paths as $path) {
            $request = $request->attach('files', file_get_contents($path), basename($path));
        }

        $response = $request->post('/voices/add', [
            'name' => $name,
            'description' => $description,
        ])->throw();

        return $response->json('voice_id') ?? throw new RuntimeException('ElevenLabs no devolvió voice_id.');
    }

    /**
     * Sintetiza texto con marcas de tiempo por carácter (sirven para los subtítulos).
     *
     * @return array{audio: string, alignment: array, characters: int}
     */
    public function speak(string $text, ?Piece $piece = null, ?string $voiceId = null): array
    {
        $voiceId ??= config('accesopro.elevenlabs.voice_id')
            ?? throw new RuntimeException('Falta ELEVENLABS_VOICE_ID en el .env.');

        $this->costs->assertWithinBudget();

        $response = $this->http()->post(
            "/text-to-speech/{$voiceId}/with-timestamps?output_format=mp3_44100_128",
            [
                'text' => $text,
                'model_id' => config('accesopro.elevenlabs.model'),
                'voice_settings' => [
                    'stability' => 0.5,
                    'similarity_boost' => 0.8,
                    'style' => 0.0,
                    'use_speaker_boost' => true,
                ],
            ],
        )->throw();

        $chars = mb_strlen($text);

        $this->costs->record(
            provider: 'elevenlabs',
            operation: 'tts',
            units: $chars,
            unitType: 'characters',
            costUsd: $chars / 1000 * (float) config('accesopro.elevenlabs.usd_per_1k_chars'),
            piece: $piece,
            meta: ['voice_id' => $voiceId, 'model' => config('accesopro.elevenlabs.model')],
        );

        return [
            'audio' => base64_decode($response->json('audio_base64')),
            'alignment' => $response->json('alignment') ?? [],
            'characters' => $chars,
        ];
    }

    public function subscription(): array
    {
        return $this->http()->get('/user/subscription')->throw()->json();
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(config('accesopro.elevenlabs.base_url'))
            ->withHeaders(['xi-api-key' => config('accesopro.elevenlabs.api_key')])
            ->timeout(180)
            ->retry(3, 2000);
    }
}
