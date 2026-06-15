<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'lastname',
        'username',
        'email',
        'password',
        'phone',
        'avatar_url',
        'status',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];


    public function profile()
    {
        return $this->belongsTo(Profile::class, 'nivel_acceso')->select(array('id', 'nombre'));;
    }

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class);
    }

    public function agendas()
    {
        return $this->belongsToMany(Agenda::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withPivot('created_at');
    }

    public function permissions()
    {
        return Permission::query()
            ->select('permissions.*')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->join('user_roles', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $this->id)
            ->distinct();
    }

    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function consents()
    {
        return $this->hasMany(UserConsent::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function loginLogs()
    {
        return $this->hasMany(LoginLog::class);
    }

    public function hasRole($code)
    {
        return $this->roles()->where('code', $code)->exists();
    }

    public function hasPermission($code)
    {
        return $this->permissions()->where('permissions.code', $code)->exists();
    }

}
