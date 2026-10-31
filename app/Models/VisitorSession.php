<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VisitorSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'visitor_id',
        'event_device_id',
        'frame_template_id',
        'session_number',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'session_number' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(EventDevice::class, 'event_device_id');
    }

    public function frameTemplate(): BelongsTo
    {
        return $this->belongsTo(FrameTemplate::class, 'frame_template_id');
    }

    public function captures(): HasMany
    {
        return $this->hasMany(PhotoCapture::class)->orderBy('slot_number');
    }

    public function photo(): HasOne
    {
        return $this->hasOne(Photo::class);
    }

    public function whatsappLogs(): HasMany
    {
        return $this->hasMany(WhatsAppLog::class);
    }
}

