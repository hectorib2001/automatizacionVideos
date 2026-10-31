<?php

namespace App\Services\Materials;

use RuntimeException;

/**
 * Lee el material de origen de un tema: el temario oficial (público) y, en
 * materiales/ (confidencial, ver CLAUDE.md regla 1), el temario de AccesoPro y el
 * guion de podcast ya elaborado.
 */
final class SourceMaterial
{
    public function officialSyllabus(): string
    {
        return $this->read(base_path('../docs/TEMARIO_OFICIAL.md'));
    }

    public function accesoProSyllabus(): string
    {
        return $this->read(config('accesopro.materiales_path').'/Temario AccesoPro Matematicas Valencia GS.txt');
    }

    /** @return array<string, mixed> */
    public function podcastScript(string $code): array
    {
        $path = config('accesopro.materiales_path')."/MAT_GS_COMUNIDAD_VALENCIANA_{$code}_podcast.json";
        $json = json_decode($this->read($path), true);

        if (! is_array($json)) {
            throw new RuntimeException("El guion de podcast en {$path} no es un JSON válido.");
        }

        return $json;
    }

    /** Todo el texto hablado del podcast, en orden, como un único bloque. */
    public function podcastTranscript(string $code): string
    {
        $turnos = $this->podcastScript($code)['podcast']['turnos'] ?? [];

        return collect($turnos)
            ->map(fn (array $turno) => "[{$turno['hablante']}] {$turno['texto']}")
            ->implode("\n");
    }

    private function read(string $path): string
    {
        if (! is_file($path)) {
            throw new RuntimeException("No se encontró el material de origen: {$path}");
        }

        return file_get_contents($path);
    }
}
