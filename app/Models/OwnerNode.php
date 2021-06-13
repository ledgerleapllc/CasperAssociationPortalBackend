<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OwnerNode extends Model
{
    use HasFactory;
    protected $table = 'owner_node';
    protected $guarded = [];  
}
