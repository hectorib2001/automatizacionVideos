<?php

namespace App\Jobs\Texto;

use App\Models\Piece;
use App\Models\Render;
use App\Services\Voice\ElevenLabsClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;

/**
 * Narra el guion con la voz clonada y guarda el audio y el alineamiento por carácter
 * (sirve para los subtítulos exactos, ver docs/DECISIONES.md D10). Idempotente: si ya
 * existe un Render de formato "audio" para la pieza, no vuelve a llamar a ElevenLabs.
 */
final class SynthesizeVoice implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly Piece $piece) {}

    public function uniqueId(): string
    {
        return "synthesize-voice-{$this->piece->id}";
    }

    public function handle(ElevenLabsClient $eleven): void
    {
        // Hecho = archivos en disco (no solo la fila en BD, ver D33). Si se perdieron (disco
        // efímero de Cloud) se vuelve a sintetizar: cuesta, pero sin audio no hay vídeo.
        if ($this->piece->isPublished()
            || ($this->piece->hasRenderFile('audio') && is_file($this->piece->runDir().'/alignment.json'))) {
            return;
        }

        $text = $this->narrationText($this->piece->script);
        $result = $eleven->speak($text, $this->piece);

        $dir = $this->piece->runDir();
        File::ensureDirectoryExists($dir);
        file_put_contents("{$dir}/audio.mp3", $result['audio']);
        file_put_contents("{$dir}/alignment.json", json_encode($result['alignment'], JSON_PRETTY_PRINT));

        $ends = $result['alignment']['character_end_times_seconds'] ?? [];
        $duration = $ends === [] ? 0.0 : (float) end($ends);

        Render::updateOrCreate(
            ['piece_id' => $this->piece->id, 'format' => 'audio'],
            ['path' => "{$dir}/audio.mp3", 'duration' => $duration],
        );
    }

    /** @param  array<string, mixed>  $script */
    private function narrationText(array $script): string
    {
        $hookA = collect($script['hooks'])->firstWhere('variant', 'A')['text']
            ?? ($script['hooks'][0]['text'] ?? '');

        return trim("{$hookA} {$script['explanation']} {$script['cta']}");
    }
}
