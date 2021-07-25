<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerkResult extends Model
{
    use HasFactory;
    protected $table = 'perk_result';
    protected $guarded = [];

    public function perk() {
		return $this->belongsTo('App\Models\Perk', 'perk_id', 'id');
	}

	public function user() {
		return $this->belongsTo('App\Models\User', 'user_id', 'id');
	}
}
