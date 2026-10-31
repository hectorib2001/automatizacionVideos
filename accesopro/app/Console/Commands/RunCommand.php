<?php

namespace App\Console\Commands;

use App\Models\Source;
use App\Services\Pipeline\PipelineRunner;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Disparador único del pipeline (ver docs/ARQUITECTURA.md): fuente de texto (ideas → guion →
 * revisión → voz → vídeo → metadata/miniatura → Drive) y, con --class, la clase grabada
 * (descarga → transcripción → momentos → revisión → corte → metadata/miniatura → Drive).
 * La lógica vive en PipelineRunner, compartida con el botón del panel.
 */
final class RunCommand extends Command
{
    protected $signature = 'accesopro:run
        {code : Código del tema, p. ej. T1_S01}
        {--class= : Enlace o ruta de la clase grabada. Si se indica, procesa también los clips.}
        {--limit=1 : Cuántas ideas procesar (guion + revisión) en esta ejecución. Cada una es una llamada de pago al generador y al revisor.}
        {--clip-limit=1 : Cuántos clips detectados revisar y montar en esta ejecución.}';

    protected $description = 'Ejecuta el pipeline de AccesoPro para un tema (y, con --class, la clase grabada).';

    public function handle(PipelineRunner $runner): int
    {
        $log = fn (string $line) => $this->info($line);

        try {
            $source = Source::firstOrCreate(
                ['type' => 'temario', 'code' => $this->argument('code')],
                ['title' => $this->argument('code')],
            );
            $this->report($runner->runTexto($source, (int) $this->option('limit'), $log), 'idea', '--limit');

            if ($classUrl = $this->option('class')) {
                $code = PipelineRunner::classCode($classUrl);
                $source = Source::firstOrCreate(
                    ['type' => 'clase', 'code' => $code],
                    ['title' => "Clase {$code}", 'ref' => $classUrl],
                );
                $this->report($runner->runClase($source, (int) $this->option('clip-limit'), $log), 'clip', '--clip-limit');
            }
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @param  array{rows: list<list<string>>, total: int, processed: int}  $result */
    private function report(array $result, string $label, string $option): void
    {
        $this->table([$label, 'estado', 'coste (€)', 'vídeo', 'publicado', 'motivo de descarte'], $result['rows']);

        if ($result['total'] > $result['processed']) {
            $remaining = $result['total'] - $result['processed'];
            $this->line("Quedan {$remaining} sin procesar. Vuelve a ejecutar con {$option} mayor cuando quieras continuar.");
        }
    }
}
