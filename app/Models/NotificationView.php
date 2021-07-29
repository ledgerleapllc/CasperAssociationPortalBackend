<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class NotificationView extends Model
{
    use HasFactory;
    protected $table = 'notification_view';

    public function perk() {
		return $this->belongsTo('App\Models\Notification', 'notification_id', 'id');
	}

	public function user() {
		return $this->belongsTo('App\Models\User', 'user_id', 'id');
	}
}
