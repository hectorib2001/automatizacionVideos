<?php

namespace App\Services\Publishing;

use App\Models\Piece;
use App\Models\Publication;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Sube los vídeos renderizados de una pieza a Google Drive. Idempotente por formato. */
final class DrivePublisher implements Publisher
{
    private const FORMATS = ['9x16', '16x9'];

    public function publish(Piece $piece): array
    {
        $results = [];

        foreach (self::FORMATS as $format) {
            $existing = Publication::where('piece_id', $piece->id)
                ->where('target', 'drive')->where('format', $format)->first();

            if ($existing?->status === 'publicada') {
                $results[] = ['format' => $format, 'url' => $existing->url];

                continue;
            }

            $render = $piece->renders()->where('format', $format)->first();

            if (! $render || ! is_file($render->path)) {
                continue;
            }

            try {
                $remotePath = $this->remotePath($piece, $format);
                Storage::disk('google')->put($remotePath, file_get_contents($render->path));
                $url = $this->toViewUrl(Storage::disk('google')->url($remotePath));

                Publication::updateOrCreate(
                    ['piece_id' => $piece->id, 'target' => 'drive', 'format' => $format],
                    ['external_id' => null, 'url' => $url, 'status' => 'publicada', 'error' => null],
                );

                $results[] = ['format' => $format, 'url' => $url];
            } catch (Throwable $e) {
                Publication::updateOrCreate(
                    ['piece_id' => $piece->id, 'target' => 'drive', 'format' => $format],
                    ['status' => 'error', 'error' => $e->getMessage()],
                );
            }
        }

        return $results;
    }

    private function remotePath(Piece $piece, string $format): string
    {
        $root = config('accesopro.drive_root');

        return "{$root}/{$piece->source->code}/{$piece->kind}/{$piece->uid}/video_{$format}.mp4";
    }

    /**
     * El adaptador de Drive devuelve un enlace de descarga directa (`export=media`); para
     * compartir con una persona (sin pedir permiso, ver docs/RETO.md "Confidencialidad") es
     * más cómodo el enlace de vista previa estándar de Drive.
     */
    private function toViewUrl(string $url): string
    {
        $id = parse_url($url, PHP_URL_QUERY);
        parse_str((string) $id, $query);

        if (empty($query['id'])) {
            return $url;
        }

        return "https://drive.google.com/file/d/{$query['id']}/view?usp=sharing";
    }
}
