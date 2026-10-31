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

/**
 * Descarga la clase grabada con yt-dlp. Si ya hay un MP4 en
 * `config('accesopro.class_video_path')` (descargado a mano o en una ejecución anterior),
 * no vuelve a descargar. Si YouTube bloquea la descarga, documenta el error y pide subir el
 * MP4 a mano (ver docs/ARQUITECTURA.md).
 */
final class FetchClassVideo implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly Source $source) {}

    public function uniqueId(): string
    {
        return "fetch-class-video-{$this->source->id}";
    }

    public function handle(): void
    {
        $path = config('accesopro.class_video_path');

        if (is_file($path)) {
            return;
        }

        File::ensureDirectoryExists(dirname($path));

        $result = Process::timeout(1800)->run([
            config('accesopro.yt_dlp_bin'),
            '-f', 'bv*[height<=1080][ext=mp4]+ba[ext=m4a]/best[ext=mp4]/best',
            '--merge-output-format', 'mp4',
            '-o', $path,
            $this->source->ref,
        ]);

        if (! $result->successful() || ! is_file($path)) {
            throw new RuntimeException(
                "No se pudo descargar la clase con yt-dlp (puede que YouTube bloquee la descarga). "
                ."Sube el vídeo manualmente a {$path} y relanza. Detalle: ".$result->errorOutput(),
            );
        }
    }
}
