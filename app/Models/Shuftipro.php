<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Shuftipro extends Model
{
    use HasFactory;
    protected $table = 'shuftipro';
    protected $guarded = []; 
    protected $casts = [
        'data' => 'array'
    ];

    public function getDocumentProofUrlAttribute()
    {
        if(!$this->document_proof) {
            return null;
        }
        $url = Storage::disk('local')->url($this->document_proof);
        return asset($url);
    }

    public function getAddressProofUrlAttribute()
    {
        if(!$this->address_proof) {
            return null;
        }
        $url = Storage::disk('local')->url($this->address_proof);
        return asset($url);
    }
}
