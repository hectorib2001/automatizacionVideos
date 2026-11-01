<?php

namespace App\Services\Export;

use App\Models\Piece;
use App\Models\Review;

/**
 * Exporta la tabla de control y las revisiones a CSV (entregables "tabla de control
 * exportada" y "ejemplos de rechazo exportados", ver docs/RETO.md).
 *
 * Formato pensado para abrirse con doble clic en Excel en español: UTF-8 con BOM,
 * separador `;` y coma decimal.
 */
final class ControlTableExporter
{
    private const SEPARATOR = ';';

    /** @return list<string> */
    public function pieceHeaders(): array
    {
        return ['id', 'uid', 'titulo', 'tipo', 'estado', 'origen', 'codigo_origen', 'clip_inicio',
            'clip_fin', 'intentos_revision', 'coste_eur', 'enlace_9x16', 'enlace_16x9', 'youtube_9x16', 'youtube_16x9',
            'motivo_descarte', 'creada', 'actualizada'];
    }

    /** @return iterable<list<string>> */
    public function pieceRows(): iterable
    {
        $pieces = Piece::query()
            ->with(['source', 'publications' => fn ($q) => $q->where('status', 'publicada')])
            ->withCount('reviews')
            ->orderBy('id')
            ->lazy();

        foreach ($pieces as $piece) {
            yield [
                (string) $piece->id,
                $piece->uid,
                $piece->idea_title,
                $piece->kind,
                $piece->status->value,
                $piece->source->title ?: $piece->source->code,
                $piece->source->code,
                $this->time($piece->clip_start),
                $this->time($piece->clip_end),
                (string) $piece->reviews_count,
                $this->decimal($piece->cost_eur, 5),
                (string) $this->url($piece, 'drive', '9x16'),
                (string) $this->url($piece, 'drive', '16x9'),
                (string) $this->url($piece, 'youtube', '9x16'),
                (string) $this->url($piece, 'youtube', '16x9'),
                (string) $piece->discard_reason,
                (string) $piece->created_at?->toDateTimeString(),
                (string) $piece->updated_at?->toDateTimeString(),
            ];
        }
    }

    /** @return list<string> */
    public function reviewHeaders(): array
    {
        return ['revision_id', 'pieza_id', 'titulo', 'tipo', 'estado_final_pieza', 'intento',
            'modelo_revisor', 'veredicto', 'comprobaciones_fallidas', 'saber_oficial', 'motivos',
            'detalle_fallos', 'fecha'];
    }

    /** @return iterable<list<string>> */
    public function reviewRows(bool $onlyRejected = false): iterable
    {
        $reviews = Review::query()
            ->with('piece')
            ->when($onlyRejected, fn ($q) => $q->where('verdict', 'rechazado'))
            ->orderBy('piece_id')->orderBy('attempt')
            ->lazy();

        foreach ($reviews as $review) {
            $checks = $review->checks ?? [];
            $failed = array_filter($checks, fn ($c) => is_array($c) && ($c['status'] ?? null) === 'fail');

            yield [
                (string) $review->id,
                (string) $review->piece_id,
                $review->piece->idea_title,
                $review->piece->kind,
                $review->piece->status->value,
                (string) $review->attempt,
                $review->model,
                $review->verdict,
                implode(', ', array_keys($failed)),
                (string) ($checks['temario']['saber_oficial'] ?? ''),
                implode(' | ', $review->reasons ?? []),
                implode(' | ', array_map(
                    fn (string $name, array $c) => "{$name}: ".($c['detail'] ?? ''),
                    array_keys($failed),
                    $failed,
                )),
                (string) $review->created_at?->toDateTimeString(),
            ];
        }
    }

    /**
     * Escribe las cabeceras y filas en un recurso abierto (archivo o php://output).
     *
     * @param  resource  $handle
     * @param  list<string>  $headers
     * @param  iterable<list<string>>  $rows
     */
    public function write($handle, array $headers, iterable $rows): void
    {
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, self::SEPARATOR, escape: '');

        foreach ($rows as $row) {
            fputcsv($handle, $row, self::SEPARATOR, escape: '');
        }
    }

    private function decimal(?float $value, int $decimals): string
    {
        return number_format((float) $value, $decimals, ',', '');
    }

    private function url(Piece $piece, string $target, string $format): ?string
    {
        return $piece->publications
            ->first(fn ($p) => $p->target === $target && $p->format === $format)?->url;
    }

    private function time(?float $seconds): string
    {
        return $seconds === null ? '' : gmdate('H:i:s', (int) $seconds);
    }
}
