<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscussionPin extends Model
{
    use HasFactory;
    protected $guarded = []; 
    protected $hidden = ['created_at', 'updated_at'];
    protected $appends = ['total_pinned'];
    protected $with = ['discussion'];

    public function discussion() {
        return $this->belongsTo('App\Models\Discussion');
    }
    public function user() {
        return $this->belongsTo('App\Models\User');
    }

    public function getTotalPinnedAttribute()
    {
        return DiscussionPin::where('discussion_id', $this->discussion_id)->count();
    }
}
