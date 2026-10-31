<?php

namespace App\Console\Commands;

use App\Services\Voice\ElevenLabsClient;
use Illuminate\Console\Command;

class VoiceCloneCommand extends Command
{
    protected $signature = 'accesopro:voice:clone
        {files* : Una o varias grabaciones (mp3, wav, m4a)}
        {--name=AccesoPro Profesor : Nombre de la voz en ElevenLabs}';

    protected $description = 'Crea la voz clonada en ElevenLabs a partir de tus grabaciones';

    public function handle(ElevenLabsClient $eleven): int
    {
        $files = $this->argument('files');

        foreach ($files as $f) {
            if (! is_file($f)) {
                $this->error("No existe: {$f}");
                return self::FAILURE;
            }
        }

        $voiceId = $eleven->cloneVoice(
            $this->option('name'),
            $files,
            'Voz clonada para la prueba técnica de AccesoPro',
        );

        $this->info("Voz creada. voice_id = {$voiceId}");
        $this->line("Añade a tu .env:  ELEVENLABS_VOICE_ID={$voiceId}");

        return self::SUCCESS;
    }
}
