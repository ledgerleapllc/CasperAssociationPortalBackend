<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Perk extends Model
{
    use HasFactory;
    protected $table = 'perk';
    protected $guarded = [];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'image_url',
    ];

	public function user() {
		return $this->belongsTo('App\Models\User', 'user_id', 'id');
    }

    public function getImageUrlAttribute()
    {
        if(!$this->image) {
            return null;
        }
        $url = Storage::disk('local')->url($this->image);
        return asset($url);
    }
}
