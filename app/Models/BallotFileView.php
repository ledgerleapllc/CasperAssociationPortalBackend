<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BallotFileView extends Model
{
    use HasFactory;

    protected $table = 'ballot_file_view';
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo('App\Models\User',  'user_id', 'id');
    }
}
