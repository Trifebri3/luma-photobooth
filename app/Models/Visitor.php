<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'uuid',
        'name',
        'phone_number',
        'email',
        'sessions_used',
        'consent_agreed_at',
    ];

    protected function casts(): array
    {
        return [
            'sessions_used' => 'integer',
            'consent_agreed_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(VisitorSession::class);
    }
}

