<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Utensil;

class UtensilController extends Controller
{
    function getAll(){
        return Utensil::all();
      }
}
