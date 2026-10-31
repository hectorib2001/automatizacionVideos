<?php

use App\Enums\PieceStatus;
use App\Jobs\Common\GenerateMetadata;
use App\Jobs\Common\GenerateThumbnail;
use App\Jobs\Common\Publish;
use App\Models\Piece;
use App\Models\Publication;
use App\Models\Source;
use App\Services\Publishing\DrivePublisher;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\ValueObjects\Usage;

function makeScript(): array
{
    return [
        'idea_title' => 'Sumar fracciones sin errores',
        'hooks' => [['variant' => 'A', 'text' => 'Un medio mas un tercio']],
        'explanation' => 'son cinco sextos',
        'cta' => 'Practica con AccesoPro',
        'scenes' => [['texto' => 'Un medio mas un tercio', 'formula' => null, 'duracion_seg' => 4]],
        'claims' => [],
        'syllabus_refs' => [],
    ];
}

it('generates metadata once and stores it in script.metadata', function () {
    $fake = Prism::fake([
        StructuredResponseFake::make()->withUsage(new Usage(200, 100))->withStructured([
            'title' => 'Suma de fracciones: el truco que te salva en el examen',
            'description' => 'Aprende a sumar fracciones sin errores. Contenido de AccesoPro para tu prueba de acceso.',
            'tags' => ['matematicas', 'fracciones', 'prueba de acceso', 'grado superior', 'examen', 'suma'],
        ]),
    ]);

    $piece = Piece::create([
        'source_id' => Source::create(['type' => 'temario', 'code' => 'T1_S01'])->id,
        'kind' => 'texto',
        'uid' => 'test-metadata',
        'idea_title' => 'Sumar fracciones sin errores',
        'status' => PieceStatus::Revisada,
        'script' => makeScript(),
    ]);

    GenerateMetadata::dispatchSync($piece);
    $piece->refresh();

    expect($piece->script['metadata']['title'])->toContain('fracciones')
        ->and($piece->script['metadata']['tags'])->toHaveCount(6);

    GenerateMetadata::dispatchSync($piece);
    $fake->assertCallCount(1);
});

it('generates a thumbnail render once', function () {
    config(['accesopro.brand.logo_path' => '/ruta/que/no/existe.png']);

    $piece = Piece::create([
        'source_id' => Source::create(['type' => 'temario', 'code' => 'T1_S01'])->id,
        'kind' => 'texto',
        'uid' => 'test-thumbnail',
        'idea_title' => 'Sumar fracciones sin errores',
        'status' => PieceStatus::Revisada,
        'script' => makeScript(),
    ]);

    GenerateThumbnail::dispatchSync($piece);
    $piece->refresh();

    $render = $piece->renders()->where('format', 'thumb')->first();

    expect($render)->not->toBeNull()
        ->and(is_file($render->path))->toBeTrue();

    File::deleteDirectory($piece->runDir());
});

it('publishes available video renders to Drive and records the publications', function () {
    Storage::fake('google');

    $piece = Piece::create([
        'source_id' => Source::create(['type' => 'temario', 'code' => 'T1_S01'])->id,
        'kind' => 'texto',
        'uid' => 'test-publish',
        'idea_title' => 'Sumar fracciones sin errores',
        'status' => PieceStatus::Revisada,
        'script' => makeScript(),
    ]);

    $dir = storage_path('app/private/pruebas/publish-test');
    File::ensureDirectoryExists($dir);
    file_put_contents("{$dir}/9x16.mp4", 'contenido falso de vídeo vertical');
    file_put_contents("{$dir}/16x9.mp4", 'contenido falso de vídeo horizontal');

    $piece->renders()->create(['format' => '9x16', 'path' => "{$dir}/9x16.mp4", 'duration' => 10]);
    $piece->renders()->create(['format' => '16x9', 'path' => "{$dir}/16x9.mp4", 'duration' => 10]);

    Publish::dispatchSync($piece);

    $publications = Publication::where('piece_id', $piece->id)->get();

    expect($publications)->toHaveCount(2)
        ->and($publications->pluck('status')->unique()->all())->toBe(['publicada']);

    Storage::disk('google')->assertExists("AccesoPro/T1_S01/texto/{$piece->uid}/video_9x16.mp4");

    File::deleteDirectory($dir);
});

it('does not re-upload a format that is already published (idempotent)', function () {
    Storage::fake('google');

    $piece = Piece::create([
        'source_id' => Source::create(['type' => 'temario', 'code' => 'T1_S01'])->id,
        'kind' => 'texto',
        'uid' => 'test-publish-idempotent',
        'idea_title' => 'x',
        'status' => PieceStatus::Revisada,
        'script' => makeScript(),
    ]);

    Publication::create([
        'piece_id' => $piece->id, 'target' => 'drive', 'format' => '9x16',
        'url' => 'https://drive.google.com/already-there', 'status' => 'publicada',
    ]);

    $results = (new DrivePublisher)->publish($piece);

    expect(collect($results)->firstWhere('format', '9x16')['url'])->toBe('https://drive.google.com/already-there');
});
