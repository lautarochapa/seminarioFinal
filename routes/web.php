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


//Getters

Route::get('/categorias','CategoryController@getAll');
Route::get('/insumos','SupplyController@getAll');
Route::get('/productos','ProductController@getAll');
Route::get('/marcas','BrandController@getAll');


Route::get('/recetas','RecipeController@getAll');
Route::get('/utensillos','UtensilController@getAll');
Route::get('/pasos','StepController@getAll');
Route::get('/dietas','DietController@getAll');
Route::get('/agendas','AgendaController@getAll');
Route::get('/grupos','GroupController@getAll');




Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');



Route::group(['middleware' => 'App\Http\Middleware\ComensalMiddleware'], function()
{
    Route::get('/comensal', function() { return view('profiles/comensal'); });
});

Route::group(['middleware' => 'App\Http\Middleware\AdminMiddleware'], function()
{
    Route::get('/admin', function() { return view('profiles/admin'); });
});

Route::group(['middleware' => 'App\Http\Middleware\SuperadminMiddleware'], function()
{
    Route::get('/superadmin', function() { return view('profiles/superadmin'); });
});


Route::group(['middleware' => 'App\Http\Middleware\SomelierMiddleware'], function()
{
    Route::get('/somelier', function() { return view('profiles/somelier'); });
});

Route::group(['middleware' => 'App\Http\Middleware\ChefMiddleware'], function()
{
    Route::get('/chef', function() { return view('profiles/chef'); });
});


Route::group(['middleware' => 'App\Http\Middleware\NutricionistaMiddleware'], function()
{
    Route::get('/nutricionista', function(){ return view('profiles/nutricionista'); });
});