<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactRecipient extends Model
{
    use HasFactory;
    protected $table = 'contact_recipient';
    protected $guarded = [];
}
