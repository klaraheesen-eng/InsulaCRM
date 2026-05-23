<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

class ScoutSession extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($session) {
            if (empty($session->tenant_id) && auth()->check()) {
                $session->tenant_id = auth()->user()->tenant_id;
            }
        });
    }

    public function points()
    {
        return $this->hasMany(ScoutPoint::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
