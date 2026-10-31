<?php

namespace App\Services\Transcription;

/**
 * Agrupa las palabras con marca de tiempo de Whisper en frases completas, para detectar y
 * cortar clips "ajustados a límites de frase" (ver docs/REVISOR.md).
 */
final class TranscriptSegmenter
{
    /**
     * @param  list<array{word: string, start: float, end: float}>  $words
     * @return list<array{text: string, start: float, end: float}>
     */
    public static function toSentences(array $words): array
    {
        $sentences = [];
        $buffer = [];

        foreach ($words as $word) {
            $buffer[] = $word;

            if (preg_match('/[.?!]+$/', trim($word['word']))) {
                $sentences[] = self::flush($buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $sentences[] = self::flush($buffer);
        }

        return $sentences;
    }

    /**
     * Texto real (no lo que diga un LLM) de las palabras entre dos marcas de tiempo.
     *
     * @param  list<array{word: string, start: float, end: float}>  $words
     */
    public static function textBetween(array $words, float $start, float $end): string
    {
        $slice = array_filter($words, fn (array $w) => $w['start'] >= $start - 0.05 && $w['end'] <= $end + 0.05);

        return trim(implode(' ', array_column($slice, 'word')));
    }

    /** @param  list<array{word: string, start: float, end: float}>  $buffer */
    private static function flush(array $buffer): array
    {
        return [
            'text' => trim(implode(' ', array_column($buffer, 'word'))),
            'start' => $buffer[0]['start'],
            'end' => end($buffer)['end'],
        ];
    }
}
