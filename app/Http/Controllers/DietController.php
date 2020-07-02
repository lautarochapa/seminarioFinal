<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Diet;

class DietController extends Controller
{
    function getAll(){
        return Diet::all();
      }
}
