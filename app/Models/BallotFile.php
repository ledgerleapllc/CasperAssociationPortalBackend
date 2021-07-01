<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BallotFile extends Model
{
    use HasFactory;

    protected $table = 'ballot_file';
    protected $guarded = [];

    protected $appends = [
        'file_url',
    ];

    public function getFileUrlAttribute()
    {
        return asset($this->url);
    }
}
