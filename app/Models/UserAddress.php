<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model
{
    use HasFactory;
    protected $table = 'user_addresses';

    public function user() {
        return $this->belongsTo('App\Models\User', 'user_id', 'id');
    }
}
