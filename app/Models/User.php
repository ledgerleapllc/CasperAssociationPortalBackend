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

    const STATUS_INCOMPLETE = 'Incomplete';
    const STATUS_INTAKE = 'Intake';
    const STATUS_NEW = 'New';
    const STATUS_ACTIVE = 'Active';
    const STATUS_PROBATION = 'Probation';
    const STATUS_REVOKED = 'Revoked';

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
        'member_status',
        'message_content',
        'role',
        'signed_file',
        'hellosign_form',
        'letter_file',
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
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'full_name',
        'signed_file_url'
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

    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getSignedFileUrlAttribute()
    {
        return url('/') .   $this->signed_file;
    }

    public function profile()
    {
        return $this->hasOne('App\Models\Profile', 'user_id');
    }

    public function shuftipro() {
        return $this->hasOne('App\Models\Shuftipro', 'user_id');
    }

    public function shuftiproTemp() {
        return $this->hasOne('App\Models\ShuftiproTemp', 'user_id');
    }

    public function ownerNodes() {
        return $this->hasMany('App\Models\OwnerNode', 'user_id');
    }
}
