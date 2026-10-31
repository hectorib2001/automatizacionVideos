<?php

use App\Enums\PieceStatus;
use App\Jobs\Texto\RenderVideo;
use App\Models\Piece;
use App\Models\Render;
use App\Models\Source;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

function makeFakeAlignment(string $text, float $step = 0.08): array
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

it('renders 9x16 and 16x9 videos from the script and a pre-existing audio render', function () {
    config(['accesopro.brand.logo_path' => '/ruta/que/no/existe.png']);

    $piece = Piece::create([
        'source_id' => Source::create(['type' => 'temario', 'code' => 'T1_S01'])->id,
        'kind' => 'texto',
        'uid' => 'test-render-video',
        'idea_title' => 'Sumar fracciones sin errores',
        'status' => PieceStatus::Revisada,
        'script' => [
            'idea_title' => 'Sumar fracciones sin errores',
            'hooks' => [['variant' => 'A', 'text' => 'Un medio mas un tercio']],
            'explanation' => 'son cinco sextos',
            'cta' => 'Practica con AccesoPro',
            'scenes' => [
                ['texto' => 'Un medio mas un tercio', 'formula' => null, 'duracion_seg' => 2],
                ['texto' => 'son cinco sextos', 'formula' => '1/2+1/3=5/6', 'duracion_seg' => 2],
            ],
            'claims' => [],
            'syllabus_refs' => [],
        ],
    ]);

    $dir = $piece->runDir();
    File::ensureDirectoryExists($dir);

    $audioPath = "{$dir}/audio.mp3";
    Process::run([config('accesopro.ffmpeg'), '-y', '-f', 'lavfi', '-i', 'anullsrc=r=44100:cl=mono', '-t', '4', '-q:a', '9', $audioPath])->throw();

    $alignment = makeFakeAlignment('Un medio mas un tercio son cinco sextos');
    file_put_contents("{$dir}/alignment.json", json_encode($alignment));

    Render::create(['piece_id' => $piece->id, 'format' => 'audio', 'path' => $audioPath, 'duration' => 4.0]);

    RenderVideo::dispatchSync($piece);

    $vertical = $piece->renders()->where('format', '9x16')->first();
    $horizontal = $piece->renders()->where('format', '16x9')->first();

    expect($vertical)->not->toBeNull()
        ->and($horizontal)->not->toBeNull()
        ->and(is_file($vertical->path))->toBeTrue()
        ->and(is_file($horizontal->path))->toBeTrue();

    $probe = Process::run([config('accesopro.ffprobe'), '-v', 'error', '-show_entries', 'stream=width,height', '-of', 'json', $vertical->path])->throw();
    $info = json_decode($probe->output(), true);
    $video = collect($info['streams'])->first();

    expect($video['width'])->toBe(1080)
        ->and($video['height'])->toBe(1920);

    File::deleteDirectory($dir);
})->group('ffmpeg');

it('does nothing when both formats already have a render (idempotent)', function () {
    $piece = Piece::create([
        'source_id' => Source::create(['type' => 'temario', 'code' => 'T1_S01'])->id,
        'kind' => 'texto',
        'uid' => 'test-render-video-skip',
        'idea_title' => 'x',
        'status' => PieceStatus::Revisada,
        'script' => ['scenes' => []],
    ]);

    $dir = sys_get_temp_dir().'/render-skip-'.uniqid();
    File::ensureDirectoryExists($dir);
    file_put_contents("{$dir}/a.mp4", 'x');
    file_put_contents("{$dir}/b.mp4", 'x');
    Render::create(['piece_id' => $piece->id, 'format' => '9x16', 'path' => "{$dir}/a.mp4", 'duration' => 1]);
    Render::create(['piece_id' => $piece->id, 'format' => '16x9', 'path' => "{$dir}/b.mp4", 'duration' => 1]);

    RenderVideo::dispatchSync($piece);

    expect($piece->renders()->count())->toBe(2);
    File::deleteDirectory($dir);
});

it('re-renders when the database has the render but the file is gone (ephemeral disk, D33)', function () {
    $piece = Piece::create([
        'source_id' => Source::create(['type' => 'temario', 'code' => 'T1_S01'])->id,
        'kind' => 'texto', 'uid' => 'test-render-video-lost-files', 'idea_title' => 'x',
        'status' => PieceStatus::Renderizada, 'script' => ['scenes' => []],
    ]);
    Render::create(['piece_id' => $piece->id, 'format' => '9x16', 'path' => '/borrado/a.mp4', 'duration' => 1]);
    Render::create(['piece_id' => $piece->id, 'format' => '16x9', 'path' => '/borrado/b.mp4', 'duration' => 1]);

    // Intenta renderizar de nuevo: sin audio, falla con el error claro (no lo da por hecho).
    expect(fn () => RenderVideo::dispatchSync($piece))->toThrow(RuntimeException::class, 'no tiene audio');
});

it('never regenerates anything for a published piece, even without its files', function () {
    $piece = Piece::create([
        'source_id' => Source::create(['type' => 'temario', 'code' => 'T1_S01'])->id,
        'kind' => 'texto', 'uid' => 'test-render-video-published', 'idea_title' => 'x',
        'status' => PieceStatus::Publicada, 'script' => ['scenes' => []],
    ]);
    Http::fake();

    App\Jobs\Texto\SynthesizeVoice::dispatchSync($piece);
    RenderVideo::dispatchSync($piece);
    App\Jobs\Common\GenerateThumbnail::dispatchSync($piece);

    Http::assertNothingSent(); // ni ElevenLabs ni nada de pago
    expect($piece->renders()->count())->toBe(0);
});

it('re-synthesizes the voice when the audio row exists but the file was lost (D33)', function () {
    $piece = Piece::create([
        'source_id' => Source::create(['type' => 'temario', 'code' => 'T1_S01'])->id,
        'kind' => 'texto', 'uid' => 'test-voice-lost-file', 'idea_title' => 'x',
        'status' => PieceStatus::Aprobada,
        'script' => ['hooks' => [['variant' => 'A', 'text' => 'Hola.']], 'explanation' => 'Uno.', 'cta' => 'Fin.'],
    ]);
    Render::create(['piece_id' => $piece->id, 'format' => 'audio', 'path' => '/borrado/audio.mp3', 'duration' => 1]);

    Http::fake(['*' => Http::response([
        'audio_base64' => base64_encode('mp3 falso'),
        'alignment' => makeFakeAlignment('Hola. Uno. Fin.'),
    ])]);

    App\Jobs\Texto\SynthesizeVoice::dispatchSync($piece);

    Http::assertSentCount(1);
    expect($piece->hasRenderFile('audio'))->toBeTrue()
        ->and(is_file($piece->runDir().'/alignment.json'))->toBeTrue();

    File::deleteDirectory($piece->runDir());
});

it('throws a clear error when SynthesizeVoice has not run yet', function () {
    $piece = Piece::create([
        'source_id' => Source::create(['type' => 'temario', 'code' => 'T1_S01'])->id,
        'kind' => 'texto',
        'uid' => 'test-render-video-no-audio',
        'idea_title' => 'x',
        'status' => PieceStatus::Revisada,
        'script' => ['scenes' => []],
    ]);

    expect(fn () => RenderVideo::dispatchSync($piece))->toThrow(RuntimeException::class);
});
