<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $guarded = [];

    protected $casts = ['reasons' => 'array', 'checks' => 'array'];

    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }
}
