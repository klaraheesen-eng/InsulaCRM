<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OfficeLookupRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'lead_id',
        'requested_by',
        'token',
        'expires_at',
        'completed_at',
        'completed_payload',
    ];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'completed_payload' => 'array',
        ];
    }

    public static function createForLead(Lead $lead, int $requestedBy, int $hours = 72): self
    {
        self::where('lead_id', $lead->id)
            ->whereNull('completed_at')
            ->where('expires_at', '>', now())
            ->update([
                'completed_at' => now(),
                'completed_payload' => ['invalidated_reason' => 'new_lookup_request'],
            ]);

        return self::create([
            'tenant_id' => $lead->tenant_id,
            'lead_id' => $lead->id,
            'requested_by' => $requestedBy,
            'token' => Str::random(64),
            'expires_at' => now()->addHours($hours),
        ]);
    }

    public function isUsable(): bool
    {
        return $this->completed_at === null && $this->expires_at?->isFuture();
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class)->withoutGlobalScopes();
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by')->withoutGlobalScopes();
    }
}
