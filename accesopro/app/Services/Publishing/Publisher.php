<?php

namespace App\Services\Publishing;

use App\Models\Piece;

/**
 * Publica los vídeos renderizados de una pieza. `DrivePublisher` ahora, `YouTubePublisher`
 * al final (elegido por `PUBLISH_TARGET`, ver docs/DECISIONES.md D5).
 */
interface Publisher
{
    /** @return list<array{format: string, url: string}> Formatos publicados con éxito. */
    public function publish(Piece $piece): array;
}
