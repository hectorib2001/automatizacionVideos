<?php

namespace App\Services\Video;

/**
 * Convierte el alineamiento por carácter de ElevenLabs (ver docs/DECISIONES.md D10) en
 * subtítulos ASS: agrupa caracteres en palabras y palabras en cues cortos (una línea,
 * pocas palabras) para que se lean cómodos en un Short.
 */
final class SubtitleBuilder
{
    private const WORDS_PER_CUE = 4;

    private const MAX_CHARS_PER_CUE = 34;

    /**
     * @param  array{characters: list<string>, character_start_times_seconds: list<float>, character_end_times_seconds: list<float>}  $alignment
     * @return list<array{text: string, start: float, end: float}>
     */
    public function buildCues(array $alignment): array
    {
        return $this->buildCuesFromWords($this->groupIntoWords($alignment));
    }

    /**
     * Igual que buildCues(), pero partiendo de palabras ya separadas con su marca de tiempo
     * (p. ej. las de Whisper/Groq para los clips de la clase, ver docs/ARQUITECTURA.md).
     *
     * @param  list<array{text: string, start: float, end: float}>  $words
     * @return list<array{text: string, start: float, end: float}>
     */
    public function buildCuesFromWords(array $words): array
    {
        $cues = [];
        $current = [];

        foreach ($words as $word) {
            $wouldBeText = trim(implode(' ', [...array_column($current, 'text'), $word['text']]));

            if ($current !== [] && (count($current) >= self::WORDS_PER_CUE || strlen($wouldBeText) > self::MAX_CHARS_PER_CUE)) {
                $cues[] = $this->flushCue($current);
                $current = [];
            }

            $current[] = $word;
        }

        if ($current !== []) {
            $cues[] = $this->flushCue($current);
        }

        return $cues;
    }

    /**
     * @param  list<array{text: string, start: float, end: float}>  $cues
     */
    public function toAss(array $cues, int $width, int $height): string
    {
        $fontSize = (int) round($width * 0.052);
        $outline = max(2, (int) round($fontSize * 0.08));
        $marginV = (int) round($height * 0.12);

        $header = <<<ASS
[Script Info]
ScriptType: v4.00+
PlayResX: {$width}
PlayResY: {$height}
WrapStyle: 2
ScaledBorderAndShadow: yes
YCbCr Matrix: None

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Default,Inter,{$fontSize},&H00FFFFFF,&H000000FF,&H00321E11,&H00000000,-1,0,0,0,100,100,0,0,1,{$outline},0,2,60,60,{$marginV},1

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
ASS;

        $lines = array_map(
            fn (array $cue) => sprintf(
                'Dialogue: 0,%s,%s,Default,,0,0,0,,%s',
                $this->formatTime($cue['start']),
                $this->formatTime($cue['end']),
                $this->escapeText($cue['text']),
            ),
            $cues,
        );

        return $header."\n".implode("\n", $lines)."\n";
    }

    /**
     * @param  array{characters: list<string>, character_start_times_seconds: list<float>, character_end_times_seconds: list<float>}  $alignment
     * @return list<array{text: string, start: float, end: float}>
     */
    private function groupIntoWords(array $alignment): array
    {
        $chars = $alignment['characters'];
        $starts = $alignment['character_start_times_seconds'];
        $ends = $alignment['character_end_times_seconds'];

        $words = [];
        $buffer = '';
        $wordStart = null;

        foreach ($chars as $i => $char) {
            if (trim($char) === '') {
                if ($buffer !== '') {
                    $words[] = ['text' => $buffer, 'start' => $wordStart, 'end' => $ends[$i - 1]];
                    $buffer = '';
                    $wordStart = null;
                }

                continue;
            }

            $buffer .= $char;
            $wordStart ??= $starts[$i];
        }

        if ($buffer !== '') {
            $words[] = ['text' => $buffer, 'start' => $wordStart, 'end' => end($ends)];
        }

        return $words;
    }

    /** @param  list<array{text: string, start: float, end: float}>  $words */
    private function flushCue(array $words): array
    {
        return [
            'text' => implode(' ', array_column($words, 'text')),
            'start' => $words[0]['start'],
            'end' => end($words)['end'],
        ];
    }

    private function formatTime(float $seconds): string
    {
        $wholeSeconds = (int) floor($seconds);
        $cs = (int) round(($seconds - $wholeSeconds) * 100);

        $h = intdiv($wholeSeconds, 3600);
        $m = intdiv($wholeSeconds % 3600, 60);
        $s = $wholeSeconds % 60;

        return sprintf('%d:%02d:%02d.%02d', $h, $m, $s, $cs);
    }

    private function escapeText(string $text): string
    {
        return str_replace(["\r\n", "\n", '{', '}'], ['\N', '\N', '(', ')'], $text);
    }
}
