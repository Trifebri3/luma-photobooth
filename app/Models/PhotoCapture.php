<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoCapture extends Model
{
    use HasFactory;

    protected $fillable = [
        'visitor_session_id',
        'slot_number',
        'file_path',
        'width',
        'height',
        'retake_count',
    ];

    protected function casts(): array
    {
        return [
            'slot_number' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'retake_count' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(VisitorSession::class, 'visitor_session_id');
    }
}

