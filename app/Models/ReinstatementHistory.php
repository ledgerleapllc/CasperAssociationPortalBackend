<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReinstatementHistory extends Model
{
    use HasFactory;
    protected $table = 'reinstatement_history';

    public function user()
    {
        return $this->hasOne('App\Models\User', 'id', 'user_id');
    }
}
