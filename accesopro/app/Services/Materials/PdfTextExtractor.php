<?php

namespace App\Services\Materials;

use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Extrae el texto de un PDF del temario subido desde el panel (smalot/pdfparser, PHP puro:
 * sin binarios externos, funciona igual en local y en Laravel Cloud).
 */
final class PdfTextExtractor
{
    /** Por encima de esto el tema no cabe razonablemente en cada llamada al generador. */
    public const MAX_CHARS = 60_000;

    /** Por debajo de esto el PDF es casi seguro escaneado (imagen) y no tiene texto real. */
    public const MIN_CHARS = 300;

    /** @throws InvalidDocumentException */
    public function extract(string $path): string
    {
        try {
            $text = (new Parser)->parseFile($path)->getText();
        } catch (Throwable $e) {
            throw new InvalidDocumentException('No se pudo leer el PDF: '.$e->getMessage(), previous: $e);
        }

        $text = $this->normalize($text);
        $length = mb_strlen($text);

        if ($length < self::MIN_CHARS) {
            throw new InvalidDocumentException(
                'El PDF casi no tiene texto seleccionable (¿es un documento escaneado?). '
                .'Sube un PDF exportado desde Word u otro editor de texto.',
            );
        }

        if ($length > self::MAX_CHARS) {
            throw new InvalidDocumentException(
                'El PDF es demasiado largo ('.number_format($length, 0, ',', '.').' caracteres; máximo '
                .number_format(self::MAX_CHARS, 0, ',', '.').'). Sube un solo tema por documento.',
            );
        }

        return $text;
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
