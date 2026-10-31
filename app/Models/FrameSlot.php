<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrameSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'frame_template_id',
        'slot_number',
        'x_position',
        'y_position',
        'width',
        'height',
        'rotation_deg',
        'aspect_ratio',
    ];

    protected function casts(): array
    {
        return [
            'slot_number' => 'integer',
            'x_position' => 'integer',
            'y_position' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'rotation_deg' => 'float',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FrameTemplate::class, 'frame_template_id');
    }
}

