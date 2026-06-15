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

    public function ownedFamilyGroups()
    {
        return $this->hasMany(FamilyGroup::class, 'owner_user_id');
    }

    public function familyGroupMemberships()
    {
        return $this->hasMany(FamilyGroupMember::class);
    }

    public function familyGroups()
    {
        return $this->belongsToMany(FamilyGroup::class, 'family_group_members')
            ->withPivot(['role_in_group', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function sentFamilyGroupInvitations()
    {
        return $this->hasMany(FamilyGroupInvitation::class, 'invited_by');
    }

    public function receivedFamilyGroupInvitations()
    {
        return $this->hasMany(FamilyGroupInvitation::class, 'invited_user_id');
    }

    public function userProfile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function bodyMeasurements()
    {
        return $this->hasMany(BodyMeasurement::class);
    }

    public function objectives()
    {
        return $this->belongsToMany(Objective::class, 'user_objectives')
            ->withPivot(['priority', 'target_value', 'target_unit', 'target_date', 'is_active', 'notes'])
            ->withTimestamps();
    }

    public function userObjectives()
    {
        return $this->hasMany(UserObjective::class);
    }

    public function dietaryRestrictions()
    {
        return $this->belongsToMany(DietaryRestriction::class, 'user_dietary_restrictions')
            ->withPivot(['notes', 'created_at']);
    }

    public function healthConditions()
    {
        return $this->belongsToMany(HealthCondition::class, 'user_health_conditions')
            ->withPivot(['notes', 'created_at']);
    }

    public function allergies()
    {
        return $this->belongsToMany(Allergy::class, 'user_allergies')
            ->withPivot(['severity', 'notes', 'created_at']);
    }

    public function nutritionTarget()
    {
        return $this->hasOne(UserNutritionTarget::class);
    }

    public function prioritySetting()
    {
        return $this->hasOne(UserPrioritySetting::class);
    }

    public function professionalLinks()
    {
        return $this->hasMany(ProfessionalUserLink::class);
    }

    public function linkedUsersAsProfessional()
    {
        return $this->hasMany(ProfessionalUserLink::class, 'professional_user_id');
    }

    public function paymentMethods()
    {
        return $this->belongsToMany(PaymentMethod::class, 'user_payment_methods')
            ->withPivot(['alias', 'status'])
            ->withTimestamps();
    }

    public function productReports()
    {
        return $this->hasMany(ProductReport::class);
    }

    public function resolvedProductReports()
    {
        return $this->hasMany(ProductReport::class, 'resolved_by');
    }

    public function scrapingJobs()
    {
        return $this->hasMany(ScrapingJob::class, 'requested_by');
    }

    public function reviewedScrapedProductCandidates()
    {
        return $this->hasMany(ScrapedProductCandidate::class, 'reviewed_by');
    }

    public function priceRefreshRequests()
    {
        return $this->hasMany(PriceRefreshRequest::class);
    }

    public function createdStockMovements()
    {
        return $this->hasMany(StockMovement::class, 'created_by');
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
