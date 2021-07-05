<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscussionRemoveNew extends Model
{
    use HasFactory;
    protected $guarded = []; 
    protected $hidden = ['created_at', 'updated_at'];
    protected $with = ['discussion'];

    public function discussion() {
        return $this->belongsTo('App\Models\Discussion');
    }
}
