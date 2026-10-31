<?php

namespace App\Http\Controllers;

use App\Services\Export\ControlTableExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Descarga en CSV de la tabla de control y de las revisiones desde el panel. */
final class ControlExportController extends Controller
{
    public function __construct(private readonly ControlTableExporter $exporter) {}

    public function pieces(): StreamedResponse
    {
        return $this->download('tabla_control', $this->exporter->pieceHeaders(), $this->exporter->pieceRows());
    }

    public function reviews(): StreamedResponse
    {
        return $this->download('revisiones', $this->exporter->reviewHeaders(), $this->exporter->reviewRows());
    }

    public function rejections(): StreamedResponse
    {
        return $this->download('rechazos', $this->exporter->reviewHeaders(), $this->exporter->reviewRows(onlyRejected: true));
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<list<string>>  $rows
     */
    private function download(string $name, array $headers, iterable $rows): StreamedResponse
    {
        $filename = $name.'_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');
            $this->exporter->write($out, $headers, $rows);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
