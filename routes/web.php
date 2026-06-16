<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


Route::get('/map2','AddressController@index');
Route::get('/map', function() { return view('map'); });



/* Implementacion Pipelines */

Route::get('/productos_index','ProductController@index');


//Getters

Route::get('/categorias','CategoryController@getAll');
Route::get('/insumos','SupplyController@getAll');
Route::get('/productos','ProductController@getAll');
Route::get('/marcas','BrandController@getAll');




Route::get('/a','CategoryController@getAll2');

//Getters con logica



Route::get('/recetas','RecipeController@getAll');
Route::get('/utensillos','UtensilController@getAll');
Route::get('/pasos','StepController@getAll');
Route::get('/dietas','DietController@getAll');
Route::get('/agendas','AgendaController@getAll');




Route::get('/redirect', 'SocialAuthGoogleController@redirect');
Route::get('/callback', 'SocialAuthGoogleController@callback');

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');

Route::get('/app', 'AppScreenController@dashboard')->name('app.dashboard');
Route::get('/app/{screen}', 'AppScreenController@index')->name('app.screen');

Route::get('/web', 'UserWebScreenController@dashboard')->name('web.dashboard');
Route::get('/web/{screen}', 'UserWebScreenController@index')->name('web.screen');



Route::group(['middleware' => 'App\Http\Middleware\ComensalMiddleware'], function()
{
    Route::get('/comensal', function() { return view('profiles/comensal'); });
});

Route::group(['middleware' => 'App\Http\Middleware\AdminMiddleware'], function()
{
    Route::get('/admin', function() { return view('profiles/admin'); });
    Route::get('/categories', function() { return view('profiles/admin/categories'); });
    Route::get('/products', function() { return view('profiles/admin/products'); });
    Route::get('/utensils', function() { return view('profiles/admin/utensils'); });


    Route::get('products/{id}', 'ProductController@viewProduct');

    //vistas
    Route::get('/api/categories','CategoryController@getAllWithSupplies');
    Route::get('/api/products','ProductController@getAll');
    Route::get('/api/productsNames','ProductController@getProductsNames');
    


    Route::get('/api/selectedSupplies','SupplyController@getSelected');
    Route::get('/api/selectedBrands','BrandController@getSelected');

    Route::get('/api/supplies','SupplyController@getAll');
    Route::get('/api/brands','BrandController@getAll');
    Route::get('/api/products/bySupply/{id}','ProductController@getBySupply');
    Route::get('/api/products/{id}','ProductController@getOne');


    Route::get('/api/utensils','UtensilController@getAll');
});

Route::group(['middleware' => 'App\Http\Middleware\SuperadminMiddleware'], function()
{
    //vistas
    Route::get('/superadmin', function() { return view('profiles/superadmin'); });
    Route::get('/users', function() { return view('profiles/superadmin/users'); });

    //apis
    Route::get('/api/users','UserController@getAll');
});


Route::group(['middleware' => 'App\Http\Middleware\SomelierMiddleware'], function()
{
    Route::get('/somelier', function() { return view('profiles/somelier'); });
    Route::get('/drinks', function() { return view('profiles/somelier/drinks'); });
    Route::get('/maridajes', function() { return view('profiles/somelier/maridajes'); });
});

Route::group(['middleware' => 'App\Http\Middleware\ChefMiddleware'], function()
{
    Route::get('/chef', function() { return view('profiles/chef'); });
    Route::get('/recipes', function() { return view('profiles/chef/recipes'); });

    
});


Route::group(['middleware' => 'App\Http\Middleware\NutricionistaMiddleware'], function()
{
    Route::get('/nutritionist', function(){ return view('profiles/nutricionista'); });
    Route::get('/diets', function() { return view('profiles/nutritionist/diets'); });
    Route::get('/patietns', function() { return view('profiles/nutritionist/patietns'); });
});




Route::get('/vue/{vue_capture?}', function () {
    return view('vue.index');
   })->where('vue_capture', '[\/\w\.-]*');

Route::prefix('api/v1/auth')->middleware(['trace_id'])->group(function () {
    Route::post('register', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'register'])
        ->middleware('throttle:60,1');
    Route::post('login', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'login'])
        ->middleware('throttle:10,1');
    Route::post('google', [\App\Http\Controllers\Api\V1\Auth\GoogleAuthController::class, 'authenticate'])
        ->middleware('throttle:10,1');
    Route::post('forgot-password', [\App\Http\Controllers\Api\V1\Auth\PasswordController::class, 'forgotPassword'])
        ->middleware('throttle:5,1');
    Route::post('reset-password', [\App\Http\Controllers\Api\V1\Auth\PasswordController::class, 'resetPassword'])
        ->middleware('throttle:60,1');

    Route::middleware('auth')->group(function () {
        Route::post('logout', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'logout']);
        Route::get('me', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'me']);
        Route::patch('me', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'updateProfile']);
    });
});

Route::prefix('api/v1/admin')->middleware(['trace_id', 'auth'])->group(function () {
    // Users
    Route::get('users', [\App\Http\Controllers\Api\V1\Admin\UserAdminController::class, 'index'])
        ->middleware('permission:security.users.read');
    Route::get('users/{id}/audit', [\App\Http\Controllers\Api\V1\Admin\UserAdminController::class, 'audit'])
        ->middleware('permission:audit.read');
    Route::get('users/{id}', [\App\Http\Controllers\Api\V1\Admin\UserAdminController::class, 'show'])
        ->middleware('permission:security.users.read');
    Route::post('users', [\App\Http\Controllers\Api\V1\Admin\UserAdminController::class, 'store'])
        ->middleware('permission:security.users.write');
    Route::patch('users/{id}/restore', [\App\Http\Controllers\Api\V1\Admin\UserAdminController::class, 'restore'])
        ->middleware('permission:security.users.write');
    Route::patch('users/{id}', [\App\Http\Controllers\Api\V1\Admin\UserAdminController::class, 'update'])
        ->middleware('permission:security.users.write');
    Route::delete('users/{id}', [\App\Http\Controllers\Api\V1\Admin\UserAdminController::class, 'destroy'])
        ->middleware('permission:security.users.write');

    // User-role assignments
    Route::post('users/{userId}/roles', [\App\Http\Controllers\Api\V1\Admin\UserRoleController::class, 'store'])
        ->middleware('permission:security.users.write');
    Route::delete('users/{userId}/roles/{roleId}', [\App\Http\Controllers\Api\V1\Admin\UserRoleController::class, 'destroy'])
        ->middleware('permission:security.users.write');

    // Roles
    Route::get('roles', [\App\Http\Controllers\Api\V1\Admin\RoleAdminController::class, 'index'])
        ->middleware('permission:security.roles.read');
    Route::get('roles/{id}/audit', [\App\Http\Controllers\Api\V1\Admin\RoleAdminController::class, 'audit'])
        ->middleware('permission:audit.read');
    Route::get('roles/{id}', [\App\Http\Controllers\Api\V1\Admin\RoleAdminController::class, 'show'])
        ->middleware('permission:security.roles.read');
    Route::post('roles', [\App\Http\Controllers\Api\V1\Admin\RoleAdminController::class, 'store'])
        ->middleware('permission:security.roles.write');
    Route::patch('roles/{id}/restore', [\App\Http\Controllers\Api\V1\Admin\RoleAdminController::class, 'restore'])
        ->middleware('permission:security.roles.write');
    Route::patch('roles/{id}', [\App\Http\Controllers\Api\V1\Admin\RoleAdminController::class, 'update'])
        ->middleware('permission:security.roles.write');
    Route::delete('roles/{id}', [\App\Http\Controllers\Api\V1\Admin\RoleAdminController::class, 'destroy'])
        ->middleware('permission:security.roles.write');

    // Role-permission assignments
    Route::post('roles/{roleId}/permissions', [\App\Http\Controllers\Api\V1\Admin\RolePermissionController::class, 'store'])
        ->middleware('permission:security.roles.write');
    Route::delete('roles/{roleId}/permissions/{permissionId}', [\App\Http\Controllers\Api\V1\Admin\RolePermissionController::class, 'destroy'])
        ->middleware('permission:security.roles.write');

    // Permissions
    Route::get('permissions', [\App\Http\Controllers\Api\V1\Admin\PermissionAdminController::class, 'index'])
        ->middleware('permission:security.permissions.read');

    // Audit logs
    Route::get('audit-logs', [\App\Http\Controllers\Api\V1\Admin\AuditLogController::class, 'index'])
        ->middleware('permission:audit.read');

    // Login logs
    Route::get('login-logs', [\App\Http\Controllers\Api\V1\Admin\LoginLogController::class, 'index'])
        ->middleware('permission:audit.read');

    // Generic resource audit (must be after specific routes)
    Route::get('{resource}/{id}/audit', [\App\Http\Controllers\Api\V1\Admin\AuditLogController::class, 'forResource'])
        ->middleware('permission:audit.read');
});

Route::prefix('api/v1/family-groups')->middleware(['trace_id', 'auth'])->group(function () {
    // Accept invitation (must be before {id} patterns to avoid conflict)
    Route::post('invitations/{invitationId}/accept', [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupInvitationController::class, 'accept']);

    // Family group CRUD
    Route::get('',      [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupController::class, 'index']);
    Route::post('',     [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupController::class, 'store']);
    Route::get('{id}',  [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupController::class, 'show']);
    Route::patch('{id}',[\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupController::class, 'update']);
    Route::delete('{id}',[\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupController::class, 'destroy']);

    // Members
    Route::get('{id}/members',                        [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupMemberController::class, 'index']);
    Route::post('{id}/members',                       [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupMemberController::class, 'store']);
    Route::patch('{id}/members/{memberId}',           [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupMemberController::class, 'update']);
    Route::delete('{id}/members/{memberId}',          [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupMemberController::class, 'destroy']);

    // Invitations
    Route::post('{id}/invitations', [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupInvitationController::class, 'store']);

    // Preferences
    Route::get('{id}/preferences',   [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupPreferenceController::class, 'show']);
    Route::patch('{id}/preferences', [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupPreferenceController::class, 'update']);
});
