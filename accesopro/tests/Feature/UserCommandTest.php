<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a user with the given password', function () {
    $this->artisan('accesopro:user', ['email' => 'Profe@AccesoPro.test', '--password' => 'una-clave-larga-123'])
        ->expectsOutputToContain('Usuario profe@accesopro.test creado.')
        ->assertSuccessful();

    $user = User::where('email', 'profe@accesopro.test')->firstOrFail();
    expect($user->name)->toBe('profe')
        ->and(Hash::check('una-clave-larga-123', $user->password))->toBeTrue();
});

it('generates and shows a password when none is given', function () {
    $this->artisan('accesopro:user', ['email' => 'profe@accesopro.test'])
        ->expectsOutputToContain('Contraseña generada')
        ->assertSuccessful();

    expect(User::where('email', 'profe@accesopro.test')->exists())->toBeTrue();
});

it('resets the password of an existing user without duplicating it', function () {
    User::factory()->create(['email' => 'profe@accesopro.test', 'name' => 'Arantxa']);

    $this->artisan('accesopro:user', ['email' => 'profe@accesopro.test', '--password' => 'otra-clave-larga-456'])
        ->expectsOutputToContain('Contraseña actualizada')
        ->assertSuccessful();

    $user = User::where('email', 'profe@accesopro.test')->sole();
    expect($user->name)->toBe('Arantxa')
        ->and(Hash::check('otra-clave-larga-456', $user->password))->toBeTrue();
});

it('rejects short passwords and invalid emails', function () {
    $this->artisan('accesopro:user', ['email' => 'profe@accesopro.test', '--password' => 'corta'])->assertFailed();
    $this->artisan('accesopro:user', ['email' => 'no-es-un-correo', '--password' => 'una-clave-larga-123'])->assertFailed();

    expect(User::count())->toBe(0);
});
