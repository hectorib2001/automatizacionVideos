<?php

use App\Services\Transcription\TranscriptSegmenter;

it('groups words into sentences at sentence-ending punctuation', function () {
    $words = [
        ['word' => 'Hola', 'start' => 0.0, 'end' => 0.3],
        ['word' => 'mundo.', 'start' => 0.3, 'end' => 0.6],
        ['word' => '¿Cómo', 'start' => 0.6, 'end' => 0.9],
        ['word' => 'estás?', 'start' => 0.9, 'end' => 1.2],
        ['word' => 'Bien', 'start' => 1.2, 'end' => 1.4],
    ];

    $sentences = TranscriptSegmenter::toSentences($words);

    expect($sentences)->toHaveCount(3)
        ->and($sentences[0]['text'])->toBe('Hola mundo.')
        ->and($sentences[0]['start'])->toBe(0.0)
        ->and($sentences[0]['end'])->toBe(0.6)
        ->and($sentences[1]['text'])->toBe('¿Cómo estás?')
        ->and($sentences[2]['text'])->toBe('Bien');
});

it('extracts the real transcript text between two timestamps', function () {
    $words = [
        ['word' => 'Uno', 'start' => 0.0, 'end' => 0.3],
        ['word' => 'dos', 'start' => 0.3, 'end' => 0.6],
        ['word' => 'tres', 'start' => 0.6, 'end' => 0.9],
        ['word' => 'cuatro', 'start' => 0.9, 'end' => 1.2],
    ];

    expect(TranscriptSegmenter::textBetween($words, 0.3, 0.9))->toBe('dos tres');
});
