<?php

namespace App\Services\Materials;

use App\Models\Source;
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
        return $this->read(config('accesopro.official_syllabus_path'));
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

    /**
     * Material con el que se extraen las ideas de un tema. Si el tema se subió como PDF desde
     * el panel, es el texto de ese documento; si no, la estructura del tema de AccesoPro +
     * el guion de podcast de materiales/ (flujo original de T1_S01).
     */
    public function ideasContext(Source $source): string
    {
        if ($text = $this->uploadedText($source)) {
            return "# Documento del tema (fuente principal)\n\n{$text}";
        }

        return "# Estructura del tema en AccesoPro\n\n{$this->accesoProSyllabus()}\n\n"
            ."# Guion de podcast ya elaborado (fuente principal)\n\n"
            .json_encode($this->podcastScript($source->code), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /** Material del que se escribe cada guion (mismo criterio que ideasContext()). */
    public function scriptContext(Source $source): string
    {
        if ($text = $this->uploadedText($source)) {
            return "# Documento del tema (fuente de contenido)\n\n{$text}";
        }

        return "# Guion de podcast (fuente de contenido)\n\n".$this->podcastTranscript($source->code);
    }

    /**
     * Texto del PDF de un tema subido desde el panel, o null si la fuente es de materiales/.
     * Un tema subido sin texto es un error: nunca debe caer en silencio al material de
     * materiales/ (generaría contenido de otro tema).
     */
    private function uploadedText(Source $source): ?string
    {
        if (! $source->isUploaded()) {
            return null;
        }

        return $source->documentText()
            ?? throw new RuntimeException("El tema subido {$source->code} no tiene texto guardado: vuelve a subir el PDF.");
    }

    private function read(string $path): string
    {
        if (! is_file($path)) {
            throw new RuntimeException("No se encontró el material de origen: {$path}");
        }

        return file_get_contents($path);
    }
}
