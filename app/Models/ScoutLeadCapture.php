<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

class ScoutLeadCapture extends Model
{
    protected $fillable = [
        'tenant_id',
        'scout_point_id',
        'lead_id',
        'user_id',
        'latitude',
        'longitude',
        'address',
        'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($capture) {
            if (empty($capture->tenant_id) && auth()->check()) {
                $capture->tenant_id = auth()->user()->tenant_id;
            }
        });
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function point()
    {
        return $this->belongsTo(ScoutPoint::class, 'scout_point_id');
    }
}
