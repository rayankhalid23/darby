<?php

namespace App\Models\Shared;

use App\Models\Parent\Child;
use App\Models\Parent\School;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteStop extends Model
{
    protected $table = 'route_stops';

    const TYPE_HOME   = 'home';
    const TYPE_SCHOOL = 'school';

    protected $fillable = [
        'route_id',
        'stop_type',
        'child_id',
        'school_id',
        'lat',
        'lng',
        'label',
        'sequence_order',
    ];

    protected $casts = [
        'lat'            => 'float',
        'lng'            => 'float',
        'sequence_order' => 'integer',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class, 'child_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }
}
