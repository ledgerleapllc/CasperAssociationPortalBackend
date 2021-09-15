<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Discussion extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $hidden = ['updated_at'];
    protected $appends = ['is_new', 'total_pinned'];
    protected $with = ['user'];

    public function commentsList()
    {
        return $this->hasMany('App\Models\DiscussionComment');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    public function getIsNewAttribute()
    {
        $user = auth()->user();
        $notRemoved = DiscussionRemoveNew::where([
            'user_id' => $user->id,
            'discussion_id' => $this->id
        ])->first() == null;
        $notOld = Carbon::now()->diffInDays(Carbon::parse($this->created_at)) < 3;
        return  $notOld && $notRemoved;
    }

    public function getTotalPinnedAttribute()
    {
        return DiscussionPin::where('discussion_id', $this->id)->count();
    }
}
