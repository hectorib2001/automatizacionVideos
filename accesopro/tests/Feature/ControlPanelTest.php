<?php

use App\Enums\PieceStatus;
use App\Models\Piece;
use App\Models\Source;
use App\Models\User;

it('redirects a guest trying to reach the control panel to login', function () {
    $this->get(route('control.index'))->assertRedirect(route('login'));
});

it('logs in with valid credentials and reaches the control panel', function () {
    $user = User::factory()->create(['password' => bcrypt('secret-pass')]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'secret-pass',
    ]);

    $response->assertRedirect(route('control.index'));
    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('secret-pass')]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('shows pieces with their status, cost, origin and discard reason once authenticated', function () {
    $user = User::factory()->create();
    $source = Source::create(['type' => 'temario', 'code' => 'T1_S01', 'title' => 'T1_S01']);

    Piece::create([
        'source_id' => $source->id,
        'kind' => 'texto',
        'uid' => 'test-panel-piece',
        'idea_title' => 'Sumar fracciones sin errores',
        'status' => PieceStatus::Descartada,
        'cost_eur' => 0.12345,
        'discard_reason' => 'Motivo de prueba',
    ]);

    $response = $this->actingAs($user)->get(route('control.index'));

    $response->assertOk()
        ->assertSee('Sumar fracciones sin errores')
        ->assertSee('T1_S01')
        ->assertSee('0.12345')
        ->assertSee('Motivo de prueba');
});

it('filters pieces by status and kind', function () {
    $user = User::factory()->create();
    $source = Source::create(['type' => 'clase', 'code' => 'abc123']);

    Piece::create(['source_id' => $source->id, 'kind' => 'clip', 'uid' => 'p1', 'idea_title' => 'Clip aprobado', 'status' => PieceStatus::Revisada]);
    Piece::create(['source_id' => $source->id, 'kind' => 'texto', 'uid' => 'p2', 'idea_title' => 'Texto descartado', 'status' => PieceStatus::Descartada]);

    $response = $this->actingAs($user)->get(route('control.index', ['status' => 'descartada']));

    $response->assertSee('Texto descartado')->assertDontSee('Clip aprobado');

    $response = $this->actingAs($user)->get(route('control.index', ['kind' => 'clip']));

    $response->assertSee('Clip aprobado')->assertDontSee('Texto descartado');
});

it('logs out and can no longer reach the control panel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

    $this->get(route('control.index'))->assertRedirect(route('login'));
});

it('shortens long discard reasons and shows each reason as a paragraph in the detail popup', function () {
    $user = User::factory()->create();
    $source = Source::create(['type' => 'clase', 'code' => 'fQBP3hY39oU', 'title' => 'Clase']);
    $reason = 'El criterio «decimal infinito, luego irracional» es falso: 1/3 también tiene infinitos decimales.; '
        .'La expresión «lo que veíamos» hace depender el clip de la clase anterior.; Usa <b>pizarra</b> que no se ve.';

    Piece::create([
        'source_id' => $source->id, 'kind' => 'clip', 'uid' => 'clip-largo',
        'idea_title' => 'Producto de irracionales', 'status' => PieceStatus::Descartada, 'discard_reason' => $reason,
    ]);

    $html = $this->actingAs($user)->get(route('control.index'))->assertOk()->getContent();

    expect($html)->toContain('Ver detalle')
        ->toContain('id="detail-dialog"')
        ->toContain('Motivo de descarte · Producto de irracionales')
        // Un motivo por párrafo en la ventana; el ";" interno de "falso:" no se toca.
        ->toContain("decimales.\n\nLa expresión")
        ->toContain('Usa &lt;b&gt;pizarra&lt;/b&gt;')
        ->not->toContain('<b>pizarra</b>');
});

it('does not show the detail button for short discard reasons', function () {
    $user = User::factory()->create();
    $source = Source::create(['type' => 'temario', 'code' => 'T1_S01', 'title' => 'T1_S01']);
    Piece::create(['source_id' => $source->id, 'kind' => 'texto', 'uid' => 'corto', 'idea_title' => 'x', 'status' => PieceStatus::Descartada, 'discard_reason' => 'Dato inventado']);

    expect($this->actingAs($user)->get(route('control.index'))->getContent())
        ->toContain('Dato inventado')->not->toContain('Ver detalle');
});
