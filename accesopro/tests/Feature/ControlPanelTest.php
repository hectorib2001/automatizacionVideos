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
