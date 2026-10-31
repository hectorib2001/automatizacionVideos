<?php

use App\Services\Video\FfmpegRenderer;
use App\Services\Video\SceneBuilder;
use App\Services\Video\SubtitleBuilder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->renderer = new FfmpegRenderer;
    $this->outDir = storage_path('app/private/pruebas/ffmpeg-renderer-test');
    @mkdir($this->outDir, 0777, true);
    config(['accesopro.brand.logo_path' => '/ruta/que/no/existe.png']);
});

afterEach(function () {
    if (! empty($this->outDir) && str_starts_with($this->outDir, storage_path())) {
        File::deleteDirectory($this->outDir);
    }
});

it('scales scene durations proportionally to match the real audio duration', function () {
    $scenes = [
        ['texto' => 'Uno', 'formula' => null, 'duracion_seg' => 10],
        ['texto' => 'Dos', 'formula' => null, 'duracion_seg' => 10],
    ];

    $timed = $this->renderer->scaleSceneTimings($scenes, 30.0);

    expect($timed[0]['start'])->toBe(0.0)
        ->and($timed[0]['end'])->toBe(15.0)
        ->and($timed[1]['start'])->toBe(15.0)
        ->and($timed[1]['end'])->toBe(30.0);
});

it('renders a real short video with background, scene text and burned subtitles', function () {
    // 1. Fondo de marca.
    $bg = "{$this->outDir}/bg.png";
    (new SceneBuilder)->renderBackground(640, 360, $bg);

    // 2. Un audio de prueba de 2s de silencio (sin coste: generado localmente con ffmpeg).
    $audio = "{$this->outDir}/audio.mp3";
    Process::run([config('accesopro.ffmpeg'), '-y', '-f', 'lavfi', '-i', 'anullsrc=r=44100:cl=mono', '-t', '2', '-q:a', '9', $audio])->throw();

    // 3. Subtítulos mínimos.
    $ass = "{$this->outDir}/subs.ass";
    file_put_contents($ass, (new SubtitleBuilder)->toAss([['text' => 'Hola mundo', 'start' => 0.0, 'end' => 2.0]], 640, 360));

    // 4. Escenas con tiempos ya repartidos.
    $scenes = (new FfmpegRenderer)->scaleSceneTimings([
        ['texto' => 'Primera escena de prueba', 'formula' => '1/2+1/3=5/6', 'duracion_seg' => 1],
        ['texto' => 'Segunda escena', 'formula' => null, 'duracion_seg' => 1],
    ], 2.0);

    $output = "{$this->outDir}/out.mp4";
    $this->renderer->render($bg, $audio, $scenes, $ass, 2.0, 640, 360, $output, "{$this->outDir}/work");

    expect(is_file($output))->toBeTrue()
        ->and(filesize($output))->toBeGreaterThan(0);

    $probe = Process::run([config('accesopro.ffprobe'), '-v', 'error', '-show_entries', 'stream=width,height,codec_type', '-of', 'json', $output])->throw();
    $info = json_decode($probe->output(), true);
    $video = collect($info['streams'])->firstWhere('codec_type', 'video');

    expect($video['width'])->toBe(640)
        ->and($video['height'])->toBe(360);
});
