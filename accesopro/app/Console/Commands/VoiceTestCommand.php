<?php

namespace App\Console\Commands;

use App\Services\Voice\ElevenLabsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class VoiceTestCommand extends Command
{
    protected $signature = 'accesopro:voice:test {--text=}';

    protected $description = 'Genera un audio de prueba con la voz clonada y lo sube a Drive';

    public function handle(ElevenLabsClient $eleven): int
    {
        $text = $this->option('text') ?: '¿Sabías que dos elevado a menos tres no es menos ocho? '
            .'Es un octavo. El exponente negativo no cambia el signo: invierte la base. '
            .'Guárdate este truco para el examen.';

        $result = $eleven->speak($text);

        $stamp = now()->format('Ymd_His');
        $local = "pruebas/voz_{$stamp}.mp3";
        Storage::disk('local')->put($local, $result['audio']);
        Storage::disk('local')->put("pruebas/voz_{$stamp}_alignment.json", json_encode($result['alignment']));

        $remote = config('accesopro.drive_root')."/pruebas/voz_{$stamp}.mp3";
        Storage::disk('google')->put($remote, $result['audio']);

        $this->info('Local: '.Storage::disk('local')->path($local));
        $this->info("Drive: {$remote}");
        $this->line("Caracteres: {$result['characters']}");

        return self::SUCCESS;
    }
}
