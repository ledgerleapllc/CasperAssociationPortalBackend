<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

use Laravel\Passport\HasApiTokens;

use App\Models\Discussion;
use App\Models\DiscussionRemoveNew;

use Carbon\Carbon;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;
    const TYPE_INDIVIDUAL = 'individual';
    const TYPE_ENTITY = 'entity';

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
        'banned',
        'letter_verified_at',
        'letter_rejected_at',
        'avatar',
        'average_peers',
        'validator_fee',
        'cspr_delegated',
        'cspr_self_staked',
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
        'signed_file_url',
        'pinned',
        'new_threads',
        'letter_file_url',
        'avatar_url',
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
        'permissions' => 'array'
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

    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getLetterFileUrlAttribute()
    {
        if(!$this->letter_file) {
            return null;
        }
        // $url = Storage::disk('local')->url($this->letter_file);
        // return asset($url);
        return $this->letter_file;
    }

    public function getAvatarUrlAttribute()
    {
        if(!$this->avatar) {
            return null;
        }
        $url = '';
        if (strpos($this->avatar, 'http') !== false) {
            $url = $this->avatar;
        } else {
            $url = Storage::disk('local')->url($this->avatar);
        }
        return $url;
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

    public function pinnedDiscussionsList() {
        return $this->hasMany('App\Models\DiscussionPin');
    }

    public function myDiscussionsList() {
        return $this->hasMany('App\Models\Discussion');
    }

    public function getPinnedAttribute() {
        return $this->pinnedDiscussionsList()->count();
    }

    public function permissions() {
        return $this->hasMany('App\Models\Permission', 'user_id');
    }

    public function ipHistories() {
        return $this->hasMany('App\Models\IpHistory', 'user_id');
    }

    public function metric() {
        return $this->hasOne('App\Models\Metric', 'user_id');
    }

    public function nodeInfo() {
        return $this->hasOne('App\Models\NodeInfo', 'node_address', 'public_address_node');
    }

    public function getNewThreadsAttribute() {
        $removedNews = DiscussionRemoveNew::where(['user_id' => $this->id])->pluck('discussion_id');
        $count = Discussion::whereNotIn('id', $removedNews)
                ->whereDate('created_at', '>',  Carbon::now()->subDays(3))
                ->count();
        
        return $count;
    }

    public function documentFiles() {
        return $this->hasMany('App\Models\DocumentFile');
    }

}
