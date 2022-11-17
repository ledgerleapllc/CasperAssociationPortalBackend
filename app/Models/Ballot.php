<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ballot extends Model
{
    use HasFactory;

    protected $table = 'ballot';
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo('App\Models\User',  'user_id', 'id');
    }

    public function files()
    {
        return $this->hasMany('App\Models\BallotFile', 'ballot_id');
    }

    public function vote()
    {
        return $this->hasOne('App\Models\Vote', 'ballot_id');
    }

    public function voteResults()
    {
        return $this->hasMany('App\Models\VoteResult', 'ballot_id');
    }
}
