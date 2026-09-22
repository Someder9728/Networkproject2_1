<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Player extends Model
{
    protected $primaryKey = 'player_id';

    protected $fillable = [
        'player_name',
        'player_uuid',
        'is_host',
        'role',
        'is_alive',
        'is_connected',
        'rooms_room_id',
        'has_left',
    ];

    protected $hidden = [
        'role',
    ];

    protected function casts(): array
    {
        return [
            'is_host' => 'boolean',
            'is_alive' => 'boolean',
            'is_connected' => 'boolean',
            'has_left' => 'boolean',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(
            Room::class,
            'rooms_room_id',
            'room_id'
        );
    }
}