<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Supply;

class SupplyController extends Controller
{
    function getAll(){
        return Supply::all();
      }
}
