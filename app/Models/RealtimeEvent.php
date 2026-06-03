<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RealtimeEvent extends Model
{
    public $timestamps = false;

    protected $table = 'realtime_events';

    protected $fillable = [
        'event',
        'model_type',
        'model_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
