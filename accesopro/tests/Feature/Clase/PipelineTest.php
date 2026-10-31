<?php

use App\Enums\PieceStatus;
use App\Jobs\Clase\CutAndRender;
use App\Jobs\Clase\DetectMoments;
use App\Jobs\Clase\ReviewClip;
use App\Models\Piece;
use App\Models\Review;
use App\Models\Source;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\ValueObjects\Usage;

function fakeTranscript(): array
{
    // Palabras de dos frases separadas, con tiempos limpios para probar el ajuste a
    // límites de frase.
    $words = [];
    $t = 0.0;
    foreach (explode(' ', 'Esto es la primera frase.') as $w) {
        $words[] = ['word' => $w, 'start' => $t, 'end' => $t + 0.4];
        $t += 0.5;
    }
    $t = 10.0;
    foreach (explode(' ', 'Y esta es la segunda frase completa.') as $w) {
        $words[] = ['word' => $w, 'start' => $t, 'end' => $t + 0.4];
        $t += 0.5;
    }

    return ['text' => '...', 'words' => $words, 'segments' => []];
}

it('detects moments, snaps them to sentence boundaries, and creates clip pieces', function () {
    $fake = Prism::fake([
        StructuredResponseFake::make()->withUsage(new Usage(500, 200))->withStructured([
            'moments' => [
                ['start_seconds' => 10.05, 'end_seconds' => 13.9, 'type' => 'explicacion_completa', 'title' => 'La segunda frase', 'why_standalone' => 'Se explica entera.'],
            ],
        ]),
    ]);

    $source = Source::create(['type' => 'clase', 'code' => 'vid123', 'ref' => 'https://youtube.com/watch?v=vid123']);
    $transcriptPath = storage_path('app/private/pruebas/transcript-test.json');
    File::ensureDirectoryExists(dirname($transcriptPath));
    file_put_contents($transcriptPath, json_encode(fakeTranscript()));
    $source->update(['meta' => ['transcript_path' => $transcriptPath]]);

    DetectMoments::dispatchSync($source);
    $source->refresh();

    $piece = Piece::where('source_id', $source->id)->first();

    expect($source->meta['moments'])->toHaveCount(1)
        ->and($piece)->not->toBeNull()
        ->and($piece->kind)->toBe('clip')
        ->and((float) $piece->clip_start)->toBe(10.0)
        ->and($piece->script['transcript'])->toContain('segunda frase completa.');

    // Idempotente: no vuelve a llamar al LLM si ya hay momentos.
    DetectMoments::dispatchSync($source);
    $fake->assertCallCount(1);

    File::delete($transcriptPath);
});

it('discards a rejected clip directly, without a correction loop', function () {
    Prism::fake([
        StructuredResponseFake::make()->withUsage(new Usage(300, 150))->withStructured([
            'verdict' => 'rechazado',
            'checks' => array_fill_keys(['matematica', 'temario', 'datos_inventados', 'pedagogia', 'formato'], ['status' => 'n/a', 'detail' => '-'])
                + ['autonomia' => ['status' => 'fail', 'detail' => 'Depende de algo dicho antes.']],
            'claims' => [],
            'reasons' => ['El clip empieza a mitad de una explicación.'],
            'fix_suggestion' => '',
        ]),
    ]);

    $piece = Piece::create([
        'source_id' => Source::create(['type' => 'clase', 'code' => 'vid123'])->id,
        'kind' => 'clip',
        'uid' => 'test-clip-reject',
        'idea_title' => 'x',
        'status' => PieceStatus::Generada,
        'clip_start' => 10,
        'clip_end' => 20,
        'script' => ['type' => 'explicacion_completa', 'title' => 'x', 'why_standalone' => 'x', 'transcript' => 'x'],
    ]);

    ReviewClip::dispatchSync($piece);
    $piece->refresh();

    expect($piece->status)->toBe(PieceStatus::Descartada)
        ->and($piece->discard_reason)->toContain('mitad de una explicación')
        ->and(Review::where('piece_id', $piece->id)->count())->toBe(1);
});

it('cuts and renders a clip in 9:16 (blurred background) and 16:9', function () {
    $dir = storage_path('app/private/pruebas/cut-and-render-test');
    File::ensureDirectoryExists($dir);

    $sourceVideo = "{$dir}/source.mp4";
    Process::run([
        config('accesopro.ffmpeg'), '-y', '-f', 'lavfi', '-i', 'testsrc=size=640x360:duration=6:rate=30',
        '-f', 'lavfi', '-i', 'anullsrc=r=44100:cl=mono', '-shortest', '-c:v', 'libx264', '-c:a', 'aac', $sourceVideo,
    ])->throw();

    config(['accesopro.class_video_path' => $sourceVideo]);

    $transcriptPath = "{$dir}/transcript.json";
    file_put_contents($transcriptPath, json_encode([
        'words' => [
            ['word' => 'Hola', 'start' => 1.0, 'end' => 1.4],
            ['word' => 'mundo.', 'start' => 1.4, 'end' => 1.8],
        ],
    ]));

    $source = Source::create(['type' => 'clase', 'code' => 'vid123', 'meta' => ['transcript_path' => $transcriptPath]]);
    $piece = Piece::create([
        'source_id' => $source->id,
        'kind' => 'clip',
        'uid' => 'test-cut-render',
        'idea_title' => 'x',
        'status' => PieceStatus::Revisada,
        'clip_start' => 1.0,
        'clip_end' => 3.0,
        'script' => ['transcript' => 'Hola mundo.'],
    ]);

    CutAndRender::dispatchSync($piece);

    $vertical = $piece->renders()->where('format', '9x16')->first();
    $horizontal = $piece->renders()->where('format', '16x9')->first();

    expect($vertical)->not->toBeNull()->and(is_file($vertical->path))->toBeTrue()
        ->and($horizontal)->not->toBeNull()->and(is_file($horizontal->path))->toBeTrue();

    $probe = Process::run([config('accesopro.ffprobe'), '-v', 'error', '-show_entries', 'stream=width,height', '-of', 'json', $vertical->path])->throw();
    $video = collect(json_decode($probe->output(), true)['streams'])->first();

    expect($video['width'])->toBe(1080)->and($video['height'])->toBe(1920);

    File::deleteDirectory($dir);
})->group('ffmpeg');
