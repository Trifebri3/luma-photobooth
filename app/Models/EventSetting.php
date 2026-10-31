<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'max_sessions_per_visitor',
        'photos_per_session',
        'countdown_seconds',
        'max_retakes',
        'is_unlimited_session',
        'require_email',
        'require_whatsapp',
        'enable_whatsapp_delivery',
        'token_expiry_days',
        'theme_color',
        'watermark_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_unlimited_session' => 'boolean',
            'require_email' => 'boolean',
            'require_whatsapp' => 'boolean',
            'enable_whatsapp_delivery' => 'boolean',
            'watermark_enabled' => 'boolean',
            'max_sessions_per_visitor' => 'integer',
            'photos_per_session' => 'integer',
            'countdown_seconds' => 'integer',
            'max_retakes' => 'integer',
            'token_expiry_days' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

