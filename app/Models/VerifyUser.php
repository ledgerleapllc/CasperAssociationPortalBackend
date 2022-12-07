<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerifyUser extends Model
{
    use HasFactory;

    const TOKEN_LIFETIME      = 300;
    const TYPE_VERIFY_EMAIL   = 'verify_email';
    const TYPE_RESET_PASSWORD = 'reset_password';
    const TYPE_CANCEL_EMAIL   = 'cancel_email';
    const TYPE_CONFIRM_EMAIL  = 'confirm_email';
    const TYPE_LOGIN_TWO_FA   = 'login_twoFA';
    const TYPE_INVITE_ADMIN   = 'invite_admin';

    public $timestamps        = false;
    public $primaryKey        = 'email';
    public $keyType           = 'string';

    protected $table          = 'verify_user';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'email', 'type', 'code', 'created_at'
    ];
}
