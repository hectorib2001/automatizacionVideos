<?php

namespace App\Services\Publishing\YouTube;

/** Llamadas a la YouTube Data API. Interfaz para poder simularla en los tests. */
interface YouTubeApi
{
    /**
     * Sube un vídeo (subida reanudable por trozos).
     *
     * @param  array{title: string, description: string, tags: list<string>, category_id: string, privacy: string, synthetic: bool}  $meta
     * @return array{id: string, privacy: string} privacy = el estado que YouTube aplicó de verdad
     */
    public function upload(string $path, array $meta): array;

    public function setThumbnail(string $videoId, string $imagePath): void;
}
