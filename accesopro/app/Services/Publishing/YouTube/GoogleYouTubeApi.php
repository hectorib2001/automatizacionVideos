<?php

namespace App\Services\Publishing\YouTube;

use Google\Http\MediaFileUpload;
use Google\Service\YouTube;
use Google\Service\YouTube\Video;
use Google\Service\YouTube\VideoSnippet;
use Google\Service\YouTube\VideoStatus;
use RuntimeException;

final class GoogleYouTubeApi implements YouTubeApi
{
    /** Trozos de 8 MiB (múltiplo de 256 KiB, como exige la subida reanudable). */
    private const CHUNK_BYTES = 8 * 1024 * 1024;

    public function __construct(private readonly YouTubeAuth $auth) {}

    public function upload(string $path, array $meta): array
    {
        $client = $this->auth->client();
        $youtube = new YouTube($client);

        $snippet = new VideoSnippet;
        $snippet->setTitle($meta['title']);
        $snippet->setDescription($meta['description']);
        $snippet->setTags($meta['tags']);
        $snippet->setCategoryId($meta['category_id']);
        $snippet->setDefaultLanguage('es');
        $snippet->setDefaultAudioLanguage('es');

        $status = new VideoStatus;
        $status->setPrivacyStatus($meta['privacy']);
        $status->setSelfDeclaredMadeForKids(false);
        $status->setContainsSyntheticMedia($meta['synthetic']);

        $video = new Video;
        $video->setSnippet($snippet);
        $video->setStatus($status);

        $client->setDefer(true);
        $request = $youtube->videos->insert('snippet,status', $video);
        $media = new MediaFileUpload($client, $request, 'video/mp4', null, true, self::CHUNK_BYTES);
        $media->setFileSize(filesize($path));

        $handle = fopen($path, 'rb') ?: throw new RuntimeException("No se pudo abrir {$path}");
        $result = false;

        try {
            while ($result === false && ! feof($handle)) {
                $result = $media->nextChunk(fread($handle, self::CHUNK_BYTES));
            }
        } finally {
            fclose($handle);
            $client->setDefer(false);
        }

        if (! $result instanceof Video || ! $result->getId()) {
            throw new RuntimeException('YouTube no devolvió el id del vídeo subido.');
        }

        return ['id' => $result->getId(), 'privacy' => (string) $result->getStatus()?->getPrivacyStatus()];
    }

    public function setThumbnail(string $videoId, string $imagePath): void
    {
        $youtube = new YouTube($this->auth->client());

        $youtube->thumbnails->set($videoId, [
            'data' => file_get_contents($imagePath),
            'mimeType' => 'image/png',
            'uploadType' => 'media',
        ]);
    }
}
