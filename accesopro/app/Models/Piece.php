<?php

namespace App\Models;

use App\Enums\PieceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Piece extends Model
{
    /** Formatos de vídeo que se montan y publican por pieza. */
    public const VIDEO_FORMATS = ['9x16', '16x9'];

    protected $guarded = [];

    protected $casts = [
        'status' => PieceStatus::class,
        'script' => 'array',
        'clip_start' => 'float',
        'clip_end' => 'float',
        'cost_eur' => 'float',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function renders(): HasMany
    {
        return $this->hasMany(Render::class);
    }

    public function costEvents(): HasMany
    {
        return $this->hasMany(CostEvent::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    /** UID estable a partir del contenido: relanzar no crea duplicados. */
    public static function makeUid(string $kind, string $sourceCode, string $key): string
    {
        return $kind.'_'.$sourceCode.'_'.substr(hash('sha256', $key), 0, 16);
    }

    /**
     * Revisada → Aprobada. Hoy la aprobación es automática (veredicto del revisor); aquí
     * entrará la aprobación humana del Nivel 2 (REQUIRE_HUMAN_APPROVAL). Devuelve true si la
     * pieza puede seguir a render/publicación, también al relanzar una pieza que ya estaba
     * más adelante (los jobs siguientes son idempotentes).
     */
    public function approve(): bool
    {
        if ($this->status === PieceStatus::Revisada) {
            $this->update(['status' => PieceStatus::Aprobada]);
        }

        return in_array($this->status, [
            PieceStatus::Aprobada,
            PieceStatus::Renderizada,
            PieceStatus::Publicada,
        ], true);
    }

    /** Aprobada → Renderizada, solo cuando existen los dos formatos de vídeo. */
    public function markRendered(): void
    {
        $rendered = $this->renders()->whereIn('format', self::VIDEO_FORMATS)->count();

        if ($this->status === PieceStatus::Aprobada && $rendered === count(self::VIDEO_FORMATS)) {
            $this->update(['status' => PieceStatus::Renderizada]);
        }
    }

    /** Aprobada/Renderizada → Publicada, solo cuando los dos formatos están publicados en el destino activo. */
    public function markPublished(): void
    {
        $published = $this->publications()
            ->where('target', config('accesopro.publish_target'))
            ->where('status', 'publicada')
            ->whereIn('format', self::VIDEO_FORMATS)
            ->count();

        if (in_array($this->status, [PieceStatus::Aprobada, PieceStatus::Renderizada], true)
            && $published === count(self::VIDEO_FORMATS)) {
            $this->update(['status' => PieceStatus::Publicada]);
        }
    }

    /**
     * ¿Existe de verdad el archivo de este formato? No basta con la fila en `renders`: en
     * Laravel Cloud el disco es efímero y un redeploy borra los archivos aunque la base de
     * datos diga que se generaron (D33).
     */
    public function hasRenderFile(string $format): bool
    {
        $path = $this->renders()->where('format', $format)->value('path');

        return $path !== null && is_file($path);
    }

    /** Una pieza publicada ya no necesita sus archivos intermedios: nada se regenera. */
    public function isPublished(): bool
    {
        return $this->status === PieceStatus::Publicada;
    }

    /** Carpeta de archivos intermedios de esta pieza (ver docs/ARQUITECTURA.md). */
    public function runDir(): string
    {
        return storage_path("app/private/runs/{$this->source->code}/{$this->uid}");
    }
}
