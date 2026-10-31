<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostEvent extends Model
{
    protected $guarded = [];

    protected $casts = ['meta' => 'array', 'units' => 'float', 'cost_eur' => 'float'];

    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }
}
