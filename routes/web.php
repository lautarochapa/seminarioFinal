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

Route::get('/productos','ProductController@getAll');
Route::get('/recetas','RecipeController@getAll');
Route::get('/utensillos','UtensilController@getAll');
Route::get('/pasos','StepController@getAll');
Route::get('/dietas','DietController@getAll');
Route::get('/agendas','AgendaController@getAll');
Route::get('/grupos','GroupController@getAll');
Route::get('/marcas','BrandController@getAll');
Route::get('/categorias','CategoryController@getAll');
Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');
