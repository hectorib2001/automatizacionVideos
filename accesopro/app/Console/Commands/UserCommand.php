<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Crea (o actualiza la contraseña de) un usuario del panel. No hay registro público: es la
 * única forma de dar acceso. Pensado también para la consola de comandos de Laravel Cloud,
 * que no es interactiva: sin --password genera una contraseña segura y la muestra una vez.
 */
final class UserCommand extends Command
{
    protected $signature = 'accesopro:user
        {email : Correo con el que se entra al panel}
        {--name= : Nombre visible (por defecto, la parte del correo antes de la @)}
        {--password= : Contraseña (mín. 12 caracteres). Si se omite, se genera una aleatoria.}';

    protected $description = 'Crea un usuario del panel o restablece su contraseña';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        $generated = $this->option('password') === null;
        $password = $this->option('password') ?? Str::password(20, symbols: false);

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email'], 'password' => ['required', 'string', 'min:12']],
            ['password.min' => 'La contraseña debe tener al menos 12 caracteres.'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $existed = $user->exists;

        $user->forceFill([
            'name' => $this->option('name') ?? $user->name ?? Str::before($email, '@'),
            'password' => Hash::make($password),
        ])->save();

        $this->info($existed ? "Contraseña actualizada para {$email}." : "Usuario {$email} creado.");

        if ($generated) {
            $this->line("Contraseña generada (guárdala, no se volverá a mostrar): <comment>{$password}</comment>");
        }

        return self::SUCCESS;
    }
}
