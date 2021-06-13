<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;
    const TYPE_INDIVIDUAL = 'Individual';
    const TYPE_ENTITY = 'Entity';
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_BAN = 'denied';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'pseudonym',
        'telegram',
        'last_login_at',
        'email_verified_at',
        'type',
        'entity_name',
        'entity_type',
        'entity_register_number',
        'entity_register_country',
        'entity_tax',
        'signature_request_id',
        'public_address_node',
        'node_verified_at',
        'kyc_verified_at',
        'status',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'deleted_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'node_verified_at' => 'datetime',
    ];
    
    public function profile() {
        return $this->hasOne('App\Profile', 'user_id');
    }
}
