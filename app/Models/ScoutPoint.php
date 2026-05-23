<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

class ScoutPoint extends Model
{
    protected $fillable = [
        'tenant_id',
        'scout_session_id',
        'user_id',
        'previous_point_id',
        'latitude',
        'longitude',
        'accuracy',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy' => 'decimal:2',
            'captured_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($point) {
            if (empty($point->tenant_id) && auth()->check()) {
                $point->tenant_id = auth()->user()->tenant_id;
            }
        });
    }

    public function session()
    {
        return $this->belongsTo(ScoutSession::class, 'scout_session_id');
    }

    public function previousPoint()
    {
        return $this->belongsTo(ScoutPoint::class, 'previous_point_id');
    }
}
