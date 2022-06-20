<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Metric extends Model
{
    use HasFactory;
    protected $table = 'metric';
    protected $guarded = [];

	public function user() {
		return $this->belongsTo('App\Models\User', 'user_id', 'id');
	}
}
