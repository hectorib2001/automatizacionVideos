<?php

namespace App\Jobs\Common;

use App\Models\Piece;
use App\Models\Publication;
use App\Services\Publishing\YouTubePublisher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Sube una pieza a YouTube a petición (botón "Subir a YouTube" de la tabla de control),
 * sea cual sea PUBLISH_TARGET. Va por la cola porque subir dos vídeos tarda más de lo que
 * aguanta una petición web. Un solo intento: reintentar a ciegas podría duplicar vídeos;
 * si falla, el error queda en la tabla y se relanza con el mismo botón (es idempotente).
 */
final class PublishToYouTube implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly Piece $piece) {}

    public function uniqueId(): string
    {
        return "publish-youtube-{$this->piece->id}";
    }

    public function handle(YouTubePublisher $publisher): void
    {
        $publisher->publish($this->piece);

        if (config('accesopro.publish_target') === 'youtube') {
            $this->piece->markPublished();
        }
    }

    public function failed(?Throwable $e): void
    {
        Publication::where('piece_id', $this->piece->id)
            ->where('target', 'youtube')->where('status', 'pendiente')
            ->update(['status' => 'error', 'error' => $e?->getMessage() ?? 'El trabajo falló.']);
    }
}
