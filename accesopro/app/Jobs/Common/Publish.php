<?php

namespace App\Jobs\Common;

use App\Models\Piece;
use App\Services\Publishing\Publisher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Publica los vídeos renderizados de una pieza con el Publisher activo (ver PUBLISH_TARGET). */
final class Publish implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly Piece $piece) {}

    public function uniqueId(): string
    {
        return "publish-{$this->piece->id}";
    }

    public function handle(Publisher $publisher): void
    {
        $publisher->publish($this->piece);
        $this->piece->markPublished();
    }
}
