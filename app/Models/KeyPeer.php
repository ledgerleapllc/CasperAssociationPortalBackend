<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeyPeer extends Model
{
    use HasFactory;

    protected $table = 'key_peers';
    protected $guarded = [];
}
