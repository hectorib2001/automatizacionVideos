<?php

use App\Enums\PieceStatus;
use App\Jobs\Texto\ExtractIdeas;
use App\Jobs\Texto\GenerateScript;
use App\Jobs\Texto\ReviseScript;
use App\Jobs\Texto\ReviewScript;
use App\Models\Piece;
use App\Models\Review;
use App\Models\Source;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\ValueObjects\Usage;

function fakeIdeasResponse(): StructuredResponseFake
{
    return StructuredResponseFake::make()->withUsage(new Usage(500, 300))->withStructured([
        'ideas' => [
            ['idea_key' => 'suma_fracciones', 'idea_title' => 'Sumar fracciones sin errores', 'resumen' => 'Cómo sumar fracciones con distinto denominador.', 'saberes' => ['Números reales: operaciones, ordenación, representación y propiedades.']],
            ['idea_key' => 'potencias_negativas', 'idea_title' => 'Potencias de exponente negativo', 'resumen' => 'Qué significa un exponente negativo.', 'saberes' => ['Números reales: operaciones, ordenación, representación y propiedades.']],
            ['idea_key' => 'prioridad_operaciones', 'idea_title' => 'Prioridad de las operaciones', 'resumen' => 'Qué se calcula primero.', 'saberes' => ['Números reales: operaciones, ordenación, representación y propiedades.']],
            ['idea_key' => 'comparar_fracciones', 'idea_title' => 'Comparar fracciones', 'resumen' => 'Cómo comparar fracciones sin pasar a decimal.', 'saberes' => ['Números reales: operaciones, ordenación, representación y propiedades.']],
            ['idea_key' => 'propiedad_distributiva', 'idea_title' => 'Propiedad distributiva', 'resumen' => 'Cómo repartir un producto sobre una suma.', 'saberes' => ['Números reales: operaciones, ordenación, representación y propiedades.']],
        ],
    ]);
}

function fakeScriptResponse(array $claims = []): StructuredResponseFake
{
    return StructuredResponseFake::make()->withUsage(new Usage(800, 400))->withStructured([
        'idea_title' => 'Sumar fracciones sin errores',
        'hooks' => [
            ['variant' => 'A', 'text' => '¿Sabes por qué sumar fracciones mal te puede costar un punto en el examen?'],
            ['variant' => 'B', 'text' => 'Este es el error de fracciones que más se repite en el examen.'],
        ],
        'explanation' => 'Para sumar fracciones necesitas el mismo denominador. Un medio más un tercio: el mínimo común múltiplo de dos y tres es seis, así que es tres sextos más dos sextos, que son cinco sextos.',
        'cta' => 'Sigue practicando con AccesoPro para llegar seguro al examen.',
        'scenes' => [
            ['texto' => '1/2 + 1/3 = ?', 'formula' => '1/2+1/3', 'duracion_seg' => 15],
            ['texto' => '1/2 + 1/3 = 5/6', 'formula' => '1/2+1/3=5/6', 'duracion_seg' => 15],
        ],
        'claims' => $claims !== [] ? $claims : [
            ['expresion' => '1/2+1/3', 'resultado' => '5/6', 'texto_original' => 'un medio más un tercio son cinco sextos'],
        ],
        'syllabus_refs' => ['Números reales: operaciones, ordenación, representación y propiedades.'],
    ]);
}

/** @param  array<string, array{status: string, detail: string}>  $checkOverrides */
function fakeReviewResponse(string $verdict = 'aprobado', array $checkOverrides = [], array $claims = [], array $reasons = []): StructuredResponseFake
{
    $checks = [];
    foreach (['matematica', 'temario', 'datos_inventados', 'pedagogia', 'autonomia', 'formato'] as $name) {
        $checks[$name] = $checkOverrides[$name] ?? ['status' => 'pass', 'detail' => 'Correcto.'];
    }

    return StructuredResponseFake::make()->withUsage(new Usage(1200, 500))->withStructured([
        'verdict' => $verdict,
        'checks' => $checks,
        'claims' => $claims !== [] ? $claims : [
            ['expresion' => '1/2+1/3', 'resultado' => '5/6', 'texto_original' => 'un medio más un tercio son cinco sextos'],
        ],
        'reasons' => $reasons,
        'fix_suggestion' => $reasons === [] ? '' : 'Corrige la afirmación numérica indicada.',
    ]);
}

it('extracts ideas once and stores them on the source, skipping a second call', function () {
    $fake = Prism::fake([fakeIdeasResponse()]);

    $source = Source::create(['type' => 'temario', 'code' => 'T1_S01', 'title' => 'T1_S01']);

    ExtractIdeas::dispatchSync($source);
    $source->refresh();

    expect($source->meta['ideas'])->toHaveCount(5)
        ->and($source->meta['ideas'][0]['idea_key'])->toBe('suma_fracciones');

    // Segunda vez: no debe volver a llamar al LLM (ya hay ideas guardadas).
    ExtractIdeas::dispatchSync($source);

    $fake->assertCallCount(1);
});

it('generates a script for one idea and is idempotent on relaunch', function () {
    $fake = Prism::fake([fakeScriptResponse()]);

    $source = Source::create(['type' => 'temario', 'code' => 'T1_S01', 'title' => 'T1_S01']);
    $idea = ['idea_key' => 'suma_fracciones', 'idea_title' => 'Sumar fracciones sin errores', 'resumen' => '...', 'saberes' => []];

    GenerateScript::dispatchSync($source, $idea);

    $piece = Piece::where('uid', Piece::makeUid('texto', 'T1_S01', 'suma_fracciones'))->first();

    expect($piece)->not->toBeNull()
        ->and($piece->status)->toBe(PieceStatus::Generada)
        ->and($piece->script['claims'])->toHaveCount(1);

    GenerateScript::dispatchSync($source, $idea);

    $fake->assertCallCount(1);
});

it('approves a script when every check passes and the math is correct', function () {
    $fake = Prism::fake([fakeReviewResponse(verdict: 'aprobado')]);

    $piece = Piece::create([
        'source_id' => Source::create(['type' => 'temario', 'code' => 'T1_S01'])->id,
        'kind' => 'texto',
        'uid' => 'test-approve',
        'idea_title' => 'Sumar fracciones sin errores',
        'status' => PieceStatus::Generada,
        'script' => fakeScriptResponse()->structured,
    ]);

    ReviewScript::dispatchSync($piece);
    $piece->refresh();

    expect($piece->status)->toBe(PieceStatus::Revisada)
        ->and(Review::where('piece_id', $piece->id)->first()->verdict)->toBe('aprobado');

    // Relanzar sobre una pieza ya aprobada no debe volver a llamar al revisor (idempotencia,
    // regla 4 de CLAUDE.md): evita gastar de nuevo al reprocesar un lote con --limit mayor.
    ReviewScript::dispatchSync($piece);

    $fake->assertCallCount(1);
    expect(Review::where('piece_id', $piece->id)->count())->toBe(1);
});

it('forces a fail when MathVerifier disagrees even if the LLM says the claim is fine', function () {
    // El LLM dice que todo está bien, pero la afirmación es matemáticamente falsa
    // (2^-3 = -8 es el caso de ejemplo de docs/REVISOR.md).
    Prism::fake([fakeReviewResponse(
        verdict: 'aprobado',
        claims: [['expresion' => '2^-3', 'resultado' => '-8', 'texto_original' => 'dos elevado a menos tres es menos ocho']],
    )]);

    $piece = Piece::create([
        'source_id' => Source::create(['type' => 'temario', 'code' => 'T1_S01'])->id,
        'kind' => 'texto',
        'uid' => 'test-math-fail',
        'idea_title' => 'Potencias de exponente negativo',
        'status' => PieceStatus::Generada,
        'script' => fakeScriptResponse()->structured,
    ]);

    ReviewScript::dispatchSync($piece);
    $piece->refresh();

    $review = Review::where('piece_id', $piece->id)->first();

    expect($piece->status)->toBe(PieceStatus::Rechazada)
        ->and($review->verdict)->toBe('rechazado')
        ->and($review->checks['matematica']['status'])->toBe('fail');
});

it('discards a piece after the third failed review attempt', function () {
    Prism::fake(array_fill(0, 3, fakeReviewResponse(
        verdict: 'rechazado',
        checkOverrides: ['datos_inventados' => ['status' => 'fail', 'detail' => "Dato sin fuente: 'resuelve el 90% de los errores'."]],
        reasons: ["Elimina la afirmación 'resuelve el 90% de los errores': no está en el material de origen."],
    )));

    $piece = Piece::create([
        'source_id' => Source::create(['type' => 'temario', 'code' => 'T1_S01'])->id,
        'kind' => 'texto',
        'uid' => 'test-discard',
        'idea_title' => 'Sumar fracciones sin errores',
        'status' => PieceStatus::Generada,
        'script' => fakeScriptResponse()->structured,
    ]);

    ReviewScript::dispatchSync($piece);
    $piece->refresh();
    ReviewScript::dispatchSync($piece);
    $piece->refresh();
    ReviewScript::dispatchSync($piece);
    $piece->refresh();

    expect($piece->status)->toBe(PieceStatus::Descartada)
        ->and($piece->discard_reason)->toContain('90%')
        ->and(Review::where('piece_id', $piece->id)->count())->toBe(3);
});

it('revises a rejected script using the review reasons and leaves it ready for re-review', function () {
    Prism::fake([fakeScriptResponse(claims: [
        ['expresion' => '1/2+1/3', 'resultado' => '5/6', 'texto_original' => 'corregido'],
    ])]);

    $piece = Piece::create([
        'source_id' => Source::create(['type' => 'temario', 'code' => 'T1_S01'])->id,
        'kind' => 'texto',
        'uid' => 'test-revise',
        'idea_title' => 'Sumar fracciones sin errores',
        'status' => PieceStatus::Rechazada,
        'script' => fakeScriptResponse()->structured,
        'attempts' => 1,
    ]);

    Review::create([
        'piece_id' => $piece->id,
        'attempt' => 1,
        'model' => 'claude-opus-5-5',
        'verdict' => 'rechazado',
        'reasons' => ["El gancho afirma algo sin fuente."],
        'checks' => [],
    ]);

    ReviseScript::dispatchSync($piece);
    $piece->refresh();

    expect($piece->status)->toBe(PieceStatus::Corregida)
        ->and($piece->script['claims'][0]['texto_original'])->toBe('corregido');
});

it('does nothing when asked to revise a piece that is not rechazada (idempotent)', function () {
    $fake = Prism::fake([fakeScriptResponse()]);

    $piece = Piece::create([
        'source_id' => Source::create(['type' => 'temario', 'code' => 'T1_S01'])->id,
        'kind' => 'texto',
        'uid' => 'test-revise-noop',
        'idea_title' => 'Sumar fracciones sin errores',
        'status' => PieceStatus::Revisada,
        'script' => fakeScriptResponse()->structured,
    ]);

    ReviseScript::dispatchSync($piece);

    $fake->assertCallCount(0);
});

it('runs the full correction loop: rejected, revised, then approved on the second review', function () {
    Prism::fake([
        fakeReviewResponse(verdict: 'rechazado', checkOverrides: ['datos_inventados' => ['status' => 'fail', 'detail' => 'Dato sin fuente.']], reasons: ['Quita el dato sin fuente del gancho.']),
        fakeScriptResponse(), // ReviseScript
        fakeReviewResponse(verdict: 'aprobado'), // segunda revisión
    ]);

    $piece = Piece::create([
        'source_id' => Source::create(['type' => 'temario', 'code' => 'T1_S01'])->id,
        'kind' => 'texto',
        'uid' => 'test-full-loop',
        'idea_title' => 'Sumar fracciones sin errores',
        'status' => PieceStatus::Generada,
        'script' => fakeScriptResponse()->structured,
    ]);

    ReviewScript::dispatchSync($piece);
    $piece->refresh();

    expect($piece->status)->toBe(PieceStatus::Rechazada);

    ReviseScript::dispatchSync($piece);
    $piece->refresh();
    ReviewScript::dispatchSync($piece);
    $piece->refresh();

    expect($piece->status)->toBe(PieceStatus::Revisada)
        ->and($piece->attempts)->toBe(2)
        ->and(Review::where('piece_id', $piece->id)->count())->toBe(2);
});
