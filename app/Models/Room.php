<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed>|null $game_snapshot
 * @property string $game_mode
 * @property CarbonImmutable|null $start_countdown_at
 */
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
        'player_limit',
        'game_mode',
        'start_countdown_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'room_phase_end_time' => 'datetime',
            'game_snapshot' => 'array',
            'player_limit' => 'integer',
            'start_countdown_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<Player, $this>
     */
    public function players(): HasMany
    {
        return $this->hasMany(
            Player::class,
            'rooms_room_id',
            'room_id'
        );
    }
}
