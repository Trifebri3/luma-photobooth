<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoAccessToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'photo_id',
        'visitor_id',
        'token',
        'views_count',
        'downloads_count',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'views_count' => 'integer',
            'downloads_count' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}

