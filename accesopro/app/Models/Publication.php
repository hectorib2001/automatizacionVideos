<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Publication extends Model
{
    protected $guarded = [];

    protected $casts = [];

    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }

    /**
     * Mensaje legible del error. La API de Google devuelve el cuerpo JSON completo como
     * mensaje de la excepción: de ahí sacamos solo `error.message`.
     */
    public function errorSummary(): ?string
    {
        $json = $this->errorJson();

        return $json['error']['message'] ?? $json['error_description'] ?? $json['error'] ?? $this->error;
    }

    /** Error completo para la ventana "Ver detalle" (JSON formateado si lo es). */
    public function errorDetail(): ?string
    {
        $json = $this->errorJson();

        return $json === null
            ? $this->error
            : json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function errorJson(): ?array
    {
        if ($this->error === null) {
            return null;
        }

        $decoded = json_decode($this->error, true);

        return is_array($decoded) ? $decoded : null;
    }
}
