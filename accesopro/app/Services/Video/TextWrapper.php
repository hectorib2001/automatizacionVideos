<?php

namespace App\Services\Video;

/** Envuelve texto en líneas por número de caracteres (usado por FfmpegRenderer y ThumbnailMaker). */
final class TextWrapper
{
    /** @return list<string> */
    public static function wrap(string $text, int $maxCharsPerLine): array
    {
        $words = preg_split('/\s+/', trim($text));
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = trim("{$current} {$word}");

            if (strlen($candidate) > $maxCharsPerLine && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }
}
