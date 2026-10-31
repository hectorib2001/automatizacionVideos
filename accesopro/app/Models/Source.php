<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    public const RUN_QUEUED = 'en_cola';

    public const RUN_PROCESSING = 'procesando';

    public const RUN_DONE = 'completado';

    public const RUN_ERROR = 'error';

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'run_started_at' => 'datetime',
        'run_finished_at' => 'datetime',
    ];

    public function pieces(): HasMany
    {
        return $this->hasMany(Piece::class);
    }

    /** Fuente añadida desde la página "Fuentes" del panel (no desde materiales/). */
    public function isUploaded(): bool
    {
        return ($this->meta['origin'] ?? null) === 'panel';
    }

    /** Carpeta propia de la fuente para archivos subidos o descargados. */
    public function storageDir(): string
    {
        return storage_path("app/private/sources/{$this->type}/{$this->code}");
    }

    /**
     * Vídeo de la clase. Las clases añadidas desde el panel tienen su propia carpeta; la clase
     * original (procesada antes de existir el panel) sigue usando la ruta configurada.
     */
    public function classVideoPath(): string
    {
        return $this->meta['video_path'] ?? config('accesopro.class_video_path');
    }

    /** Texto extraído del PDF subido, o null si la fuente usa materiales/. */
    public function documentText(): ?string
    {
        return $this->document_text !== null && trim($this->document_text) !== '' ? $this->document_text : null;
    }

    public function isRunning(): bool
    {
        return in_array($this->run_status, [self::RUN_QUEUED, self::RUN_PROCESSING], true);
    }
}
