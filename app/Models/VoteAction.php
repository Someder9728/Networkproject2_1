<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoteAction extends Model
{
    protected $table = 'votes_and_actions';

    protected $primaryKey = 'vote_id';

    protected $fillable = [
        'rooms_room_id',
        'phase_number',
        'phase_type',
        'action_type',
        'players_voter_id',
        'players_target_id',
        'ballot_number',
    ];
}