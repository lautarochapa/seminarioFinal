<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Recipe;

class Recipe_Controller extends Controller
{
    function getAll(){
        return Recipe::all();
      }
}