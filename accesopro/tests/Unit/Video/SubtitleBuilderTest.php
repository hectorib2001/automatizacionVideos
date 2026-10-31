<?php

use App\Services\Video\SubtitleBuilder;

function makeAlignment(string $text, float $step = 0.1): array
{
    $chars = mb_str_split($text);
    $starts = [];
    $ends = [];
    $t = 0.0;

    foreach ($chars as $char) {
        $starts[] = $t;
        $t += $step;
        $ends[] = $t;
    }

    return [
        'characters' => $chars,
        'character_start_times_seconds' => $starts,
        'character_end_times_seconds' => $ends,
    ];
}

beforeEach(function () {
    $this->builder = new SubtitleBuilder;
});

it('groups words into cues of at most 4 words, breaking at word boundaries', function () {
    $alignment = makeAlignment('Uno dos tres cuatro cinco seis siete');

    $cues = $this->builder->buildCues($alignment);

    expect($cues)->toHaveCount(2)
        ->and($cues[0]['text'])->toBe('Uno dos tres cuatro')
        ->and($cues[1]['text'])->toBe('cinco seis siete');
});

it('keeps cue timings monotonic and non-overlapping', function () {
    $alignment = makeAlignment('Uno dos tres cuatro cinco seis siete');

    $cues = $this->builder->buildCues($alignment);

    expect($cues[0]['start'])->toBe(0.0)
        ->and($cues[0]['end'])->toBeLessThanOrEqual($cues[1]['start'])
        ->and($cues[1]['end'])->toBeGreaterThan($cues[1]['start']);
});

it('breaks a cue early when the accumulated text would exceed the character limit', function () {
    // Palabras largas: aunque caben menos de 4, debe cortar antes de pasarse de 34 caracteres.
    $alignment = makeAlignment('palabralarga otrapalabralarga masdepalabras cuartapalabra');

    $cues = $this->builder->buildCues($alignment);

    foreach ($cues as $cue) {
        expect(strlen($cue['text']))->toBeLessThanOrEqual(34 + 20); // margen: la última palabra puede pasarse del corte
    }
    expect(count($cues))->toBeGreaterThan(1);
});

it('renders valid ASS with one Dialogue line per cue and the given resolution', function () {
    $alignment = makeAlignment('Uno dos tres cuatro cinco seis siete');
    $cues = $this->builder->buildCues($alignment);

    $ass = $this->builder->toAss($cues, 1080, 1920);

    expect($ass)->toContain('PlayResX: 1080')
        ->toContain('PlayResY: 1920')
        ->toContain('Dialogue: 0,0:00:00.00,')
        ->toContain('Uno dos tres cuatro')
        ->toContain('cinco seis siete');

    expect(substr_count($ass, 'Dialogue:'))->toBe(count($cues));
});
