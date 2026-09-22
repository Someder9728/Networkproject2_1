<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $primaryKey = 'room_id';

    protected $hidden = [
        'game_snapshot',
    ];

    protected $fillable = [
        'room_code',
        'room_status',
        'room_phase_end_time',
        'difficulty',
        'game_uuid',
        'game_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'room_phase_end_time' => 'datetime',
            'game_snapshot' => 'array',
        ];
    }

    public function players(): HasMany
    {
        return $this->hasMany(
            Player::class,
            'rooms_room_id',
            'room_id'
        );
    }
}