<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScoutVoiceNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'scout_session_id',
        'user_id',
        'latitude',
        'longitude',
        'accuracy',
        'audio_path',
        'mime_type',
        'size',
        'duration_seconds',
        'captured_at',
        'transcribed_at',
        'transcript',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'accuracy' => 'decimal:2',
        'captured_at' => 'datetime',
        'transcribed_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(ScoutSession::class, 'scout_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
