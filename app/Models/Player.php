<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, string>|null $avatar Cosmetic settings stored as JSON.
 */
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
        'is_ready',
        'avatar',
    ];

    protected $hidden = [
        'role',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_host' => 'boolean',
            'is_alive' => 'boolean',
            'is_connected' => 'boolean',
            'has_left' => 'boolean',
            'is_ready' => 'boolean',
            'avatar' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(
            Room::class,
            'rooms_room_id',
            'room_id'
        );
    }
}
