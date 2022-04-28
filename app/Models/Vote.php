<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    use HasFactory;
    protected $table = 'vote';
    protected $guarded = [];

    public function ballot() {
        return $this->hasOne('App\Models\Ballot', 'ballot_id', 'id');	
    }
  
    public function voteResults() {
        return $this->hasMany('App\Models\VoteResult', 'vote_id', 'id');
    }
}
