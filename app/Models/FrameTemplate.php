<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FrameTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'canvas_width',
        'canvas_height',
        'orientation',
        'overlay_image_path',
        'preview_image_path',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'canvas_width' => 'integer',
            'canvas_height' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(FrameSlot::class)->orderBy('slot_number');
    }
}

