<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoteResult extends Model
{
    use HasFactory;
    protected $table = 'vote_result';
    protected $guarded = [];

    public function ballot() {
		return $this->belongsTo('App\\Models\Ballot', 'id',  'ballot_id');
	}

	public function vote() {
		return $this->belongsTo('App\Models\Vote', 'id', 'vote_id');
	}

	public function user() {
		return $this->belongsTo('App\Models\User', 'user_id', 'id');
	}
}
