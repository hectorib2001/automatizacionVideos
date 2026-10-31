<?php

namespace App\Jobs\Common;

use App\Models\Piece;
use App\Models\Render;
use App\Services\Video\ThumbnailMaker;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Genera la miniatura de la pieza (1280×720). Idempotente. */
final class GenerateThumbnail implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly Piece $piece) {}

    public function uniqueId(): string
    {
        return "generate-thumbnail-{$this->piece->id}";
    }

    public function handle(ThumbnailMaker $thumbnailMaker): void
    {
        if ($this->piece->renders()->where('format', 'thumb')->exists()) {
            return;
        }

        $title = $this->piece->script['metadata']['title'] ?? $this->piece->idea_title;
        $output = "{$this->piece->runDir()}/thumbnail.png";

        $thumbnailMaker->make($title, $output, "{$this->piece->runDir()}/work_thumb");

        Render::updateOrCreate(
            ['piece_id' => $this->piece->id, 'format' => 'thumb'],
            ['path' => $output],
        );
    }
}
