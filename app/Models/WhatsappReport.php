<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender',
        'raw_message',
        'report_type',
        'parsed_payload',
        'confidence',
        'status',
        'error_notes',
        'target_type',
        'target_id',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'parsed_payload' => 'array',
            'approved_at' => 'datetime',
        ];
    }
}
