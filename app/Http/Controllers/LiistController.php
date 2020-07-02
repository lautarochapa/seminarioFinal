<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Liist;

class LiistController extends Controller
{
    function getAll(){
        return Liist::all();
      }
}
