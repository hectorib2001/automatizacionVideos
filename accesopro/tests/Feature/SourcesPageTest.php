<?php

use App\Jobs\Common\RunSourcePipeline;
use App\Models\Source;
use App\Models\User;
use App\Services\Materials\SourceMaterial;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

/** PDF mínimo válido con texto seleccionable (una línea por elemento de $lines). */
function makePdf(array $lines): string
{
    $stream = "BT /F1 11 Tf 14 TL 50 800 Td\n";
    foreach ($lines as $line) {
        $stream .= '('.addcslashes($line, '()\\').") Tj T*\n";
    }
    $stream .= 'ET';

    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
        '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $i => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
}

function pdfUpload(array $lines, string $name = 'tema.pdf'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($path, makePdf($lines));

    return new UploadedFile($path, $name, 'application/pdf', null, true);
}

function temaLines(): array
{
    return array_fill(0, 12, 'Las potencias de exponente negativo: a elevado a menos n es uno entre a elevado a n.');
}

beforeEach(function () {
    $this->user = User::factory()->create();
});

afterEach(function () {
    File::deleteDirectory(storage_path('app/private/sources/temario/TEST_PDF'));
    File::deleteDirectory(storage_path('app/private/sources/clase/dQw4w9WgXcQ'));
});

it('requires login', function () {
    $this->get(route('sources.index'))->assertRedirect(route('login'));
});

it('shows the scope note and the existing sources', function () {
    Source::create(['type' => 'temario', 'code' => 'T1_S01', 'title' => 'Números reales']);

    $this->actingAs($this->user)->get(route('sources.index'))
        ->assertOk()
        ->assertSee('solo Matemáticas, prueba de acceso a Grado Superior, Comunitat Valenciana')
        ->assertSee('Números reales')
        ->assertSee('material original (materiales/)');
});

it('uploads a pdf topic and stores its extracted text for the pipeline', function () {
    $this->actingAs($this->user)->post(route('sources.documents.store'), [
        'code' => 'TEST_PDF',
        'title' => 'Potencias',
        'document' => pdfUpload(temaLines()),
    ])->assertRedirect(route('sources.index'))->assertSessionHas('status');

    $source = Source::where('type', 'temario')->where('code', 'TEST_PDF')->firstOrFail();

    expect($source->isUploaded())->toBeTrue()
        ->and($source->ref)->toBe('tema.pdf')
        ->and($source->documentText())->toContain('exponente negativo')
        // Nada en disco: en Cloud el worker no vería los archivos de la web.
        ->and(is_dir($source->storageDir()))->toBeFalse();

    // El pipeline usa el texto del PDF, no el material original de materiales/.
    expect(app(SourceMaterial::class)->ideasContext($source))->toContain('Documento del tema', 'exponente negativo')
        ->and(app(SourceMaterial::class)->scriptContext($source))->toContain('exponente negativo');
});

it('rejects a pdf with almost no text (scanned)', function () {
    $this->actingAs($this->user)->post(route('sources.documents.store'), [
        'code' => 'TEST_PDF', 'title' => 'Escaneado', 'document' => pdfUpload(['Hola']),
    ])->assertSessionHasErrors('document');

    expect(Source::where('code', 'TEST_PDF')->exists())->toBeFalse();
});

it('rejects non pdf files, invalid codes and duplicated codes', function () {
    Source::create(['type' => 'temario', 'code' => 'T1_S01', 'title' => 'Números reales']);

    $this->actingAs($this->user)->post(route('sources.documents.store'), [
        'code' => 'T1_S01', 'title' => 'x', 'document' => UploadedFile::fake()->create('tema.docx', 10),
    ])->assertSessionHasErrors(['code', 'document']);

    $this->actingAs($this->user)->post(route('sources.documents.store'), [
        'code' => 'con espacios', 'title' => 'x', 'document' => pdfUpload(temaLines()),
    ])->assertSessionHasErrors('code');
});

it('adds a youtube class with its own video folder', function () {
    $this->actingAs($this->user)->post(route('sources.classes.store'), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'title' => 'Clase de potencias',
    ])->assertRedirect(route('sources.index'));

    $source = Source::where('type', 'clase')->where('code', 'dQw4w9WgXcQ')->firstOrFail();

    expect($source->ref)->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->and($source->classVideoPath())->toEndWith('sources/clase/dQw4w9WgXcQ/clase.mp4')
        ->and($source->classVideoPath())->not->toBe(config('accesopro.class_video_path'));
});

it('rejects non youtube links and duplicated classes', function () {
    Source::create(['type' => 'clase', 'code' => 'dQw4w9WgXcQ', 'title' => 'Ya está', 'ref' => 'https://youtu.be/dQw4w9WgXcQ']);

    $this->actingAs($this->user)->post(route('sources.classes.store'), [
        'url' => 'https://vimeo.com/123456', 'title' => 'x',
    ])->assertSessionHasErrors('url');

    $this->actingAs($this->user)->post(route('sources.classes.store'), [
        'url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'x',
    ])->assertSessionHasErrors('url');
});

it('keeps the original class on the configured video path', function () {
    $source = Source::create(['type' => 'clase', 'code' => 'fQBP3hY39oU', 'title' => 'Clase original', 'meta' => []]);

    expect($source->classVideoPath())->toBe(config('accesopro.class_video_path'));
});

it('queues the pipeline with the chosen limit', function () {
    Queue::fake();
    $source = Source::create(['type' => 'temario', 'code' => 'T1_S01', 'title' => 'Números reales']);

    $this->actingAs($this->user)->post(route('sources.run', $source), ['limit' => 3])
        ->assertSessionHas('status');

    Queue::assertPushed(RunSourcePipeline::class, fn ($job) => $job->source->is($source) && $job->limit === 3);
    expect($source->fresh()->run_status)->toBe(Source::RUN_QUEUED);
});

it('does not queue a source that is already running', function () {
    Queue::fake();
    $source = Source::create(['type' => 'temario', 'code' => 'T1_S01', 'title' => 'x', 'run_status' => Source::RUN_PROCESSING]);

    $this->actingAs($this->user)->post(route('sources.run', $source), ['limit' => 1])
        ->assertSessionHasErrors('run');

    Queue::assertNothingPushed();
});

it('records the error on the source when the pipeline fails', function () {
    $source = Source::create([
        'type' => 'temario', 'code' => 'TEST_PDF', 'title' => 'Roto',
        'meta' => ['origin' => 'panel'], // subido, pero sin texto guardado
    ]);

    expect(fn () => RunSourcePipeline::dispatchSync($source, 1))->toThrow(RuntimeException::class);

    $source->refresh();
    expect($source->run_status)->toBe(Source::RUN_ERROR)
        ->and($source->run_error)->toContain('no tiene texto guardado')
        ->and($source->run_finished_at)->not->toBeNull();
});

it('never falls back to materiales/ for an uploaded topic without text', function () {
    $source = Source::create(['type' => 'temario', 'code' => 'T1_S01', 'title' => 'x', 'meta' => ['origin' => 'panel']]);

    expect(fn () => app(SourceMaterial::class)->scriptContext($source))
        ->toThrow(RuntimeException::class, 'no tiene texto guardado');
});

it('keeps the official syllabus used by the reviewer identical to the documented copy', function () {
    $documented = base_path('../docs/TEMARIO_OFICIAL.md');

    if (! is_file($documented)) {
        $this->markTestSkipped('docs/ no está disponible (p. ej. en un despliegue).');
    }

    expect(app(SourceMaterial::class)->officialSyllabus())
        ->toBe(file_get_contents($documented))
        ->toContain('Bloque 1: Sentido numérico');
});
