<?php

namespace App\Console\Commands;

use App\Services\Llm\LlmGateway;
use App\Services\Voice\ElevenLabsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class CheckCommand extends Command
{
    protected $signature = 'accesopro:check';

    protected $description = 'Comprueba que todas las piezas del sistema están configuradas';

    public function handle(LlmGateway $llm, ElevenLabsClient $eleven): int
    {
        $results = [];

        $results[] = $this->step('FFmpeg', function () {
            $r = Process::run([config('accesopro.ffmpeg'), '-version'])->throw();
            $version = strtok($r->output(), "\n");

            // drawtext (texto de escenas, necesita libfreetype + libharfbuzz) y ass (subtítulos,
            // libass) son imprescindibles: un FFmpeg sin ellos arranca bien pero no renderiza (D32).
            $filters = Process::run([config('accesopro.ffmpeg'), '-hide_banner', '-filters'])->throw()->output();
            $missing = array_values(array_filter(
                ['drawtext', 'ass'],
                fn (string $f) => preg_match('/^\s*\S+\s+'.$f.'\s/m', $filters) !== 1,
            ));

            if ($missing !== []) {
                throw new \RuntimeException("{$version} — faltan filtros: ".implode(', ', $missing).' (¿compilado sin libharfbuzz/libass?)');
            }

            return "{$version} (drawtext y ass disponibles)";
        });

        foreach (['generator', 'reviewer'] as $role) {
            $results[] = $this->step("LLM {$role} ({$llm->modelFor($role)})", fn () => trim(
                $llm->text($role, 'Responde únicamente con la palabra OK.', 'Prueba de conexión.', operation: 'check')
            ));
        }

        $results[] = $this->step('ElevenLabs', function () use ($eleven) {
            $s = $eleven->subscription();
            $ivc = ($s['can_use_instant_voice_cloning'] ?? false) ? 'clonación disponible' : 'SIN clonación en este plan';
            return sprintf('%s · %s/%s caracteres · %s',
                $s['tier'] ?? '?', $s['character_count'] ?? '?', $s['character_limit'] ?? '?', $ivc);
        });

        $results[] = $this->step('Google Drive', function () {
            $path = config('accesopro.drive_root').'/_check/ok.txt';
            Storage::disk('google')->put($path, 'AccesoPro check '.now());
            return 'escrito '.$path;
        });

        $ok = ! in_array(false, $results, true);

        $this->newLine();
        $ok ? $this->info('Todo listo.') : $this->error('Hay pasos con error: revisa el .env.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function step(string $name, callable $fn): bool
    {
        try {
            $this->line("<info>✔</info> {$name}: ".$fn());
            return true;
        } catch (\Throwable $e) {
            $this->line("<error>✘</error> {$name}: ".$e->getMessage());
            return false;
        }
    }
}
