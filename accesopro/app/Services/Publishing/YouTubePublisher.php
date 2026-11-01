<?php

namespace App\Services\Publishing;

use App\Models\Piece;
use App\Models\Publication;
use App\Services\Publishing\YouTube\YouTubeApi;
use App\Services\Publishing\YouTube\YouTubeQuota;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Sube los dos formatos de una pieza a YouTube como no listados, con título, descripción,
 * etiquetas (de GenerateMetadata) y miniatura. Idempotente por formato: un formato ya
 * publicado nunca se vuelve a subir (cada subida gasta cuota y crearía un vídeo duplicado).
 */
final class YouTubePublisher implements Publisher
{
    private const TITLE_MAX = 100;

    private const TAGS_MAX_CHARS = 450; // YouTube admite 500 en total; dejamos margen

    public function __construct(
        private readonly YouTubeApi $api,
        private readonly YouTubeQuota $quota,
        private readonly DrivePublisher $drive,
    ) {}

    public function publish(Piece $piece): array
    {
        $results = [];

        foreach (Piece::VIDEO_FORMATS as $format) {
            $existing = Publication::where('piece_id', $piece->id)
                ->where('target', 'youtube')->where('format', $format)->first();

            if ($existing?->status === 'publicada') {
                $results[] = ['format' => $format, 'url' => $existing->url];

                continue;
            }

            try {
                $results[] = ['format' => $format, 'url' => $this->publishFormat($piece, $format)];
            } catch (Throwable $e) {
                Publication::updateOrCreate(
                    ['piece_id' => $piece->id, 'target' => 'youtube', 'format' => $format],
                    ['status' => 'error', 'error' => $e->getMessage()],
                );
            }
        }

        return $results;
    }

    private function publishFormat(Piece $piece, string $format): string
    {
        [$path, $temporary] = $this->videoFile($piece, $format);

        try {
            $this->quota->assertAvailable((int) config('accesopro.youtube.insert_cost'));

            $requestedPrivacy = (string) config('accesopro.youtube.privacy');
            $video = $this->api->upload($path, $this->metadata($piece, $format, $requestedPrivacy));
        } finally {
            if ($temporary) {
                @unlink($path);
            }
        }

        $this->quota->record('videos.insert', (int) config('accesopro.youtube.insert_cost'), $piece, [
            'format' => $format, 'video_id' => $video['id'],
        ]);

        $url = $format === '9x16'
            ? "https://www.youtube.com/shorts/{$video['id']}"
            : "https://www.youtube.com/watch?v={$video['id']}";

        $warnings = [];

        // Proyectos de API sin auditar: YouTube fuerza los vídeos a privado (ver D6).
        if ($video['privacy'] !== '' && $video['privacy'] !== $requestedPrivacy) {
            $warnings[] = "YouTube lo dejó como «{$video['privacy']}» en vez de «{$requestedPrivacy}» (proyecto de API sin auditar).";
        }

        // Los Shorts no admiten miniatura personalizada por API: solo el 16:9.
        if ($format === '16x9') {
            $warnings[] = $this->uploadThumbnail($piece, $video['id']);
        }

        // Guardamos la publicación justo después de subir: aunque algo falle luego, relanzar
        // no vuelve a subir el vídeo.
        Publication::updateOrCreate(
            ['piece_id' => $piece->id, 'target' => 'youtube', 'format' => $format],
            [
                'external_id' => $video['id'],
                'url' => $url,
                'status' => 'publicada',
                'error' => implode(' ', array_filter($warnings)) ?: null, // avisos no bloqueantes
            ],
        );

        return $url;
    }

    /** Devuelve un aviso si la miniatura no se pudo poner (no bloquea la publicación). */
    private function uploadThumbnail(Piece $piece, string $videoId): ?string
    {
        if (! $piece->hasRenderFile('thumb')) {
            return 'Sin miniatura: el archivo ya no está en disco.';
        }

        try {
            $this->quota->assertAvailable((int) config('accesopro.youtube.thumbnail_cost'));
            $this->api->setThumbnail($videoId, $piece->renders()->where('format', 'thumb')->value('path'));
            $this->quota->record('thumbnails.set', (int) config('accesopro.youtube.thumbnail_cost'), $piece, [
                'video_id' => $videoId,
            ]);

            return null;
        } catch (Throwable $e) {
            // Típico: el canal no está verificado por teléfono y no admite miniaturas propias.
            // La API de Google pone el cuerpo JSON en el mensaje: nos quedamos con el texto legible.
            $message = $e instanceof GoogleServiceException
                ? ($e->getErrors()[0]['message'] ?? $e->getMessage())
                : $e->getMessage();

            return 'Miniatura no aplicada: '.$message;
        }
    }

    /**
     * Archivo local del render; si el disco efímero de Cloud ya lo perdió, se descarga la
     * copia publicada en Drive (D33). @return array{0: string, 1: bool} [ruta, es temporal]
     */
    private function videoFile(Piece $piece, string $format): array
    {
        if ($piece->hasRenderFile($format)) {
            return [$piece->renders()->where('format', $format)->value('path'), false];
        }

        $onDrive = $piece->publications()->where('target', 'drive')
            ->where('format', $format)->where('status', 'publicada')->exists();

        if (! $onDrive) {
            throw new RuntimeException("No hay vídeo {$format}: ni el archivo renderizado ni una copia en Drive.");
        }

        $tmp = storage_path("app/private/tmp/youtube_{$piece->uid}_{$format}.mp4");
        @mkdir(dirname($tmp), 0775, true);

        $in = Storage::disk('google')->readStream($this->drive->remotePath($piece, $format));
        $out = fopen($tmp, 'wb');
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        return [$tmp, true];
    }

    /** @return array{title: string, description: string, tags: list<string>, category_id: string, privacy: string, synthetic: bool} */
    private function metadata(Piece $piece, string $format, string $privacy): array
    {
        $meta = $piece->script['metadata'] ?? [];

        // YouTube rechaza "<" y ">" en título y descripción.
        $clean = fn (string $text): string => trim(str_replace(['<', '>'], '', $text));

        $title = $clean($meta['title'] ?? $piece->idea_title);
        $description = $clean($meta['description'] ?? '');

        if ($piece->kind === 'clip') {
            $description .= "\n\nFragmento de una clase en directo de AccesoPro.";
        }

        if ($format === '9x16') {
            $description .= "\n\n#Shorts";
        }

        $tags = [];
        $length = 0;

        foreach ($meta['tags'] ?? [] as $tag) {
            $tag = $clean((string) $tag);
            $length += mb_strlen($tag) + 1;

            if ($tag === '' || $length > self::TAGS_MAX_CHARS) {
                continue;
            }

            $tags[] = $tag;
        }

        return [
            'title' => Str::limit($title, self::TITLE_MAX - 1, '…'),
            'description' => trim($description),
            'tags' => $tags,
            'category_id' => (string) config('accesopro.youtube.category_id'),
            'privacy' => $privacy,
            // Los vídeos de texto llevan voz clonada: YouTube pide declararlo como contenido sintético.
            'synthetic' => $piece->kind === 'texto',
        ];
    }
}
