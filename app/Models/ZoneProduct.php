<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZoneProduct extends Model
{
    public $timestamps = false;

    protected $fillable = ['zone_id', 'name', 'position'];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
