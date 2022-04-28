<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentFile extends Model
{
    use HasFactory;

    protected $table = 'document_file';
    protected $guarded = [];

    protected $appends = [
        'file_url',
    ];

    public function getFileUrlAttribute()
    {
        return $this->url;
    }
}
