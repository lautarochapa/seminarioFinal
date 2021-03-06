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