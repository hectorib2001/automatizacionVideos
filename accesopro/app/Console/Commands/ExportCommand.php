<?php

namespace App\Console\Commands;

use App\Services\Export\ControlTableExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/** Exporta tabla de control, revisiones y rechazos a CSV (entregables de docs/RETO.md). */
final class ExportCommand extends Command
{
    protected $signature = 'accesopro:export
        {--dir= : Carpeta de destino (por defecto storage/app/private/exports)}';

    protected $description = 'Exporta la tabla de control, las revisiones y los rechazos a CSV';

    public function handle(ControlTableExporter $exporter): int
    {
        $dir = $this->option('dir') ?: storage_path('app/private/exports');
        File::ensureDirectoryExists($dir);

        $files = [
            'tabla_control.csv' => [$exporter->pieceHeaders(), $exporter->pieceRows()],
            'revisiones.csv' => [$exporter->reviewHeaders(), $exporter->reviewRows()],
            'rechazos.csv' => [$exporter->reviewHeaders(), $exporter->reviewRows(onlyRejected: true)],
        ];

        foreach ($files as $name => [$headers, $rows]) {
            $path = $dir.DIRECTORY_SEPARATOR.$name;
            $handle = fopen($path, 'w');
            $exporter->write($handle, $headers, $rows);
            fclose($handle);
            $this->info("✔ {$path}");
        }

        return self::SUCCESS;
    }
}
