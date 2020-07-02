<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Step;

class StepController extends Controller
{
    function getAll(){
        return Step::all();
      }
}
