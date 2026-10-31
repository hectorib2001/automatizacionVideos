<?php

namespace App\Jobs\Clase;

use App\Models\Source;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/** Extrae el audio de la clase (mono, 16kHz, comprimido) para transcribirlo. */
final class ExtractAudio implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly Source $source) {}

    public function uniqueId(): string
    {
        return "extract-audio-{$this->source->id}";
    }

    public function handle(): void
    {
        $audioPath = $this->audioPath();

        if (is_file($audioPath)) {
            return;
        }

        $videoPath = $this->source->classVideoPath();

        if (! is_file($videoPath)) {
            throw new RuntimeException("No existe el vídeo de la clase en {$videoPath}: ejecuta FetchClassVideo primero.");
        }

        File::ensureDirectoryExists(dirname($audioPath));

        // 32k en vez de 64k: Groq rechaza (413) archivos de más de ~25 MB en el plan
        // gratuito. Para voz hablada, 32kbps mono a 16kHz sigue siendo suficiente para Whisper.
        $result = Process::timeout(300)->run([
            config('accesopro.ffmpeg'), '-y',
            '-i', $videoPath,
            '-vn', '-ar', '16000', '-ac', '1', '-b:a', '32k',
            $audioPath,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException("FFmpeg falló al extraer el audio: ".$result->errorOutput());
        }
    }

    public function audioPath(): string
    {
        return dirname($this->source->classVideoPath()).'/audio.mp3';
    }
}
