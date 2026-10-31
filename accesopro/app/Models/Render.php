<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Render extends Model
{
    protected $guarded = [];

    protected $casts = ['duration' => 'float'];

    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }
}
