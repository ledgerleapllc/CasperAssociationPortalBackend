<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discussion extends Model
{
    use HasFactory;
    protected $guarded = []; 
    protected $hidden = ['created_at', 'updated_at'];
    protected $with = ['user'];

    public function commentsList() {
        return $this->hasMany('App\Models\DiscussionComment'); 
    }

    public function user() {
        return $this->belongsTo('App\Models\User');
    }

}
