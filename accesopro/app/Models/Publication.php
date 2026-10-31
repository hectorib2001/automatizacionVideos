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
}
