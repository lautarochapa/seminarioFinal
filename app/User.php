<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use Notifiable, SoftDeletes;

    public function sendPasswordResetNotification($token)
    {
        app(\App\Services\TransactionalMailService::class)->send(
            $this, new \Illuminate\Auth\Notifications\ResetPassword($token), 'password_reset'
        );
    }

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
        return $this->belongsToMany(Role::class, 'user_roles')->withPivot('created_at')
            ->where('roles.status', 'active')
            ->whereNotIn('roles.code', \App\Services\Auth\RolePolicy::RETIRED);
    }

    public function permissions()
    {
        return Permission::query()
            ->select('permissions.*')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->join('user_roles', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('roles.status', 'active')
            ->whereNotIn('roles.code', \App\Services\Auth\RolePolicy::RETIRED)
            ->where('permissions.status', 'active')
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

    public function apiTokens()
    {
        return $this->hasMany(ApiToken::class);
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

    public function ownedRecipes()
    {
        return $this->hasMany(Recipe::class, 'owner_user_id');
    }

    public function recipeFavorites()
    {
        return $this->hasMany(RecipeFavorite::class);
    }

    public function favoriteRecipes()
    {
        return $this->belongsToMany(Recipe::class, 'recipe_favorites')
            ->withPivot(['created_at']);
    }

    public function recipeCookLogs()
    {
        return $this->hasMany(RecipeCookLog::class);
    }

    public function reviewedImportedRecipeCandidates()
    {
        return $this->hasMany(ImportedRecipeCandidate::class, 'reviewed_by');
    }

    public function createdMealPlans()
    {
        return $this->hasMany(MealPlan::class, 'created_by');
    }

    public function mealPlanItemPortions()
    {
        return $this->hasMany(MealPlanItemPortion::class);
    }

    public function mealPlanPreferences()
    {
        return $this->hasMany(MealPlanPreference::class);
    }

    public function mealConsumptionLogs()
    {
        return $this->hasMany(MealConsumptionLog::class);
    }

    public function createdShoppingLists()
    {
        return $this->hasMany(ShoppingList::class, 'created_by');
    }

    public function shoppingSessions()
    {
        return $this->hasMany(ShoppingSession::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function productPreferences()
    {
        return $this->hasMany(ProductPreference::class);
    }

    public function supplements()
    {
        return $this->hasMany(UserSupplement::class);
    }

    public function supplementLogs()
    {
        return $this->hasMany(SupplementLog::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function notificationPreferences()
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function reportSnapshots()
    {
        return $this->hasMany(ReportSnapshot::class);
    }

    public function reportExports()
    {
        return $this->hasMany(ReportExport::class);
    }

    public function createdThesisDocuments()
    {
        return $this->hasMany(ThesisDocument::class, 'created_by');
    }

    public function updatedThesisDocuments()
    {
        return $this->hasMany(ThesisDocument::class, 'updated_by');
    }

    public function thesisComments()
    {
        return $this->hasMany(ThesisComment::class);
    }

    public function demoScenarios()
    {
        return $this->hasMany(DemoScenario::class, 'demo_user_id');
    }

    public function hasRole($code)
    {
        return in_array($code, $this->authorizationValues('roles', function () {
            return $this->roles()->pluck('roles.code')->all();
        }), true);
    }

    // Request attributes keep permissions isolated from other users and subsequent requests.
    private function authorizationValues($kind, callable $resolve)
    {
        if (!$this->exists) {
            return [];
        }
        $request = app('request');
        $key = 'ccc.authorization.'.$this->getKey().'.'.$kind;
        if (!$request->attributes->has($key)) {
            $request->attributes->set($key, $resolve());
        }
        return $request->attributes->get($key);
    }

    /**
     * Asigna el rol por defecto ('user') que habilita las pantallas de usuario final.
     * Es la unica fuente de verdad para "que rol recibe un usuario comun al crearse";
     * la usan el registro por API, el registro web legacy y el seeder de demo.
     * Idempotente: no duplica la asignacion.
     */
    public function assignDefaultRole()
    {
        $role = Role::where('code', 'user')->where('status', 'active')->first();

        if ($role && ! $this->roles()->where('roles.id', $role->id)->exists()) {
            $this->roles()->attach($role->id, ['created_at' => now()]);
        }
    }

    public function hasPermission($code)
    {
        if ($this->status !== 'active' || $this->trashed() || \App\Services\Auth\RolePolicy::retiredPermission($code)) {
            return false;
        }
        $codes = $this->authorizationValues('permissions', function () {
            return $this->hasRole('super_admin')
                ? Permission::where('status', 'active')->pluck('code')->all()
                : $this->permissions()->pluck('permissions.code')->all();
        });
        return in_array($code, $codes, true);
    }

    public function canUseMobile(): bool
    {
        $roles = $this->roles()->pluck('code');
        return $this->status === 'active' && !$this->trashed()
            && $roles->contains('user') && $roles->every(function ($code) { return $code === 'user'; });
    }

}
