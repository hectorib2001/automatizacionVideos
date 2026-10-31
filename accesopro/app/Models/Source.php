<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    protected $guarded = [];

    protected $casts = ['meta' => 'array'];

    public function pieces(): HasMany
    {
        return $this->hasMany(Piece::class);
    }
}
