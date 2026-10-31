<?php

use App\Enums\PieceStatus;
use App\Models\Piece;
use Illuminate\Database\Migrations\Migration;

/**
 * Hasta el 31/10 ningún job avanzaba el estado más allá de `revisada`, así que las piezas ya
 * montadas y publicadas se quedaron ahí. Se recorre la misma secuencia que hace ahora el
 * pipeline (aprobar → renderizada → publicada), que solo avanza si los renders y las
 * publicaciones existen de verdad. Idempotente: relanzarla no cambia nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Piece::where('status', PieceStatus::Revisada)
            ->whereHas('renders', fn ($q) => $q->whereIn('format', Piece::VIDEO_FORMATS))
            ->each(function (Piece $piece): void {
                $piece->approve();
                $piece->markRendered();
                $piece->markPublished();
            });
    }

    public function down(): void
    {
        // Sin vuelta atrás: los estados anteriores eran incorrectos.
    }
};
