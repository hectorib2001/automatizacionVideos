<?php

use App\Jobs\Clase\ExtractAudio;
use App\Jobs\Clase\FetchClassVideo;
use App\Jobs\Clase\Transcribe;
use App\Models\Source;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->dir = storage_path('app/private/pruebas/clase-pipeline-test');
    File::ensureDirectoryExists($this->dir);
    config(['accesopro.class_video_path' => "{$this->dir}/clase.mp4"]);
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

it('does not call yt-dlp when the class video already exists', function () {
    file_put_contents(config('accesopro.class_video_path'), 'contenido falso de vídeo');
    Process::fake();

    $source = Source::create(['type' => 'clase', 'code' => 'abc123', 'ref' => 'https://youtube.com/watch?v=abc123']);
    FetchClassVideo::dispatchSync($source);

    Process::assertNothingRan();
});

it('extracts audio from the class video with ffmpeg', function () {
    file_put_contents(config('accesopro.class_video_path'), 'contenido falso de vídeo');
    Process::fake([
        '*' => Process::result(),
    ]);

    $source = Source::create(['type' => 'clase', 'code' => 'abc123', 'ref' => 'https://youtube.com/watch?v=abc123']);
    $job = new ExtractAudio($source);
    $job->handle();

    Process::assertRan(fn ($process) => str_contains($process->command[0] ?? '', 'ffmpeg') || in_array('-vn', $process->command ?? [], true));
});

it('throws when extracting audio without a class video', function () {
    $source = Source::create(['type' => 'clase', 'code' => 'abc123', 'ref' => 'https://youtube.com/watch?v=abc123']);

    expect(fn () => (new ExtractAudio($source))->handle())->toThrow(RuntimeException::class);
});

it('transcribes the audio once and stores the transcript path, not the full transcript, on the source', function () {
    Http::fake([
        '*/audio/transcriptions' => Http::response([
            'text' => 'Hola mundo.',
            'duration' => 2.0,
            'words' => [
                ['word' => 'Hola', 'start' => 0.0, 'end' => 0.5],
                ['word' => 'mundo.', 'start' => 0.5, 'end' => 1.0],
            ],
            'segments' => [],
        ]),
    ]);

    $audioPath = (new ExtractAudio(new Source))->audioPath();
    File::ensureDirectoryExists(dirname($audioPath));
    file_put_contents($audioPath, 'audio falso');

    $source = Source::create(['type' => 'clase', 'code' => 'abc123', 'ref' => 'https://youtube.com/watch?v=abc123']);
    Transcribe::dispatchSync($source);
    $source->refresh();

    expect($source->meta['transcript_words'])->toBe(2)
        ->and(is_file($source->meta['transcript_path']))->toBeTrue();

    Transcribe::dispatchSync($source);
    Http::assertSentCount(1);
});
